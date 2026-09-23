<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';
require_once __DIR__ . '/../bootstrap.php';

use Media\Config;
use Media\Database;
use Media\Event;
use Media\MediaFile;
use Media\ShareLink;
use Media\Spaces;

Config::load(__DIR__ . '/../../..');

$db = Database::connection();

$eventId = (int) ($_GET['id'] ?? 0);

if ($eventId <= 0) {
    http_response_code(404);
    exit('Event not found.');
}

$eventModel = new Event($db);
$mediaModel = new MediaFile($db);
$shareModel = new ShareLink($db);

$event = $eventModel->findById($eventId);

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$share = $shareModel->findForEvent($eventId);

if (!$share) {
    $token = $shareModel->create($eventId);

    $share = [
        'token' => $token,
    ];
}

$shareUrl = rtrim(
    Config::get('APP_URL'),
    '/'
) . '/e/' . $share['token'];

$mediaFiles = $mediaModel->forEvent($eventId);

$photos = array_filter(
    $mediaFiles,
    static fn(array $media): bool =>
        $media['media_type'] === 'photo'
);

$videos = array_filter(
    $mediaFiles,
    static fn(array $media): bool =>
        $media['media_type'] === 'video'
);

$spaces = new Spaces();

foreach ($photos as &$photo) {

    $photo['admin_web_url'] = !empty($photo['web_key'])
        ? $spaces->createDownloadUrl($photo['web_key'], 60)
        : '';

    $photo['admin_thumbnail_url'] = !empty($photo['thumbnail_key'])
        ? $spaces->createDownloadUrl($photo['thumbnail_key'], 60)
        : '';
}

unset($photo);

foreach ($videos as &$video) {

    $video['admin_video_url'] = !empty($video['video_key'])
        ? $spaces->createDownloadUrl($video['video_key'], 60)
        : '';
}

unset($video);

$completeCount = count(
    array_filter(
        $mediaFiles,
        static fn(array $media): bool =>
            $media['processing_status'] === 'complete'
    )
);

$pendingCount = count(
    array_filter(
        $mediaFiles,
        static fn(array $media): bool =>
            $media['processing_status'] === 'pending'
    )
);

$importedCount = isset($_GET['imported'])
    ? max(0, (int) $_GET['imported'])
    : null;

