<?php
// Shared navbar for student portal.
// Set $activePage = 'dashboard' | 'fees' | 'maintenance' | 'request' before including.
$activePage = $activePage ?? 'dashboard';
?>
<nav class="navbar">
    <a href="index.php" class="navbar-brand">
        <img src="../room/logo.png" alt="HostelHub">
        <span class="brand-name">HostelHub</span>
        <span class="brand-tag">Student Portal</span>
    </a>

    <div class="nav-links">
        <a href="index.php"        class="<?= $activePage==='dashboard'   ? 'active':'' ?>">Dashboard</a>
        <a href="fees.php"         class="<?= $activePage==='fees'        ? 'active':'' ?>">My Fees</a>
        <a href="maintenance.php"  class="<?= $activePage==='maintenance' ? 'active':'' ?>">Maintenance</a>
        <a href="room_request.php" class="<?= $activePage==='request'     ? 'active':'' ?>">Room Request</a>
    </div>

    <div class="navbar-user">
        <div>
            <div class="user-name"><?= htmlspecialchars($_SESSION['student_name'] ?? 'Student') ?></div>
            <div class="user-id"><?= htmlspecialchars($_SESSION['student_number'] ?? '') ?></div>
        </div>
        <a href="logout.php" class="btn-signout">Sign Out</a>
    </div>
</nav>
