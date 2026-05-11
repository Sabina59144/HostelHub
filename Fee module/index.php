<?php
/* ── Auth & DB ──────────────────────────────────────── */
require_once __DIR__ . '/../includes/session.php';
requireLogin();
require_once __DIR__ . '/../includes/db.php';

/* ── Toggle paid / unpaid ───────────────────────────── */
// is_paid is a DATE column: NULL = unpaid, date value = paid on that date
if (isset($_GET['toggle_pay'])) {
    $id   = $_GET['toggle_pay'];
    $stmt = $db->prepare("SELECT is_paid FROM fees WHERE receipt_number = ?");
    $stmt->execute([$id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ($row['is_paid']) {
            // Mark as unpaid — set is_paid back to NULL
            $db->prepare("UPDATE fees SET is_paid = NULL WHERE receipt_number = ?")->execute([$id]);
        } else {
            // Mark as paid — store today's date
            $db->prepare("UPDATE fees SET is_paid = CURDATE() WHERE receipt_number = ?")->execute([$id]);
        }
    }
    header("Location: index.php");
    exit;
}

/* ── Student filter (coming from student module) ─────── */
$filterStudentId   = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$filterStudentName = '';

if ($filterStudentId) {
    $sRow = $db->prepare("SELECT full_name FROM students WHERE student_id = ?");
    $sRow->execute([$filterStudentId]);
    $filterStudentName = $sRow->fetchColumn() ?: '';
}

/* ── Search & list fees ─────────────────────────────── */
$search = $_GET['search'] ?? '';

if ($filterStudentId) {
    // Show only this student's fees (from student module link)
    $stmt = $db->prepare("
        SELECT fees.*, students.full_name
        FROM fees
        LEFT JOIN students ON students.student_id = fees.student_id
        WHERE fees.student_id = ?
        ORDER BY fees.created_at DESC
    ");
    $stmt->execute([$filterStudentId]);
} elseif (!empty($search)) {
    $stmt = $db->prepare("
        SELECT fees.*, students.full_name
        FROM fees
        LEFT JOIN students ON students.student_id = fees.student_id
        WHERE fees.receipt_number LIKE ?
           OR students.full_name  LIKE ?
           OR fees.student_id     LIKE ?
        ORDER BY fees.created_at DESC
    ");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $db->prepare("
        SELECT fees.*, students.full_name
        FROM fees
        LEFT JOIN students ON students.student_id = fees.student_id
        ORDER BY fees.created_at DESC
    ");
    $stmt->execute();
}

$fees  = $stmt->fetchAll(PDO::FETCH_ASSOC);
$today = new DateTime();

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fees — HostelHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f0f4f8; font-family: 'DM Sans', sans-serif; }
        .container { padding: 32px 40px; max-width: 1200px; margin: 0 auto; }

        .page-header { margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .page-header h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: #0f1923; margin-bottom: 4px; }
        .page-header p  { color: #64748b; font-size: 14px; }

        .btn-add {
            display: inline-flex; align-items: center; gap: 6px;
            background: #1a56db; color: #fff;
            padding: 10px 20px; border-radius: 10px;
            font-weight: 600; font-size: 14px;
            text-decoration: none; white-space: nowrap;
            transition: background 0.2s;
        }
        .btn-add:hover { background: #1341b0; text-decoration: none; }

        .search-row { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-row input {
            flex: 1; padding: 10px 14px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 14px; font-family: 'DM Sans', sans-serif;
        }
        .search-row input:focus { outline: none; border-color: #1a56db; box-shadow: 0 0 0 3px rgba(26,86,219,0.12); }
        .search-row button {
            padding: 10px 18px; background: #1a56db; color: #fff;
            border: none; border-radius: 8px; font-size: 14px;
            font-weight: 600; cursor: pointer; font-family: 'DM Sans', sans-serif;
        }
        .search-row a {
            padding: 10px 14px; background: #f1f5f9; color: #64748b;
            border-radius: 8px; font-size: 14px; text-decoration: none;
            display: flex; align-items: center;
        }
        .search-row a:hover { background: #e2e8f0; }

        .table-card {
            background: #fff; border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid #e8edf3; overflow: hidden;
        }
        .table-card table { width: 100%; border-collapse: collapse; }
        .table-card thead th {
            background: #f8fafc; color: #374151;
            font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            padding: 13px 16px; text-align: left;
            border-bottom: 1px solid #e5e7eb; white-space: nowrap;
        }
        .table-card tbody td {
            padding: 13px 16px; border-bottom: 1px solid #f1f5f9;
            font-size: 14px; vertical-align: middle;
        }
        .table-card tbody tr:last-child td { border-bottom: none; }
        .table-card tbody tr:hover { background: #f9fafb; }
        .table-card tbody tr.late-row { background: #fff5f5 !important; }
        .table-card tbody tr.late-row td { color: #7f1d1d; }

        .badge { display: inline-block; padding: 4px 11px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .badge.paid    { background: #dcfce7; color: #166534; }
        .badge.unpaid  { background: #fef3c7; color: #92400e; }
        .badge.overdue { background: #fee2e2; color: #991b1b; }

        .type-badge { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #eff6ff; color: #1d4ed8; text-transform: capitalize; }

        .receipt     { font-family: monospace; font-size: 12px; color: #64748b; }
        .amount      { font-weight: 700; color: #1a202c; }
        .fine-amount { color: #dc2626; font-weight: 600; }
        .total-due   { font-weight: 800; color: #1e3a8a; }

        .btn-action { display: inline-block; padding: 5px 11px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; transition: opacity 0.15s; white-space: nowrap; margin: 2px 1px; }
        .btn-action:hover { opacity: 0.85; text-decoration: none; }
        .btn-edit   { background: #eff6ff; color: #1d4ed8; }
        .btn-delete { background: #fee2e2; color: #991b1b; }
        .btn-pay    { background: #dcfce7; color: #166534; }
        .btn-unpay  { background: #fef3c7; color: #92400e; }

        .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
        .empty-state .icon { font-size: 2.5rem; display: block; margin-bottom: 12px; }
        .empty-state h3 { font-size: 1.05rem; color: #64748b; }
        .empty-state p  { font-size: 14px; margin-top: 6px; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">

    <!-- Back to student link when filtering by student -->
    <?php if ($filterStudentId && $filterStudentName): ?>
    <div style="margin-bottom:16px;">
        <a href="../Student%20module/view_student.php?id=<?= $filterStudentId ?>"
           style="color:#64748b;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
            ← Back to <?= htmlspecialchars($filterStudentName) ?>
        </a>
    </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <?php if ($filterStudentId && $filterStudentName): ?>
                <h2>Fees — <?= htmlspecialchars($filterStudentName) ?></h2>
                <p>Showing all fee records for this student</p>
            <?php else: ?>
                <h2>Fee Records</h2>
                <p>Manage hostel fee payments and outstanding balances</p>
            <?php endif; ?>
        </div>
        <?php if ($isAdmin): ?>
            <a href="add.php<?= $filterStudentId ? '?student_id=' . $filterStudentId : '' ?>" class="btn-add">+ Add Fee</a>
        <?php endif; ?>
    </div>

    <?php if (!$filterStudentId): ?>
    <form method="GET" class="search-row">
        <input type="text" name="search"
               placeholder="Search by receipt number, student name or ID…"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Search</button>
        <?php if (!empty($search)): ?>
            <a href="index.php">✕ Clear</a>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <div class="table-card">
        <?php if (empty($fees)): ?>
            <div class="empty-state">
                <span class="icon">💷</span>
                <h3>No fee records found<?= !empty($search) ? ' matching your search' : '' ?></h3>
                <p><?= !empty($search) ? 'Try a different search term.' : 'Add a fee record to get started.' ?></p>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Student</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Late Fine</th>
                    <th>Total Due</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Paid On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Fine policy constants (calculated in PHP, not stored in DB)
            $FINE_RATE = 0.50; // £ per day
            $FINE_CAP  = 15.00; // £ maximum

            foreach ($fees as $fee):
                $dueDate     = new DateTime($fee['due_date']);
                $fineAmount  = 0;
                $status      = 'unpaid';
                $statusLabel = 'Unpaid';
                $rowClass    = '';

                if ($fee['is_paid']) {
                    // is_paid holds the date it was paid
                    $status      = 'paid';
                    $statusLabel = 'Paid';
                } elseif ($today > $dueDate) {
                    // Overdue — calculate fine dynamically
                    $daysOverdue = $dueDate->diff($today)->days;
                    $fineAmount  = min($daysOverdue * $FINE_RATE, $FINE_CAP);
                    $status      = 'overdue';
                    $statusLabel = '⚠ Overdue';
                    $rowClass    = 'late-row';
                }

                $totalDue = $fee['amount'] + $fineAmount;
            ?>
            <tr class="<?= $rowClass ?>">
                <td class="receipt"><?= htmlspecialchars($fee['receipt_number']) ?></td>
                <td><?= htmlspecialchars($fee['full_name'] ?? '—') ?></td>
                <td><span class="type-badge"><?= ucfirst($fee['fee_type']) ?></span></td>
                <td class="amount">£<?= number_format($fee['amount'], 2) ?></td>
                <td class="fine-amount"><?= $fineAmount > 0 ? '+£' . number_format($fineAmount, 2) : '—' ?></td>
                <td class="total-due">£<?= number_format($totalDue, 2) ?></td>
                <td><?= $dueDate->format('d M Y') ?></td>
                <td><span class="badge <?= $status ?>"><?= $statusLabel ?></span></td>
                <td style="font-size:12px;color:#64748b;">
                    <?= $fee['paid_at'] ? date('d M Y', strtotime($fee['paid_at'])) : '—' ?>
                </td>
                <td style="white-space:nowrap;">
                    <?php if ($isAdmin): ?>
                        <a class="btn-action btn-edit"
                           href="edit.php?id=<?= urlencode($fee['receipt_number']) ?>">Edit</a>
                        <a class="btn-action btn-delete"
                           href="delete.php?id=<?= urlencode($fee['receipt_number']) ?>">Delete</a>
                    <?php endif; ?>
                    <a class="btn-action <?= $fee['is_paid'] ? 'btn-unpay' : 'btn-pay' ?>"
                       href="?toggle_pay=<?= urlencode($fee['receipt_number']) ?>"
                       onclick="return confirm('<?= $fee['is_paid'] ? 'Mark this fee as unpaid?' : 'Mark this fee as paid?' ?>')">
                        <?= $fee['is_paid'] ? 'Mark Unpaid' : 'Mark Paid' ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <p style="margin-top:14px;font-size:12px;color:#94a3b8;text-align:center;">
        Late fine policy: £0.50/day · maximum £15.00 per fee · calculated automatically on overdue fees.
    </p>

</div>
</body>
</html>
