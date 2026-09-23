#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MediaConfig;
use MediaDatabase;
use MediaDownloadJob;

Config::load(__DIR__ . '/..');

$db = Database::connection();
$jobs = new DownloadJob($db);

$requeued = $jobs->requeueStale(30);

if ($requeued > 0) {
    error_log(
        'MediaBuilder download cleanup requeued '
        . $requeued
        . ' stale job(s).'
    );
}

foreach ($jobs->expired() as $job) {
    $archivePath = (string) ($job['archive_path'] ?? '');

    if ($archivePath !== '' && is_file($archivePath)) {
        @unlink($archivePath);
    }

    $jobDir = $archivePath !== ''
        ? dirname($archivePath)
        : '';

    if ($jobDir !== '' && $jobDir !== '.' && is_dir($jobDir)) {
        foreach (glob($jobDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($jobDir);
    }

    $jobs->expire((int) $job['id']);
}
