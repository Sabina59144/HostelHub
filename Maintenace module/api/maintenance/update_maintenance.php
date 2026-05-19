<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../config/db.php');

$errors = [];
$maintenance_id = isset($_POST['maintenance_id']) ? trim($_POST['maintenance_id']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$resolution_note = isset($_POST['resolution_note']) ? trim($_POST['resolution_note']) : '';

if ($maintenance_id === '' || !ctype_digit($maintenance_id)) $errors[] = 'Invalid maintenance id.';
$allowed = ['Pending', 'Inprogress', 'Completed'];
if ($status === '' || !in_array($status, $allowed, true)) $errors[] = 'Status is invalid.';
if (strlen($resolution_note) > 2000) $errors[] = 'Resolution Note must be 2000 characters or less.';

if (!empty($errors)) {
    echo json_encode([ 'success' => false, 'errors' => $errors ]);
    exit;
}

try {
    // Attempt to ensure columns exist (MySQL 8+ supports IF NOT EXISTS)
    try {
        $db->exec("ALTER TABLE maintenance ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'Pending';");
        $db->exec("ALTER TABLE maintenance ADD COLUMN IF NOT EXISTS resolution_note TEXT DEFAULT '';");
    } catch (Exception $inner) {
        // ignore alter failures (older MySQL versions) and proceed
    }

    $is_resolved = ($status === 'Completed') ? 1 : 0;

    $stmt = $db->prepare("UPDATE maintenance SET status = :status, resolution_note = :note, is_resolved = :is_resolved WHERE maintenance_id = :id");
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':note', $resolution_note, PDO::PARAM_STR);
    $stmt->bindValue(':is_resolved', $is_resolved, PDO::PARAM_INT);
    $stmt->bindValue(':id', (int)$maintenance_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([ 'success' => true ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}

?>