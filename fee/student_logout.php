<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only clear student session keys — don't destroy in case staff is also logged in
unset($_SESSION['student_id']);
unset($_SESSION['student_name']);
unset($_SESSION['student_number']);

session_destroy();

header("Location: student_login.php");
exit;
?>
