<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
$user = require_role('teacher');
$pdo  = Database::getConnection();
$me   = teacher_by_user($pdo, (int)$user['id']);
$sem  = get_active_semester($pdo);
$semId = (int)($sem['id'] ?? 0);

$subjectCount = (int)scalar($pdo, 'SELECT COUNT(*) FROM subjects WHERE teacher_id = ? AND semester_id = ?', [$me['id'], $semId]);
$studentCount = (int)scalar($pdo, "SELECT COUNT(DISTINCT e.student_id) FROM subjects sb JOIN enrollment_details ed ON ed.subject_id = sb.id
                                   JOIN enrollments e ON e.id = ed.enrollment_id AND e.status = 'approved'
                                   WHERE sb.teacher_id = ? AND sb.semester_id = ?", [$me['id'], $semId]);

// upcoming classes: next 5 slots counting from now
$q = $pdo->prepare('SELECT ss.*, c.code, c.title, sb.section FROM subject_schedules ss JOIN subjects sb ON sb.id = ss.subject_id
                    JOIN courses c ON c.id = sb.course_id WHERE sb.teacher_id = ? AND sb.semester_id = ?');
$q->execute([$me['id'], $semId]);
$todayIdx = (int)date('N') - 1; $now = date('H:i:s');
$upcoming = [];
foreach ($q->fetchAll() as $r) {
    $offset = (array_search($r['day_of_week'], DAYS_OF_WEEK, true) - $todayIdx + 7) % 7;
    if ($offset === 0 && $r['end_time'] <= $now) $offset = 7;
    $r['offset'] = $offset;
    $upcoming[] = $r;
}
usort($upcoming, fn($a, $b) => [$a['offset'], $a['start_time']] <=> [$b['offset'], $b['start_time']]);
$upcoming = array_slice($upcoming, 0, 5);

$act = $pdo->prepare('SELECT action, description, created_at FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 5');
$act->execute([$user['id']]);
$recent = $act->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card mb-4 border-0 bg-navy text-white"><div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
  <div><h4 class="mb-1">Welcome, <?= e($me['first_name']) ?>!</h4><div class="opacity-75"><?= e($me['employee_no']) ?> · <?= e($me['department_name'] ?? 'No department') ?></div></div>
  <div class="text-md-end"><div class="small opacity-75">Current semester</div><div class="fw-semibold"><?= $sem ? e($sem['ay_name'] . ' · ' . $sem['name']) : 'No active semester' ?></div></div>
</div></div>
<div class="row g-3 mb-4">
  <?php foreach ([['Assigned subjects', $subjectCount, 'bi-book', 'primary', 'teacher/my-subjects.php'], ['Students handled', $studentCount, 'bi-people', 'success', 'teacher/students.php']] as [$l, $v, $i, $c, $p]): ?>
    <div class="col-6 col-lg-3"><a class="text-decoration-none" href="<?= e(url($p)) ?>"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="stat-icon bg-<?= $c ?>-subtle text-<?= $c ?>"><i class="bi <?= $i ?>"></i></div><div><div class="fs-4 fw-bold text-dark lh-1"><?= $v ?></div><small class="text-muted"><?= $l ?></small></div></div></div></a></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card h-100"><div class="card-header bg-white fw-semibold">Upcoming classes</div>
    <?php if (!$upcoming): ?><div class="empty-state"><i class="bi bi-calendar-x"></i>No scheduled classes this semester.</div><?php else: ?>
    <div class="list-group list-group-flush"><?php foreach ($upcoming as $u): ?>
      <div class="list-group-item d-flex justify-content-between align-items-center"><div><div class="fw-semibold"><?= e($u['code']) ?> <small class="text-muted">· <?= e($u['title']) ?> (<?= e($u['section']) ?>)</small></div>
        <small class="text-muted"><?= e(format_time($u['start_time']) . ' – ' . format_time($u['end_time'])) ?><?= $u['room'] ? ' · ' . e($u['room']) : '' ?></small></div>
        <span class="badge text-bg-<?= $u['offset'] === 0 ? 'success' : 'light border' ?>"><?= $u['offset'] === 0 ? 'Today' : ($u['offset'] === 1 ? 'Tomorrow' : e($u['day_of_week'])) ?></span></div>
    <?php endforeach; ?></div><?php endif; ?></div></div>
  <div class="col-lg-5"><div class="card h-100"><div class="card-header bg-white fw-semibold">Recent activities</div>
    <?php if (!$recent): ?><div class="empty-state"><i class="bi bi-activity"></i>No activity yet.</div><?php else: ?>
    <div class="list-group list-group-flush"><?php foreach ($recent as $r): ?><div class="list-group-item small"><span class="badge text-bg-light border"><?= e($r['action']) ?></span>
      <span class="text-muted ms-1"><?= e($r['description']) ?></span><div class="text-muted" style="font-size:.7rem"><?= e(format_date($r['created_at'], 'M d, g:i A')) ?></div></div><?php endforeach; ?></div><?php endif; ?></div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
