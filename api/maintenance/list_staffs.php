<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/db.php');

try {
    $stmt = $db->prepare("SELECT staff_id, name, role, email FROM staffs ORDER BY name");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([ 'success' => true, 'data' => $rows ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}
