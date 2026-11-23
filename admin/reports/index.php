<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/enrollment.php';
require_once __DIR__ . '/../../includes/reports.php';
require_once __DIR__ . '/../../includes/csv.php';
$user = require_role('admin');
$pdo = Database::getConnection();

$types = report_types();
$type = array_key_exists($_GET['report'] ?? '', $types) ? $_GET['report'] : 'enrollment';
$activeSem = get_active_semester($pdo);
$semId = (int)($_GET['semester'] ?? ($activeSem['id'] ?? 0));
$programId = $type === 'students' ? (int)($_GET['program'] ?? 0) : 0;

$semesters = $pdo->query('SELECT s.id, s.name, ay.name AS ay_name, s.is_active FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id ORDER BY ay.start_date DESC, s.semester_no DESC')->fetchAll();
$programs  = $pdo->query('SELECT id, code FROM programs ORDER BY code')->fetchAll();
$semLabel  = '';
foreach ($semesters as $s) if ((int)$s['id'] === $semId) $semLabel = $s['ay_name'] . ' · ' . $s['name'];

[$title, $headers, $rows] = build_report($pdo, $type, $semId, $programId);

if (($_GET['export'] ?? '') === 'csv') {
    log_activity('report_exported', "$title ($semLabel)");
    send_csv(strtolower(str_replace(' ', '-', $title)) . '-' . date('Ymd') . '.csv', $headers, $rows);
}
$q = ['report' => $type, 'semester' => $semId, 'program' => $programId ?: ''];
$pageTitle = 'Reports';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header no-print"><div><h4>Reports</h4><small class="text-muted">Generate, print or export</small></div>
  <div class="d-flex gap-2"><a class="btn btn-outline-primary" href="?<?= e(http_build_query(array_filter($q) + ['export' => 'csv'])) ?>"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel (CSV)</a>
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button></div></div>

<ul class="nav nav-pills mb-3 gap-1 no-print"><?php foreach ($types as $k => $l): ?>
  <li class="nav-item"><a class="nav-link <?= $type === $k ? 'active' : 'bg-white border' ?>" href="?<?= e(http_build_query(['report' => $k, 'semester' => $semId])) ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul>

<div class="card mb-3 no-print"><div class="card-body"><form class="row g-2" method="get"><input type="hidden" name="report" value="<?= e($type) ?>">
  <div class="col-md-5"><select class="form-select" name="semester"><?php foreach ($semesters as $s): ?><option value="<?= $s['id'] ?>" <?= $semId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['ay_name'] . ' · ' . $s['name']) ?><?= $s['is_active'] ? ' (active)' : '' ?></option><?php endforeach; ?></select></div>
  <?php if ($type === 'students'): ?><div class="col-md-3"><select class="form-select" name="program"><option value="">All programs</option><?php foreach ($programs as $p): ?><option value="<?= $p['id'] ?>" <?= $programId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['code']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="col-md-2"><button class="btn btn-primary w-100">Generate</button></div></form></div></div>

<div class="card"><div class="card-body pb-0">
  <h5 class="text-navy mb-0"><?= e(setting('university_name', APP_NAME)) ?></h5>
  <div class="fw-semibold"><?= e($title) ?></div><div class="small text-muted mb-3"><?= e($semLabel) ?> · Generated <?= e(date('M d, Y g:i A')) ?> by <?= e($user['name']) ?></div></div>
  <?php if (!$rows): ?><div class="empty-state"><i class="bi bi-bar-chart"></i>No data for this report.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach ($rows as $i => $r): $last = $type === 'enrollment' && $i === count($rows) - 1; ?><tr class="<?= $last ? 'fw-bold table-light' : '' ?>"><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white small text-muted"><?= count($rows) - ($type === 'enrollment' ? 1 : 0) ?> row(s)</div><?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
