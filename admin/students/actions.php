<?php
/** Handles save (add/edit), toggle (activate/deactivate) and delete. POST + CSRF only. */
require_once __DIR__ . '/../../includes/auth_check.php';
$admin = require_role('admin');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    set_flash('danger', 'Invalid request. Please try again.');
    redirect('admin/students/index.php');
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

/** Fetch a student with its account. */
function find_student(PDO $pdo, int $id): ?array
{
    $s = $pdo->prepare('SELECT s.*, u.username FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

try {
    switch ($action) {

    case 'save':
        $in = [];
        foreach (['first_name', 'middle_name', 'last_name', 'gender', 'birth_date', 'phone', 'address',
                  'username', 'email', 'program_id', 'year_level', 'status'] as $f) {
            $in[$f] = clean($_POST[$f] ?? '');
        }
        $in['id'] = $id;
        $password = $_POST['password'] ?? '';
        $existing = $id ? find_student($pdo, $id) : null;
        $errors   = [];

        if ($id && !$existing) { set_flash('danger', 'Student not found.'); redirect('admin/students/index.php'); }

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
        if (!preg_match('/^[A-Za-z0-9_.]{4,30}$/', $in['username'])) $errors[] = 'Username must be 4-30 characters: letters, numbers, dot or underscore.';
        if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($in['email']) > 120) $errors[] = 'A valid email is required.';
        if (!scalar($pdo, "SELECT COUNT(*) FROM programs WHERE id = ? AND status = 'active'", [(int)$in['program_id']])) $errors[] = 'Please select a valid program.';
        if (!in_array((int)$in['year_level'], range(1, 6), true)) $errors[] = 'Year level must be 1 to 6.';
        if (!in_array($in['status'], ['active', 'inactive', 'graduated'], true)) $errors[] = 'Invalid status.';

        if (!$id && $password === '')  $errors[] = 'Password is required for new students.';
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
            $_SESSION['student_form'] = ['data' => $in, 'errors' => array_values($errors)];
            redirect('admin/students/index.php');
        }

        $acctStatus = $in['status'] === 'inactive' ? 'inactive' : 'active';
        $gender = $in['gender'] ?: null;
        $birth  = $in['birth_date'] ?: null;
        $pdo->beginTransaction();

        if ($existing) {
            $pdo->prepare('UPDATE users SET username = ?, email = ?, status = ? WHERE id = ?')
                ->execute([$in['username'], $in['email'], $acctStatus, $existing['user_id']]);
            if ($password !== '') {
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $existing['user_id']]);
            }
            $pdo->prepare(
                'UPDATE students SET first_name=?, middle_name=?, last_name=?, gender=?, birth_date=?, phone=?, address=?,
                 program_id=?, year_level=?, status=? WHERE id = ?'
            )->execute([$in['first_name'], $in['middle_name'] ?: null, $in['last_name'], $gender, $birth,
                        $in['phone'] ?: null, $in['address'] ?: null, (int)$in['program_id'], (int)$in['year_level'], $in['status'], $id]);
            $pdo->commit();
            log_activity('student_updated', "Updated student {$existing['student_no']}");
            set_flash('success', 'Student updated successfully.');
        } else {
            $pdo->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'student', ?)")
                ->execute([$in['username'], $in['email'], password_hash($password, PASSWORD_DEFAULT), $acctStatus]);
            $userId    = (int)$pdo->lastInsertId();
            $studentNo = generate_student_no($pdo);
            $pdo->prepare(
                'INSERT INTO students (user_id, student_no, first_name, middle_name, last_name, gender, birth_date, phone, address, program_id, year_level, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$userId, $studentNo, $in['first_name'], $in['middle_name'] ?: null, $in['last_name'], $gender, $birth,
                        $in['phone'] ?: null, $in['address'] ?: null, (int)$in['program_id'], (int)$in['year_level'], $in['status']]);
            $pdo->commit();
            log_activity('student_created', "Created student $studentNo");
            set_flash('success', "Student created. Student number: $studentNo");
        }
        break;

    case 'toggle':
        $s = find_student($pdo, $id);
        if (!$s) { set_flash('danger', 'Student not found.'); break; }
        $new = $s['status'] === 'inactive' ? 'active' : 'inactive';
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE students SET status = ? WHERE id = ?')->execute([$new, $id]);
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $s['user_id']]);
        $pdo->commit();
        log_activity('student_' . ($new === 'active' ? 'activated' : 'deactivated'), "Student {$s['student_no']}");
        set_flash('success', 'Student ' . ($new === 'active' ? 'activated' : 'deactivated') . '.');
        break;

    case 'delete':
        $s = find_student($pdo, $id);
        if (!$s) { set_flash('danger', 'Student not found.'); break; }
        if ((int)scalar($pdo, 'SELECT COUNT(*) FROM enrollments WHERE student_id = ?', [$id]) > 0) {
            set_flash('warning', 'This student has enrollment records and cannot be deleted. Deactivate the account instead to keep the history.');
            break;
        }
        // Deleting the user cascades to the student row (and documents, notifications)
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$s['user_id']]);
        log_activity('student_deleted', "Deleted student {$s['student_no']}");
        set_flash('success', 'Student deleted.');
        break;

    default:
        set_flash('danger', 'Unknown action.');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Student action failed: ' . $e->getMessage());
    set_flash('danger', $e->getCode() === '23000' ? 'That username or email is already in use.' : 'A database error occurred. Nothing was changed.');
}
redirect('admin/students/index.php');
