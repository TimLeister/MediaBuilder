<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/helpers.php';

use Media\Config;
use Media\Database;
use Media\MediaFile;
use Media\Spaces;

Config::load(__DIR__ . '/..');

$db = Database::connection();

$mediaModel = new MediaFile($db);
$spaces = new Spaces();

/*
 * Process the oldest pending media item, regardless of type.
 */
$stmt = $db->query(
    '
    SELECT *
    FROM media_files
    WHERE processing_status = "pending"
    ORDER BY
        CASE
            WHEN media_type = "photo" THEN 0
            ELSE 1
        END,
        id ASC
    LIMIT 1
    '
);

$media = $stmt->fetch();

if (!$media) {
    echo "No pending media.\n";
    exit;
}

$mediaId = (int) $media['id'];
$mediaType = $media['media_type'];

echo "Processing media #{$mediaId}: "
    . $media['original_filename']
    . " ({$mediaType})\n";

$mediaModel->markProcessing($mediaId);

$workDir = sys_get_temp_dir()
    . '/media-' . bin2hex(random_bytes(8));

if (!mkdir($workDir, 0700, true)) {
    $mediaModel->markFailed($mediaId);

    throw new RuntimeException(
        'Unable to create temporary working directory.'
    );
}

$originalPath = $workDir . '/original';

$thumbnailPath = $workDir . '/thumbnail.jpg';

$webPath = $workDir . '/web.jpg';

$videoPath = $workDir . '/video.mp4';

