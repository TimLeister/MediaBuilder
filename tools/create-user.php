<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

use Media\Config;
use Media\Database;

Config::load(__DIR__ . '/..');

$email = trim((string) ($argv[1] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/create-user.php email@example.com\n");
    exit(1);
}

$password = trim((string) readline("Password: "));

if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$db = Database::connection();

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare(
    'INSERT INTO users (email, password_hash)
     VALUES (:email, :password_hash)
     ON DUPLICATE KEY UPDATE
        password_hash = VALUES(password_hash),
        updated_at = CURRENT_TIMESTAMP'
);

$stmt->execute([
    'email' => strtolower($email),
    'password_hash' => $hash,
]);

fwrite(STDOUT, "User created/updated: " . strtolower($email) . "\n");
