<?php
/** Role-based sidebar. Pages that are not built yet show as "soon". */
$menus = [
  'admin' => [
    ['Dashboard',   'bi-speedometer2',   'admin/dashboard.php',            'admin/dashboard.php'],
    ['Students',    'bi-people',         'admin/students/index.php',       'admin/students/'],
    ['Teachers',    'bi-person-workspace','admin/teachers/index.php',      'admin/teachers/'],
    ['Courses',     'bi-journal-bookmark','admin/courses/index.php',       'admin/courses/'],
    ['Subjects',    'bi-book',           'admin/subjects/index.php',       'admin/subjects/'],
    ['Enrollments', 'bi-clipboard-check','admin/enrollments/index.php',    'admin/enrollments/'],
    ['Documents',   'bi-file-earmark-check','admin/documents/index.php',   'admin/documents/'],
    ['Academic',    'bi-calendar3',      'admin/settings/academic.php',    'admin/settings/academic'],
    ['Reports',     'bi-bar-chart-line', 'admin/reports/index.php',        'admin/reports/'],
    ['Settings',    'bi-gear',           'admin/settings/index.php',       'admin/settings/'],
  ],
  'student' => [
    ['Dashboard',  'bi-speedometer2',   'student/dashboard.php',          'student/dashboard.php'],
    ['My Profile', 'bi-person',         'student/profile.php',            'student/profile.php'],
    ['Enrollment', 'bi-pencil-square',  'student/enrollment.php',         'student/enrollment.php'],
    ['History',    'bi-clock-history',  'student/enrollment-history.php', 'student/enrollment-history.php'],
    ['Notifications','bi-bell',         'student/notifications.php',      'student/notifications.php'],
    ['Documents',  'bi-folder2-open',   'student/documents.php',          'student/documents.php'],
  ],
  'teacher' => [
    ['Dashboard',  'bi-speedometer2',   'teacher/dashboard.php',          'teacher/dashboard.php'],
    ['My Profile', 'bi-person',         'teacher/profile.php',            'teacher/profile.php'],
    ['My Subjects','bi-book',           'teacher/my-subjects.php',        'teacher/my-subjects.php'],
    ['Students',   'bi-people',         'teacher/students.php',           'teacher/students.php'],
    ['Schedules',  'bi-calendar-week',  'teacher/schedules.php',          'teacher/schedules.php'],
    ['Announcements','bi-megaphone',    'teacher/announcements.php',      'teacher/announcements.php'],
  ],
];
$current = ltrim(substr($_SERVER['SCRIPT_NAME'], strlen(BASE_URL)), '/');
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand"><i class="bi bi-mortarboard-fill text-info"></i><span class="label ms-2">UniEnroll</span></div>
  <ul class="nav flex-column sidebar-nav">
    <?php foreach ($menus[$user['role']] ?? [] as [$label, $icon, $path, $match]): ?>
      <?php $exists = file_exists(ROOT_PATH . '/' . $path); $active = str_starts_with($current, $match) && !($match === 'admin/settings/' && str_starts_with($current, 'admin/settings/academic')); ?>
      <li class="nav-item">
        <a class="nav-link<?= $active ? ' active' : '' ?><?= $exists ? '' : ' disabled' ?>"
           href="<?= $exists ? e(url($path)) : '#' ?>" title="<?= e($label) ?>">
          <i class="bi <?= e($icon) ?>"></i><span class="label"><?= e($label) ?></span>
          <?php if (!$exists): ?><span class="badge bg-secondary ms-auto label">soon</span><?php endif; ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
