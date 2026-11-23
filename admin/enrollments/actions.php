<?php
/** Approve / reject an enrollment application. POST + CSRF only. */
require_once __DIR__ . '/../../includes/auth_check.php';
$admin = require_role('admin');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    set_flash('danger', 'Invalid request. Please try again.');
    redirect('admin/enrollments/index.php');
}
$action  = $_POST['action'] ?? '';
$id      = (int)($_POST['id'] ?? 0);
$remarks = trim(strip_tags($_POST['remarks'] ?? ''));
$back    = 'admin/enrollments/view.php?id=' . $id;

if (!in_array($action, ['approve', 'reject'], true)) { set_flash('danger', 'Unknown action.'); redirect('admin/enrollments/index.php'); }
if (mb_strlen($remarks) > 500) { set_flash('danger', 'Remarks are too long (max 500 characters).'); redirect($back); }
if ($action === 'reject' && $remarks === '') { set_flash('danger', 'Please enter the reason for rejection.'); redirect($back); }

try {
    $pdo->beginTransaction();

    // lock the application and make sure it is still pending
    $q = $pdo->prepare(
        'SELECT e.id, e.status, s.user_id, s.student_no, sem.name AS sem_name, ay.name AS ay_name
         FROM enrollments e JOIN students s ON s.id = e.student_id JOIN semesters sem ON sem.id = e.semester_id
         JOIN academic_years ay ON ay.id = sem.academic_year_id WHERE e.id = ? FOR UPDATE'
    );
    $q->execute([$id]);
    $en = $q->fetch();
    if (!$en || $en['status'] !== 'pending') {
        $pdo->rollBack();
        set_flash('warning', 'This application is no longer pending.');
        redirect($back);
    }
    $term = $en['ay_name'] . ' ' . $en['sem_name'];

    if ($action === 'approve') {
        $d = $pdo->prepare('SELECT d.subject_id, sb.max_students, c.code, sb.section FROM enrollment_details d
                            JOIN subjects sb ON sb.id = d.subject_id JOIN courses c ON c.id = sb.course_id WHERE d.enrollment_id = ?');
        $d->execute([$id]);
        $subjects = $d->fetchAll();
        if (!$subjects) { $pdo->rollBack(); set_flash('danger', 'This application has no subjects and cannot be approved.'); redirect($back); }

        // lock the subjects, then re-check capacity against students approved so far
        $ph = implode(',', array_fill(0, count($subjects), '?'));
        $pdo->prepare("SELECT id FROM subjects WHERE id IN ($ph) FOR UPDATE")->execute(array_column($subjects, 'subject_id'));
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id
                              WHERE ed.subject_id = ? AND e.status = 'approved' AND e.id <> ?");
        $full = [];
        foreach ($subjects as $s) {
            $cnt->execute([$s['subject_id'], $id]);
            if ((int)$cnt->fetchColumn() >= (int)$s['max_students']) $full[] = $s['code'] . ' (' . $s['section'] . ')';
        }
        if ($full) {
            $pdo->rollBack();
            set_flash('danger', 'Cannot approve: no slots left in ' . implode(', ', $full) . '.');
            redirect($back);
        }
    }

    $status = $action === 'approve' ? 'approved' : 'rejected';
    $pdo->prepare('UPDATE enrollments SET status = ?, reviewed_by = ?, reviewed_at = NOW(), remarks = ? WHERE id = ?')
        ->execute([$status, $admin['id'], $remarks !== '' ? $remarks : null, $id]);

    if ($action === 'approve') {
        notify_user($pdo, (int)$en['user_id'], 'success', 'Enrollment approved',
            "Your enrollment for $term has been approved." . ($remarks !== '' ? " Remarks: $remarks" : ''), 'student/enrollment.php');
    } else {
        notify_user($pdo, (int)$en['user_id'], 'danger', 'Enrollment rejected',
            "Your enrollment for $term was rejected. Reason: $remarks", 'student/enrollment.php');
    }
    $pdo->commit();

    log_activity('enrollment_' . $status, "Application #$id ({$en['student_no']}) $status");
    set_flash('success', 'Application ' . $status . '. The student has been notified.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Enrollment action failed: ' . $e->getMessage());
    set_flash('danger', 'A database error occurred. Nothing was changed.');
}
redirect($back);