try {

    /*
     * Download original from Spaces.
     */
    echo "Downloading original...\n";

    $spaces->downloadToFile(
        $media['storage_key'],
        $originalPath
    );

    /*
     * Look up event storage prefix.
     */
    $eventStmt = $db->prepare(
        '
        SELECT storage_prefix
        FROM events
        WHERE id = :event_id
        LIMIT 1
        '
    );

    $eventStmt->execute([
        'event_id' => (int) $media['event_id'],
    ]);

    $event = $eventStmt->fetch();

    if (!$event || empty($event['storage_prefix'])) {
        throw new RuntimeException(
            'Event storage prefix not found.'
        );
    }

    $storagePrefix =
        rtrim($event['storage_prefix'], '/') . '/';

    $baseName = $media['uuid'];

    /*
     * ------------------------------------------------------------
     * PHOTO PROCESSING
     * ------------------------------------------------------------
     */
    if ($mediaType === 'photo') {

        /*
         * Read image dimensions.
         */
        echo "Reading image dimensions...\n";

        $identify = shell_exec(
            'identify -format "%w %h" '
            . escapeshellarg($originalPath)
        );

        if (!$identify) {
            throw new RuntimeException(
                'Unable to read image dimensions.'
            );
        }

        $dimensions = preg_split(
            '/\s+/',
            trim($identify)
        );

        $width = isset($dimensions[0])
            ? (int) $dimensions[0]
            : null;

        $height = isset($dimensions[1])
            ? (int) $dimensions[1]
            : null;

        echo "Dimensions: {$width}x{$height}\n";

        /*
         * Generate thumbnail.
         */
        echo "Generating thumbnail...\n";

        $thumbnailCommand =
            'convert '
            . escapeshellarg($originalPath)
            . ' -auto-orient '
            . '-thumbnail "400x400>" '
            . '-quality 82 '
            . '-strip '
            . escapeshellarg($thumbnailPath);

        exec(
            $thumbnailCommand,
            $output,
            $returnCode
        );

        if (
            $returnCode !== 0
            || !file_exists($thumbnailPath)
        ) {
            throw new RuntimeException(
                'Unable to generate thumbnail.'
            );
        }

        /*
         * Generate web-sized image.
         */
        echo "Generating web image...\n";

        $webCommand =
            'convert '
            . escapeshellarg($originalPath)
            . ' -auto-orient '
            . '-resize "2000x2000>" '
            . '-quality 88 '
            . '-strip '
            . escapeshellarg($webPath);

        exec(
            $webCommand,
            $output,
            $returnCode
        );

        if (
            $returnCode !== 0
            || !file_exists($webPath)
        ) {
            throw new RuntimeException(
                'Unable to generate web image.'
            );
        }

        $thumbnailKey =
            $storagePrefix
            . 'processed/thumbs/'
            . $baseName
            . '.jpg';

        $webKey =
            $storagePrefix
            . 'processed/web/'
            . $baseName
            . '.jpg';

        /*
         * Upload thumbnail.
         */
        echo "Uploading thumbnail...\n";

        $thumbnailUrl = $spaces->uploadFile(
            $thumbnailKey,
            $thumbnailPath,
            'image/jpeg'
        );

        /*
         * Upload web image.
         */
        echo "Uploading web image...\n";

        $webUrl = $spaces->uploadFile(
            $webKey,
            $webPath,
            'image/jpeg'
        );

        /*
         * Update database.
         */
        $mediaModel->markComplete(
            $mediaId,
            $width,
            $height,
            $thumbnailKey,
            $thumbnailUrl,
            $webKey,
            $webUrl
        );

        echo "Photo processing complete.\n";
    }

    /*
     * ------------------------------------------------------------
     * VIDEO PROCESSING
     * ------------------------------------------------------------
     */
    elseif ($mediaType === 'video') {

        /*
         * Read video information with ffprobe.
         */
        echo "Reading video information...\n";

        $probeCommand =
            'ffprobe '
            . '-v error '
            . '-select_streams v:0 '
            . '-show_entries stream=width,height '
            . '-show_entries format=duration '
            . '-of default=noprint_wrappers=1:nokey=1 '
            . escapeshellarg($originalPath);

        exec(
            $probeCommand,
            $probeOutput,
            $returnCode
        );

        if (
            $returnCode !== 0
            || count($probeOutput) < 3
        ) {
            throw new RuntimeException(
                'Unable to read video information.'
            );
        }

        $width = isset($probeOutput[0])
            ? (int) trim($probeOutput[0])
            : null;

        $height = isset($probeOutput[1])
            ? (int) trim($probeOutput[1])
            : null;

        $duration = isset($probeOutput[2])
            ? (float) trim($probeOutput[2])
            : null;

        echo "Dimensions: {$width}x{$height}\n";
        echo "Duration: {$duration} seconds\n";

        /*
         * Convert video to browser-friendly MP4.
         *
         * The H.264 video stream is copied without re-encoding.
         * Audio is converted to AAC.
         */
        echo "Converting video to MP4...\n";

        $videoCommand =
            'ffmpeg '
            . '-y '
            . '-i '
            . escapeshellarg($originalPath)
            . ' -map 0:v:0 '
            . '-map 0:a:0? '
            . '-c:v copy '
            . '-c:a aac '
            . '-b:a 192k '
            . '-movflags +faststart '
            . escapeshellarg($videoPath)
            . ' 2>&1';

        exec(
            $videoCommand,
            $videoOutput,
            $returnCode
        );

        if (
            $returnCode !== 0
            || !file_exists($videoPath)
            || filesize($videoPath) === 0
        ) {
            throw new RuntimeException(
                "Unable to convert video.\n"
                . implode("\n", $videoOutput)
            );
        }

        /*
         * Extract a frame for the gallery thumbnail.
         *
         * We use one second into the video when possible.
         */
        echo "Generating video thumbnail...\n";

        $thumbnailCommand =
            'ffmpeg '
            . '-y '
            . '-ss 1 '
            . '-i '
            . escapeshellarg($originalPath)
            . ' -frames:v 1 '
            . '-vf "scale=400:400:force_original_aspect_ratio=decrease" '
            . '-q:v 4 '
            . escapeshellarg($thumbnailPath)
            . ' 2>&1';

        exec(
            $thumbnailCommand,
            $thumbnailOutput,
            $returnCode
        );

        /*
         * If the video is shorter than one second, try frame zero.
         */
        if (
            $returnCode !== 0
            || !file_exists($thumbnailPath)
        ) {

            echo "Retrying thumbnail from first frame...\n";

            $thumbnailCommand =
                'ffmpeg '
                . '-y '
                . '-i '
                . escapeshellarg($originalPath)
                . ' -frames:v 1 '
                . '-vf "scale=400:400:force_original_aspect_ratio=decrease" '
                . '-q:v 4 '
                . escapeshellarg($thumbnailPath)
                . ' 2>&1';

            exec(
                $thumbnailCommand,
                $thumbnailOutput,
                $returnCode
            );
        }

        if (
            $returnCode !== 0
            || !file_exists($thumbnailPath)
        ) {
            throw new RuntimeException(
                "Unable to generate video thumbnail.\n"
                . implode("\n", $thumbnailOutput)
            );
        }

        /*
         * Storage keys.
         */
        $thumbnailKey =
            $storagePrefix
            . 'processed/thumbs/'
            . $baseName
            . '.jpg';

        $videoKey =
            $storagePrefix
            . 'processed/video/'
            . $baseName
            . '.mp4';

        /*
         * Upload thumbnail.
         */
        echo "Uploading video thumbnail...\n";

        $thumbnailUrl = $spaces->uploadFile(
            $thumbnailKey,
            $thumbnailPath,
            'image/jpeg'
        );

        /*
         * Upload processed video.
         */
        echo "Uploading processed video...\n";

        $videoUrl = $spaces->uploadFile(
            $videoKey,
            $videoPath,
            'video/mp4'
        );

        /*
         * Update database.
         */
        $mediaModel->markVideoComplete(
            $mediaId,
            $width,
            $height,
            $duration,
            $thumbnailKey,
            $thumbnailUrl,
            $videoKey,
            $videoUrl
        );

        echo "Video processing complete.\n";
    }

    /*
     * Unknown media type.
     */
    else {
        throw new RuntimeException(
            'Unsupported media type: '
            . $mediaType
        );
    }

} catch (Throwable $e) {

    $mediaModel->markFailed($mediaId);

    echo "Processing failed:\n";
    echo $e->getMessage() . "\n";

    exit(1);

} finally {

    /*
     * Clean up temporary files.
     */
    foreach ([
        $originalPath,
        $thumbnailPath,
        $webPath,
        $videoPath,
    ] as $file) {

        if (file_exists($file)) {
            unlink($file);
        }
    }

    if (is_dir($workDir)) {
        rmdir($workDir);
    }
}