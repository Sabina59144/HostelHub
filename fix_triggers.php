<?php
/**
 * One-time script: recreate broken fee-generation triggers.
 * Visit once, then delete this file.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$results = [];

$steps = [
    'Drop after_student_insert' =>
        "DROP TRIGGER IF EXISTS after_student_insert",

    'Drop after_student_room_assigned' =>
        "DROP TRIGGER IF EXISTS after_student_room_assigned",

    'Create after_student_insert' =>
        "CREATE TRIGGER after_student_insert
         AFTER INSERT ON students
         FOR EACH ROW
         BEGIN
             IF NEW.room_id IS NOT NULL THEN
                 SET @room_price = 0;
                 SELECT price_per_month INTO @room_price FROM rooms WHERE room_id = NEW.room_id;
                 SET @receipt = CONCAT('RCP-', YEAR(CURDATE()), '-', LPAD(NEW.student_id, 4, '0'), '-AUTO');
                 INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active)
                 VALUES (CONCAT(@receipt, '-DEP'), NEW.student_id, 'deposit', @room_price, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 0, NULL, 0.50, 15.00, 0.00, @room_price, 1);
                 INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active)
                 VALUES (CONCAT(@receipt, '-RENT'), NEW.student_id, 'rent', @room_price, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 0, NULL, 0.50, 15.00, 0.00, @room_price, 1);
             END IF;
         END",

    'Create after_student_room_assigned' =>
        "CREATE TRIGGER after_student_room_assigned
         AFTER UPDATE ON students
         FOR EACH ROW
         BEGIN
             IF OLD.room_id IS NULL AND NEW.room_id IS NOT NULL THEN
                 SET @room_price = 0;
                 SELECT price_per_month INTO @room_price FROM rooms WHERE room_id = NEW.room_id;
                 SET @receipt = CONCAT('RCP-', YEAR(CURDATE()), '-', LPAD(NEW.student_id, 4, '0'));
                 INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active)
                 VALUES (CONCAT(@receipt, '-DEP'), NEW.student_id, 'deposit', @room_price, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 0, NULL, 0.50, 15.00, 0.00, @room_price, 1);
                 INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid, paid_at, fine_rate, fine_cap, fine_amount, total_due, is_active)
                 VALUES (CONCAT(@receipt, '-RENT'), NEW.student_id, 'rent', @room_price, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 0, NULL, 0.50, 15.00, 0.00, @room_price, 1);
             END IF;
         END",
];

foreach ($steps as $label => $sql) {
    try {
        $db->exec($sql);
        $results[] = ['ok', $label];
    } catch (PDOException $e) {
        $results[] = ['err', "$label — " . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fix Triggers — HostelHub</title>
<style>
  body { font-family: sans-serif; max-width: 640px; margin: 60px auto; background: #f4f6f9; }
  .card { background: #fff; border-radius: 12px; padding: 28px 32px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
  h2 { margin-top: 0; }
  .ok  { color: #059669; } .ok::before  { content: '✓  '; }
  .err { color: #dc2626; } .err::before { content: '✗  '; }
  li { padding: 4px 0; font-size: 14px; }
  .done { margin-top: 20px; padding: 12px 16px; background: #ecfdf5; border-radius: 8px;
          border: 1px solid #6ee7b7; color: #065f46; font-size: 14px; }
  .fail { margin-top: 20px; padding: 12px 16px; background: #fff1f2; border-radius: 8px;
          border: 1px solid #fda4af; color: #9f1239; font-size: 14px; }
  a { color: #1a56db; }
</style>
</head>
<body>
<div class="card">
  <h2>Trigger Fix Results</h2>
  <ul>
  <?php foreach ($results as [$status, $msg]): ?>
    <li class="<?= $status ?>"><?= htmlspecialchars($msg) ?></li>
  <?php endforeach; ?>
  </ul>

  <?php $allOk = !in_array('err', array_column($results, 0)); ?>
  <?php if ($allOk): ?>
    <div class="done">
      ✅ All triggers updated successfully. You can now
      <a href="Student%20module/add_student.php">add students</a>.
      <br><br>
      <strong>Delete <code>fix_triggers.php</code> from your project now.</strong>
    </div>
  <?php else: ?>
    <div class="fail">One or more steps failed. Check the errors above.</div>
  <?php endif; ?>
</div>
</body>
</html>
