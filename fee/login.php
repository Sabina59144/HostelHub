<?php
require_once(__DIR__ . "/../includes/db.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in, go straight to dashboard
if (isset($_SESSION['staff_id'])) {
    header("Location: index.php");
    exit;
}

$error = "";
$reason = $_GET['reason'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {

        $stmt = $db->prepare("
            SELECT staff_id, full_name, username, password_hash, role
            FROM staff
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff && password_verify($password, $staff['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['staff_id']  = $staff['staff_id'];
            $_SESSION['full_name'] = $staff['full_name'];
            $_SESSION['role']      = $staff['role'];

            header("Location: index.php");
            exit;
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
    <title>Login — HostelHub</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e3a8a 0%, #2e59d9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .login-card {
            width: 400px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .login-header {
            background: #2e59d9;
            color: white;
            padding: 30px 25px 20px;
            text-align: center;
        }
        .login-header .logo { font-size: 2.5rem; margin-bottom: 8px; }
        .login-header h2 { margin: 0 0 4px; font-size: 1.5rem; color: white; }
        .login-header p { margin: 0; opacity: 0.8; font-size: 0.85rem; }
        .login-body { padding: 30px 25px; }
        .notice {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }
        .login-body label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #374151;
            display: block;
            margin-bottom: 4px;
            margin-top: 14px;
        }
        .login-body input { margin-top: 0; margin-bottom: 0; }
        .login-btn {
            margin-top: 22px;
            width: 100%;
            padding: 13px;
            background: #2e59d9;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .login-btn:hover { background: #1f3c88; }
        .default-creds {
            margin-top: 20px;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 0.8rem;
            color: #0369a1;
        }
        .default-creds strong { display: block; margin-bottom: 6px; color: #0c4a6e; }
        .cred-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px dashed #bae6fd;
        }
        .cred-row:last-child { border-bottom: none; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <div class="logo">🏠</div>
        <h2>HostelHub</h2>
        <p>Staff Login Portal</p>
    </div>
    <div class="login-body">
        <?php if ($reason === 'login_required'): ?>
        <div class="notice">🔒 Please log in to access that page.</div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" autocomplete="username"
                   placeholder="Enter your username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>

            <label>Password</label>
            <input type="password" name="password" autocomplete="current-password"
                   placeholder="Enter your password" required>

            <button type="submit" class="login-btn">🔑 Log In</button>
        </form>

       

        <div style="margin-top:20px;text-align:center;padding-top:18px;border-top:1px solid #e5e7eb;">
            <p style="font-size:0.82rem;color:#6b7280;margin-bottom:10px;">Are you a student?</p>
            <a href="student_login.php" style="
                display:inline-block;
                background:#eff6ff;
                color:#1d4ed8;
                border:1px solid #bfdbfe;
                padding:10px 24px;
                border-radius:8px;
                font-size:0.875rem;
                font-weight:600;
                text-decoration:none;
            ">🎓 Student Fee Portal →</a>
        </div>
    </div>
</div>
</body>
</html>
