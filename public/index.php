<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/helpers.php';

use Media\Config;
use Media\Database;
use Media\MediaFile;
use Media\ShareLink;
use Media\Spaces;

Config::load(__DIR__ . '/..');

$path = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

$path = trim($path, '/');

$embedPhotos = (
    isset($_GET['embed'])
    && $_GET['embed'] === 'photos'
);

/*
 * Public event route:
 *
 * /e/{token}
 */
if (preg_match('#^e/([a-f0-9]{32})$#', $path, $matches)) {

    $token = $matches[1];

    $db = Database::connection();

    $shareModel = new ShareLink($db);

    $share = $shareModel->findByToken($token);

    if (!$share || $share['status'] !== 'published') {

        http_response_code(404);

        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Gallery Not Found</title>';
        echo '<style>';
        echo 'body{margin:0;background:#111;color:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center}';
        echo '.box{padding:40px}.box h1{font-size:32px;margin:0 0 10px}.box p{color:#aaa}';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="box">';
        echo '<h1>Gallery Not Found</h1>';
        echo '<p>This gallery is unavailable.</p>';
        echo '</div>';
        echo '</body>';
        echo '</html>';

        exit;
    }

    $mediaModel = new MediaFile($db);

    $mediaFiles = $mediaModel->forEvent(
        (int) $share['event_id']
    );

    /*
     * Only display completed media.
     */
    $completeMedia = array_values(
        array_filter(
            $mediaFiles,
            static function (array $media): bool {
                return $media['processing_status'] === 'complete';
            }
        )
    );

    $photos = array_values(
        array_filter(
            $completeMedia,
            static function (array $media): bool {
                return $media['media_type'] === 'photo';
            }
        )
    );

    $videos = array_values(
        array_filter(
            $completeMedia,
            static function (array $media): bool {
                return $media['media_type'] === 'video';
            }
        )
    );

/*
 * Build temporary private display URLs.
 */
$spaces = new Spaces();

foreach ($photos as &$photo) {

    if (!empty($photo['thumbnail_key'])) {
        $photo['thumbnail_display_url'] =
            $spaces->createDownloadUrl(
                $photo['thumbnail_key'],
                60
            );
    } else {
        $photo['thumbnail_display_url'] = '';
    }

    if (!empty($photo['web_key'])) {
        $photo['web_display_url'] =
            $spaces->createDownloadUrl(
                $photo['web_key'],
                60
            );
    } else {
        $photo['web_display_url'] = '';
    }

    if (!empty($photo['storage_key'])) {
        $photo['original_display_url'] =
            $spaces->createDownloadUrl(
                $photo['storage_key'],
                60
            );
    } else {
        $photo['original_display_url'] = '';
    }
}

unset($photo);

foreach ($videos as &$video) {

    if (!empty($video['thumbnail_key'])) {
        $video['thumbnail_display_url'] =
            $spaces->createDownloadUrl(
                $video['thumbnail_key'],
                60
            );
    } else {
        $video['thumbnail_display_url'] = '';
    }

    if (!empty($video['video_key'])) {
        $video['video_display_url'] =
            $spaces->createDownloadUrl(
                $video['video_key'],
                60
            );
    } else {
        $video['video_display_url'] = '';
    }
}

unset($video);

    /*
     * Format event date.
     */
    $formattedDate = '';

    if (!empty($share['event_date'])) {

        $timestamp = strtotime(
            (string) $share['event_date']
        );

        if ($timestamp !== false) {

            $formattedDate = date(
                'F j, Y',
                $timestamp
            );
        }
    }

    /*
     * Format video durations.
     */
    foreach ($videos as &$video) {

        $duration = (float) (
            $video['duration_seconds'] ?? 0
        );

        if ($duration > 0) {

            $totalSeconds = (int) round($duration);

            $hours = intdiv(
                $totalSeconds,
                3600
            );

            $minutes = intdiv(
                $totalSeconds % 3600,
                60
            );

            $seconds =
                $totalSeconds % 60;

            if ($hours > 0) {

                $video['duration_display'] =
                    sprintf(
                        '%d:%02d:%02d',
                        $hours,
                        $minutes,
                        $seconds
                    );

            } else {

                $video['duration_display'] =
                    sprintf(
                        '%d:%02d',
                        $minutes,
                        $seconds
                    );
            }

        } else {

            $video['duration_display'] = '';
        }
    }

    unset($video);

    ?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="description"
    content="<?= e($share['name']) ?> media gallery"
>

<meta
    name="theme-color"
    content="#0b0b0c"
>

<title>
    <?= e($share['name']) ?> · Media Gallery
</title>

<style>

    :root {
        --bg: #0b0b0c;
        --surface: #131315;
        --surface-light: #1b1b1e;
        --text: #f5f5f5;
        --muted: #a0a0a5;
        --muted-dark: #707075;
        --border: rgba(255,255,255,0.08);
        --accent: #ffffff;
    }

    * {
        box-sizing: border-box;
    }

    html {
        background: var(--bg);
        scroll-behavior: smooth;
    }

    body {
        margin: 0;
        font-family:
            -apple-system,
            BlinkMacSystemFont,
            "Segoe UI",
            Roboto,
            Helvetica,
            Arial,
            sans-serif;
        color: var(--text);
        background: var(--bg);
        min-height: 100vh;
    }

    button,
    a {
        -webkit-tap-highlight-color: transparent;
    }

    /*
     * Page
     */

    .page {
        width: min(
            1500px,
            calc(100% - 40px)
        );

        margin: 0 auto;
        padding-bottom: 80px;
    }

    /*
     * Hero
     */

    .hero {
        position: relative;
        margin: 0 -20px 55px;
        padding: 90px 40px 55px;
        overflow: hidden;

        background:
            radial-gradient(
                circle at 20% 0%,
                rgba(255,255,255,0.10),
                transparent 40%
            ),
            radial-gradient(
                circle at 85% 30%,
                rgba(255,255,255,0.06),
                transparent 35%
            ),
            linear-gradient(
                135deg,
                #171719 0%,
                #0b0b0c 70%
            );

        border-bottom: 1px solid var(--border);
    }

    .hero::after {
        content: "";
        position: absolute;
        inset: auto 0 0 0;
        height: 1px;
        background:
            linear-gradient(
                90deg,
                transparent,
                rgba(255,255,255,0.25),
                transparent
            );
    }

    .hero-inner {
        position: relative;
        z-index: 1;
        max-width: 1000px;
        margin: 0 auto;
        text-align: center;
    }

    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 18px;
        color: #aaa;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.16em;
        text-transform: uppercase;
    }

    .eyebrow::before {
        content: "";
        width: 24px;
        height: 1px;
        background: #777;
    }

    .event-title {
        margin: 0;
        font-size: clamp(
            34px,
            5vw,
            64px
        );
        line-height: 1.02;
        letter-spacing: -0.035em;
        font-weight: 750;
    }

    .event-meta {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px 12px;
        margin-top: 22px;
        color: var(--muted);
        font-size: 15px;
    }

    .event-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .event-meta-separator {
        color: #555;
    }

    .event-description {
        max-width: 720px;
        margin: 25px auto 0;
        color: #b6b6ba;
        font-size: 15px;
        line-height: 1.7;
    }

    /*
     * Stats
     */

    .stats {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 30px;
    }

    .stat {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 14px;
        border: 1px solid var(--border);
        border-radius: 999px;
        background: rgba(255,255,255,0.045);
        color: #d0d0d4;
        font-size: 12px;
        font-weight: 600;
    }

    /*
     * Sections
     */

    .section {
        margin-top: 55px;
    }

    .section:first-child {
        margin-top: 0;
    }

    .section-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 22px;
    }

    .section-heading-left {
        min-width: 0;
    }

    .section-heading h2 {
        margin: 0;
        font-size: 26px;
        line-height: 1.1;
        letter-spacing: -0.02em;
    }

    .section-subtitle {
        margin-top: 5px;
        color: var(--muted-dark);
        font-size: 13px;
    }

    .section-count {
        flex-shrink: 0;
        color: var(--muted-dark);
        font-size: 13px;
    }

    /*
     * Photos
     */

    .photo-grid {
        columns: 4 260px;
        column-gap: 14px;
    }

    .photo-card {
        break-inside: avoid;
        margin-bottom: 14px;
        overflow: hidden;
        border-radius: 10px;
        background: var(--surface);
    }

    .photo-link {
        display: block;
        position: relative;
        overflow: hidden;
        cursor: zoom-in;
    }

    .photo-link::after {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(
                to bottom,
                transparent 60%,
                rgba(0,0,0,0.18)
            );
        opacity: 0;
        transition: opacity 0.25s ease;
    }

    .photo-card img {
        display: block;
        width: 100%;
        height: auto;
        transition:
            transform 0.35s ease,
            opacity 0.25s ease;
    }

    .photo-card:hover img {
        transform: scale(1.025);
    }

    .photo-card:hover .photo-link::after {
        opacity: 1;
    }

    /*
     * Videos
     */

    .video-grid {
        display: grid;
        grid-template-columns:
            repeat(
                auto-fill,
                minmax(300px, 1fr)
            );
        gap: 18px;
    }

    .video-card {
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: var(--surface);
        transition:
            transform 0.25s ease,
            border-color 0.25s ease;
    }

    .video-card:hover {
        transform: translateY(-2px);
        border-color:
            rgba(255,255,255,0.18);
    }

    .video-preview {
        position: relative;
        aspect-ratio: 16 / 9;
        overflow: hidden;
        background: #050505;
        cursor: pointer;
    }

    .video-preview img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition:
            transform 0.4s ease,
            opacity 0.25s ease;
    }

    .video-card:hover
    .video-preview img {
        transform: scale(1.03);
    }

    .video-preview-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        color: #555;
        font-size: 13px;
    }

    .video-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            linear-gradient(
                rgba(0,0,0,0.02),
                rgba(0,0,0,0.22)
            );
    }

    .play-button {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 64px;
        height: 64px;
        padding-left: 4px;
        border: 1px solid
            rgba(255,255,255,0.25);
        border-radius: 50%;
        background:
            rgba(0,0,0,0.55);
        color: white;
        font-size: 23px;
        backdrop-filter: blur(8px);
        transition:
            transform 0.2s ease,
            background 0.2s ease;
    }

    .video-card:hover .play-button {
        transform: scale(1.08);
        background:
            rgba(255,255,255,0.18);
    }

    .duration {
        position: absolute;
        right: 10px;
        bottom: 10px;
        padding: 5px 7px;
        border-radius: 5px;
        background:
            rgba(0,0,0,0.72);
        color: white;
        font-size: 11px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .video-info {
        padding: 13px 15px 15px;
    }

    .video-filename {
        overflow: hidden;
        color: #d4d4d7;
        font-size: 13px;
        line-height: 1.4;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .video-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 10px;
    }

    .watch-label {
        color: var(--muted-dark);
        font-size: 11px;
    }

    .download {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border: 1px solid var(--border);
        border-radius: 6px;
        color: #ccc;
        text-decoration: none;
        font-size: 11px;
        font-weight: 600;
        transition:
            background 0.2s ease,
            border-color 0.2s ease;
    }

    .download:hover {
        border-color:
            rgba(255,255,255,0.2);
        background:
            rgba(255,255,255,0.07);
    }

    /*
     * Empty state
     */

    .empty {
        max-width: 650px;
        margin: 50px auto 0;
        padding: 70px 30px;
        text-align: center;
        border: 1px solid var(--border);
        border-radius: 14px;
        background: var(--surface);
    }

    .empty-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 60px;
        height: 60px;
        margin: 0 auto 20px;
        border-radius: 50%;
        background: var(--surface-light);
        color: #aaa;
        font-size: 25px;
    }

    .empty h2 {
        margin: 0;
        font-size: 22px;
    }

    .empty p {
        margin: 10px 0 0;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.6;
    }

    /*
     * Photo lightbox
     */

    .lightbox {
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 30px;
        background:
            rgba(0,0,0,0.96);
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .lightbox.open {
        display: flex;
        opacity: 1;
    }

    .lightbox-image {
        display: block;
        max-width: 92vw;
        max-height: 78vh;
        object-fit: contain;
        border-radius: 3px;
        box-shadow:
            0 20px 80px rgba(0,0,0,0.5);
    }

    .lightbox-close {
        position: fixed;
        top: 18px;
        right: 20px;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border: 1px solid
            rgba(255,255,255,0.12);
        border-radius: 50%;
        background:
            rgba(255,255,255,0.08);
        color: white;
        font-size: 26px;
        cursor: pointer;
        transition:
            background 0.2s ease;
    }

    .lightbox-close:hover {
        background:
            rgba(255,255,255,0.16);
    }

    .lightbox-nav {
        position: fixed;
        top: 50%;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 62px;
        border: 1px solid
            rgba(255,255,255,0.12);
        border-radius: 9px;
        background:
            rgba(255,255,255,0.08);
        color: white;
        font-size: 31px;
        cursor: pointer;
        transform: translateY(-50%);
        transition:
            background 0.2s ease;
    }

    .lightbox-nav:hover {
        background:
            rgba(255,255,255,0.16);
    }

    .lightbox-prev {
        left: 18px;
    }

    .lightbox-next {
        right: 18px;
    }

    .lightbox-top {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        color: #aaa;
        font-size: 12px;
        font-variant-numeric: tabular-nums;
    }

    .lightbox-caption {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        padding: 0 75px;
    }

    .lightbox-filename {
        max-width: 60vw;
        overflow: hidden;
        color: #ddd;
        font-size: 12px;
        text-align: center;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .lightbox-download {
        display: inline-flex;
        align-items: center;
        padding: 8px 12px;
        flex-shrink: 0;
        border: 1px solid
            rgba(255,255,255,0.14);
        border-radius: 6px;
        background:
            rgba(255,255,255,0.08);
        color: white;
        text-decoration: none;
        font-size: 11px;
        font-weight: 600;
    }

    .lightbox-download:hover {
        background:
            rgba(255,255,255,0.15);
    }

    /*
     * Video lightbox
     */

    .video-lightbox {
        position: fixed;
        inset: 0;
        z-index: 1100;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 30px;
        background:
            rgba(0,0,0,0.97);
    }

    .video-lightbox.open {
        display: flex;
    }

    .video-lightbox-inner {
        width: min(
            1200px,
            94vw
        );
    }

    .video-lightbox video {
        display: block;
        width: 100%;
        max-height: 78vh;
        background: #000;
        border-radius: 6px;
    }

    .video-lightbox-info {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-top: 12px;
    }

    .video-lightbox-name {
        min-width: 0;
        overflow: hidden;
        color: #ddd;
        font-size: 13px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .video-lightbox-download {
        flex-shrink: 0;
        padding: 8px 12px;
        border: 1px solid
            rgba(255,255,255,0.15);
        border-radius: 6px;
        color: white;
        text-decoration: none;
        font-size: 11px;
        font-weight: 600;
    }

    .video-lightbox-download:hover {
        background:
            rgba(255,255,255,0.10);
    }

    /*
     * Footer
     */

    .footer {
        margin-top: 75px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        color: #555;
        text-align: center;
        font-size: 11px;
    }

    /*
     * Responsive
     */

    @media (max-width: 1100px) {

        .photo-grid {
            columns: 3 240px;
        }

    }

    @media (max-width: 750px) {

        .page {
            width: min(
                100% - 24px,
                1500px
            );
        }

        .hero {
            margin:
                0 -12px 40px;
            padding:
                65px 20px 40px;
        }

        .event-title {
            font-size: 38px;
        }

        .photo-grid {
            columns: 2 150px;
            column-gap: 9px;
        }

        .photo-card {
            margin-bottom: 9px;
            border-radius: 7px;
        }

        .video-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .section {
            margin-top: 42px;
        }

        .section-heading h2 {
            font-size: 22px;
        }

        .lightbox {
            padding: 10px;
        }

        .lightbox-image {
            max-width: 96vw;
            max-height: 75vh;
        }

        .lightbox-nav {
            width: 40px;
            height: 50px;
            font-size: 25px;
        }

        .lightbox-prev {
            left: 8px;
        }

        .lightbox-next {
            right: 8px;
        }

        .lightbox-top {
            top: 15px;
        }

        .lightbox-caption {
            bottom: 10px;
            padding:
                0 50px;
        }

        .lightbox-filename {
            max-width: 48vw;
        }

        .video-lightbox {
            padding: 12px;
        }

        .video-lightbox video {
            max-height: 70vh;
        }

    }

    @media (max-width: 450px) {

        .event-title {
            font-size: 32px;
        }

        .event-meta {
            font-size: 13px;
        }

        .stats {
            gap: 6px;
        }

        .stat {
            padding:
                7px 10px;
            font-size: 11px;
        }

        .photo-grid {
            columns: 2 120px;
        }

        .play-button {
            width: 54px;
            height: 54px;
            font-size: 19px;
        }

        .video-lightbox-info {
            align-items: flex-start;
            flex-direction: column;
        }

    }

    /*
     * Reduced motion
     */

    @media (prefers-reduced-motion: reduce) {

        html {
            scroll-behavior: auto;
        }

        *,
        *::before,
        *::after {
            transition: none !important;
        }

    }

    /* Photo-only external embed */

body.embed-photos {
    background: #000;
}

body.embed-photos .page {
    width: 100%;
    max-width: none;
    padding: 0;
}

body.embed-photos .hero,
body.embed-photos .video-section,
body.embed-photos .footer {
    display: none;
}

body.embed-photos .section {
    margin: 0;
}

body.embed-photos .section-heading {
    display: none;
}

body.embed-photos .photo-grid {
    width: 100%;
    padding: 8px;
    columns: 4 220px;
    column-gap: 8px;
}

body.embed-photos .photo-card {
    margin-bottom: 8px;
    border-radius: 5px;
}

body.embed-photos .lightbox-caption {
    display: none;
}

@media (max-width: 900px) {
    body.embed-photos .photo-grid {
        columns: 3 180px;
    }
}

@media (max-width: 600px) {
    body.embed-photos .photo-grid {
        columns: 2 140px;
        padding: 5px;
        column-gap: 5px;
    }

    body.embed-photos .photo-card {
        margin-bottom: 5px;
    }
}

</style>

</head>

<body class="<?= $embedPhotos ? 'embed-photos' : '' ?>">

<div class="page">

<header class="hero">

    <div class="hero-inner">

        <div class="eyebrow">
            Media Gallery
        </div>

        <h1 class="event-title">
            <?= e($share['name']) ?>
        </h1>

        <?php if (
            $formattedDate ||
            $share['location']
        ): ?>

            <div class="event-meta">

                <?php if ($formattedDate): ?>

                    <span class="event-meta-item">
                        <?= e($formattedDate) ?>
                    </span>

                <?php endif; ?>

                <?php if (
                    $formattedDate &&
                    $share['location']
                ): ?>

                    <span class="event-meta-separator">
                        ·
                    </span>

                <?php endif; ?>

                <?php if ($share['location']): ?>

                    <span class="event-meta-item">
                        <?= e($share['location']) ?>
                    </span>

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <?php if ($share['description']): ?>

            <div class="event-description">

                <?= nl2br(
                    e($share['description'])
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($completeMedia): ?>

            <div class="stats">

                <div class="stat">

                    <?= count($completeMedia) ?>

                    <?= count($completeMedia) === 1
                        ? 'item'
                        : 'items' ?>

                </div>

                <?php if ($photos): ?>

                    <div class="stat">

                        <?= count($photos) ?>

                        <?= count($photos) === 1
                            ? 'photo'
                            : 'photos' ?>

                    </div>

                <?php endif; ?>

                <?php if ($videos && !$embedPhotos): ?>

                    <div class="stat">

                        <?= count($videos) ?>

                        <?= count($videos) === 1
                            ? 'video'
                            : 'videos' ?>

                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</header>


<?php if (!$completeMedia): ?>

    <div class="empty">

        <div class="empty-icon">
            ▣
        </div>

        <h2>
            Media Coming Soon
        </h2>

        <p>
            Photos and videos from this event
            will appear here.
        </p>

    </div>

<?php else: ?>


    <?php if ($photos): ?>

        <section class="section">

            <div class="section-heading">

                <div class="section-heading-left">

                    <h2>
                        Photos
                    </h2>

                    <div class="section-subtitle">
                        Tap any photo to view it full size.
                    </div>

                </div>

                <div class="section-count">

                    <?= count($photos) ?>

                    <?= count($photos) === 1
                        ? 'photo'
                        : 'photos' ?>

                </div>

            </div>


            <div class="photo-grid">

                <?php foreach (
                    $photos as $index => $photo
                ): ?>

                    <div class="photo-card">

                        <a
                            href="<?= e(
                                $photo['web_display_url']
                            ) ?>"
                            class="photo-link"
                            data-index="<?= $index ?>"
                        >

                            <img
                                src="<?= e(
                                    $photo['thumbnail_display_url']
                                ) ?>"
                                alt="<?= e(
                                    $photo['original_filename']
                                ) ?>"
                                loading="lazy"
                                decoding="async"
                            >

                        </a>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>


    <?php if ($videos && !$embedPhotos): ?>

        <section class="section">

            <div class="section-heading">

                <div class="section-heading-left">

                    <h2>
                        Videos
                    </h2>

                    <div class="section-subtitle">
                        Tap a video to watch.
                    </div>

                </div>

                <div class="section-count">

                    <?= count($videos) ?>

                    <?= count($videos) === 1
                        ? 'video'
                        : 'videos' ?>

                </div>

            </div>


            <div class="video-grid">

                <?php foreach (
                    $videos as $index => $video
                ): ?>

                    <article
                        class="video-card"
                        data-video-index="<?= $index ?>"
                    >

                        <div
                            class="video-preview"
                            role="button"
                            tabindex="0"
                            aria-label="Play <?= e(
                                $video['original_filename']
                            ) ?>"
                            data-video-url="<?= e(
                                $video['video_display_url']
                            ) ?>"
                            data-video-name="<?= e(
                                $video['original_filename']
                            ) ?>"
                        >

                            <?php if (
                                $video['thumbnail_display_url']
                            ): ?>

                                <img
                                    src="<?= e(
                                        $video['thumbnail_display_url']
                                    ) ?>"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                >

                            <?php else: ?>

                                <div
                                    class="video-preview-placeholder"
                                >
                                    Video
                                </div>

                            <?php endif; ?>


                            <div class="video-overlay">

                                <div class="play-button">
                                    ▶
                                </div>

                            </div>


                            <?php if (
                                $video['duration_display']
                            ): ?>

                                <div class="duration">

                                    <?= e(
                                        $video['duration_display']
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="video-info">

                            <div
                                class="video-filename"
                                title="<?= e(
                                    $video['original_filename']
                                ) ?>"
                            >

                                <?= e(
                                    $video['original_filename']
                                ) ?>

                            </div>


                            <div class="video-actions">

                                <span class="watch-label">
                                    Video
                                </span>

                                <a
                                    class="download"
                                    href="<?= e(
                                        $video['video_display_url']
                                    ) ?>"
                                    download
                                >
                                    Download
                                </a>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>


