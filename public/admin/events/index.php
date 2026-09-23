<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';
require_once __DIR__ . '/../bootstrap.php';

use Media\Config;
use Media\Database;
use Media\Event;
use Media\ShareLink;

Config::load(__DIR__ . '/../../..');

$db = Database::connection();

$eventModel = new Event($db);
$shareModel = new ShareLink($db);

$events = $eventModel->all();

$created = isset($_GET['created']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Events - Sports Media</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
            line-height: 1.5;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .success {
            background: #d1e7dd;
            padding: 12px;
            margin: 20px 0;
        }

        .event {
            border: 1px solid #ddd;
            padding: 20px;
            margin-top: 15px;
            border-radius: 6px;
        }

        .event h2 {
            margin-top: 0;
        }

        a.button {
            display: inline-block;
            padding: 10px 15px;
            background: #222;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        a.button:hover {
            opacity: 0.85;
        }

        .share-link {
            margin-top: 15px;
            padding: 12px;
            background: #f5f5f5;
            word-break: break-all;
        }

        .share-link a {
            color: inherit;
        }

        .meta {
            color: #666;
        }
    </style>
</head>

<body>

<div class="top">
    <h1>Events</h1>

    <a class="button" href="/admin/events/new.php">
        Create Event
    </a>
</div>

<?php if ($created): ?>
    <div class="success">
        Event created successfully.
    </div>
<?php endif; ?>

<?php if (!$events): ?>

    <p>No events have been created yet.</p>

<?php else: ?>

    <?php foreach ($events as $event): ?>

        <?php
        $share = $shareModel->findForEvent((int) $event['id']);

        if (!$share) {
            $token = $shareModel->create((int) $event['id']);
            $share = [
                'token' => $token,
            ];
        }

        $shareUrl = rtrim(
            Config::get('APP_URL'),
            '/'
        ) . '/e/' . $share['token'];
        ?>

        <div class="event">

            <h2><?= e($event['name']) ?></h2>

            <?php if ($event['event_date']): ?>
                <div class="meta">
                    <?= e($event['event_date']) ?>
                </div>
            <?php endif; ?>

            <?php if ($event['location']): ?>
                <div class="meta">
                    <?= e($event['location']) ?>
                </div>
            <?php endif; ?>

            <?php if ($event['description']): ?>
                <p>
                    <?= nl2br(e($event['description'])) ?>
                </p>
            <?php endif; ?>

            <div class="share-link">
                <strong>Share:</strong><br>

                <a
                    href="<?= e($shareUrl) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    <?= e($shareUrl) ?>
                </a>
            </div>

<p>
    <a
        class="button"
        href="/admin/events/upload.php?id=<?= (int) $event['id'] ?>"
    >
        Upload Media
    </a>
</p>

        </div>

    <?php endforeach; ?>

<?php endif; ?>

</body>
</html>
