<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings_nav.php';
$user = require_role('admin');
$pdo = Database::getConnection();
$self = 'admin/settings/admins.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { set_flash('danger', 'Invalid request.'); redirect($self); }
    $act = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0);
    $activeAdmins = fn() => (int)scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
    try {
        if ($act === 'save') {
            $in = ['id' => $id, 'username' => clean($_POST['username'] ?? ''), 'email' => clean($_POST['email'] ?? ''), 'status' => clean($_POST['status'] ?? 'active')];
            $pw = $_POST['password'] ?? ''; $errors = [];
            $row = null;
            if ($id) { $r = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'"); $r->execute([$id]); $row = $r->fetch(); if (!$row) { set_flash('danger', 'Account not found.'); redirect($self); } }
            if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $in['username'])) $errors[] = 'Username must be 4-30 characters: letters, numbers, dot or underscore.';
            if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['email']) > 120) $errors[] = 'A valid email is required.';
            if (!in_array($in['status'], ['active', 'inactive'], true)) $errors[] = 'Invalid status.';
            if (!$id && $pw === '') $errors[] = 'Password is required for new accounts.';
            if ($pw !== '' && ($m = validate_password($pw))) $errors[] = $m;
            if ($id && $in['status'] === 'inactive' && $id === (int)$user['id']) $errors[] = 'You cannot deactivate your own account.';
            if ($id && $in['status'] === 'inactive' && $row['status'] === 'active' && $activeAdmins() <= 1) $errors[] = 'At least one active administrator is required.';
            if (!$errors && scalar($pdo, 'SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id <> ?', [$in['username'], $in['email'], $id])) $errors[] = 'That username or email is already in use.';
            if ($errors) { $_SESSION['admin_form'] = ['data' => $in, 'errors' => $errors]; redirect($self); }
            if ($id) {
                $pdo->prepare('UPDATE users SET username = ?, email = ?, status = ? WHERE id = ?')->execute([$in['username'], $in['email'], $in['status'], $id]);
                if ($pw !== '') $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
            } else {
                $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', ?)")->execute([$in['username'], $in['email'], password_hash($pw, PASSWORD_DEFAULT), $in['status']]);
            }
            log_activity($id ? 'admin_updated' : 'admin_created', 'Administrator ' . $in['username']);
            set_flash('success', 'Administrator account saved.');
        } elseif ($act === 'toggle') {
            $r = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin'"); $r->execute([$id]); $row = $r->fetch();
            if (!$row) set_flash('danger', 'Account not found.');
            elseif ($id === (int)$user['id']) set_flash('warning', 'You cannot deactivate your own account.');
            elseif ($row['status'] === 'active' && $activeAdmins() <= 1) set_flash('warning', 'At least one active administrator is required.');
            else {
                $new = $row['status'] === 'active' ? 'inactive' : 'active';
                $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $id]);
                log_activity('admin_' . $new, 'Administrator ' . $row['username']);
                set_flash('success', 'Account ' . ($new === 'active' ? 'activated' : 'deactivated') . '.');
            }
        }
    } catch (PDOException $e) { error_log($e->getMessage()); set_flash('danger', 'A database error occurred.'); }
    redirect($self);
}

$admins = $pdo->query("SELECT id, username, email, status, last_login, created_at FROM users WHERE role = 'admin' ORDER BY username")->fetchAll();
$fs = $_SESSION['admin_form'] ?? null; unset($_SESSION['admin_form']);
$pageTitle = 'Admin Accounts';
require_once __DIR__ . '/../../includes/header.php';
settings_nav('admins');
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-primary" id="addBtn"><i class="bi bi-plus-lg me-1"></i>Add administrator</button></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Username</th><th>Email</th><th>Last login</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach ($admins as $a): ?><tr><td class="fw-semibold"><?= e($a['username']) ?><?= (int)$a['id'] === (int)$user['id'] ? ' <span class="badge text-bg-info">You</span>' : '' ?></td><td><?= e($a['email']) ?></td>
  <td class="small"><?= $a['last_login'] ? e(format_date($a['last_login'], 'M d, Y g:i A')) : 'Never' ?></td><td><?= status_badge($a['status']) ?></td>
  <td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary edit-btn" data-item="<?= e(json_encode($a)) ?>"><i class="bi bi-pencil"></i></button>
    <?php if ((int)$a['id'] !== (int)$user['id']): $on = $a['status'] === 'active'; ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
      <button class="btn btn-sm btn-outline-<?= $on ? 'warning' : 'success' ?>" data-confirm="<?= $on ? 'Deactivate' : 'Activate' ?> <?= e($a['username']) ?>?" data-confirm-class="btn-<?= $on ? 'warning' : 'success' ?>" data-confirm-label="Yes"><i class="bi bi-<?= $on ? 'slash-circle' : 'check-circle' ?>"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="modal fade" id="entityModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="post" id="entityForm" novalidate><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
  <div class="modal-header"><h5 class="modal-title" id="entityTitle">Add administrator</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><?php if (!empty($fs['errors'])): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($fs['errors'] as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="row g-3"><div class="col-md-6"><label class="form-label">Username *</label><input class="form-control" name="username" maxlength="30"></div>
      <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" maxlength="120"></div>
      <div class="col-md-6"><label class="form-label">Password <span id="pwHint">*</span></label><input class="form-control" type="password" name="password" autocomplete="new-password"></div>
      <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div></form></div></div>
<script>document.addEventListener('DOMContentLoaded', () => setupEntityModal({ modalId: 'entityModal', formId: 'entityForm', titleId: 'entityTitle', addBtnId: 'addBtn', editClass: 'edit-btn',
  fields: ['id', 'username', 'email', 'status'], defaults: { status: 'active' }, titles: ['Add administrator', 'Edit administrator'],
  onOpen: (d, edit) => { document.getElementById('pwHint').textContent = edit ? '(leave blank to keep)' : '*'; }, reopen: <?= json_encode($fs['data'] ?? null) ?> }));</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
