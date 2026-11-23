<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$user = require_role('admin');
$pageTitle = 'Subjects';
$pdo = Database::getConnection();

$activeSem = get_active_semester($pdo);
$semParam  = $_GET['semester'] ?? ($activeSem ? (string)$activeSem['id'] : 'all');
$semId     = ctype_digit((string)$semParam) ? (int)$semParam : 0;
$q         = clean($_GET['q'] ?? '');
$teacherId = (int)($_GET['teacher'] ?? 0);
$status    = in_array($_GET['status'] ?? '', ['open', 'closed'], true) ? $_GET['status'] : '';

$where = []; $params = [];
if ($semId)     { $where[] = 'sb.semester_id = ?'; $params[] = $semId; }
if ($q !== '')  { $where[] = '(c.code LIKE ? OR c.title LIKE ?)'; array_push($params, "%$q%", "%$q%"); }
if ($teacherId) { $where[] = 'sb.teacher_id = ?';  $params[] = $teacherId; }
if ($status)    { $where[] = 'sb.status = ?';      $params[] = $status; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = 'FROM subjects sb JOIN courses c ON c.id = sb.course_id JOIN semesters sem ON sem.id = sb.semester_id
         JOIN academic_years ay ON ay.id = sem.academic_year_id LEFT JOIN teachers t ON t.id = sb.teacher_id';
$total = (int)scalar($pdo, "SELECT COUNT(*) $from $whereSql", $params);
$pg    = paginate($total, (int)($_GET['page'] ?? 1));

$cnt = fn(string $st) => "(SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id
                           WHERE ed.subject_id = sb.id AND e.status = '$st')";
$stmt = $pdo->prepare(
    "SELECT sb.*, c.code, c.title, c.units, sem.name AS sem_name, ay.name AS ay_name,
            CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
            {$cnt('approved')} AS enrolled, {$cnt('pending')} AS pending
     $from $whereSql ORDER BY ay.start_date DESC, sem.semester_no DESC, c.code, sb.section
     LIMIT {$pg['perPage']} OFFSET {$pg['offset']}"
);
$stmt->execute($params);
$subjects = $stmt->fetchAll();

