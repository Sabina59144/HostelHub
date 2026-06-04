<?php
header('Content-Type: application/json; charset=UTF-8');
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../config/auth.php');
require_once(__DIR__ . '/../config/schema.php');

$role = isset($_POST['role']) ? trim($_POST['role']) : '';
$identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

$errors = [];
if (!in_array($role, ['student', 'admin'], true)) $errors[] = 'Role is required.';
if ($identifier === '') $errors[] = 'Identifier is required.';
if ($password === '') $errors[] = 'Password is required.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    ensureAuthSchema($db);

    if ($role === 'student') {
        $stmt = $db->prepare("SELECT student_id, name, email, password FROM students WHERE email = :email LIMIT 1");
        $stmt->bindValue(':email', $identifier, PDO::PARAM_STR);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$student || !password_verify($password, (string)$student['password'])) {
            echo json_encode(['success' => false, 'errors' => ['Invalid student credentials.']]);
            exit;
        }

        authLoginUser('student', (int)$student['student_id'], (string)$student['name'], (string)$student['email']);
    } else {
        $stmt = $db->prepare("SELECT admin_id, username, name, password FROM admins WHERE username = :username LIMIT 1");
        $stmt->bindValue(':username', $identifier, PDO::PARAM_STR);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin || !password_verify($password, (string)$admin['password'])) {
            echo json_encode(['success' => false, 'errors' => ['Invalid admin credentials.']]);
            exit;
        }

        authLoginUser('admin', (int)$admin['admin_id'], (string)$admin['name'], (string)$admin['username']);
    }

    echo json_encode(['success' => true, 'role' => $role]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}

