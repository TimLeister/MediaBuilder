<?php

declare(strict_types=1);

namespace Media;

use PDO;
use RuntimeException;

final class DownloadJob
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function create(
        int $userId,
        int $eventId,
        string $type,
        int $totalFiles,
        int $totalBytes,
        string $downloadName
    ): int {
        if (!in_array($type, ['photos', 'videos', 'all'], true)) {
            throw new RuntimeException('Invalid download type.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO download_jobs
                (
                    user_id,
                    event_id,
                    download_type,
                    total_files,
                    total_bytes,
                    download_name
                )
             VALUES
                (
                    :user_id,
                    :event_id,
                    :download_type,
                    :total_files,
                    :total_bytes,
                    :download_name
                )'
        );

        $stmt->execute([
            'user_id' => $userId,
            'event_id' => $eventId,
            'download_type' => $type,
            'total_files' => $totalFiles,
            'total_bytes' => $totalBytes,
            'download_name' => $downloadName,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM download_jobs
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute(['id' => $id]);

        $job = $stmt->fetch();

        return $job ?: null;
    }

    public function findActive(
        int $userId,
        int $eventId,
        string $type
    ): ?array {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM download_jobs
             WHERE user_id = :user_id
               AND event_id = :event_id
               AND download_type = :download_type
               AND status IN ("queued", "processing", "complete")
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id DESC
             LIMIT 1'
        );

        $stmt->execute([
            'user_id' => $userId,
            'event_id' => $eventId,
            'download_type' => $type,
        ]);

        $job = $stmt->fetch();

        return $job ?: null;
    }

    public function forEventUser(
        int $userId,
        int $eventId,
        int $limit = 10
    ): array {
        $limit = max(1, min($limit, 25));

        $stmt = $this->db->prepare(
            'SELECT *
             FROM download_jobs
             WHERE user_id = :user_id
               AND event_id = :event_id
               AND status <> "expired"
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id DESC
             LIMIT ' . $limit
        );

        $stmt->execute([
            'user_id' => $userId,
            'event_id' => $eventId,
        ]);

        return $stmt->fetchAll();
    }

    public function claimNext(?int $jobId = null): ?array
    {
        $this->db->beginTransaction();

        try {
            if ($jobId !== null) {
                $stmt = $this->db->prepare(
                    'SELECT *
                     FROM download_jobs
                     WHERE id = :id
                       AND status = "queued"
                     LIMIT 1
                     FOR UPDATE'
                );

                $stmt->execute(['id' => $jobId]);
            } else {
                $stmt = $this->db->query(
                    'SELECT *
                     FROM download_jobs
                     WHERE status = "queued"
                     ORDER BY id ASC
                     LIMIT 1
                     FOR UPDATE'
                );
            }

            $job = $stmt->fetch();

            if (!$job) {
                $this->db->commit();
                return null;
            }

            $update = $this->db->prepare(
                'UPDATE download_jobs
                 SET
                    status = "processing",
                    attempts = attempts + 1,
                    started_at = COALESCE(started_at, NOW()),
                    last_heartbeat_at = NOW(),
                    error_message = NULL
                 WHERE id = :id'
            );

            $update->execute([
                'id' => $job['id'],
            ]);

            $this->db->commit();

            $job['status'] = 'processing';
            $job['attempts'] = (int) $job['attempts'] + 1;

            return $job;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    public function heartbeat(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE download_jobs
             SET last_heartbeat_at = NOW()
             WHERE id = :id
               AND status = "processing"'
        );

        $stmt->execute(['id' => $id]);
    }

    public function updateProgress(
        int $id,
        int $processedFiles,
        int $processedBytes,
        int $failedFiles = 0
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE download_jobs
             SET
                processed_files = :processed_files,
                processed_bytes = :processed_bytes,
                failed_files = :failed_files,
                last_heartbeat_at = NOW()
             WHERE id = :id
               AND status = "processing"'
        );

        $stmt->execute([
            'id' => $id,
            'processed_files' => $processedFiles,
            'processed_bytes' => $processedBytes,
            'failed_files' => $failedFiles,
        ]);
    }

    public function complete(
        int $id,
        string $archivePath,
        int $failedFiles = 0,
        ?string $failureDetails = null,
        int $ttlHours = 24
    ): void {
        $ttlHours = max(1, min($ttlHours, 168));

        $stmt = $this->db->prepare(
            'UPDATE download_jobs
             SET
                status = "complete",
                failed_files = :failed_files,
                failure_details = :failure_details,
                archive_path = :archive_path,
                completed_at = NOW(),
                last_heartbeat_at = NOW(),
                expires_at = DATE_ADD(
                    NOW(),
                    INTERVAL ' . $ttlHours . ' HOUR
                )
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'archive_path' => $archivePath,
            'failed_files' => $failedFiles,
            'failure_details' => $failureDetails,
        ]);

        $this->db->prepare(
            'UPDATE download_jobs
             SET processed_files = total_files - failed_files
             WHERE id = :id'
        )->execute(['id' => $id]);
    }

    public function fail(int $id, string $message): void
    {
        $stmt = $this->db->prepare(
            'UPDATE download_jobs
             SET
                status = "failed",
                error_message = :error_message,
                last_heartbeat_at = NOW()
             WHERE id = :id
               AND status = "processing"'
        );

        $stmt->execute([
            'id' => $id,
            'error_message' => mb_substr($message, 0, 2000),
        ]);
    }

    public function requeueStale(int $minutes = 30): int
    {
        $minutes = max(5, min($minutes, 1440));

        $sql = 'UPDATE download_jobs
                SET
                    status = "queued",
                    error_message = "Worker heartbeat expired; job requeued.",
                    last_heartbeat_at = NULL
                WHERE status = "processing"
                  AND last_heartbeat_at IS NOT NULL
                  AND last_heartbeat_at < DATE_SUB(NOW(), INTERVAL '
            . $minutes
            . ' MINUTE)';

        return $this->db->exec($sql);
    }

    public function expire(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE download_jobs
             SET status = "expired"
             WHERE id = :id
               AND status = "complete"'
        );

        $stmt->execute(['id' => $id]);
    }

    public function expired(): array
    {
        $stmt = $this->db->query(
            'SELECT *
             FROM download_jobs
             WHERE status = "complete"
               AND expires_at IS NOT NULL
               AND expires_at <= NOW()
             ORDER BY id ASC'
        );

        return $stmt->fetchAll();
    }
}
