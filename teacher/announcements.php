<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
$user = require_role('teacher');
$pdo  = Database::getConnection();
$me   = teacher_by_user($pdo, (int)$user['id']);

$subs = $pdo->prepare('SELECT sb.id, sb.section, c.code, c.title, sem.name AS sem_name, ay.name AS ay_name FROM subjects sb JOIN courses c ON c.id = sb.course_id
                       JOIN semesters sem ON sem.id = sb.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
                       WHERE sb.teacher_id = ? ORDER BY sem.is_active DESC, ay.start_date DESC, sem.semester_no DESC, c.code');
$subs->execute([$me['id']]);
$mine = array_column($subs->fetchAll(), null, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { set_flash('danger', 'Invalid request. Please try again.'); redirect('teacher/announcements.php'); }
    $act = $_POST['action'] ?? '';
    if ($act === 'post') {
        $sid = (int)($_POST['subject_id'] ?? 0); $title = clean($_POST['title'] ?? ''); $body = trim(strip_tags($_POST['body'] ?? ''));
        if (!isset($mine[$sid]))                                   set_flash('danger', 'Please choose one of your subjects.');
        elseif ($title === '' || mb_strlen($title) > 150)          set_flash('danger', 'A title is required (max 150 characters).');
        elseif ($body === '' || mb_strlen($body) > 2000)           set_flash('danger', 'A message is required (max 2000 characters).');
        else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO announcements (author_id, subject_id, audience, title, body) VALUES (?, ?, 'students', ?, ?)")->execute([$user['id'], $sid, $title, $body]);
                $label = $mine[$sid]['code'] . ' (' . $mine[$sid]['section'] . ')';
                $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link)
                               SELECT DISTINCT s.user_id, 'info', ?, ?, 'student/notifications.php' FROM enrollment_details ed
                               JOIN enrollments e ON e.id = ed.enrollment_id AND e.status = 'approved' JOIN students s ON s.id = e.student_id WHERE ed.subject_id = ?")
                    ->execute(["New announcement: $label", $title, $sid]);
                $pdo->commit();
                log_activity('announcement_posted', "$label: $title");
                set_flash('success', 'Announcement posted and enrolled students were notified.');
            } catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($e->getMessage()); set_flash('danger', 'Could not post the announcement.'); }
        }
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM announcements WHERE id = ? AND author_id = ?')->execute([(int)($_POST['id'] ?? 0), $user['id']]);
        set_flash('success', 'Announcement deleted.');
    }
    redirect('teacher/announcements.php');
}

$a = $pdo->prepare('SELECT a.*, c.code, sb.section FROM announcements a LEFT JOIN subjects sb ON sb.id = a.subject_id LEFT JOIN courses c ON c.id = sb.course_id
                    WHERE a.author_id = ? ORDER BY a.created_at DESC LIMIT 30');
$a->execute([$user['id']]);
$mineAnn = $a->fetchAll();
$sys = $pdo->query("SELECT a.*, 'Administrator' AS author FROM announcements a JOIN users u ON u.id = a.author_id AND u.role = 'admin'
                    WHERE a.subject_id IS NULL AND a.audience IN ('all', 'teachers') ORDER BY a.created_at DESC LIMIT 10")->fetchAll();

$pageTitle = 'Announcements';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h4>Announcements</h4></div>
<div class="row g-3">
  <div class="col-lg-5"><div class="card mb-3"><div class="card-header bg-white fw-semibold">Post to a class</div><div class="card-body">
    <?php if (!$mine): ?><div class="text-muted">You have no assigned subjects.</div><?php else: ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="post">
      <label class="form-label">Subject</label><select class="form-select mb-3" name="subject_id" required>
        <?php foreach ($mine as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['ay_name'] . ' ' . $s['sem_name'] . ' — ' . $s['code'] . ' (' . $s['section'] . ')') ?></option><?php endforeach; ?></select>
      <label class="form-label">Title</label><input class="form-control mb-3" name="title" maxlength="150" required>
      <label class="form-label">Message</label><textarea class="form-control mb-3" name="body" rows="4" maxlength="2000" required></textarea>
      <button class="btn btn-primary w-100"><i class="bi bi-megaphone me-1"></i>Post announcement</button></form><?php endif; ?></div></div>
    <div class="card"><div class="card-header bg-white fw-semibold">From the administrator</div>
      <?php if (!$sys): ?><div class="empty-state py-4"><i class="bi bi-megaphone"></i>No system announcements.</div><?php else: ?>
      <div class="list-group list-group-flush"><?php foreach ($sys as $x): ?><div class="list-group-item"><strong><?= e($x['title']) ?></strong><div class="small"><?= nl2br(e($x['body'])) ?></div>
        <small class="text-muted"><?= e(format_date($x['created_at'])) ?></small></div><?php endforeach; ?></div><?php endif; ?></div></div>
  <div class="col-lg-7"><div class="card"><div class="card-header bg-white fw-semibold">My announcements</div>
    <?php if (!$mineAnn): ?><div class="empty-state"><i class="bi bi-megaphone"></i>You haven’t posted any announcements.</div><?php else: ?>
    <div class="list-group list-group-flush"><?php foreach ($mineAnn as $x): ?><div class="list-group-item d-flex justify-content-between gap-2"><div>
      <div><strong><?= e($x['title']) ?></strong> <?php if ($x['code']): ?><span class="badge text-bg-info"><?= e($x['code'] . ' ' . $x['section']) ?></span><?php endif; ?></div>
      <div class="small"><?= nl2br(e($x['body'])) ?></div><small class="text-muted"><?= e(format_date($x['created_at'], 'M d, Y g:i A')) ?></small></div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
        <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this announcement?" data-confirm-label="Delete"><i class="bi bi-trash"></i></button></form></div><?php endforeach; ?></div><?php endif; ?></div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
