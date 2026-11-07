<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';

if (current_user() !== null) {
    redirect('/dashboard.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password' LIMIT 1";

    try {
        $result = db()->query($sql);
        $user   = $result->fetch();

        if ($user !== false) {

            $_SESSION['user_id'] = (int) $user['id'];
            redirect('/dashboard.php');
        }

        $error = 'Invalid username or password.';
    } catch (PDOException $exception) {

        $error = 'Database error: ' . $exception->getMessage();
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
