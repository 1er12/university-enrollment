<?php
/** Secure file uploads (no GD needed). Returns [bool ok, string filenameOrError]. */
function save_upload(array $file, array $allowedMimes, string $subdir, string $prefix): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return [false, 'Please choose a file.'];
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return [false, 'The file is too large (max 2 MB).'];
    if ($err !== UPLOAD_ERR_OK) return [false, 'Upload failed. Please try again.'];
    if ($file['size'] > MAX_UPLOAD_SIZE) return [false, 'The file is too large (max 2 MB).'];
    if (!is_uploaded_file($file['tmp_name'])) return [false, 'Invalid upload.'];

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);   // real type, not the browser's claim
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime] ?? null;
    if (!$ext || !in_array($mime, $allowedMimes, true)) {
        $names = array_map(fn($m) => strtoupper(explode('/', $m)[1] === 'jpeg' ? 'JPG' : explode('/', $m)[1]), $allowedMimes);
        return [false, 'Only ' . implode(', ', $names) . ' files are allowed.'];
    }
    if (str_starts_with($mime, 'image/') && !@getimagesize($file['tmp_name'])) return [false, 'The image appears to be corrupted.'];

    $dir = UPLOAD_PATH . '/' . $subdir;
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;      // random name: never trust the original
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return [false, 'Could not save the file.'];
    return [true, $name];
}

function delete_upload(string $subdir, ?string $name): void
{
    if ($name && preg_match('/^[A-Za-z0-9_.\-]+$/', $name)) @unlink(UPLOAD_PATH . "/$subdir/$name");
}
