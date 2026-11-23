<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
$user = require_role('student');
$pdo  = Database::getConnection();
$me   = student_by_user($pdo, (int)$user['id']);

$q = $pdo->prepare('SELECT e.*, sem.name AS sem_name, ay.name AS ay_name FROM enrollments e
                    JOIN semesters sem ON sem.id = e.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
                    WHERE e.student_id = ? AND (e.status <> "draft" OR EXISTS (SELECT 1 FROM enrollment_details d WHERE d.enrollment_id = e.id))
                    ORDER BY ay.start_date DESC, sem.semester_no DESC');
$q->execute([$me['id']]);
$history = $q->fetchAll();
$d = $pdo->prepare('SELECT d.units, d.result, d.grade, sb.section, c.code, c.title FROM enrollment_details d
                    JOIN subjects sb ON sb.id = d.subject_id JOIN courses c ON c.id = sb.course_id WHERE d.enrollment_id = ? ORDER BY c.code');

$pageTitle = 'Enrollment History';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h4>Enrollment History</h4></div>
<?php if (!$history): ?><div class="card"><div class="empty-state"><i class="bi bi-clock-history"></i>No enrollment records yet.</div></div><?php endif; ?>
<?php foreach ($history as $h): $d->execute([$h['id']]); $rows = $d->fetchAll(); ?>
  <div class="card mb-3">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div><span class="fw-semibold"><?= e($h['ay_name'] . ' · ' . $h['sem_name']) ?></span> <span class="text-muted small ms-2"><?= (int)$h['total_units'] ?> units</span></div>
      <div class="d-flex align-items-center gap-2"><?= status_badge($h['status']) ?>
        <?php if ($h['status'] === 'approved'): ?><a class="btn btn-sm btn-outline-primary" href="enrollment-form.php?id=<?= (int)$h['id'] ?>"><i class="bi bi-printer"></i> Form</a><?php endif; ?></div>
    </div>
    <?php if ($h['remarks']): ?><div class="px-3 pt-2 small text-muted">Remarks: <?= e($h['remarks']) ?></div><?php endif; ?>
    <?php if ($rows): ?><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Subject</th><th>Section</th><th>Units</th><th>Result</th><th>Grade</th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?><tr><td><strong><?= e($r['code']) ?></strong> <span class="text-muted"><?= e($r['title']) ?></span></td><td><?= e($r['section']) ?></td><td><?= (int)$r['units'] ?></td>
        <td><?= status_badge($r['result']) ?></td><td><?= $r['grade'] !== null ? e(number_format((float)$r['grade'], 2)) : '—' ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><div class="p-3 text-muted small">No subjects in this application.</div><?php endif; ?>
  </div>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
