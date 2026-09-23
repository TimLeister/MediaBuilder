<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';
require_once __DIR__ . '/../bootstrap.php';

use Media\Config;
use Media\Database;
use Media\Event;

Config::load(__DIR__ . '/../../..');

$db = Database::connection();

$errors = [];

$name = '';
$eventDate = '';
$location = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $eventDate = trim($_POST['event_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = 'Event name is required.';
    }

    if (!$errors) {
        $slug = slugify($name);

        $eventModel = new Event($db);

        /*
         * Make the slug unique if an event with the
         * same name already exists.
         */
        $baseSlug = $slug;
        $counter = 2;

        while ($eventModel->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

$eventId = $eventModel->create(
    $name,
    $slug,
    $eventDate,
    $location,
    $description
);

$shareModel = new \Media\ShareLink($db);

$shareModel->create($eventId);

redirect('/admin/events/?created=' . $eventId);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - Sports Media</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 6px;
        }

        input,
        textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            font-size: 16px;
        }

        textarea {
            min-height: 140px;
        }

        button {
            margin-top: 25px;
            padding: 12px 20px;
            font-size: 16px;
            cursor: pointer;
        }

        .error {
            background: #f8d7da;
            padding: 12px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

<h1>Create Event</h1>

<?php if ($errors): ?>
    <div class="error">
        <?php foreach ($errors as $error): ?>
            <div><?= e($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post">

    <label for="name">Event Name</label>
    <input
        type="text"
        id="name"
        name="name"
        value="<?= e($name) ?>"
        required
    >

    <label for="event_date">Event Date</label>
    <input
        type="date"
        id="event_date"
        name="event_date"
        value="<?= e($eventDate) ?>"
    >

    <label for="location">Location</label>
    <input
        type="text"
        id="location"
        name="location"
        value="<?= e($location) ?>"
    >

    <label for="description">Description</label>
    <textarea
        id="description"
        name="description"
    ><?= e($description) ?></textarea>

    <button type="submit">
        Create Event
    </button>

</form>

</body>
</html>
