<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/settings_nav.php';
require_once __DIR__ . '/../../includes/csv.php';
$user = require_role('admin');
$pdo = Database::getConnection();

$action = clean($_GET['action'] ?? ''); $q = clean($_GET['q'] ?? ''); $from = clean($_GET['from'] ?? ''); $to = clean($_GET['to'] ?? '');
$where = []; $params = [];
if ($action !== '') { $where[] = 'a.action = ?'; $params[] = $action; }
if ($q !== '')      { $where[] = '(u.username LIKE ? OR a.description LIKE ? OR a.ip_address LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = 'a.created_at >= ?'; $params[] = $from . ' 00:00:00'; } else $from = '';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = 'a.created_at <= ?'; $params[] = $to . ' 23:59:59'; } else $to = '';
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from_ = 'FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id';

if (($_GET['export'] ?? '') === 'csv') {
    $st = $pdo->prepare("SELECT a.created_at, COALESCE(u.username, 'system'), a.action, a.description, a.ip_address $from_ $whereSql ORDER BY a.id DESC LIMIT 5000");
    $st->execute($params);
    send_csv('activity-logs-' . date('Ymd') . '.csv', ['When', 'User', 'Action', 'Description', 'IP'], array_map('array_values', $st->fetchAll()));
}
$total = (int)scalar($pdo, "SELECT COUNT(*) $from_ $whereSql", $params);
$pg = paginate($total, (int)($_GET['page'] ?? 1), 20);
$st = $pdo->prepare("SELECT a.*, u.username $from_ $whereSql ORDER BY a.id DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}");
$st->execute($params);
$logs = $st->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$keep = array_filter(['action' => $action, 'q' => $q, 'from' => $from, 'to' => $to]);

$pageTitle = 'Activity Logs';
require_once __DIR__ . '/../../includes/header.php';
settings_nav('logs');
?>
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="get">
  <div class="col-md-3"><select class="form-select" name="action"><option value="">All actions</option><?php foreach ($actions as $a): ?><option <?= $a === $action ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><input class="form-control" name="q" placeholder="User, details or IP" value="<?= e($q) ?>"></div>
  <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="<?= e($from) ?>" title="From"></div>
  <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="<?= e($to) ?>" title="To"></div>
  <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
    <a class="btn btn-outline-primary flex-fill" title="Export CSV" href="?<?= e(http_build_query($keep + ['export' => 'csv'])) ?>"><i class="bi bi-download"></i></a></div></form></div></div>
<div class="card"><?php if (!$logs): ?><div class="empty-state"><i class="bi bi-activity"></i>No log entries found.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?><tr><td class="text-nowrap small"><?= e(format_date($l['created_at'], 'M d, Y g:i:s A')) ?></td><td><?= e($l['username'] ?? 'system') ?></td>
    <td><span class="badge text-bg-light border"><?= e($l['action']) ?></span></td><td class="text-muted small"><?= e($l['description']) ?></td><td class="small"><?= e($l['ip_address']) ?></td></tr><?php endforeach; ?></tbody></table></div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center"><small class="text-muted"><?= number_format($total) ?> entries</small><?= render_pagination($pg, $keep) ?></div><?php endif; ?></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
