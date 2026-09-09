<?php
declare(strict_types=1);

/** Local, persistent P2 review only: no network clients, DDL, or automatic acceptance. */
final class AnexReviewService
{
    private PDO $db;
    private const POLICY = 'owner_exact_and_strong_20260908';

    public function __construct(PDO $db)
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('review_requires_mysql');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->db = $db;
    }

    private function rows(string $sql, array $args = []): array
    {
        $q = $this->db->prepare($sql);
        $q->execute($args);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function json($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function id($value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/\A[1-9][0-9]{0,7}\z/D', (string)$value)) {
            throw new RuntimeException('invalid_hotel_id', 400);
        }
        return (int)$value;
    }

    private static function effective(?array $manual, ?array $policy): ?int
    {
        if ($manual !== null) {
            return $manual['decision_status'] === 'accepted' && $manual['existing_target'] !== null
                ? (int)$manual['catalog_hotel_id'] : null;
        }
        return $policy !== null && $policy['existing_target'] !== null && (int)$policy['enabled'] === 1
            && $policy['scope'] === 'preview' && $policy['approval_policy'] === self::POLICY
            && in_array($policy['match_class'], ['exact', 'strong_candidate'], true)
            ? (int)$policy['catalog_hotel_id'] : null;
    }

    public function queue(array $filters): array
    {
        $page = max(1, min(10000, (int)($filters['page'] ?? 1)));
        $search = trim((string)($filters['q'] ?? ''));
        if (strlen($search) > 200) throw new RuntimeException('search_too_long', 400);
        $country = max(0, (int)($filters['country'] ?? 0));
        $status = (string)($filters['status'] ?? 'pending');
        if (!in_array($status, ['all', 'pending', 'mapped', 'later', 'pair_rejected', 'no_candidates'], true)) {
            throw new RuntimeException('invalid_status', 400);
        }
        // This projection follows the existing preview registry, including manual blocks.
        $mapped = "CASE WHEN d.anex_hotel_id IS NOT NULL THEN CASE WHEN d.decision_status='accepted' THEN dh.id ELSE NULL END "
            . "ELSE mh.id END";
        $from = " FROM anex_search_hotel_observations o"
            . " LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=o.anex_hotel_id"
            . " LEFT JOIN catalog_hotels dh ON dh.id=d.catalog_hotel_id"
            . " LEFT JOIN anex_hotel_search_mappings m ON m.anex_hotel_id=o.anex_hotel_id AND m.enabled=1"
            . " AND m.scope='preview' AND m.approval_policy='" . self::POLICY . "' AND m.match_class IN ('exact','strong_candidate')"
            . " LEFT JOIN catalog_hotels mh ON mh.id=m.catalog_hotel_id"
            . " LEFT JOIN anex_review_state s ON s.anex_hotel_id=o.anex_hotel_id";
        $where = ['1=1']; $args = [];
        if ($search !== '') {
            $where[] = "(CAST(o.anex_hotel_id AS CHAR)=? OR o.hotel_name LIKE ? ESCAPE '!')";
            $args[] = $search;
            $args[] = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        if ($country > 0) { $where[] = 'o.country_id=?'; $args[] = $country; }
        if ($status === 'pending') $where[] = '(' . $mapped . ') IS NULL';
        if ($status === 'mapped') $where[] = '(' . $mapped . ') IS NOT NULL';
        if ($status === 'later') $where[] = 's.deferred_at IS NOT NULL';
        if ($status === 'pair_rejected') $where[] = 'EXISTS (SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=o.anex_hotel_id)';
        if ($status === 'no_candidates') $where[] = 'NOT EXISTS (SELECT 1 FROM anex_hotel_candidates c WHERE c.anex_hotel_id=o.anex_hotel_id)';
        $suffix = $from . ' WHERE ' . implode(' AND ', $where);
        $total = (int)$this->rows('SELECT COUNT(*) AS n' . $suffix, $args)[0]['n'];
        $items = $this->rows('SELECT o.*, (' . $mapped . ') AS mapped_id,s.deferred_at' . $suffix
            . ' ORDER BY o.search_count DESC,o.last_seen_utc DESC,o.anex_hotel_id ASC LIMIT 25 OFFSET ' . (($page - 1) * 25), $args);
        $countries = $this->rows('SELECT DISTINCT country_id FROM anex_search_hotel_observations ORDER BY country_id');
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / 25)), 'countries' => $countries];
    }

    /** Locked reads on writes prevent a stale decision overriding imports or another owner tab. */
    private function snapshot(int $id, bool $lock): array
    {
        $end = $lock ? ' FOR UPDATE' : '';
        $o = $this->rows('SELECT * FROM anex_search_hotel_observations WHERE anex_hotel_id=?' . $end, [$id])[0] ?? null;
        if ($o === null) throw new RuntimeException('hotel_not_observed', 404);
        $source = $this->rows('SELECT * FROM anex_hotels WHERE anex_hotel_id=?' . $end, [$id])[0] ?? null;
        $evidence = $this->rows('SELECT * FROM anex_hotel_auto_matches WHERE anex_hotel_id=?' . $end, [$id])[0] ?? null;
        $candidates = $this->rows('SELECT * FROM anex_hotel_candidates WHERE anex_hotel_id=? ORDER BY candidate_rank LIMIT 101' . $end, [$id]);
        if (count($candidates) > 100) throw new RuntimeException('candidate_display_bound');
        foreach ($candidates as &$candidate) {
            $candidate['current'] = $this->rows('SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude'
                . ' FROM catalog_hotels WHERE id=?' . $end, [$candidate['catalog_hotel_id']])[0] ?? null;
        }
        unset($candidate);
        $manual = $this->rows('SELECT d.*,h.id AS existing_target FROM anex_hotel_decisions d LEFT JOIN catalog_hotels h ON h.id=d.catalog_hotel_id WHERE d.anex_hotel_id=?' . $end, [$id])[0] ?? null;
        $policy = $this->rows('SELECT m.*,h.id AS existing_target FROM anex_hotel_search_mappings m LEFT JOIN catalog_hotels h ON h.id=m.catalog_hotel_id WHERE m.anex_hotel_id=?' . $end, [$id])[0] ?? null;
        $state = $this->rows('SELECT * FROM anex_review_state WHERE anex_hotel_id=?' . $end, [$id])[0] ?? ['revision' => 0];
        $exclusions = $this->rows('SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id=? ORDER BY catalog_hotel_id' . $end, [$id]);
        $data = ['anex_hotel_id' => $id, 'country_id' => $o['country_id'], 'hotel_name' => $o['hotel_name'],
            'source' => $source, 'evidence' => $evidence, 'candidates' => $candidates, 'manual' => $manual,
            'policy' => $policy, 'revision' => (int)$state['revision'], 'exclusions' => $exclusions];
        // Volatile search counts/timestamps are displayed, but are not mapping evidence.
        $data['version'] = hash('sha256', self::json($data));
        $data['observation'] = $o;
        $data['state'] = $state;
        $data['mapped_id'] = self::effective($manual, $policy);
        return $data;
    }

    public function detail($id): array
    {
        $this->db->beginTransaction();
        try {
            $data = $this->snapshot(self::id($id), false);
            $data['audit'] = $this->rows('SELECT action,actor,catalog_hotel_id,evidence_digest,decided_at FROM anex_review_audit WHERE anex_hotel_id=? ORDER BY id DESC LIMIT 50', [$id]);
            $this->db->commit();
            return $data;
        } catch (Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    /** Caller supplies ONLY the actor obtained from the verified server session. */
    public function decide(array $input, string $actor): array
    {
        $id = self::id($input['id'] ?? null);
        $action = $input['action'] ?? '';
        if (!in_array($action, ['accept', 'reject_pair', 'later'], true)) throw new RuntimeException('invalid_action', 400);
        $target = $action === 'later' ? null : self::id($input['target'] ?? null);
        $request = $input['request_id'] ?? '';
        $version = $input['version'] ?? '';
        if (!is_string($request) || !preg_match('/\A[0-9a-f]{32}\z/D', $request)
            || !is_string($version) || !preg_match('/\A[0-9a-f]{64}\z/D', $version)
            || !preg_match('/\A[a-zA-Z0-9:@._-]{1,200}\z/D', $actor)) throw new RuntimeException('invalid_request', 400);
        $digest = hash('sha256', self::json([$id, $target, $action, $version, $actor]));
        $this->db->beginTransaction();
        try {
            // Existing observed row is the mutex; no insert/upsert on a GET.
            if (!$this->rows('SELECT anex_hotel_id FROM anex_search_hotel_observations WHERE anex_hotel_id=? FOR UPDATE', [$id])) {
                throw new RuntimeException('hotel_not_observed', 404);
            }
            $old = $this->rows('SELECT request_digest,result_json FROM anex_review_audit WHERE request_id=? FOR UPDATE', [$request])[0] ?? null;
            if ($old !== null) {
                if (!hash_equals($old['request_digest'], $digest)) throw new RuntimeException('request_reused', 409);
                $result = json_decode($old['result_json'], true, 512, JSON_THROW_ON_ERROR);
                $this->db->commit();
                return $result + ['replayed' => true];
            }
            $before = $this->snapshot($id, true);
            if (!hash_equals($before['version'], $version)) throw new RuntimeException('stale_evidence', 409);
            $selected = null;
            foreach ($before['candidates'] as $candidate) if ((int)$candidate['catalog_hotel_id'] === $target) $selected = $candidate;
            if ($action !== 'later' && $selected === null) throw new RuntimeException('not_a_saved_candidate', 409);
            if ($action === 'accept') {
                // Existing manual decisions are immutable here. Replacement needs its own explicit reviewed flow.
                if ($before['manual'] !== null || $before['mapped_id'] !== null) throw new RuntimeException('existing_decision_preserved', 409);
                foreach ($before['exclusions'] as $pair) if ((int)$pair['catalog_hotel_id'] === $target) throw new RuntimeException('pair_already_excluded', 409);
                if ($selected['current'] === null || (int)$before['country_id'] <= 0
                    || (int)$selected['current']['country_id'] !== (int)$before['country_id']) throw new RuntimeException('target_country_mismatch', 409);
                $note = self::json(['origin' => 'owner:anex_review_panel', 'evidence_digest' => $version, 'request_id' => $request]);
                $this->rows("INSERT INTO anex_hotel_decisions (anex_hotel_id,decision_status,catalog_hotel_id,decided_by,decision_note,decided_at) VALUES (?,'accepted',?,?,?,UTC_TIMESTAMP())", [$id, $target, $actor, $note]);
            } elseif ($action === 'reject_pair') {
                if ($before['mapped_id'] === $target) throw new RuntimeException('active_mapping_preserved', 409);
                if ($this->rows('SELECT 1 FROM anex_review_pair_exclusions WHERE anex_hotel_id=? AND catalog_hotel_id=?', [$id, $target])) {
                    throw new RuntimeException('pair_already_excluded', 409);
                }
                $this->rows('INSERT INTO anex_review_pair_exclusions (anex_hotel_id,catalog_hotel_id,decided_by,decided_at,evidence_digest) VALUES (?,?,?,UTC_TIMESTAMP(),?)', [$id, $target, $actor, $version]);
            }
            $this->rows('INSERT INTO anex_review_state (anex_hotel_id,revision) VALUES (?,1) ON DUPLICATE KEY UPDATE revision=revision+1', [$id]);
            if ($action === 'later') {
                $this->rows('UPDATE anex_review_state SET deferred_at=UTC_TIMESTAMP(),deferred_by=? WHERE anex_hotel_id=?', [$actor, $id]);
            } elseif ($action === 'accept') {
                $this->rows('UPDATE anex_review_state SET deferred_at=NULL,deferred_by=NULL WHERE anex_hotel_id=?', [$id]);
            }
            $after = $this->snapshot($id, true);
            if ($action === 'accept' && $after['mapped_id'] !== $target) throw new RuntimeException('decision_readback_failed');
            $result = ['action' => $action, 'id' => $id, 'target' => $target, 'mapped_id' => $after['mapped_id'], 'version' => $after['version']];
            $this->rows('INSERT INTO anex_review_audit (request_id,request_digest,anex_hotel_id,catalog_hotel_id,action,actor,evidence_digest,before_json,result_json,decided_at) VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',
                [$request, $digest, $id, $target, $action, $actor, $version, self::json($before), self::json($result)]);
            $readback = $this->rows('SELECT result_json FROM anex_review_audit WHERE request_id=?', [$request])[0] ?? null;
            if ($readback === null || $readback['result_json'] !== self::json($result)) throw new RuntimeException('audit_readback_failed');
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
