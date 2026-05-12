<?php
/* ── Auth & DB ──────────────────────────────────────── */
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

/* ── Load active students for dropdown ─────────────── */
$students = $db->query("
    SELECT student_id, full_name
    FROM students
    WHERE status = 1
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);

/* ── Auto-generate next receipt number ─────────────── */
function generateReceipt($db) {
    $row = $db->query("
        SELECT receipt_number FROM fees
        ORDER BY created_at DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if ($row && preg_match('/(\d+)$/', $row['receipt_number'], $m)) {
        $next = (int)$m[1] + 1;
    } else {
        $next = 1;
    }
    return "RCP-" . date("Y") . "-" . str_pad($next, 4, "0", STR_PAD_LEFT);
}

$receipt_number    = generateReceipt($db);
$errors            = [];
// Pre-select student if coming from student module
$preselectedStudent = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;

/* ── Handle form submit ─────────────────────────────── */
// Schema columns: receipt_number, student_id, fee_type, amount, due_date, is_paid
if (isset($_POST['submit'])) {
    $receipt_number = trim($_POST['receipt_number']);
    $student_id     = (int) $_POST['student_id'];
    $fee_type       = $_POST['fee_type'];
    $amount         = (float) $_POST['amount'];
    $due_date       = $_POST['due_date'];

    if (empty($student_id))     $errors[] = "Please select a student.";
    if ($amount <= 0)           $errors[] = "Amount must be greater than 0.";
    if (empty($due_date))       $errors[] = "Please enter a due date.";

    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO fees (receipt_number, student_id, fee_type, amount, due_date, is_paid)
            VALUES (?, ?, ?, ?, ?, NULL)
        ");
        $stmt->execute([$receipt_number, $student_id, $fee_type, $amount, $due_date]);
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Fee — HostelHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f0f4f8; font-family: 'DM Sans', sans-serif; }
        .container { padding: 32px 40px; max-width: 620px; margin: 0 auto; }

        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: #0f1923; margin-bottom: 4px; }
        .page-header p  { color: #64748b; font-size: 14px; }

        .form-card {
            background: #fff; border-radius: 16px; padding: 32px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #e8edf3;
        }

        .error-box {
            background: #fff5f5; border: 1px solid #fecaca; border-radius: 8px;
            padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #991b1b;
        }
        .error-box ul { margin: 6px 0 0 18px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .form-group input,
        .form-group select {
            width: 100%; padding: 10px 14px;
            border: 1px solid #d1d5db; border-radius: 8px;
            font-size: 14px; font-family: 'DM Sans', sans-serif;
            background: #fff; color: #1a202c;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none; border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26,86,219,0.12);
        }
        .form-group input[readonly] { background: #f8fafc; color: #64748b; cursor: not-allowed; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .btn-submit {
            width: 100%; padding: 12px; background: #1a56db; color: #fff;
            border: none; border-radius: 10px; font-size: 15px; font-weight: 600;
            cursor: pointer; font-family: 'DM Sans', sans-serif;
            transition: background 0.2s; margin-top: 8px;
        }
        .btn-submit:hover { background: #1341b0; }

        .back-link { display: block; text-align: center; margin-top: 16px; font-size: 14px; color: #64748b; }
        .back-link:hover { color: #1a56db; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h2>Add Fee Record</h2>
        <p>Create a new fee entry for a student</p>
    </div>

    <div class="form-card">

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong>Please fix the following:</strong>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Receipt Number</label>
                <input type="text" name="receipt_number"
                       value="<?= htmlspecialchars($receipt_number) ?>" readonly>
            </div>

            <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                    <option value="">— Select Student —</option>
                    <?php foreach ($students as $s):
                        // Pre-select from POST (on error) or from GET (from student module)
                        $selected = (isset($_POST['student_id']) && $_POST['student_id'] == $s['student_id'])
                                 || (!isset($_POST['student_id']) && $preselectedStudent == $s['student_id']);
                    ?>
                        <option value="<?= $s['student_id'] ?>" <?= $selected ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Fee Type</label>
                    <select name="fee_type" required>
                        <option value="tuition" <?= (isset($_POST['fee_type']) && $_POST['fee_type']==='tuition') ? 'selected':'' ?>>Tuition</option>
                        <option value="hostel"  <?= (isset($_POST['fee_type']) && $_POST['fee_type']==='hostel')  ? 'selected':'' ?>>Hostel</option>
                        <option value="other"   <?= (isset($_POST['fee_type']) && $_POST['fee_type']==='other')   ? 'selected':'' ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Amount (£)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required
                           placeholder="0.00"
                           value="<?= isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : '' ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" required
                       value="<?= isset($_POST['due_date']) ? htmlspecialchars($_POST['due_date']) : '' ?>">
            </div>

            <button type="submit" name="submit" class="btn-submit">💾 Save Fee Record</button>
        </form>

        <?php if ($preselectedStudent): ?>
            <a href="index.php?student_id=<?= $preselectedStudent ?>" class="back-link">← Back to student's fees</a>
        <?php else: ?>
            <a href="index.php" class="back-link">← Back to Fee Records</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
