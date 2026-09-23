#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Media\Config;
use Media\Database;
use Media\DownloadJob;

Config::load(__DIR__ . '/..');

$db = Database::connection();
$jobs = new DownloadJob($db);

foreach ($jobs->expired() as $job) {
    $archivePath = (string) ($job['archive_path'] ?? '');

    if ($archivePath !== '' && is_file($archivePath)) {
        @unlink($archivePath);
    }

    $jobDir = dirname($archivePath);

    if ($jobDir !== '.' && is_dir($jobDir)) {
        @rmdir($jobDir);
    }

    $jobs->expire((int) $job['id']);
}
