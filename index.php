<?php
// index.php — entry point, redirects based on login status
require_once("includes/session.php");

if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit();
