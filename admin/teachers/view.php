<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$user = require_role('admin');
$pdo  = Database::getConnection();

$stmt = $pdo->prepare(
    'SELECT t.*, u.username, u.email, u.last_login, d.name AS department_name
     FROM teachers t JOIN users u ON u.id = t.user_id LEFT JOIN departments d ON d.id = t.department_id WHERE t.id = ?'
);
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$t = $stmt->fetch();
if (!$t) { set_flash('danger', 'Teacher not found.'); redirect('admin/teachers/index.php'); }

$subs = $pdo->prepare(
    "SELECT sb.section, sb.max_students, c.code, c.title, c.units, sem.name AS sem_name, ay.name AS ay_name,
            (SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id
             WHERE ed.subject_id = sb.id AND e.status = 'approved') AS enrolled
     FROM subjects sb JOIN courses c ON c.id = sb.course_id JOIN semesters sem ON sem.id = sb.semester_id
     JOIN academic_years ay ON ay.id = sem.academic_year_id
     WHERE sb.teacher_id = ? ORDER BY ay.start_date DESC, sem.semester_no DESC, c.code"
);
$subs->execute([$t['id']]);
$subjects = $subs->fetchAll();

$pageTitle = 'Teacher Profile';
require_once __DIR__ . '/../../includes/header.php';
$row = fn($label, $value) => '<dt class="col-sm-4 text-muted fw-normal">' . e($label) . '</dt><dd class="col-sm-8">' . e($value !== null && $value !== '' ? $value : '—') . '</dd>';
?>
<div class="page-header"><h4>Teacher Profile</h4>
  <a href="index.php" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i>Back to teachers</a></div>
<div class="row g-3">
  <div class="col-lg-4"><div class="card text-center"><div class="card-body p-4">
    <span class="initials mx-auto mb-3" style="width:72px;height:72px;font-size:1.6rem"><?= e(strtoupper(mb_substr($t['first_name'], 0, 1) . mb_substr($t['last_name'], 0, 1))) ?></span>
    <h5 class="mb-0"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></h5>
    <p class="text-muted mb-2"><?= e($t['employee_no']) ?></p><?= status_badge($t['status']) ?>
    <hr><small class="text-muted"><?= e($t['department_name'] ?? 'No department') ?></small>
  </div></div></div>
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Personal information</div><div class="card-body"><dl class="row mb-0">
      <?= $row('Full name', trim($t['first_name'] . ' ' . $t['middle_name'] . ' ' . $t['last_name'])) ?>
      <?= $row('Gender', ucfirst((string)$t['gender'])) ?>
      <?= $row('Birth date', $t['birth_date'] ? format_date($t['birth_date']) : '') ?>
      <?= $row('Phone', $t['phone']) ?><?= $row('Address', $t['address']) ?>
      <?= $row('Email', $t['email']) ?><?= $row('Username', $t['username']) ?>
      <?= $row('Last login', $t['last_login'] ? format_date($t['last_login'], 'M d, Y g:i A') : 'Never') ?>
    </dl></div></div>
    <div class="card"><div class="card-header bg-white fw-semibold">Assigned subjects</div>
      <?php if (!$subjects): ?><div class="empty-state"><i class="bi bi-book"></i>No subjects assigned yet</div><?php else: ?>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Term</th><th>Subject</th><th>Section</th><th>Units</th><th>Enrolled</th></tr></thead>
        <tbody><?php foreach ($subjects as $s): ?>
          <tr><td class="text-nowrap"><?= e($s['ay_name'] . ' · ' . $s['sem_name']) ?></td>
              <td><span class="fw-semibold"><?= e($s['code']) ?></span> <span class="text-muted"><?= e($s['title']) ?></span></td>
              <td><?= e($s['section']) ?></td><td><?= (int)$s['units'] ?></td><td><?= (int)$s['enrolled'] ?> / <?= (int)$s['max_students'] ?></td></tr>
        <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
