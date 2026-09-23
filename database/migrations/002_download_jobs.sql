CREATE TABLE download_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    event_id BIGINT UNSIGNED NOT NULL,

    download_type ENUM('photos', 'videos', 'all') NOT NULL,
    status ENUM('queued', 'processing', 'complete', 'failed', 'expired')
        NOT NULL DEFAULT 'queued',

    total_files INT UNSIGNED NOT NULL DEFAULT 0,
    processed_files INT UNSIGNED NOT NULL DEFAULT 0,
    total_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    processed_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,

    archive_path VARCHAR(1000) NULL,
    download_name VARCHAR(255) NULL,
    error_message TEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_download_jobs_status (status),
    KEY idx_download_jobs_event (event_id),
    KEY idx_download_jobs_user (user_id),
    KEY idx_download_jobs_expires (expires_at),

    CONSTRAINT fk_download_jobs_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_download_jobs_event
        FOREIGN KEY (event_id)
        REFERENCES events(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
