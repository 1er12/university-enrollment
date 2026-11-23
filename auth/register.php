<?php
/** Student self-registration. Creates a users row + a students row in one transaction. */
require_once __DIR__ . '/../config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect(dashboard_path($_SESSION['role'] ?? ''));
}

$pdo = Database::getConnection();
$programs = $pdo->query("SELECT id, code, name FROM programs WHERE status = 'active' ORDER BY name")->fetchAll();

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'username' => '', 'email' => '', 'program_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $k => $_) {
        $old[$k] = clean($_POST[$k] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if ($old['first_name'] === '' || mb_strlen($old['first_name']) > 60) $errors[] = 'First name is required (max 60 characters).';
    if ($old['last_name']  === '' || mb_strlen($old['last_name'])  > 60) $errors[] = 'Last name is required (max 60 characters).';
    if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $old['username'])) {
        $errors[] = 'Username must be 4-30 characters: letters, numbers, dot or underscore.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!in_array((int)$old['program_id'], array_map('intval', array_column($programs, 'id')), true)) {
        $errors[] = 'Please select a program.';
    }
    if ($msg = validate_password($password)) $errors[] = $msg;
    if ($password !== $confirm)               $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $dup = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
        $dup->execute([$old['username'], $old['email']]);
        foreach ($dup->fetchAll() as $row) {
            if (strcasecmp($row['username'], $old['username']) === 0) $errors[] = 'That username is already taken.';
            if (strcasecmp($row['email'], $old['email']) === 0)       $errors[] = 'That email is already registered.';
        }
        $errors = array_unique($errors);
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'student')")
                ->execute([$old['username'], $old['email'], password_hash($password, PASSWORD_DEFAULT)]);
            $userId    = (int)$pdo->lastInsertId();
            $studentNo = generate_student_no($pdo);
            $pdo->prepare(
                'INSERT INTO students (user_id, student_no, first_name, last_name, program_id, year_level)
                 VALUES (?, ?, ?, ?, ?, 1)'
            )->execute([$userId, $studentNo, $old['first_name'], $old['last_name'], (int)$old['program_id']]);
            $pdo->commit();

            log_activity('register', "New student registered ($studentNo)", $userId);
            set_flash('success', "Account created! Your student number is $studentNo. You can now log in.");
            redirect('auth/login.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Registration failed: ' . $e->getMessage());
            $errors[] = ($e->getCode() === '23000')
                ? 'That username or email is already registered.'
                : 'Could not create your account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register · <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="container py-4" style="max-width:640px">
  <div class="card shadow-sm">
    <div class="card-body p-4 p-md-5">
      <h4 class="fw-bold text-navy mb-1">Create a student account</h4>
      <p class="text-muted mb-4">Your student number is generated automatically.</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0 ps-3">
          <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="first_name">First name</label>
            <input class="form-control" id="first_name" name="first_name" value="<?= e($old['first_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="last_name">Last name</label>
            <input class="form-control" id="last_name" name="last_name" value="<?= e($old['last_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="username">Username</label>
            <input class="form-control" id="username" name="username" value="<?= e($old['username']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label" for="program_id">Program</label>
            <select class="form-select" id="program_id" name="program_id" required>
              <option value="">Select a program…</option>
              <?php foreach ($programs as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (string)$p['id'] === $old['program_id'] ? 'selected' : '' ?>>
                  <?= e($p['code'] . ' — ' . $p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" type="password" id="password" name="password" required>
            <div class="form-text">At least 8 characters, with a letter and a number.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="confirm_password">Confirm password</label>
            <input class="form-control" type="password" id="confirm_password" name="confirm_password" required>
          </div>
        </div>
        <button class="btn btn-primary w-100 py-2 mt-4" type="submit">Create account</button>
      </form>
      <p class="text-center mt-4 mb-0">Already registered? <a href="<?= e(url('auth/login.php')) ?>">Log in</a></p>
    </div>
  </div>
</div>
</body>
</html>
