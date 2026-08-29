<?php

declare(strict_types=1);

namespace AnyTour\Platform;

use PDO;
use RuntimeException;

final class ContentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function blocks(string $ownerType, int $ownerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT block_type, block_key, sort_order, source, content_json
             FROM at_content_blocks
             WHERE owner_type = :owner_type AND owner_id = :owner_id AND enabled = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);

        $blocks = [];
        foreach ($stmt->fetchAll() as $row) {
            $content = [];
            if ($row['content_json'] !== null && $row['content_json'] !== '') {
                $decoded = json_decode((string) $row['content_json'], true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Invalid content_json for block ' . (string) $row['block_key']);
                }
                $content = $decoded;
            }

            $blocks[] = [
                'type' => (string) $row['block_type'],
                'key' => (string) $row['block_key'],
                'sort_order' => (int) $row['sort_order'],
                'source' => (string) $row['source'],
                'content' => $content,
            ];
        }

        return $blocks;
    }

    /** @return array<string,mixed> */
    public function overrides(string $ownerType, int $ownerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT field_key, value_text, value_json
             FROM at_page_overrides
             WHERE owner_type = :owner_type AND owner_id = :owner_id'
        );
        $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = (string) $row['field_key'];
            if ($row['value_json'] !== null && $row['value_json'] !== '') {
                $decoded = json_decode((string) $row['value_json'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Invalid override JSON for ' . $key);
                }
                $result[$key] = $decoded;
                continue;
            }
            $result[$key] = $row['value_text'];
        }

        return $result;
    }
}
