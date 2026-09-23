<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/helpers.php';

use Media\Config;
use Media\Database;
use Media\Event;
use Media\ShareLink;
use Media\Spaces;
use Media\MediaFile;

Config::load(__DIR__ . '/../..');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'error' => 'Method not allowed.',
    ]);

    exit;
}

$input = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($input)) {
    http_response_code(400);

    echo json_encode([
        'error' => 'Invalid request.',
    ]);

    exit;
}

$eventId = (int) ($input['event_id'] ?? 0);
$filename = trim((string) ($input['filename'] ?? ''));
$contentType = trim((string) ($input['content_type'] ?? ''));
$fileSize = (int) ($input['file_size'] ?? 0);

if ($eventId <= 0 || $filename === '' || $contentType === '') {
    http_response_code(400);

    echo json_encode([
        'error' => 'Missing required fields.',
    ]);

    exit;
}

/*
 * Limit this endpoint to media we actually support.
 */
$allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'video/mp4',
    'video/quicktime',
    'video/webm',
];

if (!in_array($contentType, $allowedTypes, true)) {
    http_response_code(415);

    echo json_encode([
        'error' => 'Unsupported file type.',
    ]);

    exit;
}

if ($fileSize <= 0) {
    http_response_code(400);

    echo json_encode([
        'error' => 'Invalid file size.',
    ]);

    exit;
}

$db = Database::connection();

$eventModel = new Event($db);
$event = $eventModel->findById($eventId);

if (!$event) {
    http_response_code(404);

    echo json_encode([
        'error' => 'Event not found.',
    ]);

    exit;
}

/*
 * Generate a random filename rather than trusting the
 * original filename as the actual storage key.
 */
$uuid = bin2hex(random_bytes(16));

$extension = strtolower(
    pathinfo($filename, PATHINFO_EXTENSION)
);

$extension = preg_replace(
    '/[^a-z0-9]/',
    '',
    $extension
);

if ($extension === '') {
    $extension = match ($contentType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        default => 'bin',
    };
}

$mediaType = str_starts_with(
    $contentType,
    'video/'
)
    ? 'video'
    : 'photo';

$storageKey = sprintf(
    '%soriginals/%s.%s',
    rtrim($event['storage_prefix'], '/') . '/',
    $uuid,
    $extension
);

$spaces = new Spaces();

$uploadUrl = $spaces->createUploadUrl(
    $storageKey,
    $contentType
);

$cdnUrl = $spaces->url($storageKey);
$mediaModel = new MediaFile($db);

$mediaId = $mediaModel->create(
    $eventId,
    $uuid,
    $filename,
    $mediaType,
    $contentType,
    $fileSize,
    $storageKey,
    $cdnUrl
);

echo json_encode([
    'success' => true,
    'upload_url' => $uploadUrl,
    'cdn_url' => $cdnUrl,
    'storage_key' => $storageKey,
    'uuid' => $uuid,
'media_id' => $mediaId,
    'media_type' => $mediaType,
]);
