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
        $status          = isset($_POST['status'])          ? trim($_POST['status'])          : '';
        $resolution_note = isset($_POST['resolution_note']) ? trim($_POST['resolution_note']) : '';
        // assigned_to is always sent: empty string = clear assignment, digit string = assign to that user_id
        $assigned_to     = isset($_POST['assigned_to'])     ? trim($_POST['assigned_to'])     : '';

        $allowed = ['Pending', 'Inprogress', 'Completed'];
        if ($status === '' || !in_array($status, $allowed, true)) $errors[] = 'Status is invalid.';
        if (strlen($resolution_note) > 2000) $errors[] = 'Resolution Note must be 2000 characters or less.';
        if ($assigned_to !== '' && !ctype_digit($assigned_to)) $errors[] = 'Invalid staff selection.';
        if (!empty($errors)) {
            echo json_encode([ 'success' => false, 'errors' => $errors ]);
            exit;
        }

        // Validate staff exists if provided
        $assigned_to_id = null;
        if ($assigned_to !== '') {
            $sStmt = $db->prepare("SELECT 1 FROM users WHERE user_id = :uid AND is_active = 1 LIMIT 1");
            $sStmt->bindValue(':uid', (int)$assigned_to, PDO::PARAM_INT);
            $sStmt->execute();
            if (!$sStmt->fetchColumn()) {
                echo json_encode([ 'success' => false, 'errors' => [ 'Selected staff does not exist or is inactive.' ] ]);
                exit;
            }
            $assigned_to_id = (int)$assigned_to;
        }

        $is_resolved = ($status === 'Completed') ? 1 : 0;
        $stmt = $db->prepare("UPDATE maintenance SET status = :status, resolution_note = :note, is_resolved = :is_resolved, assigned_to_id = :assigned_id, assigned_to = NULL WHERE maintenance_id = :id AND is_deleted = 0");
        $stmt->bindValue(':status',      $status,          PDO::PARAM_STR);
        $stmt->bindValue(':note',        $resolution_note, PDO::PARAM_STR);
        $stmt->bindValue(':is_resolved', $is_resolved,     PDO::PARAM_INT);
        $stmt->bindValue(':assigned_id', $assigned_to_id,  $assigned_to_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':id',          (int)$maintenance_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() === 0) {
            echo json_encode([ 'success' => false, 'errors' => [ 'Maintenance request not found.' ] ]);
            exit;
        }
        echo json_encode([ 'success' => true ]);
        exit;
    }

    if (($user['role'] ?? '') === 'student') {
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        if ($description === '') {
            echo json_encode([ 'success' => false, 'errors' => [ 'Description is required.' ] ]);
            exit;
        }
        if (strlen($description) > 2000) {
            echo json_encode([ 'success' => false, 'errors' => [ 'Description must be 2000 characters or less.' ] ]);
            exit;
        }

        $stmt = $db->prepare("UPDATE maintenance SET description = :description WHERE maintenance_id = :id AND reported_by = :student_id AND is_deleted = 0");
        $stmt->bindValue(':description', $description,       PDO::PARAM_STR);
        $stmt->bindValue(':id',          (int)$maintenance_id, PDO::PARAM_INT);
        $stmt->bindValue(':student_id',  (int)$user['id'],   PDO::PARAM_INT);
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

