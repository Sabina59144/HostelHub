<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../config/auth.php');
require_once(__DIR__ . '/../config/schema.php');
require_once(__DIR__ . '/../config/db.php');

$user = requireLoginJson();
if (($user['role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'errors' => ['Only students can create maintenance requests.']]);
    exit;
}

function generateTicketNumber(PDO $db): string
{
    $stmt = $db->query("SELECT COALESCE(MAX(maintenance_id), 0) AS max_id FROM maintenance");
    $maxId = (int)$stmt->fetchColumn();
    $next = $maxId + 1;
    return sprintf('MT-%05d', $next);
}

$errors = [];
$room_id         = isset($_POST['room_id'])        ? trim($_POST['room_id'])        : '';
$date_reported   = isset($_POST['date_reported'])  ? trim($_POST['date_reported'])  : '';
$description     = isset($_POST['description'])    ? trim($_POST['description'])    : '';
$resolution_note = isset($_POST['resolution_note'])? trim($_POST['resolution_note']): '';
$status          = 'Pending';
$reported_by     = (int)$user['id'];

if ($room_id === '')     $errors[] = 'Room ID is required.';
if ($description === '') $errors[] = 'Description is required.';
if ($date_reported === '') $errors[] = 'Date Reported is required.';
if (strlen($description) > 2000)     $errors[] = 'Description must be 2000 characters or less.';
if (strlen($resolution_note) > 2000) $errors[] = 'Resolution Note must be 2000 characters or less.';

if ($room_id !== '' && !ctype_digit($room_id)) $errors[] = 'Room ID must be a room ID.';
if ($date_reported !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_reported)) {
    $errors[] = 'Date Reported must be in YYYY-MM-DD format.';
}
if ($date_reported !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_reported)) {
    [$y, $m, $d] = array_map('intval', explode('-', $date_reported));
    if (!checkdate($m, $d, $y)) $errors[] = 'Date Reported must be a valid calendar date.';
}

if (!empty($errors)) {
    echo json_encode([ 'success' => false, 'errors' => $errors ]);
    exit;
}

try {
    ensureMaintenanceArchiveSchema($db);

    $roomExistsStmt = $db->prepare("SELECT 1 FROM rooms WHERE room_id = :room_id LIMIT 1");
    $roomExistsStmt->bindValue(':room_id', (int)$room_id, PDO::PARAM_INT);
    $roomExistsStmt->execute();
    if (!$roomExistsStmt->fetchColumn()) $errors[] = 'Selected room does not exist.';

    if (!empty($errors)) {
        echo json_encode([ 'success' => false, 'errors' => $errors ]);
        exit;
    }

    $ticket_number = generateTicketNumber($db);
    $insertStmt = $db->prepare("INSERT INTO maintenance (ticket_number, room_id, description, date_reported, reported_by, is_resolved, status, resolution_note) VALUES (:ticket, :room, :description, :date_reported, :reported_by, :is_resolved, :status, :resolution_note)");
    $insertStmt->bindValue(':ticket',        $ticket_number, PDO::PARAM_STR);
    $insertStmt->bindValue(':room',          (int)$room_id,  PDO::PARAM_INT);
    $insertStmt->bindValue(':description',   $description,   PDO::PARAM_STR);
    $insertStmt->bindValue(':date_reported', $date_reported, PDO::PARAM_STR);
    $insertStmt->bindValue(':reported_by',   $reported_by,   PDO::PARAM_INT);
    $insertStmt->bindValue(':is_resolved',   0,              PDO::PARAM_INT);
    $insertStmt->bindValue(':status',        $status,        PDO::PARAM_STR);
    $insertStmt->bindValue(':resolution_note', $resolution_note, PDO::PARAM_STR);
    $insertStmt->execute();

    echo json_encode([
        'success' => true,
        'inserted_id' => $db->lastInsertId(),
        'data' => [
            'ticket_number' => htmlspecialchars($ticket_number, ENT_QUOTES, 'UTF-8')
        ]
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode([ 'success' => false, 'errors' => [ 'Could not generate a unique ticket number. Please try again.' ] ]);
    } else {
        echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
    }
}