$deletedCount = isset($_GET['deleted'])
    ? max(0, (int) $_GET['deleted'])
    : null;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= e($event['name']) ?> - Sports Media
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px 60px;
            line-height: 1.5;
            color: #222;
        }

        a {
            color: inherit;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 30px;
        }

        .back {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        h1 {
            margin: 5px 0;
            font-size: 32px;
        }

        .meta {
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .status-badge.draft {
            background: #eee;
            color: #666;
        }

        .status-badge.published {
            background: #e8f5e9;
            color: #18794e;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 25px 0;
        }

        .button {
            display: inline-block;
            padding: 11px 16px;
            border-radius: 6px;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            font-size: 14px;
        }

        .button-primary {
            background: #222;
            color: white;
        }

        .button-secondary {
            background: #eee;
            color: #222;
        }

        .button:hover {
            opacity: 0.85;
        }

        .share-box,
        .publish-box {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 25px;
        }

        .publish-box p {
            margin: 8px 0 0;
            color: #666;
        }

        .share-url {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }

        .share-url input {
            flex: 1;
            min-width: 0;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }


        .embed-box {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 18px;
            margin-bottom: 25px;
        }

        .embed-description {
            margin: 6px 0 12px;
            color: #666;
            font-size: 13px;
        }

        .embed-code {
            width: 100%;
            min-height: 110px;
            box-sizing: border-box;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: white;
            color: #333;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.5;
            resize: vertical;
        }

        .embed-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .stats {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 35px;
        }

        .stat {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 20px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        section {
            margin-top: 40px;
        }

        section h2 {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .photo-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            margin: 15px 0;
            padding: 12px 15px;
            background: #f5f5f5;
            border-radius: 7px;
        }

        .select-all {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            cursor: pointer;
        }

        .select-all input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .photo-toolbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .selected-count {
            color: #666;
            font-size: 13px;
        }

        .bulk-delete-button {
            padding: 9px 14px;
            border: 0;
            border-radius: 5px;
            background: #b42318;
            color: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }

        .bulk-delete-button:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .photo-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }

        .photo {
            background: #f5f5f5;
            border-radius: 7px;
            overflow: hidden;
        }

        .photo-image {
            position: relative;
        }

        .photo-image a {
            display: block;
        }

        .photo-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 2;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .photo-checkbox-bg {
            position: absolute;
            top: 7px;
            left: 7px;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.92);
            z-index: 1;
        }

        .photo img {
            display: block;
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
        }

        .photo-name {
            padding: 9px 9px 0;
            font-size: 12px;
            color: #666;
            word-break: break-word;
        }

        .media-status {
            margin: 5px 9px 0;
            font-size: 12px;
            font-weight: bold;
        }

        .media-status.pending {
            color: #996c00;
        }

        .media-status.processing {
            color: #2563eb;
        }

        .media-status.complete {
            color: #18794e;
        }

        .media-status.failed {
            color: #b42318;
        }

        .photo-actions {
            padding: 9px;
        }

        .photo-actions form {
            margin: 0;
        }

        .delete-button {
            width: 100%;
            padding: 8px 10px;
            border: 0;
            border-radius: 5px;
            background: #eee;
            color: #b42318;
            cursor: pointer;
            font-size: 12px;
        }

        .delete-button:hover {
            background: #fdd;
        }

        .video-list {
            display: grid;
            gap: 12px;
        }

        .video {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 7px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .video-name {
            word-break: break-word;
        }

        .status {
            font-size: 13px;
            color: #666;
        }

        .empty {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 35px;
            text-align: center;
            color: #666;
        }

        .notice {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 10px;
            background: #f5f5f5;
            border-radius: 6px;
        }

        @media (max-width: 700px) {

            .top {
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .share-url {
                flex-direction: column;
            }

            .video {
                align-items: flex-start;
                flex-direction: column;
            }

            .photo-toolbar {
                align-items: flex-start;
            }

            .photo-toolbar-right {
                width: 100%;
                justify-content: space-between;
            }

        }

    </style>

</head>

<body>

<div class="top">

    <div>

        <a
            class="back"
            href="/admin/events/"
        >
            ← Back to Events
        </a>

        <h1><?= e($event['name']) ?></h1>

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

    </div>

    <div>

        <?php if ($event['status'] === 'published'): ?>

            <span class="status-badge published">
                Published
            </span>

        <?php else: ?>

            <span class="status-badge draft">
                Draft
            </span>

        <?php endif; ?>

    </div>

</div>


<?php if ($importedCount !== null): ?>

    <div class="notice">
        Import complete.
        Imported <?= $importedCount ?> new
        <?= $importedCount === 1 ? 'file' : 'files' ?>.
    </div>

<?php endif; ?>


<?php if ($deletedCount !== null): ?>

    <div class="notice">
        Deleted <?= $deletedCount ?>
        <?= $deletedCount === 1 ? 'photo' : 'photos' ?>.
    </div>

<?php endif; ?>


<div class="actions">

    <a
        class="button button-primary"
        href="/admin/events/upload.php?id=<?= $eventId ?>"
    >
        Upload Media
    </a>

    <a
        class="button button-primary"
        href="/admin/events/download.php?id=<?= $eventId ?>"
    >
        Download All Media
    </a>

    <form
        method="POST"
        action="/admin/events/import.php"
        style="margin: 0;"
    >

        <input
            type="hidden"
            name="event_id"
            value="<?= $eventId ?>"
        >

        <button
            type="submit"
            class="button button-secondary"
        >
            Import from Spaces
        </button>

    </form>


    <?php if ($event['status'] === 'published'): ?>

        <form
            method="POST"
            action="/admin/events/publish.php"
            style="margin: 0;"
            onsubmit="return confirm(
                'Unpublish this gallery? The public link will stop working.'
            );"
        >

            <input
                type="hidden"
                name="event_id"
                value="<?= $eventId ?>"
            >

            <input
                type="hidden"
                name="action"
                value="unpublish"
            >

            <button
                type="submit"
                class="button button-secondary"
            >
                Unpublish Gallery
            </button>

        </form>

        <a
            class="button button-secondary"
            href="<?= e($shareUrl) ?>"
            target="_blank"
            rel="noopener"
        >
            View Public Gallery
        </a>

    <?php else: ?>

        <form
            method="POST"
            action="/admin/events/publish.php"
            style="margin: 0;"
            onsubmit="return confirm(
                'Publish this gallery? Anyone with the share link will be able to view it.'
            );"
        >

            <input
                type="hidden"
                name="event_id"
                value="<?= $eventId ?>"
            >

            <input
                type="hidden"
                name="action"
                value="publish"
            >

            <button
                type="submit"
                class="button button-primary"
            >
                Publish Gallery
            </button>

        </form>

    <?php endif; ?>

</div>


<div class="publish-box">

    <?php if ($event['status'] === 'published'): ?>

        <strong>This gallery is public.</strong>

        <p>
            Anyone with the share link can view this gallery.
        </p>

    <?php else: ?>

        <strong>This gallery is currently private.</strong>

        <p>
            The gallery will not be publicly accessible until you publish it.
        </p>

    <?php endif; ?>

</div>


<div class="share-box">

    <strong>Share Link</strong>

    <div class="share-url">

        <input
            id="shareUrl"
            type="text"
            value="<?= e($shareUrl) ?>"
            readonly
        >

        <button
            class="button button-secondary"
            type="button"
            onclick="copyShareLink()"
        >
            Copy Link
        </button>

    </div>

</div>


<div class="embed-box">

    <strong>Embed Photos</strong>

    <div class="embed-description">
        Add a photo-only gallery to an external website using an iframe.
    </div>

    <textarea
        id="embedCode"
        class="embed-code"
        readonly
    ></textarea>

    <div class="embed-actions">

        <button
            class="button button-secondary"
            type="button"
            onclick="copyEmbedCode()"
        >
            Copy Embed Code
        </button>

        <a
            class="button button-secondary"
            id="embedPreview"
            href="#"
            target="_blank"
            rel="noopener"
        >
            Preview Embed
        </a>

    </div>

</div>


<div class="stats">

    <div class="stat">

        <div class="stat-number">
            <?= count($mediaFiles) ?>
        </div>

        <div class="stat-label">
            Total Media
        </div>

    </div>

    <div class="stat">

        <div class="stat-number">
            <?= count($photos) ?>
        </div>

        <div class="stat-label">
            Photos
        </div>

    </div>

    <div class="stat">

        <div class="stat-number">
            <?= count($videos) ?>
        </div>

        <div class="stat-label">
            Videos
        </div>

    </div>

</div>


<?php if ($event['description']): ?>

    <section>

        <h2>About This Event</h2>

        <p>
            <?= nl2br(e($event['description'])) ?>
        </p>

    </section>

<?php endif; ?>


<section>

    <h2>
        Photos
        <?php if ($photos): ?>

            <span class="status">
                (<?= count($photos) ?>)
            </span>

        <?php endif; ?>
    </h2>


    <?php if (!$photos): ?>

        <div class="empty">
            No photos have been uploaded yet.
        </div>

    <?php else: ?>

        <form
            method="POST"
            action="/admin/events/bulk-delete-media.php"
            id="bulkDeleteForm"
            onsubmit="return confirmBulkDelete();"
        >

            <input
                type="hidden"
                name="event_id"
                value="<?= $eventId ?>"
            >


            <div class="photo-toolbar">

                <label class="select-all">

                    <input
                        type="checkbox"
                        id="selectAllPhotos"
                        onchange="toggleAllPhotos(this)"
                    >

                    Select All

                </label>


                <div class="photo-toolbar-right">

    <span
        class="selected-count"
        id="selectedCount"
    >
        0 selected
    </span>

    <a
        class="button button-secondary"
        href="/admin/events/download-photos.php?id=<?= $eventId ?>"
    >
        Download All Photos
    </a>

    <button
        type="submit"
        class="bulk-delete-button"
        id="bulkDeleteButton"
        disabled
    >
        Delete Selected
    </button>

</div>

            </div>


            <div class="photo-grid">

                <?php foreach ($photos as $photo): ?>

                    <div class="photo">

                        <div class="photo-image">

                            <div class="photo-checkbox-bg"></div>

                            <input
                                class="photo-checkbox"
                                type="checkbox"
                                name="media_ids[]"
                                value="<?= (int) $photo['id'] ?>"
                                onchange="updatePhotoSelection()"
                                aria-label="Select <?= e($photo['original_filename']) ?>"
                            >

                            <a
                                href="<?= e(
                                    $photo['admin_web_url']
                                    ?: $photo['cdn_url']
                                ) ?>"
                                target="_blank"
                                rel="noopener"
                            >

                                <img
                                    src="<?= e(
                                        $photo['admin_thumbnail_url']
                                        ?: $photo['cdn_url']
                                    ) ?>"
                                    alt="<?= e($photo['original_filename']) ?>"
                                    loading="lazy"
                                >

                            </a>

                        </div>


                        <div class="photo-name">
                            <?= e($photo['original_filename']) ?>
                        </div>


                        <div
                            class="media-status <?= e($photo['processing_status']) ?>"
                        >
                            <?= e(
                                ucfirst(
                                    $photo['processing_status']
                                )
                            ) ?>
                        </div>


                        <div class="photo-actions">

                            <button
                                type="button"
                                class="delete-button"
                                onclick="deleteSinglePhoto(
                                    <?= (int) $photo['id'] ?>,
                                    <?= $eventId ?>
                                )"
                            >
                                Delete Photo
                            </button>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </form>

    <?php endif; ?>

