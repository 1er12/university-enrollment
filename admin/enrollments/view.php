<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$user = require_role('admin');
$pdo  = Database::getConnection();
$id   = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT e.*, s.id AS sid, s.student_no, s.first_name, s.last_name, s.year_level, p.code AS program_code, p.name AS program_name,
            sem.name AS sem_name, sem.max_units, ay.name AS ay_name, rv.username AS reviewer
     FROM enrollments e JOIN students s ON s.id = e.student_id JOIN programs p ON p.id = s.program_id
     JOIN semesters sem ON sem.id = e.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
     LEFT JOIN users rv ON rv.id = e.reviewed_by
     WHERE e.id = ? AND e.status <> 'draft'"
);
$stmt->execute([$id]);
$en = $stmt->fetch();
if (!$en) { set_flash('danger', 'Application not found.'); redirect('admin/enrollments/index.php'); }

$d = $pdo->prepare(
    "SELECT d.*, sb.section, sb.max_students, sb.id AS sub_id, c.code, c.title, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
            (SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e2 ON e2.id = ed.enrollment_id
             WHERE ed.subject_id = sb.id AND e2.status = 'approved' AND e2.id <> d.enrollment_id) AS taken
     FROM enrollment_details d JOIN subjects sb ON sb.id = d.subject_id JOIN courses c ON c.id = sb.course_id
     LEFT JOIN teachers t ON t.id = sb.teacher_id WHERE d.enrollment_id = ? ORDER BY c.code"
);
$d->execute([$id]);
$details = $d->fetchAll();

$sched = [];
$sq = $pdo->prepare('SELECT ss.* FROM subject_schedules ss JOIN enrollment_details d ON d.subject_id = ss.subject_id WHERE d.enrollment_id = ?
                     ORDER BY FIELD(ss.day_of_week,"Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"), ss.start_time');
$sq->execute([$id]);
foreach ($sq->fetchAll() as $r) $sched[$r['subject_id']][] = $r;

// warnings: full subjects + schedule clashes inside this application
$warnings = [];
foreach ($details as $x) {
    if ((int)$x['taken'] >= (int)$x['max_students']) $warnings[] = "{$x['code']} ({$x['section']}) is already full.";
}
$flat = [];
foreach ($details as $x) foreach ($sched[$x['subject_id']] ?? [] as $r) $flat[] = $r + ['code' => $x['code']];
foreach ($flat as $i => $a) foreach ($flat as $j => $b) {
    if ($j > $i && $a['subject_id'] !== $b['subject_id'] && $a['day_of_week'] === $b['day_of_week'] && $a['start_time'] < $b['end_time'] && $a['end_time'] > $b['start_time']) {
        $warnings[] = "Schedule conflict on {$a['day_of_week']}: {$a['code']} overlaps {$b['code']}.";
    }
}
if ((int)$en['total_units'] > (int)$en['max_units']) $warnings[] = "Total units ({$en['total_units']}) exceed the semester limit of {$en['max_units']}.";

$pageTitle = 'Review Application';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><h4>Enrollment Application #<?= (int)$en['id'] ?></h4>
  <a href="index.php" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i>Back to applications</a></div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
      <h6 class="text-muted text-uppercase small">Student</h6>
      <div class="fw-semibold fs-5"><?= e($en['first_name'] . ' ' . $en['last_name']) ?></div>
      <div class="text-muted"><?= e($en['student_no']) ?></div>
      <div class="mt-2"><?= e($en['program_code']) ?> · Year <?= (int)$en['year_level'] ?></div>
      <a href="<?= e(url('admin/students/view.php?id=' . (int)$en['sid'])) ?>" class="small">View student profile</a>
      <hr><h6 class="text-muted text-uppercase small">Application</h6>
      <div><?= e($en['ay_name'] . ' · ' . $en['sem_name']) ?></div>
      <div class="my-1"><?= status_badge($en['status']) ?></div>
      <div class="small text-muted">Submitted <?= $en['submitted_at'] ? e(format_date($en['submitted_at'], 'M d, Y g:i A')) : '—' ?></div>
      <div class="mt-2"><strong><?= (int)$en['total_units'] ?></strong> units · <strong><?= count($details) ?></strong> subjects</div>
    </div></div>
    <?php if ($en['status'] !== 'pending'): ?>
    <div class="card"><div class="card-body">
      <h6 class="text-muted text-uppercase small">Decision</h6>
      <div><?= status_badge($en['status']) ?> by <strong><?= e($en['reviewer'] ?? 'unknown') ?></strong></div>
      <div class="small text-muted mb-2"><?= $en['reviewed_at'] ? e(format_date($en['reviewed_at'], 'M d, Y g:i A')) : '' ?></div>
      <div><?= $en['remarks'] ? nl2br(e($en['remarks'])) : '<span class="text-muted">No remarks.</span>' ?></div>
    </div></div>
    <?php endif; ?>
  </div>

  <div class="col-lg-8">
    <?php foreach ($warnings as $w): ?><div class="alert alert-warning py-2 mb-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e($w) ?></div><?php endforeach; ?>
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Selected subjects</div>
      <?php if (!$details): ?><div class="empty-state"><i class="bi bi-book"></i>This application has no subjects.</div><?php else: ?>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Subject</th><th>Teacher</th><th>Schedule</th><th>Units</th><th>Slots</th></tr></thead>
        <tbody><?php foreach ($details as $x): $sum = schedule_summary($sched[$x['subject_id']] ?? []); $slots = (int)$x['max_students'] - (int)$x['taken']; ?>
          <tr><td><div class="fw-semibold"><?= e($x['code']) ?> <small class="text-muted">· <?= e($x['section']) ?></small></div><small><?= e($x['title']) ?></small></td>
              <td><?= e($x['teacher_name'] ?? '—') ?></td>
              <td class="small"><?= $sum ? implode('<br>', array_map('e', $sum)) : '<span class="text-muted">TBA</span>' ?></td>
              <td><?= (int)$x['units'] ?></td>
              <td><span class="<?= $slots <= 0 ? 'text-danger fw-semibold' : '' ?>"><?= max(0, $slots) ?> left</span></td></tr>
        <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>

    <?php if ($en['status'] === 'pending'): ?>
    <div class="row g-3">
      <div class="col-md-6"><form class="card h-100" method="post" action="actions.php"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="approve">
        <div class="card-body"><h6 class="text-success"><i class="bi bi-check-circle me-1"></i>Approve</h6>
          <label class="form-label small">Remarks (optional)</label><textarea class="form-control" name="remarks" rows="3" maxlength="500"></textarea></div>
        <div class="card-footer bg-white"><button class="btn btn-success w-100" data-confirm="Approve this enrollment application?" data-confirm-class="btn-success" data-confirm-label="Approve">Approve application</button></div>
      </form></div>
      <div class="col-md-6"><form class="card h-100" method="post" action="actions.php"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="reject">
        <div class="card-body"><h6 class="text-danger"><i class="bi bi-x-circle me-1"></i>Reject</h6>
          <label class="form-label small">Reason for rejection *</label><textarea class="form-control" name="remarks" rows="3" maxlength="500" required></textarea></div>
        <div class="card-footer bg-white"><button class="btn btn-outline-danger w-100" data-confirm="Reject this application? The student will be notified." data-confirm-label="Reject">Reject application</button></div>
      </form></div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
