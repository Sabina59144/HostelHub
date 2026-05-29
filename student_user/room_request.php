<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['student_id'])) { header("Location: login.php"); exit(); }

$sid = (int) $_SESSION['student_id'];

// Create table if not exists
$db->exec("
    CREATE TABLE IF NOT EXISTS room_requests (
        request_id      INT NOT NULL AUTO_INCREMENT,
        student_id      INT NOT NULL,
        current_room_id INT DEFAULT NULL,
        preferred_type  ENUM('single','double','triple') DEFAULT NULL,
        preferred_floor ENUM('A','B','C','D','E')        DEFAULT NULL,
        reason          TEXT DEFAULT NULL,
        status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_note      VARCHAR(255) DEFAULT NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (request_id),
        KEY idx_rr_student (student_id),
        CONSTRAINT fk_rr_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Student + room
$sq = $db->prepare("
    SELECT s.room_id, r.room_number
    FROM students s LEFT JOIN rooms r ON r.room_id = s.room_id
    WHERE s.student_id = ?
");
$sq->execute([$sid]);
$student = $sq->fetch();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Block if pending request already exists
    $chk = $db->prepare("SELECT COUNT(*) FROM room_requests WHERE student_id = ? AND status = 'pending'");
    $chk->execute([$sid]);
    if ($chk->fetchColumn() > 0) {
        $error = "You already have a pending request. Wait for a response before submitting a new one.";
    } else {
        $reason = trim($_POST['reason'] ?? '');
        $type   = $_POST['preferred_type']  ?? '';
        $floor  = strtoupper($_POST['preferred_floor'] ?? '');

        if (empty($reason)) {
            $error = "Please provide a reason for the room change.";
        } else {
            $ins = $db->prepare("
                INSERT INTO room_requests (student_id, current_room_id, preferred_type, preferred_floor, reason)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([$sid, $student['room_id'] ?: null, $type ?: null, $floor ?: null, $reason]);
            $success = "Your request has been submitted. The hostel office will review it and update the status below.";
        }
    }
}

// Load student's requests
$rq = $db->prepare("SELECT * FROM room_requests WHERE student_id = ? ORDER BY created_at DESC");
$rq->execute([$sid]);
$requests = $rq->fetchAll();

$activePage = 'request';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Request — Student Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php include '_navbar.php'; ?>

<div class="page">

    <div class="page-title">
        <h1>Room Change Request</h1>
        <p>Submit a request to the hostel office to move to a different room.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid-2" style="align-items:start">

        <!-- Form -->
        <div class="card">
            <div class="card-head">
                <h2>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    New Request
                </h2>
            </div>
            <div class="card-body">

                <?php if ($student['room_id']): ?>
                    <div class="alert alert-info" style="margin-bottom:16px">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        Current room: <strong><?= htmlspecialchars($student['room_number']) ?></strong>
                    </div>
                <?php endif; ?>

                <form method="POST" action="room_request.php">
                    <div class="form-group">
                        <label>Preferred Room Type <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
                        <select name="preferred_type">
                            <option value="">No preference</option>
                            <option value="single">Single</option>
                            <option value="double">Double</option>
                            <option value="triple">Triple</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Preferred Floor <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
                        <select name="preferred_floor">
                            <option value="">No preference</option>
                            <option value="A">Floor A (1st)</option>
                            <option value="B">Floor B (2nd)</option>
                            <option value="C">Floor C (3rd)</option>
                            <option value="D">Floor D (4th)</option>
                            <option value="E">Floor E (5th)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Reason for Change <span style="color:#dc2626">*</span></label>
                        <textarea name="reason" placeholder="Explain why you would like to change your room…" required></textarea>
                        <p class="form-hint">The more detail you provide, the faster the admin can process your request.</p>
                    </div>

                    <button type="submit" class="btn-primary">Submit Request</button>
                </form>
            </div>
        </div>

        <!-- How it works -->
        <div class="card">
            <div class="card-head">
                <h2>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    How it works
                </h2>
            </div>
            <div class="card-body">
                <ul class="info-list">
                    <li style="flex-direction:column;align-items:flex-start;gap:3px;padding:12px 0">
                        <span style="font-weight:700;color:#1a1a2e">1. Submit your request</span>
                        <span style="color:#6b7280;font-size:0.82rem">Choose your preferences and explain why you need to move.</span>
                    </li>
                    <li style="flex-direction:column;align-items:flex-start;gap:3px;padding:12px 0">
                        <span style="font-weight:700;color:#1a1a2e">2. Admin reviews it</span>
                        <span style="color:#6b7280;font-size:0.82rem">The hostel office checks availability and processes the request.</span>
                    </li>
                    <li style="flex-direction:column;align-items:flex-start;gap:3px;padding:12px 0">
                        <span style="font-weight:700;color:#1a1a2e">3. Status updates below</span>
                        <span style="color:#6b7280;font-size:0.82rem">You'll see Approved or Rejected with a note from the admin.</span>
                    </li>
                </ul>
                <p style="font-size:0.78rem;color:#9ca3af;margin-top:8px">Only <strong>one pending request</strong> allowed at a time.</p>
            </div>
        </div>
    </div>

    <!-- Previous requests -->
    <?php if (!empty($requests)): ?>
    <div class="card">
        <div class="card-head">
            <h2>My Requests</h2>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr><th>Date</th><th>Preferred Type</th><th>Preferred Floor</th><th>Reason</th><th>Status</th><th>Admin Note</th></tr>
                </thead>
                <tbody>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                    <td><?= $r['preferred_type']  ? ucfirst($r['preferred_type'])  : 'Any' ?></td>
                    <td><?= $r['preferred_floor'] ? 'Floor '.$r['preferred_floor'] : 'Any' ?></td>
                    <td><?= htmlspecialchars(mb_strimwidth($r['reason'] ?? '—', 0, 50, '…')) ?></td>
                    <td>
                        <?php if ($r['status'] === 'approved'): ?>
                            <span class="badge b-green">Approved</span>
                        <?php elseif ($r['status'] === 'rejected'): ?>
                            <span class="badge b-red">Rejected</span>
                        <?php else: ?>
                            <span class="badge b-amber">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($r['admin_note'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
<?php $db = null; ?>
