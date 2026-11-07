<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

$me = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/profile.php?id=' . (int) $me['id']);
}

$file = $_FILES['avatar'] ?? null;

if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    flash('error', 'Please choose a file to upload.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

if ((int) $file['size'] > 5 * 1024 * 1024) {
    flash('error', 'The file is larger than 5 MB.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

$filename  = basename((string) $file['name']);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if ($filename === '' || !in_array($extension, $allowedExtensions, true)) {
    flash('error', 'Only JPG, PNG, GIF or WebP images are allowed.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

$destination = __DIR__ . '/uploads/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    flash('error', 'The upload could not be saved.');
    redirect('/profile.php?id=' . (int) $me['id']);
}

$stmt = db()->prepare('UPDATE users SET avatar = ? WHERE id = ?');
$stmt->execute([$filename, $me['id']]);

flash('success', 'Profile picture updated: ' . $filename);
redirect('/profile.php?id=' . (int) $me['id']);
