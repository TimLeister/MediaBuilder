<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';

use Media\Config;
use Media\Database;
use Media\Event;

Config::load(__DIR__ . '/../../..');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$eventId = (int) ($_POST['event_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($eventId <= 0) {
    http_response_code(400);
    exit('Invalid event ID.');
}

if (!in_array($action, ['publish', 'unpublish'], true)) {
    http_response_code(400);
    exit('Invalid action.');
}

$db = Database::connection();

$eventModel = new Event($db);

$event = $eventModel->findById($eventId);

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

if ($action === 'publish') {
    $eventModel->publish($eventId);
} else {
    $eventModel->unpublish($eventId);
}

redirect('/admin/events/view.php?id=' . $eventId);
