<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

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
    <title>Student Login — HostelHub</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../room/login.png') center center / cover no-repeat;
        }
        body::before {
            content: ''; position: fixed; inset: 0;
            background: rgba(10, 14, 40, 0.58);
            backdrop-filter: blur(2px); z-index: 0;
        }

        .card {
            position: relative; z-index: 1;
            width: 100%; max-width: 420px; margin: 24px;
            background: rgba(255,255,255,0.94);
            border-radius: 20px; padding: 42px 40px 36px;
            box-shadow: 0 24px 64px rgba(0,0,0,.35);
            animation: up .4s ease both;
        }
        @keyframes up { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }

        .brand { display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 28px; }
        .brand img { width: 72px; height: 72px; object-fit: contain; border-radius: 14px; margin-bottom: 12px; }
        .brand h1 { font-size: 1.5rem; font-weight: 800; color: #1a1a2e; letter-spacing: -0.02em; }
        .brand small { font-size: 0.7rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.07em; display: block; margin-top: 3px; }
        .portal-tag {
            display: inline-block; margin-top: 10px;
            background: #ede9fe; color: #6d28d9;
            font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em;
            text-transform: uppercase; padding: 4px 14px; border-radius: 999px;
        }

        .heading { margin-bottom: 22px; }
        .heading h2 { font-size: 1.25rem; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
        .heading p  { font-size: 0.85rem; color: #6b7280; }

        .alert-error {
            background: #fee2e2; border: 1px solid #fecaca; color: #991b1b;
            border-radius: 10px; padding: 11px 14px; font-size: 0.85rem;
            margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
        }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .form-group input {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            font-size: 0.9rem; color: #1a1a2e; background: #f9fafb;
            outline: none; transition: border-color .2s, box-shadow .2s;
        }
        .form-group input:focus { border-color: #7c3aed; background: #fff; box-shadow: 0 0 0 3px rgba(124,58,237,.1); }
        .form-group input::placeholder { color: #9ca3af; }
        .hint { font-size: 0.74rem; color: #9ca3af; margin-top: 4px; }

        .btn-login {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #7c3aed, #4f46e5);
            color: #fff; border: none; border-radius: 10px;
            font-size: 0.93rem; font-weight: 700; cursor: pointer;
            box-shadow: 0 4px 14px rgba(124,58,237,.35);
            transition: opacity .2s, transform .15s; margin-top: 6px;
        }
        .btn-login:hover { opacity: .92; transform: translateY(-1px); }
        .btn-login:active { transform: translateY(0); }

        .admin-link { text-align: center; margin-top: 20px; font-size: 0.83rem; color: #6b7280; }
        .admin-link a { color: #4f46e5; text-decoration: none; font-weight: 600; }
        .admin-link a:hover { text-decoration: underline; }

        .footer { text-align: center; margin-top: 24px; font-size: 0.74rem; color: #d1d5db; letter-spacing: 0.04em; }

        @media (max-width: 480px) { .card { padding: 32px 22px 28px; margin: 14px; } }
    </style>
</head>
<body>
<div class="card">

    <div class="brand">
        <img src="../room/logo.png" alt="HostelHub Logo">
        <h1>HostelHub</h1>
        <small>Management System</small>
        <span class="portal-tag">Student Portal</span>
    </div>

    <div class="heading">
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
            <input type="text" id="student_number" name="student_number"
                placeholder="e.g. S2025001"
                value="<?= htmlspecialchars($_POST['student_number'] ?? '') ?>"
                required autocomplete="username">
        </div>

        <div class="form-group">
            <label for="date_of_birth">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" required>
            <p class="hint">Used to verify your identity — no password needed.</p>
        </div>

        <button type="submit" class="btn-login">Access My Portal</button>
    </form>

    <p class="admin-link">Staff / Admin? <a href="../room/login.php">Admin Login</a></p>
    <p class="footer">HostelHub &copy; <?= date('Y') ?></p>

</div>
</body>
</html>
