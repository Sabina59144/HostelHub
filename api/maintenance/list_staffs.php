<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../config/auth.php');
requireLoginJson();

try {
    $stmt = $db->prepare("SELECT user_id AS staff_id, full_name AS name, role, NULL AS email FROM users WHERE is_active = 1 ORDER BY full_name");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([ 'success' => true, 'data' => $rows ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}
