<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../admin/bootstrap.php';

use Media\Auth;
use Media\Config;
use Media\Database;
use Media\DownloadJob;

Config::load(__DIR__ . '/../..');

header('Content-Type: application/json');

$eventId = (int) ($_GET['event_id'] ?? 0);

if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event ID.']);
    exit;
}

$db = Database::connection();
$jobsModel = new DownloadJob($db);

$jobs = $jobsModel->forEventUser(
    Auth::userId(),
    $eventId
);

echo json_encode([
    'success' => true,
    'jobs' => array_map(
        static function (array $job): array {
            $totalFiles = (int) $job['total_files'];
            $processedFiles = (int) $job['processed_files'];

            $percent = $totalFiles > 0
                ? (int) floor(
                    ($processedFiles / $totalFiles) * 100
                )
                : 0;

            if ($job['status'] === 'complete') {
                $percent = 100;
            }

            return [
                'id' => (int) $job['id'],
                'status' => $job['status'],
                'download_name' => $job['download_name'],
                'total_files' => $totalFiles,
                'processed_files' => $processedFiles,
                'total_bytes' => (int) $job['total_bytes'],
                'processed_bytes' => (int) $job['processed_bytes'],
                'percent' => $percent,
                'error_message' => $job['error_message'],
                'download_url' => $job['status'] === 'complete'
                    ? '/admin/events/download-file.php?id='
                        . (int) $job['id']
                    : null,
            ];
        },
        $jobs
    ),
]);
