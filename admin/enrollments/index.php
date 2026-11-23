<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$user = require_role('admin');
$pageTitle = 'Enrollments';
$pdo = Database::getConnection();

$activeSem = get_active_semester($pdo);
$semParam  = $_GET['semester'] ?? ($activeSem ? (string)$activeSem['id'] : 'all');
$semId     = ctype_digit((string)$semParam) ? (int)$semParam : 0;
$tab       = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected', 'cancelled'], true) ? $_GET['status'] : 'all';
$q         = clean($_GET['q'] ?? '');

// counts for the tabs (respect semester + search)
$base = "FROM enrollments e JOIN students s ON s.id = e.student_id JOIN programs p ON p.id = s.program_id
         JOIN semesters sem ON sem.id = e.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
         WHERE e.status <> 'draft'";
$bp = [];
if ($semId) { $base .= ' AND e.semester_id = ?'; $bp[] = $semId; }
if ($q !== '') {
    $base .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_no LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
    array_push($bp, "%$q%", "%$q%", "%$q%", "%$q%");
}
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0];
$c = $pdo->prepare("SELECT e.status, COUNT(*) n $base GROUP BY e.status"); $c->execute($bp);
foreach ($c->fetchAll() as $r) $counts[$r['status']] = (int)$r['n'];

$where = $base; $params = $bp;
if ($tab !== 'all') { $where .= ' AND e.status = ?'; $params[] = $tab; }
$total = (int)scalar($pdo, "SELECT COUNT(*) $where", $params);
$pg    = paginate($total, (int)($_GET['page'] ?? 1));
$stmt  = $pdo->prepare(
    "SELECT e.*, s.student_no, s.first_name, s.last_name, s.year_level, p.code AS program_code,
            sem.name AS sem_name, ay.name AS ay_name,
            (SELECT COUNT(*) FROM enrollment_details d WHERE d.enrollment_id = e.id) AS subject_count
     $where ORDER BY (e.status = 'pending') DESC, e.submitted_at DESC, e.id DESC
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$semesters = $pdo->query('SELECT s.id, s.name, ay.name AS ay_name, s.is_active FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id
                          ORDER BY ay.start_date DESC, s.semester_no DESC')->fetchAll();
$keep = array_filter(['semester' => $semParam, 'q' => $q, 'status' => $tab === 'all' ? '' : $tab]);
$tabLink = fn(string $st) => '?' . http_build_query(array_filter(['semester' => $semParam, 'q' => $q, 'status' => $st === 'all' ? '' : $st]));

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div><h4>Enrollment Applications</h4><small class="text-muted">Review, approve or reject student applications</small></div></div>

<div class="card mb-3"><div class="card-body">
  <form class="row g-2" method="get">
    <?php if ($tab !== 'all'): ?><input type="hidden" name="status" value="<?= e($tab) ?>"><?php endif; ?>
    <div class="col-md-4"><select class="form-select" name="semester"><option value="all">All semesters</option>
      <?php foreach ($semesters as $s): ?><option value="<?= $s['id'] ?>" <?= $semId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['ay_name'] . ' · ' . $s['name']) ?><?= $s['is_active'] ? ' (active)' : '' ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><input class="form-control" name="q" placeholder="Search student name or number" value="<?= e($q) ?>"></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
      <a class="btn btn-light border flex-fill" href="index.php?semester=all" title="Reset"><i class="bi bi-x-lg"></i></a></div>
  </form>
</div></div>

<ul class="nav nav-pills mb-3 gap-1">
  <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $k => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : 'bg-white border' ?>" href="<?= e($tabLink($k)) ?>"><?= $label ?>
      <span class="badge <?= $tab === $k ? 'text-bg-light' : 'text-bg-secondary' ?> ms-1"><?= $k === 'all' ? array_sum($counts) : $counts[$k] ?></span></a></li>
  <?php endforeach; ?>
</ul>

<div class="card">
  <?php if (!$rows): ?><div class="empty-state"><i class="bi bi-clipboard-check"></i>No applications found.</div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Student</th><th>Program</th><th>Semester</th><th>Subjects</th><th>Units</th><th>Submitted</th><th>Status</th><th class="text-end">Action</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><div class="fw-semibold"><?= e($r['last_name'] . ', ' . $r['first_name']) ?></div><small class="text-muted"><?= e($r['student_no']) ?></small></td>
        <td><?= e($r['program_code']) ?> · Yr <?= (int)$r['year_level'] ?></td>
        <td class="text-nowrap small"><?= e($r['ay_name']) ?><br><?= e($r['sem_name']) ?></td>
        <td><?= (int)$r['subject_count'] ?></td><td><?= (int)$r['total_units'] ?></td>
        <td class="text-nowrap small"><?= $r['submitted_at'] ? e(format_date($r['submitted_at'], 'M d, Y g:i A')) : '—' ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td class="text-end"><a class="btn btn-sm <?= $r['status'] === 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>" href="view.php?id=<?= (int)$r['id'] ?>">
          <?= $r['status'] === 'pending' ? 'Review' : 'View' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <small class="text-muted">Page <?= $pg['page'] ?> of <?= $pg['pages'] ?></small><?= render_pagination($pg, $keep) ?></div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