<?php endif; ?>


<footer class="footer">
    Media Gallery
</footer>
```

</div>

<!-- Photo Lightbox -->

<div
    class="lightbox"
    id="lightbox"
    aria-hidden="true"
>

```
<div
    class="lightbox-top"
    id="lightboxCounter"
>
</div>


<button
    class="lightbox-close"
    id="lightboxClose"
    type="button"
    aria-label="Close"
>
    ×
</button>


<button
    class="lightbox-nav lightbox-prev"
    id="lightboxPrev"
    type="button"
    aria-label="Previous photo"
>
    ‹
</button>


<img
    class="lightbox-image"
    id="lightboxImage"
    src=""
    alt=""
>


<button
    class="lightbox-nav lightbox-next"
    id="lightboxNext"
    type="button"
    aria-label="Next photo"
>
    ›
</button>


<div class="lightbox-caption">

    <span
        class="lightbox-filename"
        id="lightboxFilename"
    ></span>

    <a
        class="lightbox-download"
        id="lightboxDownload"
        href="#"
        target="_blank"
        rel="noopener"
    >
        Download Original
    </a>

</div>
```

</div>

<!-- Video Lightbox -->

<div
    class="video-lightbox"
    id="videoLightbox"
    aria-hidden="true"
>

```
<button
    class="lightbox-close"
    id="videoLightboxClose"
    type="button"
    aria-label="Close video"
