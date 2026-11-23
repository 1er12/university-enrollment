<?php
require_once __DIR__ . '/config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect(dashboard_path($_SESSION['role'] ?? ''));
}
redirect('auth/login.php');