</section>


<section>

    <h2>

        Videos

        <?php if ($videos): ?>

            <span class="status">
                (<?= count($videos) ?>)
            </span>

        <?php endif; ?>

    </h2>


    <?php if (!$videos): ?>

        <div class="empty">
            No videos have been uploaded yet.
        </div>

    <?php else: ?>

        <div class="video-list">

            <?php foreach ($videos as $video): ?>

                <div class="video">

                    <div>

                        <div class="video-name">
                            <?= e($video['original_filename']) ?>
                        </div>

                        <div class="status">

                            <?= e($video['mime_type']) ?>

                            ·

                            <?= number_format(
                                ((int) $video['file_size']) / 1048576,
                                1
                            ) ?> MB

                            ·

                            <?= e(
                                ucfirst(
                                    $video['processing_status']
                                )
                            ) ?>

                        </div>

                    </div>


                    <?php if (
                        !empty($video['admin_video_url'])
                    ): ?>

                        <a
                            class="button button-secondary"
                            href="<?= e($video['admin_video_url']) ?>"
                            target="_blank"
                            rel="noopener"
                        >
                            View
                        </a>

                    <?php else: ?>

                        <span class="status">
                            Processing
                        </span>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>


<script>

function copyShareLink() {

    const input =
        document.getElementById('shareUrl');

    const button =
        document.querySelector('.share-url button');

    navigator.clipboard.writeText(
        input.value
    ).then(() => {

        const original =
            button.textContent;

        button.textContent =
            'Copied!';

        setTimeout(() => {

            button.textContent =
                original;

        }, 1500);

    });

}


