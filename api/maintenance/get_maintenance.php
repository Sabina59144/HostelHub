<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../config/auth.php');
require_once(__DIR__ . '/../config/schema.php');
$user = requireLoginJson();

try {
    // ensureMaintenanceArchiveSchema guarantees is_deleted, deleted_at, description columns exist
    ensureMaintenanceArchiveSchema($db);

    $view = isset($_GET['view']) ? strtolower(trim($_GET['view'])) : 'active';
    if (!in_array($view, ['active', 'archived'], true)) {
        $view = 'active';
    }
    $deletedFlag = $view === 'archived' ? 1 : 0;

    // assigned_to_id is the FK → users; reported_by is student_id or user_id depending on who reported.
    $sql = "SELECT
                m.maintenance_id,
                m.ticket_number,
                r.room_number,
                m.description,
                m.assigned_to_id,
                COALESCE(au.full_name, '') AS assigned_to_name,
                COALESCE(u.full_name, s.full_name, '') AS reported_by_name,
                m.date_reported,
                m.is_resolved,
                COALESCE(m.status, IF(m.is_resolved=1,'Completed','Pending')) AS status,
                COALESCE(m.resolution_note, '') AS resolution_note,
                m.is_deleted,
                m.deleted_at
            FROM maintenance m
            LEFT JOIN users u    ON m.reported_by = u.user_id
            LEFT JOIN students s ON m.reported_by = s.student_id
            LEFT JOIN rooms r    ON m.room_id     = r.room_id
            LEFT JOIN users au   ON m.assigned_to_id = au.user_id
            WHERE m.is_deleted = :is_deleted";

    if (($user['role'] ?? '') === 'student') {
        $sql .= " AND m.reported_by = :reported_by";
    }
    $sql .= " ORDER BY m.date_reported DESC";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':is_deleted', $deletedFlag, PDO::PARAM_INT);
    if (($user['role'] ?? '') === 'student') {
        $stmt->bindValue(':reported_by', (int)$user['id'], PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([ 'success' => true, 'view' => $view, 'data' => $rows ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}
