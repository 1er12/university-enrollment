<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    redirect('auth/login.php');
}
// CSRF-protected logout (prevents forced logout from other sites)
if (!verify_csrf($_GET['token'] ?? null)) {
    redirect(dashboard_path($_SESSION['role'] ?? ''));
}

log_activity('logout', 'User logged out');

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
}
session_destroy();

session_start();
session_regenerate_id(true);
set_flash('success', 'You have been logged out.');
redirect('auth/login.php');
