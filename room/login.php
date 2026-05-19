<?php
// ─────────────────────────────────────────────────────────────────────────────
// room/login.php  –  Admin / Staff Login Page
//
// This page handles authentication for the admin side of the system.
// It uses username + password (with bcrypt hashing via password_verify).
// Students have their own login at student_user/login.php.
// ─────────────────────────────────────────────────────────────────────────────

// Load the shared session helper and database connection.
require_once(__DIR__ . "/../includes/session.php");
require_once(__DIR__ . "/../includes/db.php");

// If the user is already logged in, skip the login page and go straight to the dashboard.
if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// ── Handle the login form submission ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Read what the user typed, trimming any leading/trailing spaces.
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Simple validation: both fields must be filled in.
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Look up the user by username. is_active = 1 means the account is not disabled.
        // LIMIT 1 stops the query as soon as it finds a match (faster).
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // password_verify() checks the plain-text password against the stored bcrypt hash.
        // This is the safe way — we never store passwords in plain text.
        if ($user && password_verify($password, $user['password'])) {

            // Login successful: store key user data in the session.
            // The session acts like a "logged-in stamp" that persists across pages.
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            // Send the user to the dashboard.
            header("Location: index.php");
            exit();
        } else {
            // Wrong username or password. Keep the message vague on purpose
            // so attackers can't tell which one was wrong.
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — HostelHub</title>
    <style>
        /* Reset: remove default browser margin/padding and use border-box sizing */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* Full-page background using login.png with a dark semi-transparent overlay */
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;       /* vertical center */
            justify-content: center;   /* horizontal center */
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;

            /* login.png fills the entire background */
            background: url('login.png') center center / cover no-repeat;
            position: relative;
        }

        /* Dark overlay placed on top of the background image for readability */
        body::before {
            content: '';
            position: fixed;
            inset: 0;                              /* covers the whole screen */
            background: rgba(10, 14, 40, 0.55);   /* dark blue tint at 55% opacity */
            backdrop-filter: blur(2px);            /* slight blur of the photo */
            z-index: 0;
        }

        /* ── Login card (glassmorphism style) ── */
        .login-card {
            position: relative;
            z-index: 1;               /* sits above the dark overlay */
            width: 100%;
            max-width: 420px;
            margin: 24px;
            background: rgba(255, 255, 255, 0.92); /* nearly-white semi-transparent */
            border-radius: 20px;
            padding: 42px 40px 36px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.35), 0 2px 8px rgba(0,0,0,0.12);
            animation: slideUp 0.4s ease both;     /* slides in from below on load */
        }
        /* Slide-up animation for the card */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Logo + brand name at the top ── */
        .brand-row {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
            text-align: center;
        }
        .brand-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 16px;
            margin-bottom: 12px;
            box-shadow: 0 6px 20px rgba(79,70,229,.2);
        }
        .brand-text h1 {
            font-size: 1.6rem;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.03em;
            line-height: 1;
        }
        .brand-text small {
            font-size: 0.72rem;
            color: #9ca3af;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            margin-top: 4px;
            display: block;
        }

        /* ── Heading text below the logo ── */
        .form-heading { margin-bottom: 24px; }
        .form-heading h2 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        .form-heading p { font-size: 0.875rem; color: #6b7280; }

        /* ── Red error alert box ── */
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.85rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Form field groups ── */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: 0.83rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.93rem;
            color: #111827;
            background: #f9fafb;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        /* Highlight the field with a purple ring when clicked */
        .form-group input:focus {
            border-color: #4f46e5;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(79,70,229,.12);
        }
        .form-group input::placeholder { color: #9ca3af; }

        /* ── Show/hide password toggle button ── */
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 44px; } /* make room for the eye icon */
        .toggle-password {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; color: #6b7280;
            padding: 4px; line-height: 1;
            display: flex; align-items: center;
        }
        .toggle-password:hover { color: #374151; }

        /* ── Main submit button ── */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed); /* indigo to purple */
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.01em;
            box-shadow: 0 4px 14px rgba(79,70,229,.35);
            transition: opacity 0.2s, box-shadow 0.2s, transform 0.15s;
            margin-top: 6px;
        }
        .btn-login:hover {
            opacity: 0.92;
            box-shadow: 0 6px 20px rgba(79,70,229,.45);
            transform: translateY(-1px); /* tiny lift on hover */
        }
        .btn-login:active { transform: translateY(0); }

        /* ── Forgot password link ── */
        .forget-password {
            text-align: center;
            margin-top: 16px;
            font-size: 0.84rem;
            color: #6b7280;
        }
        .forget-password a { color: #4f46e5; text-decoration: none; font-weight: 600; }
        .forget-password a:hover { text-decoration: underline; }

        /* ── Footer copyright text ── */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            font-size: 0.75rem;
            color: #d1d5db;
            letter-spacing: 0.04em;
        }

        /* ── "or" divider line between form and student portal button ── */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0 16px;
            color: #d1d5db;
            font-size: 0.78rem;
        }
        /* Horizontal lines on each side of the "or" text */
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: #e5e7eb;
        }

        /* ── Student portal shortcut button ── */
        .btn-student-portal {
            width: 100%;
            padding: 11px;
            background: #f5f3ff;          /* light purple background */
            color: #6d28d9;
            border: 1.5px solid #ddd6fe;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background .2s, border-color .2s;
        }
        .btn-student-portal:hover { background: #ede9fe; border-color: #c4b5fd; }

        /* ── Small screen adjustments ── */
        @media (max-width: 480px) {
            .login-card { padding: 36px 24px 28px; margin: 16px; }
            .brand-logo { width: 64px; height: 64px; }
        }
    </style>
</head>
<body>

    <div class="login-card">

        <!-- Logo image (logo.png) + application name -->
        <div class="brand-row">
            <img src="logo.png" alt="HostelHub Logo" class="brand-logo">
            <div class="brand-text">
                <h1>HostelHub</h1>
                <small>Management System</small>
            </div>
        </div>

        <!-- Short heading and sub-text -->
        <div class="form-heading">
            <h2>Sign in to your account</h2>
            <p>Enter your credentials to continue</p>
        </div>

        <!-- Show error message if login failed -->
        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Login form — POST sends data securely (not in the URL) -->
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"<!-- keep the value if there was an error -->
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <!-- password-wrapper positions the eye icon inside the input -->
                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <!-- Eye button: calls togglePassword() to show/hide the password -->
                    <button type="button" class="toggle-password" onclick="togglePassword()" title="Show/hide password">
                        <svg id="eye-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <p class="forget-password">
            <a href="forget_password.php">Forgot your password?</a>
        </p>

        <!-- Divider between admin login and the student portal link -->
        <div class="divider"><span>or</span></div>

        <!-- Link to the student self-service portal -->
        <a href="../student/login.php" class="btn-student-portal">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
            Go to Student Portal
        </a>

        <!-- Footer: auto-updates the year using PHP's date() -->
        <p class="login-footer">HostelHub &copy; <?= date('Y') ?></p>

    </div>

    <script>
        // Toggles the password field between hidden (dots) and visible (plain text).
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
