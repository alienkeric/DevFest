<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);

    return $flashes;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        flash('error', 'Please sign in to continue.');
        redirect('/index.php');
    }

    return $user;
}

function is_admin(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

function initials(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return '?';
    }

    preg_match('/^./u', $name, $match);

    return strtoupper($match[0] ?? '?');
}

function nice_date(string $timestamp): string
{
    $time = strtotime($timestamp);

    return $time === false ? $timestamp : date('M j, Y \a\t H:i', $time) . ' UTC';
}
