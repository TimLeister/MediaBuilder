<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

use Media\Auth;
use Media\Config;
use Media\Database;

Config::load(__DIR__ . '/../..');

$db = Database::connection();

if (Auth::check($db)) {
    redirect('/admin/events/');
}

Auth::startSession();

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($error !== '') {
        // Stop here when the CSRF token is invalid.
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } elseif (!Auth::attempt($db, $email, $password)) {
        $error = 'Invalid email address or password.';
    } else {
        Auth::redirectAfterLogin();
    }
}

$csrfToken = Auth::csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In - Sports Media</title>
<style>
    :root {
        --bg: #0b0b0c;
        --surface: #151517;
        --border: rgba(255,255,255,.10);
        --text: #f5f5f5;
        --muted: #a1a1a6;
        --danger: #ff8b8b;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        display: grid;
        place-items: center;
        padding: 24px;
        background:
            radial-gradient(circle at 20% 10%, rgba(255,255,255,.08), transparent 35%),
            linear-gradient(135deg, #171719, var(--bg));
        color: var(--text);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
    }
    .card {
        width: min(430px, 100%);
        padding: 36px;
        border: 1px solid var(--border);
        border-radius: 16px;
        background: rgba(21,21,23,.94);
        box-shadow: 0 30px 90px rgba(0,0,0,.35);
    }
    .eyebrow {
        margin-bottom: 10px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
    }
    h1 { margin: 0; font-size: 32px; letter-spacing: -.03em; }
    p { color: var(--muted); line-height: 1.6; }
    label {
        display: block;
        margin: 20px 0 7px;
        font-size: 13px;
        font-weight: 650;
    }
    input {
        width: 100%;
        padding: 12px 13px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: #0e0e10;
        color: var(--text);
        font: inherit;
    }
    input:focus {
        outline: 2px solid rgba(255,255,255,.18);
        outline-offset: 1px;
    }
    button {
        width: 100%;
        margin-top: 24px;
        padding: 13px 16px;
        border: 0;
        border-radius: 8px;
        background: #fff;
        color: #111;
        font-weight: 700;
        cursor: pointer;
    }
    .error {
        margin-top: 20px;
        padding: 11px 13px;
        border: 1px solid rgba(255,139,139,.22);
        border-radius: 8px;
        background: rgba(255,80,80,.08);
        color: var(--danger);
        font-size: 13px;
    }
</style>
</head>
<body>
<main class="card">
    <div class="eyebrow">MediaBuilder</div>
    <h1>Backend Sign In</h1>
    <p>Sign in to manage events, upload media, and download event archives.</p>

    <?php if ($error !== ''): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <label for="email">Email</label>
        <input
            id="email"
            name="email"
            type="email"
            value="<?= e($email) ?>"
            autocomplete="username"
            required
        >

        <label for="password">Password</label>
        <input
            id="password"
            name="password"
            type="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">Sign In</button>
    </form>
</main>
</body>
</html>
