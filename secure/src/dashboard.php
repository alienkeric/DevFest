<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

$me = require_login();

$completionFields = ['full_name', 'email', 'bio', 'avatar'];
$filled = 0;

foreach ($completionFields as $field) {
    if (trim((string) ($me[$field] ?? '')) !== '') {
        $filled++;
    }
}

$completion = (int) round(($filled / count($completionFields)) * 100);

$feedbackStmt = db()->prepare('SELECT COUNT(*) FROM feedback WHERE user_id = ?');
$feedbackStmt->execute([$me['id']]);
$feedbackCount = (int) $feedbackStmt->fetchColumn();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>

<section class="hero card">
    <span class="eyebrow">// overview</span>
    <h1>Hi, <?= e($me['full_name'] ?: $me['username']) ?>.</h1>
    <p>
        This is your account dashboard. Complete your profile, upload a picture and tell us
        what you think about the community.
    </p>
    <div class="btn-row">
        <a class="btn" href="/profile.php?id=<?= (int) $me['id'] ?>">Open my profile</a>
        <a class="btn btn--ghost" href="/feedback.php">Leave feedback</a>
    </div>
</section>

<div class="grid grid--3">
    <div class="kpi">
        <strong><?= $completion ?>%</strong>
        <span>Profile complete</span>
    </div>
    <div class="kpi">
        <strong><?= $feedbackCount ?></strong>
        <span>Feedback submitted</span>
    </div>
    <div class="kpi">
        <strong><?= e(ucfirst((string) $me['role'])) ?></strong>
        <span>Account role</span>
    </div>
</div>

<div class="grid grid--2">
    <article class="card">
        <h2>Your account</h2>
        <dl class="detail-list">
            <dt>Username</dt>
            <dd>@<?= e($me['username']) ?></dd>
            <dt>Full name</dt>
            <dd><?= e($me['full_name'] ?: 'Not set') ?></dd>
            <dt>Email</dt>
            <dd><?= e($me['email'] ?: 'Not set') ?></dd>
            <dt>Member since</dt>
            <dd><?= e(nice_date((string) $me['created_at'])) ?></dd>
        </dl>
    </article>

    <article class="card">
        <h2>Make it yours</h2>
        <p class="muted">
            Add a profile picture and a short bio so other attendees know who they are
            talking to.
        </p>
        <div class="btn-row">
            <a class="btn btn--ghost" href="/profile.php?id=<?= (int) $me['id'] ?>">Complete profile</a>
        </div>

        <?php if (is_admin($me)): ?>
            <hr class="divider">
            <h2>Admin console</h2>
            <p class="muted">You have elevated privileges.</p>
            <a class="btn" href="/admin.php">Open admin console</a>
        <?php endif; ?>
    </article>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
