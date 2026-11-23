<?php
/** Enrollment business rules shared by the student module (and reusable elsewhere). */

function student_by_user(PDO $pdo, int $userId): ?array
{
    $s = $pdo->prepare('SELECT s.*, p.code AS program_code, p.name AS program_name, u.email, u.username
                        FROM students s JOIN programs p ON p.id = s.program_id JOIN users u ON u.id = s.user_id WHERE s.user_id = ?');
    $s->execute([$userId]);
    return $s->fetch() ?: null;
}

function teacher_by_user(PDO $pdo, int $userId): ?array
{
    $s = $pdo->prepare('SELECT t.*, d.name AS department_name, u.email, u.username
                        FROM teachers t LEFT JOIN departments d ON d.id = t.department_id JOIN users u ON u.id = t.user_id WHERE t.user_id = ?');
    $s->execute([$userId]);
    return $s->fetch() ?: null;
}

/** Course ids this student has already passed. */
function passed_course_ids(PDO $pdo, int $studentId): array
{
    $s = $pdo->prepare("SELECT DISTINCT sb.course_id FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id
                        JOIN subjects sb ON sb.id = ed.subject_id WHERE e.student_id = ? AND ed.result = 'passed'");
    $s->execute([$studentId]);
    return array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
}

/** subject_id => schedule rows, ordered by day and time. */
function subject_schedule_rows(PDO $pdo, array $subjectIds): array
{
    $subjectIds = array_values(array_unique(array_map('intval', $subjectIds)));
    if (!$subjectIds) return [];
    $ph = implode(',', array_fill(0, count($subjectIds), '?'));
    $s = $pdo->prepare("SELECT * FROM subject_schedules WHERE subject_id IN ($ph)
                        ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time");
    $s->execute($subjectIds);
    $out = [];
    foreach ($s->fetchAll() as $r) $out[$r['subject_id']][] = $r;
    return $out;
}

function schedules_overlap(array $rowsA, array $rowsB): bool
{
    foreach ($rowsA as $a) foreach ($rowsB as $b) {
        if ($a['day_of_week'] === $b['day_of_week'] && $a['start_time'] < $b['end_time'] && $a['end_time'] > $b['start_time']) return true;
    }
    return false;
}

/** [bool open, string message] for the semester's enrollment period. */
function enrollment_window(?array $sem): array
{
    if (!$sem) return [false, 'There is no active semester right now.'];
    $today = date('Y-m-d');
    $start = $sem['enrollment_start']; $end = $sem['enrollment_end'];
    if ($start && $today < $start) return [false, 'Enrollment opens on ' . format_date($start) . '.'];
    if ($end && $today > $end)     return [false, 'Enrollment closed on ' . format_date($end) . '.'];
    return [true, $end ? 'Enrollment is open until ' . format_date($end) . '.' : 'Enrollment is open.'];
}

function get_or_create_enrollment(PDO $pdo, int $studentId, int $semesterId): array
{
    $q = $pdo->prepare('SELECT * FROM enrollments WHERE student_id = ? AND semester_id = ?');
    $q->execute([$studentId, $semesterId]);
    if ($row = $q->fetch()) return $row;
    try {
        $pdo->prepare("INSERT INTO enrollments (student_id, semester_id, status) VALUES (?, ?, 'draft')")->execute([$studentId, $semesterId]);
    } catch (PDOException $e) { /* created concurrently */ }
    $q->execute([$studentId, $semesterId]);
    return $q->fetch();
}

/** Subjects currently selected in an enrollment. */
function enrollment_selected(PDO $pdo, int $enrollmentId): array
{
    $s = $pdo->prepare("SELECT d.id AS detail_id, d.subject_id, d.units, sb.section, c.id AS course_id, c.code, c.title,
                               CONCAT(t.first_name, ' ', t.last_name) AS teacher_name
                        FROM enrollment_details d JOIN subjects sb ON sb.id = d.subject_id JOIN courses c ON c.id = sb.course_id
                        LEFT JOIN teachers t ON t.id = sb.teacher_id WHERE d.enrollment_id = ? ORDER BY c.code");
    $s->execute([$enrollmentId]);
    return $s->fetchAll();
}

/**
 * Validate one subject for a student against the enrollment rules.
 * $otherIds = the other subjects already selected (not including $subjectId).
 * Returns a list of error messages (empty = OK).
 */
function check_subject_rules(PDO $pdo, array $student, array $sem, int $subjectId, array $otherIds, array $passed): array
{
    $q = $pdo->prepare('SELECT sb.*, c.code, c.title, c.units, c.program_id, c.status AS course_status
                        FROM subjects sb JOIN courses c ON c.id = sb.course_id WHERE sb.id = ? AND sb.semester_id = ?');
    $q->execute([$subjectId, $sem['id']]);
    $sb = $q->fetch();
    if (!$sb) return ['That subject is not offered this semester.'];

    $label = $sb['code'] . ' (' . $sb['section'] . ')';
    $errors = [];
    if ($sb['status'] !== 'open' || $sb['course_status'] !== 'available') $errors[] = "$label is closed for enrollment.";
    if ($sb['program_id'] && (int)$sb['program_id'] !== (int)$student['program_id']) $errors[] = "$label is not part of your program.";
    if (in_array((int)$sb['course_id'], $passed, true)) $errors[] = "You have already passed {$sb['code']}.";

    // prerequisites
    $p = $pdo->prepare('SELECT c.id, c.code FROM course_prerequisites cp JOIN courses c ON c.id = cp.prerequisite_id WHERE cp.course_id = ?');
    $p->execute([$sb['course_id']]);
    $missing = [];
    foreach ($p->fetchAll() as $pre) if (!in_array((int)$pre['id'], $passed, true)) $missing[] = $pre['code'];
    if ($missing) $errors[] = "$label requires you to pass: " . implode(', ', $missing) . '.';

    // capacity (approved students only; pending applications don't hold a slot)
    $taken = (int)scalar($pdo, "SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id
                                WHERE ed.subject_id = ? AND e.status = 'approved'", [$subjectId]);
    if ($taken >= (int)$sb['max_students']) $errors[] = "$label is full.";

    // duplicate course / schedule conflicts against the other selected subjects
    if ($otherIds) {
        $ph = implode(',', array_fill(0, count($otherIds), '?'));
        $o = $pdo->prepare("SELECT sb.id, sb.course_id, c.code FROM subjects sb JOIN courses c ON c.id = sb.course_id WHERE sb.id IN ($ph)");
        $o->execute(array_values($otherIds));
        $others = $o->fetchAll();
        $sched = subject_schedule_rows($pdo, array_merge([$subjectId], $otherIds));
        foreach ($others as $ot) {
            if ((int)$ot['course_id'] === (int)$sb['course_id']) $errors[] = "You already selected {$sb['code']}.";
            elseif (schedules_overlap($sched[$subjectId] ?? [], $sched[$ot['id']] ?? [])) $errors[] = "$label has a schedule conflict with {$ot['code']}.";
        }
    }
    return array_values(array_unique($errors));
}
