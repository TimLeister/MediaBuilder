<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';

use Media\Config;
use Media\Database;
use Media\Event;
use Media\MediaFile;
use Media\Spaces;

Config::load(__DIR__ . '/../../..');

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

$eventModel = new Event($db);
$mediaModel = new MediaFile($db);
$spaces = new Spaces();

$event = $eventModel->findById($eventId);

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$mediaFiles = $mediaModel->forEvent($eventId);

$photos = array_filter(
    $mediaFiles,
    static fn(array $media): bool =>
        $media['media_type'] === 'photo'
        && $media['processing_status'] === 'complete'
        && !empty($media['web_key'])
);

if (!$photos) {
    http_response_code(404);
    exit('No processed photos are available for download.');
}

$tempDir = sys_get_temp_dir()
    . '/media-download-'
    . bin2hex(random_bytes(8));

if (!mkdir($tempDir, 0700, true)) {
    http_response_code(500);
    exit('Unable to create temporary directory.');
}

$zipPath = $tempDir . '/photos.zip';

try {

    $zip = new ZipArchive();

    if ($zip->open(
        $zipPath,
        ZipArchive::CREATE | ZipArchive::OVERWRITE
    ) !== true) {
        throw new RuntimeException(
            'Unable to create ZIP archive.'
        );
    }

    $usedNames = [];

    foreach ($photos as $photo) {

        $filename = basename(
            $photo['original_filename']
        );

        if ($filename === '') {
            $filename =
                'photo-' . $photo['id'] . '.jpg';
        }

        /*
         * Make sure duplicate filenames don't overwrite
         * each other inside the ZIP.
         */
        $baseName =
            pathinfo($filename, PATHINFO_FILENAME);

        $extension =
            pathinfo($filename, PATHINFO_EXTENSION);

        $nameKey = strtolower($filename);

        if (isset($usedNames[$nameKey])) {

            $usedNames[$nameKey]++;

            $suffix =
                '-' . $usedNames[$nameKey];

            $filename =
                $baseName
                . $suffix
                . ($extension
                    ? '.' . $extension
                    : '');

        } else {

            $usedNames[$nameKey] = 1;

        }

        $localPath =
            $tempDir
            . '/'
            . bin2hex(random_bytes(8))
            . '.jpg';

        $spaces->downloadToFile(
            $photo['web_key'],
            $localPath
        );

        if (!$zip->addFile(
            $localPath,
            $filename
        )) {
            throw new RuntimeException(
                'Unable to add photo to ZIP.'
            );
        }

    }

    if (!$zip->close()) {
        throw new RuntimeException(
            'Unable to finalize ZIP archive.'
        );
    }

    $safeEventName =
        preg_replace(
            '/[^a-zA-Z0-9_-]+/',
            '-',
            $event['name']
        );

    $safeEventName =
        trim($safeEventName, '-');

    if ($safeEventName === '') {
        $safeEventName = 'event';
    }

    $downloadName =
        $safeEventName
        . '-photos.zip';

    header(
        'Content-Type: application/zip'
    );

    header(
        'Content-Disposition: attachment; filename="'
        . $downloadName
        . '"'
    );

    header(
        'Content-Length: '
        . filesize($zipPath)
    );

    header(
        'Cache-Control: no-store, no-cache, must-revalidate'
    );

    readfile($zipPath);

} catch (Throwable $e) {

    http_response_code(500);

    echo 'Unable to create photo download.';

} finally {

    /*
     * Remove temporary files and directory.
     */
    if (is_dir($tempDir)) {

        $files = glob(
            $tempDir . '/*'
        );

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
