CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    event_date DATE NULL,
    location VARCHAR(255) NULL,
    description TEXT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_events_slug (slug),
    KEY idx_events_status (status),
    KEY idx_events_date (event_date)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE media_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,

    uuid CHAR(36) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,

    media_type ENUM('photo', 'video') NOT NULL,
    mime_type VARCHAR(100) NOT NULL,

    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,

    storage_key VARCHAR(500) NOT NULL,
    cdn_url VARCHAR(1000) NOT NULL,

    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    duration_seconds DECIMAL(10,2) NULL,

    thumbnail_key VARCHAR(500) NULL,
    thumbnail_url VARCHAR(1000) NULL,

    processing_status ENUM('pending', 'processing', 'complete', 'failed')
        NOT NULL DEFAULT 'pending',

    sort_order INT NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_media_uuid (uuid),

    KEY idx_media_event (event_id),
    KEY idx_media_type (media_type),
    KEY idx_media_processing (processing_status),

    CONSTRAINT fk_media_event
        FOREIGN KEY (event_id)
        REFERENCES events(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE share_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NOT NULL,

    token CHAR(32) NOT NULL,

    is_active TINYINT(1) NOT NULL DEFAULT 1,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_share_token (token),
    KEY idx_share_event (event_id),
    KEY idx_share_active (is_active),

    CONSTRAINT fk_share_event
        FOREIGN KEY (event_id)
        REFERENCES events(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


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
