<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$user = require_role('admin');
$pageTitle = 'Students';
$pdo = Database::getConnection();

// ---- filters ----
$q         = clean($_GET['q'] ?? '');
$programId = (int)($_GET['program'] ?? 0);
$year      = (int)($_GET['year'] ?? 0);
$status    = in_array($_GET['status'] ?? '', ['active', 'inactive', 'graduated'], true) ? $_GET['status'] : '';

$where = []; $params = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_no LIKE ? OR u.email LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
    array_push($params, $like, $like, $like, $like, $like);
}
if ($programId) { $where[] = 's.program_id = ?'; $params[] = $programId; }
if ($year)      { $where[] = 's.year_level = ?'; $params[] = $year; }
if ($status)    { $where[] = 's.status = ?';     $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from  = 'FROM students s JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id';
$total = (int)scalar($pdo, "SELECT COUNT(*) $from $whereSql", $params);
$pg    = paginate($total, (int)($_GET['page'] ?? 1));

$stmt = $pdo->prepare(
    "SELECT s.*, u.username, u.email, p.code AS program_code $from $whereSql
     ORDER BY s.last_name, s.first_name LIMIT {$pg['perPage']} OFFSET {$pg['offset']}"
);
$stmt->execute($params);
$students = $stmt->fetchAll();

$programs  = $pdo->query("SELECT id, code, name FROM programs WHERE status = 'active' ORDER BY code")->fetchAll();
$formState = $_SESSION['student_form'] ?? null;   // set by actions.php when validation fails
unset($_SESSION['student_form']);
$keep = array_filter(['q' => $q, 'program' => $programId, 'year' => $year, 'status' => $status]);

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div><h4>Students</h4><small class="text-muted"><?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?> found</small></div>
  <button class="btn btn-primary" id="addStudentBtn"><i class="bi bi-plus-lg me-1"></i>Add student</button>
</div>

<div class="card mb-3"><div class="card-body">
  <form class="row g-2" method="get">
    <div class="col-md-4"><input class="form-control" name="q" placeholder="Search name, student no. or email" value="<?= e($q) ?>"></div>
    <div class="col-6 col-md-2">
      <select class="form-select" name="program"><option value="">All programs</option>
        <?php foreach ($programs as $p): ?><option value="<?= $p['id'] ?>" <?= $programId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['code']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-6 col-md-2">
      <select class="form-select" name="year"><option value="">All years</option>
        <?php for ($i = 1; $i <= 6; $i++): ?><option value="<?= $i ?>" <?= $year === $i ? 'selected' : '' ?>>Year <?= $i ?></option><?php endfor; ?>
      </select></div>
    <div class="col-6 col-md-2">
      <select class="form-select" name="status"><option value="">All statuses</option>
        <?php foreach (['active', 'inactive', 'graduated'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-6 col-md-2 d-flex gap-2">
      <button class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
      <a class="btn btn-light border flex-fill" href="index.php" title="Reset"><i class="bi bi-x-lg"></i></a>
    </div>
  </form>
</div></div>

<div class="card">
  <?php if (!$students): ?>
    <div class="empty-state"><i class="bi bi-people"></i>
      <?= $keep ? 'No students match your filters.' : 'No students yet. Click “Add student” to create one.' ?></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Student</th><th>Student No.</th><th>Program</th><th>Contact</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($students as $s): $full = $s['first_name'] . ' ' . $s['last_name']; ?>
        <tr>
          <td><div class="d-flex align-items-center gap-2">
            <span class="initials"><?= e(strtoupper(mb_substr($s['first_name'], 0, 1) . mb_substr($s['last_name'], 0, 1))) ?></span>
            <div><div class="fw-semibold"><?= e($s['last_name'] . ', ' . $s['first_name']) ?></div>
                 <small class="text-muted">@<?= e($s['username']) ?></small></div></div></td>
          <td class="text-nowrap"><?= e($s['student_no']) ?></td>
          <td><?= e($s['program_code']) ?> <span class="text-muted">· Year <?= (int)$s['year_level'] ?></span></td>
          <td><div><?= e($s['email']) ?></div><small class="text-muted"><?= e($s['phone'] ?: '—') ?></small></td>
          <td><?= status_badge($s['status']) ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$s['id'] ?>" title="View"><i class="bi bi-eye"></i></a>
            <button class="btn btn-sm btn-outline-secondary edit-btn" title="Edit"
                    data-student="<?= e(json_encode($s, JSON_HEX_TAG | JSON_HEX_AMP)) ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" action="actions.php" class="d-inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <?php $deact = $s['status'] !== 'inactive'; ?>
              <input type="hidden" name="action" value="toggle">
              <button class="btn btn-sm btn-outline-<?= $deact ? 'warning' : 'success' ?>" title="<?= $deact ? 'Deactivate' : 'Activate' ?>"
                      data-confirm="<?= $deact ? 'Deactivate' : 'Activate' ?> <?= e($full) ?>?"
                      data-confirm-class="btn-<?= $deact ? 'warning' : 'success' ?>" data-confirm-label="<?= $deact ? 'Deactivate' : 'Activate' ?>">
                <i class="bi bi-<?= $deact ? 'slash-circle' : 'check-circle' ?>"></i></button>
            </form>
            <form method="post" action="actions.php" class="d-inline">
              <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="delete">
              <button class="btn btn-sm btn-outline-danger" title="Delete"
                      data-confirm="Permanently delete <?= e($full) ?>? This cannot be undone." data-confirm-label="Delete">
                <i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <small class="text-muted">Page <?= $pg['page'] ?> of <?= $pg['pages'] ?></small>
    <?= render_pagination($pg, $keep) ?>
  </div>
  <?php endif; ?>
</div>

<!-- Add / Edit modal -->
<div class="modal fade" id="studentModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <form class="modal-content" method="post" action="actions.php" id="studentForm" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="f_id" value="">
      <div class="modal-header"><h5 class="modal-title" id="studentModalTitle">Add student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php if (!empty($formState['errors'])): ?>
          <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($formState['errors'] as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">First name *</label><input class="form-control" name="first_name" id="f_first_name" required maxlength="60"></div>
          <div class="col-md-4"><label class="form-label">Middle name</label><input class="form-control" name="middle_name" id="f_middle_name" maxlength="60"></div>
          <div class="col-md-4"><label class="form-label">Last name *</label><input class="form-control" name="last_name" id="f_last_name" required maxlength="60"></div>
          <div class="col-md-4"><label class="form-label">Gender</label>
            <select class="form-select" name="gender" id="f_gender"><option value="">—</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
          <div class="col-md-4"><label class="form-label">Birth date</label><input class="form-control" type="date" name="birth_date" id="f_birth_date" max="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" id="f_phone" maxlength="30"></div>
          <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" id="f_address" maxlength="255"></div>
          <div class="col-md-6"><label class="form-label">Program *</label>
            <select class="form-select" name="program_id" id="f_program_id" required><option value="">Select…</option>
              <?php foreach ($programs as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['code'] . ' — ' . $p['name']) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-3"><label class="form-label">Year level *</label>
            <select class="form-select" name="year_level" id="f_year_level"><?php for ($i = 1; $i <= 6; $i++): ?><option value="<?= $i ?>">Year <?= $i ?></option><?php endfor; ?></select></div>
          <div class="col-md-3"><label class="form-label">Status</label>
            <select class="form-select" name="status" id="f_status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="graduated">Graduated</option></select></div>
          <hr class="my-1">
          <div class="col-md-4"><label class="form-label">Username *</label><input class="form-control" name="username" id="f_username" required maxlength="30"></div>
          <div class="col-md-4"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" id="f_email" required maxlength="120"></div>
          <div class="col-md-4"><label class="form-label">Password <span id="pwHint">*</span></label>
            <input class="form-control" type="password" name="password" id="f_password" autocomplete="new-password">
            <div class="form-text" id="pwHelp">8+ characters, letter and number.</div></div>
        </div>
        <p class="text-muted small mt-3 mb-0" id="studentNoNote"><i class="bi bi-info-circle me-1"></i>The student number is generated automatically.</p>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save student</button></div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('studentModal');
  const modal = new bootstrap.Modal(modalEl);
  const fields = ['id','first_name','middle_name','last_name','gender','birth_date','phone','address','program_id','year_level','status','username','email'];
  const form = document.getElementById('studentForm');

  function open(data) {
    form.reset();
    const isEdit = data && data.id;
    fields.forEach(f => { const el = document.getElementById('f_' + f); if (el) el.value = (data && data[f] != null) ? data[f] : (f === 'year_level' ? 1 : (f === 'status' ? 'active' : '')); });
    document.getElementById('studentModalTitle').textContent = isEdit ? 'Edit student' : 'Add student';
    document.getElementById('pwHint').textContent = isEdit ? '(optional)' : '*';
    document.getElementById('pwHelp').textContent = isEdit ? 'Leave blank to keep the current password.' : '8+ characters, letter and number.';
    document.getElementById('f_password').required = !isEdit;
    modal.show();
  }
  document.getElementById('addStudentBtn').addEventListener('click', () => open(null));
  document.querySelectorAll('.edit-btn').forEach(b => b.addEventListener('click', () => open(JSON.parse(b.dataset.student))));

  <?php if ($formState): ?>open(<?= json_encode($formState['data'], JSON_HEX_TAG | JSON_HEX_AMP) ?>);<?php endif; ?>
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
