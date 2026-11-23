<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
$user = require_role('student');
$pdo  = Database::getConnection();
$me   = student_by_user($pdo, (int)$user['id']);
$sem  = get_active_semester($pdo);
[$open, $windowMsg] = enrollment_window($sem);
$canEnroll = $sem && $me['status'] === 'active';
$self = 'student/enrollment.php';

function recalc_units(PDO $pdo, int $enrId): void
{
    $pdo->prepare('UPDATE enrollments SET total_units = (SELECT COALESCE(SUM(units), 0) FROM enrollment_details WHERE enrollment_id = ?) WHERE id = ?')
        ->execute([$enrId, $enrId]);
}
function flash_errors(array $errors): void
{
    foreach (array_slice($errors, 0, 6) as $er) set_flash('danger', $er);
}

// ------------------------------------------------------------ actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { set_flash('danger', 'Invalid request. Please try again.'); redirect($self); }
    if (!$canEnroll) { set_flash('warning', 'Enrollment is not available for your account right now.'); redirect($self); }

    $enr = get_or_create_enrollment($pdo, (int)$me['id'], (int)$sem['id']);
    $act = $_POST['action'] ?? '';
    $editable = in_array($enr['status'], ['draft', 'rejected', 'cancelled'], true);

    try {
        if (in_array($act, ['add', 'remove', 'submit'], true) && (!$open || !$editable)) {
            set_flash('warning', !$open ? $windowMsg : 'This application can no longer be changed.');
        } elseif ($act === 'add') {
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $sel = enrollment_selected($pdo, (int)$enr['id']);
            $ids = array_map('intval', array_column($sel, 'subject_id'));
            if (in_array($subjectId, $ids, true)) {
                set_flash('warning', 'You already selected that subject.');
            } else {
                $errors = check_subject_rules($pdo, $me, $sem, $subjectId, $ids, passed_course_ids($pdo, (int)$me['id']));
                $units  = (int)scalar($pdo, 'SELECT c.units FROM subjects sb JOIN courses c ON c.id = sb.course_id WHERE sb.id = ?', [$subjectId]);
                $current = array_sum(array_column($sel, 'units'));
                if (!$errors && $current + $units > (int)$sem['max_units']) $errors[] = "Adding this subject would exceed the maximum of {$sem['max_units']} units.";
                if ($errors) flash_errors($errors);
                else {
                    $pdo->beginTransaction();
                    $pdo->prepare('INSERT INTO enrollment_details (enrollment_id, subject_id, units) VALUES (?, ?, ?)')->execute([$enr['id'], $subjectId, $units]);
                    $pdo->prepare("UPDATE enrollments SET status = 'draft' WHERE id = ?")->execute([$enr['id']]);
                    recalc_units($pdo, (int)$enr['id']);
                    $pdo->commit();
                    set_flash('success', 'Subject added.');
                }
            }
        } elseif ($act === 'remove') {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM enrollment_details WHERE id = ? AND enrollment_id = ?')->execute([(int)($_POST['detail_id'] ?? 0), $enr['id']]);
            $pdo->prepare("UPDATE enrollments SET status = 'draft' WHERE id = ?")->execute([$enr['id']]);
            recalc_units($pdo, (int)$enr['id']);
            $pdo->commit();
            set_flash('success', 'Subject removed.');
        } elseif ($act === 'submit') {
            $sel = enrollment_selected($pdo, (int)$enr['id']);
            $ids = array_map('intval', array_column($sel, 'subject_id'));
            $errors = [];
            if (!$sel) $errors[] = 'Select at least one subject before submitting.';
            $passed = passed_course_ids($pdo, (int)$me['id']);
            foreach ($sel as $s) {
                $errors = array_merge($errors, check_subject_rules($pdo, $me, $sem, (int)$s['subject_id'], array_values(array_diff($ids, [(int)$s['subject_id']])), $passed));
            }
            if (array_sum(array_column($sel, 'units')) > (int)$sem['max_units']) $errors[] = "Total units exceed the maximum of {$sem['max_units']}.";
            if ($errors) { flash_errors(array_values(array_unique($errors))); }
            else {
                $pdo->beginTransaction();
                recalc_units($pdo, (int)$enr['id']);
                $pdo->prepare("UPDATE enrollments SET status = 'pending', submitted_at = NOW(), reviewed_by = NULL, reviewed_at = NULL, remarks = NULL WHERE id = ?")->execute([$enr['id']]);
                $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link)
                               SELECT id, 'info', 'New enrollment application', ?, 'admin/enrollments/index.php?status=pending' FROM users WHERE role = 'admin' AND status = 'active'")
                    ->execute([$me['first_name'] . ' ' . $me['last_name'] . ' (' . $me['student_no'] . ') submitted an enrollment application.']);
                notify_user($pdo, (int)$user['id'], 'info', 'Application submitted', 'Your enrollment application is pending review.', $self);
                $pdo->commit();
                log_activity('enrollment_submitted', 'Submitted enrollment application #' . $enr['id']);
                set_flash('success', 'Your application was submitted and is now pending review.');
            }
        } elseif ($act === 'cancel') {
            if ($enr['status'] === 'pending') {
                $pdo->prepare("UPDATE enrollments SET status = 'cancelled' WHERE id = ?")->execute([$enr['id']]);
                log_activity('enrollment_cancelled', 'Cancelled enrollment application #' . $enr['id']);
                set_flash('success', 'Application cancelled. You can edit your subjects and submit again.');
            } else set_flash('warning', 'Only pending applications can be cancelled.');
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Enrollment failed: ' . $e->getMessage());
        set_flash('danger', 'A database error occurred. Please try again.');
    }
    redirect($self);
}

