<?php
/* ── Auth & DB ──────────────────────────────────────── */
require_once __DIR__ . '/../includes/session.php';
requireRole('admin');
require_once __DIR__ . '/../includes/db.php';

/* ── Load fee record ────────────────────────────────── */
$id   = $_GET['id'] ?? '';
$stmt = $db->prepare("
    SELECT fees.*, students.full_name
    FROM fees
    LEFT JOIN students ON students.student_id = fees.student_id
    WHERE fees.receipt_number = ?
");
$stmt->execute([$id]);
$fee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fee) {
    header("Location: index.php");
    exit;
}

/* ── Load students for dropdown ─────────────────────── */
$students = $db->query("
    SELECT student_id, full_name FROM students
    WHERE status = 1 ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ── Mark as paid (set is_paid = today's date) ──────── */
if (isset($_POST['mark_paid'])) {
    $db->prepare("UPDATE fees SET is_paid = CURDATE() WHERE receipt_number = ?")
       ->execute([$id]);
    header("Location: index.php");
    exit;
}

/* ── Mark as unpaid ─────────────────────────────────── */
if (isset($_POST['mark_unpaid'])) {
    $db->prepare("UPDATE fees SET is_paid = NULL WHERE receipt_number = ?")
       ->execute([$id]);
    header("Location: index.php");
    exit;
}

/* ── Update fee details ─────────────────────────────── */
// Schema: receipt_number, student_id, fee_type, amount, due_date, is_paid
if (isset($_POST['update'])) {
    $db->prepare("
        UPDATE fees SET student_id = ?, fee_type = ?, amount = ?, due_date = ?
        WHERE receipt_number = ?
    ")->execute([
        $_POST['student_id'], $_POST['fee_type'],
        $_POST['amount'],     $_POST['due_date'],
        $id
    ]);
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Fee — HostelHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f0f4f8; font-family: 'DM Sans', sans-serif; }
        .container { padding: 32px 40px; max-width: 640px; margin: 0 auto; }

        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: #0f1923; margin-bottom: 4px; }
        .page-header p  { color: #64748b; font-size: 14px; }

        .form-card {
            background: #fff; border-radius: 16px; padding: 28px 32px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #e8edf3;
            margin-bottom: 20px;
        }
        .form-card h3 { font-size: 15px; font-weight: 700; color: #374151; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }

        .form-group { margin-bottom: 18px; }
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

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .receipt-display {
            background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 10px 14px; font-family: monospace; font-size: 14px; color: #374151;
        }

        .btn-green {
            width: 100%; padding: 11px; background: #059669; color: #fff;
            border: none; border-radius: 10px; font-size: 14px; font-weight: 600;
            cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background 0.2s;
        }
        .btn-green:hover { background: #047857; }

        .btn-amber {
            width: 100%; padding: 11px; background: #d97706; color: #fff;
            border: none; border-radius: 10px; font-size: 14px; font-weight: 600;
            cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background 0.2s;
        }
        .btn-amber:hover { background: #b45309; }

        .btn-blue {
            width: 100%; padding: 11px; background: #1a56db; color: #fff;
            border: none; border-radius: 10px; font-size: 14px; font-weight: 600;
            cursor: pointer; font-family: 'DM Sans', sans-serif; transition: background 0.2s; margin-top: 8px;
        }
        .btn-blue:hover { background: #1341b0; }

        .paid-notice {
            background: #dcfce7; border: 1px solid #bbf7d0; border-radius: 8px;
            padding: 12px 16px; font-size: 14px; color: #166534; margin-bottom: 4px;
        }

        .back-link { display: block; text-align: center; margin-top: 16px; font-size: 14px; color: #64748b; }
        .back-link:hover { color: #1a56db; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
    <div class="page-header">
        <h2>Edit Fee Record</h2>
        <p>Receipt: <strong><?= htmlspecialchars($fee['receipt_number']) ?></strong>
           · Student: <strong><?= htmlspecialchars($fee['full_name'] ?? '—') ?></strong></p>
    </div>

    <!-- Payment status toggle -->
    <div class="form-card">
        <h3>💳 Payment Status</h3>
        <?php if ($fee['is_paid']): ?>
            <div class="paid-notice">
                ✅ Paid on <strong><?= (new DateTime($fee['is_paid']))->format('d M Y') ?></strong>
            </div>
            <form method="POST" style="margin-top:12px;">
                <button type="submit" name="mark_unpaid" class="btn-amber"
                        onclick="return confirm('Mark this fee as unpaid?')">
                    Mark as Unpaid
                </button>
            </form>
        <?php else: ?>
            <form method="POST">
                <button type="submit" name="mark_paid" class="btn-green"
                        onclick="return confirm('Mark this fee as paid today?')">
                    ✅ Mark as Paid (today)
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Edit fee details -->
    <div class="form-card">
        <h3>✏️ Edit Fee Details</h3>
        <form method="POST">
            <div class="form-group">
                <label>Receipt Number</label>
                <div class="receipt-display"><?= htmlspecialchars($fee['receipt_number']) ?></div>
            </div>

            <div class="form-group">
                <label>Student</label>
                <select name="student_id">
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['student_id'] ?>"
                            <?= $fee['student_id'] == $s['student_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Fee Type</label>
                    <select name="fee_type">
                        <?php foreach (['rent','deposit','utility','fine','other'] as $type): ?>
                            <option value="<?= $type ?>" <?= $fee['fee_type'] === $type ? 'selected' : '' ?>>
                                <?= ucfirst($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Amount (£)</label>
                    <input type="number" step="0.01" min="0.01" name="amount"
                           value="<?= htmlspecialchars($fee['amount']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" value="<?= htmlspecialchars($fee['due_date']) ?>">
            </div>

            <button type="submit" name="update" class="btn-blue">💾 Save Changes</button>
        </form>
    </div>

    <a href="index.php" class="back-link">← Back to Fee Records</a>
</div>
</body>
</html>