>
    ×
</button>


<div class="video-lightbox-inner">

    <video
        id="videoPlayer"
        controls
        playsinline
        preload="metadata"
    >
        Your browser does not support
        video playback.
    </video>


    <div class="video-lightbox-info">

        <div
            class="video-lightbox-name"
            id="videoLightboxName"
        >
        </div>

        <a
            class="video-lightbox-download"
            id="videoLightboxDownload"
            href="#"
            target="_blank"
            rel="noopener"
            download
        >
            Download Video
        </a>

    </div>

</div>

</div>

<script>

const photos = [

<?php foreach ($photos as $photo): ?>

    {
        url: <?= json_encode(
            $photo['web_display_url'],
            JSON_UNESCAPED_SLASHES
        ) ?>,

        original: <?= json_encode(
    $photo['original_display_url'],
            JSON_UNESCAPED_SLASHES
        ) ?>,

        name: <?= json_encode(
            $photo['original_filename'],
            JSON_UNESCAPED_SLASHES
        ) ?>
    },

<?php endforeach; ?>

];


const lightbox =
    document.getElementById(
        'lightbox'
    );

const lightboxImage =
    document.getElementById(
        'lightboxImage'
    );

const lightboxFilename =
    document.getElementById(
        'lightboxFilename'
    );