// ------------------------------------------------------------ view
$enr = null; $selected = [];
if ($sem) {
    $q = $pdo->prepare('SELECT * FROM enrollments WHERE student_id = ? AND semester_id = ?');
    $q->execute([$me['id'], $sem['id']]);
    $enr = $q->fetch() ?: null;
    if ($enr) $selected = enrollment_selected($pdo, (int)$enr['id']);
}
$status   = $enr['status'] ?? 'draft';
$editable = in_array($status, ['draft', 'rejected', 'cancelled'], true);
$selIds   = array_map('intval', array_column($selected, 'subject_id'));
$totalUnits = array_sum(array_column($selected, 'units'));
$maxUnits = (int)($sem['max_units'] ?? 24);

$available = []; $sched = [];
$search = clean($_GET['q'] ?? '');
if ($canEnroll && $open && $editable) {
    $sql = "SELECT sb.id, sb.section, sb.max_students, c.code, c.title, c.units, c.description, CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
                   (SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id WHERE ed.subject_id = sb.id AND e.status = 'approved') AS taken
            FROM subjects sb JOIN courses c ON c.id = sb.course_id LEFT JOIN teachers t ON t.id = sb.teacher_id
            WHERE sb.semester_id = ? AND sb.status = 'open' AND c.status = 'available' AND (c.program_id IS NULL OR c.program_id = ?)";
    $params = [$sem['id'], $me['program_id']];
    if ($search !== '') { $sql .= ' AND (c.code LIKE ? OR c.title LIKE ?)'; array_push($params, "%$search%", "%$search%"); }
    $q = $pdo->prepare($sql . ' ORDER BY c.code, sb.section');
    $q->execute($params);
    $available = $q->fetchAll();
    $sched = subject_schedule_rows($pdo, array_column($available, 'id'));
}
$selSched = subject_schedule_rows($pdo, $selIds);

$pageTitle = 'Online Enrollment';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><h4>Online Enrollment</h4>
  <small class="text-muted"><?= $sem ? e($sem['ay_name'] . ' · ' . $sem['name']) : 'No active semester' ?></small></div>
  <?php if ($status === 'approved'): ?><a class="btn btn-outline-primary" href="enrollment-form.php?id=<?= (int)$enr['id'] ?>"><i class="bi bi-printer me-1"></i>Enrollment form</a><?php endif; ?></div>

<?php if (!$sem): ?>
  <div class="card"><div class="empty-state"><i class="bi bi-calendar-x"></i>There is no active semester right now. Please check back later.</div></div>
