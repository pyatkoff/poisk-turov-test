<?php
declare(strict_types=1);

/** Immutable local evidence archive. Does not write mappings, decisions, staging or catalogue. */
final class AnexReviewDossierStore
{
    private PDO $db;
    public function __construct(PDO $db) {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('dossier_requires_mysql');
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
        $this->db = $db;
    }

    private function rows(string $sql, array $args = []): array
    {
        $query = $this->db->prepare($sql);
        $query->execute($args);
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function hashField($value, int $length = 64): string
    {
        if (!is_string($value) || !preg_match('/\A[0-9a-f]{'.$length.'}\z/D', $value)) throw new RuntimeException('dossier_digest_invalid');
        return $value;
    }

    private static function validateRow(array $item): array
    {
        if (!is_int($item['id'] ?? null) || $item['id'] <= 0 || $item['id'] >= 100000000) throw new RuntimeException('dossier_id_invalid');
        foreach (['row','evidence'] as $part) {
            if (!is_string($item[$part.'_json'] ?? null) || strlen($item[$part.'_json']) > 1000000
                || !hash_equals(self::hashField($item[$part.'_digest'] ?? null), hash('sha256', $item[$part.'_json']))) {
                throw new RuntimeException('dossier_row_digest_invalid');
            }
        }
        $row = json_decode($item['row_json'], true, 64, JSON_THROW_ON_ERROR);
        $evidence = json_decode($item['evidence_json'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($row) || !is_array($evidence) || ($row['anex_hotel_id'] ?? null) !== $item['id']
            || ($evidence['external_id'] ?? null) !== $item['id'] || ($row['observation']['anex_hotel_id'] ?? null) !== $item['id']
            || ($row['automatic_acceptance'] ?? null) !== false || ($row['automatic_retry'] ?? null) !== false
            || !in_array($row['status'] ?? '', ['review','source_error','unmatched','protected'], true)
            || ($evidence['status'] ?? null) !== $row['status'] || ($row['evidence'] ?? null) !== $evidence
            || ($row['evidence_row_sha256'] ?? null) !== $item['evidence_digest']
            || !in_array($row['evidence_origin'] ?? '', ['live_checkpoint','legacy_checkpoint'], true)) {
            throw new RuntimeException('dossier_row_contract');
        }
        return $row;
    }

    private static function candidateRows(array $row): array
    {
        if ($row['status'] !== 'review' || (int)($row['observation']['country_id'] ?? 0) <= 0) return [];
        $result = [];
        foreach (is_array($row['evidence']['candidates'] ?? null) ? $row['evidence']['candidates'] : [] as $candidate) {
            if (!is_array($candidate)) continue;
            $target = $candidate['id'] ?? $candidate['catalog_hotel_id'] ?? null;
            if (!is_int($target) || $target <= 0 || $target >= 100000000 || isset($result[$target])) continue;
            $result[$target] = $candidate;
            if (count($result) === 20) break;
        }
        return $result;
    }

    private static function validateIndex(array $stored, array $row): void
    {
        if ((int)$stored['country_id'] !== max(0,(int)($row['observation']['country_id'] ?? 0))
            || (int)$stored['display_candidate_count'] !== count(self::candidateRows($row))
            || $stored['status'] !== $row['status']) throw new RuntimeException('dossier_index_invalid');
    }

    public function import(array $input): array
    {
        if ($this->db->inTransaction()) throw new RuntimeException('dossier_transaction_owned');
        if (($input['protocol_version'] ?? null) !== 1 || ($input['kind'] ?? '') !== 'observed_dossier_import'
            || ($input['scope'] ?? '') !== 'preview' || !is_int($input['artifact_id'] ?? null)
            || $input['artifact_id'] <= 0 || $input['artifact_id'] > 9000000000000000
            || !is_array($input['rows'] ?? null) || count($input['rows']) > 1000) throw new RuntimeException('dossier_contract');
        $source = self::hashField($input['source_sha'] ?? null, 40);
        $digest = self::hashField($input['source_digest'] ?? null);
        $checkpoint = self::hashField($input['checkpoint_digest'] ?? null);
        $ids = []; $size = 0;
        foreach ($input['rows'] as $item) {
            if (!is_array($item)) throw new RuntimeException('dossier_contract');
            self::validateRow($item);
            if (isset($ids[$item['id']])) throw new RuntimeException('dossier_duplicate_id');
            $ids[$item['id']] = true;
            $size += strlen($item['row_json']) + strlen($item['evidence_json']);
        }
        if ($size > 16000000) throw new RuntimeException('dossier_batch_bound');
        usort($input['rows'], static fn(array $a,array $b): int => $a['id'] <=> $b['id']);
        $artifact = $input['artifact_id'];
        $this->db->beginTransaction();
        try {
            $old = $this->rows('SELECT * FROM anex_review_dossier_batches WHERE artifact_id=? FOR UPDATE', [$artifact])[0] ?? null;
            if ($old !== null && ($old['source_digest'] !== $digest || $old['source_sha'] !== $source
                || $old['checkpoint_digest'] !== $checkpoint || (int)$old['row_count'] !== count($ids))) throw new RuntimeException('dossier_artifact_conflict');
            if ($old === null) {
                $this->rows('INSERT INTO anex_review_dossier_batches (artifact_id,source_sha,source_digest,checkpoint_digest,row_count,stored_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())',
                    [$artifact,$source,$digest,$checkpoint,count($ids)]);
            }
            $inserted = 0;
            foreach ($input['rows'] as $item) {
                // Lock the same observation mutex as the panel writer, so dossier versions
                // cannot change between its evidence check and decision commit.
                if (!$this->rows('SELECT anex_hotel_id FROM anex_search_hotel_observations WHERE anex_hotel_id=? FOR UPDATE', [$item['id']])) throw new RuntimeException('dossier_not_observed');
                $row = self::validateRow($item);
                $existing = $this->rows('SELECT * FROM anex_review_dossiers WHERE artifact_id=? AND anex_hotel_id=? FOR UPDATE', [$artifact,$item['id']])[0] ?? null;
                if ($existing !== null) {
                    foreach (['row_digest','evidence_digest','row_json','evidence_json'] as $key) if ($existing[$key] !== $item[$key]) throw new RuntimeException('dossier_existing_changed');
                    self::validateIndex($existing,$row);
                } else {
                    if ($old !== null) throw new RuntimeException('dossier_history_incomplete');
                    $this->rows('INSERT INTO anex_review_dossiers (artifact_id,anex_hotel_id,row_digest,evidence_digest,status,row_json,evidence_json,country_id,display_candidate_count) VALUES (?,?,?,?,?,?,?,?,?)',
                        [$artifact,$item['id'],$item['row_digest'],$item['evidence_digest'],$row['status'],$item['row_json'],$item['evidence_json'],max(0,(int)($row['observation']['country_id'] ?? 0)),count(self::candidateRows($row))]);
                    $inserted++;
                }
                $back = $this->rows('SELECT * FROM anex_review_dossiers WHERE artifact_id=? AND anex_hotel_id=?', [$artifact,$item['id']])[0];
                if ($back['row_json'] !== $item['row_json'] || $back['evidence_json'] !== $item['evidence_json']) throw new RuntimeException('dossier_readback_failed');
                self::validateIndex($back,$row);
            }
            if ((int)$this->rows('SELECT COUNT(*) AS n FROM anex_review_dossiers WHERE artifact_id=?', [$artifact])[0]['n'] !== count($ids)) throw new RuntimeException('dossier_count_mismatch');
            $this->db->commit();
            return ['status'=>$old === null ? 'stored' : 'already_stored','inserted'=>$inserted,'rows'=>count($ids),'artifact_id'=>$artifact];
        } catch (Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); throw $e; }
    }

    /** No artifact HTTP request: old versions stay archived; latest per hotel is read locally. */
    public function latest(int $id, bool $lock = false): ?array
    {
        $item = $this->rows('SELECT d.*,b.source_sha,b.source_digest,b.checkpoint_digest FROM anex_review_dossiers d'
            . ' JOIN anex_review_dossier_batches b ON b.artifact_id=d.artifact_id WHERE d.anex_hotel_id=? ORDER BY d.artifact_id DESC LIMIT 1' . ($lock ? ' FOR UPDATE' : ''), [$id])[0] ?? null;
        if ($item === null) return null;
        $item['id'] = (int)$item['anex_hotel_id'];
        $row = self::validateRow($item);
        self::validateIndex($item,$row);
        return ['artifact_id'=>(int)$item['artifact_id'],'source_sha'=>$item['source_sha'],
            'source_digest'=>$item['source_digest'],'checkpoint_digest'=>$item['checkpoint_digest'],
            'row_digest'=>$item['row_digest'],'row'=>$row];
    }

    /** Read-only projection. Historic hints and unknown results never become candidates. */
    public function panel(int $id, int $country, bool $lock = false): ?array
    {
        $saved = $this->latest($id,$lock);
        if ($saved === null) return null;
        $row = $saved['row']; $raw = $row['evidence'];
        $api = is_array($raw['api'] ?? null) ? $raw['api'] : [];
        $source = ['anex_hotel_id'=>$id];
        foreach (['name','country','region','town','address'] as $key) $source['api_'.$key] = is_string($api[$key] ?? null) ? $api[$key] : null;
        foreach (['latitude','longitude'] as $key) $source[$key] = is_numeric($api[$key] ?? null) ? $api[$key] : null;
        $source['checked_at'] = $raw['checked_at_utc'] ?? $raw['checked_at'] ?? null;
        $rawCandidates = is_array($raw['candidates'] ?? null) ? $raw['candidates'] : [];
        $validCountry = (int)($row['observation']['country_id'] ?? 0) === $country && $country > 0;
        $candidates = [];
        if ($validCountry) foreach (self::candidateRows($row) as $target => $candidate) {
            $candidates[] = ['anex_hotel_id'=>$id,'candidate_rank'=>count($candidates)+1,'catalog_hotel_id'=>$target,
                'score'=>$candidate['score'] ?? null,'name_similarity'=>$candidate['name_similarity'] ?? null,
                'distance_m'=>$candidate['distance_m'] ?? null,
                'candidate_json'=>json_encode($candidate,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)];
        }
        unset($saved['row']);
        return ['source'=>$source,'candidates'=>$candidates,'evidence'=>['automated_status'=>$row['status'],
            'automated_reason'=>$validCountry ? ($row['reason'] ?? '') : 'observed_country_changed',
            'candidate_count'=>count($rawCandidates),'stored_candidate_count'=>count($rawCandidates),
            'candidate_limit'=>$raw['candidate_limit'] ?? null,'dossier_provenance'=>$saved,
            'evidence_origin'=>$row['evidence_origin'],'display_limit'=>20]];
    }
}
