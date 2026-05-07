<?php
require_once(__DIR__ . "/../includes/db.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already logged in as student, redirect
if (isset($_SESSION['student_id'])) {
    header("Location: student_portal.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id_input = trim($_POST['student_id'] ?? '');
    $email            = trim($_POST['email'] ?? '');

    if (empty($student_id_input) || empty($email)) {
        $error = "Please enter both your Student ID and Email.";
    } else {
        $stmt = $db->prepare("
            SELECT student_id, full_name, student_number, email
            FROM students
            WHERE student_id = ? AND email = ? AND status = 1
        ");
        $stmt->execute([$student_id_input, $email]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            session_regenerate_id(true);
            $_SESSION['student_id']     = $student['student_id'];
            $_SESSION['student_name']   = $student['full_name'];
            $_SESSION['student_number'] = $student['student_number'];
            header("Location: student_portal.php");
            exit;
        } else {
            $error = "No matching student found. Please check your Student Number and Email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Fee Portal — HostelHub</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 440px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 36px 28px 28px;
            text-align: center;
        }

        .card-header .icon {
            font-size: 3rem;
            margin-bottom: 10px;
            display: block;
        }

        .card-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .card-header p {
            opacity: 0.85;
            font-size: 0.9rem;
        }

        .card-body {
            padding: 32px 28px;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 22px;
            font-size: 0.83rem;
            color: #1d4ed8;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .info-box span { flex-shrink: 0; font-size: 1rem; }

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #dc2626;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            color: #b91c1c;
            font-weight: 600;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
            margin-top: 16px;
        }

        input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #d1d5db;
            border-radius: 9px;
            font-size: 0.95rem;
            color: #111827;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }

        .login-btn {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            letter-spacing: 0.3px;
            transition: opacity 0.2s, transform 0.1s;
        }

        .login-btn:hover { opacity: 0.92; transform: translateY(-1px); }
        .login-btn:active { transform: translateY(0); }

        .footer-note {
            text-align: center;
            margin-top: 20px;
            font-size: 0.8rem;
            color: #9ca3af;
        }

        .staff-link {
            text-align: center;
            margin-top: 14px;
            font-size: 0.82rem;
        }

        .staff-link a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }

        .staff-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <span class="icon">🎓</span>
        <h1>Student Fee Portal</h1>
        <p>HostelHub — View your fee records</p>
    </div>
    <div class="card-body">
        <div class="info-box">
            <span>ℹ️</span>
            <div>Enter your <strong>Student ID</strong> & your registered <strong>Email</strong> to view your fees.</div>
        </div>

        <?php if ($error): ?>
        <div class="error-box">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="student_id">Student ID</label>
            <input
                type="number"
                id="student_id"
                name="student_id"
                placeholder="e.g. 100"
                value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>"
                required
                autocomplete="off"
            >

            <label for="email">Registered Email</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="e.g. you@student.edu"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                required
                autocomplete="email"
            >

            <button type="submit" class="login-btn">🔍 View My Fees</button>
        </form>

        <p class="footer-note">Read-only access — no changes can be made here.</p>
        <p class="staff-link"><a href="login.php">Staff login →</a></p>
    </div>
</div>
</body>
</html>
