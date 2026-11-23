<?php
/** Shared profile page for students and teachers. Expects $user and $role ('student'|'teacher'). */
require_once __DIR__ . '/enrollment.php';
require_once __DIR__ . '/upload.php';

$pdo   = Database::getConnection();
$table = $role === 'student' ? 'students' : 'teachers';
$me    = $role === 'student' ? student_by_user($pdo, (int)$user['id']) : teacher_by_user($pdo, (int)$user['id']);
if (!$me) { set_flash('danger', 'Profile not found.'); redirect(dashboard_path($role)); }
$self = $role . '/profile.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { set_flash('danger', 'Invalid request. Please try again.'); redirect($self); }
    $act = $_POST['action'] ?? '';

    if ($act === 'contact') {
        $phone = clean($_POST['phone'] ?? ''); $address = clean($_POST['address'] ?? '');
        if (!preg_match('/^[0-9+()\-\s]{0,30}$/', $phone)) set_flash('danger', 'Phone may only contain digits, spaces and + - ( ).');
        elseif (mb_strlen($address) > 255)                set_flash('danger', 'Address is too long (max 255).');
        else {
            $pdo->prepare("UPDATE $table SET phone = ?, address = ? WHERE id = ?")->execute([$phone ?: null, $address ?: null, $me['id']]);
            log_activity('profile_updated', 'Updated contact information');
            set_flash('success', 'Contact information updated.');
        }
    } elseif ($act === 'picture') {
        [$ok, $res] = save_upload($_FILES['picture'] ?? [], ALLOWED_IMAGE_TYPES, 'profiles', $role . $me['id']);
        if ($ok) {
            delete_upload('profiles', $me['profile_picture']);
            $pdo->prepare("UPDATE $table SET profile_picture = ? WHERE id = ?")->execute([$res, $me['id']]);
            log_activity('profile_picture', 'Changed profile picture');
            set_flash('success', 'Profile picture updated.');
        } else set_flash('danger', $res);
    } elseif ($act === 'password') {
        $cur = $_POST['current_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $conf = $_POST['confirm_password'] ?? '';
        $hash = scalar($pdo, 'SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        if (!password_verify($cur, $hash))      set_flash('danger', 'Your current password is incorrect.');
        elseif ($m = validate_password($new))   set_flash('danger', $m);
        elseif ($new !== $conf)                 set_flash('danger', 'New passwords do not match.');
        else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            log_activity('password_changed', 'Changed password');
            set_flash('success', 'Password changed.');
        }
    }
    redirect($self);
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/header.php';
$info = [
    'Full name' => trim($me['first_name'] . ' ' . $me['middle_name'] . ' ' . $me['last_name']),
    ($role === 'student' ? 'Student number' : 'Employee number') => $role === 'student' ? $me['student_no'] : $me['employee_no'],
    ($role === 'student' ? 'Program' : 'Department') => $role === 'student' ? $me['program_name'] . ' · Year ' . $me['year_level'] : ($me['department_name'] ?? '—'),
    'Email' => $me['email'], 'Username' => $me['username'],
    'Gender' => ucfirst((string)$me['gender']), 'Birth date' => $me['birth_date'] ? format_date($me['birth_date']) : '',
];
?>
<div class="page-header"><h4>My Profile</h4></div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card text-center"><div class="card-body p-4">
      <?php if ($me['profile_picture']): ?>
        <img src="<?= e(asset('uploads/profiles/' . $me['profile_picture'])) ?>" alt="Profile picture" class="rounded-circle mb-3" style="width:110px;height:110px;object-fit:cover">
      <?php else: ?>
        <span class="initials mx-auto mb-3" style="width:110px;height:110px;font-size:2.2rem"><?= e(initials($me['first_name'], $me['last_name'])) ?></span>
      <?php endif; ?>
      <h5 class="mb-0"><?= e($me['first_name'] . ' ' . $me['last_name']) ?></h5>
      <p class="text-muted"><?= e($role === 'student' ? $me['student_no'] : $me['employee_no']) ?></p>
      <form method="post" enctype="multipart/form-data" class="text-start"><?= csrf_field() ?><input type="hidden" name="action" value="picture">
        <label class="form-label small">Change picture (JPG/PNG, max 2 MB)</label>
        <input class="form-control form-control-sm mb-2" type="file" name="picture" accept="image/jpeg,image/png" required>
        <button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-upload me-1"></i>Upload</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Personal information</div><div class="card-body"><dl class="row mb-0">
      <?php foreach ($info as $k => $v): ?><dt class="col-sm-4 text-muted fw-normal"><?= e($k) ?></dt><dd class="col-sm-8"><?= e($v !== '' ? $v : '—') ?></dd><?php endforeach; ?>
    </dl><small class="text-muted">To change your name or program details, contact the administrator.</small></div></div>

    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Contact information</div><div class="card-body">
      <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="contact">
        <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="30" value="<?= e($me['phone']) ?>"></div>
        <div class="col-md-8"><label class="form-label">Address</label><input class="form-control" name="address" maxlength="255" value="<?= e($me['address']) ?>"></div>
        <div class="col-12"><button class="btn btn-primary">Save contact information</button></div>
      </form></div></div>

    <div class="card"><div class="card-header bg-white fw-semibold">Change password</div><div class="card-body">
      <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="col-md-4"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" required autocomplete="current-password"></div>
        <div class="col-md-4"><label class="form-label">New password</label><input class="form-control" type="password" name="new_password" required autocomplete="new-password"></div>
        <div class="col-md-4"><label class="form-label">Confirm new password</label><input class="form-control" type="password" name="confirm_password" required autocomplete="new-password"></div>
        <div class="col-12"><button class="btn btn-outline-primary">Change password</button> <small class="text-muted ms-2">8+ characters with a letter and a number.</small></div>
      </form></div></div>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
