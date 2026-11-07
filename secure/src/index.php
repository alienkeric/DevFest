<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/csrf.php';

if (current_user() !== null) {
    redirect('/dashboard.php');
}

$error    = '';
$username = '';

$dummyHash = '$2y$12$TEkwc95MJQdEwT/qGWTzs.p/1uzEYMnGo2UcdFsocZltGiQe3BpCa';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    try {

        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        $valid = $user !== false && password_verify($password, (string) $user['password']);

        if ($user === false) {
            password_verify($password, $dummyHash);
        }

        if ($valid) {

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            unset($_SESSION['csrf_token']);

            redirect('/dashboard.php');
        }

        $error = 'Invalid username or password.';
    } catch (PDOException $exception) {
        error_log('Login failed: ' . $exception->getMessage());
        $error = 'Something went wrong. Please try again.';
    }
}

$pageTitle = 'Sign in';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-card">
    <h1>Welcome back</h1>
    <p class="muted">Sign in to your account.</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/index.php" class="stack" autocomplete="off">
        <?= csrf_field() ?>

        <label class="field">
            <span>Username</span>
            <input type="text" name="username" value="<?= e($username) ?>" placeholder="alice" required autofocus>
        </label>

        <label class="field">
            <span>Password</span>
            <input type="password" name="password" placeholder="Your password" required>
        </label>

        <button class="btn btn--full" type="submit">Sign in</button>
    </form>

    <p class="auth-aside">
        No account yet? <a href="/register.php">Create one</a>.
    </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
