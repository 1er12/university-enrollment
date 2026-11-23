<?php
/** Teacher actions: save (add/edit), toggle, delete. POST + CSRF only. */
require_once __DIR__ . '/../../includes/auth_check.php';
$admin = require_role('admin');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    set_flash('danger', 'Invalid request. Please try again.');
    redirect('admin/teachers/index.php');
}
$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

function find_teacher(PDO $pdo, int $id): ?array
{
    $s = $pdo->prepare('SELECT t.*, u.username FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

try {
    switch ($action) {

    case 'save':
        $in = [];
        foreach (['first_name', 'middle_name', 'last_name', 'gender', 'birth_date', 'phone', 'address', 'department_id', 'status', 'username', 'email'] as $f) {
            $in[$f] = clean($_POST[$f] ?? '');
        }
        $in['id'] = $id;
        $password = $_POST['password'] ?? '';
        $existing = $id ? find_teacher($pdo, $id) : null;
        $errors   = [];
        if ($id && !$existing) { set_flash('danger', 'Teacher not found.'); redirect('admin/teachers/index.php'); }

        if ($in['first_name'] === '' || mb_strlen($in['first_name']) > 60) $errors[] = 'First name is required (max 60 characters).';
        if ($in['last_name']  === '' || mb_strlen($in['last_name'])  > 60) $errors[] = 'Last name is required (max 60 characters).';
        if (mb_strlen($in['middle_name']) > 60) $errors[] = 'Middle name is too long.';
        if (!in_array($in['gender'], ['', 'male', 'female', 'other'], true)) $errors[] = 'Invalid gender.';
        if ($in['birth_date'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $in['birth_date']);
            if (!$d || $d->format('Y-m-d') !== $in['birth_date'] || $d > new DateTime()) $errors[] = 'Birth date is invalid.';
        }
        if (!preg_match('/^[0-9+()\-\s]{0,30}$/', $in['phone'])) $errors[] = 'Phone may only contain digits, spaces and + - ( ).';
        if (mb_strlen($in['address']) > 255) $errors[] = 'Address is too long (max 255).';
        if ($in['department_id'] !== '' && !scalar($pdo, "SELECT COUNT(*) FROM departments WHERE id = ? AND status = 'active'", [(int)$in['department_id']])) $errors[] = 'Please select a valid department.';
        if (!in_array($in['status'], ['active', 'inactive'], true)) $errors[] = 'Invalid status.';
        if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $in['username'])) $errors[] = 'Username must be 4-30 characters: letters, numbers, dot or underscore.';
        if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['email']) > 120) $errors[] = 'A valid email is required.';
        if (!$id && $password === '') $errors[] = 'Password is required for new teachers.';
        if ($password !== '' && ($m = validate_password($password))) $errors[] = $m;

        if (!$errors) {
            $dup = $pdo->prepare('SELECT username, email FROM users WHERE (username = ? OR email = ?) AND id <> ?');
            $dup->execute([$in['username'], $in['email'], $existing['user_id'] ?? 0]);
            foreach ($dup->fetchAll() as $row) {
                if (strcasecmp($row['username'], $in['username']) === 0) $errors[] = 'That username is already taken.';
                if (strcasecmp($row['email'], $in['email']) === 0)       $errors[] = 'That email is already used by another account.';
            }
            $errors = array_unique($errors);
        }
        if ($errors) {
            $_SESSION['teacher_form'] = ['data' => $in, 'errors' => array_values($errors)];
            redirect('admin/teachers/index.php');
        }

        $gender = $in['gender'] ?: null;
        $birth  = $in['birth_date'] ?: null;
        $dept   = $in['department_id'] !== '' ? (int)$in['department_id'] : null;
        $pdo->beginTransaction();

        if ($existing) {
            $pdo->prepare('UPDATE users SET username = ?, email = ?, status = ? WHERE id = ?')
                ->execute([$in['username'], $in['email'], $in['status'], $existing['user_id']]);
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $existing['user_id']]);
            }
            $pdo->prepare('UPDATE teachers SET first_name=?, middle_name=?, last_name=?, gender=?, birth_date=?, phone=?, address=?, department_id=?, status=? WHERE id=?')
                ->execute([$in['first_name'], $in['middle_name'] ?: null, $in['last_name'], $gender, $birth, $in['phone'] ?: null, $in['address'] ?: null, $dept, $in['status'], $id]);
            $pdo->commit();
            log_activity('teacher_updated', "Updated teacher {$existing['employee_no']}");
            set_flash('success', 'Teacher updated successfully.');
        } else {
            $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'teacher', ?)")
                ->execute([$in['username'], $in['email'], password_hash($password, PASSWORD_DEFAULT), $in['status']]);
            $userId = (int)$pdo->lastInsertId();
            $empNo  = generate_employee_no($pdo);
            $pdo->prepare('INSERT INTO teachers (user_id, employee_no, first_name, middle_name, last_name, gender, birth_date, phone, address, department_id, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$userId, $empNo, $in['first_name'], $in['middle_name'] ?: null, $in['last_name'], $gender, $birth, $in['phone'] ?: null, $in['address'] ?: null, $dept, $in['status']]);
            $pdo->commit();
            log_activity('teacher_created', "Created teacher $empNo");
            set_flash('success', "Teacher created. Employee number: $empNo");
        }
        break;

    case 'toggle':
        $t = find_teacher($pdo, $id);
        if (!$t) { set_flash('danger', 'Teacher not found.'); break; }
        $new = $t['status'] === 'active' ? 'inactive' : 'active';
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE teachers SET status = ? WHERE id = ?')->execute([$new, $id]);
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $t['user_id']]);
        $pdo->commit();
        log_activity('teacher_' . ($new === 'active' ? 'activated' : 'deactivated'), "Teacher {$t['employee_no']}");
        set_flash('success', 'Teacher ' . ($new === 'active' ? 'activated' : 'deactivated') . '.');
        break;

    case 'delete':
        $t = find_teacher($pdo, $id);
        if (!$t) { set_flash('danger', 'Teacher not found.'); break; }
        if ((int)scalar($pdo, 'SELECT COUNT(*) FROM subjects WHERE teacher_id = ?', [$id]) > 0) {
            set_flash('warning', 'This teacher is assigned to subjects and cannot be deleted. Reassign the subjects first, or deactivate the account.');
            break;
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$t['user_id']]);
        log_activity('teacher_deleted', "Deleted teacher {$t['employee_no']}");
        set_flash('success', 'Teacher deleted.');
        break;

    default:
        set_flash('danger', 'Unknown action.');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Teacher action failed: ' . $e->getMessage());
    set_flash('danger', $e->getCode() === '23000' ? 'That username or email is already in use.' : 'A database error occurred. Nothing was changed.');
}
redirect('admin/teachers/index.php');
