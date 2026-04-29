<?php
header('Content-Type: application/json');

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

// Basic pattern checks (example)
if ($room_id !== '' && !preg_match('/^[A-Za-z0-9\-]+$/', $room_id)) {
    $errors[] = 'Room ID contains invalid characters.';
}

if ($date_reported !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_reported)) {
    $errors[] = 'Date Reported must be in YYYY-MM-DD format.';
}

if (!empty($errors)) {
    echo json_encode([ 'success' => false, 'errors' => $errors ]);
    exit;
}

// At this point, data is validated. Insert into DB or process as needed.
// For now, return success and echo the sanitized data back (without storing).

$response = [
    'success' => true,
    'data' => [
        'ticket_number' => htmlspecialchars($ticket_number, ENT_QUOTES, 'UTF-8'),
        'room_id' => htmlspecialchars($room_id, ENT_QUOTES, 'UTF-8'),
        'assigned_to' => htmlspecialchars($assigned_to, ENT_QUOTES, 'UTF-8'),
        'date_reported' => $date_reported,
        'reported_by' => htmlspecialchars($reported_by, ENT_QUOTES, 'UTF-8'),
        'status' => htmlspecialchars($status, ENT_QUOTES, 'UTF-8')
    ]
];

echo json_encode($response);

?>
