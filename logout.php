<?php
/**
 * logout.php
 * ─────────────────────────────────────────────────────────────
 * Destroys the current user session and redirects to login.
 * session_unset() clears all session variables first, then
 * session_destroy() removes the session from the server.
 * ─────────────────────────────────────────────────────────────
 */
require_once 'includes/session.php';
session_unset();    // Clear all $_SESSION variables
session_destroy();  // Remove the session from the server
header("Location: login.php");
exit();
