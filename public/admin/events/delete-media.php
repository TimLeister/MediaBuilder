<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';

use Media\Config;
use Media\Database;
use Media\MediaFile;
use Media\Spaces;

Config::load(__DIR__ . '/../../..');

$db = Database::connection();

$mediaModel = new MediaFile($db);
$spaces = new Spaces();

$mediaId = (int) ($_POST['media_id'] ?? 0);
$eventId = (int) ($_POST['event_id'] ?? 0);

if ($mediaId <= 0 || $eventId <= 0) {
    http_response_code(400);
    exit('Invalid request.');
}

$media = $mediaModel->findById($mediaId);

if (!$media) {
    http_response_code(404);
    exit('Media not found.');
}

/*
 * Make absolutely sure the media belongs
 * to the event supplied in the request.
 */
if ((int) $media['event_id'] !== $eventId) {
    http_response_code(403);
    exit('Invalid media/event combination.');
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

redirect('/admin/events/view.php?id=' . $eventId);
