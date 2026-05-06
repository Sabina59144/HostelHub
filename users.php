<?php
// pages/users.php
require_once("../includes/session.php");
require_once("../includes/db.php");
requireRole('admin'); // Only admins can manage users

// ---------------------------------------------------
// Handle toggle-active (enable / disable staff)
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $toggle_id = (int) $_POST['toggle_user_id'];
    // Prevent admin from deactivating their own account
    if ($toggle_id !== (int) $_SESSION['user_id']) {
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
        $stmt->execute([$toggle_id]);
    }
    header("Location: users.php");
    exit();
}

// ---------------------------------------------------
// Fetch all staff with optional search/filter
// ---------------------------------------------------
$search     = trim($_GET['q']      ?? '');
$filterRole = trim($_GET['role']   ?? '');

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

// Stats
$totalStmt  = $db->query("SELECT COUNT(*) AS cnt FROM users");
$totalCount = $totalStmt->fetch()['cnt'];

$activeStmt  = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE is_active = 1");
$activeCount = $activeStmt->fetch()['cnt'];

$adminStmt  = $db->query("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin'");
$adminCount = $adminStmt->fetch()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management — HostelHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ── Page layout ── */
        .users-page { padding: 2rem 1.5rem 4rem; }
        .page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
        .page-header h2 { font-size: 1.6rem; font-weight: 700; color: var(--text-primary, #1a1a2e); }
        .page-header p  { color: var(--text-muted, #666); margin-top: .2rem; }

        /* ── Stats row ── */
        .staff-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.75rem; }
        @media (max-width: 520px) { .staff-stats { grid-template-columns: 1fr 1fr; } }
        .staff-stat-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            display: flex; align-items: center; gap: .85rem;
        }
        .staff-stat-card .stat-icon { font-size: 1.5rem; }
        .staff-stat-card .stat-val  { font-size: 1.4rem; font-weight: 700; color: #1a1a2e; line-height: 1; }
        .staff-stat-card .stat-lbl  { font-size: .78rem; color: #9ca3af; margin-top: .15rem; text-transform: uppercase; letter-spacing: .04em; }

        /* ── Toolbar ── */
        .toolbar {
            display: flex; gap: .75rem; align-items: center; flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }
        .toolbar input[type="text"] {
            padding: .6rem .9rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: .9rem;
            flex: 1; min-width: 180px;
            transition: border-color .2s;
        }
        .toolbar input[type="text"]:focus { outline: none; border-color: #6366f1; }
        .toolbar select {
            padding: .6rem .85rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: .9rem;
            background: #fff;
        }
        .toolbar .btn-search {
            padding: .62rem 1.2rem;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
        }
        .toolbar .btn-search:hover { background: #4f46e5; }
        .toolbar .btn-clear {
            padding: .62rem 1rem;
            background: transparent;
            color: #6b7280;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: .9rem;
            cursor: pointer;
            text-decoration: none;
        }

        /* ── Add button ── */
        .btn-add {
            padding: .68rem 1.4rem;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .92rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            transition: background .2s;
            white-space: nowrap;
        }
        .btn-add:hover { background: #059669; }

        /* ── Table card ── */
        .table-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .table-card table { width: 100%; border-collapse: collapse; }
        .table-card thead tr { background: #f9fafb; }
        .table-card th {
            padding: .85rem 1.1rem;
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6b7280;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .table-card td {
            padding: .9rem 1.1rem;
            font-size: .92rem;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        .table-card tbody tr:last-child td { border-bottom: none; }
        .table-card tbody tr:hover { background: #f9fafb; }

        /* Avatar */
        .avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a78bfa);
            display: inline-flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: .85rem;
            flex-shrink: 0;
        }
        .user-cell { display: flex; align-items: center; gap: .75rem; }
        .user-cell .user-name  { font-weight: 600; color: #111827; }
        .user-cell .user-login { font-size: .78rem; color: #9ca3af; }

        /* Role badge */
        .badge {
            display: inline-block;
            padding: .22rem .65rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .03em;
        }
        .badge-admin { background: #ede9fe; color: #6d28d9; }
        .badge-staff { background: #dbeafe; color: #1d4ed8; }

        /* Status badge */
        .badge-active   { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }

        /* Actions */
        .action-cell { display: flex; gap: .5rem; align-items: center; }
        .btn-toggle {
            padding: .38rem .85rem;
            border-radius: 6px;
            font-size: .8rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: opacity .2s;
        }
        .btn-toggle:hover { opacity: .8; }
        .btn-toggle.deactivate { background: #fee2e2; color: #dc2626; }
        .btn-toggle.activate   { background: #d1fae5; color: #059669; }
        .btn-edit {
            padding: .38rem .85rem;
            border-radius: 6px;
            font-size: .8rem;
            font-weight: 600;
            border: 1.5px solid #e5e7eb;
            background: #fff;
            color: #374151;
            text-decoration: none;
            cursor: pointer;
            transition: border-color .2s;
        }
        .btn-edit:hover { border-color: #6366f1; color: #6366f1; }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3.5rem 1rem;
            color: #9ca3af;
        }
        .empty-state .empty-icon { font-size: 2.5rem; margin-bottom: .75rem; }
        .empty-state p { font-size: .95rem; }

        /* Current user indicator */
        .you-badge {
            display: inline-block;
            padding: .12rem .5rem;
            background: #fef3c7;
            color: #92400e;
            border-radius: 10px;
            font-size: .68rem;
            font-weight: 700;
            margin-left: .4rem;
            vertical-align: middle;
        }

        /* Result count */
        .result-count { font-size: .85rem; color: #9ca3af; margin-bottom: .75rem; }
    </style>
</head>
<body>

    <?php include("../includes/navbar.php"); ?>

    <main class="main-content">
        <div class="users-page">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h2>👥 Staff Management</h2>
                    <p>View, add, and manage hostel staff accounts.</p>
                </div>
                <a href="register_staff.php" class="btn-add">＋ Add Staff</a>
            </div>

            <!-- Stats -->
            <div class="staff-stats">
                <div class="staff-stat-card">
                    <span class="stat-icon">👤</span>
                    <div>
                        <div class="stat-val"><?= $totalCount ?></div>
                        <div class="stat-lbl">Total Users</div>
                    </div>
                </div>
                <div class="staff-stat-card">
                    <span class="stat-icon">✅</span>
                    <div>
                        <div class="stat-val"><?= $activeCount ?></div>
                        <div class="stat-lbl">Active</div>
                    </div>
                </div>
                <div class="staff-stat-card">
                    <span class="stat-icon">🛡</span>
                    <div>
                        <div class="stat-val"><?= $adminCount ?></div>
                        <div class="stat-lbl">Admins</div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter Toolbar -->
            <form method="GET" action="users.php">
                <div class="toolbar">
                    <input
                        type="text"
                        name="q"
                        placeholder="🔍  Search by name or username…"
                        value="<?= htmlspecialchars($search) ?>"
                    >
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $filterRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $filterRole === 'staff' ? 'selected' : '' ?>>Staff</option>
                    </select>
                    <button type="submit" class="btn-search">Filter</button>
                    <?php if ($search || $filterRole): ?>
                        <a href="users.php" class="btn-clear">✕ Clear</a>
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
                        <div class="empty-icon">🔍</div>
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
                                    $isSelf    = ((int) $u['user_id'] === (int) $_SESSION['user_id']);
                                    $initials  = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $u['full_name']), 0, 2)));
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
                                    <td>
                                        <span class="badge badge-<?= $u['role'] ?>">
                                            <?= ucfirst(htmlspecialchars($u['role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $u['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap;font-size:.85rem;">
                                        <?= date('d M Y', strtotime($u['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div class="action-cell">
                                            <a href="edit_staff.php?id=<?= $u['user_id'] ?>" class="btn-edit">Edit</a>

                                            <?php if (!$isSelf): ?>
                                                <form method="POST" action="users.php" style="margin:0;" onsubmit="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this account?')">
                                                    <input type="hidden" name="toggle_user_id" value="<?= $u['user_id'] ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn-toggle <?= $u['is_active'] ? 'deactivate' : 'activate' ?>"
                                                    >
                                                        <?= $u['is_active'] ? ' Disable' : ' Enable' ?>
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
            </div><!-- /.table-card -->

        </div><!-- /.users-page -->
    </main>

</body>
</html>