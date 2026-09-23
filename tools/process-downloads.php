#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MediaConfig;
use MediaDatabase;
use MediaDownloadJob;
use MediaEvent;
use MediaMediaFile;
use MediaSpaces;

Config::load(__DIR__ . '/..');

$db = Database::connection();
$jobs = new DownloadJob($db);

$jobId = isset($argv[1]) ? (int) $argv[1] : null;

$lockPath = sys_get_temp_dir() . '/mediabuilder-download-worker.lock';
$lockHandle = fopen($lockPath, 'c');

if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$job = null;
$jobDir = null;

try {
    $job = $jobs->claimNext($jobId);

    if (!$job) {
        exit(0);
    }

    $jobId = (int) $job['id'];

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

    $jobDir = $storageDir . '/job-' . $jobId;

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
    $failedFiles = 0;
    $failures = [];

    foreach ($mediaFiles as $media) {
        $jobs->heartbeat($jobId);

        $key = (string) $media['storage_key'];
        $originalName = basename(
            (string) $media['original_filename']
        );

        if ($originalName === '') {
            $extension = pathinfo($key, PATHINFO_EXTENSION);
            $originalName =
                'media-' . $media['id']
                . ($extension ? '.' . $extension : '');
        }

        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
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

        $downloaded = false;
        $lastError = '';
        $localPath = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $jobs->heartbeat($jobId);

            try {
                if (!$spaces->objectExists($key)) {
                    throw new RuntimeException(
                        'Object is missing from Spaces.'
                    );
                }

                $localPath =
                    $jobDir . '/source-' . bin2hex(random_bytes(8));

                $spaces->downloadToFile($key, $localPath);

                if (!is_file($localPath)) {
                    throw new RuntimeException(
                        'Spaces download did not create a local file.'
                    );
                }

                $localSize = filesize($localPath);

                if ($localSize === false) {
                    throw new RuntimeException(
                        'Unable to determine downloaded file size.'
                    );
                }

                if (!$zip->addFile(
                    $localPath,
                    $folder . '/' . $originalName
                )) {
                    @unlink($localPath);
                    throw new RuntimeException(
                        'Unable to add media to ZIP.'
                    );
                }

                $size = (int) ($media['file_size'] ?? $localSize);

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
                $downloaded = true;

                $jobs->updateProgress(
                    $jobId,
                    $processedFiles,
                    $processedBytes,
                    $failedFiles
                );

                break;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();

                if (isset($localPath) && is_file($localPath)) {
                    @unlink($localPath);
                }

                if ($attempt < 3) {
                    sleep(2 * $attempt);
                }
            }
        }

        if (!$downloaded) {
            $failedFiles++;

            $failures[] = [
                'media_id' => (int) $media['id'],
                'filename' => $originalName,
                'storage_key' => $key,
                'error' => mb_substr($lastError, 0, 500),
            ];

            $manifest[] = implode(',', [
                '"' . str_replace(
                    '"',
                    '""',
                    $originalName
                ) . '"',
                $media['media_type'],
                '0',
                'failed',
            ]) . "\n";

            $jobs->updateProgress(
                $jobId,
                $processedFiles,
                $processedBytes,
                $failedFiles
            );
        }
    }

    if ($processedFiles === 0) {
        $zip->close();

        throw new RuntimeException(
            'Every media file failed to download.'
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

    $archiveSize = filesize($zipPath);

    if ($archiveSize === false || $archiveSize <= 100) {
        throw new RuntimeException(
            'ZIP archive failed integrity checks.'
        );
    }

    $verify = new ZipArchive();

    if ($verify->open(
        $zipPath,
        ZipArchive::CHECKCONS
    ) !== true) {
        throw new RuntimeException(
            'ZIP archive failed integrity verification.'
        );
    }

    $entryCount = $verify->numFiles;
    $verify->close();

    if ($entryCount !== $processedFiles + 1) {
        throw new RuntimeException(
            'ZIP archive contains an unexpected number of files.'
        );
    }

    $failureDetails = $failedFiles > 0
        ? json_encode(
            $failures,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        )
        : null;

    $jobs->complete(
        $jobId,
        $zipPath,
        $failedFiles,
        $failureDetails,
        24
    );
} catch (Throwable $e) {
    if ($job !== null) {
        $jobs->fail(
            (int) $job['id'],
            $e->getMessage()
        );
    }

    error_log(
        'MediaBuilder download worker failed: '
        . $e->getMessage()
    );

    if ($jobDir !== null && is_dir($jobDir)) {
        foreach (glob($jobDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($jobDir);
    }

    exit(1);
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}
