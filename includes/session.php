<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * SESSION & AUTHENTICATION HANDLER
 * 
 * Location: includes/session.php
 * Purpose: Manage user sessions and authentication across all modules
 * ═══════════════════════════════════════════════════════════════════════════
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool True if user session exists
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user's role
 * @return string User role (admin, staff, student)
 */
function currentRole(): string {
    return $_SESSION['role'] ?? 'guest';
}

/**
 * Get current logged-in user's data
 * @return array|null User data array or null if not logged in
 */
function currentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'user_id'   => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
    ];
}

/**
 * Calculate relative path from current location to project root
 * Handles both root level and subdirectory requests
 * @param string $file Target file path relative to root
 * @return string Relative path with proper navigation
 */
function _rootPath(string $file = ''): string {
    // session.php is in includes/, so project root is one level up
    $projectRoot = dirname(dirname(__FILE__));
    $scriptDir   = dirname($_SERVER['SCRIPT_FILENAME']);
    $inSubdir    = (realpath($scriptDir) !== realpath($projectRoot));
    
    $prefix = $inSubdir ? '../' : '';
    return $prefix . $file;
}

/**
 * Require user to be logged in
 * Redirects to login page if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: " . _rootPath('login.php'));
        exit;
    }
}

/**
 * Require specific role (admin, staff, student)
 * @param string $requiredRole Role required to access page
 * @throws Error if user lacks permissions
 */
function requireRole(string $requiredRole): void {
    requireLogin();
    
    $userRole = currentRole();
    if ($userRole !== $requiredRole) {
        http_response_code(403);
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied</title>
            <style>
                body { 
                    margin: 0; 
                    padding: 20px; 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
                    background: #f5f5f5;
                }
                .denied-container {
                    max-width: 500px;
                    margin: 100px auto;
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    text-align: center;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                h1 { color: #dc3545; margin-top: 0; }
                p { color: #666; line-height: 1.6; }
                a { color: #007bff; text-decoration: none; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="denied-container">
                <h1>🔒 Access Denied</h1>
                <p>You do not have permission to access this page.</p>
                <p><strong>Required role:</strong> ' . htmlspecialchars($requiredRole) . '</p>
                <p><strong>Your role:</strong> ' . htmlspecialchars($userRole) . '</p>
                <p><a href="' . _rootPath('dashboard.php') . '">← Back to Dashboard</a></p>
            </div>
        </body>
        </html>';
        
        exit;
    }
}

/**
 * Require multiple roles (at least one must match)
 * @param array $allowedRoles Array of roles that are permitted
 */
function requireAnyRole(array $allowedRoles): void {
    requireLogin();
    
    $userRole = currentRole();
    if (!in_array($userRole, $allowedRoles, true)) {
        http_response_code(403);
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Access Denied</title>
            <style>
                body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background: #f5f5f5; }
                .denied-container { max-width: 500px; margin: 100px auto; background: white; padding: 40px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #dc3545; }
                a { color: #007bff; text-decoration: none; }
            </style>
        </head>
        <body>
            <div class="denied-container">
                <h1>Access Denied</h1>
                <p>Your account does not have access to this resource.</p>
                <p><a href="' . _rootPath('dashboard.php') . '">← Back to Dashboard</a></p>
            </div>
        </body>
        </html>';
        
        exit;
    }
}

/**
 * Logout current user
 * Destroys session and redirects to login
 */
function logout(): void {
    session_destroy();
    header("Location: " . _rootPath('login.php'));
    exit;
}
?>
