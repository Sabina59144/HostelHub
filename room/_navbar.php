<?php
// Shared navbar for the Room module.
// Set $activeNav = 'rooms' (default) before including to highlight a tab.
$activeNav = $activeNav ?? 'rooms';
?>
<nav class="navbar">
    <div class="navbar-left">
        <div class="brand-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </div>
        <div class="brand-text">
            <h1>HostelHub</h1>
            <small>MANAGEMENT SYSTEM</small>
        </div>
    </div>

    <div class="nav-links">
        <a href="../dashboard.php"               class="<?php echo $activeNav==='dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <a href="../Student module/index.php"    class="<?php echo $activeNav==='students'  ? 'active' : ''; ?>">Students</a>
        <a href="index.php"                      class="<?php echo $activeNav==='rooms'     ? 'active' : ''; ?>">Rooms</a>
        <a href="../fees/index.php"              class="<?php echo $activeNav==='fees'      ? 'active' : ''; ?>">Fees</a>
        <a href="../maintenance/index.php"       class="<?php echo $activeNav==='maintenance' ? 'active' : ''; ?>">Maintenance</a>
        <a href="../users/index.php"             class="<?php echo $activeNav==='users'     ? 'active' : ''; ?>">Users</a>
    </div>

    <div class="navbar-right">
        <div class="user-info">
            <div class="name"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'System Administrator'; ?></div>
            <div class="role">Admin</div>
        </div>
        <a href="../logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
