<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
$user = require_role('student');
$pdo  = Database::getConnection();
$me   = student_by_user($pdo, (int)$user['id']);
$sem  = get_active_semester($pdo);
[$open, $windowMsg] = enrollment_window($sem);

$enr = null; $subjectCount = 0; $units = 0;
if ($sem) {
    $q = $pdo->prepare('SELECT * FROM enrollments WHERE student_id = ? AND semester_id = ?');
    $q->execute([$me['id'], $sem['id']]);
    $enr = $q->fetch() ?: null;
    if ($enr) {
        $subjectCount = (int)scalar($pdo, 'SELECT COUNT(*) FROM enrollment_details WHERE enrollment_id = ?', [$enr['id']]);
        $units = (int)scalar($pdo, 'SELECT COALESCE(SUM(units), 0) FROM enrollment_details WHERE enrollment_id = ?', [$enr['id']]);
    }
}
$status = $enr['status'] ?? 'not started';
$n = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5');
$n->execute([$user['id']]);
$notes = $n->fetchAll();

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card mb-4 border-0 bg-navy text-white"><div class="card-body p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
  <div><h4 class="mb-1">Welcome, <?= e($me['first_name']) ?>!</h4>
    <div class="opacity-75">Student ID <strong><?= e($me['student_no']) ?></strong> · <?= e($me['program_code']) ?> · Year <?= (int)$me['year_level'] ?></div></div>
  <div class="text-md-end"><div class="small opacity-75">Current term</div>
    <div class="fw-semibold"><?= $sem ? e($sem['ay_name'] . ' · ' . $sem['name']) : 'No active semester' ?></div></div>
</div></div>

<?php if ($enr && $enr['status'] === 'rejected'): ?>
  <div class="alert alert-danger"><strong>Your application was rejected.</strong> <?= $enr['remarks'] ? e($enr['remarks']) : '' ?> You can update your subjects and resubmit.</div>
<?php elseif ($enr && $enr['status'] === 'approved'): ?>
  <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>You are enrolled for this semester.</div>
<?php elseif ($sem): ?>
  <div class="alert alert-<?= $open ? 'info' : 'secondary' ?> py-2"><i class="bi bi-calendar-event me-1"></i><?= e($windowMsg) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ([
    ['Enrollment status', ucfirst($status), 'bi-clipboard-check', 'primary'],
    ['Subjects this semester', $subjectCount, 'bi-book', 'info'],
    ['Total units', $units . ($sem ? ' / ' . (int)$sem['max_units'] : ''), 'bi-123', 'success'],
    ['Unread notifications', (int)scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$user['id']]), 'bi-bell', 'warning'],
  ] as [$label, $value, $icon, $color]): ?>
    <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
      <div class="stat-icon bg-<?= $color ?>-subtle text-<?= $color ?>"><i class="bi <?= $icon ?>"></i></div>
      <div><div class="fs-5 fw-bold lh-1"><?= e($value) ?></div><small class="text-muted"><?= e($label) ?></small></div>
    </div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-5"><div class="card h-100"><div class="card-header bg-white fw-semibold">Quick actions</div>
    <div class="list-group list-group-flush">
      <a class="list-group-item list-group-item-action" href="<?= e(url('student/enrollment.php')) ?>"><i class="bi bi-pencil-square me-2 text-primary"></i><?= $enr ? 'View / continue enrollment' : 'Start enrollment' ?></a>
      <a class="list-group-item list-group-item-action" href="<?= e(url('student/enrollment-history.php')) ?>"><i class="bi bi-clock-history me-2 text-primary"></i>Enrollment history</a>
      <a class="list-group-item list-group-item-action" href="<?= e(url('student/documents.php')) ?>"><i class="bi bi-folder2-open me-2 text-primary"></i>Upload documents</a>
      <a class="list-group-item list-group-item-action" href="<?= e(url('student/profile.php')) ?>"><i class="bi bi-person me-2 text-primary"></i>Update my profile</a>
    </div></div></div>
  <div class="col-lg-7"><div class="card h-100"><div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">Recent notifications</span>
      <a href="<?= e(url('student/notifications.php')) ?>" class="small">View all</a></div>
    <?php if (!$notes): ?><div class="empty-state"><i class="bi bi-bell-slash"></i>No notifications yet</div><?php else: ?>
    <div class="list-group list-group-flush"><?php foreach ($notes as $x): ?>
      <div class="list-group-item"><div class="d-flex justify-content-between"><strong class="<?= $x['is_read'] ? '' : 'text-primary' ?>"><?= e($x['title']) ?></strong>
        <small class="text-muted"><?= e(format_date($x['created_at'], 'M d, g:i A')) ?></small></div><div class="small text-muted"><?= e($x['message']) ?></div></div>
    <?php endforeach; ?></div><?php endif; ?></div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
