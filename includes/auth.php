<?php
// /htdocs/daily_closing/includes/auth.php

// Start secure-ish session (works on HTTP too, better on HTTPS)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function login_user(array $user): void {
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['name']      = $user['name'];
    $_SESSION['role']      = strtolower($user['role']);
    $outletId = $user['outlet_id'] ?? null;
    $_SESSION['outlet_id'] = $outletId === null ? null : (int)$outletId;
    session_regenerate_id(true); // prevent session fixation
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /daily_closing/login.php');
        exit;
    }
}

function require_role($roles): void {
    require_login();
    $roles = (array)$roles;
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

function role_redirect(string $role): string {
    $map = [
        'manager' => '/daily_closing/views/manager/dashboard.php',
        'account' => '/daily_closing/views/account/queue.php',
        'finance' => '/daily_closing/views/finance/dashboard.php',
        'ceo'     => '/daily_closing/views/ceo/dashboard.php',
        'admin'   => '/daily_closing/views/admin/index.php',
    ];
    $role = strtolower($role);
    return $map[$role] ?? '/daily_closing/index.php';
}
