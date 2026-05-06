<?php
require_once("../includes/db.php");

$message = "";


$stmt = $db->query("
    SELECT receipt_number
    FROM fees
    ORDER BY CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED) DESC
    LIMIT 1
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $lastNumber = (int) substr($row['receipt_number'], -4);
    $nextNumber = $lastNumber + 1;
} else {
    $nextNumber = 1;
}

$receipt_number = "RCP-" . date("Y") . "-" . str_pad($nextNumber, 4, "0", STR_PAD_LEFT);

// BUG FIX 3: Load students from DB for dropdown (can't type a valid student_id blindly)
$studentStmt = $db->query("SELECT student_id, full_name FROM students WHERE status = 1 ORDER BY full_name ASC");
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['submit'])) {

    $receipt_number = trim($_POST['receipt_number']);
    $student_id     = (int) $_POST['student_id'];
    $fee_type       = $_POST['fee_type'];
    $amount         = (float) $_POST['amount'];
    $due_date       = $_POST['due_date'];
    $fine_rate      = isset($_POST['fine_rate']) ? (float) $_POST['fine_rate'] : 0.50;
    $fine_cap       = isset($_POST['fine_cap'])  ? (float) $_POST['fine_cap']  : 15.00;

    if (
        empty($receipt_number) ||
        $student_id <= 0 ||
        $amount <= 0 ||
        empty($due_date)
    ) {
        $message = "❌ Invalid information entered.";
    } else {

        $check = $db->prepare("SELECT COUNT(*) FROM fees WHERE receipt_number = ?");
        $check->execute([$receipt_number]);

        if ($check->fetchColumn() > 0) {
            $message = "❌ Receipt number already exists.";
        } else {

            $today       = date('Y-m-d');
            $fine_amount = 0.00;

            if ($due_date < $today) {
                $days_overdue = floor((strtotime($today) - strtotime($due_date)) / 86400);
                $fine_amount  = min(round($days_overdue * $fine_rate, 2), $fine_cap);
            }

            $total_due = $amount + $fine_amount;

            $stmt = $db->prepare("
                INSERT INTO fees (
                    receipt_number, student_id, fee_type, amount, due_date,
                    is_paid, fine_rate, fine_cap, fine_amount, total_due, is_active
                ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $receipt_number, $student_id, $fee_type, $amount, $due_date,
                $fine_rate, $fine_cap, $fine_amount, $total_due
            ]);

            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Fee — HostelHub</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>

<div class="invoice-wrapper">
    <div class="invoice-card">

        <div class="invoice-header">
            <h2>Generate Fee Entry</h2>
            <p>Hostel Fee Management System</p>
        </div>

        <div class="invoice-body">

            <?php if ($message): ?>
                <div class="error"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST">

                <label>Receipt Number</label>
                <input type="text"
                       name="receipt_number"
                       value="<?= htmlspecialchars($receipt_number) ?>"
                       required>

                <!-- BUG FIX 3: Student dropdown instead of a blank number input -->
                <label>Student</label>
                <select name="student_id" required>
                    <option value="">— Select a Student —</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['student_id'] ?>">
                            <?= htmlspecialchars($s['full_name']) ?>
                            (ID: <?= $s['student_id'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Fee Type</label>
                <select name="fee_type" required>
                    <option value="rent">Rent</option>
                    <option value="deposit">Deposit</option>
                    <option value="utility">Utility</option>
                    <option value="fine">Fine</option>
                    <option value="laundry">Laundry</option>
                    <option value="other">Other</option>
                </select>

                <label>Amount ($)</label>
                <input type="number" step="0.01" min="0.01" name="amount" required>

                <label>Due Date</label>
                <input type="text" id="due_date" name="due_date" required readonly>

                <div class="fine-settings">
                    <label>Fine Rate ($ per day overdue)</label>
                    <input type="number" step="0.01" min="0" name="fine_rate" value="0.50">

                    <label>Fine Cap ($)</label>
                    <input type="number" step="0.01" min="0" name="fine_cap" value="15.00">
                </div>

                <button type="submit" name="submit">Confirm Fee Entry</button>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#due_date", { dateFormat: "Y-m-d" });
</script>

</body>
</html>
