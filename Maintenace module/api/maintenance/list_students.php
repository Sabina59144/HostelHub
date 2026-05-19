<?php
// Returns users for the "Reported By" dropdown.
// reported_by in maintenance table is INT FK → users(user_id).
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/db.php');

try {
    $stmt = $db->prepare("SELECT user_id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([ 'success' => true, 'data' => $rows ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}
