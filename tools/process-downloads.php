#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Media\Config;
use Media\Database;
use Media\DownloadJob;
use Media\Event;
use Media\MediaFile;
use Media\Spaces;

Config::load(__DIR__ . '/..');

$db = Database::connection();
$jobs = new DownloadJob($db);

$jobId = isset($argv[1]) ? (int) $argv[1] : null;

$lockPath = sys_get_temp_dir() . '/mediabuilder-download-worker.lock';
$lockHandle = fopen($lockPath, 'c');

if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    exit(0);
}

try {
    $job = $jobs->claimNext($jobId);

    if (!$job) {
        exit(0);
    }

    $event = (new Event($db))->findById((int) $job['event_id']);

    if (!$event) {
        throw new RuntimeException('Event no longer exists.');
    }

    $mediaFiles = (new MediaFile($db))->forEvent((int) $event['id']);

    $type = $job['download_type'];

    $mediaFiles = array_values(array_filter(
        $mediaFiles,
        static function (array $media) use ($type): bool {
            if (empty($media['storage_key'])) {
                return false;
            }

            if ($media['processing_status'] !== 'complete') {
                return false;
            }

            return $type === 'all'
                || $media['media_type'] === rtrim($type, 's');
        }
    ));

    if (!$mediaFiles) {
        throw new RuntimeException(
            'No processed media is available for this download.'
        );
    }

    $storageDir = __DIR__ . '/../storage/downloads';

    if (!is_dir($storageDir)
        && !mkdir($storageDir, 0700, true)
    ) {
        throw new RuntimeException(
            'Unable to create download storage directory.'
        );
    }

    $jobDir = $storageDir . '/job-' . (int) $job['id'];

    if (!is_dir($jobDir) && !mkdir($jobDir, 0700, true)) {
        throw new RuntimeException(
            'Unable to create job directory.'
        );
    }

    $zipPath = $jobDir . '/archive.zip';
    $spaces = new Spaces();

    $zip = new ZipArchive();

    if ($zip->open(
        $zipPath,
        ZipArchive::CREATE | ZipArchive::OVERWRITE
    ) !== true) {
        throw new RuntimeException('Unable to create ZIP archive.');
    }

    $usedNames = [];
    $manifest = [
        "filename,type,size_bytes,status\n"
    ];

    $processedFiles = 0;
    $processedBytes = 0;

    foreach ($mediaFiles as $media) {
        $key = (string) $media['storage_key'];

        if (!$spaces->objectExists($key)) {
            continue;
        }

        $originalName = basename(
            (string) $media['original_filename']
        );

        if ($originalName === '') {
            $extension = pathinfo($key, PATHINFO_EXTENSION);
            $originalName =
                'media-' . $media['id']
                . ($extension ? '.' . $extension : '');
        }

        $baseName = pathinfo(
            $originalName,
            PATHINFO_FILENAME
        );

        $extension = pathinfo(
            $originalName,
            PATHINFO_EXTENSION
        );

        $nameKey = strtolower($originalName);

        if (isset($usedNames[$nameKey])) {
            $usedNames[$nameKey]++;
            $originalName =
                $baseName
                . '-' . $usedNames[$nameKey]
                . ($extension ? '.' . $extension : '');
        } else {
            $usedNames[$nameKey] = 1;
        }

        $folder = $media['media_type'] === 'video'
            ? 'videos'
            : 'photos';

        $localPath =
            $jobDir . '/source-' . bin2hex(random_bytes(8));

        $spaces->downloadToFile($key, $localPath);

        if (!$zip->addFile(
            $localPath,
            $folder . '/' . $originalName
        )) {
            @unlink($localPath);
            throw new RuntimeException(
                'Unable to add media to ZIP.'
            );
        }

        $size = (int) ($media['file_size'] ?? 0);

        $manifest[] = implode(',', [
            '"' . str_replace(
                '"',
                '""',
                $originalName
            ) . '"',
            $media['media_type'],
            (string) $size,
            $media['processing_status'],
        ]) . "\n";

        $processedFiles++;
        $processedBytes += $size;

        $jobs->updateProgress(
            (int) $job['id'],
            $processedFiles,
            $processedBytes
        );
    }

    $zip->addFromString(
        'manifest.csv',
        implode('', $manifest)
    );

    if (!$zip->close()) {
        throw new RuntimeException(
            'Unable to finalize ZIP archive.'
        );
    }

    foreach (glob($jobDir . '/source-*') ?: [] as $source) {
        @unlink($source);
    }

    if (!is_file($zipPath)) {
        throw new RuntimeException(
            'ZIP archive was not created.'
        );
    }

    $jobs->complete(
        (int) $job['id'],
        $zipPath,
        24
    );
} catch (Throwable $e) {
    if (isset($job['id'])) {
        $jobs->fail(
            (int) $job['id'],
            $e->getMessage()
        );
    }

    error_log(
        'MediaBuilder download worker failed: '
        . $e->getMessage()
    );

    if (isset($job['id'])) {
        $jobDir = __DIR__
            . '/../storage/downloads/job-'
            . (int) $job['id'];

        if (is_dir($jobDir)) {
            foreach (glob($jobDir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }

            @rmdir($jobDir);
        }
    }

    exit(1);
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}
