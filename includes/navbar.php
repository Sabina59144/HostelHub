<?php
// includes/navbar.php
$user        = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);

// Use real filesystem paths — avoids URL encoding issues with folder names containing spaces
$projectRoot = dirname(__DIR__); // navbar.php is in includes/, so dirname gives HostelHub root
$scriptDir   = dirname($_SERVER['SCRIPT_FILENAME']);
$inSubdir    = (realpath($scriptDir) !== realpath($projectRoot));
$base        = $inSubdir ? '../' : '';
?>

<nav class="navbar">
    <!-- Logo -->
    <a href="<?= $base ?>dashboard.php" class="navbar-logo" style="text-decoration:none;display:flex;align-items:center;gap:10px;margin-right:16px;flex-shrink:0;">
        <div style="
            width:38px;height:38px;border-radius:10px;
            background:#0f1923;
            display:flex;align-items:center;justify-content:center;
            flex-shrink:0;
            box-shadow:0 2px 8px rgba(0,0,0,0.35);
        ">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="13" y="3" width="3" height="5" rx="0.5" fill="#1a56db"/>
                <polygon points="2,11 11,3 20,11" fill="#1a56db"/>
                <rect x="4" y="11" width="14" height="9" rx="1" fill="#1a56db"/>
                <rect x="8.5" y="14" width="5" height="6" rx="1" fill="#0f1923"/>
                <rect x="5" y="12.5" width="3" height="2.5" rx="0.5" fill="#fdd835"/>
                <rect x="14" y="12.5" width="3" height="2.5" rx="0.5" fill="#fdd835"/>
            </svg>
        </div>
        <div style="line-height:1;">
            <div style="font-family:'Playfair Display','Georgia',serif;font-size:17px;font-weight:700;color:#ffffff;letter-spacing:0.01em;">
                Hostel<span style="color:#60a5fa;">Hub</span>
            </div>
            <div style="font-family:'DM Sans',sans-serif;font-size:8.5px;color:#64748b;letter-spacing:0.12em;text-transform:uppercase;margin-top:2px;">
                Management System
            </div>
        </div>
    </a>

    <ul class="navbar-links">
        <li>
            <a href="<?= $base ?>dashboard.php"
               class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                Dashboard
            </a>
        </li>
        <li>
            <a href="<?= $base ?>Student%20module/index.php"
               class="<?= $currentPage === 'index.php' && strpos($_SERVER['PHP_SELF'], 'Student') !== false ? 'active' : '' ?>">
                Students
            </a>
        </li>
        <li>
            <a href="<?= $base ?>Room%20module/index.php"
               class="<?= strpos($_SERVER['SCRIPT_FILENAME'], 'Room module') !== false ? 'active' : '' ?>">
                Rooms
            </a>
        </li>
        <li>
            <a href="<?= $base ?>Fee%20module/dashboard.php"
   class="<?= strpos($_SERVER['SCRIPT_FILENAME'], 'Fee module') !== false ? 'active' : '' ?>">
    Fees
</a>
        </li>
        <li>
            <a href="<?= $base ?>pages/maintenance.php"
               class="<?= $currentPage === 'maintenance.php' ? 'active' : '' ?>">
                Maintenance
            </a>
        </li>
        <?php if ($user && $user['role'] === 'admin'): ?>
        <li>
            <a href="<?= $base ?>pages/users.php"
               class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">
                Users
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="navbar-user">
        <span class="user-info">
            <span class="user-name"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></span>
            <span class="user-role"><?= ucfirst($user['role'] ?? '') ?></span>
        </span>
        <a href="<?= $base ?>logout.php" class="btn-logout">Logout</a>
    </div>
</nav>