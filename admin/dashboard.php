<?php
require_once __DIR__ . '/../includes/auth_check.php';
$user = require_role('admin');
$pageTitle = 'Admin Dashboard';

$pdo   = Database::getConnection();
$sem   = get_active_semester($pdo);
$semId = (int)($sem['id'] ?? 0);

$byStatus = fn(string $s) => (int)scalar($pdo, 'SELECT COUNT(*) FROM enrollments WHERE semester_id = ? AND status = ?', [$semId, $s]);

$stats = [
  ['Total Students',      (int)scalar($pdo, 'SELECT COUNT(*) FROM students'),                              'bi-people',          'primary', 'admin/students/index.php'],
  ['Total Teachers',      (int)scalar($pdo, 'SELECT COUNT(*) FROM teachers'),                              'bi-person-workspace','info',    'admin/teachers/index.php'],
  ['Enrolled Students',   (int)scalar($pdo, "SELECT COUNT(DISTINCT student_id) FROM enrollments WHERE semester_id = ? AND status = 'approved'", [$semId]), 'bi-mortarboard', 'success', 'admin/enrollments/index.php'],
  ['Available Courses',   (int)scalar($pdo, "SELECT COUNT(*) FROM courses WHERE status = 'available'"),    'bi-journal-bookmark','secondary','admin/courses/index.php'],
  ['Pending Applications',$byStatus('pending'),                                                            'bi-hourglass-split', 'warning', 'admin/enrollments/index.php'],
  ['Approved Applications',$byStatus('approved'),                                                          'bi-check-circle',    'success', 'admin/enrollments/index.php'],
  ['Rejected Applications',$byStatus('rejected'),                                                          'bi-x-circle',        'danger',  'admin/enrollments/index.php'],
];

// Chart data
$statusData = [$byStatus('pending'), $byStatus('approved'), $byStatus('rejected'), $byStatus('cancelled')];
$stmt = $pdo->prepare(
  "SELECT p.code, COUNT(e.id) AS total
   FROM programs p
   LEFT JOIN students st ON st.program_id = p.id
   LEFT JOIN enrollments e ON e.student_id = st.id AND e.semester_id = ? AND e.status = 'approved'
   GROUP BY p.id, p.code ORDER BY p.code"
);
$stmt->execute([$semId]);
$programRows = $stmt->fetchAll();

$recent = $pdo->query(
  'SELECT a.action, a.description, a.created_at, u.username
   FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 8'
)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <h4>Dashboard</h4>
    <small class="text-muted">
      <?= $sem ? e($sem['ay_name'] . ' · ' . $sem['name']) . ' (active semester)' : 'No active semester. Set one under Academic.' ?>
    </small>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php foreach ($stats as [$label, $value, $icon, $color, $path]): ?>
    <div class="col-6 col-md-4 col-xl-3">
      <a href="<?= e(page_url($path)) ?>" class="text-decoration-none">
        <div class="card stat-card h-100"><div class="card-body d-flex align-items-center gap-3">
          <div class="stat-icon bg-<?= $color ?>-subtle text-<?= $color ?>"><i class="bi <?= $icon ?>"></i></div>
          <div><div class="fs-4 fw-bold text-dark lh-1"><?= number_format($value) ?></div>
               <small class="text-muted"><?= e($label) ?></small></div>
        </div></div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-body">
      <h6 class="fw-semibold mb-3">Applications by status</h6>
      <?php if (array_sum($statusData) === 0): ?>
        <div class="empty-state"><i class="bi bi-pie-chart"></i>No applications this semester yet</div>
      <?php else: ?><div class="chart-box"><canvas id="statusChart"></canvas></div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100"><div class="card-body">
      <h6 class="fw-semibold mb-3">Enrolled students per program</h6>
      <?php if (!$programRows): ?>
        <div class="empty-state"><i class="bi bi-bar-chart"></i>No programs yet</div>
      <?php else: ?><div class="chart-box"><canvas id="programChart"></canvas></div><?php endif; ?>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-header bg-white fw-semibold">Recent activities</div>
  <?php if (!$recent): ?>
    <div class="empty-state"><i class="bi bi-activity"></i>No activity recorded yet</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="text-nowrap"><?= e(format_date($r['created_at'], 'M d, g:i A')) ?></td>
          <td><?= e($r['username'] ?? 'system') ?></td>
          <td><span class="badge text-bg-light border"><?= e($r['action']) ?></span></td>
          <td class="text-muted"><?= e($r['description']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const statusEl = document.getElementById('statusChart');
if (statusEl) new Chart(statusEl, {
  type: 'doughnut',
  data: { labels: ['Pending', 'Approved', 'Rejected', 'Cancelled'],
          datasets: [{ data: <?= json_encode($statusData) ?>, backgroundColor: ['#F59E0B', '#16A34A', '#DC2626', '#94A3B8'] }] },
  options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
});
const programEl = document.getElementById('programChart');
if (programEl) new Chart(programEl, {
  type: 'bar',
  data: { labels: <?= json_encode(array_column($programRows, 'code'), JSON_HEX_TAG) ?>,
          datasets: [{ label: 'Enrolled students', data: <?= json_encode(array_map('intval', array_column($programRows, 'total'))) ?>,
                       backgroundColor: '#2563EB', borderRadius: 6 }] },
  options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
             scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
