<?php
/**
 * Access control. Include at the top of every protected page:
 *
 *   require_once __DIR__ . '/../includes/auth_check.php';
 *   $user = require_role('admin');          // or ['admin','teacher']
 *
 * Checks run on the SERVER on every request: login, idle timeout, account still
 * active in the database, and role.
 */
require_once __DIR__ . '/../config/bootstrap.php';

const SESSION_TIMEOUT = 1800; // 30 minutes of inactivity

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function force_logout(string $message): void
{
    $_SESSION = [];
    session_destroy();
    session_start();                 // fresh session to carry the flash message
    session_regenerate_id(true);
    set_flash('warning', $message);
    redirect('auth/login.php');
}

/** Requires a valid, active session. Returns ['id','username','role','status','name']. */
function require_login(): array
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to continue.');
        redirect('auth/login.php');
    }
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        force_logout('Your session expired due to inactivity. Please log in again.');
    }

    $pdo  = Database::getConnection();
    $stmt = $pdo->prepare('SELECT id, username, role, status FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        force_logout('Your account is no longer active.');
    }
    if ($user['role'] !== ($_SESSION['role'] ?? '')) {
        force_logout('Your permissions changed. Please log in again.');
    }

    $_SESSION['last_activity'] = time();
    $user['name'] = $_SESSION['display_name'] ?? $user['username'];
    return $user;
}

/** Requires one of the given roles; otherwise responds 403. */
function require_role($roles): array
{
    $roles = (array)$roles;
    $user  = require_login();

    if (!in_array($user['role'], $roles, true)) {
        log_activity('access_denied', 'Attempted ' . ($_SERVER['REQUEST_URI'] ?? ''), (int)$user['id']);
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Access denied</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet"></head>
        <body class="d-flex align-items-center justify-content-center vh-100">
          <div class="text-center">
            <h1 class="display-1 fw-bold text-navy">403</h1>
            <p class="lead">You do not have permission to view this page.</p>
            <a class="btn btn-primary" href="<?= e(url(dashboard_path($user['role']))) ?>">Back to my dashboard</a>
          </div>
        </body></html>
        <?php
        exit;
    }
    return $user;
}
