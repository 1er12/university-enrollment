<?php
/** Subject offering actions: save (with schedules + conflict checks), toggle open/closed, delete. */
require_once __DIR__ . '/../../includes/auth_check.php';
$admin = require_role('admin');
$pdo   = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    set_flash('danger', 'Invalid request. Please try again.');
    redirect('admin/subjects/index.php');
}
$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

function find_subject(PDO $pdo, int $id): ?array
{
    $s = $pdo->prepare('SELECT sb.*, c.code FROM subjects sb JOIN courses c ON c.id = sb.course_id WHERE sb.id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** Teacher and room clashes with other offerings in the same semester. */
function schedule_conflicts(PDO $pdo, int $semesterId, int $subjectId, ?int $teacherId, array $rows): array
{
    $base = 'SELECT c.code, sb.section, ss.start_time, ss.end_time, ss.room
             FROM subject_schedules ss JOIN subjects sb ON sb.id = ss.subject_id JOIN courses c ON c.id = sb.course_id
             WHERE sb.semester_id = ? AND sb.id <> ? AND ss.day_of_week = ? AND ss.start_time < ? AND ss.end_time > ?';
    $errors = [];
    foreach ($rows as $r) {
        $args = [$semesterId, $subjectId, $r['day'], $r['end'] . ':00', $r['start'] . ':00'];
        if ($teacherId) {
            $q = $pdo->prepare($base . ' AND sb.teacher_id = ?');
            $q->execute([...$args, $teacherId]);
            foreach ($q->fetchAll() as $c) {
                $errors[] = "Teacher conflict on {$r['day']}: already teaching {$c['code']} ({$c['section']}) " . format_time($c['start_time']) . '–' . format_time($c['end_time']) . '.';
            }
        }
        if ($r['room'] !== '') {
            $q = $pdo->prepare($base . ' AND ss.room = ?');
            $q->execute([...$args, $r['room']]);
            foreach ($q->fetchAll() as $c) {
                $errors[] = "Room conflict on {$r['day']}: {$r['room']} is used by {$c['code']} ({$c['section']}) " . format_time($c['start_time']) . '–' . format_time($c['end_time']) . '.';
            }
        }
    }
    return array_values(array_unique($errors));
}

try {
    switch ($action) {

    case 'save':
        $existing = $id ? find_subject($pdo, $id) : null;
        if ($id && !$existing) { set_flash('danger', 'Subject not found.'); redirect('admin/subjects/index.php'); }

        $in = [
            'id'           => $id,
            'semester_id'  => $existing ? (string)$existing['semester_id'] : clean($_POST['semester_id'] ?? ''),
            'course_id'    => $existing ? (string)$existing['course_id']   : clean($_POST['course_id'] ?? ''),
            'section'      => clean($_POST['section'] ?? ''),
            'max_students' => clean($_POST['max_students'] ?? ''),
            'teacher_id'   => clean($_POST['teacher_id'] ?? ''),
            'status'       => clean($_POST['status'] ?? ''),
        ];
        // schedule rows
        $rows = []; $errors = [];
        $days = (array)($_POST['sched_day'] ?? []); $starts = (array)($_POST['sched_start'] ?? []);
        $ends = (array)($_POST['sched_end'] ?? []); $rooms = (array)($_POST['sched_room'] ?? []);
        foreach ($days as $i => $day) {
            $r = ['day' => clean($day), 'start' => clean($starts[$i] ?? ''), 'end' => clean($ends[$i] ?? ''), 'room' => clean($rooms[$i] ?? '')];
            if ($r['start'] === '' && $r['end'] === '' && $r['room'] === '') continue;        // ignore blank rows
            $timeOk = '/^([01]\d|2[0-3]):[0-5]\d$/';
            if (!in_array($r['day'], DAYS_OF_WEEK, true))                           $errors[] = 'Invalid day in schedule.';
            elseif (!preg_match($timeOk, $r['start']) || !preg_match($timeOk, $r['end'])) $errors[] = "Enter a start and end time for the {$r['day']} slot.";
            elseif ($r['start'] >= $r['end'])                                         $errors[] = "{$r['day']}: end time must be after start time.";
            elseif (mb_strlen($r['room']) > 50)                                       $errors[] = 'Room name is too long (max 50).';
            else $rows[] = $r;
        }
        // overlaps inside the same form
        foreach ($rows as $i => $a) foreach ($rows as $j => $b) {
            if ($j > $i && $a['day'] === $b['day'] && $a['start'] < $b['end'] && $a['end'] > $b['start']) {
                $errors[] = "{$a['day']}: two time slots overlap.";
            }
        }
        $in['schedules'] = array_map(fn($r) => ['day_of_week' => $r['day'], 'start_time' => $r['start'], 'end_time' => $r['end'], 'room' => $r['room']], $rows);

        // fields
        if (!$existing) {
            if (!scalar($pdo, 'SELECT COUNT(*) FROM semesters WHERE id = ?', [(int)$in['semester_id']])) $errors[] = 'Please select a semester.';
            if (!scalar($pdo, "SELECT COUNT(*) FROM courses WHERE id = ? AND status = 'available'", [(int)$in['course_id']])) $errors[] = 'Please select an available course.';
        }
        if ($in['section'] === '' || mb_strlen($in['section']) > 20) $errors[] = 'Section is required (max 20 characters).';
        if (!ctype_digit($in['max_students']) || (int)$in['max_students'] < 1 || (int)$in['max_students'] > 500) $errors[] = 'Max students must be between 1 and 500.';
        if (!in_array($in['status'], ['open', 'closed'], true)) $errors[] = 'Invalid status.';
        $teacherId = $in['teacher_id'] !== '' ? (int)$in['teacher_id'] : null;
        if ($teacherId && !scalar($pdo, "SELECT COUNT(*) FROM teachers WHERE id = ? AND (status = 'active' OR id = ?)", [$teacherId, (int)($existing['teacher_id'] ?? 0)])) {
            $errors[] = 'Please select an active teacher.';
        }
        if (!$errors) {
            if (scalar($pdo, 'SELECT COUNT(*) FROM subjects WHERE course_id = ? AND semester_id = ? AND section = ? AND id <> ?',
                       [(int)$in['course_id'], (int)$in['semester_id'], $in['section'], $id])) {
                $errors[] = 'That course already has a section "' . $in['section'] . '" in this semester.';
            }
            if ($existing) {
                $approved = (int)scalar($pdo, "SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id
                                               WHERE ed.subject_id = ? AND e.status = 'approved'", [$id]);
                if ((int)$in['max_students'] < $approved) $errors[] = "Capacity can't be lower than the $approved students already enrolled.";
            }
            $errors = array_merge($errors, schedule_conflicts($pdo, (int)$in['semester_id'], $id, $teacherId, $rows));
        }
        if ($errors) {
            $_SESSION['subject_form'] = ['data' => $in, 'errors' => array_values(array_unique($errors))];
            redirect('admin/subjects/index.php');
        }

        $pdo->beginTransaction();
        if ($existing) {
            $pdo->prepare('UPDATE subjects SET section=?, max_students=?, teacher_id=?, status=? WHERE id=?')
                ->execute([$in['section'], (int)$in['max_students'], $teacherId, $in['status'], $id]);
            $pdo->prepare('DELETE FROM subject_schedules WHERE subject_id = ?')->execute([$id]);
        } else {
            $pdo->prepare('INSERT INTO subjects (course_id, semester_id, teacher_id, section, max_students, status) VALUES (?,?,?,?,?,?)')
                ->execute([(int)$in['course_id'], (int)$in['semester_id'], $teacherId, $in['section'], (int)$in['max_students'], $in['status']]);
            $id = (int)$pdo->lastInsertId();
        }
        $ins = $pdo->prepare('INSERT INTO subject_schedules (subject_id, day_of_week, start_time, end_time, room) VALUES (?,?,?,?,?)');
        foreach ($rows as $r) $ins->execute([$id, $r['day'], $r['start'] . ':00', $r['end'] . ':00', $r['room'] ?: null]);
        $pdo->commit();

        $code = $existing['code'] ?? scalar($pdo, 'SELECT code FROM courses WHERE id = ?', [(int)$in['course_id']]);
        log_activity($existing ? 'subject_updated' : 'subject_created', "$code section {$in['section']}");
        set_flash('success', $existing ? 'Subject offering updated.' : 'Subject offering created.');
        break;

    case 'toggle':
        $s = find_subject($pdo, $id);
        if (!$s) { set_flash('danger', 'Subject not found.'); break; }
        $new = $s['status'] === 'open' ? 'closed' : 'open';
        $pdo->prepare('UPDATE subjects SET status = ? WHERE id = ?')->execute([$new, $id]);
        log_activity('subject_' . $new, "{$s['code']} section {$s['section']}");
        set_flash('success', "{$s['code']} section {$s['section']} is now $new.");
        break;

    case 'delete':
        $s = find_subject($pdo, $id);
        if (!$s) { set_flash('danger', 'Subject not found.'); break; }
        if (scalar($pdo, 'SELECT COUNT(*) FROM enrollment_details WHERE subject_id = ?', [$id])) {
            set_flash('warning', 'Students have enrolled in this subject, so it cannot be deleted. Close it instead.');
            break;
        }
        $pdo->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);
        log_activity('subject_deleted', "{$s['code']} section {$s['section']}");
        set_flash('success', 'Subject offering deleted.');
        break;

    default:
        set_flash('danger', 'Unknown action.');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Subject action failed: ' . $e->getMessage());
    set_flash('danger', $e->getCode() === '23000' ? 'That course/section already exists in this semester.' : 'A database error occurred. Nothing was changed.');
}
redirect('admin/subjects/index.php');