function updateEmbedCode() {

    const shareUrl =
        document.getElementById('shareUrl').value;

    const embedUrl =
        shareUrl + '?embed=photos';

    const eventName =
        <?= json_encode(
            $event['name'],
            JSON_UNESCAPED_SLASHES
        ) ?>;

    const code =
        `<iframe
    src="${embedUrl}"
    style="width: 100%; height: 900px; border: 0;"
    loading="lazy"
    title="${eventName} Photos">
</iframe>`;

    document.getElementById('embedCode').value =
        code;

    document.getElementById('embedPreview').href =
        embedUrl;
}


function copyEmbedCode() {

    const textarea =
        document.getElementById('embedCode');

    const button =
        document.querySelector(
            '.embed-actions button'
        );

    navigator.clipboard.writeText(
        textarea.value
    ).then(() => {

        const original =
            button.textContent;

        button.textContent =
            'Copied!';

        setTimeout(() => {

            button.textContent =
                original;

        }, 1500);

    });

}


updateEmbedCode();


function updatePhotoSelection() {

    const checkboxes =
        document.querySelectorAll(
            '.photo-checkbox'
        );

    const checked =
        document.querySelectorAll(
            '.photo-checkbox:checked'
        );

    const count =
        checked.length;

    document.getElementById(
        'selectedCount'
    ).textContent =
        count + ' selected';

    document.getElementById(
        'bulkDeleteButton'
    ).disabled =
        count === 0;

    document.getElementById(
        'selectAllPhotos'
    ).checked =
        checkboxes.length > 0 &&
        checked.length === checkboxes.length;

}


function toggleAllPhotos(selectAll) {

    document
        .querySelectorAll('.photo-checkbox')
        .forEach((checkbox) => {

            checkbox.checked =
                selectAll.checked;

        });

    updatePhotoSelection();

}


function confirmBulkDelete() {

    const count =
        document.querySelectorAll(
            '.photo-checkbox:checked'
        ).length;

    if (count === 0) {
        return false;
    }

    return confirm(
        'Delete ' +
        count +
        (count === 1
            ? ' photo'
            : ' photos') +
        ' permanently?'
    );

}


function deleteSinglePhoto(mediaId, eventId) {

    if (!confirm(
        'Delete this photo permanently?'
    )) {
        return;
    }

    const form =
        document.createElement('form');

    form.method = 'POST';

    form.action =
        '/admin/events/delete-media.php';


    const mediaInput =
        document.createElement('input');

    mediaInput.type = 'hidden';

    mediaInput.name =
        'media_id';

    mediaInput.value =
        mediaId;


    const eventInput =
        document.createElement('input');

    eventInput.type = 'hidden';

    eventInput.name =
        'event_id';

    eventInput.value =
        eventId;


    form.appendChild(
        mediaInput
    );

    form.appendChild(
        eventInput
    );

    document.body.appendChild(
        form
    );

    form.submit();

}

</script>

</body>
</html>