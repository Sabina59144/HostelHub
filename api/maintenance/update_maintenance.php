<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../config/auth.php');
require_once(__DIR__ . '/../config/schema.php');

$user = requireLoginJson();
$errors = [];
$maintenance_id = isset($_POST['maintenance_id']) ? trim($_POST['maintenance_id']) : '';

if ($maintenance_id === '' || !ctype_digit($maintenance_id)) {
    $errors[] = 'Invalid maintenance id.';
}

if (!empty($errors)) {
    echo json_encode([ 'success' => false, 'errors' => $errors ]);
    exit;
}

try {
    ensureMaintenanceArchiveSchema($db);
    if (!schemaColumnExists($db, 'maintenance', 'status')) {
        $db->exec("ALTER TABLE maintenance ADD COLUMN status VARCHAR(20) DEFAULT 'Pending'");
    }
    if (!schemaColumnExists($db, 'maintenance', 'resolution_note')) {
        $db->exec("ALTER TABLE maintenance ADD COLUMN resolution_note TEXT DEFAULT ''");
    }

    if (($user['role'] ?? '') === 'admin') {
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $resolution_note = isset($_POST['resolution_note']) ? trim($_POST['resolution_note']) : '';
        $allowed = ['Pending', 'Inprogress', 'Completed'];
        if ($status === '' || !in_array($status, $allowed, true)) $errors[] = 'Status is invalid.';
        if (strlen($resolution_note) > 2000) $errors[] = 'Resolution Note must be 2000 characters or less.';
        if (!empty($errors)) {
            echo json_encode([ 'success' => false, 'errors' => $errors ]);
            exit;
        }

        $is_resolved = ($status === 'Completed') ? 1 : 0;
        $stmt = $db->prepare("UPDATE maintenance SET status = :status, resolution_note = :note, is_resolved = :is_resolved WHERE maintenance_id = :id AND is_deleted = 0");
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':note', $resolution_note, PDO::PARAM_STR);
        $stmt->bindValue(':is_resolved', $is_resolved, PDO::PARAM_INT);
        $stmt->bindValue(':id', (int)$maintenance_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            echo json_encode([ 'success' => false, 'errors' => [ 'Maintenance request not found.' ] ]);
            exit;
        }
        echo json_encode([ 'success' => true ]);
        exit;
    }

    if (($user['role'] ?? '') === 'student') {
        $room_id = isset($_POST['room_id']) ? trim($_POST['room_id']) : '';
        if ($room_id === '' || !ctype_digit($room_id)) {
            echo json_encode([ 'success' => false, 'errors' => [ 'Room ID is required.' ] ]);
            exit;
        }

        $roomStmt = $db->prepare("SELECT 1 FROM rooms WHERE room_id = :room_id LIMIT 1");
        $roomStmt->bindValue(':room_id', (int)$room_id, PDO::PARAM_INT);
        $roomStmt->execute();
        if (!$roomStmt->fetchColumn()) {
            echo json_encode([ 'success' => false, 'errors' => [ 'Selected room does not exist.' ] ]);
            exit;
        }

        $stmt = $db->prepare("UPDATE maintenance SET room_id = :room_id WHERE maintenance_id = :id AND reported_by = :student_id AND is_deleted = 0");
        $stmt->bindValue(':room_id', (int)$room_id, PDO::PARAM_INT);
        $stmt->bindValue(':id', (int)$maintenance_id, PDO::PARAM_INT);
        $stmt->bindValue(':student_id', (int)$user['id'], PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            echo json_encode([ 'success' => false, 'errors' => [ 'You can only edit your own active requests.' ] ]);
            exit;
        }
        echo json_encode([ 'success' => true ]);
        exit;
    }

    echo json_encode([ 'success' => false, 'errors' => [ 'Unsupported role.' ] ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}

