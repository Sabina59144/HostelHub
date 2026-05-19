<?php
// ─────────────────────────────────────────────────────────────────────────────
// room/logout.php  –  Admin Logout Page
//
// This file does two things:
//   1. Calls the logout() function which clears the session data.
//   2. Shows a nice "You have been logged out" screen that auto-redirects
//      to login.php after 3 seconds.
// ─────────────────────────────────────────────────────────────────────────────

// Load the session helper — logout() lives there.
require_once __DIR__ . '/../includes/session.php';

// Destroy the session and clear all logged-in data immediately.
logout();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Auto-redirect to login.php after 3 seconds (meta refresh) -->
    <meta http-equiv="refresh" content="3;url=login.php">
    <title>Logged Out — HostelHub</title>
    <style>
        /* Reset default browser spacing */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* Dark purple full-screen background */
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f0c29;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow: hidden;
        }

        /* ── Animated background blobs ── */
        /* These are large blurred coloured circles that slowly drift around */
        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);           /* heavy blur makes it look soft */
            animation: blobDrift ease-in-out infinite alternate;
            z-index: 0;
        }
        .blob-1 { width: 600px; height: 600px; background: rgba(99,102,241,.35); top:-150px; left:-150px; animation-duration:14s; }
        .blob-2 { width: 500px; height: 500px; background: rgba(139,92,246,.3); bottom:-120px; right:-100px; animation-duration:18s; animation-delay:-6s; }
        @keyframes blobDrift {
            0%   { transform: translate(0,0) scale(1); }
            100% { transform: translate(40px,30px) scale(1.1); }
        }

        /* ── Subtle grid texture overlay ── */
        .grid {
            position: fixed; inset: 0; z-index: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        /* ── Glassmorphism card in the centre ── */
        .card {
            position: relative; z-index: 1;   /* above blobs and grid */
            background: rgba(255,255,255,.06);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 24px;
            padding: 3rem 3.5rem;
            text-align: center;
            max-width: 420px; width: 90%;
            box-shadow: 0 24px 60px rgba(0,0,0,.4);
            animation: fadeUp .5s ease both;  /* slides up when the page loads */
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Circular icon at the top of the card ── */
        .icon-wrap {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a78bfa); /* indigo to violet */
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 24px rgba(99,102,241,.45);
            animation: popIn .5s .2s cubic-bezier(.34,1.56,.64,1) both; /* bouncy scale-in */
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(.6); }
            to   { opacity: 1; transform: scale(1); }
        }

        h1 { font-size: 1.65rem; font-weight: 800; color: #fff; margin-bottom: .5rem; letter-spacing: -.02em; }
        .sub { font-size: .92rem; color: rgba(255,255,255,.6); margin-bottom: 2rem; line-height: 1.55; }

        /* ── Progress bar that drains over 3 seconds ── */
        /* This matches the meta-refresh countdown so the user can see it happening */
        .progress-wrap {
            background: rgba(255,255,255,.1);
            border-radius: 999px; height: 4px;
            overflow: hidden; margin-bottom: 1.25rem;
        }
        .progress-fill {
            height: 100%; width: 100%;
            background: linear-gradient(90deg, #6366f1, #a78bfa);
            border-radius: 999px;
            animation: drain 3s linear forwards; /* shrinks from 100% to 0% in 3 s */
            transform-origin: left;
        }
        @keyframes drain {
            from { width: 100%; }
            to   { width: 0%; }
        }

        .redirect-note { font-size: .8rem; color: rgba(255,255,255,.4); margin-bottom: 1.75rem; }

        /* ── Manual "Go to Login" button (in case auto-redirect is blocked) ── */
        .btn-login {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .75rem 1.75rem;
            background: #6366f1; color: #fff;
            border: none; border-radius: 10px;
            font-size: .93rem; font-weight: 700;
            text-decoration: none;
            transition: background .2s, box-shadow .2s;
            box-shadow: 0 4px 14px rgba(99,102,241,.4);
        }
        .btn-login:hover { background: #4f46e5; box-shadow: 0 6px 18px rgba(99,102,241,.5); }

        .brand { margin-top: 2rem; font-size: .78rem; color: rgba(255,255,255,.25); letter-spacing: .06em; text-transform: uppercase; }
    </style>
</head>
<body>

    <!-- Decorative animated blobs (purely visual) -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="grid"></div>

    <!-- Centred logout confirmation card -->
    <div class="card">

        <!-- Logout icon -->
        <div class="icon-wrap">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </div>

        <h1>You have been logged out</h1>
        <p class="sub">Your session has ended securely.<br>Redirecting you to the login page&hellip;</p>

        <!-- Visual countdown bar — drains in sync with the 3-second meta refresh -->
        <div class="progress-wrap">
            <div class="progress-fill"></div>
        </div>
        <p class="redirect-note">Redirecting in 3 seconds</p>

        <!-- Manual button in case the browser blocks the auto-redirect -->
        <a href="login.php" class="btn-login">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                <polyline points="10 17 15 12 10 7"/>
                <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Go to Login
        </a>

        <p class="brand">HostelHub &mdash; Room Management</p>
    </div>

</body>
</html>
