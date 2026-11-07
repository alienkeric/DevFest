<?php

$me        = $me ?? current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$flashes   = pull_flashes();
$current   = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="app">
<header class="topbar">
    <a class="brand" href="<?= $me ? '/dashboard.php' : '/index.php' ?>">
        <span class="brand__mark">P</span>
        <span><?= e(APP_NAME) ?></span>
    </a>

    <nav class="nav" aria-label="Main navigation">
        <?php if ($me !== null): ?>
            <a class="<?= $current === 'dashboard.php' ? 'is-active' : '' ?>" href="/dashboard.php">Dashboard</a>
            <a class="<?= $current === 'profile.php' ? 'is-active' : '' ?>" href="/profile.php?id=<?= (int) $me['id'] ?>">My profile</a>
            <a class="<?= $current === 'feedback.php' ? 'is-active' : '' ?>" href="/feedback.php">Feedback</a>
            <?php if (is_admin($me)): ?>
                <a class="<?= $current === 'admin.php' ? 'is-active' : '' ?>" href="/admin.php">Admin</a>
            <?php endif; ?>
            <a class="nav__logout" href="/logout.php">Sign out</a>
        <?php else: ?>
            <a class="<?= $current === 'index.php' ? 'is-active' : '' ?>" href="/index.php">Sign in</a>
            <a class="<?= $current === 'register.php' ? 'is-active' : '' ?>" href="/register.php">Create account</a>
        <?php endif; ?>
    </nav>
</header>

<main class="container">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert--<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
