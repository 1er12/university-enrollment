<?php
/** Academic structure actions: years, semesters, departments, programs. POST + CSRF only. */
require_once __DIR__ . '/../../includes/auth_check.php';
$admin = require_role('admin');
$pdo   = Database::getConnection();

$tabFor = ['year' => 'years', 'semester' => 'years', 'department' => 'departments', 'program' => 'programs'];
$entity = $_POST['entity'] ?? '';
$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$tab    = $tabFor[$entity] ?? 'years';
$back   = 'admin/settings/academic.php?tab=' . $tab;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null) || !isset($tabFor[$entity])) {
    set_flash('danger', 'Invalid request. Please try again.');
    redirect('admin/settings/academic.php');
}
$validDate = function (string $d): bool { $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; };
$fail = function (array $in, array $errors) use ($entity, $tab, $back) {
    $_SESSION['academic_form'] = ['entity' => $entity, 'tab' => $tab, 'data' => $in, 'errors' => array_values(array_unique($errors))];
    redirect($back);
};
$count = fn(string $sql, array $p) => (int)scalar($pdo, $sql, $p);

try {
    if ($action === 'save') {
        $in = ['id' => $id];
        foreach (array_keys($_POST) as $k) if (!in_array($k, ['csrf_token', 'action', 'entity', 'id'], true)) $in[$k] = clean($_POST[$k]);
        $errors = [];

        switch ($entity) {
        case 'year':
            if (!preg_match('/^\d{4}-\d{4}$/', $in['name'] ?? '')) $errors[] = 'Name must look like 2027-2028.';
            if (!$validDate($in['start_date'] ?? '') || !$validDate($in['end_date'] ?? '')) $errors[] = 'Enter valid start and end dates.';
            elseif ($in['start_date'] >= $in['end_date']) $errors[] = 'The end date must be after the start date.';
            if (!$errors && $count('SELECT COUNT(*) FROM academic_years WHERE name = ? AND id <> ?', [$in['name'], $id])) $errors[] = 'That academic year already exists.';
            if ($errors) $fail($in, $errors);
            if ($id) $pdo->prepare('UPDATE academic_years SET name=?, start_date=?, end_date=? WHERE id=?')->execute([$in['name'], $in['start_date'], $in['end_date'], $id]);
            else     $pdo->prepare('INSERT INTO academic_years (name, start_date, end_date) VALUES (?,?,?)')->execute([$in['name'], $in['start_date'], $in['end_date']]);
            $label = 'Academic year ' . $in['name'];
            break;

        case 'semester':
            $no = (int)($in['semester_no'] ?? 0);
            if (!$count('SELECT COUNT(*) FROM academic_years WHERE id = ?', [(int)($in['academic_year_id'] ?? 0)])) $errors[] = 'Academic year not found.';
            if (($in['name'] ?? '') === '' || mb_strlen($in['name']) > 50) $errors[] = 'Name is required (max 50 characters).';
            if ($no < 1 || $no > 3) $errors[] = 'Semester number must be 1, 2 or 3.';
            if (!$validDate($in['start_date'] ?? '') || !$validDate($in['end_date'] ?? '')) $errors[] = 'Enter valid semester dates.';
            elseif ($in['start_date'] >= $in['end_date']) $errors[] = 'The semester must end after it starts.';
            $es = $in['enrollment_start'] ?? ''; $ee = $in['enrollment_end'] ?? '';
            if (($es === '') !== ($ee === '')) $errors[] = 'Set both enrollment dates, or leave both empty.';
            elseif ($es !== '' && (!$validDate($es) || !$validDate($ee) || $es > $ee)) $errors[] = 'Enrollment close date must be on or after the open date.';
            $mu = (int)($in['max_units'] ?? 0);
            if ($mu < 1 || $mu > 40) $errors[] = 'Max units must be between 1 and 40.';
            if (!$errors && $count('SELECT COUNT(*) FROM semesters WHERE academic_year_id = ? AND semester_no = ? AND id <> ?', [(int)$in['academic_year_id'], $no, $id])) $errors[] = 'That semester number already exists in this academic year.';
            if ($errors) $fail($in, $errors);
            $args = [$in['name'], $no, $in['start_date'], $in['end_date'], $es ?: null, $ee ?: null, $mu];
            if ($id) $pdo->prepare('UPDATE semesters SET name=?, semester_no=?, start_date=?, end_date=?, enrollment_start=?, enrollment_end=?, max_units=? WHERE id=?')->execute([...$args, $id]);
            else     $pdo->prepare('INSERT INTO semesters (name, semester_no, start_date, end_date, enrollment_start, enrollment_end, max_units, academic_year_id) VALUES (?,?,?,?,?,?,?,?)')->execute([...$args, (int)$in['academic_year_id']]);
            $label = 'Semester ' . $in['name'];
            break;

        case 'department':
            $in['code'] = strtoupper($in['code'] ?? '');
            if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $in['code'])) $errors[] = 'Code must be 2-20 letters, numbers or dashes.';
            if (($in['name'] ?? '') === '' || mb_strlen($in['name']) > 150) $errors[] = 'Name is required (max 150 characters).';
            if (!in_array($in['status'] ?? '', ['active', 'inactive'], true)) $errors[] = 'Invalid status.';
            if (!$errors && $count('SELECT COUNT(*) FROM departments WHERE code = ? AND id <> ?', [$in['code'], $id])) $errors[] = 'That department code already exists.';
            if ($errors) $fail($in, $errors);
            $args = [$in['code'], $in['name'], ($in['description'] ?? '') ?: null, $in['status']];
            if ($id) $pdo->prepare('UPDATE departments SET code=?, name=?, description=?, status=? WHERE id=?')->execute([...$args, $id]);
            else     $pdo->prepare('INSERT INTO departments (code, name, description, status) VALUES (?,?,?,?)')->execute($args);
            $label = 'Department ' . $in['code'];
            break;

        case 'program':
            $in['code'] = strtoupper($in['code'] ?? '');
            if (!$count("SELECT COUNT(*) FROM departments WHERE id = ? AND status = 'active'", [(int)($in['department_id'] ?? 0)])) $errors[] = 'Please select an active department.';
            if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $in['code'])) $errors[] = 'Code must be 2-20 letters, numbers or dashes.';
            if (($in['name'] ?? '') === '' || mb_strlen($in['name']) > 150) $errors[] = 'Name is required (max 150 characters).';
            $dy = (int)($in['duration_years'] ?? 0);
            if ($dy < 1 || $dy > 6) $errors[] = 'Year levels must be between 1 and 6.';
            if (!in_array($in['status'] ?? '', ['active', 'inactive'], true)) $errors[] = 'Invalid status.';
            if (!$errors && $count('SELECT COUNT(*) FROM programs WHERE code = ? AND id <> ?', [$in['code'], $id])) $errors[] = 'That program code already exists.';
            if ($errors) $fail($in, $errors);
            $args = [(int)$in['department_id'], $in['code'], $in['name'], $dy, $in['status']];
            if ($id) $pdo->prepare('UPDATE programs SET department_id=?, code=?, name=?, duration_years=?, status=? WHERE id=?')->execute([...$args, $id]);
            else     $pdo->prepare('INSERT INTO programs (department_id, code, name, duration_years, status) VALUES (?,?,?,?,?)')->execute($args);
            $label = 'Program ' . $in['code'];
            break;
        }
        log_activity($entity . ($id ? '_updated' : '_created'), $label);
        set_flash('success', ucfirst($entity) . ($id ? ' updated.' : ' created.'));

    } elseif ($action === 'activate' && $entity === 'semester') {
        $s = $pdo->prepare('SELECT * FROM semesters WHERE id = ?'); $s->execute([$id]); $sem = $s->fetch();
        if (!$sem) { set_flash('danger', 'Semester not found.'); redirect($back); }
        $pdo->beginTransaction();
        $pdo->exec('UPDATE semesters SET is_active = 0');
        $pdo->exec('UPDATE academic_years SET is_active = 0');
        $pdo->prepare('UPDATE semesters SET is_active = 1 WHERE id = ?')->execute([$id]);
        $pdo->prepare('UPDATE academic_years SET is_active = 1 WHERE id = ?')->execute([$sem['academic_year_id']]);
        $pdo->commit();
        log_activity('semester_activated', $sem['name'] . ' (semester #' . $id . ')');
        set_flash('success', 'Semester set as the active term.');

    } elseif ($action === 'delete') {
        $blocked = null;
        switch ($entity) {
        case 'year':
            if ($count('SELECT COUNT(*) FROM semesters WHERE academic_year_id = ?', [$id])) $blocked = 'Delete this year’s semesters first.';
            else $pdo->prepare('DELETE FROM academic_years WHERE id = ?')->execute([$id]);
            break;
        case 'semester':
            if ($count('SELECT COUNT(*) FROM semesters WHERE id = ? AND is_active = 1', [$id])) $blocked = 'The active semester cannot be deleted. Activate another one first.';
            elseif ($count('SELECT COUNT(*) FROM subjects WHERE semester_id = ?', [$id]) || $count('SELECT COUNT(*) FROM enrollments WHERE semester_id = ?', [$id])) $blocked = 'This semester has subjects or enrollment records and cannot be deleted.';
            else $pdo->prepare('DELETE FROM semesters WHERE id = ?')->execute([$id]);
            break;
        case 'department':
            if ($count('SELECT COUNT(*) FROM programs WHERE department_id = ?', [$id]) || $count('SELECT COUNT(*) FROM teachers WHERE department_id = ?', [$id])) $blocked = 'This department still has programs or teachers. Mark it inactive instead.';
            else $pdo->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
            break;
        case 'program':
            if ($count('SELECT COUNT(*) FROM students WHERE program_id = ?', [$id]) || $count('SELECT COUNT(*) FROM courses WHERE program_id = ?', [$id])) $blocked = 'This program has students or courses. Mark it inactive instead.';
            else $pdo->prepare('DELETE FROM programs WHERE id = ?')->execute([$id]);
            break;
        }
        if ($blocked) set_flash('warning', $blocked);
        else { log_activity($entity . '_deleted', "$entity #$id"); set_flash('success', ucfirst($entity) . ' deleted.'); }
    } else {
        set_flash('danger', 'Unknown action.');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Academic action failed: ' . $e->getMessage());
    set_flash('danger', $e->getCode() === '23000' ? 'That record conflicts with an existing one (duplicate or still in use).' : 'A database error occurred. Nothing was changed.');
}
redirect($back);
