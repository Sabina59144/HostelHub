<?php
header('Content-Type: application/json; charset=UTF-8');

$errors = [];

$ticket_number = isset($_POST['ticket_number']) ? trim($_POST['ticket_number']) : '';
$room_id = isset($_POST['room_id']) ? trim($_POST['room_id']) : '';
$date_reported = isset($_POST['date_reported']) ? trim($_POST['date_reported']) : '';
$reported_by = isset($_POST['reported_by']) ? trim($_POST['reported_by']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

if ($ticket_number === '') $errors[] = 'Ticket Number is required.';
if ($room_id === '') $errors[] = 'Room ID is required.';
$assigned_to = isset($_POST['assigned_to']) ? trim($_POST['assigned_to']) : '';
if ($assigned_to === '') $errors[] = 'Assigned To is required.';
if ($date_reported === '') $errors[] = 'Date Reported is required.';
if ($reported_by === '') $errors[] = 'Reported By is required.';
if ($status === '') $errors[] = 'Status is required.';
if ($ticket_number !== '' && strlen($ticket_number) > 20) $errors[] = 'Ticket Number must be 20 characters or less.';

// room_id should be numeric id
if ($room_id !== '' && !ctype_digit($room_id)) {
    $errors[] = 'Room ID must be a room ID.';
}

if ($date_reported !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_reported)) {
    $errors[] = 'Date Reported must be in YYYY-MM-DD format.';
}
if ($date_reported !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_reported)) {
    [$y, $m, $d] = array_map('intval', explode('-', $date_reported));
    if (!checkdate($m, $d, $y)) {
        $errors[] = 'Date Reported must be a valid calendar date.';
    }
}

// assigned_to and reported_by should be numeric IDs
if ($assigned_to !== '' && !ctype_digit($assigned_to)) {
    $errors[] = 'Assigned To must be a staff ID.';
}
if ($reported_by !== '' && !ctype_digit($reported_by)) {
    $errors[] = 'Reported By must be a student ID.';
}
if ($status !== '' && !in_array($status, ['Resolved', 'Not Resolved'], true)) {
    $errors[] = 'Status must be either Resolved or Not Resolved.';
}

if (!empty($errors)) {
    echo json_encode([ 'success' => false, 'errors' => $errors ]);
    exit;
}
// At this point, data is validated. Insert into DB.
require_once(__DIR__ . '/../config/db.php');

try {
    $roomExistsStmt = $db->prepare("SELECT 1 FROM rooms WHERE room_id = :room_id LIMIT 1");
    $roomExistsStmt->bindValue(':room_id', (int)$room_id, PDO::PARAM_INT);
    $roomExistsStmt->execute();
    if (!$roomExistsStmt->fetchColumn()) {
        $errors[] = 'Selected room does not exist.';
    }

    $staffExistsStmt = $db->prepare("SELECT 1 FROM staffs WHERE staff_id = :staff_id LIMIT 1");
    $staffExistsStmt->bindValue(':staff_id', (int)$assigned_to, PDO::PARAM_INT);
    $staffExistsStmt->execute();
    if (!$staffExistsStmt->fetchColumn()) {
        $errors[] = 'Selected staff does not exist.';
    }

    $studentExistsStmt = $db->prepare("SELECT 1 FROM students WHERE student_id = :student_id LIMIT 1");
    $studentExistsStmt->bindValue(':student_id', (int)$reported_by, PDO::PARAM_INT);
    $studentExistsStmt->execute();
    if (!$studentExistsStmt->fetchColumn()) {
        $errors[] = 'Selected student does not exist.';
    }

    if (!empty($errors)) {
        echo json_encode([ 'success' => false, 'errors' => $errors ]);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO maintenance (ticket_number, room_id, assigned_to, date_reported, reported_by, is_resolved) VALUES (:ticket, :room, :assigned, :date_reported, :reported_by, :is_resolved)");

    $stmt->bindValue(':ticket', $ticket_number, PDO::PARAM_STR);
    $stmt->bindValue(':room', (int)$room_id, PDO::PARAM_INT);
    $stmt->bindValue(':assigned', (int)$assigned_to, PDO::PARAM_INT);
    $stmt->bindValue(':date_reported', $date_reported, PDO::PARAM_STR);
    $stmt->bindValue(':reported_by', (int)$reported_by, PDO::PARAM_INT);
    $stmt->bindValue(':is_resolved', ($status === 'Resolved') ? 1 : 0, PDO::PARAM_INT);

    $stmt->execute();

    $response = [
        'success' => true,
        'inserted_id' => $db->lastInsertId(),
        'data' => [
            'ticket_number' => htmlspecialchars($ticket_number, ENT_QUOTES, 'UTF-8'),
            'room_id' => (int)$room_id,
            'assigned_to' => htmlspecialchars($assigned_to, ENT_QUOTES, 'UTF-8'),
            'date_reported' => $date_reported,
            'reported_by' => (int)$reported_by,
            'status' => htmlspecialchars($status, ENT_QUOTES, 'UTF-8')
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode([ 'success' => false, 'errors' => [ 'Ticket Number already exists. Please use a unique Ticket Number.' ] ]);
    } else {
        echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
    }
}
?>
