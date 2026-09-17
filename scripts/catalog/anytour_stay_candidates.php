<?php
/** Read-only evidence inventory for future AnyTour room/meal mapping review. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../v2/data/anytour-stay-catalog-v1.php';

final class AnyTourStayCandidates
{
    public const LIMIT = 5000;

    public function __construct(private PDO $pdo) {}

    private static function limit(int $value): int
    {
        if ($value < 1 || $value > self::LIMIT) throw new InvalidArgumentException('Limit must be 1..5000');
        return $value;
    }

    private function begin(): void
    {
        if ($this->pdo->inTransaction() || $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || $this->pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new RuntimeException('Dedicated exception-mode MySQL connection required');
        }
        $tables = $this->pdo->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
            AND TABLE_NAME IN ('anytour_hotels','anytour_hotel_sources','anytour_meal_plans','anytour_stay_mappings',
            'hot_tours_current','tour_price_observations') ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($tables) !== 6 || count(array_filter($tables, static fn($engine) => $engine === 'InnoDB')) !== 6) {
            throw new RuntimeException('Expected installed AnyTour stay catalogue and saved offer evidence tables');
        }
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
    }

    private static function row(array $r, string $kind, string $keyKind, string $externalKey): array
    {
        return [
            'scope' => [
                'namespace' => 'legacy_catalog',
                'hotelKey' => (string)$r['hotel_key'],
                'operatorKey' => (string)$r['operator_key'],
            ],
            'hotelId' => (int)$r['anytour_hotel_id'],
            'sourceSha256' => (string)$r['source_sha256'],
            'reference' => ['kind' => $kind, 'keyKind' => $keyKind, 'externalKey' => $externalKey],
            'observedCount' => (int)$r['observed_count'],
            'lastSeenAt' => (string)$r['last_seen_at'],
            'sampleLabel' => $r['sample_label'] === null ? null : (string)$r['sample_label'],
            'distinctLabels' => (int)$r['distinct_labels'],
            'labelConflict' => (int)$r['distinct_labels'] > 1,
            'decisionState' => $r['decision_state'] === null ? 'unmapped' : (string)$r['decision_state'],
        ];
    }

    private function mealCandidates(int $limit): array
    {
        $sql = "SELECT s.external_key AS hotel_key,s.anytour_hotel_id,s.source_sha256,
                CAST(h.operator_id AS CHAR) AS operator_key,CAST(h.meal_id AS CHAR) AS supplier_key,
                MIN(NULLIF(TRIM(h.meal_name),'')) AS sample_label,
                COUNT(DISTINCT COALESCE(NULLIF(TRIM(h.meal_name),''),'__EMPTY__')) AS distinct_labels,
                COUNT(*) AS observed_count,MAX(h.fetched_at) AS last_seen_at,MAX(m.state) AS decision_state
            FROM anytour_hotel_sources s
            JOIN anytour_hotels a ON a.id=s.anytour_hotel_id AND a.is_active=1
            JOIN hot_tours_current h ON h.hotel_id=CAST(s.external_key AS UNSIGNED)
                AND s.external_key=CAST(h.hotel_id AS BINARY)
            LEFT JOIN anytour_stay_mappings m ON m.namespace='legacy_catalog' AND m.external_hotel_key=s.external_key
                AND m.operator_key=CAST(h.operator_id AS BINARY) AND m.kind='meal' AND m.key_kind='code'
                AND m.external_key=CAST(h.meal_id AS BINARY)
            WHERE s.namespace='legacy_catalog'
                AND h.operator_id IS NOT NULL AND h.operator_id>0 AND h.meal_id IS NOT NULL AND h.meal_id>0
            GROUP BY s.external_key,s.anytour_hotel_id,s.source_sha256,h.operator_id,h.meal_id
            ORDER BY observed_count DESC,last_seen_at DESC,s.anytour_hotel_id ASC,h.operator_id ASC,h.meal_id ASC
            LIMIT " . $limit;
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $r): array => self::row($r, 'meal', 'code', (string)$r['supplier_key']), $rows);
    }

    private function roomCandidates(int $limit): array
    {
        $keyKind = "CASE WHEN o.room_id IS NOT NULL AND o.room_id>0 THEN 'code' ELSE 'label' END";
        // Both CASE branches are binary so production utf8mb4 label collations cannot be
        // coerced against exact VARBINARY mapping keys. Labels remain untouched evidence.
        $supplierKey = "CASE WHEN o.room_id IS NOT NULL AND o.room_id>0 THEN CAST(o.room_id AS BINARY) ELSE CAST(o.room_type AS BINARY) END";
        $sql = "SELECT s.external_key AS hotel_key,s.anytour_hotel_id,s.source_sha256,
                CAST(o.operator_id AS CHAR) AS operator_key,
                {$keyKind} AS supplier_key_kind,
                {$supplierKey} AS supplier_key,
                MIN(NULLIF(TRIM(o.room_type),'')) AS sample_label,
                COUNT(DISTINCT COALESCE(NULLIF(TRIM(o.room_type),''),'__EMPTY__')) AS distinct_labels,
                COUNT(*) AS observed_count,MAX(o.observed_at) AS last_seen_at,MAX(m.state) AS decision_state
            FROM anytour_hotel_sources s
            JOIN anytour_hotels a ON a.id=s.anytour_hotel_id AND a.is_active=1
            JOIN tour_price_observations o ON o.hotel_id=CAST(s.external_key AS UNSIGNED)
                AND s.external_key=CAST(o.hotel_id AS BINARY)
            LEFT JOIN anytour_stay_mappings m ON m.namespace='legacy_catalog' AND m.external_hotel_key=s.external_key
                AND m.operator_key=CAST(o.operator_id AS BINARY) AND m.kind='room'
                AND ((o.room_id IS NOT NULL AND o.room_id>0 AND m.key_kind='code' AND m.external_key=CAST(o.room_id AS BINARY))
                  OR ((o.room_id IS NULL OR o.room_id=0) AND m.key_kind='label' AND m.external_key=CAST(o.room_type AS BINARY)))
            WHERE s.namespace='legacy_catalog'
                AND o.operator_id IS NOT NULL AND o.operator_id>0
                AND ((o.room_id IS NOT NULL AND o.room_id>0) OR NULLIF(TRIM(o.room_type),'') IS NOT NULL)
            GROUP BY s.external_key,s.anytour_hotel_id,s.source_sha256,o.operator_id,{$keyKind},{$supplierKey}
            ORDER BY observed_count DESC,last_seen_at DESC,s.anytour_hotel_id ASC,o.operator_id ASC,supplier_key ASC
            LIMIT " . $limit;
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $r): array => self::row($r, 'room', (string)$r['supplier_key_kind'], (string)$r['supplier_key']), $rows);
    }

    public function collect(int $limit = 1000): array
    {
        $limit = self::limit($limit);
        $this->begin();
        try {
            $meals = $this->mealCandidates($limit);
            $rooms = $this->roomCandidates($limit);
            $localMeals = (new AnyTourStayCatalog($this->pdo))->meals();
            $this->pdo->commit();
            $unmapped = static fn(array $rows): int => count(array_filter($rows, static fn(array $row): bool => $row['decisionState'] === 'unmapped'));
            return [
                'status' => 'read_only_evidence_inventory',
                'source' => 'saved_db_only',
                'namespace' => 'legacy_catalog',
                'mealCandidates' => $meals,
                'roomCandidates' => $rooms,
                'localMealPlans' => $localMeals,
                'counts' => [
                    'mealCandidates' => count($meals),
                    'unmappedMeals' => $unmapped($meals),
                    'roomCandidates' => count($rooms),
                    'unmappedRooms' => $unmapped($rooms),
                ],
                'writes' => 0,
                'supplierCalls' => 0,
                'automaticAccepts' => 0,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $dsn = getenv('ANYTOUR_STAY_CANDIDATES_DSN') ?: '';
    $user = getenv('ANYTOUR_STAY_CANDIDATES_USER') ?: '';
    $password = getenv('ANYTOUR_STAY_CANDIDATES_PASSWORD') ?: '';
    if ($dsn === '') throw new RuntimeException('ANYTOUR_STAY_CANDIDATES_DSN is required');
    $limit = 1000;
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--limit=([0-9]+)$/D', $arg, $match)) $limit = (int)$match[1];
        else throw new InvalidArgumentException('Usage: anytour_stay_candidates.php [--limit=1..5000]');
    }
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    echo json_encode((new AnyTourStayCandidates($pdo))->collect($limit), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
}
