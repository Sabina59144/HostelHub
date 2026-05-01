<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/db.php');

try {
    $sql = "SELECT m.maintenance_id, m.ticket_number, r.room_number, s.name AS assigned_to_name, st.name AS reported_by_name, m.date_reported, m.is_resolved
            FROM maintenance m
            LEFT JOIN staffs s ON m.assigned_to = s.staff_id
            LEFT JOIN students st ON m.reported_by = st.student_id
            LEFT JOIN rooms r ON m.room_id = r.room_id
            ORDER BY m.date_reported DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([ 'success' => true, 'data' => $rows ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}
