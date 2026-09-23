<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';

use Media\Config;
use Media\Database;
use Media\MediaFile;
use Media\Spaces;

Config::load(__DIR__ . '/../../..');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$mediaIds = $_POST['media_ids'] ?? [];

if ($eventId <= 0 || !is_array($mediaIds)) {
    http_response_code(400);
    exit('Invalid request.');
}

$mediaModel = new MediaFile(Database::connection());
$spaces = new Spaces();

$deleted = 0;

foreach ($mediaIds as $mediaId) {

    $mediaId = (int) $mediaId;

    if ($mediaId <= 0) {
        continue;
    }

    $media = $mediaModel->findById($mediaId);

    if (!$media) {
        continue;
    }

    /*
     * Make absolutely sure the media belongs
     * to the event supplied in the request.
     */
    if ((int) $media['event_id'] !== $eventId) {
        continue;
    }

    /*
     * Only allow bulk deletion of photos for now.
     */
    if ($media['media_type'] !== 'photo') {
        continue;
    }

    /*
     * Delete original.
     */
    if (!empty($media['storage_key'])) {
        $spaces->delete($media['storage_key']);
    }

    /*
     * Delete thumbnail.
     */
    if (!empty($media['thumbnail_key'])) {
        $spaces->delete($media['thumbnail_key']);
    }

    /*
     * Delete web-sized image.
     */
    if (!empty($media['web_key'])) {
        $spaces->delete($media['web_key']);
    }

    /*
     * Delete database record.
     */
    $mediaModel->delete($mediaId);

    $deleted++;
}

redirect(
    '/admin/events/view.php?id='
    . $eventId
    . '&deleted='
    . $deleted
);

