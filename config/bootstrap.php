<?php
/**
 * Bootstrap: include this at the top of every PHP page.
 * Loads config, database, helpers and starts a secure session.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/functions.php';

date_default_timezone_set(APP_TIMEZONE);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('UES_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
