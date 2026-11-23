<?php
/** Report builders. Each returns [title, headers[], rows[][]]. */
function report_types(): array
{
    return ['enrollment' => 'Enrollment statistics', 'students' => 'Students', 'teachers' => 'Teachers', 'subjects' => 'Subjects'];
}

function build_report(PDO $pdo, string $type, int $semId, int $programId): array
{
    switch ($type) {
    case 'students':
        $sql = "SELECT s.student_no, CONCAT(s.last_name, ', ', s.first_name), p.code, s.year_level, u.email, COALESCE(s.phone, ''), s.status, COALESCE(e.status, '—')
                FROM students s JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
                LEFT JOIN enrollments e ON e.student_id = s.id AND e.semester_id = ?" . ($programId ? ' WHERE s.program_id = ?' : '') . ' ORDER BY s.last_name, s.first_name';
        $st = $pdo->prepare($sql); $st->execute($programId ? [$semId, $programId] : [$semId]);
        return ['Student Enrollment Report', ['Student No.', 'Name', 'Program', 'Year', 'Email', 'Phone', 'Account status', 'Enrollment (selected term)'], $st->fetchAll(PDO::FETCH_NUM)];

    case 'teachers':
        $st = $pdo->prepare("SELECT t.employee_no, CONCAT(t.last_name, ', ', t.first_name), COALESCE(d.code, '—'), u.email, COALESCE(t.phone, ''), t.status,
                (SELECT COUNT(*) FROM subjects sb WHERE sb.teacher_id = t.id AND sb.semester_id = ?),
                (SELECT COUNT(DISTINCT e.student_id) FROM subjects sb JOIN enrollment_details ed ON ed.subject_id = sb.id
                   JOIN enrollments e ON e.id = ed.enrollment_id AND e.status = 'approved' WHERE sb.teacher_id = t.id AND sb.semester_id = ?)
            FROM teachers t JOIN users u ON u.id = t.user_id LEFT JOIN departments d ON d.id = t.department_id ORDER BY t.last_name, t.first_name");
        $st->execute([$semId, $semId]);
        return ['Teacher Report', ['Employee No.', 'Name', 'Department', 'Email', 'Phone', 'Status', 'Subjects (term)', 'Students (term)'], $st->fetchAll(PDO::FETCH_NUM)];

    case 'subjects':
        $st = $pdo->prepare("SELECT sb.id, c.code, c.title, sb.section, c.units, COALESCE(CONCAT(t.first_name, ' ', t.last_name), 'Unassigned'), sb.max_students,
                (SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id WHERE ed.subject_id = sb.id AND e.status = 'approved'),
                (SELECT COUNT(*) FROM enrollment_details ed JOIN enrollments e ON e.id = ed.enrollment_id WHERE ed.subject_id = sb.id AND e.status = 'pending'), sb.status
            FROM subjects sb JOIN courses c ON c.id = sb.course_id LEFT JOIN teachers t ON t.id = sb.teacher_id WHERE sb.semester_id = ? ORDER BY c.code, sb.section");
        $st->execute([$semId]);
        $rows = $st->fetchAll(PDO::FETCH_NUM);
        $sched = subject_schedule_rows($pdo, array_column($rows, 0));
        $out = [];
        foreach ($rows as $r) {
            [$id, $code, $title, $sec, $units, $teacher, $max, $enrolled, $pending, $status] = $r;
            $out[] = [$code, $title, $sec, $units, $teacher, implode('; ', schedule_summary($sched[$id] ?? [])) ?: 'TBA', $max, $enrolled, $pending, max(0, $max - $enrolled), $status];
        }
        return ['Subject Report', ['Code', 'Title', 'Section', 'Units', 'Teacher', 'Schedule', 'Capacity', 'Enrolled', 'Pending', 'Slots left', 'Status'], $out];

    default: // enrollment statistics by program
        $cnt = fn(string $st) => "(SELECT COUNT(*) FROM enrollments e JOIN students s ON s.id = e.student_id WHERE s.program_id = p.id AND e.semester_id = ? AND e.status = '$st')";
        $st = $pdo->prepare("SELECT p.code, p.name, (SELECT COUNT(*) FROM students s WHERE s.program_id = p.id AND s.status = 'active'),
                {$cnt('pending')}, {$cnt('approved')}, {$cnt('rejected')},
                (SELECT COALESCE(SUM(e.total_units), 0) FROM enrollments e JOIN students s ON s.id = e.student_id WHERE s.program_id = p.id AND e.semester_id = ? AND e.status = 'approved')
            FROM programs p ORDER BY p.code");
        $st->execute([$semId, $semId, $semId, $semId]);
        $rows = $st->fetchAll(PDO::FETCH_NUM);
        $tot = ['TOTAL', '', 0, 0, 0, 0, 0];
        foreach ($rows as $r) for ($i = 2; $i <= 6; $i++) $tot[$i] += (int)$r[$i];
        $rows[] = $tot;
        return ['Enrollment Statistics', ['Program', 'Name', 'Active students', 'Pending', 'Approved (enrolled)', 'Rejected', 'Approved units'], $rows];
    }
}
