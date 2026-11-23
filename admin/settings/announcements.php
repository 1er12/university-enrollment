<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings_nav.php';
$user = require_role('admin');
$pdo = Database::getConnection();
$self = 'admin/settings/announcements.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { set_flash('danger', 'Invalid request.'); redirect($self); }
    if (($_POST['action'] ?? '') === 'post') {
        $aud = $_POST['audience'] ?? ''; $title = clean($_POST['title'] ?? ''); $body = trim(strip_tags($_POST['body'] ?? ''));
        if (!in_array($aud, ['all', 'students', 'teachers'], true)) set_flash('danger', 'Choose an audience.');
        elseif ($title === '' || mb_strlen($title) > 150)          set_flash('danger', 'A title is required (max 150 characters).');
        elseif ($body === '' || mb_strlen($body) > 2000)           set_flash('danger', 'A message is required (max 2000 characters).');
        else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO announcements (author_id, subject_id, audience, title, body) VALUES (?, NULL, ?, ?, ?)')->execute([$user['id'], $aud, $title, $body]);
                $roles = ['all' => "('student','teacher')", 'students' => "('student')", 'teachers' => "('teacher')"][$aud];
                $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link)
                               SELECT id, 'info', 'Announcement', ?, CASE role WHEN 'student' THEN 'student/notifications.php' ELSE 'teacher/announcements.php' END
                               FROM users WHERE status = 'active' AND role IN $roles")->execute([$title]);
                $pdo->commit();
                log_activity('announcement_posted', "To $aud: $title");
                set_flash('success', 'Announcement published and users were notified.');
            } catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($e->getMessage()); set_flash('danger', 'Could not publish the announcement.'); }
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM announcements WHERE id = ? AND subject_id IS NULL')->execute([(int)($_POST['id'] ?? 0)]);
        log_activity('announcement_deleted', '#' . (int)($_POST['id'] ?? 0));
        set_flash('success', 'Announcement deleted.');
    }
    redirect($self);
}
$list = $pdo->query("SELECT a.*, u.username FROM announcements a JOIN users u ON u.id = a.author_id WHERE a.subject_id IS NULL ORDER BY a.created_at DESC LIMIT 50")->fetchAll();
$pageTitle = 'Announcements';
require_once __DIR__ . '/../../includes/header.php';
settings_nav('announcements');
?>
<div class="row g-3">
  <div class="col-lg-5"><div class="card"><div class="card-header bg-white fw-semibold">New announcement</div><div class="card-body">
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="post">
      <label class="form-label">Audience</label><select class="form-select mb-3" name="audience"><option value="all">Everyone (students and teachers)</option><option value="students">Students only</option><option value="teachers">Teachers only</option></select>
      <label class="form-label">Title</label><input class="form-control mb-3" name="title" maxlength="150" required>
      <label class="form-label">Message</label><textarea class="form-control mb-3" name="body" rows="5" maxlength="2000" required></textarea>
      <button class="btn btn-primary w-100"><i class="bi bi-megaphone me-1"></i>Publish &amp; notify</button></form></div></div></div>
  <div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-semibold">Published announcements</div>
    <?php if (!$list): ?><div class="empty-state"><i class="bi bi-megaphone"></i>No announcements yet.</div><?php else: ?>
    <div class="list-group list-group-flush"><?php foreach ($list as $x): ?><div class="list-group-item d-flex justify-content-between gap-2"><div>
      <strong><?= e($x['title']) ?></strong> <span class="badge text-bg-light border text-capitalize"><?= e($x['audience']) ?></span><div class="small"><?= nl2br(e($x['body'])) ?></div>
      <small class="text-muted"><?= e($x['username']) ?> · <?= e(format_date($x['created_at'], 'M d, Y g:i A')) ?></small></div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
        <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this announcement?" data-confirm-label="Delete"><i class="bi bi-trash"></i></button></form></div><?php endforeach; ?></div><?php endif; ?></div></div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
