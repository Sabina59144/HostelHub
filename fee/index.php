<?php
require_once(__DIR__ . "/../includes/db.php");
require_once(__DIR__ . "/../includes/auth.php");
requireLogin();

$search = $_GET['search'] ?? '';

if (!empty($search)) {
    $stmt = $db->prepare("
        SELECT fees.*
        FROM fees
        LEFT JOIN students ON students.student_id = fees.student_id
        WHERE fees.is_active = 1
        AND (
            fees.receipt_number LIKE ?
            OR students.full_name LIKE ?
            OR fees.student_id LIKE ?
        )
        ORDER BY fees.created_at DESC
    ");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $db->prepare("
        SELECT * FROM fees
        WHERE is_active = 1
        ORDER BY created_at DESC
    ");
    $stmt->execute();
}

$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
$today = new DateTime();

if (isset($_GET['toggle_pay'])) {
    $id = $_GET['toggle_pay'];
    $stmt = $db->prepare("SELECT is_paid FROM fees WHERE receipt_number=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ($row['is_paid']) {
            $db->prepare("
                UPDATE fees SET is_paid=0, paid_at=NULL, payment_method=NULL
                WHERE receipt_number=?
            ")->execute([$id]);
        } else {
            $db->prepare("
                UPDATE fees SET is_paid=1, paid_at=NOW()
                WHERE receipt_number=?
            ")->execute([$id]);
        }
    }
    header("Location: index.php");
    exit;
}

$isAdmin = currentRole() === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fees List — HostelHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e3a8a;
            color: white;
            padding: 12px 24px;
            margin: -30px -30px 24px -30px;
            border-radius: 12px 12px 0 0;
        }
        .topbar .brand { font-weight: bold; font-size: 1.1rem; }
        .topbar .user-info { font-size: 0.875rem; opacity: 0.9; }
        .topbar .user-info span {
            background: rgba(255,255,255,0.15);
            padding: 3px 10px;
            border-radius: 20px;
            margin-left: 8px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: bold;
        }
        .topbar a.logout {
            color: white;
            background: rgba(220,53,69,0.8);
            padding: 7px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .topbar a.logout:hover { background: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div class="brand">🏠 HostelHub</div>
        <div class="user-info">
            👤 <?= htmlspecialchars($_SESSION['full_name']) ?>
            <span><?= htmlspecialchars(currentRole()) ?></span>
        </div>
        <a href="logout.php" class="logout">🚪 Logout</a>
    </div>

    <h2>Hostel Fee Records</h2>

    <?php if ($isAdmin): ?>
    <a href="add.php" class="add-btn">+ Add Fee</a>
    <?php endif; ?>

    <form method="GET" class="search-box">
        <input type="text" name="search"
               placeholder="Search receipt, student name or ID"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Search</button>
    </form>

    <table>
        <thead>
        <tr>
            <th>Receipt</th>
            <th>Student</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Fine</th>
            <th>Total Due</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Payment Method</th>
            <th>Paid At</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($fees as $fee): ?>
        <?php
        $dueDate = new DateTime($fee['due_date']);
        $fineAmount = 0;
        $status = "Unpaid";
        $statusClass = "unpaid";
        $rowClass = "";

        if ($fee['is_paid']) {
            $status = "Paid";
            $statusClass = "paid";
        } elseif ($today > $dueDate) {
            $daysOverdue = $dueDate->diff($today)->days;
            $fineAmount = min($daysOverdue * $fee['fine_rate'], $fee['fine_cap']);
            $status = "OVERDUE ⚠";
            $statusClass = "overdue";
            $rowClass = "late-row";
        }

        $totalDue = $fee['amount'] + $fineAmount;
        ?>
        <tr class="<?= $rowClass ?>">
            <td><?= htmlspecialchars($fee['receipt_number']) ?></td>
            <td><?= htmlspecialchars($fee['student_id']) ?></td>
            <td><?= ucfirst($fee['fee_type']) ?></td>
            <td>$<?= number_format($fee['amount'],2) ?></td>
            <td>$<?= number_format($fineAmount,2) ?></td>
            <td>$<?= number_format($totalDue,2) ?></td>
            <td><?= $fee['due_date'] ?></td>
            <td><span class="<?= $statusClass ?>"><?= $status ?></span></td>
            <td><?= $fee['payment_method'] ? htmlspecialchars($fee['payment_method']) : '—' ?></td>
            <td><?= $fee['paid_at'] ?: '—' ?></td>
            <td>
                <?php if ($isAdmin): ?>
                <a class="pay-btn" href="edit.php?id=<?= urlencode($fee['receipt_number']) ?>">Edit</a>
                <a class="delete-btn" href="delete.php?id=<?= urlencode($fee['receipt_number']) ?>">Delete</a>
                <?php endif; ?>
                <a class="pay-btn"
                   style="background:<?= $fee['is_paid'] ? '#dc3545' : '#28a745' ?>;"
                   href="?toggle_pay=<?= urlencode($fee['receipt_number']) ?>">
                   <?= $fee['is_paid'] ? 'Mark Unpaid' : 'Mark Paid' ?>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
