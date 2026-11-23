<?php
require_once __DIR__ . '/../../includes/auth_check.php';
$user = require_role('admin');
$pdo = Database::getConnection();
$tab = in_array($_GET['status'] ?? '', ['pending', 'verified', 'rejected', 'all'], true) ? $_GET['status'] : 'pending';
$q = clean($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { set_flash('danger', 'Invalid request.'); redirect('admin/documents/index.php'); }
    $act = $_POST['action'] ?? ''; $id = (int)($_POST['id'] ?? 0); $remarks = clean($_POST['remarks'] ?? '');
    $d = $pdo->prepare('SELECT d.*, s.user_id FROM student_documents d JOIN students s ON s.id = d.student_id WHERE d.id = ?'); $d->execute([$id]); $doc = $d->fetch();
    if (!$doc || !in_array($act, ['verify', 'reject'], true)) set_flash('danger', 'Document not found.');
    elseif ($act === 'reject' && $remarks === '') set_flash('danger', 'Please enter the reason for rejection.');
    elseif (mb_strlen($remarks) > 250) set_flash('danger', 'Remarks are too long (max 250).');
    else {
        $status = $act === 'verify' ? 'verified' : 'rejected';
        $pdo->prepare('UPDATE student_documents SET status = ?, remarks = ?, verified_by = ?, verified_at = NOW() WHERE id = ?')->execute([$status, $remarks ?: null, $user['id'], $id]);
        notify_user($pdo, (int)$doc['user_id'], $status === 'verified' ? 'success' : 'danger', 'Document ' . $status,
            $doc['document_type'] . ' was ' . $status . ($remarks ? ". $remarks" : '.'), 'student/documents.php');
        log_activity('document_' . $status, $doc['document_type'] . ' #' . $id);
        set_flash('success', 'Document ' . $status . '. The student was notified.');
    }
    redirect('admin/documents/index.php?status=' . $tab);
}

$where = $tab === 'all' ? '1=1' : 'd.status = ?'; $params = $tab === 'all' ? [] : [$tab];
if ($q !== '') { $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_no LIKE ?)"; array_push($params, "%$q%", "%$q%", "%$q%"); }
$from = 'FROM student_documents d JOIN students s ON s.id = d.student_id';
$total = (int)scalar($pdo, "SELECT COUNT(*) $from WHERE $where", $params);
$pg = paginate($total, (int)($_GET['page'] ?? 1));
$st = $pdo->prepare("SELECT d.*, s.first_name, s.last_name, s.student_no $from WHERE $where ORDER BY d.uploaded_at DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}");
$st->execute($params);
$docs = $st->fetchAll();
$counts = ['pending' => 0, 'verified' => 0, 'rejected' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) n FROM student_documents GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
$keep = array_filter(['status' => $tab, 'q' => $q]);

$pageTitle = 'Documents';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div><h4>Student Documents</h4><small class="text-muted">Verify the documents students upload for enrollment</small></div>
  <form class="d-flex gap-1" method="get"><input type="hidden" name="status" value="<?= e($tab) ?>"><input class="form-control" name="q" placeholder="Search student" value="<?= e($q) ?>"><button class="btn btn-primary"><i class="bi bi-search"></i></button></form></div>
<ul class="nav nav-pills mb-3 gap-1"><?php foreach (['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $l): ?>
  <li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : 'bg-white border' ?>" href="?status=<?= $k ?>"><?= $l ?><?php if ($k !== 'all'): ?> <span class="badge <?= $tab === $k ? 'text-bg-light' : 'text-bg-secondary' ?>"><?= $counts[$k] ?></span><?php endif; ?></a></li><?php endforeach; ?></ul>
<div class="card"><?php if (!$docs): ?><div class="empty-state"><i class="bi bi-file-earmark-check"></i>No documents here.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Student</th><th>Document</th><th>Uploaded</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php foreach ($docs as $d): ?><tr><td><div class="fw-semibold"><?= e($d['last_name'] . ', ' . $d['first_name']) ?></div><small class="text-muted"><?= e($d['student_no']) ?></small></td>
    <td><?= e($d['document_type']) ?><div class="small text-muted"><?= e($d['original_name']) ?></div><?php if ($d['remarks']): ?><div class="small text-danger"><?= e($d['remarks']) ?></div><?php endif; ?></td>
    <td class="small text-nowrap"><?= e(format_date($d['uploaded_at'])) ?></td><td><?= status_badge($d['status']) ?></td>
    <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e(url('download.php?id=' . (int)$d['id'])) ?>"><i class="bi bi-eye"></i></a>
      <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="verify"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <button class="btn btn-sm btn-outline-success" title="Verify" data-confirm="Mark this document as verified?" data-confirm-class="btn-success" data-confirm-label="Verify"><i class="bi bi-check-lg"></i></button></form>
      <button class="btn btn-sm btn-outline-danger reject-btn" data-id="<?= (int)$d['id'] ?>" title="Reject"><i class="bi bi-x-lg"></i></button></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white"><?= render_pagination($pg, $keep) ?></div><?php endif; ?></div>

<div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" id="rejectId">
  <div class="modal-header"><h5 class="modal-title">Reject document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><label class="form-label">Reason *</label><textarea class="form-control" name="remarks" rows="3" maxlength="250" required></textarea></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reject</button></div></form></div></div>
<script>document.addEventListener('DOMContentLoaded', () => { const m = new bootstrap.Modal(document.getElementById('rejectModal'));
  document.querySelectorAll('.reject-btn').forEach(b => b.addEventListener('click', () => { document.getElementById('rejectId').value = b.dataset.id; m.show(); })); });</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
