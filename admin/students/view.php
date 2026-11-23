<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$user = require_role('admin');
$pdo  = Database::getConnection();

$stmt = $pdo->prepare(
    'SELECT s.*, u.username, u.email, u.last_login, p.code AS program_code, p.name AS program_name, d.name AS department_name
     FROM students s JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
     JOIN departments d ON d.id = p.department_id WHERE s.id = ?'
);
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$s = $stmt->fetch();
if (!$s) { set_flash('danger', 'Student not found.'); redirect('admin/students/index.php'); }

$hist = $pdo->prepare(
    'SELECT e.*, sem.name AS sem_name, ay.name AS ay_name,
            (SELECT COUNT(*) FROM enrollment_details d WHERE d.enrollment_id = e.id) AS subject_count
     FROM enrollments e JOIN semesters sem ON sem.id = e.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
     WHERE e.student_id = ? ORDER BY ay.start_date DESC, sem.semester_no DESC'
);
$hist->execute([$s['id']]);
$history = $hist->fetchAll();

$pageTitle = 'Student Profile';
require_once __DIR__ . '/../../includes/header.php';
$row = fn($label, $value) => '<dt class="col-sm-4 text-muted fw-normal">' . e($label) . '</dt><dd class="col-sm-8">' . e($value !== null && $value !== '' ? $value : '—') . '</dd>';
?>
<div class="page-header">
  <h4>Student Profile</h4>
  <a href="index.php" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i>Back to students</a>
</div>
<div class="row g-3">
  <div class="col-lg-4">
    <div class="card text-center"><div class="card-body p-4">
      <span class="initials mx-auto mb-3" style="width:72px;height:72px;font-size:1.6rem"><?= e(strtoupper(mb_substr($s['first_name'], 0, 1) . mb_substr($s['last_name'], 0, 1))) ?></span>
      <h5 class="mb-0"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></h5>
      <p class="text-muted mb-2"><?= e($s['student_no']) ?></p>
      <?= status_badge($s['status']) ?>
      <hr><p class="mb-1"><?= e($s['program_code']) ?> · Year <?= (int)$s['year_level'] ?></p>
      <small class="text-muted"><?= e($s['department_name']) ?></small>
    </div></div>
  </div>
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Personal information</div>
      <div class="card-body"><dl class="row mb-0">
        <?= $row('Full name', trim($s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name'])) ?>
        <?= $row('Gender', ucfirst((string)$s['gender'])) ?>
        <?= $row('Birth date', $s['birth_date'] ? format_date($s['birth_date']) : '') ?>
        <?= $row('Phone', $s['phone']) ?>
        <?= $row('Address', $s['address']) ?>
        <?= $row('Email', $s['email']) ?>
        <?= $row('Username', $s['username']) ?>
        <?= $row('Program', $s['program_name']) ?>
        <?= $row('Last login', $s['last_login'] ? format_date($s['last_login'], 'M d, Y g:i A') : 'Never') ?>
        <?= $row('Registered', format_date($s['created_at'])) ?>
      </dl></div></div>
    <div class="card"><div class="card-header bg-white fw-semibold">Enrollment history</div>
      <?php if (!$history): ?><div class="empty-state"><i class="bi bi-clipboard"></i>No enrollment records yet</div>
      <?php else: ?>
      <div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Academic year</th><th>Semester</th><th>Subjects</th><th>Units</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($history as $h): ?>
          <tr><td><?= e($h['ay_name']) ?></td><td><?= e($h['sem_name']) ?></td><td><?= (int)$h['subject_count'] ?></td>
              <td><?= (int)$h['total_units'] ?></td><td><?= status_badge($h['status']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
