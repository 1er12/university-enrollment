<?php
/** Reusable helper functions */

/** Escape output (XSS prevention). */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Build a URL relative to the project root. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Flash messages (shown once). Types: success, danger, warning, info */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function display_flash(): string
{
    $html = '';
    foreach (get_flash() as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
              . e($f['message'])
              . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    return $html;
}

/** CSRF protection */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return !empty($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Trim + strip tags for plain-text input. */
function clean(?string $value): string
{
    return trim(strip_tags((string)$value));
}

function format_date(?string $date, string $format = 'M d, Y'): string
{
    return $date ? date($format, strtotime($date)) : '—';
}

/* ===================== Phase 3 helpers ===================== */

/** Client IP (REMOTE_ADDR only; forwarded headers can be spoofed). */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Write an entry to the audit trail. */
function log_activity(string $action, string $description = '', ?int $userId = null): void
{
    try {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, mb_substr($description, 0, 255), client_ip()]);
    } catch (PDOException $e) {
        error_log('log_activity failed: ' . $e->getMessage());
    }
}

/** Dashboard path for a role. */
function dashboard_path(string $role): string
{
    return match ($role) {
        'admin'   => 'admin/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php',
        default   => 'auth/login.php',
    };
}

/** Returns an error message, or null when the password is acceptable. */
function validate_password(string $password): ?string
{
    if (strlen($password) < 8)                return 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Za-z]/', $password)) return 'Password must contain at least one letter.';
    if (!preg_match('/\d/', $password))       return 'Password must contain at least one number.';
    return null;
}

/**
 * Next student number, e.g. 2026-00002.
 * MUST be called inside an open transaction (uses FOR UPDATE to avoid duplicates).
 */
function generate_student_no(PDO $pdo): string
{
    $prefix = date('Y');
    $stmt = $pdo->prepare(
        'SELECT student_no FROM students WHERE student_no LIKE ? ORDER BY student_no DESC LIMIT 1 FOR UPDATE'
    );
    $stmt->execute([$prefix . '-%']);
    $last = $stmt->fetchColumn();
    $next = $last ? ((int)substr($last, 5)) + 1 : 1;
    return sprintf('%s-%05d', $prefix, $next);
}

/** Display name for a user row (students/teachers table, or username for admin). */
function load_display_name(PDO $pdo, array $user): string
{
    $table = ['student' => 'students', 'teacher' => 'teachers'][$user['role']] ?? null;
    if ($table) {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM $table WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        if ($row = $stmt->fetch()) {
            return $row['first_name'] . ' ' . $row['last_name'];
        }
    }
    return $user['username'];
}

/* ===================== Phase 4 helpers ===================== */

/** Run a query and return the first column of the first row. */
function scalar(PDO $pdo, string $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/** Currently active semester (with academic year name), or null. */
function get_active_semester(PDO $pdo): ?array
{
    $row = $pdo->query(
        'SELECT s.*, ay.name AS ay_name FROM semesters s
         JOIN academic_years ay ON ay.id = s.academic_year_id
         WHERE s.is_active = 1 ORDER BY s.id DESC LIMIT 1'
    )->fetch();
    return $row ?: null;
}

/** URL if the page exists yet, otherwise '#'. */
function page_url(string $path): string
{
    return file_exists(ROOT_PATH . '/' . $path) ? url($path) : '#';
}

/** Bootstrap badge for a status string. */
function status_badge(string $status): string
{
    $map = [
        'active' => 'success', 'approved' => 'success', 'verified' => 'success', 'open' => 'success', 'available' => 'success',
        'pending' => 'warning', 'inactive' => 'secondary', 'draft' => 'secondary', 'closed' => 'secondary',
        'unavailable' => 'secondary', 'passed' => 'success', 'failed' => 'danger', 'enrolled' => 'primary', 'dropped' => 'secondary', 'rejected' => 'danger', 'cancelled' => 'dark', 'graduated' => 'info',
    ];
    return '<span class="badge bg-' . ($map[$status] ?? 'secondary') . '-subtle text-' . ($map[$status] ?? 'secondary')
         . '-emphasis border border-' . ($map[$status] ?? 'secondary') . '-subtle text-capitalize">' . e($status) . '</span>';
}

/** Pagination math. */
function paginate(int $total, int $page, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);
    return ['total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage, 'offset' => ($page - 1) * $perPage];
}

/** Pagination links; $query keeps the current search/filter values. */
function render_pagination(array $p, array $query = []): string
{
    if ($p['pages'] <= 1) return '';
    $link = fn(int $n) => '?' . http_build_query(array_merge($query, ['page' => $n]));
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item' . ($p['page'] <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($link($p['page'] - 1)) . '">&laquo;</a></li>';
    $start = max(1, $p['page'] - 2);
    $end   = min($p['pages'], $p['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '"><a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($p['page'] >= $p['pages'] ? ' disabled' : '') . '"><a class="page-link" href="' . e($link($p['page'] + 1)) . '">&raquo;</a></li>';
    return $html . '</ul></nav>';
}

/** Next employee number, e.g. EMP-0002. Call inside a transaction. */
function generate_employee_no(PDO $pdo): string
{
    $last = $pdo->query("SELECT employee_no FROM teachers WHERE employee_no LIKE 'EMP-%' ORDER BY employee_no DESC LIMIT 1 FOR UPDATE")->fetchColumn();
    return sprintf('EMP-%04d', $last ? ((int)substr($last, 4)) + 1 : 1);
}

/* ===================== Phase 4C helpers ===================== */

const DAYS_OF_WEEK = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

function format_time(string $t): string
{
    return date('g:i A', strtotime($t));
}

/** ["Mon/Wed 8:00 AM–9:30 AM · Lab 1", ...] from subject_schedules rows (sorted by day). */
function schedule_summary(array $rows): array
{
    $groups = [];
    foreach ($rows as $r) {
        $key = $r['start_time'] . '|' . $r['end_time'] . '|' . ($r['room'] ?? '');
        $groups[$key]['days'][] = substr($r['day_of_week'], 0, 3);
        $groups[$key]['r'] = $r;
    }
    $out = [];
    foreach ($groups as $g) {
        $out[] = implode('/', $g['days']) . ' ' . format_time($g['r']['start_time']) . '–' . format_time($g['r']['end_time'])
               . (!empty($g['r']['room']) ? ' · ' . $g['r']['room'] : '');
    }
    return $out;
}

/** Create an in-app notification for a user. */
function notify_user(PDO $pdo, int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    $pdo->prepare('INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, $type, mb_substr($title, 0, 150), $message, $link]);
}

/* ===================== Phase 5-7 helpers ===================== */
const DOCUMENT_TYPES = ['Birth Certificate', 'Report Card / Form 138', 'Certificate of Good Moral Character', 'ID Photo', 'Transcript of Records', 'Other'];

/** Setting value from system_settings (cached per request). */
function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (Database::getConnection()->query('SELECT setting_key, setting_value FROM system_settings') as $r) {
                $cache[$r['setting_key']] = (string)$r['setting_value'];
            }
        } catch (PDOException $e) { /* ignore */ }
    }
    return $cache[$key] ?? $default;
}

function initials(string $first, string $last): string
{
    return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}
