<?php
/**
 * login.php
 * ─────────────────────────────────────────────────────────────
 * Login page — the entry point for all authenticated users.
 *
 * Flow:
 *   1. If already logged in → redirect to dashboard immediately
 *   2. On POST: validate credentials against the users table
 *   3. Passwords are stored as bcrypt hashes (password_verify)
 *   4. Only active users (is_active = 1) can log in
 *   5. On success → store user info in session, redirect to dashboard
 *   6. On failure → show error message, re-display form
 * ─────────────────────────────────────────────────────────────
 */
require_once("includes/session.php");
require_once("includes/db.php");

// Redirect already-authenticated users away from the login page
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Fetch only active users matching the given username
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // password_verify compares plaintext against the stored bcrypt hash
        if ($user && password_verify($password, $user['password'])) {
            // Store key user info in session — available across all pages
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            header("Location: dashboard.php");
            exit();
        } else {
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
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">🏨</div>
            <h1>HostelHub</h1>
            <p>Student Hostel Management System</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       placeholder="Enter your username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password"
                           required autocomplete="current-password">
                    <button type="button" class="toggle-password" onclick="togglePassword()">👁️</button>
                </div>
            </div>
            <button type="submit" class="btn-login">Login</button>
        </form>

        <p class="forgot-password">
            <a href="forget_password.php">Forgot your password?</a>
        </p>
        <p class="login-footer">HostelHub &copy; <?= date('Y') ?></p>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
