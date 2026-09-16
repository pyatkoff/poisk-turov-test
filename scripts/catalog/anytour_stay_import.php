<?php
/** Explicit reviewed batch importer; no HTTP endpoint, schema install or provider requests. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../v2/data/anytour-stay-catalog-v1.php';

final class AnyTourStayCommitUncertain extends RuntimeException {}
final class AnyTourStayReadbackFailed extends RuntimeException {}

final class AnyTourStayImport
{
    public const LIMIT = 1000;
    public const MAX_BYTES = 4194304;
    public function __construct(private PDO $pdo) {}
    public static function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    private static function digest(array $value): string { return hash('sha256', self::json($value)); }
    private static function keys(array $value, array $keys): void
    {
        $got = array_keys($value); sort($got); sort($keys);
        if ($got !== $keys) throw new InvalidArgumentException('Unexpected or missing manifest fields');
    }
    private static function text(mixed $v, int $max): string
    {
        if (!is_string($v) || trim($v) === '' || strlen($v) > $max || !preg_match('//u', $v)
            || preg_match('/[\x00-\x1f\x7f]/', $v)) throw new InvalidArgumentException('Invalid text field');
        return $v;
    }
    private static function sha(mixed $v): string
    {
        if (!is_string($v) || !preg_match('/^[a-f0-9]{64}$/D', $v)) throw new InvalidArgumentException('Exact SHA256 required');
        return $v;
    }
    /** Normalization is only structural. Supplier keys and reviewed room distinctions remain exact. */
    public static function manifest(array $input): array
    {
        self::keys($input, ['version', 'operation', 'rows']);
        if ($input['version'] !== 1 || !is_array($input['rows']) || !array_is_list($input['rows'])
            || !$input['rows'] || count($input['rows']) > self::LIMIT) throw new InvalidArgumentException('Expected manifest v1 with 1..1000 rows');
        $operation = self::text($input['operation'], 128);
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $operation)) throw new InvalidArgumentException('Invalid operation ID');
        $rows = $rooms = [];
        foreach ($input['rows'] as $value) {
            if (!is_array($value)) throw new InvalidArgumentException('Row must be an object');
            self::keys($value, ['scope', 'reference', 'hotelId', 'sourceSha256', 'target', 'evidence']);
            if (!is_array($value['scope']) || !is_array($value['reference']) || !is_array($value['target'])
                || !is_array($value['evidence'])) throw new InvalidArgumentException('Invalid row objects');
            self::keys($value['scope'], ['namespace', 'hotelKey', 'operatorKey']);
            self::keys($value['reference'], ['kind', 'keyKind', 'externalKey']);
            $scope = AnyTourStayCatalog::scope($value['scope']);
            $ref = AnyTourStayCatalog::reference($value['reference']);
            $hotel = $value['hotelId'];
            if (!is_int($hotel) || $hotel < 1) throw new InvalidArgumentException('Explicit independent hotel ID required');
            $target = $value['target'];
            if ($ref['kind'] === 'meal') {
                self::keys($target, ['code']);
                $target = ['code' => self::text($target['code'], 64)];
            } else {
                self::keys($target, ['localKey', 'nameRu', 'categoryCode', 'facts']);
                if (!is_array($target['facts'])) throw new InvalidArgumentException('Room facts must be an object');
                $target = ['localKey' => self::text($target['localKey'], 128), 'nameRu' => self::text($target['nameRu'], 255),
                    'categoryCode' => $target['categoryCode'] === null ? null : self::text($target['categoryCode'], 64),
                    'facts' => AnyTourStayCatalog::roomFacts($target['facts'])];
                $roomKey = self::json([$hotel, $target['localKey']]);
                if (isset($rooms[$roomKey]) && $rooms[$roomKey] !== self::json($target)) throw new InvalidArgumentException('Conflicting definitions of one local room');
                $rooms[$roomKey] = self::json($target);
            }
            self::keys($value['evidence'], ['ref', 'sha256', 'reviewedBy']);
            $evidence = ['ref' => self::text($value['evidence']['ref'], 255), 'sha256' => self::sha($value['evidence']['sha256']),
                'reviewedBy' => self::text($value['evidence']['reviewedBy'], 128)];
            $key = self::json([$scope, $ref]);
            if (isset($rows[$key])) throw new InvalidArgumentException('Duplicate exact source decision');
            $rows[$key] = ['scope' => $scope, 'reference' => $ref, 'hotelId' => $hotel,
                'sourceSha256' => self::sha($value['sourceSha256']), 'target' => $target, 'evidence' => $evidence];
        }
        ksort($rows, SORT_STRING);
        $manifest = ['version' => 1, 'operation' => $operation, 'rows' => array_values($rows)];
        if (strlen(self::json($manifest)) > self::MAX_BYTES) throw new InvalidArgumentException('Manifest too large');
        return $manifest;
    }
    private function one(string $sql, array $args, bool $lock): array|false
    {
        $s = $this->pdo->prepare($sql . ($lock ? ' FOR UPDATE' : '')); $s->execute($args);
        return $s->fetch(PDO::FETCH_ASSOC);
    }
    private function begin(bool $write): void
    {
        if ($this->pdo->inTransaction() || $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
            || $this->pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new RuntimeException('Dedicated exception-mode MySQL connection required');
        }
        $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $this->pdo->exec('SET TRANSACTION ' . ($write ? 'READ WRITE' : 'READ ONLY'));
        $this->pdo->beginTransaction();
    }
    private function schema(bool $lock): array
    {
        $tables = $this->pdo->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
            AND TABLE_NAME IN ('anytour_catalog_control','anytour_hotels','anytour_hotel_sources','anytour_meal_plans',
            'anytour_room_categories','anytour_hotel_rooms','anytour_stay_mappings') ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (count($tables) !== 7 || count(array_filter($tables, static fn($e) => $e === 'InnoDB')) !== 7) {
            throw new RuntimeException('All seven previously installed InnoDB catalogue tables required');
        }
        $control = $this->one('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1', [], $lock);
        if (!$control || (int)$control['schema_version'] !== 1) throw new RuntimeException('Unsupported hotel schema version');
        // Bind a reviewed plan to the configured database, not merely identical cloned rows.
        $identity = $this->pdo->query('SELECT DATABASE() AS database_name, @@hostname AS server_host, @@port AS server_port')->fetch(PDO::FETCH_ASSOC);
        if (!$identity || !is_string($identity['database_name']) || $identity['database_name'] === '') {
            throw new RuntimeException('Explicit target database identity required');
        }
        return ['tables' => $tables, 'databaseIdentitySha256' => self::digest($identity)];
    }
    /** Current authoritative rows only. This neither discovers nor invents hotel-source links. */
    private function inspect(array $manifest, bool $lock): array
    {
        $schema = $this->schema($lock); $snapshot = []; $newRooms = []; $newMappings = 0;
        foreach ($manifest['rows'] as $v) {
            $s = $v['scope']; $r = $v['reference']; $t = $v['target'];
            $source = $this->one('SELECT s.id,s.anytour_hotel_id,s.source_sha256,s.source_json,
                h.profile_sha256,h.profile_json,h.revision,h.is_active FROM anytour_hotel_sources s
                JOIN anytour_hotels h ON h.id=s.anytour_hotel_id WHERE s.namespace=? AND s.external_key=?', [$s['namespace'], $s['hotelKey']], $lock);
            if (!$source || (int)$source['anytour_hotel_id'] !== $v['hotelId'] || (int)$source['is_active'] !== 1
                || !hash_equals($v['sourceSha256'], $source['source_sha256'])
                || !hash_equals($source['source_sha256'], hash('sha256', $source['source_json']))
                || !hash_equals($source['profile_sha256'], hash('sha256', $source['profile_json']))) {
                throw new RuntimeException('Current hotel/source identity or evidence differs');
            }
            unset($source['source_json'], $source['profile_json']);
            $category = false;
            if ($r['kind'] === 'meal') {
                $target = $this->one('SELECT * FROM anytour_meal_plans WHERE code=?', [$t['code']], $lock);
                if (!$target || (int)$target['is_active'] !== 1) throw new RuntimeException('Exact local meal unavailable');
            } else {
                if ($t['categoryCode'] !== null) {
                    $category = $this->one('SELECT * FROM anytour_room_categories WHERE code=?', [$t['categoryCode']], $lock);
                    if (!$category) throw new RuntimeException('Unknown local room category');
                }
                $target = $this->one('SELECT * FROM anytour_hotel_rooms WHERE anytour_hotel_id=? AND local_key=?', [$v['hotelId'], $t['localKey']], $lock);
                if ($target) {
                    $facts = json_decode($target['facts_json'], true, 32, JSON_THROW_ON_ERROR);
                    if ((int)$target['is_active'] !== 1 || $target['name_ru'] !== $t['nameRu'] || $target['category_code'] !== $t['categoryCode']
                        || !is_array($facts) || self::json(AnyTourStayCatalog::roomFacts($facts)) !== self::json($t['facts'])) {
                        throw new RuntimeException('Existing canonical room content differs; never overwrite it');
                    }
                } else $newRooms[self::json([$v['hotelId'], $t['localKey']])] = true;
            }
            $mapping = $this->one('SELECT * FROM anytour_stay_mappings WHERE namespace=? AND external_hotel_key=? AND operator_key=?
                AND kind=? AND key_kind=? AND external_key=?', [$s['namespace'], $s['hotelKey'], $s['operatorKey'], $r['kind'], $r['keyKind'], $r['externalKey']], $lock);
            if ($mapping) {
                $targetId = $r['kind'] === 'room' ? $mapping['room_id'] : $mapping['meal_id'];
                if ($mapping['state'] !== 'accepted' || (int)$mapping['anytour_hotel_id'] !== $v['hotelId']
                    || !$target || (int)$targetId !== (int)$target['id']
                    || $mapping['evidence_ref'] !== $v['evidence']['ref'] || $mapping['evidence_sha256'] !== $v['evidence']['sha256']
                    || $mapping['reviewed_by'] !== $v['evidence']['reviewedBy']) {
                    throw new RuntimeException('Existing decision differs; explicit separate review required');
                }
            } else $newMappings++;
            $snapshot[] = ['source' => $source, 'category' => $category, 'target' => $target, 'mapping' => $mapping];
        }
        return ['schema' => $schema, 'rows' => $snapshot, 'newRooms' => count($newRooms), 'newMappings' => $newMappings];
    }
    private static function planResult(array $manifest, array $snapshot): array
    {
        $inputHash = self::digest($manifest); $stateHash = self::digest($snapshot);
        return ['status' => 'prepared_read_only', 'operation' => $manifest['operation'], 'rows' => count($manifest['rows']),
            'manifestSha256' => $inputHash, 'currentSha256' => $stateHash,
            'planSha256' => self::digest(['version' => 1, 'manifest' => $inputHash, 'current' => $stateHash]),
            'createRooms' => $snapshot['newRooms'], 'createMappings' => $snapshot['newMappings'],
            'unchangedMappings' => count($manifest['rows']) - $snapshot['newMappings'], 'writes' => 0];
    }
    public function plan(array $input): array
    {
        $manifest = self::manifest($input); $this->begin(false);
        try { $result = self::planResult($manifest, $this->inspect($manifest, false)); $this->pdo->commit(); return $result; }
        catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    /** No internal retries. COMMIT uncertainty and post-COMMIT verification failure are distinct. */
    public function apply(array $input, string $expectedPlan, callable $checkpoint): array
    {
        $manifest = self::manifest($input); self::sha($expectedPlan); $this->begin(true); $phase = 'before_commit';
        try {
            $before = $this->inspect($manifest, true); $plan = self::planResult($manifest, $before);
            if (!hash_equals($expectedPlan, $plan['planSha256'])) throw new RuntimeException('Plan drifted; inspect a fresh read-only plan');
            $catalog = new AnyTourStayCatalog($this->pdo); $madeRooms = $madeMappings = 0;
            foreach ($manifest['rows'] as $i => $v) {
                if ($before['rows'][$i]['mapping']) continue;
                $target = $before['rows'][$i]['target']; $r = $v['reference']; $t = $v['target'];
                if ($r['kind'] === 'room' && !$target) {
                    // A preceding source in this same batch may already have created the reviewed local room.
                    $target = $this->one('SELECT id FROM anytour_hotel_rooms WHERE anytour_hotel_id=? AND local_key=?', [$v['hotelId'], $t['localKey']], true);
                    if (!$target) {
                        $target = ['id' => $catalog->createRoom($v['hotelId'], $t['localKey'], $t['nameRu'], $t['categoryCode'], $t['facts'])]; $madeRooms++;
                    }
                }
                $catalog->recordDecision($v['scope'], $r, $v['hotelId'], 'accepted', (int)$target['id'], $v['evidence']); $madeMappings++;
            }
            $after = $this->inspect($manifest, true);
            if ($after['newRooms'] || $after['newMappings'] || $madeRooms !== $plan['createRooms'] || $madeMappings !== $plan['createMappings']) {
                throw new RuntimeException('Batch postcondition differs before COMMIT');
            }
            $receipt = ['operation' => $manifest['operation'], 'manifestSha256' => $plan['manifestSha256'], 'planSha256' => $expectedPlan,
                'createdRooms' => $madeRooms, 'createdMappings' => $madeMappings, 'unchangedMappings' => $plan['unchangedMappings'],
                'verifiedMappings' => count($manifest['rows']), 'expectedReadbackSha256' => self::digest($after),
                'sourceWrites' => 0, 'canonicalOverwrites' => 0, 'supplierCalls' => 0];
            $checkpoint(array_merge($receipt, ['status' => 'before_commit', 'noReplay' => true]));
            $phase = 'commit_attempted'; $this->pdo->commit();
        } catch (Throwable $e) {
            if ($phase === 'commit_attempted') throw new AnyTourStayCommitUncertain('COMMIT outcome unknown; readback required, no replay', 0, $e);
            if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e;
        }
        try {
            $checkpoint(array_merge($receipt, ['status' => 'committed_unverified', 'noReplay' => true]));
            // New transaction, not read-your-own-uncommitted-writes verification.
            $verified = $this->plan($manifest);
            if (!hash_equals($receipt['expectedReadbackSha256'], $verified['currentSha256'])
                || $verified['createRooms'] !== 0 || $verified['createMappings'] !== 0) throw new RuntimeException('Readback differs');
            $receipt = array_merge($receipt, ['status' => 'committed_verified', 'noReplay' => true]);
            $checkpoint($receipt); return $receipt;
        } catch (Throwable $e) { throw new AnyTourStayReadbackFailed('COMMIT succeeded, verification incomplete; no replay', 0, $e); }
    }
}

// The library can be loaded by a checked, existing operations runner without opening credentials.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $journal = null;
    try {
        $options = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (!preg_match('/^--(manifest|apply-plan-sha256|receipt)=(.+)$/D', $arg, $m) || isset($options[$m[1]])) throw new InvalidArgumentException('Unknown/duplicate argument');
            $options[$m[1]] = $m[2];
        }
        if (!isset($options['manifest']) || isset($options['receipt']) !== isset($options['apply-plan-sha256'])) throw new InvalidArgumentException('Use --manifest=FILE [--apply-plan-sha256=SHA --receipt=NEW_FILE]');
        $path = $options['manifest'];
        if (is_link($path) || !is_file($path)) throw new InvalidArgumentException('Local regular manifest file required');
        $inputFile = fopen($path, 'rb');
        if (!$inputFile) throw new RuntimeException('Manifest read failed');
        try { $raw = stream_get_contents($inputFile, AnyTourStayImport::MAX_BYTES + 1); } finally { fclose($inputFile); }
        if ($raw === false || strlen($raw) > AnyTourStayImport::MAX_BYTES) throw new InvalidArgumentException('Oversized manifest');
        $input = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new InvalidArgumentException('Manifest object required');
        $input = AnyTourStayImport::manifest($input);
        $checkpoint = static function(array $state) use (&$journal): void {
            $line = AnyTourStayImport::json($state) . "\n";
            if (!is_resource($journal) || fwrite($journal, $line) !== strlen($line) || !fflush($journal) || !fsync($journal)) throw new RuntimeException('Durable receipt write failed');
        };
        if (isset($options['apply-plan-sha256'])) {
            $oldMask = umask(0077);
            try { $journal = @fopen($options['receipt'], 'x+b'); } finally { umask($oldMask); }
            if (!$journal) throw new RuntimeException('Exclusive NEW receipt required; inspect prior operation instead of replay');
            $checkpoint(['status' => 'reserved', 'operation' => $input['operation'], 'inputBytesSha256' => hash('sha256', $raw),
                'planSha256' => $options['apply-plan-sha256'], 'noReplay' => true]);
        }
        require_once __DIR__ . '/../../v2/data/db-v1.php';
        $import = new AnyTourStayImport(v2_data_db());
        $result = isset($options['apply-plan-sha256']) ? $import->apply($input, $options['apply-plan-sha256'], $checkpoint) : $import->plan($input);
        echo AnyTourStayImport::json($result) . "\n";
    } catch (Throwable $e) {
        $state = $e instanceof AnyTourStayCommitUncertain ? 'commit_unknown' : ($e instanceof AnyTourStayReadbackFailed ? 'committed_unverified' : 'failed_before_commit');
        // Never expose raw PDO messages, DSNs, credentials or paths in shared logs.
        if (is_resource($journal)) { try { $checkpoint(['status' => $state, 'noReplay' => true]); } catch (Throwable) {} }
        fwrite(STDERR, 'ANYTOUR_STAY_IMPORT_FAILED state=' . $state . ' class=' . get_class($e) . "; inspect retained receipt, do not replay\n"); exit(1);
    } finally { if (is_resource($journal)) fclose($journal); }
}
