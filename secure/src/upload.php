<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/csrf.php';

$me = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/profile.php?id=' . (int) $me['id']);
}

csrf_verify();

$file = $_FILES['avatar'] ?? null;

if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    flash('error', 'Please choose a file to upload.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

if ((int) $file['size'] > 5 * 1024 * 1024) {
    flash('error', 'The file is larger than 5 MB.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

if (!is_uploaded_file((string) $file['tmp_name'])) {
    flash('error', 'That upload does not look like a valid file.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file((string) $file['tmp_name']);

if (!is_string($mime) || !isset($allowed[$mime])) {
    flash('error', 'Only JPG, PNG, GIF or WebP images are allowed.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

$imageInfo = @getimagesize((string) $file['tmp_name']);

if ($imageInfo === false || $imageInfo[0] < 1 || $imageInfo[1] < 1) {
    flash('error', 'The file is not a valid image.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

$newName     = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
$destination = __DIR__ . '/uploads/' . $newName;

if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
    flash('error', 'The upload could not be saved.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

@chmod($destination, 0644);

try {
    $stmt = db()->prepare('UPDATE users SET avatar = ? WHERE id = ?');
    $stmt->execute([$newName, (int) $me['id']]);
} catch (PDOException $exception) {
    @unlink($destination);
    error_log('Avatar update failed: ' . $exception->getMessage());
    flash('error', 'The upload could not be saved.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

if (!empty($me['avatar'])) {
    $oldPath = __DIR__ . '/uploads/' . basename((string) $me['avatar']);

    if (is_file($oldPath)) {
        @unlink($oldPath);
    }
}

flash('success', 'Profile picture updated.');
redirect('/profile.php?id=' . (int) $me['id']);
