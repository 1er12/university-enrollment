<?php
/** Permission-checked download of student documents. */
require_once __DIR__ . '/includes/auth_check.php';
$user = require_login();
$pdo  = Database::getConnection();

$st = $pdo->prepare('SELECT d.*, s.user_id AS owner FROM student_documents d JOIN students s ON s.id = d.student_id WHERE d.id = ?');
$st->execute([(int)($_GET['id'] ?? 0)]);
$doc = $st->fetch();

$allowed = $doc && ($user['role'] === 'admin' || ($user['role'] === 'student' && (int)$doc['owner'] === (int)$user['id']));
if (!$allowed) { http_response_code(404); exit('File not found.'); }

$path = UPLOAD_PATH . '/documents/' . basename($doc['file_path']);
if (!is_file($path)) { http_response_code(404); exit('File not found.'); }

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9_.\-]/', '_', $doc['original_name']) . '"');
readfile($path);
