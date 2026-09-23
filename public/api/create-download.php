<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../admin/bootstrap.php';

use Media\Auth;
use Media\Config;
use Media\Database;
use Media\DownloadJob;
use Media\Event;
use Media\MediaFile;

Config::load(__DIR__ . '/../..');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

if (!Auth::verifyCsrf(
    $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_POST['csrf_token']
        ?? null
)) {
    http_response_code(419);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$type = trim((string) ($_POST['type'] ?? ''));

if ($eventId <= 0 || !in_array($type, ['photos', 'videos', 'all'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid download request.']);
    exit;
}

$db = Database::connection();
$event = (new Event($db))->findById($eventId);

if (!$event) {
    http_response_code(404);
    echo json_encode(['error' => 'Event not found.']);
    exit;
}

$sessionUserId = Auth::userId();

if ($sessionUserId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$media = (new MediaFile($db))->forEvent($eventId);

$media = array_values(array_filter(
    $media,
    static function (array $item) use ($type): bool {
        if (empty($item['storage_key'])) {
            return false;
        }

        if ($item['processing_status'] !== 'complete') {
            return false;
        }

        return $type === 'all'
            || $item['media_type'] === rtrim($type, 's');
    }
));

if (!$media) {
    http_response_code(404);
    echo json_encode([
        'error' => 'No processed media is available for this download.'
    ]);
    exit;
}

$downloadName = preg_replace(
    '/[^a-zA-Z0-9_-]+/',
    '-',
    (string) $event['name']
);

$downloadName = trim(
    (string) $downloadName,
    '-'
);

if ($downloadName === '') {
    $downloadName = 'event';
}

$downloadName .= '-' . $type . '.zip';

$jobs = new DownloadJob($db);

$existing = $jobs->findActive(
    $sessionUserId,
    $eventId,
    $type
);

if ($existing) {
    echo json_encode([
        'success' => true,
        'job' => [
            'id' => (int) $existing['id'],
            'status' => $existing['status'],
        ],
    ]);
    exit;
}

$totalBytes = array_sum(
    array_map(
        static fn(array $item): int =>
            (int) ($item['file_size'] ?? 0),
        $media
    )
);

$jobId = $jobs->create(
    $sessionUserId,
    $eventId,
    $type,
    count($media),
    $totalBytes,
    $downloadName
);

echo json_encode([
    'success' => true,
    'job' => [
        'id' => $jobId,
        'status' => 'queued',
    ],
]);
