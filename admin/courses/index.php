<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$user = require_role('admin');
$pageTitle = 'Courses';
$pdo = Database::getConnection();

$q       = clean($_GET['q'] ?? '');
$program = $_GET['program'] ?? '';                       // id, 'general' or ''
$year    = (int)($_GET['year'] ?? 0);
$status  = in_array($_GET['status'] ?? '', ['available', 'unavailable'], true) ? $_GET['status'] : '';

$where = []; $params = [];
if ($q !== '') { $where[] = '(c.code LIKE ? OR c.title LIKE ?)'; array_push($params, "%$q%", "%$q%"); }
if ($program === 'general')      { $where[] = 'c.program_id IS NULL'; }
elseif ((int)$program > 0)       { $where[] = 'c.program_id = ?'; $params[] = (int)$program; }
else                             { $program = ''; }
if ($year)   { $where[] = 'c.year_level = ?'; $params[] = $year; }
if ($status) { $where[] = 'c.status = ?';     $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from  = 'FROM courses c LEFT JOIN programs p ON p.id = c.program_id';
$total = (int)scalar($pdo, "SELECT COUNT(*) $from $whereSql", $params);
$pg    = paginate($total, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare(
    "SELECT c.*, p.code AS program_code,
       (SELECT GROUP_CONCAT(pc.code ORDER BY pc.code SEPARATOR ', ') FROM course_prerequisites cp
          JOIN courses pc ON pc.id = cp.prerequisite_id WHERE cp.course_id = c.id) AS prereq_codes,
       (SELECT COUNT(*) FROM subjects sb WHERE sb.course_id = c.id) AS offering_count
     $from $whereSql ORDER BY c.code LIMIT {$pg['perPage']} OFFSET {$pg['offset']}"
);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// attach prerequisite ids (for the edit modal)
if ($courses) {
    $idList = implode(',', array_map('intval', array_column($courses, 'id')));
    $map = [];
    foreach ($pdo->query("SELECT course_id, prerequisite_id FROM course_prerequisites WHERE course_id IN ($idList)") as $r) {
        $map[$r['course_id']][] = (int)$r['prerequisite_id'];
    }
    foreach ($courses as &$c) { $c['prereq_ids'] = $map[$c['id']] ?? []; }
    unset($c);
}

$programs   = $pdo->query("SELECT id, code, name FROM programs WHERE status = 'active' ORDER BY code")->fetchAll();
$allCourses = $pdo->query('SELECT id, code, title FROM courses ORDER BY code')->fetchAll();
$formState  = $_SESSION['course_form'] ?? null;
unset($_SESSION['course_form']);
$keep = array_filter(['q' => $q, 'program' => $program, 'year' => $year, 'status' => $status]);

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div><h4>Courses</h4><small class="text-muted"><?= number_format($total) ?> course<?= $total === 1 ? '' : 's' ?> in the catalog</small></div>
  <button class="btn btn-primary" id="addBtn"><i class="bi bi-plus-lg me-1"></i>Add course</button>
</div>

<div class="card mb-3"><div class="card-body">
  <form class="row g-2" method="get">
    <div class="col-md-4"><input class="form-control" name="q" placeholder="Search code or title" value="<?= e($q) ?>"></div>
    <div class="col-6 col-md-2"><select class="form-select" name="program"><option value="">All programs</option><option value="general" <?= $program === 'general' ? 'selected' : '' ?>>General</option>
      <?php foreach ($programs as $p): ?><option value="<?= $p['id'] ?>" <?= (string)$p['id'] === (string)$program ? 'selected' : '' ?>><?= e($p['code']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><select class="form-select" name="year"><option value="">All years</option>
      <?php for ($i = 1; $i <= 6; $i++): ?><option value="<?= $i ?>" <?= $year === $i ? 'selected' : '' ?>>Year <?= $i ?></option><?php endfor; ?></select></div>
    <div class="col-6 col-md-2"><select class="form-select" name="status"><option value="">All statuses</option>
      <?php foreach (['available', 'unavailable'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
      <a class="btn btn-light border flex-fill" href="index.php" title="Reset"><i class="bi bi-x-lg"></i></a></div>
  </form>
</div></div>

<div class="card">
  <?php if (!$courses): ?>
    <div class="empty-state"><i class="bi bi-journal-bookmark"></i><?= $keep ? 'No courses match your filters.' : 'No courses yet. Click “Add course” to create one.' ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Code</th><th>Title</th><th>Units</th><th>Program</th><th>Prerequisites</th><th>Offered</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($courses as $c): ?>
      <tr>
        <td class="fw-semibold text-nowrap"><?= e($c['code']) ?></td>
        <td><?= e($c['title']) ?><?php if ($c['year_level']): ?> <small class="text-muted">· Year <?= (int)$c['year_level'] ?></small><?php endif; ?></td>
        <td><?= (int)$c['units'] ?></td>
        <td><?= e($c['program_code'] ?? 'General') ?></td>
        <td><?= $c['prereq_codes'] ? e($c['prereq_codes']) : '<span class="text-muted">None</span>' ?></td>
        <td><span class="badge text-bg-light border"><?= (int)$c['offering_count'] ?></span></td>
        <td><?= status_badge($c['status']) ?></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-secondary edit-btn" title="Edit" data-item="<?= e(json_encode($c, JSON_HEX_TAG | JSON_HEX_AMP)) ?>"><i class="bi bi-pencil"></i></button>
          <?php $avail = $c['status'] === 'available'; ?>
          <form method="post" action="actions.php" class="d-inline"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="toggle">
            <button class="btn btn-sm btn-outline-<?= $avail ? 'warning' : 'success' ?>" title="<?= $avail ? 'Mark unavailable' : 'Mark available' ?>"
                    data-confirm="Mark <?= e($c['code']) ?> as <?= $avail ? 'unavailable' : 'available' ?>?" data-confirm-class="btn-<?= $avail ? 'warning' : 'success' ?>"
                    data-confirm-label="Yes, change"><i class="bi bi-<?= $avail ? 'slash-circle' : 'check-circle' ?>"></i></button></form>
          <form method="post" action="actions.php" class="d-inline"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="delete">
            <button class="btn btn-sm btn-outline-danger" title="Delete" data-confirm="Permanently delete <?= e($c['code']) ?>?" data-confirm-label="Delete"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <small class="text-muted">Page <?= $pg['page'] ?> of <?= $pg['pages'] ?></small><?= render_pagination($pg, $keep) ?></div>
  <?php endif; ?>
</div>

<div class="modal fade" id="entityModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form class="modal-content" method="post" action="actions.php" id="entityForm" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
      <div class="modal-header"><h5 class="modal-title" id="entityTitle">Add course</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php if (!empty($formState['errors'])): ?><div class="alert alert-danger"><ul class="mb-0 ps-3">
          <?php foreach ($formState['errors'] as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Course code *</label><input class="form-control text-uppercase" name="code" maxlength="20" placeholder="e.g. IT104"></div>
          <div class="col-md-8"><label class="form-label">Title *</label><input class="form-control" name="title" maxlength="150"></div>
          <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
          <div class="col-6 col-md-3"><label class="form-label">Units *</label><input class="form-control" type="number" name="units" min="1" max="12"></div>
          <div class="col-6 col-md-3"><label class="form-label">Year level</label><select class="form-select" name="year_level"><option value="">Any</option>
            <?php for ($i = 1; $i <= 6; $i++): ?><option value="<?= $i ?>">Year <?= $i ?></option><?php endfor; ?></select></div>
          <div class="col-md-3"><label class="form-label">Program</label><select class="form-select" name="program_id"><option value="">General (all)</option>
            <?php foreach ($programs as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['code']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Availability</label><select class="form-select" name="status"><option value="available">Available</option><option value="unavailable">Unavailable</option></select></div>
          <div class="col-12"><label class="form-label">Prerequisites</label>
            <div class="border rounded p-2" style="max-height:160px;overflow-y:auto">
              <?php if (!$allCourses): ?><span class="text-muted small">No other courses yet.</span><?php endif; ?>
              <?php foreach ($allCourses as $ac): ?>
                <div class="form-check"><input class="form-check-input prereq-cb" type="checkbox" name="prereq[]" value="<?= $ac['id'] ?>" id="pr<?= $ac['id'] ?>">
                  <label class="form-check-label" for="pr<?= $ac['id'] ?>"><strong><?= e($ac['code']) ?></strong> <span class="text-muted"><?= e($ac['title']) ?></span></label></div>
              <?php endforeach; ?>
            </div><div class="form-text">Students must have passed all selected courses before enrolling.</div></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save course</button></div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => setupEntityModal({
  modalId: 'entityModal', formId: 'entityForm', titleId: 'entityTitle', addBtnId: 'addBtn', editClass: 'edit-btn',
  fields: ['id','code','title','description','units','year_level','program_id','status'],
  defaults: { units: 3, status: 'available' }, titles: ['Add course', 'Edit course'],
  onOpen: (d) => {
    const ids = ((d && (d.prereq_ids || d.prereq)) || []).map(String);
    document.querySelectorAll('.prereq-cb').forEach(cb => {
      cb.checked = ids.includes(cb.value);
      cb.disabled = !!(d && d.id && String(d.id) === cb.value);   // a course can't require itself
    });
  },
  reopen: <?= json_encode($formState['data'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP) ?>
}));
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
