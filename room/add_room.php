<?php
session_start();  
// ── Start session and check login ─────────────────────────────
//session_start();
//if (!isset($_SESSION['user_id'])) {
//    header("Location: ../login.php");
//    exit();
//}

// ── Connect to database ───────────────────────────────────────
require_once '../includes/db.php';

// ── Initialise variables ──────────────────────────────────────
$errors          = [];
$success         = "";
$room_number     = $room_type = $available_from = "";
$capacity        = $price_per_month = "";
$ensuite_facility = 0;

// ── Handle form submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Get and sanitise inputs ───────────────────────────────
    $room_number      = trim($_POST['room_number']);
    $room_type        = trim($_POST['room_type']);
    $capacity         = trim($_POST['capacity']);
    $price_per_month  = trim($_POST['price_per_month']);
    $available_from   = trim($_POST['available_from']);
    $ensuite_facility = isset($_POST['ensuite_facility']) ? 1 : 0;

    // ── Validation ────────────────────────────────────────────

    // Room number — required
    if (empty($room_number)) {
        $errors[] = "Room number is required.";
    }

    // Room type — must be a valid option
    $validTypes = ['single', 'double', 'triple'];
    if (empty($room_type) || !in_array($room_type, $validTypes)) {
        $errors[] = "Please select a valid room type.";
    }

    // Capacity — required, must be a positive integer
    if ($capacity === '') {
        $errors[] = "Capacity is required.";
    } elseif (!ctype_digit($capacity) || (int)$capacity < 1) {
        $errors[] = "Capacity must be a whole number greater than 0.";
    }

    // Price — required, must be a valid positive number
    if ($price_per_month === '') {
        $errors[] = "Price per month is required.";
    } elseif (!is_numeric($price_per_month) || (float)$price_per_month < 0) {
        $errors[] = "Price per month must be a valid positive number.";
    }

    // Available from — required, must be a valid date
    if (empty($available_from)) {
        $errors[] = "Available from date is required.";
    } elseif (!strtotime($available_from)) {
        $errors[] = "Please enter a valid date for available from.";
    }

    // Check room number is unique
    if (empty($errors)) {
        $checkRoom = $db->prepare("SELECT room_id FROM rooms WHERE room_number = ?");
        $checkRoom->execute([$room_number]);
        if ($checkRoom->rowCount() > 0) {
            $errors[] = "This room number already exists. Please use a unique room number.";
        }
    }

    // ── Insert into database if no errors ─────────────────────
    if (empty($errors)) {
        $stmt = $db->prepare(
            "INSERT INTO rooms (room_number, room_type, capacity, price_per_month, ensuite_facility, available_from)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if ($stmt->execute([$room_number, $room_type, (int)$capacity, (float)$price_per_month, $ensuite_facility, $available_from])) {
            header("Location: index.php?msg=added");
            exit();
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Add Room</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue:        #1a56db;
            --blue-dark:   #1341b0;
            --blue-mid:    #2d6ef7;
            --blue-soft:   #eef3ff;
            --blue-glow:   rgba(26,86,219,0.13);
            --navy:        #0f1d40;
            --text:        #1c2333;
            --muted:       #64748b;
            --border:      #dde3f0;
            --bg:          #f4f6fb;
            --white:       #ffffff;
            --success:     #059669;
            --error:       #dc2626;
            --error-bg:    #fff1f1;
            --shadow-sm:   0 1px 4px rgba(15,29,64,0.07);
            --shadow-md:   0 4px 20px rgba(15,29,64,0.10);
            --shadow-lg:   0 16px 48px rgba(15,29,64,0.14);
            --radius:      14px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ════════════════════════════
           NAVBAR
        ════════════════════════════ */
        .navbar {
            position: relative; z-index: 100;
            height: 72px;
            padding: 0 32px;
            display: flex; align-items: center; justify-content: space-between;
            background: var(--navy);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            box-shadow: 0 2px 16px rgba(0,0,0,0.25);
        }

        .navbar-logo img {
            height: 44px;
            filter: brightness(0) invert(1);
            object-fit: contain;
        }

        .navbar-center {
            display: flex; align-items: center; gap: 8px;
        }

        .nav-pill {
            display: inline-flex; flex-direction: column; align-items: center; gap: 2px;
            color: rgba(255,255,255,0.80); text-decoration: none;
            font-size: 10px; font-weight: 600; letter-spacing: 0.4px;
            text-transform: uppercase;
            padding: 7px 16px; border-radius: 40px;
            border: 1px solid rgba(255,255,255,0.10);
            background: rgba(255,255,255,0.06);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .nav-pill .icon { font-size: 17px; line-height: 1; margin-bottom: 1px; }
        .nav-pill:hover {
            background: rgba(255,255,255,0.14);
            color: #fff;
            transform: translateY(-1px);
            border-color: rgba(255,255,255,0.22);
        }

        .nav-guest {
            display: inline-flex; flex-direction: column; align-items: center; gap: 2px;
            color: #fff;
            font-size: 10px; font-weight: 700; letter-spacing: 0.4px;
            text-transform: uppercase;
            padding: 7px 18px; border-radius: 40px;
            background: var(--blue);
            border: 1px solid rgba(255,255,255,0.20);
            white-space: nowrap;
        }
        .nav-guest .icon { font-size: 17px; line-height: 1; }

        .navbar-right { width: 100px; }

        /* ════════════════════════════
           HERO BANNER
        ════════════════════════════ */
        .hero {
            position: relative;
            height: 260px;
            overflow: hidden;
            display: flex; align-items: center;
        }
        .hero-img {
            position: absolute; inset: 0;
            background:
                url('https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=1600&q=80&fit=crop')
                center/cover no-repeat;
        }
        .hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(
                to bottom,
                rgba(15,29,64,0.45) 0%,
                rgba(15,29,64,0.72) 60%,
                rgba(15,29,64,0.90) 100%
            );
        }
        .hero-content {
            position: relative; z-index: 1;
            padding: 0 48px;
            margin-top: -40px;
            animation: fadeUp 0.5s ease both;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 600; letter-spacing: 0.8px;
            text-transform: uppercase; color: rgba(255,255,255,0.70);
            margin-bottom: 8px;
        }
        .hero-badge::before {
            content: ''; display: block;
            width: 20px; height: 2px;
            background: var(--blue-mid);
            border-radius: 2px;
        }
        .hero-content h1 {
            font-family: 'DM Serif Display', serif;
            font-size: 36px; font-weight: 400;
            color: #fff; line-height: 1.1;
            letter-spacing: -0.3px;
        }
        .hero-content p {
            font-size: 14px; color: rgba(255,255,255,0.62);
            margin-top: 6px;
        }

        /* ════════════════════════════
           PAGE LAYOUT
        ════════════════════════════ */
        .page-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 28px;
            max-width: 1040px;
            margin: -40px auto 60px;
            padding: 0 28px;
            position: relative; z-index: 10;
            animation: fadeUp 0.55s 0.1s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ════════════════════════════
           SIDE PANEL
        ════════════════════════════ */
        .side-panel {
            display: flex; flex-direction: column; gap: 16px;
        }

        .info-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(221,227,240,0.6);
        }

        .info-card-title {
            font-size: 11px; font-weight: 700;
            letter-spacing: 0.8px; text-transform: uppercase;
            color: var(--blue); margin-bottom: 14px;
            display: flex; align-items: center; gap: 7px;
        }
        .info-card-title::after {
            content: ''; flex: 1; height: 1px;
            background: var(--blue-soft);
        }

        .tip-item {
            display: flex; gap: 10px; align-items: flex-start;
            margin-bottom: 12px;
        }
        .tip-item:last-child { margin-bottom: 0; }
        .tip-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--blue-soft);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }
        .tip-text strong { display: block; font-size: 12px; font-weight: 600; color: var(--text); }
        .tip-text span   { font-size: 11px; color: var(--muted); line-height: 1.4; }

        .stat-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
        }
        .stat-box {
            background: var(--blue-soft);
            border-radius: 10px;
            padding: 14px 12px;
            text-align: center;
        }
        .stat-box .val {
            font-size: 22px; font-weight: 700; color: var(--blue);
            font-family: 'DM Serif Display', serif;
        }
        .stat-box .lbl {
            font-size: 10px; font-weight: 600;
            color: var(--muted); text-transform: uppercase;
            letter-spacing: 0.5px; margin-top: 2px;
        }

        /* ════════════════════════════
           FORM CARD
        ════════════════════════════ */
        .form-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(221,227,240,0.5);
            overflow: hidden;
        }

        .form-card-header {
            padding: 22px 32px;
            background: linear-gradient(to right, #f8f9ff, #eef3ff);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 12px;
        }
        .form-card-header-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: var(--blue);
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .form-card-header h2 {
            font-size: 17px; font-weight: 700; color: var(--text);
        }
        .form-card-header p {
            font-size: 12px; color: var(--muted); margin-top: 1px;
        }

        .form-body { padding: 28px 32px 32px; }

        /* ════════════════════════════
           SECTION LABELS
        ════════════════════════════ */
        .section-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 10px; font-weight: 700;
            letter-spacing: 1px; text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 16px; margin-top: 24px;
        }
        .section-label:first-child { margin-top: 0; }
        .section-label::before {
            content: ''; width: 3px; height: 14px;
            background: var(--blue); border-radius: 2px;
        }
        .section-label::after {
            content: ''; flex: 1; height: 1px;
            background: var(--border);
        }

        /* ════════════════════════════
           FORM FIELDS
        ════════════════════════════ */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

        .form-group { margin-bottom: 18px; }

        .form-group label {
            display: flex; align-items: center; gap: 5px;
            font-size: 13px; font-weight: 600;
            color: var(--text); margin-bottom: 7px;
        }
        .req { color: var(--blue); font-size: 15px; line-height: 1; }

        .input-wrap { position: relative; }
        .input-prefix {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            font-size: 14px; color: var(--muted); pointer-events: none;
        }

        .form-group input,
        .form-group select {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-size: 14px; font-family: inherit; color: var(--text);
            background: #fafbff;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
            appearance: auto;
        }
        .form-group input.has-prefix { padding-left: 34px; }
        .form-group input:hover,
        .form-group select:hover { border-color: #b0bfe0; }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--blue);
            background: var(--white);
            box-shadow: 0 0 0 3px var(--blue-glow);
        }
        .form-group input::placeholder { color: #c0c8db; }

        .hint {
            font-size: 11px; color: var(--muted);
            margin-top: 5px; line-height: 1.4;
        }

        /* ════════════════════════════
           CHECKBOX
        ════════════════════════════ */
        .checkbox-wrap {
            display: flex; align-items: center; gap: 12px;
            padding: 13px 16px;
            border: 1.5px solid var(--border);
            border-radius: 9px;
            background: #fafbff;
            cursor: pointer;
            transition: all 0.18s;
        }
        .checkbox-wrap:hover { border-color: var(--blue); background: var(--blue-soft); }
        .checkbox-wrap input[type="checkbox"] {
            width: 17px; height: 17px;
            accent-color: var(--blue); cursor: pointer; flex-shrink: 0;
        }
        .checkbox-wrap label {
            font-size: 13px; color: var(--text);
            font-weight: 500; cursor: pointer; margin: 0;
        }

        /* ════════════════════════════
           ERROR ALERT
        ════════════════════════════ */
        .alert-error {
            background: var(--error-bg);
            border: 1.5px solid #fca5a5;
            color: var(--error);
            padding: 13px 16px; border-radius: 10px;
            margin-bottom: 20px; font-size: 13px;
        }
        .alert-error ul { padding-left: 16px; margin-top: 6px; }
        .alert-error li { margin-top: 3px; }

        /* ════════════════════════════
           BUTTONS
        ════════════════════════════ */
        .btn-row { display: flex; gap: 10px; margin-top: 28px; align-items: center; }

        .btn-submit {
            display: inline-flex; align-items: center; gap: 8px;
            background: linear-gradient(135deg, var(--blue) 0%, var(--blue-mid) 100%);
            color: white; border: none;
            padding: 12px 28px; border-radius: 9px;
            font-size: 14px; font-family: inherit;
            cursor: pointer; font-weight: 700;
            letter-spacing: 0.2px;
            box-shadow: 0 4px 16px rgba(26,86,219,0.30);
            transition: all 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(26,86,219,0.40);
        }
        .btn-submit:active { transform: translateY(0); }

        .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            background: transparent; color: var(--muted);
            border: 1.5px solid var(--border);
            padding: 12px 22px; border-radius: 9px;
            font-size: 14px; font-family: inherit;
            text-decoration: none; font-weight: 600;
            transition: all 0.18s;
        }
        .btn-back:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-soft);
        }

        /* ════════════════════════════
           PROGRESS STEPS
        ════════════════════════════ */
        .steps {
            display: flex; align-items: center; gap: 0;
            margin-bottom: 24px;
        }
        .step {
            display: flex; flex-direction: column; align-items: center;
            flex: 1; position: relative;
        }
        .step:not(:last-child)::after {
            content: ''; position: absolute;
            top: 14px; left: calc(50% + 14px);
            width: calc(100% - 28px); height: 2px;
            background: var(--border);
        }
        .step.done:not(:last-child)::after { background: var(--blue); }
        .step-circle {
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--border); color: var(--muted);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; position: relative; z-index: 1;
            transition: all 0.2s;
        }
        .step.active .step-circle { background: var(--blue); color: white; }
        .step.done .step-circle { background: var(--success); color: white; }
        .step-label {
            font-size: 10px; font-weight: 600; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.4px;
            margin-top: 5px; white-space: nowrap;
        }
        .step.active .step-label { color: var(--blue); }
        .step.done .step-label { color: var(--success); }
    </style>