<?php else: ?>
  <?php if ($status === 'pending'): ?><div class="alert alert-warning"><i class="bi bi-hourglass-split me-1"></i><strong>Pending review.</strong> Your application is waiting for the administrator.</div>
  <?php elseif ($status === 'approved'): ?><div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><strong>Approved.</strong> You are enrolled for this semester.<?= $enr['remarks'] ? ' Remarks: ' . e($enr['remarks']) : '' ?></div>
  <?php elseif ($status === 'rejected' || ($status === 'draft' && $enr && $enr['remarks'])): ?><div class="alert alert-danger"><strong>Application rejected.</strong> Reason: <?= e($enr['remarks'] ?? '') ?> Update your subjects and submit again.</div>
  <?php elseif ($status === 'cancelled'): ?><div class="alert alert-secondary">You cancelled your application. You can edit your subjects and submit again.</div><?php endif; ?>
  <?php if ($editable): ?><div class="alert alert-<?= $open ? 'info' : 'secondary' ?> py-2"><i class="bi bi-calendar-event me-1"></i><?= e($windowMsg) ?></div><?php endif; ?>

  <div class="row g-3">
    <?php if ($editable && $open && $canEnroll): ?>
    <div class="col-lg-7">
      <div class="card"><div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2"><span class="fw-semibold">Available subjects</span>
        <form method="get" class="d-flex gap-1"><input class="form-control form-control-sm" name="q" placeholder="Search code or title" value="<?= e($search) ?>"><button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button></form></div>
        <?php if (!$available): ?><div class="empty-state"><i class="bi bi-book"></i>No subjects available<?= $search ? ' for that search' : ' this semester' ?>.</div><?php else: ?>
        <div class="list-group list-group-flush">
        <?php foreach ($available as $s): $left = (int)$s['max_students'] - (int)$s['taken']; $picked = in_array((int)$s['id'], $selIds, true); $sum = schedule_summary($sched[$s['id']] ?? []); ?>
          <div class="list-group-item"><div class="d-flex justify-content-between gap-3">
            <div><div class="fw-semibold"><?= e($s['code']) ?> <span class="fw-normal"><?= e($s['title']) ?></span> <small class="text-muted">· Sec <?= e($s['section']) ?> · <?= (int)$s['units'] ?> units</small></div>
              <?php if ($s['description']): ?><div class="small text-muted"><?= e(mb_strimwidth($s['description'], 0, 110, '…')) ?></div><?php endif; ?>
              <div class="small"><i class="bi bi-person me-1"></i><?= e($s['teacher_name'] ?? 'TBA') ?>
                · <i class="bi bi-clock me-1"></i><?= $sum ? e(implode(' | ', $sum)) : 'TBA' ?></div>
              <div class="small <?= $left <= 0 ? 'text-danger' : 'text-muted' ?>"><i class="bi bi-people me-1"></i><?= max(0, $left) ?> of <?= (int)$s['max_students'] ?> slots left</div></div>
            <form method="post" class="align-self-center"><?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="subject_id" value="<?= (int)$s['id'] ?>">
              <?php if ($picked): ?><button class="btn btn-sm btn-success" disabled><i class="bi bi-check-lg"></i> Added</button>
              <?php elseif ($left <= 0): ?><button class="btn btn-sm btn-outline-secondary" disabled>Full</button>
              <?php else: ?><button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Add</button><?php endif; ?></form>
          </div></div>
        <?php endforeach; ?></div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="col-lg-<?= ($editable && $open && $canEnroll) ? '5' : '12' ?>">
      <div class="card"><div class="card-header bg-white fw-semibold">Selected subjects</div>
        <?php if (!$selected): ?><div class="empty-state"><i class="bi bi-cart"></i>No subjects selected yet.<?= $editable ? ' Add subjects from the list.' : '' ?></div><?php else: ?>
        <div class="table-responsive"><table class="table mb-0">
          <thead><tr><th>Subject</th><th>Schedule</th><th>Units</th><?php if ($editable): ?><th></th><?php endif; ?></tr></thead><tbody>
          <?php foreach ($selected as $s): $sum = schedule_summary($selSched[$s['subject_id']] ?? []); ?>
            <tr><td><div class="fw-semibold"><?= e($s['code']) ?> <small class="text-muted">· <?= e($s['section']) ?></small></div><small><?= e($s['title']) ?></small><div class="small text-muted"><?= e($s['teacher_name'] ?? 'TBA') ?></div></td>
              <td class="small"><?= $sum ? implode('<br>', array_map('e', $sum)) : 'TBA' ?></td><td><?= (int)$s['units'] ?></td>
              <?php if ($editable): ?><td class="text-end"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="detail_id" value="<?= (int)$s['detail_id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Remove" <?= $open ? '' : 'disabled' ?>><i class="bi bi-x-lg"></i></button></form></td><?php endif; ?></tr>
          <?php endforeach; ?></tbody></table></div><?php endif; ?>
        <div class="card-body border-top">
          <div class="d-flex justify-content-between mb-1"><span>Total units</span><strong class="<?= $totalUnits > $maxUnits ? 'text-danger' : '' ?>"><?= (int)$totalUnits ?> / <?= $maxUnits ?></strong></div>
          <div class="progress mb-3" style="height:6px"><div class="progress-bar <?= $totalUnits > $maxUnits ? 'bg-danger' : '' ?>" style="width:<?= min(100, $maxUnits ? $totalUnits / $maxUnits * 100 : 0) ?>%"></div></div>
          <?php if ($editable): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="submit">
              <button class="btn btn-primary w-100" <?= ($selected && $open && $canEnroll) ? '' : 'disabled' ?>
                data-confirm="Submit your enrollment application for review?" data-confirm-class="btn-primary" data-confirm-label="Submit"><i class="bi bi-send me-1"></i>Submit application</button></form>
          <?php elseif ($status === 'pending'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel">
              <button class="btn btn-outline-danger w-100" data-confirm="Cancel your pending application?" data-confirm-label="Cancel application">Cancel application</button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
