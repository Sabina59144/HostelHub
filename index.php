<?php
// Root entry point — redirect to login
require_once __DIR__ . '/includes/session.php';

if (isLoggedIn()) {
    header("Location: room/index.php");
} else {
    header("Location: room/login.php");
}
exit();