const lightboxDownload =
    document.getElementById(
        'lightboxDownload'
    );

const lightboxCounter =
    document.getElementById(
        'lightboxCounter'
    );

const lightboxClose =
    document.getElementById(
        'lightboxClose'
    );

const lightboxPrev =
    document.getElementById(
        'lightboxPrev'
    );

const lightboxNext =
    document.getElementById(
        'lightboxNext'
    );


let currentPhoto = 0;


/*
 * Photo links
 */

document
    .querySelectorAll('.photo-link')
    .forEach(link => {

        link.addEventListener(
            'click',
            event => {

                event.preventDefault();

                currentPhoto =
                    parseInt(
                        link.dataset.index,
                        10
                    );

                showPhoto();

            }
        );

    });


function showPhoto() {

    if (!photos.length) {
        return;
    }

    const photo =
        photos[currentPhoto];

    lightboxImage.src =
        photo.url;

    lightboxImage.alt =
        photo.name;

    lightboxFilename.textContent =
        photo.name;

    lightboxDownload.href =
        photo.original;

    lightboxCounter.textContent =
        `${currentPhoto + 1} / ${photos.length}`;

    lightbox.classList.add(
        'open'
    );

    lightbox.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.style.overflow =
        'hidden';

}


function closeLightbox() {

    lightbox.classList.remove(
        'open'
    );

    lightbox.setAttribute(
        'aria-hidden',
        'true'
    );

    lightboxImage.src =
        '';

    document.body.style.overflow =
        '';

}


