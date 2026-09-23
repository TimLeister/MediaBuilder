<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';
require_once __DIR__ . '/../bootstrap.php';

use MediaDatabase;
use MediaDownloadJob;

$jobId = (int) ($_GET['id'] ?? 0);

if ($jobId <= 0) {
    http_response_code(400);
    exit('Invalid download ID.');
}

$db = Database::connection();
$job = (new DownloadJob($db))->findById($jobId);

if (!$job || (int) $job['user_id'] !== MediaAuth::userId()) {
    http_response_code(404);
    exit('Download not found.');
}

if ($job['status'] !== 'complete') {
    http_response_code(409);
    exit('Download is not ready.');
}

$path = (string) ($job['archive_path'] ?? '');

if ($path === '' || !is_file($path)) {
    http_response_code(404);
    exit('Download file is no longer available.');
}

$downloadName = (string) ($job['download_name'] ?? 'event-media.zip');

header('Content-Type: application/zip');
header(
    'Content-Disposition: attachment; filename="' .
    str_replace('"', '', $downloadName) .
    '"'
);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($path);
