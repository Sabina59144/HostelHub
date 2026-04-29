<?php
// ── Start session and check login ─────────────────────────────
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ── Connect to database ───────────────────────────────────────
require_once '../includes/db.php';

// ── Get all rooms for the dropdown ────────────────────────────
$roomsResult = $db->query("SELECT room_id, room_number, room_type FROM rooms ORDER BY room_number")->fetchAll();

// ── Initialise variables ──────────────────────────────────────
$errors         = [];
$success        = "";
$student_number = $full_name = $email = $date_of_birth = "";
$room_id        = "";

// ── Handle form submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Get and sanitise inputs ───────────────────────────────
    $student_number = trim($_POST['student_number']);
    $full_name      = trim($_POST['full_name']);
    $email          = trim($_POST['email']);
    $date_of_birth  = trim($_POST['date_of_birth']);
    $room_id        = $_POST['room_id'] !== '' ? (int)$_POST['room_id'] : null;
    $status         = 1; // default: currently enrolled

    // ── Validation ────────────────────────────────────────────

    // Student number — required and must match format STU-YYYY-XXX
    if (empty($student_number)) {
        $errors[] = "Student number is required.";
    } elseif (!preg_match('/^STU-\d{4}-\d{3}$/', $student_number)) {
        $errors[] = "Student number must follow the format STU-YYYY-XXX (e.g. STU-2024-001).";
    }

    // Full name — required
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }

    // Email — required and must be valid format
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Check student_number is unique
    if (empty($errors)) {
        $checkNum = $db->prepare("SELECT student_id FROM students WHERE student_number = ?");
        $checkNum->execute([$student_number]);
        if ($checkNum->rowCount() > 0) {
            $errors[] = "This student number already exists. Please use a unique number.";
        }
    }

    // Check email is unique
    if (empty($errors)) {
        $checkEmail = $db->prepare("SELECT student_id FROM students WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->rowCount() > 0) {
            $errors[] = "This email address is already registered.";
        }
    }

    // ── Insert into database if no errors ─────────────────────
    if (empty($errors)) {
        $dob  = !empty($date_of_birth) ? $date_of_birth : null;
        $stmt = $db->prepare(
            "INSERT INTO students (student_number, full_name, email, date_of_birth, room_id, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if ($stmt->execute([$student_number, $full_name, $email, $dob, $room_id, $status])) {
            $success        = "Student '{$full_name}' has been registered successfully!";
            // Clear form fields after successful insert
            $student_number = $full_name = $email = $date_of_birth = "";
            $room_id        = "";
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
    <title>HostelHub — Add Student</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; color: #333; }

        /* ── Navbar ── */
        .navbar {
            background: #B71C1C; color: white;
            padding: 14px 30px; display: flex;
            justify-content: space-between; align-items: center;
        }
        .navbar h1 { font-size: 20px; }
        .navbar a  { color: white; text-decoration: none; margin-left: 20px; font-size: 13px; }
        .navbar a:hover { text-decoration: underline; }

        /* ── Container ── */
        .container { padding: 30px; max-width: 700px; margin: 0 auto; }

        /* ── Page title ── */
        .page-title { margin-bottom: 24px; }
        .page-title h2 { font-size: 22px; color: #B71C1C; }
        .page-title p  { font-size: 13px; color: #666; margin-top: 4px; }

        /* ── Alerts ── */
        .alert-success {
            background: #e8f5e9; border: 1px solid #a5d6a7;
            color: #2e7d32; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;
        }
        .alert-error {
            background: #ffebee; border: 1px solid #ef9a9a;
            color: #c62828; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px;
        }
        .alert-error ul { padding-left: 18px; margin-top: 6px; }

        /* ── Form card ── */
        .form-card {
            background: white; border-radius: 10px;
            padding: 28px 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        /* ── Form fields ── */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block; font-size: 13px;
            font-weight: 600; margin-bottom: 6px; color: #444;
        }
        .form-group input,
        .form-group select {
            width: 100%; padding: 10px 12px;
            border: 1px solid #ddd; border-radius: 6px;
            font-size: 14px; font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none; border-color: #B71C1C;
        }
        .form-group .hint {
            font-size: 11px; color: #999; margin-top: 4px;
        }

        /* ── Buttons ── */
        .btn-row { display: flex; gap: 12px; margin-top: 24px; }
        .btn-submit {
            background: #B71C1C; color: white;
            border: none; padding: 11px 28px;
            border-radius: 6px; font-size: 14px;
            cursor: pointer; font-weight: 600;
        }
        .btn-submit:hover { background: #8B0000; }
        .btn-back {
            background: white; color: #555;
            border: 1px solid #ccc; padding: 11px 28px;
            border-radius: 6px; font-size: 14px;
            text-decoration: none; font-weight: 600;
        }
        .btn-back:hover { background: #f5f5f5; }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="navbar">
    <h1>🏨 HostelHub</h1>
    <div>
        <span style="font-size:13px; opacity:0.85;">Logged in as:
            <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </span>
        <a href="index.php">👨‍🎓 Students</a>
        <a href="../dashboard.php">🏠 Dashboard</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>
</nav>

<!-- ── Main Content ── -->
<div class="container">

    <div class="page-title">
        <h2>➕ Add New Student</h2>
        <p>Fill in the form below to register a new student into the system</p>
    </div>

    <!-- Success message -->
    <?php if ($success): ?>
        <div class="alert-success">✅ <?php echo $success; ?>
            <a href="list_students.php" style="margin-left:10px;">View all students →</a>
        </div>
    <?php endif; ?>

    <!-- Error messages -->
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

    <!-- Add Student Form -->
    <div class="form-card">
        <form method="POST" action="">

            <!-- Student Number -->
            <div class="form-group">
                <label for="student_number">Student Number *</label>
                <input type="text" id="student_number" name="student_number"
                       value="<?php echo htmlspecialchars($student_number); ?>"
                       placeholder="e.g. STU-2024-001">
                <div class="hint">Format must be STU-YYYY-XXX</div>
            </div>

            <!-- Full Name -->
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name"
                       value="<?php echo htmlspecialchars($full_name); ?>"
                       placeholder="e.g. John Smith">
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email"
                       value="<?php echo htmlspecialchars($email); ?>"
                       placeholder="e.g. john@student.edu">
            </div>

            <!-- Date of Birth -->
            <div class="form-group">
                <label for="date_of_birth">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth"
                       value="<?php echo htmlspecialchars($date_of_birth); ?>">
                <div class="hint">Optional</div>
            </div>

            <!-- Room Assignment -->
            <div class="form-group">
                <label for="room_id">Assign Room</label>
                <select id="room_id" name="room_id">
                    <option value="">-- No room assigned yet --</option>
                    <?php
                    foreach ($roomsResult as $room):
                    ?>
                        <option value="<?php echo $room['room_id']; ?>"
                            <?php echo ($room_id == $room['room_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($room['room_number'] . ' (' . $room['room_type'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="hint">Optional — can be assigned later</div>
            </div>

            <!-- Buttons -->
            <div class="btn-row">
                <button type="submit" class="btn-submit">➕ Register Student</button>
                <a href="index.php" class="btn-back">← Back</a>
            </div>

        </form>
    </div>

</div><!-- end container -->

</body>
</html>
<?php $db = null; ?>
