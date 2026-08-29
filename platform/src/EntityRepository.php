<?php

declare(strict_types=1);

namespace AnyTour\Platform;

use PDO;
use RuntimeException;

final class EntityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByKey(string $entityKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, entity_key, entity_type, slug, name, parent_id, status, sort_order, data_json
             FROM at_entities
             WHERE entity_key = :entity_key
             LIMIT 1'
        );
        $stmt->execute(['entity_key' => $entityKey]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /** @return array<string,mixed>|null */
    public function findById(int $entityId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, entity_key, entity_type, slug, name, parent_id, status, sort_order, data_json
             FROM at_entities
             WHERE id = :entity_id
             LIMIT 1'
        );
        $stmt->execute(['entity_id' => $entityId]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /** @return list<array<string,mixed>> */
    public function childrenOf(int $parentId, ?string $entityType = null): array
    {
        $sql = 'SELECT id, entity_key, entity_type, slug, name, parent_id, status, sort_order, data_json
                FROM at_entities
                WHERE parent_id = :parent_id AND status = \'active\'';
        $params = ['parent_id' => $parentId];

        if ($entityType !== null) {
            $sql .= ' AND entity_type = :entity_type';
            $params['entity_type'] = $entityType;
        }

        $sql .= ' ORDER BY sort_order ASC, name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row): array => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @return array<string,string> */
    public function externalIds(int $entityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT provider, external_type, external_id
             FROM at_entity_external_ids
             WHERE entity_id = :entity_id'
        );
        $stmt->execute(['entity_id' => $entityId]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = (string) $row['provider'];
            if ((string) ($row['external_type'] ?? '') !== '') {
                $key .= ':' . (string) $row['external_type'];
            }
            $result[$key] = (string) $row['external_id'];
        }

        return $result;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrate(array $row): array
    {
        $data = [];
        if (isset($row['data_json']) && $row['data_json'] !== null && $row['data_json'] !== '') {
            $decoded = json_decode((string) $row['data_json'], true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Invalid entity data_json for ' . (string) $row['entity_key']);
            }
            $data = $decoded;
        }

        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        $row['sort_order'] = (int) $row['sort_order'];
        $row['data'] = $data;
        unset($row['data_json']);

        return $row;
    }
}
