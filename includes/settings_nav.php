<?php
function settings_nav(string $active): void
{
    $items = ['index' => ['University info', 'bi-building'], 'academic' => ['Academic', 'bi-calendar3'], 'announcements' => ['Announcements', 'bi-megaphone'],
              'admins' => ['Admin accounts', 'bi-shield-lock'], 'logs' => ['Activity logs', 'bi-activity']];
    echo '<ul class="nav nav-pills mb-3 gap-1 no-print">';
    foreach ($items as $k => [$label, $icon]) {
        echo '<li class="nav-item"><a class="nav-link ' . ($k === $active ? 'active' : 'bg-white border') . '" href="' . e(url("admin/settings/$k.php")) . '"><i class="bi ' . $icon . ' me-1"></i>' . e($label) . '</a></li>';
    }
    echo '</ul>';
}
