<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/helpers.php';
require_once __DIR__ . '/../bootstrap.php';

use Media\Config;
use Media\Database;
use Media\Event;

Config::load(__DIR__ . '/../../..');

$eventId = (int) ($_GET['id'] ?? 0);

if ($eventId <= 0) {
    http_response_code(400);
    exit('Invalid event.');
}

$db = Database::connection();

$eventModel = new Event($db);
$event = $eventModel->findById($eventId);

if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

$shareUrl = rtrim(
    Config::get('APP_URL'),
    '/'
) . '/e/';

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
        Upload Media - <?= e($event['name']) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px 60px;
            color: #222;
        }

        a {
            color: inherit;
        }

        .back {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        h1 {
            margin: 8px 0 5px;
            font-size: 32px;
        }

        .meta {
            color: #666;
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
            border: 0;
            cursor: pointer;
            text-decoration: none;
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

        .drop-zone {
            border: 3px dashed #aaa;
            padding: 65px 20px;
            text-align: center;
            border-radius: 12px;
            margin-top: 30px;
            transition: background 0.15s, border-color 0.15s;
        }

        .drop-zone.dragover {
            border-color: #222;
            background: #f5f5f5;
        }

        .drop-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .drop-subtitle {
            color: #666;
            margin-bottom: 20px;
        }

        .choose-button {
            display: inline-block;
            padding: 11px 18px;
            background: #222;
            color: white;
            border-radius: 6px;
            cursor: pointer;
        }

        .choose-button:hover {
            opacity: 0.85;
        }

        input[type="file"] {
            display: none;
        }

        .summary {
            display: none;
            margin-top: 25px;
            padding: 18px;
            background: #f5f5f5;
            border-radius: 8px;
        }

        .summary.visible {
            display: block;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 10px;
        }

        .overall-progress {
            width: 100%;
            height: 12px;
            margin-top: 8px;
        }

        .files {
            margin-top: 20px;
        }

        .file {
            display: grid;
            grid-template-columns: 70px 1fr auto;
            gap: 15px;
            align-items: center;
            padding: 14px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
        }

        .preview {
            width: 70px;
            height: 70px;
            border-radius: 6px;
            overflow: hidden;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            font-size: 11px;
            text-align: center;
        }

        .preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .file-info {
            min-width: 0;
        }

        .file-name {
            font-weight: bold;
            word-break: break-word;
        }

        .file-size {
            color: #666;
            font-size: 13px;
            margin-top: 3px;
        }

        .file progress {
            width: 100%;
            height: 9px;
            margin-top: 9px;
        }

        .status {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
        }

        .success {
            color: #087f3f;
            font-weight: bold;
        }

        .error {
            color: #b00020;
            font-weight: bold;
        }

        .complete-actions {
            display: none;
            margin-top: 30px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
        }

        .complete-actions.visible {
            display: block;
        }

        @media (max-width: 650px) {

            .file {
                grid-template-columns: 55px 1fr;
            }

            .preview {
                width: 55px;
                height: 55px;
            }

            .status {
                grid-column: 2;
            }

            .summary-row {
                flex-direction: column;
                gap: 4px;
            }

        }

    </style>

</head>

<body>

<a
    class="back"
    href="/admin/events/view.php?id=<?= $eventId ?>"
>
    ← Back to Event
</a>

<h1>Upload Media</h1>

<div class="meta">

    <?= e($event['name']) ?>

    <?php if ($event['event_date']): ?>

        · <?= e($event['event_date']) ?>

    <?php endif; ?>

    <?php if ($event['location']): ?>

        · <?= e($event['location']) ?>

    <?php endif; ?>

</div>


<div class="actions">

    <a
        class="button button-secondary"
        href="/admin/events/view.php?id=<?= $eventId ?>"
    >
        Event Dashboard
    </a>

</div>


<div
    class="drop-zone"
    id="dropZone"
>

    <div class="drop-title">
        Drag photos and videos here
    </div>

    <div class="drop-subtitle">
        Drop as many files as you want, or choose files from your computer.
    </div>

    <label
        class="choose-button"
        for="fileInput"
    >
        Choose Files
    </label>

    <input
        type="file"
        id="fileInput"
        multiple
        accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm"
    >

</div>


<div
    class="summary"
    id="summary"
>

    <div class="summary-row">

        <strong>
            Upload Progress
        </strong>

        <span id="summaryText">
            Preparing...
        </span>

    </div>

    <progress
        class="overall-progress"
        id="overallProgress"
        value="0"
        max="100"
    ></progress>

</div>


<div
    class="files"
    id="files"
></div>


<div
    class="complete-actions"
    id="completeActions"
>

    <strong>
        Uploads finished.
    </strong>

    <div style="margin-top: 12px;">

        <a
            class="button button-primary"
            href="/admin/events/view.php?id=<?= $eventId ?>"
        >
            Back to Event
        </a>

        <a
            class="button button-secondary"
            id="galleryLink"
            href="#"
            target="_blank"
            rel="noopener"
        >
            View Public Gallery
        </a>

    </div>

</div>


<script>

const eventId = <?= $eventId ?>;

const dropZone =
    document.getElementById('dropZone');

const fileInput =
    document.getElementById('fileInput');

const filesContainer =
    document.getElementById('files');

const summary =
    document.getElementById('summary');

const summaryText =
    document.getElementById('summaryText');

const overallProgress =
    document.getElementById('overallProgress');

const completeActions =
    document.getElementById('completeActions');

const galleryLink =
    document.getElementById('galleryLink');


let totalFiles = 0;
let completedFiles = 0;
let totalBytes = 0;
let uploadedBytes = 0;


fileInput.addEventListener(
    'change',
    () => {

        handleFiles(
            Array.from(fileInput.files)
        );

        fileInput.value = '';

    }
);


dropZone.addEventListener(
    'dragover',
    (event) => {

        event.preventDefault();

        dropZone.classList.add(
            'dragover'
        );

    }
);


dropZone.addEventListener(
    'dragleave',
    () => {

        dropZone.classList.remove(
            'dragover'
        );

    }
);


dropZone.addEventListener(
    'drop',
    (event) => {

        event.preventDefault();

        dropZone.classList.remove(
            'dragover'
        );

        handleFiles(
            Array.from(
                event.dataTransfer.files
            )
        );

    }
);


async function handleFiles(files) {

    if (!files.length) {
        return;
    }

    const validFiles =
        files.filter(isAllowedFile);

    const invalidCount =
        files.length - validFiles.length;

    if (invalidCount > 0) {

        alert(
            invalidCount +
            ' file(s) were skipped because their file type is not supported.'
        );

    }

    if (!validFiles.length) {
        return;
    }

    totalFiles += validFiles.length;

    totalBytes += validFiles.reduce(
        (total, file) => total + file.size,
        0
    );

    summary.classList.add('visible');

    updateOverallProgress();

    /*
     * Upload concurrently.
     *
     * The browser controls the number of
     * simultaneous requests naturally, while
     * each file retains its own progress.
     */

    await Promise.all(
        validFiles.map(
            file => uploadFile(file)
        )
    );

    if (
        completedFiles >= totalFiles
    ) {

        completeActions.classList.add(
            'visible'
        );

    }

}


function isAllowedFile(file) {

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'video/mp4',
        'video/quicktime',
        'video/webm'
    ];

    return allowedTypes.includes(
        file.type
    );

}


async function uploadFile(file) {

    const container =
        document.createElement('div');

    container.className = 'file';

    const preview =
        document.createElement('div');

    preview.className = 'preview';

    if (
        file.type.startsWith('image/')
    ) {

        const image =
            document.createElement('img');

        image.src =
            URL.createObjectURL(file);

        image.alt = '';

        preview.appendChild(image);

    } else {

        preview.textContent =
            'VIDEO';

    }


    const info =
        document.createElement('div');

    info.className = 'file-info';

    info.innerHTML = `
        <div class="file-name">
            ${escapeHtml(file.name)}
        </div>

        <div class="file-size">
            ${formatBytes(file.size)}
        </div>

        <progress
            value="0"
            max="100"
        ></progress>
    `;


    const status =
        document.createElement('div');

    status.className = 'status';

    status.textContent =
        'Preparing...';


    container.appendChild(preview);
    container.appendChild(info);
    container.appendChild(status);

    filesContainer.appendChild(container);


    const progress =
        info.querySelector('progress');


    try {

        const response =
            await fetch(
                '/api/upload-url.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({
                        event_id: eventId,
                        filename: file.name,
                        content_type: file.type,
                        file_size: file.size
                    })
                }
            );


        const data =
            await response.json();


        if (
            !response.ok ||
            !data.success
        ) {

            throw new Error(
                data.error ||
                'Unable to prepare upload.'
            );

        }


        status.textContent =
            'Uploading...';


        await uploadToSpaces(
            data.upload_url,
            file,
            progress,
            (loaded) => {

                updateOverallProgress(
                    loaded,
                    file.size
                );

            }
        );


        status.textContent =
            'Verifying...';


        const completeResponse =
            await fetch(
                '/api/complete-upload.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({
                        media_id:
                            data.media_id
                    })
                }
            );


        const completeData =
            await completeResponse.json();


        if (
            !completeResponse.ok ||
            !completeData.success
        ) {

            throw new Error(
                completeData.error ||
                'Unable to verify uploaded file.'
            );

        }


        completedFiles++;

        status.innerHTML =
            '<span class="success">' +
            '✓ Complete' +
            '</span>';


        updateOverallProgress();


    } catch (error) {

        console.error(error);

        status.innerHTML =
            '<span class="error">' +
            escapeHtml(
                error.message
            ) +
            '</span>';

    }

}


