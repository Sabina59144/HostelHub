<?php

function authStartSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function authCurrentUser(): ?array
{
    authStartSession();
    if (!isset($_SESSION['auth_user']) || !is_array($_SESSION['auth_user'])) {
        return null;
    }
    return $_SESSION['auth_user'];
}

function authLoginUser(string $role, int $id, string $name, string $identifier): void
{
    authStartSession();
    session_regenerate_id(true);
    $_SESSION['auth_user'] = [
        'role' => $role,
        'id' => $id,
        'name' => $name,
        'identifier' => $identifier
    ];
}

function authLogoutUser(): void
{
    authStartSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function requireLoginPage(string $redirectUrl): void
{
    if (authCurrentUser() !== null) {
        return;
    }
    header('Location: ' . $redirectUrl);
    exit;
}

function requireLoginJson(): array
{
    $user = authCurrentUser();
    if ($user !== null) {
        return $user;
    }
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'errors' => ['Authentication required.']]);
    exit;
}