function nextPhoto() {

    if (!photos.length) {
        return;
    }

    currentPhoto =
        (
            currentPhoto + 1
        ) % photos.length;

    showPhoto();

}


function previousPhoto() {

    if (!photos.length) {
        return;
    }

    currentPhoto =
        (
            currentPhoto - 1 +
            photos.length
        ) % photos.length;

    showPhoto();

}


lightboxClose.addEventListener(
    'click',
    closeLightbox
);

lightboxNext.addEventListener(
    'click',
    nextPhoto
);

lightboxPrev.addEventListener(
    'click',
    previousPhoto
);


lightbox.addEventListener(
    'click',
    event => {

        if (
            event.target === lightbox
        ) {

            closeLightbox();

        }

    }
);


/*
 * Video lightbox
 */

const videoLightbox =
    document.getElementById(
        'videoLightbox'
    );

const videoPlayer =
    document.getElementById(
        'videoPlayer'
    );

const videoLightboxName =
    document.getElementById(
        'videoLightboxName'
    );

const videoLightboxDownload =
    document.getElementById(
        'videoLightboxDownload'
    );

const videoLightboxClose =
    document.getElementById(
        'videoLightboxClose'
    );


function openVideo(
    url,
    name
) {

    videoPlayer.src =
        url;

    videoLightboxName.textContent =
        name;

    videoLightboxDownload.href =
        url;

    videoLightbox.classList.add(
        'open'
    );

    videoLightbox.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.style.overflow =
        'hidden';

    videoPlayer.play()
        .catch(() => {});

}


