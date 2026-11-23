<?php
require_once __DIR__ . '/../config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect(dashboard_path($_SESSION['role'] ?? ''));
}

const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_MINUTES     = 15;

function too_many_attempts(PDO $pdo, string $ip): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM activity_logs
         WHERE action = 'login_failed' AND ip_address = ?
           AND created_at > (NOW() - INTERVAL " . LOCKOUT_MINUTES . " MINUTE)"
    );
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn() >= MAX_FAILED_ATTEMPTS;
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = clean($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $pdo        = Database::getConnection();

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Please enter your username or email and your password.';
    } elseif (too_many_attempts($pdo, client_ip())) {
        $error = 'Too many failed attempts. Please try again in ' . LOCKOUT_MINUTES . ' minutes.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $found = $stmt->fetch();

        // Always run password_verify so response time doesn't reveal valid usernames
        $hash  = $found['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash) && $found;

        if (!$valid) {
            log_activity('login_failed', 'Failed login for "' . $identifier . '"', $found['id'] ?? null);
            $error = 'Invalid username/email or password.';
        } elseif ($found['status'] !== 'active') {
            log_activity('login_blocked', 'Inactive account tried to log in', (int)$found['id']);
            $error = 'Your account is deactivated. Please contact the administrator.';
        } else {
            // ---- success ----
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $_SESSION['user_id']       = (int)$found['id'];
            $_SESSION['role']          = $found['role'];
            $_SESSION['display_name']  = load_display_name($pdo, $found);
            $_SESSION['last_activity'] = time();

            if (password_needs_rehash($found['password_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $found['id']]);
            }
            $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$found['id']]);
            log_activity('login', 'User logged in', (int)$found['id']);

            redirect(dashboard_path($found['role']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login · <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="container min-vh-100 d-flex align-items-center justify-content-center py-4">
  <div class="card shadow-sm auth-card overflow-hidden w-100" style="max-width:860px">
    <div class="row g-0">
      <div class="col-md-5 bg-navy text-white p-4 d-flex flex-column justify-content-center">
        <i class="bi bi-mortarboard-fill fs-1 mb-3 text-info"></i>
        <h4 class="fw-bold"><?= e(APP_NAME) ?></h4>
        <p class="mb-0 opacity-75">Sign in to manage enrollment, subjects and classes.</p>
      </div>
      <div class="col-md-7 p-4 p-md-5 bg-white">
        <h4 class="fw-bold text-navy mb-1">Welcome back</h4>
        <p class="text-muted mb-4">Log in to your account</p>

        <?= display_flash() ?>
        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label" for="identifier">Username or email</label>
            <input class="form-control" id="identifier" name="identifier" value="<?= e($identifier) ?>" autofocus required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <div class="input-group">
              <input class="form-control" type="password" id="password" name="password" required>
              <button class="btn btn-outline-secondary" type="button" id="togglePw" aria-label="Show password">
                <i class="bi bi-eye"></i></button>
            </div>
          </div>
          <button class="btn btn-primary w-100 py-2" type="submit">Log in</button>
        </form>

        <p class="text-center mt-4 mb-0">New student?
          <a href="<?= e(url('auth/register.php')) ?>">Create an account</a></p>
        <?php if (APP_DEBUG): ?>
          <p class="text-center text-muted small mt-3 mb-0">Demo: admin / teacher1 / student1 &middot; Admin@12345</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePw').addEventListener('click', function () {
  const f = document.getElementById('password');
  const show = f.type === 'password';
  f.type = show ? 'text' : 'password';
  this.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
});
</script>
</body>
</html>
