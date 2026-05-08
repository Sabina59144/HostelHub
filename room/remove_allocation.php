<?php
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     header("Location: ../login.php");
//     exit();
// }

require_once '../includes/db.php';

// ── Validate student_id ───────────────────────────────────────────────
$student_id = filter_input(INPUT_GET,  'student_id', FILTER_VALIDATE_INT)
           ?: filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

if (!$student_id) {
    header("Location: list_rooms.php");
    exit();
}

// ── Load student (must be active and currently allocated) ─────────────
$sStmt = $db->prepare(
    "SELECT s.student_id, s.student_number, s.full_name, s.email,
            s.room_id,
            r.room_number, r.room_type, r.floor, r.capacity,
            r.price_per_month, r.ensuite_facility
     FROM students s
     LEFT JOIN rooms r ON r.room_id = s.room_id
     WHERE s.student_id = ? AND s.status = 1"
);
$sStmt->execute([$student_id]);
$student = $sStmt->fetch();

if (!$student || !$student['room_id']) {
    // Student not found, inactive, or not in any room
    header("Location: list_rooms.php");
    exit();
}

$errors  = [];
$success = "";

// ── Handle confirmed removal (POST) ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {

    $postedStudentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);

    if ((int)$postedStudentId !== (int)$student_id) {
        $errors[] = "Invalid request — student ID mismatch.";
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // Remove student from the room
            $db->prepare("UPDATE students SET room_id = NULL WHERE student_id = ?")
               ->execute([$student_id]);

            // Close any open allocation history row for this student
            try {
                $db->prepare(
                    "UPDATE allocations
                     SET end_date = CURDATE()
                     WHERE student_id = ? AND end_date IS NULL"
                )->execute([$student_id]);
            } catch (PDOException $ignored) { /* allocations table may not exist */ }

            $db->commit();

            // Redirect back with success message
            header("Location: list_rooms.php?msg=removed");
            exit();

        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Could not remove the allocation: " . $e->getMessage();
        }
    }
}

$activeNav = 'rooms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HostelHub — Remove Student from Room</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .warning-card { border-top: 4px solid #f59e0b; }

        .student-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .student-summary .s-label {
            font-size: 11px; font-weight: 700; color: #92400e;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .student-summary .s-value {
            font-size: 15px; color: #1f2937; font-weight: 600;
            margin-top: 4px;
        }

        .room-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }
        .room-summary .r-label {
            font-size: 11px; font-weight: 700; color: #6b7280;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .room-summary .r-value {
            font-size: 15px; color: #1f2937; font-weight: 600;
            margin-top: 4px;
        }

        .confirm-note {
            font-size: 14px; color: #374151;
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 8px; padding: 14px 16px;
            margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .confirm-note svg { flex-shrink: 0; color: #dc2626; margin-top: 2px; }

        .btn-danger {
            background: #dc2626; color: white;
            border: none; padding: 10px 22px;
            border-radius: 8px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: background 0.15s;
        }
        .btn-danger:hover { background: #b91c1c; }
    </style>
</head>
<body>

<?php include '_navbar.php'; ?>

<div class="container">

    <div class="page-title">
        <h2>Remove Student from Room</h2>
        <p>You are about to unallocate a student from their current room. Please review the details below.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">
            <?php foreach ($errors as $e): ?>
                <div><?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card warning-card">
        <div class="card-header">
            <h3>Allocation to Remove</h3>
            <a href="list_rooms.php">Back to Rooms</a>
        </div>

        <!-- Student details -->
        <div class="section-title" style="margin-bottom: 10px;">Student</div>
        <div class="student-summary">
            <div>
                <div class="s-label">Student Number</div>
                <div class="s-value"><?php echo htmlspecialchars($student['student_number']); ?></div>
            </div>
            <div>
                <div class="s-label">Full Name</div>
                <div class="s-value"><?php echo htmlspecialchars($student['full_name']); ?></div>
            </div>
            <div>
                <div class="s-label">Email</div>
                <div class="s-value"><?php echo htmlspecialchars($student['email']); ?></div>
            </div>
        </div>

        <!-- Current room details -->
        <div class="section-title" style="margin-bottom: 10px;">Current Room</div>
        <div class="room-summary">
            <div>
                <div class="r-label">Room Number</div>
                <div class="r-value"><?php echo htmlspecialchars($student['room_number']); ?></div>
            </div>
            <div>
                <div class="r-label">Floor</div>
                <div class="r-value">Floor <?php echo htmlspecialchars($student['floor'] ?? '—'); ?></div>
            </div>
            <div>
                <div class="r-label">Room Type</div>
                <div class="r-value"><?php echo ucfirst(htmlspecialchars($student['room_type'])); ?></div>
            </div>
            <div>
                <div class="r-label">Price / Month</div>
                <div class="r-value"><?php echo number_format($student['price_per_month'], 2); ?> kr.</div>
            </div>
        </div>

        <!-- Warning note -->
        <div class="confirm-note">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.4"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>
                Removing this allocation will set
                <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>'s
                room to <em>none</em>. The student record will remain active — they can be
                re-allocated to any room at any time.
            </span>
        </div>

        <!-- Confirmation form -->
        <form method="POST" action=""
              onsubmit="return confirm('Remove <?php echo htmlspecialchars($student['full_name'], ENT_QUOTES); ?> from Room <?php echo htmlspecialchars($student['room_number'], ENT_QUOTES); ?>?');">
            <input type="hidden" name="student_id" value="<?php echo (int)$student_id; ?>">
            <input type="hidden" name="confirm"    value="yes">

            <div class="btn-row">
                <button type="submit" class="btn btn-danger">
                    Yes, Remove from Room
                </button>
                <a href="list_rooms.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

    </div>

</div>

</body>
</html>
<?php $db = null; ?>
