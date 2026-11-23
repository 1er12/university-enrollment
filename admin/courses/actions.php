<?php
/** Course actions: save (add/edit + prerequisites), toggle availability, delete. POST + CSRF only. */
require_once __DIR__ . '/../../includes/auth_check.php';
$admin = require_role('admin');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    set_flash('danger', 'Invalid request. Please try again.');
    redirect('admin/courses/index.php');
}
$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

/** True if $courseId requires $targetId, directly or through a chain of prerequisites. */
function course_depends_on(PDO $pdo, int $courseId, int $targetId): bool
{
    $stmt = $pdo->prepare('SELECT prerequisite_id FROM course_prerequisites WHERE course_id = ?');
    $queue = [$courseId]; $seen = [];
    while ($queue) {
        $cur = array_shift($queue);
        if (isset($seen[$cur])) continue;
        $seen[$cur] = true;
        $stmt->execute([$cur]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $pre) {
            if ((int)$pre === $targetId) return true;
            $queue[] = (int)$pre;
        }
    }
    return false;
}

try {
    switch ($action) {

    case 'save':
        $in = [
            'id'          => $id,
            'code'        => strtoupper(clean($_POST['code'] ?? '')),
            'title'       => clean($_POST['title'] ?? ''),
            'description' => clean($_POST['description'] ?? ''),
            'units'       => clean($_POST['units'] ?? ''),
            'year_level'  => clean($_POST['year_level'] ?? ''),
            'program_id'  => clean($_POST['program_id'] ?? ''),
            'status'      => clean($_POST['status'] ?? ''),
            'prereq'      => array_values(array_unique(array_filter(array_map('intval', (array)($_POST['prereq'] ?? []))))),
        ];
        $errors = [];
        if ($id && !scalar($pdo, 'SELECT COUNT(*) FROM courses WHERE id = ?', [$id])) { set_flash('danger', 'Course not found.'); redirect('admin/courses/index.php'); }

        if (!preg_match('/^[A-Z0-9\- ]{2,20}$/', $in['code'])) $errors[] = 'Course code must be 2-20 characters: letters, numbers, spaces or dashes.';
        if ($in['title'] === '' || mb_strlen($in['title']) > 150) $errors[] = 'Title is required (max 150 characters).';
        if (!ctype_digit($in['units']) || (int)$in['units'] < 1 || (int)$in['units'] > 12) $errors[] = 'Units must be a whole number from 1 to 12.';
        if ($in['year_level'] !== '' && !in_array((int)$in['year_level'], range(1, 6), true)) $errors[] = 'Year level must be 1 to 6.';
        if ($in['program_id'] !== '' && !scalar($pdo, "SELECT COUNT(*) FROM programs WHERE id = ? AND status = 'active'", [(int)$in['program_id']])) $errors[] = 'Please select a valid program.';
        if (!in_array($in['status'], ['available', 'unavailable'], true)) $errors[] = 'Invalid availability.';
        if (!$errors && scalar($pdo, 'SELECT COUNT(*) FROM courses WHERE code = ? AND id <> ?', [$in['code'], $id])) $errors[] = 'That course code already exists.';

        if ($in['prereq']) {
            $ph = implode(',', array_fill(0, count($in['prereq']), '?'));
            if ((int)scalar($pdo, "SELECT COUNT(*) FROM courses WHERE id IN ($ph)", $in['prereq']) !== count($in['prereq'])) {
                $errors[] = 'One or more prerequisites no longer exist.';
            } elseif ($id) {
                foreach ($in['prereq'] as $pre) {
                    if ($pre === $id) { $errors[] = 'A course cannot be its own prerequisite.'; break; }
                    if (course_depends_on($pdo, $pre, $id)) {
                        $code = scalar($pdo, 'SELECT code FROM courses WHERE id = ?', [$pre]);
                        $errors[] = "$code already requires this course, so it would create a circular prerequisite.";
                    }
                }
            }
        }
        if ($errors) {
            $_SESSION['course_form'] = ['data' => $in, 'errors' => array_values($errors)];
            redirect('admin/courses/index.php');
        }

        $year = $in['year_level'] !== '' ? (int)$in['year_level'] : null;
        $prog = $in['program_id'] !== '' ? (int)$in['program_id'] : null;
        $pdo->beginTransaction();
        if ($id) {
            $pdo->prepare('UPDATE courses SET code=?, title=?, description=?, units=?, year_level=?, program_id=?, status=? WHERE id=?')
                ->execute([$in['code'], $in['title'], $in['description'] ?: null, (int)$in['units'], $year, $prog, $in['status'], $id]);
            $pdo->prepare('DELETE FROM course_prerequisites WHERE course_id = ?')->execute([$id]);
        } else {
            $pdo->prepare('INSERT INTO courses (code, title, description, units, year_level, program_id, status) VALUES (?,?,?,?,?,?,?)')
                ->execute([$in['code'], $in['title'], $in['description'] ?: null, (int)$in['units'], $year, $prog, $in['status']]);
            $id = (int)$pdo->lastInsertId();
        }
        $ins = $pdo->prepare('INSERT INTO course_prerequisites (course_id, prerequisite_id) VALUES (?, ?)');
        foreach ($in['prereq'] as $pre) $ins->execute([$id, $pre]);
        $pdo->commit();

        log_activity($_POST['id'] ? 'course_updated' : 'course_created', "Course {$in['code']}");
        set_flash('success', $_POST['id'] ? 'Course updated successfully.' : 'Course created successfully.');
        break;

    case 'toggle':
        $c = $pdo->prepare('SELECT code, status FROM courses WHERE id = ?'); $c->execute([$id]); $c = $c->fetch();
        if (!$c) { set_flash('danger', 'Course not found.'); break; }
        $new = $c['status'] === 'available' ? 'unavailable' : 'available';
        $pdo->prepare('UPDATE courses SET status = ? WHERE id = ?')->execute([$new, $id]);
        log_activity('course_' . $new, "Course {$c['code']}");
        set_flash('success', "{$c['code']} is now $new.");
        break;

    case 'delete':
        $c = $pdo->prepare('SELECT code FROM courses WHERE id = ?'); $c->execute([$id]); $code = $c->fetchColumn();
        if (!$code) { set_flash('danger', 'Course not found.'); break; }
        if (scalar($pdo, 'SELECT COUNT(*) FROM subjects WHERE course_id = ?', [$id])) {
            set_flash('warning', "$code has been offered as a subject and cannot be deleted. Mark it unavailable instead.");
            break;
        }
        if (scalar($pdo, 'SELECT COUNT(*) FROM course_prerequisites WHERE prerequisite_id = ?', [$id])) {
            set_flash('warning', "$code is a prerequisite of other courses. Remove it from those courses first.");
            break;
        }
        $pdo->prepare('DELETE FROM courses WHERE id = ?')->execute([$id]);
        log_activity('course_deleted', "Course $code");
        set_flash('success', 'Course deleted.');
        break;

    default:
        set_flash('danger', 'Unknown action.');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Course action failed: ' . $e->getMessage());
    set_flash('danger', $e->getCode() === '23000' ? 'That course code already exists.' : 'A database error occurred. Nothing was changed.');
}
redirect('admin/courses/index.php');
