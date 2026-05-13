<?php
// auth.php — include at top of every protected page

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
}

function currentRole(): string {
    return $_SESSION['role'] ?? '';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: login.php?reason=login_required");
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    if (currentRole() !== $role) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>Access Denied</title>
        <link rel="stylesheet" href="style.css"></head><body>
        <div class="invoice-wrapper"><div class="invoice-card">
        <div class="invoice-header" style="background:#dc3545;"><h2>Access Denied</h2></div>
        <div class="invoice-body" style="text-align:center;">
            <p>You do not have permission to access this page.</p>
            <p>This action requires <strong>' . htmlspecialchars($role) . '</strong> privileges.</p>
            <a href="index.php" style="color:#2e59d9;">&#8592; Back to Dashboard</a>
        </div></div></div></body></html>';
        exit;
    }
}
?>
