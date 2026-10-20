<?php
// config/jo-edit-modal.php
// "Edit Details" modal for ONE job order. Include it inside the loop where $jo is set.
// Needs the modal CSS that admin-joborders.php / owner-joborders.php already define.
$_jeId   = (int)$jo['id'];
$_jeSvc  = [
  'Powder Coating' => ['Powder Coating — Matte Finish','Powder Coating — Gloss Finish','Powder Coating — Metallic Finish','Powder Coating — Custom Color'],
  'Rim Services'   => ['Rim Repair','Rim Straightening','Rim Cleaning & Polishing'],
];
$_jeFlat   = array_merge(...array_values($_jeSvc));
$_jeCounts = ['1 Rim (Front)','1 Rim (Rear)','2 Rims (Full Set)'];
$_jeSizes  = ['14"','17"','18"','19"','21"'];
$_jeH = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
    <!-- EDIT DETAILS MODAL -->
    <div class="modal-overlay" id="editModal<?= $_jeId ?>">
      <div class="modal">
        <h3>Edit Details &mdash; <?= $_jeH($jo['jo_number']) ?></h3>
        <p style="font-size:.78rem;color:var(--muted);margin:-10px 0 16px;">Customer: <strong><?= $_jeH($jo['customer_name']) ?></strong>. Use this if the customer changed their mind.</p>
        <form method="POST">
          <input type="hidden" name="action" value="update_details">
          <input type="hidden" name="jo_id" value="<?= $_jeId ?>">
          <div class="form-grid">
            <div class="form-group span2"><label>Service</label>
              <select name="service" required>
                <?php if (($jo['service'] ?? '') !== '' && !in_array($jo['service'], $_jeFlat, true)): ?>
                <option selected><?= $_jeH($jo['service']) ?></option>
                <?php endif; ?>
                <?php foreach ($_jeSvc as $_g => $_opts): ?>
                <optgroup label="<?= $_jeH($_g) ?>">
                  <?php foreach ($_opts as $_o): ?>
                  <option<?= ($jo['service'] ?? '') === $_o ? ' selected' : '' ?>><?= $_jeH($_o) ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Rims</label>
              <select name="rim_count">
                <option value="">—</option>
                <?php if (($jo['rim_count'] ?? '') !== '' && !in_array($jo['rim_count'], $_jeCounts, true)): ?><option selected><?= $_jeH($jo['rim_count']) ?></option><?php endif; ?>
                <?php foreach ($_jeCounts as $_o): ?><option<?= ($jo['rim_count'] ?? '') === $_o ? ' selected' : '' ?>><?= $_jeH($_o) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Rim Size</label>
              <select name="rim_size">
                <option value="">—</option>
                <?php if (($jo['rim_size'] ?? '') !== '' && !in_array($jo['rim_size'], $_jeSizes, true)): ?><option selected><?= $_jeH($jo['rim_size']) ?></option><?php endif; ?>
                <?php foreach ($_jeSizes as $_o): ?><option<?= ($jo['rim_size'] ?? '') === $_o ? ' selected' : '' ?>><?= $_jeH($_o) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Color / Finish</label><input type="text" name="color_finish" value="<?= $_jeH($jo['color_finish'] ?? '') ?>" placeholder="e.g. Gloss Gold"></div>
            <div class="form-group"><label>Motorcycle</label><input type="text" name="vehicle" value="<?= $_jeH($jo['vehicle'] ?? '') ?>"></div>
            <div class="form-group"><label>Contact Number</label><input type="tel" name="contact_number" value="<?= $_jeH($jo['contact_number'] ?? '') ?>"></div>
            <div class="form-group"><label>Est. Completion</label><input type="date" name="est_completion" value="<?= $_jeH(substr((string)($jo['est_completion'] ?? ''), 0, 10)) ?>"></div>
            <div class="form-group span2"><label>Notes</label><textarea name="notes" rows="3"><?= $_jeH($jo['notes'] ?? '') ?></textarea></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('editModal<?= $_jeId ?>').classList.remove('open')">Cancel</button>
            <button type="submit" class="btn btn-red">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
