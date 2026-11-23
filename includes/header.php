<?php
/** App layout (opens). Requires $user from require_role(). Optional: $pageTitle. */
$pageTitle = $pageTitle ?? APP_NAME;
$_pdo = Database::getConnection();
$_unread = (int)scalar($_pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$user['id']]);
$_stmt = $_pdo->prepare('SELECT title, message, type, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5');
$_stmt->execute([$user['id']]);
$_notes = $_stmt->fetchAll();
$_profile = ['student' => 'student/profile.php', 'teacher' => 'teacher/profile.php'][$user['role']] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <div class="app-main">
    <header class="topbar">
      <button class="btn btn-light btn-sm" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list fs-5"></i></button>
      <h6 class="mb-0 ms-3 fw-semibold d-none d-sm-block"><?= e($pageTitle) ?></h6>
      <div class="ms-auto d-flex align-items-center gap-2">
        <div class="dropdown">
          <button class="btn btn-light btn-sm position-relative" data-bs-toggle="dropdown" aria-label="Notifications">
            <i class="bi bi-bell fs-5"></i>
            <?php if ($_unread): ?><span class="badge rounded-pill bg-danger notif-badge"><?= $_unread > 9 ? '9+' : $_unread ?></span><?php endif; ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end p-0 notif-menu">
            <div class="px-3 py-2 border-bottom fw-semibold">Notifications</div>
            <?php if (!$_notes): ?>
              <div class="p-4 text-center text-muted small"><i class="bi bi-bell-slash d-block fs-3 mb-1"></i>No notifications yet</div>
            <?php else: foreach ($_notes as $n): ?>
              <div class="px-3 py-2 border-bottom small">
                <div class="fw-semibold"><?= e($n['title']) ?></div>
                <div class="text-muted text-truncate"><?= e($n['message']) ?></div>
                <div class="text-muted" style="font-size:.7rem"><?= e(format_date($n['created_at'], 'M d, g:i A')) ?></div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <div class="dropdown">
          <button class="btn btn-light btn-sm d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <span class="avatar"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
            <span class="d-none d-md-inline"><?= e($user['name']) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li class="dropdown-header text-capitalize"><?= e($user['role']) ?></li>
            <?php if ($_profile): ?><li><a class="dropdown-item" href="<?= e(page_url($_profile)) ?>"><i class="bi bi-person me-2"></i>My profile</a></li><?php endif; ?>
            <li><a class="dropdown-item text-danger" href="<?= e(url('auth/logout.php?token=' . csrf_token())) ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </header>
    <main class="content">
      <?= display_flash() ?>
