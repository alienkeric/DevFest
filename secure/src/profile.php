<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/csrf.php';

$me          = require_login();
$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : (int) $me['id'];
$isOwner     = $requestedId === (int) $me['id'];

if (!$isOwner && !is_admin($me)) {
    http_response_code(403);
    $pageTitle = 'Forbidden';
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="auth-card">
        <span class="eyebrow">// 403</span>
        <h1>Not your profile</h1>
        <p class="muted">You are only allowed to view your own profile.</p>
        <a class="btn" href="/profile.php?id=<?= (int) $me['id'] ?>">Go to my profile</a>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$requestedId]);
$user = $stmt->fetch();

if ($user === false) {
    http_response_code(404);
    $pageTitle = 'User not found';
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="auth-card">
        <h1>User not found</h1>
        <p class="muted">There is no profile with that id.</p>
        <a class="btn" href="/dashboard.php">Back to dashboard</a>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $isOwner ? 'My profile' : 'Profile: @' . $user['username'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">// profile #<?= (int) $user['id'] ?></span>
        <h1><?= $isOwner ? 'My profile' : 'Public profile' ?></h1>
        <p><?= $isOwner ? 'This is how other members see you.' : 'Administrator view of another member.' ?></p>
    </div>
    <a class="btn btn--ghost" href="/dashboard.php">Back to dashboard</a>
</div>

<div class="grid <?= $isOwner ? 'grid--2' : '' ?>">
    <article class="card">
        <div class="profile-head">
            <div class="avatar" aria-hidden="true">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="/uploads/<?= e(rawurlencode((string) $user['avatar'])) ?>" alt="">
                <?php else: ?>
                    <?= e(initials((string) ($user['full_name'] ?: $user['username']))) ?>
                <?php endif; ?>
            </div>
            <div>
                <h1><?= e($user['full_name'] ?: $user['username']) ?></h1>
                <p class="muted">
                    @<?= e($user['username']) ?>
                    <span class="chip <?= is_admin($user) ? 'chip--admin' : '' ?>"><?= e($user['role']) ?></span>
                </p>
            </div>
        </div>

        <dl class="detail-list">
            <dt>Username</dt>
            <dd>@<?= e($user['username']) ?></dd>
            <dt>Email</dt>
            <dd><?= e($user['email'] ?: 'Not set') ?></dd>
            <dt>Bio</dt>
            <dd><?= e($user['bio'] ?: 'No bio yet.') ?></dd>
            <dt>Member since</dt>
            <dd><?= e(nice_date((string) $user['created_at'])) ?></dd>
        </dl>

        <?php if ($isOwner): ?>
            <div class="note-box">
                <h3>Private note</h3>
                <p><?= e($user['private_note'] ?: 'No private note.') ?></p>
            </div>
        <?php endif; ?>
    </article>

    <?php if ($isOwner): ?>
        <article class="card">
            <h2>Profile picture</h2>
            <p class="muted">Upload a JPG, PNG, GIF or WebP (max 5 MB).</p>

            <form method="post" action="/upload.php" enctype="multipart/form-data" class="stack">
                <?= csrf_field() ?>

                <label class="field">
                    <span>Avatar image</span>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" required>
                </label>
                <button class="btn" type="submit">Upload picture</button>
            </form>
        </article>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
