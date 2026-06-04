<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../config/auth.php');
$user = requireLoginJson();

try {
    if (($user['role'] ?? '') === 'student') {
        // Students can only submit maintenance for their own room
        $stmt = $db->prepare(
            "SELECT r.room_id, r.room_number, r.capacity
             FROM students s
             JOIN rooms r ON s.room_id = r.room_id
             WHERE s.student_id = :id"
        );
        $stmt->bindValue(':id', (int)$user['id'], PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT room_id, room_number, capacity FROM rooms ORDER BY room_number");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode([ 'success' => true, 'data' => $rows ]);
} catch (PDOException $e) {
    echo json_encode([ 'success' => false, 'errors' => [ $e->getMessage() ] ]);
}
