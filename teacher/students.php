<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
require_once __DIR__ . '/../includes/csv.php';
$user = require_role('teacher');
$pdo  = Database::getConnection();
$me   = teacher_by_user($pdo, (int)$user['id']);

// Only subjects assigned to THIS teacher (server-side ownership check)
$subs = $pdo->prepare('SELECT sb.id, sb.section, sb.max_students, c.code, c.title, sem.name AS sem_name, ay.name AS ay_name
                       FROM subjects sb JOIN courses c ON c.id = sb.course_id JOIN semesters sem ON sem.id = sb.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
                       WHERE sb.teacher_id = ? ORDER BY ay.start_date DESC, sem.semester_no DESC, c.code');
$subs->execute([$me['id']]);
$mine = $subs->fetchAll();
$byId = array_column($mine, null, 'id');
$subjectId = (int)($_GET['subject'] ?? 0);
if (!isset($byId[$subjectId])) $subjectId = $mine ? (int)$mine[0]['id'] : 0;
$subject = $byId[$subjectId] ?? null;
$q = clean($_GET['q'] ?? '');

$students = [];
if ($subject) {
    $sql = "SELECT s.id, s.student_no, s.first_name, s.middle_name, s.last_name, s.gender, s.year_level, s.phone, u.email,
                   p.code AS program_code, p.name AS program_name, e.reviewed_at, e.total_units
            FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id AND e.status = 'approved'
            JOIN students s ON s.id = e.student_id JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
            WHERE ed.subject_id = ?";
    $params = [$subjectId];
    if ($q !== '') { $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_no LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)"; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
    $st = $pdo->prepare($sql . ' ORDER BY s.last_name, s.first_name');
    $st->execute($params);
    $students = $st->fetchAll();

    if (($_GET['export'] ?? '') === 'csv') {
        $rows = []; $i = 1;
        foreach ($students as $s) $rows[] = [$i++, $s['student_no'], $s['last_name'] . ', ' . $s['first_name'], $s['program_code'], $s['year_level'], $s['email'], $s['phone']];
        log_activity('class_list_export', $subject['code'] . ' ' . $subject['section']);
        send_csv('class-list-' . $subject['code'] . '-' . $subject['section'] . '.csv', ['#', 'Student No.', 'Name', 'Program', 'Year', 'Email', 'Phone'], $rows);
    }
}
$exportUrl = '?' . http_build_query(['subject' => $subjectId, 'q' => $q, 'export' => 'csv']);

$pageTitle = 'Students';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h4>Class List</h4><?php if ($subject): ?><small class="text-muted"><?= e($subject['code'] . ' · ' . $subject['title'] . ' · Section ' . $subject['section'] . ' · ' . $subject['ay_name'] . ' ' . $subject['sem_name']) ?></small><?php endif; ?></div>
  <?php if ($subject): ?><div class="d-flex gap-2 no-print"><a class="btn btn-outline-primary" href="<?= e($exportUrl) ?>"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export (Excel/CSV)</a>
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / PDF</button></div><?php endif; ?></div>
<?php if (!$mine): ?><div class="card"><div class="empty-state"><i class="bi bi-people"></i>You have no assigned subjects yet.</div></div><?php else: ?>
<div class="card mb-3 no-print"><div class="card-body"><form class="row g-2" method="get">
  <div class="col-md-6"><select class="form-select" name="subject" onchange="this.form.submit()">
    <?php foreach ($mine as $s): ?><option value="<?= $s['id'] ?>" <?= $subjectId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['ay_name'] . ' ' . $s['sem_name'] . ' — ' . $s['code'] . ' (' . $s['section'] . ') ' . $s['title']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-4"><input class="form-control" name="q" placeholder="Search student name or number" value="<?= e($q) ?>"></div>
  <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button><a class="btn btn-light border flex-fill" href="?subject=<?= $subjectId ?>"><i class="bi bi-x-lg"></i></a></div></form></div></div>
<div class="card">
  <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">Enrolled students</span><span class="text-muted small"><?= count($students) ?> of <?= (int)$subject['max_students'] ?> slots</span></div>
  <?php if (!$students): ?><div class="empty-state"><i class="bi bi-people"></i>No enrolled students<?= $q ? ' match your search' : ' yet' ?>.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>#</th><th>Student No.</th><th>Name</th><th>Program</th><th>Email</th><th>Phone</th><th class="no-print"></th></tr></thead><tbody>
  <?php foreach ($students as $i => $s): ?><tr><td><?= $i + 1 ?></td><td><?= e($s['student_no']) ?></td><td class="fw-semibold"><?= e($s['last_name'] . ', ' . $s['first_name']) ?></td>
    <td><?= e($s['program_code']) ?> · Yr <?= (int)$s['year_level'] ?></td><td><?= e($s['email']) ?></td><td><?= e($s['phone'] ?: '—') ?></td>
    <td class="text-end no-print"><button class="btn btn-sm btn-outline-primary view-btn" data-item="<?= e(json_encode($s, JSON_HEX_TAG | JSON_HEX_AMP)) ?>"><i class="bi bi-eye"></i></button></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<div class="modal fade" id="profileModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Student profile</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><dl class="row mb-0" id="profileBody"></dl></div></div></div></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const m = new bootstrap.Modal(document.getElementById('profileModal'));
  document.querySelectorAll('.view-btn').forEach(b => b.addEventListener('click', () => {
    const s = JSON.parse(b.dataset.item), body = document.getElementById('profileBody'); body.innerHTML = '';
    [['Name', [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ')], ['Student no.', s.student_no], ['Program', s.program_name],
     ['Year level', s.year_level], ['Gender', s.gender], ['Email', s.email], ['Phone', s.phone], ['Units enrolled', s.total_units], ['Approved on', s.reviewed_at]]
    .forEach(([k, v]) => { const dt = document.createElement('dt'), dd = document.createElement('dd');
      dt.className = 'col-5 text-muted fw-normal'; dd.className = 'col-7'; dt.textContent = k; dd.textContent = (v === null || v === '' || v === undefined) ? '—' : v; body.append(dt, dd); });
    m.show();
  }));
});
</script><?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
