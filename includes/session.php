<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Calculate relative path back to project root using real file paths
function _rootPath($file) {
    // session.php lives in includes/, so project root is one level up
    $projectRoot = dirname(dirname(__FILE__));
    $scriptDir   = dirname($_SERVER['SCRIPT_FILENAME']);
    $inSubdir    = (realpath($scriptDir) !== realpath($projectRoot));
    return ($inSubdir ? '../' : '') . $file;
}

function _loginPath()     { return _rootPath('login.php'); }
function _dashboardPath() { return _rootPath('dashboard.php'); }

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . _loginPath());
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header("Location: " . _dashboardPath());
        exit();
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    return [
        'user_id'   => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
    ];
}
