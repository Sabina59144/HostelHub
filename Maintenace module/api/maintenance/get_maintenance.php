<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/db.php');

try {
    // assigned_to is VARCHAR(100) — stored as text, no JOIN needed.
    // reported_by is INT FK → users(user_id), JOIN users for the name.
    // status / resolution_note may not exist yet — detect via information_schema.
    $extraCols = [];
    $check = $db->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'maintenance'
          AND COLUMN_NAME IN ('status', 'resolution_note')
    ");
    $check->execute();
    $found = $check->fetchAll(PDO::FETCH_COLUMN);
    $selectExtra = '';
    if (in_array('status',          $found, true)) $selectExtra .= ', m.status';
    if (in_array('resolution_note', $found, true)) $selectExtra .= ', m.resolution_note';

    $sql = "
        SELECT m.maintenance_id,
               m.ticket_number,
               r.room_number,
               m.assigned_to    AS assigned_to_name,
               u.full_name      AS reported_by_name,
               m.date_reported,
               m.is_resolved
               $selectExtra
        FROM maintenance m
        LEFT JOIN rooms r ON m.room_id    = r.room_id
        LEFT JOIN users u ON m.reported_by = u.user_id
        ORDER BY m.date_reported DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
