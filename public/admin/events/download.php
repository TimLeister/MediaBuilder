<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';
require_once __DIR__ . '/../bootstrap.php';

use Media\Database;
use Media\Event;
use Media\MediaFile;
use Media\Spaces;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed.');
}

$eventId = (int) ($_GET['id'] ?? 0);

if ($eventId <= 0) {
    http_response_code(400);
    exit('Invalid event ID.');
}

$db = Database::connection();
$event = (new Event($db))->findById($eventId);

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$mediaFiles = (new MediaFile($db))->forEvent($eventId);

$mediaFiles = array_values(array_filter(
    $mediaFiles,
    static fn(array $media): bool =>
        !empty($media['storage_key'])
        && in_array($media['media_type'], ['photo', 'video'], true)
));

if (!$mediaFiles) {
    http_response_code(404);
    exit('No media is available for this event.');
}

$spaces = new Spaces();

$tempDir = sys_get_temp_dir()
    . '/media-event-download-'
    . bin2hex(random_bytes(8));

if (!mkdir($tempDir, 0700, true)) {
    http_response_code(500);
    exit('Unable to create temporary directory.');
}

$zipPath = $tempDir . '/event-media.zip';
$downloadName = preg_replace(
    '/[^a-zA-Z0-9_-]+/',
    '-',
    (string) $event['name']
);
$downloadName = trim((string) $downloadName, '-');

if ($downloadName === '') {
    $downloadName = 'event';
}

$downloadName .= '-media.zip';

set_time_limit(0);

try {
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

    foreach ($mediaFiles as $media) {
        $key = (string) $media['storage_key'];

        /*
         * The original storage object is used here so the archive
         * contains the photographer's source files, not web derivatives.
         */
        if (!$spaces->objectExists($key)) {
            continue;
        }

        $originalName = basename((string) $media['original_filename']);

        if ($originalName === '') {
            $extension = pathinfo($key, PATHINFO_EXTENSION);
            $originalName = 'media-' . $media['id']
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

        $localPath =
            $tempDir
            . '/' . bin2hex(random_bytes(8));

        $spaces->downloadToFile($key, $localPath);

        if (!$zip->addFile(
            $localPath,
            $folder . '/' . $originalName
        )) {
            throw new RuntimeException('Unable to add media to ZIP.');
        }

        $manifest[] = implode(',', [
            '"' . str_replace('"', '""', $originalName) . '"',
            $media['media_type'],
            (string) ((int) ($media['file_size'] ?? 0)),
            $media['processing_status'],
        ]) . "\n";
    }

    if (!$zip->addFromString(
        'manifest.csv',
        implode('', $manifest)
    )) {
        throw new RuntimeException('Unable to add manifest to ZIP.');
    }

    if (!$zip->close()) {
        throw new RuntimeException('Unable to finalize ZIP archive.');
    }

    if (!is_file($zipPath)) {
        throw new RuntimeException('ZIP archive was not created.');
    }

    header('Content-Type: application/zip');
    header(
        'Content-Disposition: attachment; filename="' .
        $downloadName .
        '"'
    );
    header('Content-Length: ' . filesize($zipPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($zipPath);
} catch (Throwable $e) {
    error_log(
        'MediaBuilder event download failed: '
        . $e->getMessage()
    );

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'Unable to create event media download.';
} finally {
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '/*');

        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        @rmdir($tempDir);
    }
}
