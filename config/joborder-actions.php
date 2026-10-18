<?php
// config/joborder-actions.php
// =====================================================
// Shared job order logic. Admin, Owner and Worker pages all call these
// functions so the rules (and the bugs) live in exactly ONE place.
//
// Every function returns [bool $ok, string $message].
// =====================================================

const JO_STEPS = ['received', 'stripping', 'coating', 'curing', 'ready'];
const JO_STEP_MESSAGES = [
  'received'  => 'Rims received',
  'stripping' => 'Stripping old coating',
  'coating'   => 'Powder coating applied',
  'curing'    => 'Curing in oven',
  'ready'     => 'Rims ready for pickup',
];
const JO_PAYMENT_METHODS = ['cash', 'gcash', 'other'];

/**
 * Write a job order log line WITH the person who did it.
 *  - The actor's name is always appended to the message, so accountability works
 *    even before the DB migration is applied.
 *  - If job_order_logs.performed_by exists (see sql/001_priority_fixes.sql) the
 *    user id is stored there too.
 */
function jo_log(PDO $pdo, int $joId, string $step, string $message, ?int $actorId, string $actorName = ''): void {
  if ($actorName !== '') $message .= ' — by ' . $actorName;
  try {
    $pdo->prepare("INSERT INTO job_order_logs (job_order_id, step, message, performed_by) VALUES (?,?,?,?)")
        ->execute([$joId, $step, $message, $actorId]);
  } catch (PDOException $e) {
    // 42S22 = unknown column (migration not applied yet) -> fall back to the old shape
    if ($e->getCode() !== '42S22') throw $e;
    $pdo->prepare("INSERT INTO job_order_logs (job_order_id, step, message) VALUES (?,?,?)")
        ->execute([$joId, $step, $message]);
  }
}

/** Next reference number like JO-2026-0007, counted per year and per prefix. */
function jo_next_reference(PDO $pdo, string $table, string $column, string $prefix): string {
  $like = $prefix . '-' . date('Y') . '-%';
  // $table/$column are hard-coded by the callers below, never user input.
  $stmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX($column,'-',-1) AS UNSIGNED)),0)+1 FROM $table WHERE $column LIKE ? FOR UPDATE");
  $stmt->execute([$like]);
  return $prefix . '-' . date('Y') . '-' . str_pad((string)$stmt->fetchColumn(), 4, '0', STR_PAD_LEFT);
}

/** Returns the user row if $userId is a real customer account, otherwise null. */
function jo_find_customer(PDO $pdo, $userId): ?array {
  if (empty($userId)) return null;
  $stmt = $pdo->prepare("SELECT id, full_name, contact_number FROM users WHERE id = ? AND role = 'customer'");
  $stmt->execute([(int)$userId]);
  return $stmt->fetch() ?: null;
}

/** CREATE a job order. $in is normally $_POST. */
function jo_create(PDO $pdo, array $in, ?int $actorId, string $actorName): array {
  $name    = trim($in['customer_name'] ?? '');
  $service = trim($in['service'] ?? '');
  if ($name === '' || $service === '') return [false, 'Customer name and service are required.'];

  $type = in_array($in['customer_type'] ?? '', ['walk-in', 'online'], true) ? $in['customer_type'] : 'walk-in';

  $pdo->beginTransaction();
  try {
    $userId  = null;
    $contact = trim($in['contact_number'] ?? '');
    $queueId = !empty($in['queue_id']) ? (int)$in['queue_id'] : null;

    // Linked queue entry: must exist, must not already have a job order,
    // and its customer is inherited (this is what links the JO to the customer's account).
    if ($queueId) {
      $q = $pdo->prepare("SELECT id, user_id, contact_number FROM queue WHERE id = ? FOR UPDATE");
      $q->execute([$queueId]);
      $queueRow = $q->fetch();
      if (!$queueRow) { $pdo->rollBack(); return [false, 'The selected queue entry no longer exists.']; }

      $dup = $pdo->prepare("SELECT jo_number FROM job_orders WHERE queue_id = ? LIMIT 1");
      $dup->execute([$queueId]);
      if ($existing = $dup->fetchColumn()) { $pdo->rollBack(); return [false, "That queue entry already has job order $existing."]; }

      if (!empty($queueRow['user_id'])) $userId = (int)$queueRow['user_id'];
      if ($contact === '' && !empty($queueRow['contact_number'])) $contact = $queueRow['contact_number'];
    }

    // Explicit registered-customer pick (only used if the queue did not already supply one)
    if (!$userId && ($cust = jo_find_customer($pdo, $in['user_id'] ?? null))) {
      $userId = (int)$cust['id'];
      $type = 'online';
    }
    // Registered customer but no phone typed -> use the one on their account
    if ($userId && $contact === '') {
      $c = jo_find_customer($pdo, $userId);
      if ($c && !empty($c['contact_number'])) $contact = $c['contact_number'];
    }
    if ($userId) $type = 'online';

    $joNum = jo_next_reference($pdo, 'job_orders', 'jo_number', 'JO');

    $est = trim($in['est_completion'] ?? '');
    $pdo->prepare("INSERT INTO job_orders (jo_number,customer_name,contact_number,customer_type,vehicle,service,rim_count,rim_size,color_finish,notes,current_step,status,est_completion,user_id,queue_id) VALUES (?,?,?,?,?,?,?,?,?,?,'received','in_progress',?,?,?)")
        ->execute([
          $joNum, $name, $contact, $type,
          trim($in['vehicle'] ?? ''), $service,
          trim($in['rim_count'] ?? ''), trim($in['rim_size'] ?? ''), trim($in['color_finish'] ?? ''),
          trim($in['notes'] ?? ''),
          $est !== '' ? $est : null,
          $userId, $queueId,
        ]);
    $joId = (int)$pdo->lastInsertId();

    jo_log($pdo, $joId, 'received', 'Job order created - rims received.', $actorId, $actorName);

    if ($queueId) {
      $pdo->prepare("UPDATE appointments SET status='in_progress' WHERE id=(SELECT appointment_id FROM queue WHERE id=?)")->execute([$queueId]);
    }
    $pdo->commit();
    return [true, "Job Order $joNum created successfully."];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('jo_create: ' . $e->getMessage());
    return [false, 'Could not create the job order. Please try again.'];
  }
}

