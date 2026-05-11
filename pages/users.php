<?php
// pages/users.php
require_once("../includes/session.php");
require_once("../includes/db.php");
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $toggle_id = (int) $_POST['toggle_user_id'];
    if ($toggle_id !== (int) $_SESSION['user_id']) {
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
        $stmt->execute([$toggle_id]);
    }
    header("Location: users.php");
    exit();
}

$search     = trim($_GET['q']    ?? '');
$filterRole = trim($_GET['role'] ?? '');

$sql    = "SELECT user_id, username, full_name, role, is_active, created_at FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql    .= " AND (full_name LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (in_array($filterRole, ['admin', 'staff'])) {
    $sql    .= " AND role = ?";
    $params[] = $filterRole;
}
$sql .= " ORDER BY created_at DESC";

$stmt  = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$totalCount  = $db->query("SELECT COUNT(*) AS c FROM users")->fetch()['c'];
$activeCount = $db->query("SELECT COUNT(*) AS c FROM users WHERE is_active = 1")->fetch()['c'];
$adminCount  = $db->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management — HostelHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>

        /* ══════════════════════════════════════════════
           HERO BANNER
        ══════════════════════════════════════════════ */
        .hero-banner {
            position: relative;
            width: 100%;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            text-align: center;
            background: #0f0c29;
        }

        /* Animated gradient base */
        .hero-bg {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            z-index: 0;
        }

        /* Colour blobs */
        .hero-bg::before {
            content: '';
            position: absolute;
            width: 650px; height: 650px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99,102,241,.45) 0%, transparent 70%);
            top: -200px; left: -120px;
            animation: blob1 16s ease-in-out infinite alternate;
        }
        .hero-bg::after {
            content: '';
            position: absolute;
            width: 550px; height: 550px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16,185,129,.3) 0%, transparent 70%);
            bottom: -180px; right: -80px;
            animation: blob2 20s ease-in-out infinite alternate;
        }
        @keyframes blob1 {
            0%   { transform: translate(0,0)    scale(1);    }
            50%  { transform: translate(70px,35px)  scale(1.1); }
            100% { transform: translate(25px,-40px) scale(.95); }
        }
        @keyframes blob2 {
            0%   { transform: translate(0,0)     scale(1);   }
            50%  { transform: translate(-50px,-25px) scale(1.1); }
            100% { transform: translate(15px,35px)  scale(.9); }
        }

        /* Grid texture */
        .hero-overlay {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 48px 48px;
            z-index: 1;
        }

        /* Floating orbs */
        .hero-orbs {
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
        }
        .hero-orbs span {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.13);
            animation: floatOrb linear infinite;
        }
        .hero-orbs span:nth-child(1) { width:160px; height:160px; top:5%;  left:3%;   animation-duration:18s; animation-delay:0s;   }
        .hero-orbs span:nth-child(2) { width: 80px; height: 80px; top:55%; left:10%;  animation-duration:14s; animation-delay:-4s;  }
        .hero-orbs span:nth-child(3) { width:120px; height:120px; top:15%; left:76%;  animation-duration:20s; animation-delay:-7s;  }
        .hero-orbs span:nth-child(4) { width: 55px; height: 55px; top:70%; left:82%;  animation-duration:12s; animation-delay:-2s;  }
        .hero-orbs span:nth-child(5) { width:260px; height:260px; top:-15%;left:38%;  background:rgba(56,189,248,.06); border-color:rgba(56,189,248,.12); animation-duration:25s; animation-delay:-10s; }
        @keyframes floatOrb {
            0%   { transform: translateY(0)    rotate(0deg);   opacity:.5; }
            50%  { transform: translateY(-28px) rotate(180deg); opacity:1;  }
            100% { transform: translateY(0)    rotate(360deg); opacity:.5; }
        }

        /* Shimmer strip */
        .hero-shimmer {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #10b981, #38bdf8, #6366f1);
            background-size: 300% 100%;
            animation: shimmer 4s linear infinite;
            z-index: 5;
        }
        @keyframes shimmer { to { background-position: -300% 0; } }

        /* Hero text */
        .hero-content {
            position: relative;
            z-index: 3;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .hero-eyebrow {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: rgba(255,255,255,.5);
            margin-bottom: .9rem;
        }
        .hero-badge {
            display: inline-block;
            background: rgba(99,102,241,.3);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(165,180,252,.35);
            border-radius: 999px;
            padding: .32rem 1rem;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .07em;
            color: #c7d2fe;
            text-transform: uppercase;
            margin-bottom: 1.1rem;
        }
        .hero-content h1 {
            font-size: 2.6rem;
            font-weight: 900;
            margin: 0 0 .55rem;
            line-height: 1.1;
            letter-spacing: -.02em;
            text-shadow: 0 4px 20px rgba(0,0,0,.4);
        }
        .hero-content p {
            font-size: 1rem;
            color: rgba(255,255,255,.7);
            margin: 0;
        }

        /* ══════════════════════════════════════════════
           PAGE CONTENT
        ══════════════════════════════════════════════ */
        .main-content {
            background: #f5f6fa;
            min-height: calc(100vh - 300px - 60px);
            padding: 2.5rem 1.5rem 4rem;
        }
        .users-page { max-width: 1100px; margin: 0 auto; }

        /* Top bar */
        .page-topbar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1.75rem;
        }
        .btn-add {
            padding: .7rem 1.5rem;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: .92rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            transition: background .2s, box-shadow .2s;
            box-shadow: 0 4px 12px rgba(16,185,129,.3);
        }
        .btn-add:hover { background: #059669; text-decoration: none; }

        /* Stats */
        .staff-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.75rem;
        }
        @media (max-width: 520px) { .staff-stats { grid-template-columns: 1fr 1fr; } }
        .staff-stat-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.15rem 1.4rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
        }
        .stat-icon-wrap {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon-wrap.indigo { background: #ede9fe; color: #6d28d9; }
        .stat-icon-wrap.green  { background: #d1fae5; color: #059669; }
        .stat-icon-wrap.blue   { background: #dbeafe; color: #1d4ed8; }
        .stat-val { font-size: 1.5rem; font-weight: 800; color: #111827; line-height: 1; }
        .stat-lbl { font-size: .75rem; color: #9ca3af; margin-top: .2rem; text-transform: uppercase; letter-spacing: .05em; }

        /* Toolbar */
        .toolbar {
            display: flex; gap: .75rem; align-items: center; flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }
        .toolbar input[type="text"] {
            padding: .65rem 1rem;
            border: 1.5px solid #d1d5db;
            border-radius: 9px;
            font-size: .9rem;
            flex: 1; min-width: 200px;
            transition: border-color .2s;
            background: #fff;
        }
        .toolbar input[type="text"]:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
        .toolbar select {
            padding: .65rem .9rem;
            border: 1.5px solid #d1d5db;
            border-radius: 9px;
            font-size: .9rem;
            background: #fff;
        }
        .btn-search {
            padding: .65rem 1.25rem;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: .9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-search:hover { background: #4f46e5; }
        .btn-clear {
            padding: .65rem 1rem;
            background: transparent;
            color: #6b7280;
            border: 1.5px solid #d1d5db;
            border-radius: 9px;
            font-size: .9rem;
            cursor: pointer;
            text-decoration: none;
            transition: border-color .2s;
        }
        .btn-clear:hover { border-color: #9ca3af; }

        /* Table card */
        .table-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,.05);
        }
        .table-card table { width: 100%; border-collapse: collapse; }
        .table-card thead tr { background: #f9fafb; }
        .table-card th {
            padding: .9rem 1.2rem;
            font-size: .75rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: .07em;
            color: #6b7280; text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .table-card td {
            padding: .95rem 1.2rem;
            font-size: .92rem; color: #374151;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        .table-card tbody tr:last-child td { border-bottom: none; }
        .table-card tbody tr:hover { background: #fafafa; }

        /* Avatar */
        .avatar {
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a78bfa);
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: .83rem;
            flex-shrink: 0;
        }
        .user-cell { display: flex; align-items: center; gap: .8rem; }
        .user-name  { font-weight: 700; color: #111827; }
        .user-login { font-size: .78rem; color: #9ca3af; }

        /* Badges */
        .badge { display: inline-block; padding: .24rem .7rem; border-radius: 20px; font-size: .75rem; font-weight: 700; }
        .badge-admin    { background: #ede9fe; color: #6d28d9; }
        .badge-staff    { background: #dbeafe; color: #1d4ed8; }
        .badge-active   { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }
        .you-badge {
            display: inline-block; padding: .1rem .48rem;
            background: #fef3c7; color: #92400e;
            border-radius: 10px; font-size: .66rem; font-weight: 800;
            margin-left: .35rem; vertical-align: middle;
        }

        /* Actions */
        .action-cell { display: flex; gap: .5rem; align-items: center; }
        .btn-toggle {
            padding: .38rem .85rem; border-radius: 7px;
            font-size: .8rem; font-weight: 700;
            border: none; cursor: pointer; transition: opacity .2s;
        }
        .btn-toggle:hover { opacity: .8; }
        .btn-toggle.deactivate { background: #fee2e2; color: #dc2626; }
        .btn-toggle.activate   { background: #d1fae5; color: #059669; }
        .btn-edit {
            padding: .38rem .85rem; border-radius: 7px;
            font-size: .8rem; font-weight: 700;
            border: 1.5px solid #e5e7eb; background: #fff; color: #374151;
            text-decoration: none; cursor: pointer; transition: border-color .2s, color .2s;
        }
        .btn-edit:hover { border-color: #6366f1; color: #6366f1; }

        /* Empty & count */
        .empty-state { text-align: center; padding: 3.5rem 1rem; color: #9ca3af; }
        .empty-state svg { margin-bottom: .75rem; opacity: .4; }
        .empty-state p { font-size: .95rem; }
        .result-count { font-size: .85rem; color: #9ca3af; margin-bottom: .75rem; }

    </style>
</head>
<body>

    <?php include("../includes/navbar.php"); ?>

    <!-- ══ Hero Banner ══ -->
    <div class="hero-banner">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>
        <div class="hero-orbs">
            <span></span><span></span><span></span>
            <span></span><span></span>
        </div>
        <div class="hero-content">
            <div class="hero-eyebrow">HostelHub &nbsp;/&nbsp; Administration</div>
            <div class="hero-badge">Admin Access</div>
            <h1>Staff Management</h1>
            <p>View, add, and manage all hostel staff accounts.</p>
        </div>
        <div class="hero-shimmer"></div>
    </div>

    <main class="main-content">
        <div class="users-page">

            <!-- Add Staff button -->
            <div class="page-topbar">
                <a href="register_staff.php" class="btn-add">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    Add Staff
                </a>
            </div>

            <!-- Stats -->
            <div class="staff-stats">
                <div class="staff-stat-card">
                    <div class="stat-icon-wrap indigo">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?= $totalCount ?></div>
                        <div class="stat-lbl">Total Users</div>
                    </div>
                </div>
                <div class="staff-stat-card">
                    <div class="stat-icon-wrap green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?= $activeCount ?></div>
                        <div class="stat-lbl">Active</div>
                    </div>
                </div>
                <div class="staff-stat-card">
                    <div class="stat-icon-wrap blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <div class="stat-val"><?= $adminCount ?></div>
                        <div class="stat-lbl">Admins</div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter -->
            <form method="GET" action="users.php">
                <div class="toolbar">
                    <input
                        type="text" name="q"
                        placeholder="Search by name or username..."
                        value="<?= htmlspecialchars($search) ?>"
                    >
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Staff</option>
                    </select>
                    <button type="submit" class="btn-search">Filter</button>
                    <?php if ($search || $filterRole): ?>
                        <a href="users.php" class="btn-clear">Clear</a>
                    <?php endif; ?>
                </div>
            </form>

            <p class="result-count">
                Showing <?= count($users) ?> of <?= $totalCount ?> user<?= $totalCount !== 1 ? 's' : '' ?>
            </p>

            <!-- Staff Table -->
            <div class="table-card">
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <p>No staff members found<?= $search ? " for &ldquo;" . htmlspecialchars($search) . "&rdquo;" : '' ?>.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Staff Member</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Member Since</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $i => $u): ?>
                                <?php
                                    $isSelf   = ((int)$u['user_id'] === (int)$_SESSION['user_id']);
                                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $u['full_name']), 0, 2)));
                                ?>
                                <tr>
                                    <td style="color:#9ca3af;font-size:.82rem;"><?= $i + 1 ?></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                                            <div>
                                                <div class="user-name">
                                                    <?= htmlspecialchars($u['full_name']) ?>
                                                    <?php if ($isSelf): ?>
                                                        <span class="you-badge">You</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="user-login">@<?= htmlspecialchars($u['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                                    <td><span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    <td style="white-space:nowrap;font-size:.85rem;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                    <td>
                                        <div class="action-cell">
                                            <a href="edit_staff.php?id=<?= $u['user_id'] ?>" class="btn-edit">Edit</a>
                                            <?php if (!$isSelf): ?>
                                                <form method="POST" action="users.php" style="margin:0;" onsubmit="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this account?')">
                                                    <input type="hidden" name="toggle_user_id" value="<?= $u['user_id'] ?>">
                                                    <button type="submit" class="btn-toggle <?= $u['is_active'] ? 'deactivate' : 'activate' ?>">
                                                        <?= $u['is_active'] ? 'Disable' : 'Enable' ?>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span style="font-size:.78rem;color:#d1d5db;padding:.4rem .6rem;">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div>
    </main>

</body>
</html>
