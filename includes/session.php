<?php

// Start the session only if one isn't already running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirects to login page if the user is not logged in
// Call this at the top of any page that requires authentication
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();  // Stop executing the rest of the page
    }
}

// Ensures the logged-in user has a specific role (e.g. "admin")
// If they don't match, redirects to the dashboard instead of showing an error
function requireRole($role) {
    requireLogin();  // First make sure they're logged in at all
    if ($_SESSION['role'] !== $role) {
        header("Location: ../dashboard.php");
        exit();  // Stop executing the rest of the page
    }
}

// Returns true if a user is currently logged in, false otherwise
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Returns the current user's data as an array, or null if not logged in
// Used wherever you need to display or check the logged-in user's details
function currentUser() {
    if (!isLoggedIn()) return null;

    // Build a clean array from session data — avoids passing the raw $_SESSION around
    return [
        'user_id'   => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
    ];
}