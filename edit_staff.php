<?php
// pages/edit_staff.php
require_once("../includes/session.php");
require_once("../includes/db.php");
requireRole('admin');

// ---------------------------------------------------
// Load the staff member to edit
// ---------------------------------------------------
$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header("Location: users.php");
    exit();
}

$stmt = $db->prepare("SELECT user_id, username, full_name, role, is_active FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$id]);
$staff = $stmt->fetch();

if (!$staff) {
    header("Location: users.php");
    exit();
}

$errors  = [];
$success = "";

// Pre-fill form with existing data
$formData = [
    'full_name' => $staff['full_name'],
    'username'  => $staff['username'],
    'role'      => $staff['role'],
];

// ---------------------------------------------------
// Handle form submission
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = trim($_POST['full_name']   ?? '');
    $username         = trim($_POST['username']    ?? '');
    $role             = $_POST['role']             ?? 'staff';
    $new_password     = $_POST['new_password']     ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $formData = compact('full_name', 'username', 'role');

    // --- Validation ---
    if (empty($full_name)) $errors['full_name'] = "Full name is required.";
    if (empty($username))  $errors['username']  = "Username is required.";
    if (!in_array($role, ['admin', 'staff'])) $errors['role'] = "Invalid role.";

    // Username uniqueness — exclude current user
    if (empty($errors['username'])) {
        $check = $db->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ? LIMIT 1");
        $check->execute([$username, $id]);
        if ($check->fetch()) {
            $errors['username'] = "That username is already taken.";
        }
    }

    // Password only validated if user typed something
    if ($new_password !== '') {
        if (strlen($new_password) < 8) {
            $errors['new_password'] = "Password must be at least 8 characters.";
        } elseif ($new_password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match.";
        }
    }

    // --- Update DB if no errors ---
    if (empty($errors)) {
        if ($new_password !== '') {
            // Update with new password
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, password = ? WHERE user_id = ?");
            $stmt->execute([$full_name, $username, $role, $hashed, $id]);
        } else {
            // Update without touching password
            $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, role = ? WHERE user_id = ?");
            $stmt->execute([$full_name, $username, $role, $id]);
        }

        $success = "Staff member updated successfully.";

        // Refresh staff data
        $stmt = $db->prepare("SELECT user_id, username, full_name, role, is_active FROM users WHERE user_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $staff = $stmt->fetch();
        $formData = ['full_name' => $staff['full_name'], 'username' => $staff['username'], 'role' => $staff['role']];
    }
}

$isSelf = ((int) $staff['user_id'] === (int) $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff — HostelHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .form-page { max-width: 680px; margin: 0 auto; padding: 2rem 1rem 4rem; }

        .form-page .page-header { margin-bottom: 2rem; }
        .form-page .page-header h2 { font-size: 1.6rem; font-weight: 700; color: var(--text-primary, #1a1a2e); }
        .form-page .page-header p  { color: var(--text-muted, #666); margin-top: .25rem; }

        /* Staff identity badge */
        .staff-badge {
            display: flex; align-items: center; gap: 1rem;
            background: #f9fafb; border: 1px solid #e5e7eb;
            border-radius: 10px; padding: .85rem 1.1rem;
            margin-bottom: 1.75rem;
        }
        .avatar {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a78bfa);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: .9rem; flex-shrink: 0;
        }
        .staff-badge .name  { font-weight: 600; color: #111827; }
        .staff-badge .login { font-size: .82rem; color: #9ca3af; }

        /* Card */
        .staff-card {
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 12px; padding: 2rem 2.25rem;
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

        /* Form */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
        @media (max-width: 540px) { .form-row { grid-template-columns: 1fr; } }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block; font-size: .85rem; font-weight: 600;
            color: #374151; margin-bottom: .4rem; letter-spacing: .01em;
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

        /* Password hint */
        .field-hint { font-size: .78rem; color: #9ca3af; margin-top: .3rem; display: block; }

        /* Password wrapper */
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 2.8rem; }
        .password-wrapper .toggle-password {
            position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            font-size: 1rem; color: #9ca3af; padding: 0; line-height: 1;
        }
        .password-wrapper .toggle-password:hover { color: #6366f1; }

        /* Strength bar */
        .strength-bar { height: 4px; border-radius: 2px; margin-top: .4rem; background: #e5e7eb; overflow: hidden; }
        .strength-bar-fill { height: 100%; width: 0; border-radius: 2px; transition: width .3s, background .3s; }

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
        .btn-secondary:hover { border-color: #9ca3af; color: #374151; }
    </style>
</head>
<body>

    <?php include("../includes/navbar.php"); ?>

    <main class="main-content">
        <div class="form-page">

            <div class="page-header">
                <h2>Edit Staff Member</h2>
                <p>Update details for this staff account.</p>
            </div>

            <!-- Who we're editing -->
            <?php
                $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice(explode(' ', $staff['full_name']), 0, 2)));
            ?>
            <div class="staff-badge">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                <div>
                    <div class="name"><?= htmlspecialchars($staff['full_name']) ?></div>
                    <div class="login">@<?= htmlspecialchars($staff['username']) ?> &mdash; <?= ucfirst($staff['role']) ?></div>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="staff-card">
                <form method="POST" action="edit_staff.php?id=<?= $id ?>" novalidate>

                    <div class="section-title">Account Details</div>

                    <!-- Full Name + Username -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color:#ef4444">*</span></label>
                            <input
                                type="text" id="full_name" name="full_name"
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
                            class="<?= isset($errors['role']) ? 'is-invalid' : '' ?>"
                            <?= $isSelf ? 'disabled' : '' ?>>
                            <option value="staff" <?= $formData['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <?php if ($isSelf): ?>
                            <span class="field-hint">⚠️ You cannot change your own role.</span>
                            <!-- Hidden fallback so role is still submitted -->
                            <input type="hidden" name="role" value="<?= htmlspecialchars($formData['role']) ?>">
                        <?php endif; ?>
                    </div>

                    <div class="section-title">Change Password <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#d1d5db;">— leave blank to keep current password</span></div>

                    <!-- New Password + Confirm -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="password-wrapper">
                                <input
                                    type="password" id="new_password" name="new_password"
                                    placeholder="Min. 8 characters"
                                    class="<?= isset($errors['new_password']) ? 'is-invalid' : '' ?>"
                                    oninput="updateStrength(this.value)"
                                    autocomplete="new-password"
                                >
                                <button type="button" class="toggle-password" onclick="togglePwd('new_password')">👁</button>
                            </div>
                            <div class="strength-bar"><div class="strength-bar-fill" id="strengthFill"></div></div>
                            <?php if (isset($errors['new_password'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['new_password']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="password-wrapper">
                                <input
                                    type="password" id="confirm_password" name="confirm_password"
                                    placeholder="Repeat new password"
                                    class="<?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                                    autocomplete="new-password"
                                >
                                <button type="button" class="toggle-password" onclick="togglePwd('confirm_password')">👁</button>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <span class="field-error"><?= htmlspecialchars($errors['confirm_password']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">💾 Save Changes</button>
                        <a href="users.php" class="btn-secondary">← Back to Staff List</a>
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