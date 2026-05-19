<?php
// pages/edit_staff.php
require_once("../includes/session.php");
require_once("../includes/db.php");
requireRole('admin');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { header("Location: users.php"); exit(); }

$stmt = $db->prepare("SELECT user_id, username, full_name, role, is_active, created_at FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$id]);
$staff = $stmt->fetch();
if (!$staff) { header("Location: users.php"); exit(); }

$errors  = [];
$success = "";

$formData = [
    'full_name' => $staff['full_name'],
    'username'  => $staff['username'],
    'role'      => $staff['role'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name']   ?? '');
    $username         = trim($_POST['username']    ?? '');
    $role             = $_POST['role']             ?? 'staff';
    $new_password     = $_POST['new_password']     ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $formData = compact('full_name', 'username', 'role');

    if (empty($full_name)) $errors['full_name'] = "Full name is required.";
    if (empty($username))  $errors['username']  = "Username is required.";
    if (!in_array($role, ['admin', 'staff'])) $errors['role'] = "Invalid role.";

    if (empty($errors['username'])) {
        $check = $db->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ? LIMIT 1");
        $check->execute([$username, $id]);
        if ($check->fetch()) $errors['username'] = "That username is already taken.";
    }

    if ($new_password !== '') {
        if (strlen($new_password) < 8) {
            $errors['new_password'] = "Password must be at least 8 characters.";
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match.";
        }
    }

    if (empty($errors)) {
        if ($new_password !== '') {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET full_name=?, username=?, role=?, password=? WHERE user_id=?");
            $stmt->execute([$full_name, $username, $role, $hashed, $id]);
        } else {
            $stmt = $db->prepare("UPDATE users SET full_name=?, username=?, role=? WHERE user_id=?");
            $stmt->execute([$full_name, $username, $role, $id]);
        }
        $success = "Staff member updated successfully.";
        $stmt = $db->prepare("SELECT user_id, username, full_name, role, is_active, created_at FROM users WHERE user_id=? LIMIT 1");
        $stmt->execute([$id]);
        $staff    = $stmt->fetch();
        $formData = ['full_name' => $staff['full_name'], 'username' => $staff['username'], 'role' => $staff['role']];
    }
}

$isSelf   = ((int)$staff['user_id'] === (int)$_SESSION['user_id']);
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $staff['full_name']), 0, 2)));
$memberSince = date('d M Y', strtotime($staff['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff — HostelHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>

        /* ══════════════════════════════════════════════
           HERO BANNER
        ══════════════════════════════════════════════ */
        .hero-banner {
            position: relative;
            width: 100%;
            height: 260px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            text-align: center;
            background: #0f0c29;
        }
        .hero-bg {
            position: absolute; inset: 0;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            z-index: 0;
        }
        .hero-bg::before {
            content: '';
            position: absolute;
            width: 600px; height: 600px; border-radius: 50%;
            background: radial-gradient(circle, rgba(99,102,241,.45) 0%, transparent 70%);
            top: -180px; left: -100px;
            animation: blob1 16s ease-in-out infinite alternate;
        }
        .hero-bg::after {
            content: '';
            position: absolute;
            width: 500px; height: 500px; border-radius: 50%;
            background: radial-gradient(circle, rgba(139,92,246,.35) 0%, transparent 70%);
            bottom: -160px; right: -60px;
            animation: blob2 20s ease-in-out infinite alternate;
        }
        @keyframes blob1 {
            0%   { transform: translate(0,0) scale(1); }
            50%  { transform: translate(60px,30px) scale(1.1); }
            100% { transform: translate(20px,-35px) scale(.95); }
        }
        @keyframes blob2 {
            0%   { transform: translate(0,0) scale(1); }
            50%  { transform: translate(-45px,-20px) scale(1.1); }
            100% { transform: translate(12px,30px) scale(.9); }
        }
        .hero-overlay {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
            background-size: 48px 48px;
            z-index: 1;
        }
        .hero-orbs {
            position: absolute; inset: 0; z-index: 2; pointer-events: none;
        }
        .hero-orbs span {
            position: absolute; border-radius: 50%;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            animation: floatOrb linear infinite;
        }
        .hero-orbs span:nth-child(1) { width:140px; height:140px; top:5%;  left:2%;   animation-duration:18s; }
        .hero-orbs span:nth-child(2) { width: 70px; height: 70px; top:55%; left:8%;   animation-duration:14s; animation-delay:-4s; }
        .hero-orbs span:nth-child(3) { width:110px; height:110px; top:10%; left:78%;  animation-duration:20s; animation-delay:-7s; }
        .hero-orbs span:nth-child(4) { width:220px; height:220px; top:-20%;left:40%;  background:rgba(56,189,248,.05); animation-duration:24s; animation-delay:-10s; }
        @keyframes floatOrb {
            0%   { transform: translateY(0)    rotate(0deg);   opacity:.5; }
            50%  { transform: translateY(-25px) rotate(180deg); opacity:1;  }
            100% { transform: translateY(0)    rotate(360deg); opacity:.5; }
        }
        .hero-shimmer {
            position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #6366f1, #a78bfa, #38bdf8, #6366f1);
            background-size: 300% 100%;
            animation: shimmer 4s linear infinite; z-index: 5;
        }
        @keyframes shimmer { to { background-position: -300% 0; } }
        .hero-content {
            position: relative; z-index: 3; color: #fff;
            display: flex; flex-direction: column; align-items: center;
        }
        .hero-eyebrow {
            font-size: .72rem; font-weight: 700; letter-spacing: .14em;
            text-transform: uppercase; color: rgba(255,255,255,.5); margin-bottom: .85rem;
        }
        .hero-badge {
            display: inline-block;
            background: rgba(99,102,241,.3); backdrop-filter: blur(8px);
            border: 1px solid rgba(165,180,252,.35); border-radius: 999px;
            padding: .3rem 1rem; font-size: .74rem; font-weight: 700;
            letter-spacing: .07em; color: #c7d2fe; text-transform: uppercase; margin-bottom: 1rem;
        }
        .hero-content h1 {
            font-size: 2.4rem; font-weight: 900; margin: 0 0 .5rem;
            line-height: 1.1; letter-spacing: -.02em;
            text-shadow: 0 4px 20px rgba(0,0,0,.4);
        }
        .hero-content p { font-size: .95rem; color: rgba(255,255,255,.65); margin: 0; }

        /* ══════════════════════════════════════════════
           LAYOUT
        ══════════════════════════════════════════════ */
        .main-content {
            background: #f1f3f8;
            min-height: calc(100vh - 260px - 60px);
            padding: 2.5rem 1.5rem 5rem;
        }
        .edit-layout {
            max-width: 1020px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 1.75rem;
            align-items: start;
        }
        @media (max-width: 780px) {
            .edit-layout { grid-template-columns: 1fr; }
        }

        /* ══════════════════════════════════════════════
           PROFILE SIDEBAR
        ══════════════════════════════════════════════ */
        .profile-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,.07);
        }
        .profile-card-header {
            background: linear-gradient(135deg, #312e81 0%, #4f46e5 100%);
            padding: 2rem 1.5rem 3.5rem;
            text-align: center;
            position: relative;
        }
        /* Large human silhouette background watermark */
        .profile-card-header::before {
            content: '';
            position: absolute;
            bottom: -1px; left: 50%; transform: translateX(-50%);
            width: 120px; height: 60px;
            background: #fff;
            border-radius: 120px 120px 0 0;
        }

        .profile-avatar-wrap {
            position: relative;
            display: inline-block;
            margin-bottom: .5rem;
        }
        .profile-avatar {
            width: 88px; height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #818cf8, #c4b5fd);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.9rem; font-weight: 900;
            border: 4px solid rgba(255,255,255,.35);
            box-shadow: 0 8px 24px rgba(0,0,0,.25);
            position: relative; z-index: 1;
            letter-spacing: -.02em;
        }
        /* Subtle ring pulse */
        .profile-avatar-wrap::after {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,.2);
            animation: pulse-ring 2.5s ease-out infinite;
        }
        @keyframes pulse-ring {
            0%   { transform: scale(1);   opacity: .6; }
            100% { transform: scale(1.2); opacity: 0;  }
        }

        /* Status dot */
        .status-dot {
            position: absolute; bottom: 4px; right: 4px;
            width: 16px; height: 16px; border-radius: 50%;
            border: 3px solid #fff;
            background: <?php echo $staff['is_active'] ? '#10b981' : '#9ca3af'; ?>;
            z-index: 2;
        }

        .profile-body {
            padding: 1rem 1.5rem 1.75rem;
            text-align: center;
            margin-top: .5rem;
        }
        .profile-name {
            font-size: 1.15rem; font-weight: 800; color: #111827;
            margin-bottom: .25rem;
        }
        .profile-username {
            font-size: .82rem; color: #9ca3af; margin-bottom: .85rem;
        }

        .profile-badges {
            display: flex; justify-content: center; gap: .5rem; flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .badge {
            display: inline-block; padding: .28rem .75rem;
            border-radius: 999px; font-size: .73rem; font-weight: 700;
        }
        .badge-admin    { background: #ede9fe; color: #6d28d9; }
        .badge-staff    { background: #dbeafe; color: #1d4ed8; }
        .badge-active   { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }
        .you-badge {
            background: #fef3c7; color: #92400e;
            padding: .28rem .75rem; border-radius: 999px;
            font-size: .73rem; font-weight: 700;
        }

        .profile-meta {
            border-top: 1px solid #f3f4f6;
            padding-top: 1.1rem;
            text-align: left;
        }
        .meta-row {
            display: flex; align-items: center; gap: .65rem;
            padding: .5rem 0;
            font-size: .83rem; color: #6b7280;
            border-bottom: 1px solid #f9fafb;
        }
        .meta-row:last-child { border-bottom: none; }
        .meta-row svg { color: #a78bfa; flex-shrink: 0; }
        .meta-row strong { color: #374151; }

        /* Human silhouette illustration inside header */
        .human-silhouette {
            position: absolute;
            bottom: 12px; left: 50%; transform: translateX(-50%);
            opacity: .12;
            z-index: 0;
        }

        /* ══════════════════════════════════════════════
           FORM CARD
        ══════════════════════════════════════════════ */
        .form-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2.25rem 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.07);
        }
        .form-card-title {
            font-size: 1.1rem; font-weight: 800; color: #111827;
            margin: 0 0 .25rem;
        }
        .form-card-sub {
            font-size: .85rem; color: #9ca3af; margin: 0 0 1.75rem;
        }

        .section-title {
            font-size: .72rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: .09em; color: #9ca3af;
            border-bottom: 1px solid #f3f4f6; padding-bottom: .5rem;
            margin: 1.75rem 0 1.25rem;
        }
        .section-title:first-of-type { margin-top: 0; }
        .section-title small {
            font-weight: 400; text-transform: none;
            letter-spacing: 0; color: #d1d5db; font-size: .78rem;
        }

        /* Alerts */
        .alert { padding: .9rem 1.2rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: .92rem; display: flex; align-items: center; gap: .6rem; }
        .alert-success { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

        /* Form grid */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 560px) { .form-row { grid-template-columns: 1fr; } }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block; font-size: .82rem; font-weight: 700;
            color: #374151; margin-bottom: .45rem; letter-spacing: .01em;
        }
        .form-group input,
        .form-group select {
            width: 100%; padding: .7rem .95rem;
            border: 1.5px solid #d1d5db; border-radius: 9px;
            font-size: .93rem; color: #111827; background: #fafafa;
            transition: border-color .2s, box-shadow .2s; box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none; border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.15); background: #fff;
        }
        .form-group input.is-invalid,
        .form-group select.is-invalid { border-color: #ef4444; }
        .field-error { color: #dc2626; font-size: .78rem; margin-top: .3rem; display: block; }
        .field-hint  { color: #9ca3af; font-size: .78rem; margin-top: .3rem; display: block; }

        /* Password */
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 48px; }
        .toggle-password {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: #9ca3af; padding: 4px; line-height: 1; transition: color .2s;
        }
        .toggle-password:hover { color: #6366f1; }

        /* Strength bar */
        .strength-bar { height: 4px; background: #e5e7eb; border-radius: 2px; margin-top: 7px; overflow: hidden; }
        .strength-bar-fill { height: 100%; width: 0; border-radius: 2px; transition: width .35s, background .35s; }

        /* Actions */
        .form-actions { display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap; }
        .btn-primary {
            padding: .78rem 2rem; background: #6366f1; color: #fff;
            border: none; border-radius: 9px; font-size: .93rem; font-weight: 700;
            cursor: pointer; transition: background .2s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(99,102,241,.35);
            display: inline-flex; align-items: center; gap: .5rem;
        }
        .btn-primary:hover { background: #4f46e5; box-shadow: 0 6px 18px rgba(99,102,241,.45); }
        .btn-secondary {
            padding: .78rem 1.6rem; background: transparent; color: #6b7280;
            border: 1.5px solid #d1d5db; border-radius: 9px; font-size: .93rem;
            font-weight: 600; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: .4rem;
            transition: border-color .2s, color .2s;
        }
        .btn-secondary:hover { border-color: #9ca3af; color: #374151; text-decoration: none; }

    </style>
</head>
<body>

    <?php include("../includes/navbar.php"); ?>

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="hero-bg"></div>
        <div class="hero-overlay"></div>
        <div class="hero-orbs">
            <span></span><span></span><span></span><span></span>
        </div>
        <div class="hero-content">
            <div class="hero-eyebrow">HostelHub &nbsp;/&nbsp; Staff Management</div>
            <div class="hero-badge">Admin Access</div>
            <h1>Edit Staff Member</h1>
            <p>Update account details for <?= htmlspecialchars($staff['full_name']) ?></p>
        </div>
        <div class="hero-shimmer"></div>
    </div>

    <main class="main-content">
        <div class="edit-layout">

            <!-- ── Profile Sidebar ── -->
            <aside class="profile-card">
                <div class="profile-card-header">
                    <!-- Faint human silhouette SVG watermark -->
                    <svg class="human-silhouette" width="160" height="160" viewBox="0 0 24 24" fill="white">
                        <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                    </svg>
                    <div class="profile-avatar-wrap">
                        <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                        <div class="status-dot"></div>
                    </div>
                </div>

                <div class="profile-body">
                    <div class="profile-name"><?= htmlspecialchars($staff['full_name']) ?></div>
                    <div class="profile-username">@<?= htmlspecialchars($staff['username']) ?></div>

                    <div class="profile-badges">
                        <span class="badge badge-<?= $staff['role'] ?>"><?= ucfirst($staff['role']) ?></span>
                        <span class="badge <?= $staff['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $staff['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                        <?php if ($isSelf): ?>
                            <span class="you-badge">You</span>
                        <?php endif; ?>
                    </div>

                    <div class="profile-meta">
                        <div class="meta-row">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>Joined <strong><?= $memberSince ?></strong></span>
                        </div>
                        <div class="meta-row">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span>ID: <strong>#<?= $staff['user_id'] ?></strong></span>
                        </div>
                        <div class="meta-row">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>Role: <strong><?= ucfirst($staff['role']) ?></strong></span>
                        </div>
                        <div class="meta-row">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><?php if ($staff['is_active']): ?><polyline points="20 6 9 17 4 12"/><?php else: ?><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/><?php endif; ?></svg>
                            <span>Status: <strong><?= $staff['is_active'] ? 'Active' : 'Inactive' ?></strong></span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- ── Edit Form ── -->
            <div class="form-card">
                <p class="form-card-title">Account Information</p>
                <p class="form-card-sub">Changes are saved immediately to the database.</p>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="edit_staff.php?id=<?= $id ?>" novalidate>

                    <div class="section-title">Personal Details</div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color:#ef4444">*</span></label>
                            <input type="text" id="full_name" name="full_name"
                                value="<?= htmlspecialchars($formData['full_name']) ?>"
                                class="<?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['full_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="username">Username <span style="color:#ef4444">*</span></label>
                            <input type="text" id="username" name="username"
                                value="<?= htmlspecialchars($formData['username']) ?>"
                                class="<?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                                autocomplete="off" required>
                            <?php if (isset($errors['username'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['username']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group" style="max-width:300px;">
                        <label for="role">Role <span style="color:#ef4444">*</span></label>
                        <select id="role" name="role"
                            class="<?= isset($errors['role']) ? 'is-invalid' : '' ?>"
                            <?= $isSelf ? 'disabled' : '' ?>>
                            <option value="staff" <?= $formData['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <?php if ($isSelf): ?>
                            <span class="field-hint">You cannot change your own role.</span>
                            <input type="hidden" name="role" value="<?= htmlspecialchars($formData['role']) ?>">
                        <?php endif; ?>
                    </div>

                    <div class="section-title">Change Password <small>— leave blank to keep current</small></div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" name="new_password"
                                    placeholder="Min. 8 characters"
                                    class="<?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                                    oninput="updateStrength(this.value)"
                                    autocomplete="new-password">
                                <button type="button" class="toggle-password" onclick="togglePwd('new_password','eye-new')">
                                    <svg id="eye-new" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <div class="strength-bar"><div class="strength-bar-fill" id="strengthFill"></div></div>
                            <?php if (isset($errors['new_password'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['new_password']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password"
                                    placeholder="Repeat new password"
                                    class="<?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                    autocomplete="new-password">
                                <button type="button" class="toggle-password" onclick="togglePwd('confirm_password','eye-confirm')">
                                    <svg id="eye-confirm" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Changes
                        </button>
                        <a href="users.php" class="btn-secondary">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back to Staff List
                        </a>
                    </div>

                </form>
            </div>

        </div>
    </main>

    <script>
        function togglePwd(inputId, svgId) {
            const input = document.getElementById(inputId);
            const svg   = document.getElementById(svgId);
            const show  = input.type === 'password';
            input.type  = show ? 'text' : 'password';
            svg.innerHTML = show
                ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
                : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
        }

        function updateStrength(val) {
            const fill = document.getElementById('strengthFill');
            let score = 0;
            if (val.length >= 8)          score++;
            if (/[A-Z]/.test(val))        score++;
            if (/[0-9]/.test(val))        score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const map = {
                0: { w: '0%',   c: '#ef4444' },
                1: { w: '25%',  c: '#ef4444' },
                2: { w: '50%',  c: '#f59e0b' },
                3: { w: '75%',  c: '#3b82f6' },
                4: { w: '100%', c: '#10b981' },
            };
            fill.style.width      = map[score].w;
            fill.style.background = map[score].c;
        }
    </script>

</body>
</html>
