<?php

declare(strict_types=1);

namespace Media;

use PDO;

final class ShareLink
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function create(int $eventId): string
    {
        do {
            $token = bin2hex(random_bytes(16));

            $stmt = $this->db->prepare(
                'SELECT id FROM share_links WHERE token = :token LIMIT 1'
            );

            $stmt->execute([
                'token' => $token,
            ]);

        } while ($stmt->fetch());

        $stmt = $this->db->prepare(
            '
            INSERT INTO share_links
                (event_id, token)
            VALUES
                (:event_id, :token)
            '
        );

        $stmt->execute([
            'event_id' => $eventId,
            'token' => $token,
        ]);

        return $token;
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            '
            SELECT
                share_links.*,
                events.name,
                events.slug,
                events.event_date,
                events.location,
                events.description,
                events.status
            FROM share_links
            INNER JOIN events
                ON events.id = share_links.event_id
            WHERE share_links.token = :token
              AND share_links.is_active = 1
            LIMIT 1
            '
        );

        $stmt->execute([
            'token' => $token,
        ]);

        $link = $stmt->fetch();

        return $link ?: null;
    }

    public function findForEvent(int $eventId): ?array
    {
        $stmt = $this->db->prepare(
            '
            SELECT *
            FROM share_links
            WHERE event_id = :event_id
              AND is_active = 1
            ORDER BY id ASC
            LIMIT 1
            '
        );

        $stmt->execute([
            'event_id' => $eventId,
        ]);

        $link = $stmt->fetch();

        return $link ?: null;
    }
}
