<?php
// config/queue-helpers.php

/**
 * Next queue number for a given date.
 * MUST be called inside an open transaction: the FOR UPDATE lock stops two
 * admins from being handed the same number at the same moment.
 */
function next_queue_number(PDO $pdo, string $date): int {
  $stmt = $pdo->prepare("SELECT COALESCE(MAX(queue_number),0)+1 FROM queue WHERE queue_date = ? FOR UPDATE");
  $stmt->execute([$date]);
  return (int)$stmt->fetchColumn();
}
