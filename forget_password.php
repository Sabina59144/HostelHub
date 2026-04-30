<?php
// forgot_password.php
require_once("includes/session.php");

// If already logged in, go to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — HostelHub</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .forgot-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e293b 0%, #1a56db 100%);
        }

        .forgot-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 36px 48px;
            width: 100%;
            max-width: 680px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }

        .forgot-card .login-logo {
            font-size: 36px;
            margin-bottom: 8px;
        }

        .forgot-card h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 26px;
            color: var(--text);
            margin-bottom: 4px;
        }

        .forgot-card .subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 24px;
        }

        .info-box {
            background: #eff6ff;
            border: 1.5px solid #bfdbfe;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 24px;
            text-align: left;
        }

        .info-box h3 {
            font-size: 15px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 14px;
            text-align: center;
        }

        .steps {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .steps li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14px;
            color: #1e40af;
        }

        .step-number {
            background: #1a56db;
            color: #fff;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .btn-back {
            display: block;
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: background 0.2s;
            text-decoration: none;
        }

        .btn-back:hover {
            background: var(--primary-dark);
            text-decoration: none;
            color: #fff;
        }

        .login-footer {
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body class="forgot-page">

    <div class="forgot-card">
        <div class="login-logo">🔐</div>
        <h1>HostelHub</h1>
        <p class="subtitle">Forgot Password</p>

        <div class="info-box">
            <h3>Need to reset your password?</h3>
            <ul class="steps">
                <li>
                    <span class="step-number">1</span>
                    <span>Contact your hostel admin in person or via phone</span>
                </li>
                <li>
                    <span class="step-number">2</span>
                    <span>Admin will reset your password from the system</span>
                </li>
                <li>
                    <span class="step-number">3</span>
                    <span>Admin gives you a temporary password</span>
                </li>
                <li>
                    <span class="step-number">4</span>
                    <span>Log in and update your password immediately</span>
                </li>
            </ul>
        </div>

        <a href="login.php" class="btn-back">← Back to Login</a>

        <p class="login-footer">HostelHub &copy; <?= date('Y') ?></p>
    </div>

</body>
</html>