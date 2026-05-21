<?php
// dashboard.php
require_once("includes/session.php");
require_once("includes/db.php");
requireLogin();

$totalStudents   = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$availableRooms  = $db->query("SELECT COUNT(*) FROM rooms WHERE room_id NOT IN (SELECT room_id FROM students WHERE room_id IS NOT NULL)")->fetchColumn();
$feesPending     = $db->query("SELECT COUNT(*) FROM fees WHERE is_paid IS NULL")->fetchColumn();
$maintenanceOpen = $db->query("SELECT COUNT(*) FROM maintenance WHERE is_resolved = 0")->fetchColumn();

$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — HostelHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
        }

        /* ── Hero Banner ── */
        .dashboard-hero {
            position: relative;
            width: 100%;
            height: 340px;
            display: flex;
            align-items: flex-end;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, #0d1b2a 0%, #1a3a5c 50%, #2563a8 100%);
        }

        .stars { position: absolute; inset: 0; pointer-events: none; }
        .star {
            position: absolute;
            background: #fff;
            border-radius: 50%;
            animation: twinkle 3s infinite alternate;
        }
        @keyframes twinkle {
            from { opacity: 0.15; }
            to   { opacity: 0.9; }
        }

        .moon {
            position: absolute;
            top: 30px; right: 100px;
            width: 52px; height: 52px;
            background: radial-gradient(circle at 38% 38%, #fffde7, #fdd835);
            border-radius: 50%;
            box-shadow: 0 0 35px 10px rgba(253,216,53,0.22);
        }

        /* SVG city scene */
        .city-svg {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            width: 100%; height: 260px;
        }

        .ground-strip {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 26px;
            background: #0a111f;
        }
        .road-dashes {
            position: absolute;
            bottom: 10px; left: 0; right: 0;
            height: 3px;
            background: repeating-linear-gradient(
                90deg, #fdd835 0, #fdd835 28px, transparent 28px, transparent 56px
            );
            opacity: 0.45;
        }

        .hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(to bottom, transparent 40%, rgba(8,16,28,0.88) 100%);
        }

        .hero-content {
            position: relative; z-index: 2;
            padding: 0 40px 32px;
            width: 100%;
        }
        .hero-greeting {
            font-size: 12px; font-weight: 600;
            letter-spacing: .14em; text-transform: uppercase;
            color: #fdd835; margin-bottom: 6px;
        }
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem; font-weight: 700;
            color: #fff; line-height: 1.2; margin-bottom: 5px;
        }
        .hero-sub { color: rgba(255,255,255,0.5); font-size: 14px; }

        .hero-date {
            position: absolute; top: 24px; right: 40px; z-index: 2; text-align: right;
        }
        .hero-date .date-day {
            font-family: 'Playfair Display', serif;
            font-size: 2.6rem; color: #fff; line-height: 1; font-weight: 700;
        }
        .hero-date .date-rest {
            font-size: 12px; color: rgba(255,255,255,0.45);
            letter-spacing: .08em; text-transform: uppercase;
        }

        /* ── Main ── */
        .main-content {
            background: #f0f4f8;
            padding: 32px 40px 48px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-label {
            font-size: 11px; font-weight: 700;
            letter-spacing: .12em; text-transform: uppercase;
            color: #94a3b8; margin-bottom: 16px;
        }

        /* ── Stat cards ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px; margin-bottom: 36px;
        }
        @media (max-width: 900px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }

        .stat-card {
            background: #fff; border-radius: 16px;
            padding: 24px 22px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid #e8edf3;
            position: relative; overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }

        .stat-card::before {
            content: ''; position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            border-radius: 16px 16px 0 0;
        }
        .stat-card.blue::before  { background: linear-gradient(90deg,#1a56db,#60a5fa); }
        .stat-card.green::before { background: linear-gradient(90deg,#059669,#34d399); }
        .stat-card.amber::before { background: linear-gradient(90deg,#d97706,#fbbf24); }
        .stat-card.rose::before  { background: linear-gradient(90deg,#dc2626,#fb7185); }

        .stat-icon-wrap {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin-bottom: 16px;
        }
        .blue  .stat-icon-wrap { background: #eff6ff; }
        .green .stat-icon-wrap { background: #ecfdf5; }
        .amber .stat-icon-wrap { background: #fffbeb; }
        .rose  .stat-icon-wrap { background: #fff1f2; }

        .stat-number {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem; font-weight: 700;
            line-height: 1; color: #0f1923;
            display: block; margin-bottom: 5px;
        }
        .stat-label { font-size: 13px; color: #64748b; font-weight: 500; display: block; }

        .stat-arrow {
            position: absolute; bottom: 18px; right: 18px;
            width: 26px; height: 26px; border-radius: 50%;
            background: #f8fafc; border: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; color: #94a3b8; text-decoration: none;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }
        .stat-card:hover .stat-arrow { background: #1a56db; color: #fff; border-color: #1a56db; }

        /* ── Quick links ── */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }
        @media (max-width: 700px) { .quick-links { grid-template-columns: 1fr 1fr; } }

        .quick-link {
            background: #fff; border-radius: 12px;
            padding: 18px 20px;
            display: flex; align-items: center; gap: 14px;
            border: 1px solid #e8edf3;
            text-decoration: none; color: #1e293b;
            font-weight: 600; font-size: 14px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s, color 0.18s;
        }
        .quick-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.09);
            border-color: #1a56db; color: #1a56db; text-decoration: none;
        }
        .quick-link-icon {
            width: 38px; height: 38px; border-radius: 10px;
            background: #f1f5f9;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }

        /* ── Footer ── */
        .dashboard-footer {
            background: #0f1923;
            color: rgba(255,255,255,0.28);
            text-align: center; font-size: 12px;
            padding: 14px; letter-spacing: .04em;
        }
    </style>
</head>
<body>

<?php include("includes/navbar.php"); ?>

<!-- Hero with CSS cityscape -->
<div class="dashboard-hero">
    <div class="hero-bg"></div>
    <div class="stars" id="stars"></div>
    <div class="moon"></div>

    <!-- SVG City Buildings -->
    <svg class="city-svg" viewBox="0 0 1440 260" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
        <!-- Sky gradient def -->
        <defs>
            <linearGradient id="winGlow" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#fdd835" stop-opacity="0.9"/>
                <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.6"/>
            </linearGradient>
        </defs>

        <!-- Building group (dark silhouettes) -->
        <!-- Far left -->
        <rect x="0"   y="130" width="80"  height="130" fill="#0a1628"/>
        <rect x="85"  y="160" width="60"  height="100" fill="#0c1a30"/>
        <rect x="150" y="110" width="50"  height="150" fill="#0a1628"/>
        <rect x="205" y="145" width="90"  height="115" fill="#0c1a30"/>

        <!-- Left-center -->
        <rect x="300" y="80"  width="70"  height="180" fill="#0a1628"/>
        <rect x="375" y="120" width="55"  height="140" fill="#0c1a30"/>
        <rect x="435" y="100" width="40"  height="160" fill="#091525"/>

        <!-- Main hostel (center, tallest, highlighted) -->
        <rect x="560" y="30"  width="160" height="230" fill="#0d1f38" rx="2"/>
        <!-- Blue accent top -->
        <rect x="560" y="30"  width="160" height="4"   fill="#1a56db"/>
        <!-- Sign -->
        <rect x="600" y="50"  width="80"  height="20"  fill="#1a56db" rx="3" opacity="0.8"/>

        <!-- Right-center -->
        <rect x="730" y="90"  width="75"  height="170" fill="#0a1628"/>
        <rect x="810" y="130" width="50"  height="130" fill="#0c1a30"/>
        <rect x="865" y="105" width="65"  height="155" fill="#091525"/>

        <!-- Far right -->
        <rect x="940" y="140" width="100" height="120" fill="#0c1a30"/>
        <rect x="1045" y="115" width="60" height="145" fill="#0a1628"/>
        <rect x="1110" y="80"  width="80" height="180" fill="#0c1a30"/>
        <rect x="1195" y="130" width="70" height="130" fill="#0a1628"/>
        <rect x="1270" y="100" width="90" height="160" fill="#091525"/>
        <rect x="1365" y="140" width="75" height="120" fill="#0c1a30"/>

        <!-- Windows - lit up warm yellow -->
        <!-- Left buildings -->
        <rect x="10"  y="145" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="28"  y="145" width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.4"/>
        <rect x="46"  y="145" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="10"  y="165" width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.5"/>
        <rect x="28"  y="165" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="46"  y="165" width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.3"/>
        <rect x="10"  y="185" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="28"  y="185" width="12" height="10" fill="url(#winGlow)" rx="1"/>

        <rect x="160" y="125" width="10" height="8" fill="url(#winGlow)" rx="1"/>
        <rect x="178" y="125" width="10" height="8" fill="url(#winGlow)" rx="1" opacity="0.4"/>
        <rect x="160" y="143" width="10" height="8" fill="url(#winGlow)" rx="1" opacity="0.7"/>
        <rect x="178" y="143" width="10" height="8" fill="url(#winGlow)" rx="1"/>
        <rect x="160" y="161" width="10" height="8" fill="url(#winGlow)" rx="1"/>
        <rect x="178" y="161" width="10" height="8" fill="url(#winGlow)" rx="1" opacity="0.3"/>

        <rect x="312" y="95"  width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="332" y="95"  width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.5"/>
        <rect x="352" y="95"  width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="312" y="115" width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.4"/>
        <rect x="332" y="115" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="352" y="115" width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.6"/>
        <rect x="312" y="135" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="332" y="135" width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.3"/>
        <rect x="352" y="135" width="12" height="10" fill="url(#winGlow)" rx="1"/>

        <!-- Main hostel windows (grid, more lit) -->
        <rect x="575" y="70"  width="18" height="14" fill="url(#winGlow)" rx="2"/>
        <rect x="601" y="70"  width="18" height="14" fill="url(#winGlow)" rx="2" opacity="0.5"/>
        <rect x="627" y="70"  width="18" height="14" fill="url(#winGlow)" rx="2"/>
        <rect x="653" y="70"  width="18" height="14" fill="url(#winGlow)" rx="2"/>
        <rect x="679" y="70"  width="18" height="14" fill="url(#winGlow)" rx="2" opacity="0.4"/>

        <rect x="575" y="95"  width="18" height="14" fill="url(#winGlow)" rx="2" opacity="0.4"/>
        <rect x="601" y="95"  width="18" height="14" fill="url(#winGlow)" rx="2"/>
        <rect x="627" y="95"  width="18" height="14" fill="url(#winGlow)" rx="2"/>
        <rect x="653" y="95"  width="18" height="14" fill="url(#winGlow)" rx="2" opacity="0.3"/>
        <rect x="679" y="95"  width="18" height="14" fill="url(#winGlow)" rx="2"/>

        <rect x="575" y="120" width="18" height="14" fill="url(#winGlow)" rx="2"/>
        <rect x="601" y="120" width="18" height="14" fill="url(#winGlow)" rx="2" opacity="0.6"/>
        <rect x="627" y="120" width="18" height="14" fill="url(#winGlow)" rx="2" opacity="0.3"/>
        <rect x="653" y="120" width="18" height="14" fill="url(#winGlow)" rx="2"/>
        <rect x="679" y="120" width="18" height="14" fill="url(#winGlow)" rx="2"/>

        <rect x="575" y="145" width="18" height="14" fill="url(#winGlow)" rx="2" opacity="0.5"/>
        <rect x="601" y="145" width="18" height="14" fill="url(#winGlow)" rx="2"/>
        <rect x="627" y="145" width="18" height="14" fill="url(#winGlow)" rx="2"/>
        <rect x="653" y="145" width="18" height="14" fill="url(#winGlow)" rx="2" opacity="0.4"/>
        <rect x="679" y="145" width="18" height="14" fill="url(#winGlow)" rx="2"/>

        <!-- Right buildings windows -->
        <rect x="742" y="105" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="762" y="105" width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.4"/>
        <rect x="782" y="105" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="742" y="125" width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.6"/>
        <rect x="762" y="125" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="782" y="125" width="12" height="10" fill="url(#winGlow)" rx="1" opacity="0.3"/>
        <rect x="742" y="145" width="12" height="10" fill="url(#winGlow)" rx="1"/>
        <rect x="762" y="145" width="12" height="10" fill="url(#winGlow)" rx="1"/>

        <rect x="1120" y="95"  width="14" height="11" fill="url(#winGlow)" rx="1"/>
        <rect x="1142" y="95"  width="14" height="11" fill="url(#winGlow)" rx="1" opacity="0.5"/>
        <rect x="1164" y="95"  width="14" height="11" fill="url(#winGlow)" rx="1"/>
        <rect x="1120" y="116" width="14" height="11" fill="url(#winGlow)" rx="1" opacity="0.3"/>
        <rect x="1142" y="116" width="14" height="11" fill="url(#winGlow)" rx="1"/>
        <rect x="1164" y="116" width="14" height="11" fill="url(#winGlow)" rx="1" opacity="0.7"/>
        <rect x="1120" y="137" width="14" height="11" fill="url(#winGlow)" rx="1"/>
        <rect x="1142" y="137" width="14" height="11" fill="url(#winGlow)" rx="1" opacity="0.4"/>

        <!-- Trees -->
        <rect x="488" y="210" width="8"  height="50" fill="#1a2e1a"/>
        <ellipse cx="492" cy="200" rx="18" ry="28" fill="#1a4d2e"/>
        <rect x="510" y="215" width="6"  height="45" fill="#1a2e1a"/>
        <ellipse cx="513" cy="207" rx="14" ry="22" fill="#1c5433"/>

        <rect x="870" y="205" width="8"  height="55" fill="#1a2e1a"/>
        <ellipse cx="874" cy="194" rx="20" ry="30" fill="#1a4d2e"/>

        <!-- Ground -->
        <rect x="0" y="234" width="1440" height="26" fill="#0a111f"/>
        <!-- Road dashes -->
        <rect x="0"   y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="56"  y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="112" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="168" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="224" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="280" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="336" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="392" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="448" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="504" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="560" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="616" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="672" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="728" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="784" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="840" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="896" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="952" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="1008" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="1064" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="1120" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="1176" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="1232" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="1288" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="1344" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
        <rect x="1400" y="248" width="28" height="3" fill="#fdd835" opacity="0.45"/>
    </svg>

    <div class="hero-overlay"></div>

    <div class="hero-date">
        <div class="date-day"><?= date('d') ?></div>
        <div class="date-rest"><?= date('M Y') ?></div>
    </div>

    <div class="hero-content">
        <div class="hero-greeting"><?= $greeting ?></div>
        <h1 class="hero-title"><?= htmlspecialchars($_SESSION['full_name']) ?></h1>
        <p class="hero-sub">Here's what's happening at your hostel today.</p>
    </div>
</div>

<!-- Stats & Quick Actions -->
<div class="main-content">

    <p class="section-label">Overview</p>
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-icon-wrap">🎓</div>
            <span class="stat-number"><?= $totalStudents ?></span>
            <span class="stat-label">Total Students</span>
            <a href="pages/students.php" class="stat-arrow">→</a>
        </div>
        <div class="stat-card green">
            <div class="stat-icon-wrap">🛏️</div>
            <span class="stat-number"><?= $availableRooms ?></span>
            <span class="stat-label">Rooms Available</span>
            <a href="pages/rooms.php" class="stat-arrow">→</a>
        </div>
         <div class="stat-card amber">
            <div class="stat-icon-wrap">💰</div>
            <span class="stat-number"><?= $feesPending ?></span>
            <span class="stat-label">Fees Pending</span>
            <a href="pages/fees.php" class="stat-arrow">→</a>
        </div>
        <div class="stat-card rose">
            <div class="stat-icon-wrap">🔧</div>
            <span class="stat-number"><?= $maintenanceOpen ?></span>
            <span class="stat-label">Maintenance Open</span>
            <a href="pages/maintenance.php" class="stat-arrow">→</a>
        </div>
    </div>

    <p class="section-label">Quick Actions</p>
    <div class="quick-links">
        <a href="pages/students.php" class="quick-link">
            <div class="quick-link-icon">🎓</div> Manage Students
        </a>
        <a href="pages/rooms.php" class="quick-link">
            <div class="quick-link-icon">🛏️</div> Manage Rooms
        </a>
        <a href="pages/fees.php" class="quick-link">
            <div class="quick-link-icon">💰</div> View Fees
        </a>
        <a href="pages/maintenance.php" class="quick-link">
            <div class="quick-link-icon">🔧</div> Maintenance
        </a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="pages/users.php" class="quick-link">
            <div class="quick-link-icon">👥</div> Staff Management
        </a>
        <a href="pages/register_staff.php" class="quick-link">
            <div class="quick-link-icon">➕</div> Add Staff
        </a>
        <?php endif; ?>
    </div>

</div>

<div class="dashboard-footer">
    HostelHub &copy; <?= date('Y') ?> &mdash; Student Hostel Management System
</div>

<script>
    // Generate twinkling stars
    const starsEl = document.getElementById('stars');
    for (let i = 0; i < 90; i++) {
        const s = document.createElement('div');
        s.className = 'star';
        const size = Math.random() * 2.5 + 0.5;
        s.style.cssText = `
            width:${size}px;height:${size}px;
            top:${Math.random() * 70}%;
            left:${Math.random() * 100}%;
            animation-delay:${Math.random() * 4}s;
            animation-duration:${1.5 + Math.random() * 3}s;
        `;
        starsEl.appendChild(s);
    }
</script>

</body>
</html>