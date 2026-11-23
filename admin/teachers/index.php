<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$user = require_role('admin');
$pageTitle = 'Teachers';
$pdo = Database::getConnection();

$q      = clean($_GET['q'] ?? '');
$deptId = (int)($_GET['department'] ?? 0);
$status = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '';

$where = []; $params = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(t.first_name LIKE ? OR t.last_name LIKE ? OR t.employee_no LIKE ? OR u.email LIKE ? OR CONCAT(t.first_name, ' ', t.last_name) LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($deptId) { $where[] = 't.department_id = ?'; $params[] = $deptId; }
if ($status) { $where[] = 't.status = ?';        $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from  = 'FROM teachers t JOIN users u ON u.id = t.user_id LEFT JOIN departments d ON d.id = t.department_id';
$total = (int)scalar($pdo, "SELECT COUNT(*) $from $whereSql", $params);
$pg    = paginate($total, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare(
    "SELECT t.*, u.username, u.email, d.code AS dept_code,
            (SELECT COUNT(*) FROM subjects sb WHERE sb.teacher_id = t.id) AS subject_count
     $from $whereSql ORDER BY t.last_name, t.first_name LIMIT {$pg['perPage']} OFFSET {$pg['offset']}"
);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

$departments = $pdo->query("SELECT id, code, name FROM departments WHERE status = 'active' ORDER BY name")->fetchAll();
$formState = $_SESSION['teacher_form'] ?? null;
unset($_SESSION['teacher_form']);
$keep = array_filter(['q' => $q, 'department' => $deptId, 'status' => $status]);

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div><h4>Teachers</h4><small class="text-muted"><?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?> found</small></div>
  <button class="btn btn-primary" id="addBtn"><i class="bi bi-plus-lg me-1"></i>Add teacher</button>
</div>

<div class="card mb-3"><div class="card-body">
  <form class="row g-2" method="get">
    <div class="col-md-5"><input class="form-control" name="q" placeholder="Search name, employee no. or email" value="<?= e($q) ?>"></div>
    <div class="col-6 col-md-3"><select class="form-select" name="department"><option value="">All departments</option>
      <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>" <?= $deptId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['code']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><select class="form-select" name="status"><option value="">All statuses</option>
      <?php foreach (['active', 'inactive'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
      <a class="btn btn-light border flex-fill" href="index.php" title="Reset"><i class="bi bi-x-lg"></i></a></div>
  </form>
</div></div>

<div class="card">
  <?php if (!$teachers): ?>
    <div class="empty-state"><i class="bi bi-person-workspace"></i><?= $keep ? 'No teachers match your filters.' : 'No teachers yet. Click “Add teacher” to create one.' ?></div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Teacher</th><th>Employee No.</th><th>Department</th><th>Contact</th><th>Subjects</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($teachers as $t): $full = $t['first_name'] . ' ' . $t['last_name']; ?>
      <tr>
        <td><div class="d-flex align-items-center gap-2">
          <span class="initials"><?= e(strtoupper(mb_substr($t['first_name'], 0, 1) . mb_substr($t['last_name'], 0, 1))) ?></span>
          <div><div class="fw-semibold"><?= e($t['last_name'] . ', ' . $t['first_name']) ?></div><small class="text-muted">@<?= e($t['username']) ?></small></div></div></td>
        <td class="text-nowrap"><?= e($t['employee_no']) ?></td>
        <td><?= e($t['dept_code'] ?? '—') ?></td>
        <td><div><?= e($t['email']) ?></div><small class="text-muted"><?= e($t['phone'] ?: '—') ?></small></td>
        <td><span class="badge text-bg-light border"><?= (int)$t['subject_count'] ?></span></td>
        <td><?= status_badge($t['status']) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$t['id'] ?>" title="View"><i class="bi bi-eye"></i></a>
          <button class="btn btn-sm btn-outline-secondary edit-btn" title="Edit" data-item="<?= e(json_encode($t, JSON_HEX_TAG | JSON_HEX_AMP)) ?>"><i class="bi bi-pencil"></i></button>
          <?php $deact = $t['status'] === 'active'; ?>
          <form method="post" action="actions.php" class="d-inline"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="action" value="toggle">
            <button class="btn btn-sm btn-outline-<?= $deact ? 'warning' : 'success' ?>" title="<?= $deact ? 'Deactivate' : 'Activate' ?>"
                    data-confirm="<?= $deact ? 'Deactivate' : 'Activate' ?> <?= e($full) ?>?" data-confirm-class="btn-<?= $deact ? 'warning' : 'success' ?>"
                    data-confirm-label="<?= $deact ? 'Deactivate' : 'Activate' ?>"><i class="bi bi-<?= $deact ? 'slash-circle' : 'check-circle' ?>"></i></button></form>
          <form method="post" action="actions.php" class="d-inline"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="action" value="delete">
            <button class="btn btn-sm btn-outline-danger" title="Delete" data-confirm="Permanently delete <?= e($full) ?>? This cannot be undone."
                    data-confirm-label="Delete"><i class="bi bi-trash"></i></button></form>
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
      <div class="modal-header"><h5 class="modal-title" id="entityTitle">Add teacher</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php if (!empty($formState['errors'])): ?><div class="alert alert-danger"><ul class="mb-0 ps-3">
          <?php foreach ($formState['errors'] as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">First name *</label><input class="form-control" name="first_name" maxlength="60"></div>
          <div class="col-md-4"><label class="form-label">Middle name</label><input class="form-control" name="middle_name" maxlength="60"></div>
          <div class="col-md-4"><label class="form-label">Last name *</label><input class="form-control" name="last_name" maxlength="60"></div>
          <div class="col-md-4"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="">—</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
          <div class="col-md-4"><label class="form-label">Birth date</label><input class="form-control" type="date" name="birth_date" max="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="30"></div>
          <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" maxlength="255"></div>
          <div class="col-md-8"><label class="form-label">Department</label><select class="form-select" name="department_id"><option value="">— None —</option>
            <?php foreach ($departments as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['code'] . ' — ' . $d['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
          <hr class="my-1">
          <div class="col-md-4"><label class="form-label">Username *</label><input class="form-control" name="username" maxlength="30"></div>
          <div class="col-md-4"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" maxlength="120"></div>
          <div class="col-md-4"><label class="form-label">Password <span id="pwHint">*</span></label><input class="form-control" type="password" name="password" autocomplete="new-password">
            <div class="form-text" id="pwHelp">8+ characters, letter and number.</div></div>
        </div>
        <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>The employee number is generated automatically. Assign subjects to teachers under Subjects.</p>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save teacher</button></div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => setupEntityModal({
  modalId: 'entityModal', formId: 'entityForm', titleId: 'entityTitle', addBtnId: 'addBtn', editClass: 'edit-btn',
  fields: ['id','first_name','middle_name','last_name','gender','birth_date','phone','address','department_id','status','username','email'],
  defaults: { status: 'active' }, titles: ['Add teacher', 'Edit teacher'],
  onOpen: (d, isEdit) => {
    document.getElementById('pwHint').textContent = isEdit ? '(optional)' : '*';
    document.getElementById('pwHelp').textContent = isEdit ? 'Leave blank to keep the current password.' : '8+ characters, letter and number.';
  },
  reopen: <?= json_encode($formState['data'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP) ?>
}));
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
