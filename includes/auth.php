<?php
// auth.php — include at top of every protected page

// Start the session only if one isn't already running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Returns true if the user is logged in (checks session for staff_id)
function isLoggedIn(): bool {
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
}

// Returns the current user's role (e.g. "admin" or "staff"), or "" if not logged in
function currentRole(): string {
    return $_SESSION['role'] ?? '';
}

// Redirects to login page if the user is not logged in
// Call this at the top of any page that requires authentication
function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: login.php?reason=login_required");
        exit;  // Stop executing the rest of the page
    }
}

// Ensures the logged-in user has a specific role (e.g. "admin")
// If they don't, shows a 403 Access Denied page and stops execution
function requireRole(string $role): void {
    requireLogin();  // First make sure they're logged in at all
    if (currentRole() !== $role) {
        http_response_code(403);  // Send HTTP 403 Forbidden status
        // Inline HTML for the access denied page — no separate template needed
        echo '<!DOCTYPE html><html><head><title>Access Denied</title>
        <link rel="stylesheet" href="style.css"></head><body>
        <div class="invoice-wrapper"><div class="invoice-card">
        <div class="invoice-header" style="background:#dc3545;"><h2>Access Denied</h2></div>
        <div class="invoice-body" style="text-align:center;">
            <p>You do not have permission to access this page.</p>
            <p>This action requires <strong>' . htmlspecialchars($role) . '</strong> privileges.</p>
            <a href="index.php" style="color:#2e59d9;">&#8592; Back to Dashboard</a>
        </div></div></div></body></html>';
        exit;  // Stop executing the rest of the page
    }
}
?>