<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings_nav.php';
$user = require_role('admin');
$pageTitle = 'Academic Management';
$pdo = Database::getConnection();

$tab = in_array($_GET['tab'] ?? '', ['years', 'departments', 'programs'], true) ? $_GET['tab'] : 'years';
$fs  = $_SESSION['academic_form'] ?? null;
unset($_SESSION['academic_form']);
if ($fs) $tab = $fs['tab'];

$years = $pdo->query('SELECT * FROM academic_years ORDER BY start_date DESC')->fetchAll();
$sems = [];
foreach ($pdo->query('SELECT s.*, (SELECT COUNT(*) FROM subjects sb WHERE sb.semester_id = s.id) AS subject_count,
                      (SELECT COUNT(*) FROM enrollments e WHERE e.semester_id = s.id AND e.status <> "draft") AS enrollment_count
                      FROM semesters s ORDER BY s.semester_no') as $r) $sems[$r['academic_year_id']][] = $r;
$departments = $pdo->query('SELECT d.*, (SELECT COUNT(*) FROM programs p WHERE p.department_id = d.id) AS program_count,
                            (SELECT COUNT(*) FROM teachers t WHERE t.department_id = d.id) AS teacher_count FROM departments d ORDER BY d.name')->fetchAll();
$programs = $pdo->query('SELECT p.*, d.code AS dept_code, (SELECT COUNT(*) FROM students s WHERE s.program_id = p.id) AS student_count
                         FROM programs p JOIN departments d ON d.id = p.department_id ORDER BY p.code')->fetchAll();
$activeDepts = array_filter($departments, fn($d) => $d['status'] === 'active');

$errBox = function (string $entity) use ($fs) {
    if (!$fs || $fs['entity'] !== $entity) return '';
    return '<div class="alert alert-danger"><ul class="mb-0 ps-3">' . implode('', array_map(fn($x) => '<li>' . e($x) . '</li>', $fs['errors'])) . '</ul></div>';
};
$reopen = fn(string $entity) => ($fs && $fs['entity'] === $entity) ? $fs['data'] : null;
$post = fn(string $entity, string $action, int $id, string $label, string $msg, string $cls = 'btn-outline-danger', string $icon = 'trash', string $confirmLabel = 'Delete') =>
    '<form method="post" action="academic_actions.php" class="d-inline">' . csrf_field() . '<input type="hidden" name="entity" value="' . $entity . '"><input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="id" value="' . $id . '">'
    . '<button class="btn btn-sm ' . $cls . '" title="' . e($label) . '" data-confirm="' . e($msg) . '" data-confirm-label="' . e($confirmLabel) . '"><i class="bi bi-' . $icon . '"></i></button></form>';

require_once __DIR__ . '/../../includes/header.php';
settings_nav('academic');
?>
<ul class="nav nav-tabs mb-3">
  <?php foreach (['years' => 'Academic years & semesters', 'departments' => 'Departments', 'programs' => 'Programs'] as $k => $l): ?>
    <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?>
</ul>

<?php if ($tab === 'years'): ?>
  <div class="d-flex justify-content-between align-items-center mb-3"><span class="text-muted">The active semester controls what students see on the enrollment page.</span>
    <button class="btn btn-primary" id="addYearBtn"><i class="bi bi-plus-lg me-1"></i>Add academic year</button></div>
  <?php if (!$years): ?><div class="card"><div class="empty-state"><i class="bi bi-calendar3"></i>No academic years yet.</div></div><?php endif; ?>
  <?php foreach ($years as $y): $list = $sems[$y['id']] ?? []; ?>
    <div class="card mb-3"><div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div><span class="fw-bold text-navy fs-5"><?= e($y['name']) ?></span> <?= $y['is_active'] ? '<span class="badge text-bg-success ms-1">Active</span>' : '' ?>
        <div class="small text-muted"><?= e(format_date($y['start_date']) . ' – ' . format_date($y['end_date'])) ?></div></div>
      <div class="d-flex gap-1">
        <button class="btn btn-sm btn-outline-primary add-sem-btn" data-year="<?= (int)$y['id'] ?>" data-next="<?= min(3, count($list) + 1) ?>"><i class="bi bi-plus-lg"></i> Semester</button>
        <button class="btn btn-sm btn-outline-secondary edit-year" data-item="<?= e(json_encode($y)) ?>"><i class="bi bi-pencil"></i></button>
        <?= $post('year', 'delete', (int)$y['id'], 'Delete year', 'Delete academic year ' . $y['name'] . '?') ?></div></div>
      <?php if (!$list): ?><div class="p-3 text-muted small">No semesters yet.</div><?php else: ?>
      <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Semester</th><th>Dates</th><th>Enrollment period</th><th>Max units</th><th>Subjects</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
      <?php foreach ($list as $s): ?><tr>
        <td class="fw-semibold"><?= e($s['name']) ?></td><td class="small text-nowrap"><?= e(format_date($s['start_date']) . ' – ' . format_date($s['end_date'])) ?></td>
        <td class="small text-nowrap"><?= $s['enrollment_start'] ? e(format_date($s['enrollment_start']) . ' – ' . format_date($s['enrollment_end'])) : '<span class="text-muted">Not set (always open)</span>' ?></td>
        <td><?= (int)$s['max_units'] ?></td><td><?= (int)$s['subject_count'] ?></td>
        <td><?= $s['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-light border">Inactive</span>' ?></td>
        <td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary edit-sem" data-item="<?= e(json_encode($s)) ?>"><i class="bi bi-pencil"></i></button>
          <?php if (!$s['is_active']) echo $post('semester', 'activate', (int)$s['id'], 'Set active', 'Make ' . $s['name'] . ' ' . $y['name'] . ' the active semester? Students will enroll in this term.', 'btn-outline-success', 'toggle-on', 'Set active'); ?>
          <?= $post('semester', 'delete', (int)$s['id'], 'Delete', 'Delete ' . $s['name'] . ' ' . $y['name'] . '?') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
  <?php endforeach; ?>

<?php elseif ($tab === 'departments'): ?>
  <div class="d-flex justify-content-end mb-3"><button class="btn btn-primary" id="addDeptBtn"><i class="bi bi-plus-lg me-1"></i>Add department</button></div>
  <div class="card"><?php if (!$departments): ?><div class="empty-state"><i class="bi bi-building"></i>No departments yet.</div><?php else: ?>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Code</th><th>Name</th><th>Programs</th><th>Teachers</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($departments as $d): ?><tr><td class="fw-semibold"><?= e($d['code']) ?></td><td><?= e($d['name']) ?><div class="small text-muted"><?= e($d['description']) ?></div></td>
      <td><?= (int)$d['program_count'] ?></td><td><?= (int)$d['teacher_count'] ?></td><td><?= status_badge($d['status']) ?></td>
      <td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary edit-dept" data-item="<?= e(json_encode($d)) ?>"><i class="bi bi-pencil"></i></button>
        <?= $post('department', 'delete', (int)$d['id'], 'Delete', 'Delete department ' . $d['code'] . '?') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>

<?php else: ?>
  <div class="d-flex justify-content-end mb-3"><button class="btn btn-primary" id="addProgBtn"><i class="bi bi-plus-lg me-1"></i>Add program</button></div>
  <div class="card"><?php if (!$programs): ?><div class="empty-state"><i class="bi bi-diagram-3"></i>No programs yet.</div><?php else: ?>
    <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Code</th><th>Program</th><th>Department</th><th>Year levels</th><th>Students</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($programs as $p): ?><tr><td class="fw-semibold"><?= e($p['code']) ?></td><td><?= e($p['name']) ?></td><td><?= e($p['dept_code']) ?></td><td><?= (int)$p['duration_years'] ?></td>
      <td><?= (int)$p['student_count'] ?></td><td><?= status_badge($p['status']) ?></td>
      <td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary edit-prog" data-item="<?= e(json_encode($p)) ?>"><i class="bi bi-pencil"></i></button>
        <?= $post('program', 'delete', (int)$p['id'], 'Delete', 'Delete program ' . $p['code'] . '?') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php endif; ?>

<?php
$modal = function (string $id, string $entity, string $title, string $fields) use ($errBox) {
    echo '<div class="modal fade" id="' . $id . '" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" method="post" action="academic_actions.php" id="' . $id . 'Form" novalidate>'
       . csrf_field() . '<input type="hidden" name="action" value="save"><input type="hidden" name="entity" value="' . $entity . '"><input type="hidden" name="id" value="">'
       . '<div class="modal-header"><h5 class="modal-title" id="' . $id . 'Title">' . $title . '</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
       . '<div class="modal-body">' . $errBox($entity) . '<div class="row g-3">' . $fields . '</div></div>'
       . '<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div></form></div></div>';
};
$f = fn($label, $name, $type = 'text', $col = 12, $extra = '') => '<div class="col-md-' . $col . '"><label class="form-label">' . $label . '</label><input class="form-control" type="' . $type . '" name="' . $name . '" ' . $extra . '></div>';
$sel = fn($label, $name, $opts, $col = 12) => '<div class="col-md-' . $col . '"><label class="form-label">' . $label . '</label><select class="form-select" name="' . $name . '">' . $opts . '</select></div>';

$modal('yearModal', 'year', 'Add academic year', $f('Name (e.g. 2027-2028) *', 'name', 'text', 12, 'maxlength="9"') . $f('Start date *', 'start_date', 'date', 6) . $f('End date *', 'end_date', 'date', 6));
$modal('semModal', 'semester', 'Add semester',
    '<input type="hidden" name="academic_year_id">' . $f('Name *', 'name', 'text', 8, 'maxlength="50" placeholder="e.g. First Semester"') . $f('Number *', 'semester_no', 'number', 4, 'min="1" max="3"')
    . $f('Starts *', 'start_date', 'date', 6) . $f('Ends *', 'end_date', 'date', 6)
    . $f('Enrollment opens', 'enrollment_start', 'date', 6) . $f('Enrollment closes', 'enrollment_end', 'date', 6)
    . $f('Max units per student *', 'max_units', 'number', 6, 'min="1" max="40"') . '<div class="col-12 small text-muted">Leave the enrollment dates empty to keep enrollment always open while this semester is active.</div>');
$deptOpts = '<option value="">Select…</option>' . implode('', array_map(fn($d) => '<option value="' . $d['id'] . '">' . e($d['code'] . ' — ' . $d['name']) . '</option>', $activeDepts));
$stat = '<option value="active">Active</option><option value="inactive">Inactive</option>';
$modal('deptModal', 'department', 'Add department', $f('Code *', 'code', 'text', 4, 'maxlength="20"') . $f('Name *', 'name', 'text', 8, 'maxlength="150"')
    . '<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>' . $sel('Status', 'status', $stat));
$modal('progModal', 'program', 'Add program', $sel('Department *', 'department_id', $deptOpts) . $f('Code *', 'code', 'text', 4, 'maxlength="20"') . $f('Name *', 'name', 'text', 8, 'maxlength="150"')
    . $f('Year levels *', 'duration_years', 'number', 6, 'min="1" max="6"') . $sel('Status', 'status', $stat, 6));
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const mk = (id, fields, defaults, titles, edit, add) => setupEntityModal({ modalId: id, formId: id + 'Form', titleId: id + 'Title', addBtnId: add || 'none', editClass: edit,
      fields, defaults, titles, reopen: null });
  <?php if ($tab === 'years'): ?>
  const ry = <?= json_encode($reopen('year')) ?>, rs = <?= json_encode($reopen('semester')) ?>;
  const y = mk('yearModal', ['id', 'name', 'start_date', 'end_date'], {}, ['Add academic year', 'Edit academic year'], 'edit-year', 'addYearBtn');
  const s = mk('semModal', ['id', 'academic_year_id', 'name', 'semester_no', 'start_date', 'end_date', 'enrollment_start', 'enrollment_end', 'max_units'], { max_units: 24 }, ['Add semester', 'Edit semester'], 'edit-sem');
  document.querySelectorAll('.add-sem-btn').forEach(b => b.addEventListener('click', () => s.open({ academic_year_id: b.dataset.year, semester_no: b.dataset.next })));
  if (ry) y.open(ry); if (rs) s.open(rs);
  <?php elseif ($tab === 'departments'): ?>
  const d = mk('deptModal', ['id', 'code', 'name', 'description', 'status'], { status: 'active' }, ['Add department', 'Edit department'], 'edit-dept', 'addDeptBtn');
  const rd = <?= json_encode($reopen('department')) ?>; if (rd) d.open(rd);
  <?php else: ?>
  const p = mk('progModal', ['id', 'department_id', 'code', 'name', 'duration_years', 'status'], { status: 'active', duration_years: 4 }, ['Add program', 'Edit program'], 'edit-prog', 'addProgBtn');
  const rp = <?= json_encode($reopen('program')) ?>; if (rp) p.open(rp);
  <?php endif; ?>
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
