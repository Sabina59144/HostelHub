<?php
/**
 * api/maintenance/list_rooms.php
 * ─────────────────────────────────────────────────────────────
 * REST endpoint — returns all rooms for the "Room" dropdown
 * on the Add Maintenance Request form (plan.php).
 *
 * Method: GET
 * Response: { success: true, data: [ {room_id, room_number, capacity}, ... ] }
 * ─────────────────────────────────────────────────────────────
 */
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/db.php');

try {
    $stmt = $db->prepare("SELECT room_id, room_number, capacity FROM rooms ORDER BY room_number");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([ 'success' => true, 'data' => $rows ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}
