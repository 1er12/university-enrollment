<?php
/**
 * Setup checker. Verifies PHP, extensions, folders and the database.
 * DELETE this file before deploying to production.
 */
require_once __DIR__ . '/config/config.php';

$checks = [];
$add = function (string $label, bool $ok, string $detail = '') use (&$checks) {
    $checks[] = compact('label', 'ok', 'detail');
};

$add('PHP version 8.0 or higher', version_compare(PHP_VERSION, '8.0.0', '>='), 'Detected ' . PHP_VERSION);
foreach (['pdo', 'pdo_mysql', 'mbstring', 'fileinfo'] as $ext) {
    $add("PHP extension: $ext", extension_loaded($ext));
}
$add('Uploads folder is writable', is_writable(UPLOAD_PATH), UPLOAD_PATH);
$add('Detected BASE_URL', true, BASE_URL === '' ? '(web root)' : BASE_URL);

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $add('MySQL server connection', true, 'Connected as ' . DB_USER);
    $stmt = $pdo->prepare('SHOW DATABASES LIKE ?');
    $stmt->execute([DB_NAME]);
    $exists = $stmt->fetch();
    $add('Database "' . DB_NAME . '" exists', (bool)$exists,
         $exists ? '' : 'Import database/university_enrollment_db.sql in phpMyAdmin');

    if ($exists) {
        $pdo->exec('USE `' . DB_NAME . '`');
        $tables = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        $add('Database tables imported (18 expected)', $tables >= 18, "Found $tables tables" . ($tables >= 18 ? '' : '. Import database/university_enrollment_db.sql in phpMyAdmin'));
        if ($tables >= 18) {
            $users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username IN ('admin','teacher1','student1')")->fetchColumn();
            $add('Demo accounts seeded (3 expected)', $users === 3, "Found $users of 3");
        }
    }
} catch (PDOException $e) {
    $add('MySQL server connection', false, $e->getMessage() . ' (is MySQL running in XAMPP?)');
}
$allOk = !in_array(false, array_column($checks, 'ok'), true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup Check · <?= htmlspecialchars(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container py-5" style="max-width:760px">
  <div class="card shadow-sm">
    <div class="card-header bg-navy text-white py-3">
      <h5 class="mb-0"><i class="bi bi-gear me-2"></i><?= htmlspecialchars(APP_NAME) ?> — Setup Check</h5>
    </div>
    <ul class="list-group list-group-flush">
      <?php foreach ($checks as $c): ?>
        <li class="list-group-item d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($c['label']) ?></div>
            <?php if ($c['detail']): ?><small class="text-muted"><?= htmlspecialchars($c['detail']) ?></small><?php endif; ?>
          </div>
          <?php if ($c['ok']): ?>
            <span class="badge bg-success"><i class="bi bi-check-lg"></i> OK</span>
          <?php else: ?>
            <span class="badge bg-danger"><i class="bi bi-x-lg"></i> Fix</span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="card-footer bg-white py-3">
      <?php if ($allOk): ?>
        <div class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Setup is complete. You are ready for the next phase.</div>
      <?php else: ?>
        <div class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Fix the items marked above, then refresh this page.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
