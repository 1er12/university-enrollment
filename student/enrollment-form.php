<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
$user = require_role('student');
$pdo  = Database::getConnection();
$me   = student_by_user($pdo, (int)$user['id']);

$q = $pdo->prepare("SELECT e.*, sem.name AS sem_name, ay.name AS ay_name FROM enrollments e JOIN semesters sem ON sem.id = e.semester_id
                    JOIN academic_years ay ON ay.id = sem.academic_year_id WHERE e.id = ? AND e.student_id = ? AND e.status = 'approved'");
$q->execute([(int)($_GET['id'] ?? 0), $me['id']]);
$en = $q->fetch();
if (!$en) { set_flash('warning', 'Enrollment form not available.'); redirect('student/enrollment-history.php'); }

$rows = enrollment_selected($pdo, (int)$en['id']);
$sched = subject_schedule_rows($pdo, array_column($rows, 'subject_id'));

$pageTitle = 'Enrollment Form';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header no-print"><h4>Enrollment Form</h4>
  <div class="d-flex gap-2"><a href="enrollment-history.php" class="btn btn-light border">Back</a>
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button></div></div>
<div class="card"><div class="card-body p-4 p-md-5">
  <div class="text-center mb-4"><h3 class="text-navy mb-0"><?= e(setting('university_name', APP_NAME)) ?></h3>
    <div class="text-muted"><?= e(setting('university_address')) ?></div><h5 class="mt-3 text-uppercase">Certificate of Enrollment</h5>
    <div><?= e($en['ay_name'] . ' · ' . $en['sem_name']) ?></div></div>
  <div class="row mb-4"><div class="col-sm-6"><div><strong>Name:</strong> <?= e($me['last_name'] . ', ' . $me['first_name'] . ' ' . $me['middle_name']) ?></div>
      <div><strong>Student No.:</strong> <?= e($me['student_no']) ?></div></div>
    <div class="col-sm-6"><div><strong>Program:</strong> <?= e($me['program_name']) ?></div><div><strong>Year level:</strong> <?= (int)$me['year_level'] ?></div></div></div>
  <table class="table table-bordered"><thead><tr><th>Code</th><th>Subject</th><th>Section</th><th>Schedule</th><th>Teacher</th><th class="text-end">Units</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $sum = schedule_summary($sched[$r['subject_id']] ?? []); ?>
    <tr><td><?= e($r['code']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['section']) ?></td><td class="small"><?= $sum ? implode('<br>', array_map('e', $sum)) : 'TBA' ?></td>
      <td><?= e($r['teacher_name'] ?? 'TBA') ?></td><td class="text-end"><?= (int)$r['units'] ?></td></tr><?php endforeach; ?>
  <tr><td colspan="5" class="text-end fw-bold">Total units</td><td class="text-end fw-bold"><?= (int)$en['total_units'] ?></td></tr></tbody></table>
  <div class="d-flex justify-content-between mt-5 small text-muted"><div>Approved: <?= $en['reviewed_at'] ? e(format_date($en['reviewed_at'], 'M d, Y')) : '' ?></div><div>Printed: <?= e(date('M d, Y')) ?></div></div>
</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
