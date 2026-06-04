<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../config/auth.php');
require_once(__DIR__ . '/../config/schema.php');
$user = requireLoginJson();

$id = isset($_GET['id']) ? trim($_GET['id']) : (isset($_POST['maintenance_id']) ? trim($_POST['maintenance_id']) : '');
if ($id === '' || !ctype_digit($id)) {
    echo json_encode([ 'success' => false, 'errors' => [ 'Invalid maintenance id.' ] ]);
    exit;
}

try {
    ensureMaintenanceArchiveSchema($db);

    $sql = "SELECT m.*,
                   r.room_number,
                   m.assigned_to_id,
                   COALESCE(au.full_name, '') AS assigned_to_name,
                   COALESCE(u.full_name, s.full_name, '') AS reported_by_name
            FROM maintenance m
            LEFT JOIN users u    ON m.reported_by = u.user_id
            LEFT JOIN students s ON m.reported_by = s.student_id
            LEFT JOIN rooms r    ON m.room_id     = r.room_id
            LEFT JOIN users au   ON m.assigned_to_id = au.user_id
            WHERE m.maintenance_id = :id AND m.is_deleted = 0";
    if (($user['role'] ?? '') === 'student') {
        $sql .= " AND m.reported_by = :reported_by";
    }
    $sql .= " LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
    if (($user['role'] ?? '') === 'student') {
        $stmt->bindValue(':reported_by', (int)$user['id'], PDO::PARAM_INT);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode([ 'success' => false, 'errors' => [ 'Maintenance request not found.' ] ]);
        exit;
    }

    if (!array_key_exists('status', $row)) $row['status'] = ($row['is_resolved'] == 1 ? 'Completed' : 'Pending');
    if (!array_key_exists('resolution_note', $row)) $row['resolution_note'] = '';

    echo json_encode([ 'success' => true, 'data' => $row ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}

?>
