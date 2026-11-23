<?php
require_once __DIR__ . '/../includes/auth_check.php';
$user = require_role('teacher');
$role = 'teacher';
require __DIR__ . '/../includes/profile_page.php';
