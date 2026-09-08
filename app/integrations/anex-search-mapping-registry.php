<?php
declare(strict_types=1);

/** Owner-approved DB identities for the isolated ANEX search preview. */
final class AnyTourAnexSearchMappingRegistry
{
    private const POLICY = 'owner_exact_and_strong_20260908';
    private const MAX_ROWS = 50000;
    private const PROVIDERS = ['anex_xml' => true, 'anex_online' => true];
    private $index;

    private function __construct(array $index)
    {
        $this->index = $index;
    }

    public static function fromPdo(PDO $pdo): self
    {
        $mappings = $pdo->prepare(
            'SELECT m.anex_hotel_id,m.catalog_hotel_id,m.match_class,m.approval_policy,m.enabled,m.scope,'
            . ' h.id AS existing_catalog_hotel_id FROM anex_hotel_search_mappings m'
            . ' INNER JOIN catalog_hotels h ON h.id=m.catalog_hotel_id'
            . " WHERE m.enabled=1 AND m.approval_policy=? AND m.scope='preview'"
            . " AND m.match_class IN ('exact','strong_candidate') LIMIT 50001"
        );
        $mappings->execute([self::POLICY]);
        $mappingRows = $mappings->fetchAll(PDO::FETCH_ASSOC);
        // Read all decisions, including decisions with a missing target, so an
        // explicit block can never disappear through an inner join or fallback.
        $decisions = $pdo->query(
            'SELECT d.anex_hotel_id,d.decision_status,d.catalog_hotel_id,'
            . ' h.id AS existing_catalog_hotel_id FROM anex_hotel_decisions d'
            . ' LEFT JOIN catalog_hotels h ON h.id=d.catalog_hotel_id LIMIT 50001'
        );
        return self::fromRows($mappingRows, $decisions->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Rows use the SQL projection above; existing_catalog_hotel_id establishes
     * that the target exists. No price or availability evidence is required.
     */
    public static function fromRows(array $mappingRows, array $decisionRows = []): self
    {
        if (count($mappingRows) > self::MAX_ROWS || count($decisionRows) > self::MAX_ROWS) {
            throw new UnexpectedValueException('anex_search_mapping_registry_invalid');
        }
        $index = [];
        $seen = [];
        foreach ($mappingRows as $row) {
            $id = self::rowId($row, $seen);
            $target = self::target($row);
            if (!in_array($row['enabled'] ?? null, [1, '1', true], true)
                || ($row['approval_policy'] ?? null) !== self::POLICY
                || ($row['scope'] ?? null) !== 'preview'
                || !in_array($row['match_class'] ?? null, ['exact', 'strong_candidate'], true)
                || $target === null) {
                continue;
            }
            $index[$id] = $target;
        }
        $seen = [];
        foreach ($decisionRows as $row) {
            $id = self::rowId($row, $seen);
            // Every manual decision supersedes the policy mapping. Only an
            // explicit acceptance with an existing target can enable identity.
            unset($index[$id]);
            $target = self::target($row);
            if (($row['decision_status'] ?? null) === 'accepted' && $target !== null) {
                $index[$id] = $target;
            }
        }
        return new self($index);
    }

    public function resolve(string $provider, $externalId, string $scope = 'production'): ?int
    {
        $id = self::id($externalId, 8);
        if ($scope !== 'preview' || !isset(self::PROVIDERS[$provider]) || $id === null) {
            return null;
        }
        return $this->index[$id] ?? null;
    }

    public function previewResolver(): callable
    {
        return function (string $provider, $externalId): ?int {
            return $this->resolve($provider, $externalId, 'preview');
        };
    }

    public function count(): int
    {
        return count($this->index);
    }

    private static function id($value, int $digits): ?string
    {
        if (is_int($value)) $value = (string) $value;
        return is_string($value) && preg_match('/\A[1-9][0-9]{0,' . ($digits - 1) . '}\z/D', $value)
            ? $value : null;
    }

    private static function rowId($row, array &$seen): string
    {
        $id = is_array($row) ? self::id($row['anex_hotel_id'] ?? null, 8) : null;
        if ($id === null || isset($seen[$id])) {
            throw new UnexpectedValueException('anex_search_mapping_registry_invalid');
        }
        $seen[$id] = true;
        return $id;
    }

    private static function target(array $row): ?int
    {
        $id = self::id($row['catalog_hotel_id'] ?? null, 10);
        return $id !== null && $id === self::id($row['existing_catalog_hotel_id'] ?? null, 10)
            ? (int) $id : null;
    }
}
