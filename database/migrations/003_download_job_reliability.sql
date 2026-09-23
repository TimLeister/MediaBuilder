ALTER TABLE download_jobs
    ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER error_message,
    ADD COLUMN failed_files INT UNSIGNED NOT NULL DEFAULT 0 AFTER processed_bytes,
    ADD COLUMN failure_details LONGTEXT NULL AFTER failed_files,
    ADD COLUMN last_heartbeat_at TIMESTAMP NULL DEFAULT NULL AFTER started_at,
    ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER last_heartbeat_at;

