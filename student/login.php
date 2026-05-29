<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

// Already logged in as student
if (!empty($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = trim($_POST['student_number'] ?? '');
    $dob            = trim($_POST['date_of_birth']  ?? '');

    if (empty($student_number) || empty($dob)) {
        $error = "Please enter your Student Number and Date of Birth.";
    } else {
        $stmt = $db->prepare(
            "SELECT * FROM students WHERE student_number = ? AND date_of_birth = ? AND status = 1 LIMIT 1"
        );
        $stmt->execute([$student_number, $dob]);
        $student = $stmt->fetch();

        if ($student) {
            $_SESSION['student_id']     = $student['student_id'];
            $_SESSION['student_number'] = $student['student_number'];
            $_SESSION['student_name']   = $student['full_name'];
            $_SESSION['student_room']   = $student['room_id'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid Student Number or Date of Birth.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal — HostelHub</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../room/login.png') center center / cover no-repeat;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(10, 14, 40, 0.58);
            backdrop-filter: blur(2px);
            z-index: 0;
        }

        .login-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            margin: 24px;
            background: rgba(255,255,255,0.93);
            border-radius: 20px;
            padding: 42px 40px 36px;
            box-shadow: 0 24px 64px rgba(0,0,0,.35), 0 2px 8px rgba(0,0,0,.12);
            animation: slideUp .4s ease both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Brand */
        .brand-row {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 28px;
            text-align: center;
        }
        .brand-logo {
            width: 76px; height: 76px;
            object-fit: contain;
            border-radius: 16px;
            margin-bottom: 12px;
            box-shadow: 0 6px 20px rgba(79,70,229,.2);
        }
        .brand-text h1 {
            font-size: 1.55rem;
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
            display: block;
            margin-top: 4px;
        }

        /* Portal badge */
        .portal-badge {
            display: inline-block;
            background: #ede9fe;
            color: #6d28d9;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 999px;
            margin-top: 10px;
        }

        /* Heading */
        .form-heading { margin-bottom: 22px; }
        .form-heading h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        .form-heading p { font-size: 0.875rem; color: #6b7280; }

        /* Error */
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

        /* Form */
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
            transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .form-group input:focus {
            border-color: #7c3aed;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(124,58,237,.12);
        }
        .form-group input::placeholder { color: #9ca3af; }

        /* Hint text */
        .form-hint {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 5px;
        }

        /* Button */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(124,58,237,.35);
            transition: opacity .2s, box-shadow .2s, transform .15s;
            margin-top: 6px;
        }
        .btn-login:hover {
            opacity: 0.92;
            box-shadow: 0 6px 20px rgba(124,58,237,.45);
            transform: translateY(-1px);
        }
        .btn-login:active { transform: translateY(0); }

        /* Admin link */
        .admin-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.84rem;
            color: #6b7280;
        }
        .admin-link a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }
        .admin-link a:hover { text-decoration: underline; }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.75rem;
            color: #d1d5db;
            letter-spacing: 0.04em;
        }

        @media (max-width: 480px) {
            .login-card { padding: 36px 24px 28px; margin: 16px; }
            .brand-logo { width: 60px; height: 60px; }
        }
    </style>
</head>
<body>

<div class="login-card">

    <div class="brand-row">
        <img src="../room/logo.png" alt="HostelHub Logo" class="brand-logo">
        <div class="brand-text">
            <h1>HostelHub</h1>
            <small>Management System</small>
        </div>
        <span class="portal-badge">Student Portal</span>
    </div>

    <div class="form-heading">
        <h2>Sign in to your portal</h2>
        <p>Use your student number and date of birth</p>
    </div>

    <?php if ($error): ?>
        <div class="alert-error">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label for="student_number">Student Number</label>
            <input
                type="text"
                id="student_number"
                name="student_number"
                placeholder="e.g. S2025001"
                value="<?= htmlspecialchars($_POST['student_number'] ?? '') ?>"
                required
                autocomplete="username"
            >
        </div>

        <div class="form-group">
            <label for="date_of_birth">Date of Birth</label>
            <input
                type="date"
                id="date_of_birth"
                name="date_of_birth"
                required
            >
            <p class="form-hint">Used to verify your identity — no password needed.</p>
        </div>

        <button type="submit" class="btn-login">Access My Portal</button>
    </form>

    <p class="admin-link">
        Staff / Admin? <a href="../room/login.php">Go to Admin Login</a>
    </p>

    <p class="login-footer">HostelHub &copy; <?= date('Y') ?></p>
</div>

</body>
</html>
