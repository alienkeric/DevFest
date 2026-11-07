<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

$me = require_login();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($message === '') {
        $error = 'Please write a message before submitting.';
    } else {

        $stmt = db()->prepare('INSERT INTO feedback (user_id, message) VALUES (?, ?)');
        $stmt->execute([$me['id'], $message]);

        flash('success', 'Thanks! Your feedback is now visible to everyone.');
        redirect('/feedback.php');
    }
}

$feedback = db()->query(
    'SELECT f.id, f.message, f.created_at, u.username, u.full_name
     FROM feedback f
     JOIN users u ON u.id = f.user_id
     ORDER BY f.id DESC'
)->fetchAll();

$pageTitle = 'Feedback';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">// community wall</span>
        <h1>Feedback</h1>
        <p>Tell the community what you think. Everything is shown on this page.</p>
    </div>
</div>

<div class="grid grid--2">
    <section class="card">
        <h2>Leave a note</h2>
        <p class="muted">Feedback is stored and displayed to every signed-in attendee.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/feedback.php" class="stack">
            <label class="field">
                <span>Your message</span>
                <textarea name="message" maxlength="1000" placeholder="Share your thoughts..." required></textarea>
                <small>Plain text only. 1000 characters max.</small>
            </label>
            <button class="btn" type="submit">Post feedback</button>
        </form>
    </section>

    <section class="card">
        <h2>What people are saying</h2>

        <?php if ($feedback === []): ?>
            <div class="empty-state">No feedback yet. Be the first.</div>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($feedback as $item): ?>
                    <article class="feedback-item">
                        <header>
                            <strong><?= e($item['full_name'] ?: $item['username']) ?> <span class="muted">@<?= e($item['username']) ?></span></strong>
                            <time datetime="<?= e($item['created_at']) ?>"><?= e(nice_date((string) $item['created_at'])) ?></time>
                        </header>
                        <?php

                        ?>
                        <p><?= $item['message'] ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
