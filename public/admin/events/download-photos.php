<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';
require_once __DIR__ . '/../bootstrap.php';

$eventId = (int) ($_GET['id'] ?? 0);

if ($eventId <= 0) {
    http_response_code(400);
    exit('Invalid event ID.');
}

/*
 * Downloads are now handled asynchronously by the Download Center.
 * Keep this endpoint as a compatibility redirect for old bookmarks.
 */
header(
    'Location: /admin/events/view.php?id=' . $eventId,
    true,
    302
);
exit;
