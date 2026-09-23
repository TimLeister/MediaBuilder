<?php

declare(strict_types=1);

namespace Media;

use PDO;

final class Event
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function create(
        string $name,
        string $slug,
        ?string $eventDate,
        ?string $location,
        ?string $description
    ): int {

        /*
         * Create a human-readable, stable storage folder.
         *
         * Example:
         *
         * events/lock-haven-vs-bucknell-football-2026-10-03/
         */
        $datePart = $eventDate
            ? $eventDate
            : date('Y-m-d');

        $storagePrefix =
            'events/'
            . trim($slug, '/')
            . '-'
            . $datePart
            . '/';

        $stmt = $this->db->prepare(
            '
            INSERT INTO events
                (
                    name,
                    slug,
                    storage_prefix,
                    event_date,
                    location,
                    description
                )
            VALUES
                (
                    :name,
                    :slug,
                    :storage_prefix,
                    :event_date,
                    :location,
                    :description
                )
            '
        );

        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'storage_prefix' => $storagePrefix,
            'event_date' => $eventDate ?: null,
            'location' => $location ?: null,
            'description' => $description ?: null,
        ]);

        $eventId = (int) $this->db->lastInsertId();

        /*
         * Spaces does not have real folders. Create a zero-byte
         * placeholder so the event prefix appears immediately.
         */
        $spaces = new Spaces();
        $spaces->put(
            $storagePrefix . '.keep',
            '',
            'application/octet-stream'
        );

        return $eventId;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM events WHERE id = :id LIMIT 1'
        );

        $stmt->execute([
            'id' => $id,
        ]);

        $event = $stmt->fetch();

        return $event ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM events WHERE slug = :slug LIMIT 1'
        );

        $stmt->execute([
            'slug' => $slug,
        ]);

        $event = $stmt->fetch();

        return $event ?: null;
    }

    public function all(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM events
             ORDER BY event_date DESC, id DESC'
        );

        return $stmt->fetchAll();
    }
    public function publish(int $id): void
{
    $stmt = $this->db->prepare(
        'UPDATE events
         SET status = "published"
         WHERE id = :id'
    );

    $stmt->execute([
        'id' => $id,
    ]);
}

public function unpublish(int $id): void
{
    $stmt = $this->db->prepare(
        'UPDATE events
         SET status = "draft"
         WHERE id = :id'
    );

    $stmt->execute([
        'id' => $id,
    ]);
}
}