<?php
/**
 * Application configuration
 * Edit the values below to match your XAMPP setup.
 */

// ---- Application ----
define('APP_NAME', 'University Enrollment System');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Manila');
define('APP_DEBUG', true);            // Set to false in production

// ---- Database (XAMPP defaults) ----
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'university_enrollment_db');
define('DB_USER', 'root');
define('DB_PASS', '');                // XAMPP default root password is empty
define('DB_CHARSET', 'utf8mb4');

// ---- Paths ----
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads');

// Auto-detect the URL of the project folder (e.g. /university-enrollment)
$docRoot  = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
$rootPath = str_replace('\\', '/', ROOT_PATH);
define('BASE_URL', rtrim(str_replace($docRoot, '', $rootPath), '/'));

// ---- Uploads ----
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024);                 // 2 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'image/jpeg', 'image/png']);

// ---- Pagination ----
define('PER_PAGE', 10);
