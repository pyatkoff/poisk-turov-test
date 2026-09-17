<?php
/** Read-only selection of the next saved, presentation-ready legacy cohort for AnyTour ownership. */
declare(strict_types=1);
require_once __DIR__ . '/anytour-canonical-catalog-v1.php';

final class AnyTourCatalogBackfillV1
{
    public function __construct(private PDO $pdo) {}

    public static function limit(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)) {
            throw new InvalidArgumentException('Backfill limit must be an integer from 1 to 1000');
        }
        $limit = (int)$value;
        if ($limit < 1 || $limit > AnyTourCanonicalCatalog::BATCH_LIMIT) {
            throw new InvalidArgumentException('Backfill limit must be an integer from 1 to 1000');
        }
        return $limit;
    }

    private function beginReadOnly(): void
    {
        if ($this->pdo->inTransaction()) throw new RuntimeException('Caller transaction must not be nested');
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION READ ONLY');
        $this->pdo->beginTransaction();
    }

    /**
     * Content-ready here is deliberately narrow: active named saved hotel + successful
     * detail row + nonblank saved description + a nonempty JSON image array. It does
     * not infer hotel identity, provider links, room/meal mappings or canonical prose.
     */
    public function planNext(mixed $requestedLimit): array
    {
        $limit = self::limit($requestedLimit);
        $catalog = new AnyTourCanonicalCatalog($this->pdo);
        $catalog->assertSchema();
        $this->beginReadOnly();
        try {
            $eligibility = "h.is_active=1
                AND TRIM(h.name)<>''
                AND d.status='success'
                AND d.description IS NOT NULL AND TRIM(d.description)<>''
                AND d.images_json IS NOT NULL AND JSON_VALID(d.images_json)=1
                AND JSON_TYPE(d.images_json)='ARRAY' AND JSON_LENGTH(d.images_json)>0";
            $countsSql = "SELECT COUNT(*) AS content_ready_total,
                    SUM(CASE WHEN s.id IS NULL THEN 1 ELSE 0 END) AS content_ready_missing,
                    SUM(CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END) AS content_ready_bridged
                FROM catalog_hotels h
                JOIN catalog_hotel_details d ON d.hotel_id=h.id
                LEFT JOIN anytour_hotel_sources s
                  ON s.namespace='legacy_catalog' AND s.external_key=CAST(h.id AS CHAR)
                WHERE $eligibility";
            $counts = $this->pdo->query($countsSql)->fetch(PDO::FETCH_ASSOC);
            if (!is_array($counts)) throw new RuntimeException('Could not count content-ready hotel coverage');

            $selectSql = "SELECT h.id
                FROM catalog_hotels h
                JOIN catalog_hotel_details d ON d.hotel_id=h.id
                LEFT JOIN anytour_hotel_sources s
                  ON s.namespace='legacy_catalog' AND s.external_key=CAST(h.id AS CHAR)
                WHERE $eligibility AND s.id IS NULL
                ORDER BY h.id ASC
                LIMIT $limit";
            $stmt = $this->pdo->query($selectSql);
            if ($stmt === false) throw new RuntimeException('Could not select next content-ready hotel cohort');
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        $missing = (int)$counts['content_ready_missing'];
        $report = [
            'status' => 'backfill_plan_read_only',
            'definition' => 'active_named_success_description_nonempty_images_array',
            'limit' => $limit,
            'content_ready_total' => (int)$counts['content_ready_total'],
            'content_ready_bridged' => (int)$counts['content_ready_bridged'],
            'content_ready_missing' => $missing,
            'selectedIds' => $ids,
            'selected' => count($ids),
            'remaining_after_selected' => max(0, $missing - count($ids)),
            'writes' => 0,
            'supplier_calls' => 0,
            'seed_authorized' => false,
        ];
        if ($ids === []) {
            $report['canonical_plan'] = null;
            $report['ready_for_seed_review'] = false;
            return $report;
        }

        // Reuse the existing canonical DTO/digest. A concurrent bridge or source drift
        // is visible here and therefore cannot silently become seed authorization.
        $canonicalPlan = $catalog->plan($ids);
        $report['canonical_plan'] = [
            'source_sha256' => $canonicalPlan['source_sha256'],
            'source_profiles' => $canonicalPlan['source_profiles'],
            'missingIds' => $canonicalPlan['missingIds'],
            'existing_bridges' => $canonicalPlan['existing_bridges'],
        ];
        $report['ready_for_seed_review'] = $canonicalPlan['source_profiles'] === count($ids)
            && $canonicalPlan['missingIds'] === [] && $canonicalPlan['existing_bridges'] === 0;
        return $report;
    }
}
