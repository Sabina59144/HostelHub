<?php
// Get the filename of the currently executing script (e.g., "dashboard.php")
$current = basename($_SERVER['PHP_SELF']);

// Check if the logged-in user has the 'admin' role; default to empty string if not set
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
?>

<!-- Outer wrapper: controls width and bottom margin for the nav bar -->
<div class="fee-nav-wrap">

    <!-- Inner nav container: holds all navigation links in a styled flex row -->
    <div class="fee-nav">

        <!-- Dashboard link: adds 'active' class if the current page is dashboard.php -->
        <a href="dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">
            📊 Dashboard
        </a>

        <!-- Fee Records link: adds 'active' class if the current page is index.php -->
        <a href="index.php" class="<?= $current === 'index.php' ? 'active' : '' ?>">
            📋 Fee Records
        </a>

        <?php if ($isAdmin): ?>
        <!-- Add Fee link: only shown to admin users (role-based access control) -->
        <!-- Adds 'active' class if the current page is add.php -->
        <a href="add.php" class="<?= $current === 'add.php' ? 'active' : '' ?>">
            ➕ Add Fee
        </a>
        <?php endif; ?>

        <!-- Reports link: adds 'active' class if the current page is report.php -->
        <a href="report.php" class="<?= $current === 'report.php' ? 'active' : '' ?>">
            📈 Reports
        </a>

        <!-- Quick filter link: loads the fee list pre-filtered to show only overdue fees -->
        <a href="index.php?filter=overdue">
            ⚠ Overdue
        </a>

        <!-- Quick filter link: loads the fee list pre-filtered to show only unpaid fees -->
        <a href="index.php?filter=unpaid">
            💷 Unpaid
        </a>
        
    </div>
</div>