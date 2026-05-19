<?php
/**
 * api/maintenance/list_staffs.php
 * ─────────────────────────────────────────────────────────────
 * REST endpoint — returns active users to populate the
 * "Assigned To" dropdown on the Add Maintenance Request form.
 *
 * Important: maintenance.assigned_to is VARCHAR(100), so the
 * dropdown value in plan.php must be full_name (text), not user_id.
 * That text is what gets stored directly in the maintenance row.
 *
 * Method: GET
 * Response: { success: true, data: [ {user_id, full_name, role}, ... ] }
 * ─────────────────────────────────────────────────────────────
 */
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
