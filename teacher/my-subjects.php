<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
$user = require_role('teacher');
$pdo  = Database::getConnection();
$me   = teacher_by_user($pdo, (int)$user['id']);
$activeSem = get_active_semester($pdo);
$semParam = $_GET['semester'] ?? ($activeSem ? (string)$activeSem['id'] : 'all');
$semId = ctype_digit((string)$semParam) ? (int)$semParam : 0;

$sql = "SELECT sb.*, c.code, c.title, c.units, c.description, sem.name AS sem_name, ay.name AS ay_name,
          (SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id WHERE ed.subject_id = sb.id AND e.status = 'approved') AS enrolled,
          (SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id WHERE ed.subject_id = sb.id AND e.status = 'pending') AS pending
        FROM subjects sb JOIN courses c ON c.id = sb.course_id JOIN semesters sem ON sem.id = sb.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
        WHERE sb.teacher_id = ?" . ($semId ? ' AND sb.semester_id = ?' : '') . ' ORDER BY ay.start_date DESC, sem.semester_no DESC, c.code';
$st = $pdo->prepare($sql);
$st->execute($semId ? [$me['id'], $semId] : [$me['id']]);
$subjects = $st->fetchAll();
$sched = subject_schedule_rows($pdo, array_column($subjects, 'id'));
$semesters = $pdo->query('SELECT s.id, s.name, ay.name AS ay_name FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id ORDER BY ay.start_date DESC, s.semester_no DESC')->fetchAll();

$pageTitle = 'My Subjects';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h4>My Subjects</h4>
  <form method="get"><select class="form-select" name="semester" onchange="this.form.submit()"><option value="all">All semesters</option>
    <?php foreach ($semesters as $s): ?><option value="<?= $s['id'] ?>" <?= $semId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['ay_name'] . ' · ' . $s['name']) ?></option><?php endforeach; ?></select></form></div>
<?php if (!$subjects): ?><div class="card"><div class="empty-state"><i class="bi bi-book"></i>No subjects assigned for this selection.</div></div><?php else: ?>
<div class="row g-3"><?php foreach ($subjects as $s): $pct = $s['max_students'] ? min(100, $s['enrolled'] / $s['max_students'] * 100) : 0; $sum = schedule_summary($sched[$s['id']] ?? []); ?>
  <div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body">
    <div class="d-flex justify-content-between"><div><div class="fw-bold text-navy"><?= e($s['code']) ?> <small class="text-muted fw-normal">· Section <?= e($s['section']) ?></small></div><div><?= e($s['title']) ?></div></div><?= status_badge($s['status']) ?></div>
    <div class="small text-muted mt-1"><?= e($s['ay_name'] . ' · ' . $s['sem_name']) ?> · <?= (int)$s['units'] ?> units</div>
    <?php if ($s['description']): ?><p class="small mt-2 mb-2"><?= e(mb_strimwidth($s['description'], 0, 120, '…')) ?></p><?php endif; ?>
    <div class="small mb-2"><i class="bi bi-clock me-1"></i><?= $sum ? implode('<br><i class="bi bi-clock me-1 invisible"></i>', array_map('e', $sum)) : 'Schedule TBA' ?></div>
    <div class="d-flex justify-content-between small"><span>Enrolled</span><span><?= (int)$s['enrolled'] ?> / <?= (int)$s['max_students'] ?><?= $s['pending'] ? ' · ' . (int)$s['pending'] . ' pending' : '' ?></span></div>
    <div class="progress" style="height:6px"><div class="progress-bar <?= $pct >= 100 ? 'bg-danger' : '' ?>" style="width:<?= $pct ?>%"></div></div>
  </div><div class="card-footer bg-white"><a class="btn btn-sm btn-primary" href="<?= e(url('teacher/students.php?subject=' . (int)$s['id'])) ?>"><i class="bi bi-people me-1"></i>Class list</a></div></div></div>
<?php endforeach; ?></div><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
