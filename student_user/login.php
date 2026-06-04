<?php
// Consolidated student login — all student logins use student/login.php
require_once __DIR__ . '/../includes/session.php';

if (!empty($_SESSION['student_id'])) {
    header("Location: ../student_dashboard.php");
    exit();
}

header("Location: ../student/login.php");
exit();