function uploadToSpaces(
    url,
    file,
    progress,
    onProgress
) {

    return new Promise(
        (resolve, reject) => {

            const xhr =
                new XMLHttpRequest();


            xhr.open(
                'PUT',
                url,
                true
            );


            xhr.setRequestHeader(
                'Content-Type',
                file.type
            );


            xhr.upload.addEventListener(
                'progress',
                (event) => {

                    if (
                        event.lengthComputable
                    ) {

                        const loaded =
                            event.loaded;

                        progress.value =
                            (
                                loaded /
                                event.total
                            ) * 100;


                        if (onProgress) {

                            onProgress(
                                loaded
                            );

                        }

                    }

                }
            );


            xhr.onload = () => {

                if (
                    xhr.status >= 200 &&
                    xhr.status < 300
                ) {

                    resolve();

                } else {

                    reject(
                        new Error(
                            'Spaces upload failed (' +
                            xhr.status +
                            ').'
                        )
                    );

                }

            };


            xhr.onerror = () => {

                reject(
                    new Error(
                        'Network error during upload.'
                    )
                );

            };


            xhr.send(file);

        }
    );

}


function updateOverallProgress() {

    /*
     * For now, calculate progress from completed
     * files. Individual file progress remains
     * visible and accurate.
     */

    if (totalFiles <= 0) {
        return;
    }

    const percent =
        (
            completedFiles /
            totalFiles
        ) * 100;

    overallProgress.value =
        percent;


    summaryText.textContent =
        completedFiles +
        ' of ' +
        totalFiles +
        ' files complete';

}


function formatBytes(bytes) {

    if (bytes === 0) {
        return '0 Bytes';
    }

    const units = [
        'Bytes',
        'KB',
        'MB',
        'GB',
        'TB'
    ];

    const i =
        Math.floor(
            Math.log(bytes) /
            Math.log(1024)
        );

    return (
        parseFloat(
            (
                bytes /
                Math.pow(1024, i)
            ).toFixed(2)
        )
        + ' '
        + units[i]
    );

}


function escapeHtml(value) {

    const div =
        document.createElement('div');

    div.textContent =
        value;

    return div.innerHTML;

}

</script>

</body>
</html>