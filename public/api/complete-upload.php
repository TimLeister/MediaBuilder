<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../app/helpers.php';
require_once __DIR__ . '/../admin/bootstrap.php';

use Media\Config;
use Media\Database;
use Media\MediaFile;
use Media\Spaces;

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

$mediaId = (int) ($input['media_id'] ?? 0);

if ($mediaId <= 0) {
    http_response_code(400);

    echo json_encode([
        'error' => 'Missing media ID.',
    ]);

    exit;
}

$db = Database::connection();

$mediaModel = new MediaFile($db);

$media = $mediaModel->findById($mediaId);

if (!$media) {
    http_response_code(404);

    echo json_encode([
        'error' => 'Media file not found.',
    ]);

    exit;
}

$spaces = new Spaces();

if (!$spaces->objectExists($media['storage_key'])) {
    http_response_code(409);

    echo json_encode([
        'error' => 'Uploaded file was not found in storage.',
    ]);

    exit;
}

/*
 * The upload itself is complete, but media processing is not.
 *
 * Leave the record as "pending" so the background processor
 * can generate the appropriate photo or video derivatives.
 */

echo json_encode([
    'success' => true,
    'media_id' => $mediaId,
    'status' => 'pending',
]);