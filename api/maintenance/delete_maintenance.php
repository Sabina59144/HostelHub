<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../config/auth.php');
require_once(__DIR__ . '/../config/schema.php');
$user = requireLoginJson();
if (($user['role'] ?? '') !== 'admin') {
    echo json_encode([ 'success' => false, 'errors' => [ 'Only admins can archive requests.' ] ]);
    exit;
}

$maintenance_id = isset($_POST['maintenance_id']) ? trim($_POST['maintenance_id']) : '';
if ($maintenance_id === '' || !ctype_digit($maintenance_id)) {
    echo json_encode([ 'success' => false, 'errors' => [ 'Invalid maintenance id.' ] ]);
    exit;
}

try {
    ensureMaintenanceArchiveSchema($db);
    $stmt = $db->prepare("UPDATE maintenance SET is_deleted = 1, deleted_at = NOW() WHERE maintenance_id = :id AND is_deleted = 0");
    $stmt->bindValue(':id', (int)$maintenance_id, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() === 0) {
        echo json_encode([ 'success' => false, 'errors' => [ 'Maintenance request not found or already archived.' ] ]);
        exit;
    }
    echo json_encode([ 'success' => true ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}

?>
