<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/enrollment.php';
require_once __DIR__ . '/../includes/upload.php';
$user = require_role('student');
$pdo  = Database::getConnection();
$me   = student_by_user($pdo, (int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) { set_flash('danger', 'Invalid request. Please try again.'); redirect('student/documents.php'); }
    $act = $_POST['action'] ?? '';
    if ($act === 'upload') {
        $type = clean($_POST['document_type'] ?? '');
        if (!in_array($type, DOCUMENT_TYPES, true)) set_flash('danger', 'Please choose a document type.');
        else {
            [$ok, $res] = save_upload($_FILES['document'] ?? [], ALLOWED_DOC_TYPES, 'documents', 'doc' . $me['id']);
            if ($ok) {
                $orig = mb_substr(basename($_FILES['document']['name']), 0, 255);
                $pdo->prepare('INSERT INTO student_documents (student_id, document_type, original_name, file_path) VALUES (?,?,?,?)')->execute([$me['id'], $type, $orig, $res]);
                log_activity('document_uploaded', $type);
                set_flash('success', 'Document uploaded. It will be verified by the administrator.');
            } else set_flash('danger', $res);
        }
    } elseif ($act === 'delete') {
        $q = $pdo->prepare("SELECT * FROM student_documents WHERE id = ? AND student_id = ? AND status <> 'verified'");
        $q->execute([(int)($_POST['id'] ?? 0), $me['id']]);
        if ($doc = $q->fetch()) {
            $pdo->prepare('DELETE FROM student_documents WHERE id = ?')->execute([$doc['id']]);
            delete_upload('documents', $doc['file_path']);
            set_flash('success', 'Document deleted.');
        } else set_flash('warning', 'Verified documents cannot be deleted.');
    }
    redirect('student/documents.php');
}

$d = $pdo->prepare('SELECT * FROM student_documents WHERE student_id = ? ORDER BY uploaded_at DESC');
$d->execute([$me['id']]);
$docs = $d->fetchAll();

$pageTitle = 'My Documents';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><h4>Enrollment Documents</h4></div>
<div class="row g-3">
  <div class="col-lg-4"><div class="card"><div class="card-header bg-white fw-semibold">Upload a document</div><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="upload">
      <label class="form-label">Document type</label>
      <select class="form-select mb-3" name="document_type" required><option value="">Select…</option><?php foreach (DOCUMENT_TYPES as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select>
      <label class="form-label">File (PDF, JPG or PNG, max 2 MB)</label>
      <input class="form-control mb-3" type="file" name="document" accept="application/pdf,image/jpeg,image/png" required>
      <button class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Upload</button>
    </form></div></div></div>
  <div class="col-lg-8"><div class="card"><div class="card-header bg-white fw-semibold">My uploads</div>
    <?php if (!$docs): ?><div class="empty-state"><i class="bi bi-folder2-open"></i>No documents uploaded yet.</div><?php else: ?>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Document</th><th>Uploaded</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php foreach ($docs as $x): ?><tr>
      <td><div class="fw-semibold"><?= e($x['document_type']) ?></div><small class="text-muted"><?= e($x['original_name']) ?></small>
        <?php if ($x['remarks']): ?><div class="small text-danger">Remarks: <?= e($x['remarks']) ?></div><?php endif; ?></td>
      <td class="small text-nowrap"><?= e(format_date($x['uploaded_at'])) ?></td><td><?= status_badge($x['status']) ?></td>
      <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e(url('download.php?id=' . (int)$x['id'])) ?>" title="View"><i class="bi bi-eye"></i></a>
        <?php if ($x['status'] !== 'verified'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
          <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this document?" data-confirm-label="Delete"><i class="bi bi-trash"></i></button></form><?php endif; ?></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
