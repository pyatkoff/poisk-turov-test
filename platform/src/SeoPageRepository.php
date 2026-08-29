<?php

declare(strict_types=1);

namespace AnyTour\Platform;

use PDO;

final class SeoPageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByRouteKey(string $routeKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, page_type, route_key, url_path, primary_entity_type, primary_entity_id,
                    context_json, template_code, index_status, canonical_path,
                    manual_title, manual_description, manual_h1, manual_canonical_path,
                    schema_json, quality_score, quality_json, priority_score
             FROM at_seo_pages
             WHERE route_key = :route_key
             LIMIT 1'
        );
        $stmt->execute(['route_key' => $routeKey]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        foreach (['context_json' => 'context', 'schema_json' => 'schema', 'quality_json' => 'quality'] as $source => $target) {
            $decoded = [];
            if (($row[$source] ?? null) !== null && (string) $row[$source] !== '') {
                $value = json_decode((string) $row[$source], true);
                $decoded = is_array($value) ? $value : [];
            }
            $row[$target] = $decoded;
            unset($row[$source]);
        }

        $row['id'] = (int) $row['id'];
        $row['primary_entity_id'] = $row['primary_entity_id'] === null ? null : (int) $row['primary_entity_id'];
        $row['quality_score'] = (int) $row['quality_score'];
        $row['priority_score'] = (float) $row['priority_score'];

        return $row;
    }
}