function closeVideo() {

    videoPlayer.pause();

    videoPlayer.removeAttribute(
        'src'
    );

    videoPlayer.load();

    videoLightbox.classList.remove(
        'open'
    );

    videoLightbox.setAttribute(
        'aria-hidden',
        'true'
    );

    document.body.style.overflow =
        '';

}


document
    .querySelectorAll('.video-preview')
    .forEach(preview => {

        const play =
            () => {

                openVideo(
                    preview.dataset.videoUrl,
                    preview.dataset.videoName
                );

            };


        preview.addEventListener(
            'click',
            play
        );


        preview.addEventListener(
            'keydown',
            event => {

                if (
                    event.key === 'Enter' ||
                    event.key === ' '
                ) {

                    event.preventDefault();

                    play();

                }

            }
        );

    });


videoLightboxClose.addEventListener(
    'click',
    closeVideo
);


videoLightbox.addEventListener(
    'click',
    event => {

        if (
            event.target === videoLightbox
        ) {

            closeVideo();

        }

    }
);


/*
 * Keyboard controls
 */

document.addEventListener(
    'keydown',
    event => {

        if (
            videoLightbox.classList.contains(
                'open'
            )
        ) {

            if (
                event.key === 'Escape'
            ) {

                closeVideo();

            }

            return;
        }


        if (
            !lightbox.classList.contains(
                'open'
            )
        ) {

            return;

        }


        if (
            event.key === 'Escape'
        ) {

            closeLightbox();

        }


        if (
            event.key === 'ArrowRight'
        ) {

            nextPhoto();

        }


        if (
            event.key === 'ArrowLeft'
        ) {

            previousPhoto();

        }

    }
);

</script>

</body>
</html>

<?php

    exit;
}


/*
 * Everything else currently goes to the admin.
 */

if (
    $path === '' ||
    $path === 'index.php'
) {

    redirect('/admin/events/');

}


http_response_code(404);

echo '<h1>404</h1>';
echo '<p>Page not found.</p>';
?>
