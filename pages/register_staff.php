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

// ---------------------------------------------------
// Handle form submission
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name']        ?? '');
    $username         = trim($_POST['username']         ?? '');
    $password         = $_POST['password']              ?? '';
    $confirm_password = $_POST['confirm_password']      ?? '';
    $role             = $_POST['role']                  ?? 'staff';

    // Keep values for re-population
    $formData = compact('full_name', 'username', 'role');

    // --- Validation ---
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

    // --- Username uniqueness check ---
    if (empty($errors['username'])) {
        $check = $db->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $check->execute([$username]);
        if ($check->fetch()) {
            $errors['username'] = "That username is already taken.";
        }
    }

    // --- Save to DB if no errors ---
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt   = $db->prepare(
            "INSERT INTO users (username, password, full_name, role, is_active)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([$username, $hashed, $full_name, $role]);

        $success  = "Staff member <strong>" . htmlspecialchars($full_name) . "</strong> added successfully.";
        $formData = ['full_name' => '', 'username' => '', 'role' => 'staff']; // reset form
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
        /* ── Hero Banner ─────────────────────────────────────────── */
        .hero-banner {
            position: relative;
            width: 100%;
            height: 220px;
            background:
                linear-gradient(135deg, rgba(15,23,42,.72) 0%, rgba(79,70,229,.55) 100%),
                url('https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=1400&auto=format&fit=crop&q=80')
                center/cover no-repeat;
            display: flex;
            align-items: center;
            overflow: hidden;
        }
        .hero-banner::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #06b6d4, #6366f1);
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
        }
        @keyframes shimmer { to { background-position: -200% 0; } }
        .hero-inner {
            max-width: 860px;
            margin: 0 auto;
            padding: 0 2rem;
            color: #fff;
        }
        .hero-breadcrumb {
            font-size: .75rem;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: rgba(255,255,255,.65);
            margin-bottom: .6rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .hero-breadcrumb span { color: rgba(255,255,255,.4); }
        .hero-inner h1 {
            font-size: 1.85rem;
            font-weight: 800;
            margin: 0 0 .4rem;
            line-height: 1.2;
            text-shadow: 0 2px 8px rgba(0,0,0,.35);
        }
        .hero-inner p {
            font-size: .95rem;
            color: rgba(255,255,255,.8);
            margin: 0;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            background: rgba(255,255,255,.15);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 999px;
            padding: .3rem .85rem;
            font-size: .78rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: .85rem;
        }

        /* ── Form page ──────────────────────────────────────────── */
        .form-page { max-width: 680px; margin: 0 auto; padding: 2rem 1rem 4rem; }

        .form-page .page-header { margin-bottom: 2rem; }
        .form-page .page-header h2 { font-size: 1.6rem; font-weight: 700; color: var(--text-primary, #1a1a2e); }
        .form-page .page-header p  { color: var(--text-muted, #666); margin-top: .25rem; }

        /* Card */
        .staff-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 2rem 2.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        .section-title {
            font-size: .8rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; color: #9ca3af;
            border-bottom: 1px solid #f3f4f6; padding-bottom: .5rem;
            margin: 1.5rem 0 1.1rem;
        }
        .section-title:first-child { margin-top: 0; }

        /* Alerts */
        .alert { padding: .85rem 1.1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: .93rem; }
        .alert-success { background: #ecfdf5; border: 1px solid #6ee7b7; color: #065f46; }
        .alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

        /* Form layout */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 540px) { .form-row { grid-template-columns: 1fr; } }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block; font-size: .85rem; font-weight: 600;
            color: #374151; margin-bottom: .4rem;
        }
        .form-group input,
        .form-group select {
            width: 100%; padding: .65rem .85rem;
            border: 1.5px solid #d1d5db; border-radius: 8px;
            font-size: .95rem; color: #111827; background: #fafafa;
            transition: border-color .2s, box-shadow .2s; box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none; border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.15); background: #fff;
        }
        .form-group input.is-invalid,
        .form-group select.is-invalid { border-color: #ef4444; }
        .field-error { color: #dc2626; font-size: .8rem; margin-top: .3rem; display: block; }

        /* Password wrapper */
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 44px; }
        .toggle-password {
            position: absolute; right: 10px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            font-size: 16px; opacity: .6; transition: opacity .2s;
        }
        .toggle-password:hover { opacity: 1; }

        /* Strength bar */
        .strength-bar {
            height: 4px; background: #e5e7eb; border-radius: 2px;
            margin-top: 6px; overflow: hidden;
        }
        .strength-bar-fill {
            height: 100%; width: 0%; border-radius: 2px;
            transition: width .3s, background .3s;
        }

        /* Actions */
        .form-actions { display: flex; gap: 1rem; margin-top: 1.75rem; flex-wrap: wrap; }
        .btn-primary {
            padding: .72rem 1.75rem; background: #6366f1; color: #fff;
            border: none; border-radius: 8px; font-size: .95rem; font-weight: 600;
            cursor: pointer; transition: background .2s, transform .1s;
        }
        .btn-primary:hover  { background: #4f46e5; }
        .btn-primary:active { transform: scale(.98); }
        .btn-secondary {
            padding: .72rem 1.5rem; background: transparent; color: #6b7280;
            border: 1.5px solid #d1d5db; border-radius: 8px; font-size: .95rem;
            font-weight: 600; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center;
            transition: border-color .2s, color .2s;
        }
        .btn-secondary:hover { border-color: #9ca3af; color: #374151; text-decoration: none; }
    </style>
</head>
<body>

    <?php include("../includes/navbar.php"); ?>

    <!-- ── Hero Banner ── -->
    <div class="hero-banner">
        <div class="hero-inner">
            <div class="hero-breadcrumb">
                🏠 HostelHub <span>/</span> Staff Management <span>/</span> Register
            </div>
            <div class="hero-badge">👤 Admin Access</div>
            <h1>Add Staff Member</h1>
            <p>Create a new hostel staff account with role-based access.</p>
        </div>
    </div>

    <main class="main-content">
        <div class="form-page">

            <div class="page-header">
                <h2>➕ Add Staff Member</h2>
                <p>Create a new hostel staff account.</p>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>

            <div class="staff-card">
                <form method="POST" action="register_staff.php" novalidate>

                    <div class="section-title">Account Details</div>

                    <!-- Full Name + Username -->
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

                    <!-- Role -->
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

                    <!-- Password + Confirm -->
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
                                <button type="button" class="toggle-password" onclick="togglePwd('password')">👁</button>
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
                                <button type="button" class="toggle-password" onclick="togglePwd('confirm_password')">👁</button>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">💾 Save Staff Member</button>
                        <a href="users.php" class="btn-secondary">← Cancel</a>
                    </div>

                </form>
            </div>

        </div>
    </main>

    <script>
        function togglePwd(id) {
            const el = document.getElementById(id);
            el.type = el.type === 'password' ? 'text' : 'password';
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