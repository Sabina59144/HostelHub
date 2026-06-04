<?php
/**
 * HostelHub Authentication Helpers
 * Location: includes/auth.php
 */

// Start the session only if one isn't already running
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

/* AUTH HELPERS — guarded to avoid redeclaration if session.php already loaded */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('currentRole')) {
    function currentRole(): string {
        return $_SESSION['role'] ?? 'guest';
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin(): void {
        if (!isLoggedIn()) {
            header('Location: ../login.php');
            exit();
        }
    }
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function isAdmin(): bool {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

if (!function_exists('logout')) {
    function logout(): void {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
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
    }

    $attempts[] = $now;
    $_SESSION[$cacheKey] = $attempts;
    return true;
}

function clearLoginRateLimit(string $identifier): void {
    $cacheKey = 'login_attempt_' . md5($identifier);
    unset($_SESSION[$cacheKey]);
}
?>
