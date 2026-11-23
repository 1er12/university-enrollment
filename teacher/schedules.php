<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
$user = require_role('teacher');
$pdo  = Database::getConnection();
$me   = teacher_by_user($pdo, (int)$user['id']);
$activeSem = get_active_semester($pdo);
$semId = (int)($_GET['semester'] ?? ($activeSem['id'] ?? 0));

$st = $pdo->prepare('SELECT ss.*, c.code, c.title, sb.section, sb.id AS sub_id,
          (SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id WHERE ed.subject_id = sb.id AND e.status = "approved") AS enrolled
        FROM subject_schedules ss JOIN subjects sb ON sb.id = ss.subject_id JOIN courses c ON c.id = sb.course_id
        WHERE sb.teacher_id = ? AND sb.semester_id = ? ORDER BY ss.start_time');
$st->execute([$me['id'], $semId]);
$byDay = [];
foreach ($st->fetchAll() as $r) $byDay[$r['day_of_week']][] = $r;
$semesters = $pdo->query('SELECT s.id, s.name, ay.name AS ay_name FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id ORDER BY ay.start_date DESC, s.semester_no DESC')->fetchAll();
$today = date('l');

$pageTitle = 'Class Schedules';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h4>Class Schedules</h4>
  <form method="get" class="no-print"><select class="form-select" name="semester" onchange="this.form.submit()"><?php foreach ($semesters as $s): ?><option value="<?= $s['id'] ?>" <?= $semId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['ay_name'] . ' · ' . $s['name']) ?></option><?php endforeach; ?></select></form></div>
<?php if (!$byDay): ?><div class="card"><div class="empty-state"><i class="bi bi-calendar-x"></i>No scheduled classes for this semester.</div></div><?php else: ?>
<div class="row g-3"><?php foreach (DAYS_OF_WEEK as $day): if (empty($byDay[$day])) continue; ?>
  <div class="col-md-6 col-xl-4"><div class="card h-100 <?= $day === $today ? 'border-primary' : '' ?>"><div class="card-header bg-white fw-semibold"><?= e($day) ?><?= $day === $today ? ' <span class="badge text-bg-primary ms-1">Today</span>' : '' ?></div>
    <div class="list-group list-group-flush"><?php foreach ($byDay[$day] as $r): ?>
      <a class="list-group-item list-group-item-action" href="<?= e(url('teacher/students.php?subject=' . (int)$r['sub_id'])) ?>">
        <div class="fw-semibold"><?= e(format_time($r['start_time']) . ' – ' . format_time($r['end_time'])) ?></div>
        <div><?= e($r['code']) ?> <small class="text-muted">· <?= e($r['title']) ?> (<?= e($r['section']) ?>)</small></div>
        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($r['room'] ?: 'TBA') ?> · <i class="bi bi-people ms-1 me-1"></i><?= (int)$r['enrolled'] ?> students</small></a>
    <?php endforeach; ?></div></div></div>
<?php endforeach; ?></div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