/**
 * UPDATE the current step.
 *  - $onlyWorkerId: pass the worker's id to restrict them to jobs assigned to them.
 *  - Completed / cancelled jobs can NEVER be changed again.
 */
function jo_update_step(PDO $pdo, int $joId, string $step, ?int $actorId, string $actorName, ?int $onlyWorkerId = null): array {
  if (!in_array($step, JO_STEPS, true)) return [false, 'Invalid step.'];

  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare("SELECT id, status, worker_id FROM job_orders WHERE id = ? FOR UPDATE");
    $stmt->execute([$joId]);
    $jo = $stmt->fetch();

    if (!$jo) { $pdo->rollBack(); return [false, 'Job order not found.']; }
    if ($onlyWorkerId !== null && (int)$jo['worker_id'] !== $onlyWorkerId) {
      $pdo->rollBack(); return [false, 'You can only update steps on your assigned jobs.'];
    }
    if (!in_array($jo['status'], ['in_progress', 'ready'], true)) {
      $pdo->rollBack(); return [false, 'This job order is already ' . $jo['status'] . ' and can no longer be changed.'];
    }

    $newStatus = $step === 'ready' ? 'ready' : 'in_progress';
    $pdo->prepare("UPDATE job_orders SET current_step=?, status=? WHERE id=?")->execute([$step, $newStatus, $joId]);
    jo_log($pdo, $joId, $step, JO_STEP_MESSAGES[$step], $actorId, $actorName);

    if ($step === 'ready') {
      $pdo->prepare("UPDATE appointments SET status='completed' WHERE id=(SELECT appointment_id FROM queue WHERE id=(SELECT queue_id FROM job_orders WHERE id=?))")->execute([$joId]);
    } elseif ($jo['status'] === 'ready') {
      // Job moved back out of "ready" -> the appointment is not completed any more
      $pdo->prepare("UPDATE appointments SET status='in_progress' WHERE id=(SELECT appointment_id FROM queue WHERE id=(SELECT queue_id FROM job_orders WHERE id=?))")->execute([$joId]);
    }
    $pdo->commit();
    return [true, 'Step updated to: ' . ucfirst($step)];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('jo_update_step: ' . $e->getMessage());
    return [false, 'Could not update the step. Please try again.'];
  }
}

/** MARK COMPLETED + record payment. Only a job that is "ready" can be completed, once. */
function jo_complete(PDO $pdo, int $joId, $amount, string $method, ?int $actorId, string $actorName): array {
  if (!is_numeric($amount) || (float)$amount < 0 || (float)$amount > 9999999) {
    return [false, 'Please enter a valid amount.'];
  }
  $method = strtolower(trim($method));
  if (!in_array($method, JO_PAYMENT_METHODS, true)) return [false, 'Invalid payment method.'];

  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare("SELECT id, status FROM job_orders WHERE id = ? FOR UPDATE");
    $stmt->execute([$joId]);
    $jo = $stmt->fetch();

    if (!$jo) { $pdo->rollBack(); return [false, 'Job order not found.']; }
    if ($jo['status'] === 'completed') { $pdo->rollBack(); return [false, 'This job order is already completed.']; }
    if ($jo['status'] !== 'ready')     { $pdo->rollBack(); return [false, 'The job must be marked "Ready" before it can be completed.']; }

    // Loaner rims must be back before the customer's own rims are released
    $b = $pdo->prepare("SELECT COUNT(*) FROM borrowings WHERE job_order_id = ? AND status IN ('borrowed','overdue')");
    $b->execute([$joId]);
    if ((int)$b->fetchColumn() > 0) {
      $pdo->rollBack(); return [false, 'This job still has loaner rims out. Mark them as returned first.'];
    }

    $pdo->prepare("UPDATE job_orders SET status='completed', payment_status='paid', amount_charged=?, payment_method=?, completed_at=NOW() WHERE id=?")
        ->execute([number_format((float)$amount, 2, '.', ''), $method, $joId]);
    jo_log($pdo, $joId, 'completed', 'Job completed. Payment received via ' . ucfirst($method), $actorId, $actorName);

    $pdo->commit();
    return [true, 'Job order marked as completed.'];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('jo_complete: ' . $e->getMessage());
    return [false, 'Could not complete the job order. Please try again.'];
  }
}

/** ASSIGN (or unassign) a worker. The id must belong to a real worker account. */
function jo_assign_worker(PDO $pdo, int $joId, ?int $workerId): array {
  try {
    if ($workerId !== null) {
      $w = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'worker'");
      $w->execute([$workerId]);
      if (!$w->fetch()) return [false, 'That user is not a worker.'];
    }
    $pdo->prepare("UPDATE job_orders SET worker_id=? WHERE id=?")->execute([$workerId, $joId]);
    return [true, $workerId ? 'Worker assigned.' : 'Worker unassigned.'];
  } catch (Throwable $e) {
    error_log('jo_assign_worker: ' . $e->getMessage());
    return [false, 'Could not assign the worker. Please try again.'];
  }
}
