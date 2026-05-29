<?php
// login.php
require_once(__DIR__ . "/includes/session.php");
require_once(__DIR__ . "/includes/db.php");

// If already logged in, go to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$student_error = '';
$login_type = $_POST['login_type'] ?? '';

// ---------------------------------------------------
// Handle form submission (login validation)
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Management / Staff Login ----
    if ($login_type === 'staff') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
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

    // ---- Student Login ----
    if ($login_type === 'student') {
        $student_id = trim($_POST['student_id'] ?? '');
        $password   = trim($_POST['student_password'] ?? '');

        if (empty($student_id) || empty($password)) {
            $student_error = "Please enter both student ID and password.";
        } else {
            $stmt = $db->prepare("SELECT * FROM students WHERE student_number = ? AND status = 1 LIMIT 1");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password'])) {
                $_SESSION['student_id']   = $student['student_id'];
                $_SESSION['student_name'] = $student['full_name'];
                $_SESSION['role']         = 'student';

                header("Location: student_dashboard.php");
                exit();
            } else {
                $student_error = "Invalid student ID or password.";
            }
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
    <style>
        /* ── Layout ── */
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f1b35;
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding: 2rem 1.5rem;
            position: relative;
            box-sizing: border-box;
        }

        #bg-canvas {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
        }

        /* ── Shared header ── */
        .login-header {
            text-align: center;
            margin-bottom: 1.25rem;
        }
        .login-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            color: #e8eeff;
            letter-spacing: -0.5px;
        }
        .login-header p {
            margin: 0.25rem 0 0;
            color: #8fa3cc;
            font-size: 0.9rem;
        }

        /* ── Single centred panel ── */
        .login-panels {
            display: flex;
            justify-content: center;
        }

        /* ── Panel card ── */
        .login-panel {
            background: #fff;
            border-radius: 18px;
            padding: 2rem 2.25rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.22);
            display: flex;
            flex-direction: column;
            width: 100%;
            box-sizing: border-box;
        }

        .panel-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.25rem 0.7rem;
            border-radius: 99px;
            margin-bottom: 0.85rem;
            width: fit-content;
        }
        .badge-staff  { background: #e8eeff; color: #3a57cc; }
        .badge-student{ background: #e6f9f2; color: #1a8a5a; }

        .panel-title {
            margin: 0 0 0.2rem;
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a2340;
        }
        .panel-subtitle {
            margin: 0 0 1.1rem;
            font-size: 0.85rem;
            color: #8894b0;
        }

        /* ── Form elements ── */
        .form-group {
            margin-bottom: 0.85rem;
        }
        .form-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5470;
            margin-bottom: 0.3rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1.5px solid #dde2ef;
            border-radius: 9px;
            font-size: 0.95rem;
            color: #1a2340;
            background: #f9fafe;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
            outline: none;
        }
        .form-group input:focus {
            border-color: #5b79e8;
            box-shadow: 0 0 0 3px rgba(91,121,232,0.12);
            background: #fff;
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input {
            padding-right: 2.8rem;
        }
        .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: #8894b0;
            padding: 0;
            line-height: 1;
        }

        /* ── Buttons ── */
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 9px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: opacity 0.2s, transform 0.1s;
        }
        .btn-login:active { transform: scale(0.98); }

        .btn-staff {
            background: #3a57cc;
            color: #fff;
        }
        .btn-staff:hover { background: #2f48b8; }

        .btn-student {
            background: #1a8a5a;
            color: #fff;
        }
        .btn-student:hover { background: #147a4f; }

        /* ── Alert ── */
        .alert-error {
            background: #fff0f0;
            border: 1px solid #f5c0c0;
            color: #c0392b;
            border-radius: 8px;
            padding: 0.6rem 0.9rem;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        /* ── Footer links ── */
        .panel-links {
            margin-top: auto;
            padding-top: 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
        }
        .panel-links a {
            font-size: 0.8rem;
            color: #6b7a99;
            text-decoration: none;
        }
        .panel-links a:hover { text-decoration: underline; }

        /* ── Student portal link ── */
        .student-portal-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            font-size: 0.88rem;
            font-weight: 600;
            color: #1a8a5a;
            text-decoration: none;
            padding: 0.55rem 1.5rem;
            border: 2px solid #1a8a5a;
            border-radius: 9px;
            margin-top: 0.25rem;
            width: 100%;
            box-sizing: border-box;
            transition: background 0.2s, color 0.2s;
        }
        .student-portal-link:hover {
            background: #1a8a5a;
            color: #fff;
            text-decoration: none !important;
        }

        /* ── Divider (unused, kept for compatibility) ── */
        .panel-divider { display: none; }

        /* ── Footer ── */
        .login-footer {
            text-align: center;
            color: #4e6080;
            font-size: 0.78rem;
            margin-top: 1.25rem;
        }
    </style>
</head>
<body class="login-page">

    <canvas id="bg-canvas"></canvas>

    <div class="login-wrapper">

        <!-- Shared header -->
        <div class="login-header">
            <h1>HostelHub</h1>
            <p>Student Hostel Management System</p>
        </div>

        <!-- Two panels -->
        <div class="login-panels">

            <!-- ── Staff / Management Panel ── -->
            <div class="login-panel" id="staff-panel">
                <span class="panel-badge badge-staff">⚙ Management</span>
                <h2 class="panel-title">Staff Login</h2>
                <p class="panel-subtitle">For hostel management and admin staff</p>

                <?php if (!empty($error)): ?>
                    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <input type="hidden" name="login_type" value="staff">

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter your username"
                            value="<?= htmlspecialchars(($login_type === 'staff') ? ($_POST['username'] ?? '') : '') ?>"
                            required
                            autocomplete="username"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">👁</button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login btn-staff">Login as Staff</button>
                </form>

                <div class="panel-links">
                    <a href="forget_password.php">Forgot your password?</a>
                    <a href="#" class="student-portal-link" onclick="showStudentForm(); return false;">🎓 Login as Student</a>
                </div>
            </div>

            <!-- ── Student Panel (hidden by default) ── -->
            <div class="login-panel" id="student-panel" style="display:none;">
                <span class="panel-badge badge-student">🎓 Student</span>
                <h2 class="panel-title">Student Login</h2>
                <p class="panel-subtitle">For hostel residents to access their portal</p>

                <?php if (!empty($student_error)): ?>
                    <div class="alert-error"><?= htmlspecialchars($student_error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <input type="hidden" name="login_type" value="student">

                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input
                            type="text"
                            id="student_id"
                            name="student_id"
                            placeholder="e.g. STU-2024-001"
                            value="<?= htmlspecialchars(($login_type === 'student') ? ($_POST['student_id'] ?? '') : '') ?>"
                            required
                            autocomplete="username"
                        >
                    </div>

                    <div class="form-group">
                        <label for="student_password">Password</label>
                        <div class="password-wrapper">
                            <input
                                type="password"
                                id="student_password"
                                name="student_password"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('student_password')">👁</button>
                        </div>
                    </div>

                    <button type="submit" class="btn-login btn-student">Login as Student</button>
                </form>

                <div class="panel-links">
                    <a href="student_forget_password.php">Forgot your password?</a>
                    <a href="#" class="student-portal-link" style="border-color:#3a57cc;color:#3a57cc;" onclick="showStaffForm(); return false;">⚙ Back to Staff Login</a>
                </div>
            </div>

        </div><!-- /.login-panels -->

        <p class="login-footer">HostelHub &copy; <?= date('Y') ?></p>

    </div><!-- /.login-wrapper -->

    <script>
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        function showStudentForm() {
            document.getElementById('staff-panel').style.display = 'none';
            document.getElementById('student-panel').style.display = 'flex';
        }

        function showStaffForm() {
            document.getElementById('student-panel').style.display = 'none';
            document.getElementById('staff-panel').style.display = 'flex';
        }

        // Auto-show student panel if student login failed
        <?php if ($login_type === 'student'): ?>
        document.addEventListener('DOMContentLoaded', showStudentForm);
        <?php endif; ?>
    </script>

    <script>
        const canvas = document.getElementById('bg-canvas');
        const ctx = canvas.getContext('2d');

        function resize() {
            canvas.width  = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resize();
        window.addEventListener('resize', resize);

        const ICONS = [
            // Bed
            (cx, cy, sz, a) => {
                ctx.save(); ctx.translate(cx, cy); ctx.rotate(a); ctx.scale(sz, sz);
                ctx.beginPath(); ctx.rect(-14, 0, 28, 10); ctx.fill();
                ctx.beginPath(); ctx.rect(-14, -6, 28, 6); ctx.fill();
                ctx.beginPath(); ctx.rect(-16, -8, 6, 18); ctx.fill();
                ctx.beginPath(); ctx.rect(10, -8, 6, 18); ctx.fill();
                ctx.beginPath(); ctx.rect(-10, -5, 8, 5); ctx.fillStyle = 'rgba(255,255,255,0.15)'; ctx.fill();
                ctx.restore();
            },
            // Key
            (cx, cy, sz, a) => {
                ctx.save(); ctx.translate(cx, cy); ctx.rotate(a); ctx.scale(sz, sz);
                ctx.beginPath(); ctx.arc(0, 0, 8, 0, Math.PI * 2); ctx.lineWidth = 2.5; ctx.stroke(); ctx.fill();
                ctx.beginPath(); ctx.moveTo(8, 0); ctx.lineTo(22, 0); ctx.lineWidth = 2.5; ctx.stroke();
                ctx.beginPath(); ctx.moveTo(18, 0); ctx.lineTo(18, 4); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(22, 0); ctx.lineTo(22, 4); ctx.stroke();
                ctx.restore();
            },
            // Book
            (cx, cy, sz, a) => {
                ctx.save(); ctx.translate(cx, cy); ctx.rotate(a); ctx.scale(sz, sz);
                ctx.beginPath(); ctx.rect(-10, -12, 20, 24); ctx.fill();
                ctx.beginPath(); ctx.rect(-10, -12, 3, 24); ctx.fillStyle = 'rgba(255,255,255,0.12)'; ctx.fill();
                for (let i = -6; i <= 6; i += 4) {
                    ctx.beginPath(); ctx.moveTo(-5, i); ctx.lineTo(8, i); ctx.lineWidth = 1; ctx.stroke();
                }
                ctx.restore();
            },
            // Star
            (cx, cy, sz, a) => {
                ctx.save(); ctx.translate(cx, cy); ctx.rotate(a); ctx.scale(sz, sz);
                ctx.beginPath();
                for (let i = 0; i < 5; i++) {
                    const outerA = (i * 4 * Math.PI / 5) - Math.PI / 2;
                    const innerA = outerA + Math.PI / 5;
                    i === 0 ? ctx.moveTo(Math.cos(outerA)*10, Math.sin(outerA)*10)
                            : ctx.lineTo(Math.cos(outerA)*10, Math.sin(outerA)*10);
                    ctx.lineTo(Math.cos(innerA)*4.5, Math.sin(innerA)*4.5);
                }
                ctx.closePath(); ctx.fill();
                ctx.restore();
            },
            // Moon
            (cx, cy, sz, a) => {
                ctx.save(); ctx.translate(cx, cy); ctx.rotate(a); ctx.scale(sz, sz);
                ctx.beginPath(); ctx.arc(0, 0, 10, 0, Math.PI * 2);
                ctx.fill();
                ctx.globalCompositeOperation = 'destination-out';
                ctx.beginPath(); ctx.arc(5, -3, 8, 0, Math.PI * 2);
                ctx.fill();
                ctx.globalCompositeOperation = 'source-over';
                ctx.restore();
            },
            // Door
            (cx, cy, sz, a) => {
                ctx.save(); ctx.translate(cx, cy); ctx.rotate(a); ctx.scale(sz, sz);
                ctx.beginPath(); ctx.rect(-9, -14, 18, 28); ctx.fill();
                ctx.beginPath(); ctx.arc(5, 0, 2, 0, Math.PI * 2); ctx.fillStyle = 'rgba(255,255,255,0.3)'; ctx.fill();
                ctx.restore();
            },
            // Wifi / signal
            (cx, cy, sz, a) => {
                ctx.save(); ctx.translate(cx, cy); ctx.rotate(a); ctx.scale(sz, sz);
                [14, 9, 5].forEach((r, i) => {
                    ctx.beginPath(); ctx.arc(0, 4, r, Math.PI + 0.3, Math.PI * 2 - 0.3);
                    ctx.lineWidth = 2; ctx.globalAlpha = 0.4 + i * 0.2; ctx.stroke();
                });
                ctx.globalAlpha = 1;
                ctx.beginPath(); ctx.arc(0, 4, 2, 0, Math.PI * 2); ctx.fill();
                ctx.restore();
            },
            // Lamp / bulb
            (cx, cy, sz, a) => {
                ctx.save(); ctx.translate(cx, cy); ctx.rotate(a); ctx.scale(sz, sz);
                ctx.beginPath(); ctx.arc(0, -4, 8, 0, Math.PI * 2); ctx.fill();
                ctx.beginPath(); ctx.rect(-3, 4, 6, 6); ctx.fill();
                ctx.beginPath(); ctx.moveTo(-4, 10); ctx.lineTo(4, 10); ctx.lineWidth = 1.5; ctx.stroke();
                ctx.restore();
            },
        ];

        function makeParticle() {
            const iconIdx = Math.floor(Math.random() * ICONS.length);
            return {
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height,
                vx: (Math.random() - 0.5) * 0.3,
                vy: -(Math.random() * 0.4 + 0.1),
                size: Math.random() * 0.55 + 0.35,
                alpha: Math.random() * 0.18 + 0.05,
                rotation: Math.random() * Math.PI * 2,
                rotSpeed: (Math.random() - 0.5) * 0.004,
                icon: ICONS[iconIdx],
            };
        }

        const COUNT = 38;
        const particles = Array.from({ length: COUNT }, makeParticle);

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            for (const p of particles) {
                ctx.save();
                ctx.globalAlpha = p.alpha;
                ctx.strokeStyle = 'rgba(160,190,255,0.6)';
                ctx.fillStyle   = 'rgba(100,150,255,0.18)';
                ctx.lineWidth   = 1.5;
                p.icon(p.x, p.y, p.size, p.rotation);
                ctx.restore();

                p.x += p.vx;
                p.y += p.vy;
                p.rotation += p.rotSpeed;

                if (p.y < -40) { p.y = canvas.height + 40; p.x = Math.random() * canvas.width; }
                if (p.x < -40) p.x = canvas.width + 40;
                if (p.x > canvas.width + 40) p.x = -40;
            }

            requestAnimationFrame(draw);
        }

        draw();
    </script>
</body>
</html>
