<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings_nav.php';
$user = require_role('admin');
$pdo = Database::getConnection();
$keys = ['university_name' => 'University name', 'university_address' => 'Address', 'university_email' => 'Contact email', 'university_phone' => 'Contact phone', 'default_max_units' => 'Default max units per semester'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { set_flash('danger', 'Invalid request.'); redirect('admin/settings/index.php'); }
    $v = []; foreach ($keys as $k => $_) $v[$k] = clean($_POST[$k] ?? '');
    $err = null;
    if ($v['university_name'] === '' || mb_strlen($v['university_name']) > 150) $err = 'University name is required (max 150 characters).';
    elseif (mb_strlen($v['university_address']) > 255) $err = 'Address is too long.';
    elseif ($v['university_email'] !== '' && !filter_var($v['university_email'], FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid contact email.';
    elseif (!preg_match('/^[0-9+()\-\s]{0,30}$/', $v['university_phone'])) $err = 'Phone may only contain digits, spaces and + - ( ).';
    elseif (!ctype_digit($v['default_max_units']) || (int)$v['default_max_units'] < 1 || (int)$v['default_max_units'] > 40) $err = 'Default max units must be between 1 and 40.';
    if ($err) set_flash('danger', $err);
    else {
        $st = $pdo->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($v as $k => $val) $st->execute([$k, $val]);
        log_activity('settings_updated', 'Updated university information');
        set_flash('success', 'Settings saved.');
    }
    redirect('admin/settings/index.php');
}
$pageTitle = 'System Settings';
require_once __DIR__ . '/../../includes/header.php';
settings_nav('index');
?>
<div class="card" style="max-width:720px"><div class="card-header bg-white fw-semibold">University information</div><div class="card-body">
  <form method="post" class="row g-3"><?= csrf_field() ?>
    <?php foreach ($keys as $k => $label): ?><div class="col-12"><label class="form-label"><?= e($label) ?></label>
      <input class="form-control" name="<?= $k ?>" value="<?= e(setting($k)) ?>" <?= $k === 'default_max_units' ? 'type="number" min="1" max="40"' : '' ?>></div><?php endforeach; ?>
    <div class="col-12"><button class="btn btn-primary">Save settings</button></div>
  </form></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
