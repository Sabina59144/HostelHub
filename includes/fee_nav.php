<?php
$current = basename($_SERVER['PHP_SELF']);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
?>

<div class="fee-nav-wrap">
    <div class="fee-nav">
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
            📊 Dashboard
        </a>

        <a href="index.php" class="<?= $current === 'index.php' ? 'active' : '' ?>">
            📋 Fee Records
        </a>

        <?php if ($isAdmin): ?>
        <a href="add.php" class="<?= $current === 'add.php' ? 'active' : '' ?>">
            ➕ Add Fee
        </a>
        <?php endif; ?>

        <a href="report.php" class="<?= $current === 'report.php' ? 'active' : '' ?>">
            📈 Reports
        </a>

        <a href="index.php?filter=overdue">
            ⚠ Overdue
        </a>

        <a href="index.php?filter=unpaid">
            💷 Unpaid
        </a>
    </div>
</div>