<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

$me = require_admin();

$users = db()->query(
    'SELECT id, username, full_name, email, role, created_at FROM users ORDER BY id'
)->fetchAll();

$feedbackCount = (int) db()->query('SELECT COUNT(*) FROM feedback')->fetchColumn();

$pageTitle = 'Admin console';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">// restricted area</span>
        <h1>Admin console</h1>
        <p>This area is restricted to administrators.</p>
    </div>
    <span class="chip chip--admin">admin only</span>
</div>

<div class="grid grid--2">
    <article class="card">
        <h2>Admin tools</h2>
        <p class="muted">Review accounts and keep the workspace tidy.</p>
        <div class="btn-row">
            <a class="btn btn--ghost" href="/feedback.php">Review feedback</a>
            <a class="btn btn--ghost" href="/profile.php?id=<?= (int) $me['id'] ?>">My profile</a>
        </div>
    </article>

    <article class="card">
        <h2>Workspace stats</h2>
        <div class="grid grid--3">
            <div class="kpi">
                <strong><?= count($users) ?></strong>
                <span>Users</span>
            </div>
            <div class="kpi">
                <strong><?= $feedbackCount ?></strong>
                <span>Feedback</span>
            </div>
            <div class="kpi">
                <strong>v1.0</strong>
                <span>Release</span>
            </div>
        </div>
    </article>
</div>

<article class="card">
    <h2>Registered users</h2>
    <p class="muted">Administrator view of registered accounts.</p>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Joined</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td class="mono">#<?= (int) $user['id'] ?></td>
                    <td><a href="/profile.php?id=<?= (int) $user['id'] ?>">@<?= e($user['username']) ?></a></td>
                    <td><?= e($user['full_name'] ?: '—') ?></td>
                    <td><?= e($user['email'] ?: '—') ?></td>
                    <td><span class="chip <?= $user['role'] === 'admin' ? 'chip--admin' : '' ?>"><?= e($user['role']) ?></span></td>
                    <td class="muted"><?= e(nice_date((string) $user['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
