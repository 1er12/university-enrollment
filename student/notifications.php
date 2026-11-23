<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
$user = require_role('student');
$pdo  = Database::getConnection();
$me   = student_by_user($pdo, (int)$user['id']);

$total = (int)scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$user['id']]);
$pg = paginate($total, (int)($_GET['page'] ?? 1), 15);
$n = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}");
$n->execute([$user['id']]);
$notes = $n->fetchAll();

$a = $pdo->prepare(
    "SELECT a.*, COALESCE(CONCAT(t.first_name, ' ', t.last_name), 'Administrator') AS author, c.code AS subject_code
     FROM announcements a LEFT JOIN teachers t ON t.user_id = a.author_id
     LEFT JOIN subjects sb ON sb.id = a.subject_id LEFT JOIN courses c ON c.id = sb.course_id
     WHERE (a.subject_id IS NULL AND a.audience IN ('all', 'students'))
        OR a.subject_id IN (SELECT ed.subject_id FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id WHERE e.student_id = ? AND e.status = 'approved')
     ORDER BY a.created_at DESC LIMIT 20"
);
$a->execute([$me['id']]);
$announcements = $a->fetchAll();

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
$colors = ['info' => 'primary', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'];
$icons  = ['info' => 'info-circle', 'success' => 'check-circle', 'warning' => 'exclamation-triangle', 'danger' => 'x-circle'];
?>
<div class="page-header"><h4>Notifications &amp; Announcements</h4></div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-semibold">Notifications</div>
    <?php if (!$notes): ?><div class="empty-state"><i class="bi bi-bell-slash"></i>You have no notifications.</div><?php else: ?>
    <div class="list-group list-group-flush"><?php foreach ($notes as $x): $c = $colors[$x['type']] ?? 'primary'; ?>
      <div class="list-group-item d-flex gap-3"><i class="bi bi-<?= $icons[$x['type']] ?? 'info-circle' ?> text-<?= $c ?> fs-5"></i>
        <div class="flex-fill"><div class="d-flex justify-content-between"><strong><?= e($x['title']) ?> <?php if (!$x['is_read']): ?><span class="badge text-bg-primary">New</span><?php endif; ?></strong>
          <small class="text-muted"><?= e(format_date($x['created_at'], 'M d, Y g:i A')) ?></small></div>
          <div class="text-muted small"><?= e($x['message']) ?></div>
          <?php if ($x['link']): ?><a class="small" href="<?= e(url($x['link'])) ?>">Open</a><?php endif; ?></div></div>
    <?php endforeach; ?></div>
    <div class="card-footer bg-white"><?= render_pagination($pg) ?></div><?php endif; ?></div></div>
  <div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">Announcements</div>
    <?php if (!$announcements): ?><div class="empty-state"><i class="bi bi-megaphone"></i>No announcements.</div><?php else: ?>
    <div class="list-group list-group-flush"><?php foreach ($announcements as $x): ?>
      <div class="list-group-item"><div class="d-flex justify-content-between"><strong><?= e($x['title']) ?></strong>
        <?php if ($x['subject_code']): ?><span class="badge text-bg-info"><?= e($x['subject_code']) ?></span><?php endif; ?></div>
        <div class="small"><?= nl2br(e($x['body'])) ?></div>
        <small class="text-muted"><?= e($x['author']) ?> · <?= e(format_date($x['created_at'], 'M d, Y')) ?></small></div>
    <?php endforeach; ?></div><?php endif; ?></div></div>
</div>
<?php
// mark as read after displaying so the "New" badges show once
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$user['id']]);
require_once __DIR__ . '/../includes/footer.php';
