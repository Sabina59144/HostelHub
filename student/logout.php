<?php
require_once __DIR__ . '/../includes/session.php';

// Clear only student session keys, leave admin session intact if any
unset($_SESSION['student_id'], $_SESSION['student_number'], $_SESSION['student_name'], $_SESSION['student_room']);
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="3;url=login.php">
    <title>Signed Out — HostelHub</title>
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        body {
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            font-family:-apple-system, BlinkMacSystemFont,'Segoe UI',sans-serif;
            background:url('../room/login.png') center center/cover no-repeat;
            position:relative;
        }
        body::before { content:''; position:fixed; inset:0; background:rgba(10,14,40,.58); backdrop-filter:blur(2px); z-index:0; }

        .card {
            position:relative; z-index:1;
            background:rgba(255,255,255,.93); border-radius:20px;
            padding:42px 40px; text-align:center; max-width:400px; width:90%;
            box-shadow:0 24px 64px rgba(0,0,0,.35);
            animation:fadeUp .4s ease both;
        }
        @keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }

        .icon-wrap {
            width:72px; height:72px; border-radius:50%;
            background:linear-gradient(135deg,#7c3aed,#4f46e5);
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 20px;
            box-shadow:0 8px 24px rgba(124,58,237,.35);
            animation:pop .5s .15s cubic-bezier(.34,1.56,.64,1) both;
        }
        @keyframes pop { from{opacity:0;transform:scale(.6)} to{opacity:1;transform:scale(1)} }

        h1 { font-size:1.45rem; font-weight:800; color:#111827; margin-bottom:8px; letter-spacing:-0.02em; }
        p  { font-size:0.875rem; color:#6b7280; margin-bottom:24px; line-height:1.55; }

        .progress-wrap { background:#e5e7eb; border-radius:999px; height:4px; overflow:hidden; margin-bottom:12px; }
        .progress-fill {
            height:100%; background:linear-gradient(90deg,#7c3aed,#4f46e5);
            border-radius:999px; animation:drain 3s linear forwards;
        }
        @keyframes drain { from{width:100%} to{width:0%} }

        .redirect-note { font-size:0.78rem; color:#9ca3af; margin-bottom:24px; }

        .btn {
            display:inline-flex; align-items:center; gap:8px;
            padding:10px 22px; background:linear-gradient(135deg,#7c3aed,#4f46e5);
            color:#fff; border-radius:10px; text-decoration:none;
            font-size:0.9rem; font-weight:700;
            box-shadow:0 4px 14px rgba(124,58,237,.35);
            transition:opacity .2s;
        }
        .btn:hover { opacity:.9; }

        .brand { margin-top:24px; font-size:0.72rem; color:#d1d5db; letter-spacing:0.06em; text-transform:uppercase; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon-wrap">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
    </div>

    <h1>You've been signed out</h1>
    <p>Your student session has ended securely.<br>Redirecting to the login page…</p>

    <div class="progress-wrap"><div class="progress-fill"></div></div>
    <p class="redirect-note">Redirecting in 3 seconds</p>

    <a href="login.php" class="btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
            <polyline points="10 17 15 12 10 7"/>
            <line x1="15" y1="12" x2="3" y2="12"/>
        </svg>
        Back to Student Login
    </a>

    <p class="brand">HostelHub &mdash; Student Portal</p>
</div>
</body>
</html>
