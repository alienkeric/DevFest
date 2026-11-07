<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/csrf.php';

if (current_user() !== null) {
    redirect('/dashboard.php');
}

$error = '';
$old   = [
    'username'  => '',
    'full_name' => '',
    'email'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['username']  = trim((string) ($_POST['username'] ?? ''));
    $old['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $old['email']     = trim((string) ($_POST['email'] ?? ''));
    $password         = (string) ($_POST['password'] ?? '');
    $confirm          = (string) ($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $old['username'])) {
        $error = 'Username must be 3-20 characters: letters, numbers or underscore.';
    } elseif (strlen($old['full_name']) < 2 || strlen($old['full_name']) > 100) {
        $error = 'Please enter a full name between 2 and 100 characters.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 10) {
        $error = 'Password must be at least 10 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = db()->prepare(
                'INSERT INTO users (username, password, full_name, email)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$old['username'], $hash, $old['full_name'], $old['email']]);

            flash('success', 'Account created. You can sign in now.');
            redirect('/index.php');
        } catch (PDOException $exception) {
            error_log('Registration failed: ' . $exception->getMessage());

            if ($exception->getCode() === '23000') {
                $error = 'That username is already taken.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-card">
    <span class="eyebrow">// get started</span>
    <h1>Create an account</h1>
    <p class="muted">A couple of details and you are in.</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/register.php" class="stack" autocomplete="off">
        <?= csrf_field() ?>

        <label class="field">
            <span>Username</span>
            <input type="text" name="username" value="<?= e($old['username']) ?>" placeholder="yourhandle" required>
            <small>3-20 characters, letters, numbers and underscore.</small>
        </label>

        <label class="field">
            <span>Full name</span>
            <input type="text" name="full_name" value="<?= e($old['full_name']) ?>" placeholder="Ada Lovelace" required>
        </label>

        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= e($old['email']) ?>" placeholder="ada@example.com" required>
        </label>

        <label class="field">
            <span>Password</span>
            <input type="password" name="password" placeholder="At least 10 characters" required>
        </label>

        <label class="field">
            <span>Confirm password</span>
            <input type="password" name="confirm_password" placeholder="Repeat your password" required>
        </label>

        <button class="btn btn--full" type="submit">Create account</button>
    </form>

    <p class="auth-aside">
        Already registered? <a href="/index.php">Sign in</a>.
    </p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
