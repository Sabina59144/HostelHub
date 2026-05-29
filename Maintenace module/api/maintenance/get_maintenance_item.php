<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../config/db.php');

$id = isset($_GET['id']) ? trim($_GET['id']) : (isset($_POST['maintenance_id']) ? trim($_POST['maintenance_id']) : '');
if ($id === '' || !ctype_digit($id)) {
    echo json_encode([ 'success' => false, 'errors' => [ 'Invalid maintenance id.' ] ]);
    exit;
}

try {
    // Try to select including newer columns; if columns missing, fall back
    $sql = "SELECT m.*, r.room_number,
                   m.assigned_to       AS assigned_to_name,
                   u.full_name         AS reported_by_name
            FROM maintenance m
            LEFT JOIN rooms r ON m.room_id    = r.room_id
            LEFT JOIN users u ON m.reported_by = u.user_id
            WHERE m.maintenance_id = :id LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode([ 'success' => false, 'errors' => [ 'Maintenance request not found.' ] ]);
        exit;
    }

    // Ensure keys exist for compatibility
    if (!array_key_exists('status', $row)) $row['status'] = ($row['is_resolved'] == 1 ? 'Completed' : 'Pending');
    if (!array_key_exists('resolution_note', $row)) $row['resolution_note'] = '';

    echo json_encode([ 'success' => true, 'data' => $row ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}

?>