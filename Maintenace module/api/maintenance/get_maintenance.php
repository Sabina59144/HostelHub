<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/db.php');

try {
    // Check whether new columns exist; some installations may still be on older schema
    $extraCols = [];
    $check = $db->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'maintenance' AND COLUMN_NAME IN ('status','resolution_note')");
    $check->bindValue(':schema', isset($dbname) ? $dbname : '', PDO::PARAM_STR);
    $check->execute();
    $found = $check->fetchAll(PDO::FETCH_COLUMN);
    $selectExtra = '';
    if (in_array('status', $found, true)) $selectExtra .= ', m.status';
    if (in_array('resolution_note', $found, true)) $selectExtra .= ', m.resolution_note';

    $sql = "SELECT m.maintenance_id, m.ticket_number, r.room_number, s.name AS assigned_to_name, st.name AS reported_by_name, m.date_reported, m.is_resolved" . $selectExtra . "
            FROM maintenance m
            LEFT JOIN staffs s ON m.assigned_to = s.staff_id
            LEFT JOIN students st ON m.reported_by = st.student_id
            LEFT JOIN rooms r ON m.room_id = r.room_id
            ORDER BY m.date_reported DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize results: ensure keys exist for compatibility with frontend
    foreach ($rows as &$row) {
        if (!array_key_exists('status', $row)) {
            $row['status'] = ($row['is_resolved'] == 1) ? 'Completed' : 'Pending';
        }
        if (!array_key_exists('resolution_note', $row)) {
            $row['resolution_note'] = '';
        }
    }

    echo json_encode([ 'success' => true, 'data' => $rows ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}
