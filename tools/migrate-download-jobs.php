#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MediaConfig;
use MediaDatabase;

Config::load(__DIR__ . '/..');

$db = Database::connection();

$sql = file_get_contents(
    __DIR__ . '/../database/migrations/002_download_jobs.sql'
);

if ($sql === false) {
    fwrite(STDERR, "Unable to read download jobs migration.\n");
    exit(1);
}

try {
    $db->exec($sql);
    fwrite(STDOUT, "Download jobs table created successfully.\n");
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        fwrite(STDOUT, "Download jobs table already exists.\n");
        exit(0);
    }

    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
