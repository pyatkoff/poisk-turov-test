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
     * Content-ready is deliberately narrow: active named saved hotel + successful
     * detail row + nonblank saved description + at least one image accepted by the
     * same presentation sanitizer used by the canonical seed. No identity is inferred.
     */
    public function planNext(mixed $requestedLimit): array
    {
        $limit = self::limit($requestedLimit);
        $catalog = new AnyTourCanonicalCatalog($this->pdo);
        $catalog->assertSchema();
        $this->beginReadOnly();
        try {
            $stmt = $this->pdo->query("SELECT h.id
                FROM catalog_hotels h
                JOIN catalog_hotel_details d ON d.hotel_id=h.id
                WHERE h.is_active=1 AND TRIM(h.name)<>''
                  AND d.status='success'
                  AND d.description IS NOT NULL AND TRIM(d.description)<>''
                ORDER BY h.id ASC");
            if ($stmt === false) throw new RuntimeException('Could not select saved detail candidates');
            $candidateIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $bridges = [];
            foreach (array_chunk($candidateIds, AnyTourCanonicalCatalog::BATCH_LIMIT) as $chunk) {
                foreach ($catalog->legacyTargets($chunk) as $legacyId => $ownId) $bridges[(int)$legacyId] = $ownId;
            }

            $contentReadyTotal = $contentReadyBridged = $contentReadyMissing = 0;
            $selected = [];
            foreach (array_chunk($candidateIds, HOTEL_PRESENTATION_READ_LIMIT) as $chunk) {
                $read = hotel_presentation_read_many($this->pdo, $chunk);
                foreach ($read['items'] as $profile) {
                    // The shared reader has already removed unsafe/invalid media here.
                    if ($profile['description'] === null || $profile['images'] === []) continue;
                    $legacyId = (int)$profile['id'];
                    $contentReadyTotal++;
                    if (isset($bridges[$legacyId])) {
                        $contentReadyBridged++;
                        continue;
                    }
                    $contentReadyMissing++;
                    if (count($selected) < $limit) $selected[] = $legacyId;
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        $report = [
            'status' => 'backfill_plan_read_only',
            'definition' => 'active_named_success_description_safe_images',
            'limit' => $limit,
            'saved_detail_candidates' => count($candidateIds),
            'content_ready_total' => $contentReadyTotal,
            'content_ready_bridged' => $contentReadyBridged,
            'content_ready_missing' => $contentReadyMissing,
            'selectedIds' => $selected,
            'selected' => count($selected),
            'remaining_after_selected' => max(0, $contentReadyMissing - count($selected)),
            'writes' => 0,
            'supplier_calls' => 0,
            'seed_authorized' => false,
        ];
        if ($selected === []) {
            $report['canonical_plan'] = null;
            $report['ready_for_seed_review'] = false;
            return $report;
        }

        // Reuse the existing canonical DTO/digest after the selection snapshot. A
        // concurrent bridge/source change is visible and cannot become authorization.
        $canonicalPlan = $catalog->plan($selected);
        $report['canonical_plan'] = [
            'source_sha256' => $canonicalPlan['source_sha256'],
            'source_profiles' => $canonicalPlan['source_profiles'],
            'missingIds' => $canonicalPlan['missingIds'],
            'existing_bridges' => $canonicalPlan['existing_bridges'],
        ];
        $report['ready_for_seed_review'] = $canonicalPlan['source_profiles'] === count($selected)
            && $canonicalPlan['missingIds'] === [] && $canonicalPlan['existing_bridges'] === 0;
        return $report;
    }
}
