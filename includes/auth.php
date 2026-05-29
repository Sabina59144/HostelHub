<?php
/**
 * HostelHub Authentication Helpers
 * Location: includes/auth.php
 */

// Start the session only if one isn't already running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

<<<<<<< HEAD
/* PASSWORD HELPERS */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/* CSRF HELPERS */
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/* AUTH HELPERS */
function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit();
    }
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
=======
// Returns true if the user is logged in (checks session for staff_id)
function isLoggedIn(): bool {
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
>>>>>>> aa5c0b6e2539ab2140fb1f8574a56a3ff3aa921d
}

// Returns the current user's role (e.g. "admin" or "staff"), or "" if not logged in
function currentRole(): string {
    return $_SESSION['role'] ?? 'guest';
}

<<<<<<< HEAD
function isAdmin(): bool {
    return currentRole() === 'admin';
}

function logout(): void {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

/* ACTIVITY LOGGING */
function logUserActivity(int $userId, string $action, string $details = ''): void {
    global $db;

    if (!isset($db)) return;

    try {
        $stmt = $db->prepare("
            INSERT INTO user_activity_logs
            (user_id, action, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log user activity: " . $e->getMessage());
    }
}

/* LOGIN RATE LIMITING */
function checkLoginRateLimit(
    string $identifier,
    int $maxAttempts = 5,
    int $timewindowSeconds = 300
): bool {
    $cacheKey = 'login_attempt_' . md5($identifier);
    $attempts = $_SESSION[$cacheKey] ?? [];

    $now = time();
    $attempts = array_filter($attempts, function ($time) use ($now, $timewindowSeconds) {
        return ($now - $time) < $timewindowSeconds;
    });

    if (count($attempts) >= $maxAttempts) {
        return false;
=======
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
>>>>>>> aa5c0b6e2539ab2140fb1f8574a56a3ff3aa921d
    }

    $attempts[] = $now;
    $_SESSION[$cacheKey] = $attempts;

    return true;
}
<<<<<<< HEAD

function clearLoginRateLimit(string $identifier): void {
    $cacheKey = 'login_attempt_' . md5($identifier);
    unset($_SESSION[$cacheKey]);
}
=======
>>>>>>> aa5c0b6e2539ab2140fb1f8574a56a3ff3aa921d
?>