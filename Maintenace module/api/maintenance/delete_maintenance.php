<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../config/db.php');

$maintenance_id = isset($_POST['maintenance_id']) ? trim($_POST['maintenance_id']) : '';
if ($maintenance_id === '' || !ctype_digit($maintenance_id)) {
    echo json_encode([ 'success' => false, 'errors' => [ 'Invalid maintenance id.' ] ]);
    exit;
}

try {
    $stmt = $db->prepare("DELETE FROM maintenance WHERE maintenance_id = :id");
    $stmt->bindValue(':id', (int)$maintenance_id, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode([ 'success' => true ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}

?>