// schedules for the listed subjects
$sched = [];
if ($subjects) {
    $idList = implode(',', array_map('intval', array_column($subjects, 'id')));
    foreach ($pdo->query("SELECT * FROM subject_schedules WHERE subject_id IN ($idList)
                          ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time") as $r) {
        $sched[$r['subject_id']][] = $r;
    }
    foreach ($subjects as &$s) {
        $rows = $sched[$s['id']] ?? [];
        $s['summary'] = schedule_summary($rows);
        $s['schedules'] = array_map(fn($r) => ['day_of_week' => $r['day_of_week'], 'start_time' => substr($r['start_time'], 0, 5),
                                                 'end_time' => substr($r['end_time'], 0, 5), 'room' => $r['room']], $rows);
    }
    unset($s);
}

$semesters = $pdo->query('SELECT s.id, s.name, ay.name AS ay_name, s.is_active FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id
                          ORDER BY ay.start_date DESC, s.semester_no DESC')->fetchAll();
$courses   = $pdo->query('SELECT id, code, title, status FROM courses ORDER BY code')->fetchAll();
$teachers  = $pdo->query("SELECT id, CONCAT(last_name, ', ', first_name) AS name, status FROM teachers ORDER BY last_name, first_name")->fetchAll();
$formState = $_SESSION['subject_form'] ?? null;
unset($_SESSION['subject_form']);
$keep = array_filter(['semester' => $semParam, 'q' => $q, 'teacher' => $teacherId, 'status' => $status]);

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header">
  <div><h4>Subjects</h4><small class="text-muted">Courses offered per semester · <?= number_format($total) ?> offering<?= $total === 1 ? '' : 's' ?></small></div>
  <button class="btn btn-primary" id="addBtn"><i class="bi bi-plus-lg me-1"></i>Add subject offering</button>
</div>

<div class="card mb-3"><div class="card-body">
  <form class="row g-2" method="get">
    <div class="col-md-3"><select class="form-select" name="semester"><option value="all">All semesters</option>
      <?php foreach ($semesters as $s): ?><option value="<?= $s['id'] ?>" <?= $semId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['ay_name'] . ' · ' . $s['name']) ?><?= $s['is_active'] ? ' (active)' : '' ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><input class="form-control" name="q" placeholder="Search code or title" value="<?= e($q) ?>"></div>
    <div class="col-6 col-md-2"><select class="form-select" name="teacher"><option value="">All teachers</option>
      <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>" <?= $teacherId === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><select class="form-select" name="status"><option value="">All statuses</option>
      <?php foreach (['open', 'closed'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
      <a class="btn btn-light border flex-fill" href="index.php?semester=all" title="Show all"><i class="bi bi-x-lg"></i></a></div>
  </form>
</div></div>

<div class="card">
  <?php if (!$subjects): ?>
    <div class="empty-state"><i class="bi bi-book"></i>No subject offerings found. Click “Add subject offering” to create one.</div>
  <?php else: ?>
  <div class="table-responsive"><table class="table table-hover mb-0">
    <thead><tr><th>Subject</th><th>Section</th><th>Teacher</th><th>Schedule</th><th>Enrolled</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($subjects as $s): $full = (int)$s['enrolled'] >= (int)$s['max_students']; ?>
      <tr>
        <td><div class="fw-semibold"><?= e($s['code']) ?> <span class="fw-normal"><?= e($s['title']) ?></span></div>
            <small class="text-muted"><?= e($s['ay_name'] . ' · ' . $s['sem_name']) ?> · <?= (int)$s['units'] ?> units</small></td>
        <td><?= e($s['section']) ?></td>
        <td><?= e($s['teacher_name'] ?? '—') ?></td>
        <td class="small"><?= $s['summary'] ? implode('<br>', array_map('e', $s['summary'])) : '<span class="text-muted">TBA</span>' ?></td>
        <td class="text-nowrap"><span class="<?= $full ? 'text-danger fw-semibold' : '' ?>"><?= (int)$s['enrolled'] ?> / <?= (int)$s['max_students'] ?></span>
          <?php if ($s['pending']): ?><br><small class="text-muted"><?= (int)$s['pending'] ?> pending</small><?php endif; ?></td>
        <td><?= status_badge($s['status']) ?></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-secondary edit-btn" title="Edit" data-item="<?= e(json_encode($s, JSON_HEX_TAG | JSON_HEX_AMP)) ?>"><i class="bi bi-pencil"></i></button>
          <?php $open = $s['status'] === 'open'; ?>
          <form method="post" action="actions.php" class="d-inline"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="toggle">
            <button class="btn btn-sm btn-outline-<?= $open ? 'warning' : 'success' ?>" title="<?= $open ? 'Close enrollment' : 'Open enrollment' ?>"
                    data-confirm="<?= $open ? 'Close' : 'Open' ?> <?= e($s['code'] . ' ' . $s['section']) ?> for enrollment?" data-confirm-class="btn-<?= $open ? 'warning' : 'success' ?>"
                    data-confirm-label="Yes"><i class="bi bi-<?= $open ? 'lock' : 'unlock' ?>"></i></button></form>
          <form method="post" action="actions.php" class="d-inline"><?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="delete">
            <button class="btn btn-sm btn-outline-danger" title="Delete" data-confirm="Delete <?= e($s['code'] . ' section ' . $s['section']) ?>?" data-confirm-label="Delete"><i class="bi bi-trash"></i></button></form>
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
      <div class="modal-header"><h5 class="modal-title" id="entityTitle">Add subject offering</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php if (!empty($formState['errors'])): ?><div class="alert alert-danger"><ul class="mb-0 ps-3">
          <?php foreach ($formState['errors'] as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Semester *</label><select class="form-select" name="semester_id"><option value="">Select…</option>
            <?php foreach ($semesters as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['ay_name'] . ' · ' . $s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Course *</label><select class="form-select" name="course_id"><option value="">Select…</option>
            <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['code'] . ' — ' . $c['title']) ?><?= $c['status'] === 'unavailable' ? ' (unavailable)' : '' ?></option><?php endforeach; ?></select></div>
          <div class="col-6 col-md-3"><label class="form-label">Section *</label><input class="form-control" name="section" maxlength="20" placeholder="A"></div>
          <div class="col-6 col-md-3"><label class="form-label">Max students *</label><input class="form-control" type="number" name="max_students" min="1" max="500"></div>
          <div class="col-md-4"><label class="form-label">Teacher</label><select class="form-select" name="teacher_id"><option value="">— Unassigned —</option>
            <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?><?= $t['status'] === 'inactive' ? ' (inactive)' : '' ?></option><?php endforeach; ?></select></div>
          <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="open">Open</option><option value="closed">Closed</option></select></div>
          <div class="col-12" id="lockNote" style="display:none"><div class="alert alert-info py-2 mb-0 small">Semester and course can’t be changed after creation. Delete and recreate the offering if needed.</div></div>
          <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label mb-0">Schedule</label>
              <button type="button" class="btn btn-sm btn-outline-primary" id="addSched"><i class="bi bi-plus-lg me-1"></i>Add time slot</button></div>
            <div id="schedRows"></div>
            <div class="text-muted small" id="schedEmpty">No time slots yet (schedule shows as TBA).</div>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save offering</button></div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const days = <?= json_encode(DAYS_OF_WEEK) ?>;
  const box = document.getElementById('schedRows'), empty = document.getElementById('schedEmpty');
  const refresh = () => { empty.style.display = box.children.length ? 'none' : 'block'; };

  function addRow(r) {
    r = r || {};
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 align-items-center';
    row.innerHTML = '<div class="col-6 col-md-3"><select name="sched_day[]" class="form-select form-select-sm">' +
      days.map(d => '<option>' + d + '</option>').join('') + '</select></div>' +
      '<div class="col-3 col-md-2"><input type="time" name="sched_start[]" class="form-control form-control-sm"></div>' +
      '<div class="col-3 col-md-2"><input type="time" name="sched_end[]" class="form-control form-control-sm"></div>' +
      '<div class="col-9 col-md-4"><input name="sched_room[]" class="form-control form-control-sm" maxlength="50" placeholder="Room"></div>' +
      '<div class="col-3 col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger rm"><i class="bi bi-x-lg"></i></button></div>';
    row.querySelector('[name="sched_day[]"]').value = r.day_of_week || 'Monday';
    row.querySelector('[name="sched_start[]"]').value = (r.start_time || '').slice(0, 5);
    row.querySelector('[name="sched_end[]"]').value = (r.end_time || '').slice(0, 5);
    row.querySelector('[name="sched_room[]"]').value = r.room || '';
    row.querySelector('.rm').addEventListener('click', () => { row.remove(); refresh(); });
    box.appendChild(row); refresh();
  }
  document.getElementById('addSched').addEventListener('click', () => addRow());

  setupEntityModal({
    modalId: 'entityModal', formId: 'entityForm', titleId: 'entityTitle', addBtnId: 'addBtn', editClass: 'edit-btn',
    fields: ['id', 'semester_id', 'course_id', 'section', 'max_students', 'teacher_id', 'status'],
    defaults: { semester_id: <?= json_encode((string)($semId ?: ($activeSem['id'] ?? ''))) ?>, section: 'A', max_students: 40, status: 'open' },
    titles: ['Add subject offering', 'Edit subject offering'],
    onOpen: (d, isEdit, form) => {
      form.elements['semester_id'].disabled = isEdit;
      form.elements['course_id'].disabled = isEdit;
      document.getElementById('lockNote').style.display = isEdit ? 'block' : 'none';
      box.innerHTML = '';
      ((d && d.schedules) || []).forEach(addRow); refresh();
    },
    reopen: <?= json_encode($formState['data'] ?? null, JSON_HEX_TAG | JSON_HEX_AMP) ?>
  });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
