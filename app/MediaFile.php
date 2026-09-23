<?php

declare(strict_types=1);

namespace Media;

use PDO;

final class MediaFile
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function create(
        int $eventId,
        string $uuid,
        string $originalFilename,
        string $mediaType,
        string $mimeType,
        int $fileSize,
        string $storageKey,
        string $cdnUrl
    ): int {
        $stmt = $this->db->prepare(
            '
            INSERT INTO media_files
                (
                    event_id,
                    uuid,
                    original_filename,
                    media_type,
                    mime_type,
                    file_size,
                    storage_key,
                    cdn_url,
                    processing_status
                )
            VALUES
                (
                    :event_id,
                    :uuid,
                    :original_filename,
                    :media_type,
                    :mime_type,
                    :file_size,
                    :storage_key,
                    :cdn_url,
                    :processing_status
                )
            '
        );

        $stmt->execute([
            'event_id' => $eventId,
            'uuid' => $uuid,
            'original_filename' => $originalFilename,
            'media_type' => $mediaType,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'storage_key' => $storageKey,
            'cdn_url' => $cdnUrl,
            'processing_status' => 'pending',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            '
            SELECT *
            FROM media_files
            WHERE id = :id
            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $media = $stmt->fetch();

        return $media ?: null;
    }

    public function forEvent(int $eventId): array
    {
        $stmt = $this->db->prepare(
            '
            SELECT *
            FROM media_files
            WHERE event_id = :event_id
            ORDER BY sort_order ASC, id ASC
            '
        );

        $stmt->execute([
            'event_id' => $eventId,
        ]);

        return $stmt->fetchAll();
    }

    public function markProcessing(int $id): void
{
    $stmt = $this->db->prepare(
        '
        UPDATE media_files
        SET processing_status = "processing"
        WHERE id = :id
        '
    );

    $stmt->execute([
        'id' => $id,
    ]);
}

public function markComplete(
    int $id,
    ?int $width,
    ?int $height,
    string $thumbnailKey,
    string $thumbnailUrl,
    string $webKey,
    string $webUrl
): void {
    $stmt = $this->db->prepare(
        '
        UPDATE media_files
        SET
            width = :width,
            height = :height,
            thumbnail_key = :thumbnail_key,
            thumbnail_url = :thumbnail_url,
            web_key = :web_key,
            web_url = :web_url,
            processing_status = "complete"
        WHERE id = :id
        '
    );

    $stmt->execute([
        'id' => $id,
        'width' => $width,
        'height' => $height,
        'thumbnail_key' => $thumbnailKey,
        'thumbnail_url' => $thumbnailUrl,
        'web_key' => $webKey,
        'web_url' => $webUrl,
    ]);
}

public function markVideoComplete(
    int $id,
    ?int $width,
    ?int $height,
    ?float $durationSeconds,
    string $thumbnailKey,
    string $thumbnailUrl,
    string $videoKey,
    string $videoUrl
): void {
    $stmt = $this->db->prepare(
        '
        UPDATE media_files
        SET
            width = :width,
            height = :height,
            duration_seconds = :duration_seconds,
            thumbnail_key = :thumbnail_key,
            thumbnail_url = :thumbnail_url,
            video_key = :video_key,
            video_url = :video_url,
            processing_status = "complete"
        WHERE id = :id
        '
    );

    $stmt->execute([
        'id' => $id,
        'width' => $width,
        'height' => $height,
        'duration_seconds' => $durationSeconds,
        'thumbnail_key' => $thumbnailKey,
        'thumbnail_url' => $thumbnailUrl,
        'video_key' => $videoKey,
        'video_url' => $videoUrl,
    ]);
}

public function markFailed(int $id): void
{
    $stmt = $this->db->prepare(
        '
        UPDATE media_files
        SET processing_status = "failed"
        WHERE id = :id
        '
    );

    $stmt->execute([
        'id' => $id,
    ]);
}
public function delete(int $id): void
{
    $stmt = $this->db->prepare(
        '
        DELETE FROM media_files
        WHERE id = :id
        LIMIT 1
        '
    );

    $stmt->execute([
        'id' => $id,
    ]);
}
}
