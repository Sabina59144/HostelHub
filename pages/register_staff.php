<?php
// pages/register_staff.php
require_once("../includes/session.php");
require_once("../includes/db.php");
requireRole('admin'); // Only admins can add staff

$errors   = [];
$success  = "";
$formData = [
    'full_name' => '',
    'username'  => '',
    'role'      => 'staff',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name']        ?? '');
    $username         = trim($_POST['username']         ?? '');
    $password         = $_POST['password']              ?? '';
    $confirm_password = $_POST['confirm_password']      ?? '';
    $role             = $_POST['role']                  ?? 'staff';

    $formData = compact('full_name', 'username', 'role');

    if (empty($full_name)) $errors['full_name'] = "Full name is required.";
    if (empty($username))  $errors['username']  = "Username is required.";

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters.";
    }

    if (empty($confirm_password)) {
        $errors['confirm_password'] = "Please confirm the password.";
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    if (!in_array($role, ['admin', 'staff'])) {
        $errors['role'] = "Invalid role selected.";
    }

    if (empty($errors['username'])) {
        $check = $db->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $check->execute([$username]);
        if ($check->fetch()) {
            $errors['username'] = "That username is already taken.";
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt   = $db->prepare(
            "INSERT INTO users (username, password, full_name, role, is_active)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([$username, $hashed, $full_name, $role]);

        $success  = "Staff member <strong>" . htmlspecialchars($full_name) . "</strong> added successfully.";
        $formData = ['full_name' => '', 'username' => '', 'role' => 'staff'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff — HostelHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>

        /* ══════════════════════════════════════════════════
           HERO BANNER
        ══════════════════════════════════════════════════ */
        .hero-banner {
            position: relative;
            width: 100%;
            height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            text-align: center;
            background: #0f0c29;
        }

        /* Animated gradient mesh base */
        .hero-bg {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            z-index: 0;
        }

        /* Animated colour blobs */
        .hero-bg::before {
            content: '';
            position: absolute;
            width: 700px; height: 700px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99,102,241,0.45) 0%, transparent 70%);
            top: -200px; left: -150px;
            animation: blobMove1 14s ease-in-out infinite alternate;
        }
        .hero-bg::after {
            content: '';
            position: absolute;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(139,92,246,0.4) 0%, transparent 70%);
            bottom: -200px; right: -100px;
            animation: blobMove2 18s ease-in-out infinite alternate;
        }
        @keyframes blobMove1 {
            0%   { transform: translate(0px, 0px)   scale(1);    }
            50%  { transform: translate(80px, 40px)  scale(1.1);  }
            100% { transform: translate(30px, -40px) scale(0.95); }
        }
        @keyframes blobMove2 {
            0%   { transform: translate(0px, 0px)    scale(1);   }
            50%  { transform: translate(-60px, -30px) scale(1.1); }
            100% { transform: translate(20px, 40px)  scale(0.9); }
        }

        /* Subtle grid texture overlay */
        .hero-overlay {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 48px 48px;
            z-index: 1;
        }

        /* Floating animated orbs */
        .hero-orbs {
            position: absolute;
            inset: 0;
            z-index: 2;
            pointer-events: none;
        }
        .hero-orbs span {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.15);
            backdrop-filter: blur(2px);
            animation: floatOrb linear infinite;
        }
        .hero-orbs span:nth-child(1)  { width:180px; height:180px; top:10%;  left:5%;   animation-duration:18s; animation-delay:0s;   }
        .hero-orbs span:nth-child(2)  { width: 90px; height: 90px; top:55%;  left:12%;  animation-duration:14s; animation-delay:-4s;  }
        .hero-orbs span:nth-child(3)  { width:130px; height:130px; top:20%;  left:75%;  animation-duration:20s; animation-delay:-7s;  }
        .hero-orbs span:nth-child(4)  { width: 60px; height: 60px; top:70%;  left:80%;  animation-duration:12s; animation-delay:-2s;  }
        .hero-orbs span:nth-child(5)  { width:220px; height:220px; top:-10%; left:40%;  animation-duration:25s; animation-delay:-10s; }
        .hero-orbs span:nth-child(6)  { width: 50px; height: 50px; top:80%;  left:45%;  animation-duration:10s; animation-delay:-6s;  }
        .hero-orbs span:nth-child(7)  { width:300px; height:300px; top:30%;  left:55%;  background: rgba(56,189,248,.08); border-color: rgba(56,189,248,.15); animation-duration:22s; animation-delay:-3s; }
        @keyframes floatOrb {
            0%   { transform: translateY(0px)   rotate(0deg);   opacity: .5; }
            50%  { transform: translateY(-30px) rotate(180deg); opacity: 1;  }
            100% { transform: translateY(0px)   rotate(360deg); opacity: .5; }
        }

        /* Shimmer line at the bottom of the banner */
        .hero-shimmer {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #a78bfa, #38bdf8, #6366f1);
            background-size: 300% 100%;
            animation: shimmer 4s linear infinite;
            z-index: 5;
        }
        @keyframes shimmer { to { background-position: -300% 0; } }

        /* Hero text content */
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
            color: rgba(255,255,255,.55);
            margin-bottom: 1rem;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(99,102,241,.35);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(165,180,252,.4);
            border-radius: 999px;
            padding: .35rem 1.1rem;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .06em;
            color: #c7d2fe;
            text-transform: uppercase;
            margin-bottom: 1.2rem;
        }

        .hero-content h1 {
            font-size: 3rem;
            font-weight: 900;
            margin: 0 0 .6rem;
            line-height: 1.1;
            letter-spacing: -.02em;
            text-shadow: 0 4px 20px rgba(0,0,0,.4);
        }

        .hero-content p {
            font-size: 1.05rem;
            color: rgba(255,255,255,.72);
            margin: 0;
            max-width: 440px;
        }

        /* ══════════════════════════════════════════════════
           MAIN LAYOUT — centered form
        ══════════════════════════════════════════════════ */
        .main-content {
            background: #f5f6fa;
            min-height: calc(100vh - 380px - 60px);
            padding: 3rem 1rem 5rem;
        }

        .form-page {
            max-width: 700px;
            margin: 0 auto;
        }

        /* ── Alerts ── */
        .alert { padding: .9rem 1.2rem; border-radius: 10px; margin-bottom: 1.75rem; font-size: .93rem; }
        .alert-success { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

        /* ── Card ── */
        .staff-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2.5rem 2.75rem;
            box-shadow: 0 4px 24px rgba(0,0,0,.07);
        }

        .card-heading {
            margin: 0 0 2rem;
            text-align: center;
        }
        .card-heading h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #111827;
            margin: 0 0 .3rem;
        }
        .card-heading p {
            font-size: .9rem;
            color: #6b7280;
            margin: 0;
        }

        /* ── Section dividers ── */
        .section-title {
            font-size: .75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #9ca3af;
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: .5rem;
            margin: 1.75rem 0 1.25rem;
        }
        .section-title:first-of-type { margin-top: 0; }

        /* ── Form layout ── */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        @media (max-width: 560px) {
            .form-row { grid-template-columns: 1fr; }
            .staff-card { padding: 1.75rem 1.25rem; }
            .hero-content h1 { font-size: 2rem; }
        }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: .83rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: .45rem;
            letter-spacing: .01em;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: .7rem .95rem;
            border: 1.5px solid #d1d5db;
            border-radius: 9px;
            font-size: .95rem;
            color: #111827;
            background: #fafafa;
            transition: border-color .2s, box-shadow .2s;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.15);
            background: #fff;
        }
        .form-group input.is-invalid,
        .form-group select.is-invalid { border-color: #ef4444; }
        .field-error { color: #dc2626; font-size: .78rem; margin-top: .3rem; display: block; }

        /* ── Password ── */
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 52px; }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            padding: 4px;
            line-height: 1;
            transition: color .2s;
        }
        .toggle-password:hover { color: #6366f1; }
        .toggle-password svg { display: block; }

        /* ── Strength bar ── */
        .strength-bar {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 7px;
            overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%;
            width: 0%;
            border-radius: 2px;
            transition: width .35s ease, background .35s ease;
        }

        /* ── Actions ── */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-primary {
            padding: .8rem 2.25rem;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: .02em;
            transition: background .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(99,102,241,.35);
        }
        .btn-primary:hover  { background: #4f46e5; box-shadow: 0 6px 18px rgba(99,102,241,.45); }
        .btn-primary:active { transform: scale(.97); }
        .btn-secondary {
            padding: .8rem 1.75rem;
            background: transparent;
            color: #6b7280;
            border: 1.5px solid #d1d5db;
            border-radius: 9px;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
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
            <span></span><span></span><span></span>
            <span></span><span></span><span></span><span></span>
        </div>
        <div class="hero-content">
            <div class="hero-eyebrow">HostelHub &nbsp;/&nbsp; Staff Management</div>
            <div class="hero-badge">Admin Access</div>
            <h1>Register Staff Member</h1>
            <p>Create a new hostel staff account with role-based access control.</p>
        </div>
        <div class="hero-shimmer"></div>
    </div>

    <main class="main-content">
        <div class="form-page">

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <div class="staff-card">

                <div class="card-heading">
                    <h2>New Staff Account</h2>
                    <p>Fill in the details below to register a new team member.</p>
                </div>

                <form method="POST" action="register_staff.php" novalidate>

                    <div class="section-title">Account Details</div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color:#ef4444">*</span></label>
                            <input
                                type="text" id="full_name" name="full_name"
                                placeholder="e.g. Jane Smith"
                                value="<?= htmlspecialchars($formData['full_name']) ?>"
                                class="<?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                                required
                            >
                            <?php if (isset($errors['full_name'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['full_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="username">Username <span style="color:#ef4444">*</span></label>
                            <input
                                type="text" id="username" name="username"
                                placeholder="e.g. jsmith"
                                value="<?= htmlspecialchars($formData['username']) ?>"
                                class="<?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                                autocomplete="off" required
                            >
                            <?php if (isset($errors['username'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['username']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group" style="max-width:320px;">
                        <label for="role">Role <span style="color:#ef4444">*</span></label>
                        <select id="role" name="role"
                            class="<?= isset($errors['role']) ? 'is-invalid' : '' ?>">
                            <option value="staff" <?= $formData['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <?php if (isset($errors['role'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['role']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="section-title">Set Password</div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password <span style="color:#ef4444">*</span></label>
                            <div class="password-wrapper">
                                <input
                                    type="password" id="password" name="password"
                                    placeholder="Min. 8 characters"
                                    class="<?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                    oninput="updateStrength(this.value)"
                                    autocomplete="new-password" required
                                >
                                <button type="button" class="toggle-password" onclick="togglePwd('password')" aria-label="Show password">
                                    <svg id="eye-password" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                            <div class="strength-bar"><div class="strength-bar-fill" id="strengthFill"></div></div>
                            <?php if (isset($errors['password'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['password']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span style="color:#ef4444">*</span></label>
                            <div class="password-wrapper">
                                <input
                                    type="password" id="confirm_password" name="confirm_password"
                                    placeholder="Repeat password"
                                    class="<?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                    autocomplete="new-password" required
                                >
                                <button type="button" class="toggle-password" onclick="togglePwd('confirm_password')" aria-label="Show confirm password">
                                    <svg id="eye-confirm_password" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Save Staff Member</button>
                        <a href="users.php" class="btn-secondary">Cancel</a>
                    </div>

                </form>
            </div>

        </div>
    </main>

    <script>
        function togglePwd(id) {
            const input = document.getElementById(id);
            const svg   = document.getElementById('eye-' + id);
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            svg.innerHTML = isHidden
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
</html> 