</head>
<body>

<!-- ════ NAVBAR ════ -->
<nav class="navbar">
    <div class="navbar-logo">
        <img src="../logo.png" alt="HostelHub Logo">
    </div>
    <div class="navbar-center">
        <span class="nav-guest">
            <span class="icon">👤</span>
            <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
        </span>
        <a href="index.php"        class="nav-pill"><span class="icon">🛏️</span>Rooms</a>
        <a href="../dashboard.php" class="nav-pill"><span class="icon">🏠</span>Dashboard</a>
        <a href="../logout.php"    class="nav-pill"><span class="icon">🚪</span>Logout</a>
    </div>
    <div class="navbar-right"></div>
</nav>

<!-- ════ HERO BANNER ════ -->
<div class="hero">
    <div class="hero-img"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-badge">Room Management</div>
        <h1>Register a New Room</h1>
        <p>Add a room to the HostelHub system with full details and availability settings</p>
    </div>
</div>

<!-- ════ TWO-COLUMN LAYOUT ════ -->
<div class="page-layout">

    <!-- ── Side Panel ── -->
    <aside class="side-panel">

        <div class="info-card">
            <div class="info-card-title">Quick Stats</div>
            <div class="stat-grid">
                <div class="stat-box"><div class="val">3</div><div class="lbl">Room Types</div></div>
                <div class="stat-box"><div class="val">kr.</div><div class="lbl">Currency</div></div>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-title">Tips</div>
            <div class="tip-item">
                <div class="tip-icon">🔑</div>
                <div class="tip-text">
                    <strong>Unique Numbers</strong>
                    <span>Room numbers must be distinct across the entire system.</span>
                </div>
            </div>
            <div class="tip-item">
                <div class="tip-icon">📅</div>
                <div class="tip-text">
                    <strong>Availability Date</strong>
                    <span>Set the earliest date this room can be assigned to a student.</span>
                </div>
            </div>
            <div class="tip-item">
                <div class="tip-icon">🚿</div>
                <div class="tip-text">
                    <strong>Ensuite Rooms</strong>
                    <span>Ensuite rooms can be priced differently from shared facilities.</span>
                </div>
            </div>
        </div>

        <div class="info-card">
            <div class="info-card-title">Room Types</div>
            <div class="tip-item">
                <div class="tip-icon">🛏️</div>
                <div class="tip-text"><strong>Single</strong><span>1 student, private space</span></div>
            </div>
            <div class="tip-item">
                <div class="tip-icon">🛏️</div>
                <div class="tip-text"><strong>Double</strong><span>2 students, shared room</span></div>
            </div>
            <div class="tip-item">
                <div class="tip-icon">🛏️</div>
                <div class="tip-text"><strong>Triple</strong><span>3 students, shared room</span></div>
            </div>
        </div>

    </aside>

    <!-- ── Main Form ── -->
    <main>

        <?php if (!empty($errors)): ?>
            <div class="alert-error">
                ⚠️ Please fix the following errors:
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon">🏨</div>
                <div>
                    <h2>Room Details</h2>
                    <p>All fields marked with * are required</p>
                </div>
            </div>

            <div class="form-body">
                <form method="POST" action="">

                    <!-- Steps indicator -->
                    <div class="steps">
                        <div class="step active">
                            <div class="step-circle">1</div>
                            <div class="step-label">Identity</div>
                        </div>
                        <div class="step active">
                            <div class="step-circle">2</div>
                            <div class="step-label">Pricing</div>
                        </div>
                        <div class="step active">
                            <div class="step-circle">3</div>
                            <div class="step-label">Availability</div>
                        </div>
                    </div>

                    <div class="section-label">Room Identity</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="room_number">Room Number <span class="req">*</span></label>
                            <input type="text" id="room_number" name="room_number"
                                   value="<?php echo htmlspecialchars($room_number); ?>"
                                   placeholder="e.g. 101A">
                            <div class="hint">Must be unique across all rooms</div>
                        </div>
                        <div class="form-group">
                            <label for="room_type">Room Type <span class="req">*</span></label>
                            <select id="room_type" name="room_type">
                                <option value="">-- Select type --</option>
                                <option value="single" <?php echo $room_type==='single' ? 'selected':''; ?>>Single</option>
                                <option value="double" <?php echo $room_type==='double' ? 'selected':''; ?>>Double</option>
                                <option value="triple" <?php echo $room_type==='triple' ? 'selected':''; ?>>Triple</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-label">Capacity &amp; Pricing</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="capacity">Capacity <span class="req">*</span></label>
                            <input type="number" id="capacity" name="capacity"
                                   value="<?php echo htmlspecialchars($capacity); ?>"
                                   placeholder="e.g. 2" min="1" max="10">
                            <div class="hint">Number of students this room can hold</div>
                        </div>
                        <div class="form-group">
                            <label for="price_per_month">Price per Month <span class="req">*</span></label>
                            <div class="input-wrap">
                                <span class="input-prefix">kr.</span>
                                <input type="number" id="price_per_month" name="price_per_month"
                                       class="has-prefix"
                                       value="<?php echo htmlspecialchars($price_per_month); ?>"
                                       placeholder="450.00" step="0.01" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="section-label">Availability &amp; Features</div>
                    <div class="form-group">
                        <label for="available_from">Available From <span class="req">*</span></label>
                        <input type="date" id="available_from" name="available_from"
                               value="<?php echo htmlspecialchars($available_from); ?>">
                        <div class="hint">Earliest date this room can be assigned to a student</div>
                    </div>

                    <div class="form-group">
                        <label>Ensuite Facility</label>
                        <div class="checkbox-wrap">
                            <input type="checkbox" id="ensuite_facility" name="ensuite_facility"
                                   <?php echo $ensuite_facility ? 'checked' : ''; ?>>
                            <label for="ensuite_facility">🚿 This room has a private ensuite bathroom</label>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btn-submit">✚ Add Room</button>
                        <a href="index.php" class="btn-back">← Back to Rooms</a>
                    </div>

                </form>
            </div>
        </div>
    </main>

</div>

</body>
</html>
<?php $db = null; ?>