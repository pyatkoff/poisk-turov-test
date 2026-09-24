<?php
declare(strict_types=1);

/**
 * INT-owned orchestration for one bounded direct-ANEX refresh into local persistence.
 * Supplier transport, APD arithmetic, mapping and AnyTour offer-store writes remain
 * delegated to the already-tested callbacks supplied by the runtime/CLI.
 */
final class AnyTourAnexLocalOfferCollectorV1
{
    /**
     * Split one user-visible direct-ANEX date intent into exact-day supplier windows.
     * The owner contract is at most 21 inclusive days. Live supplier evidence shows
     * broad windows can fail while later exact days remain valid, so a bad date must
     * not prevent collection of independently valid dates. Invalid/reversed/too-wide
     * ranges still fail before I/O.
     *
     * @return list<array{from:string,to:string}>
     */
    public static function dateWindows(string $from, string $to): array
    {
        $parse = static function (string $value): DateTimeImmutable {
            if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $value, $m)
                || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_DATE_RANGE');
            }
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        };
        $start = $parse($from);
        $end = $parse($to);
        if ($end < $start) throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_DATE_RANGE');
        $inclusiveDays = (int)$start->diff($end)->days + 1;
        if ($inclusiveDays < 1 || $inclusiveDays > 21) {
            throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_DATE_RANGE');
        }

        $windows = [];
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            $day = $cursor->format('Y-m-d');
            $windows[] = ['from' => $day, 'to' => $day];
        }
        return $windows;
    }

    /**
     * Run supplier-safe windows sequentially. Persistence/invariant failures still
     * fail-stop. In a multi-window range only bounded supplier transport/result
     * errors are isolated to their exact window so later independent windows can
     * still complete. A failed supplier window never becomes an empty-success fact.
     *
     * @param callable(array,int,array):array $collectWindow
     */
    public static function collectRange(
        array $searchRequest,
        string $from,
        string $to,
        callable $collectWindow
    ): array {
        if (($searchRequest['action'] ?? null) !== 'search'
            || !is_int($searchRequest['generation'] ?? null)
            || !is_array($searchRequest['params'] ?? null)) {
            throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_INPUT');
        }
        $windows = self::dateWindows($from, $to);
        $receipts = [];
        $completed = 0;
        $status = 'complete';
        $multiWindow = count($windows) > 1;
        foreach ($windows as $index => $window) {
            $request = $searchRequest;
            $request['params']['dateFrom'] = $window['from'];
            $request['params']['dateTo'] = $window['to'];
            $supplierError = false;
            try {
                $result = $collectWindow($request, $index, $window);
            } catch (RuntimeException $error) {
                if (!$multiWindow || !in_array($error->getMessage(), ['ANEX_SUPPLIER_ERROR', 'ANEX_HTTP_ERROR'], true)) {
                    throw $error;
                }
                $supplierError = true;
                $result = [
                    'source' => 'anex-local-offer-collector-range-v1',
                    'status' => 'supplier_error',
                    'error_code' => $error->getMessage(),
                    'requested_date_range' => $window,
                    'selection_authority' => false,
                ];
            }
            if (!is_array($result) || !is_string($result['status'] ?? null)) {
                throw new RuntimeException('ANEX_LOCAL_COLLECTOR_RANGE_RESULT');
            }
            $receipts[] = ['date_range' => $window, 'result' => $result];
            if ($result['status'] !== 'complete') {
                $status = 'incomplete';
                $isolatedGap = $supplierError
                    || ($multiWindow && in_array($result['status'], ['supplier_error', 'unknown_no_replay'], true));
                if ($isolatedGap) continue;
                break;
            }
            ++$completed;
        }
        return [
            'source' => 'anex-local-offer-collector-range-v1',
            'status' => $status,
            'requested_date_range' => ['from' => $from, 'to' => $to],
            'window_count' => count($windows),
            'windows_completed' => $completed,
            'windows' => $receipts,
            'selection_authority' => false,
        ];
    }

    public static function collect(
        array $searchRequest,
        array &$state,
        callable $search,
        callable $expand,
        callable $recordPrograms,
        callable $additionalBatch,
        int $maxExpands = 600,
        int $maxBatchItems = 600
    ): array {
        if (($searchRequest['action'] ?? null) !== 'search'
            || !is_int($searchRequest['generation'] ?? null)
            || !is_array($searchRequest['params'] ?? null)
            || $maxExpands < 0 || $maxExpands > 600
            || $maxBatchItems < 1 || $maxBatchItems > 600) {
            throw new InvalidArgumentException('ANEX_LOCAL_COLLECTOR_INPUT');
        }

        $searchData = $search($searchRequest, $state);
        if (!is_array($searchData) || ($searchData['provider'] ?? null) !== 'anex'
            || !is_string($searchData['search_ref'] ?? null)
            || !is_array($searchData['hotels'] ?? null)) {
            throw new RuntimeException('ANEX_LOCAL_COLLECTOR_SEARCH');
        }
        $recordPrograms($state);

        $grouped = [];
        $charters = [];
        $regular = [];
        self::collectOffers($searchData['hotels'], $grouped, $charters, $regular);

        $processed = 0;
        $ready = 0;
        $complete = 0;
        $retryable = 0;
        $batchCalls = 0;
        // Persist full batches as soon as their concrete offers are discovered.
        // Keep the final short tail until normal completion: batching, item order
        // and budgets stay unchanged, and failures never trigger a retry/flush.
        $flush = static function (bool $finish) use (
            &$charters, &$state, $searchRequest, $searchData, $additionalBatch, $maxBatchItems,
            &$processed, &$ready, &$complete, &$retryable, &$batchCalls
        ): void {
            $items = array_slice(array_values($charters), $processed, $maxBatchItems - $processed);
            foreach (array_chunk($items, 6) as $chunk) {
                if (!$finish && count($chunk) < 6) break;
                $batch = $additionalBatch([
                    'action' => 'additional_prices_batch',
                    'generation' => $searchRequest['generation'],
                    'search_ref' => $searchData['search_ref'],
                    'items' => $chunk,
                ], $state);
                ++$batchCalls;
                if (!is_array($batch) || ($batch['status'] ?? null) !== 'additional_prices_batch'
                    || !is_array($batch['offers'] ?? null)) {
                    throw new RuntimeException('ANEX_LOCAL_COLLECTOR_APD');
                }
                foreach ($batch['offers'] as $offer) {
                    if (!is_array($offer)) continue;
                    if (($offer['status'] ?? null) === 'additional_prices') ++$complete;
                    if (($offer['finalPriceReady'] ?? null) === true) ++$ready;
                    if (($offer['retryable'] ?? null) === true) ++$retryable;
                }
                $processed += count($chunk);
            }
        };
        $flush(false);

        $expanded = 0;
        foreach ($grouped as $group) {
            if ($expanded >= $maxExpands || count($charters) >= $maxBatchItems) break;
            $reply = $expand([
                'action' => 'expand',
                'generation' => $searchRequest['generation'],
                'search_ref' => $searchData['search_ref'],
                'offer_ref' => $group['offer_ref'],
                'local_hotel_id' => $group['local_hotel_id'],
            ], $state);
            ++$expanded;
            $recordPrograms($state);
            if (!is_array($reply) || ($reply['status'] ?? null) !== 'expanded') continue;
            $ignoredGrouped = [];
            self::collectOffers($reply['hotels'] ?? [], $ignoredGrouped, $charters, $regular);
            $flush(false);
        }

        $flush(true);

        $groupedDrained = $expanded >= count($grouped);
        $concreteDrained = count($charters) <= $maxBatchItems;
        $discoveredSetDrained = $groupedDrained && $concreteDrained;
        return [
            'source' => 'anex-local-offer-collector-v1',
            // A bounded invocation may safely preserve batches it already completed,
            // but it must not claim the scope itself was completed until every grouped
            // candidate and discovered concrete charter fit inside the caller's bounds.
            'status' => $discoveredSetDrained ? 'complete' : 'incomplete',
            'search_hotels' => count($searchData['hotels']),
            'grouped_candidates' => count($grouped),
            'expand_calls' => $expanded,
            'charter_concrete_candidates' => count($charters),
            'regular_concrete_candidates' => count($regular),
            'apd_batch_items' => $processed,
            'apd_batch_calls' => $batchCalls,
            'apd_complete_offers' => $complete,
            'final_price_ready_offers' => $ready,
            'retryable_offers' => $retryable,
            'grouped_drained' => $groupedDrained,
            'concrete_drained' => $concreteDrained,
            'discovered_set_drained' => $discoveredSetDrained,
            'selection_authority' => false,
        ];
    }

    private static function collectOffers(array $hotels, array &$grouped, array &$charters, array &$regular): void
    {
        foreach ($hotels as $hotel) {
            if (!is_array($hotel) || !is_int($hotel['local_id'] ?? null) || !is_array($hotel['tours'] ?? null)) continue;
            $local = $hotel['local_id'];
            foreach ($hotel['tours'] as $tour) {
                if (!is_array($tour) || !is_string($tour['offer_ref'] ?? null)) continue;
                $pair = ['offer_ref' => $tour['offer_ref'], 'local_hotel_id' => $local];
                $key = $tour['offer_ref'] . ':' . $local;
                if (($tour['kind'] ?? null) === 'group_minimum') {
                    $grouped[$key] = $pair;
                    continue;
                }
                if (($tour['kind'] ?? null) !== 'concrete') continue;
                if (($tour['flight_type'] ?? null) === 'charter') $charters[$key] = $pair;
                elseif (($tour['flight_type'] ?? null) === 'regular') $regular[$key] = $pair;
            }
        }
    }
}

/**
 * Durable private per-day no-replay ledger for one direct-ANEX range generation.
 * It stores fixed metadata/counters only; supplier ids, URLs, responses and secrets
 * are never persisted here.
 */
final class AnyTourAnexRangeCheckpointV1
{
    private string $dir;
    private string $checkpointId;
    private string $scopeDigest;

    public function __construct(string $root, string $checkpointId, string $scopeDigest)
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{7,95}\z/D', $checkpointId)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $scopeDigest)) {
            throw new InvalidArgumentException('ANEX_RANGE_CHECKPOINT_ID');
        }
        if ($root === '' || str_contains($root, "\0") || is_link($root)) {
            throw new RuntimeException('ANEX_RANGE_CHECKPOINT_ROOT');
        }
        self::ensureDir($root);
        $dir = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $checkpointId;
        self::ensureDir($dir);
        $this->dir = $dir;
        $this->checkpointId = $checkpointId;
        $this->scopeDigest = $scopeDigest;

        $manifestPath = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
        $manifest = self::readJson($manifestPath);
        if ($manifest === null) {
            self::writeJson($manifestPath, [
                'version' => 1,
                'checkpoint_id' => $checkpointId,
                'scope_digest' => $scopeDigest,
            ], true);
        } elseif (($manifest['version'] ?? null) !== 1
            || ($manifest['checkpoint_id'] ?? null) !== $checkpointId
            || ($manifest['scope_digest'] ?? null) !== $scopeDigest
            || array_keys($manifest) !== ['version', 'checkpoint_id', 'scope_digest']) {
            throw new RuntimeException('ANEX_RANGE_CHECKPOINT_SCOPE');
        }
    }

    /**
     * @return array{action:string,result?:array}
     */
    public function begin(string $day): array
    {
        self::validDay($day);
        $path = $this->dayPath($day);
        $entry = self::readJson($path);
        if ($entry === null) {
            self::writeJson($path, [
                'version' => 1,
                'checkpoint_id' => $this->checkpointId,
                'scope_digest' => $this->scopeDigest,
                'date' => $day,
                'state' => 'started',
                'started_at' => time(),
                'terminal_at' => null,
                'result' => null,
            ], true);
            return ['action' => 'run'];
        }
        $this->validateEntry($entry, $day);
        if ($entry['state'] === 'started') {
            $entry['state'] = 'unknown_no_replay';
            $entry['terminal_at'] = time();
            $entry['result'] = [
                'source' => 'anex-range-checkpoint-v1',
                'status' => 'unknown_no_replay',
                'selection_authority' => false,
            ];
            self::writeJson($path, $entry, false);
        }
        return ['action' => 'reuse', 'result' => $this->resultFromEntry($entry)];
    }

    public function finish(string $day, string $state, array $result): void
    {
        self::validDay($day);
        if (!in_array($state, ['complete', 'supplier_error'], true)) {
            throw new InvalidArgumentException('ANEX_RANGE_CHECKPOINT_STATE');
        }
        $path = $this->dayPath($day);
        $entry = self::readJson($path);
        if ($entry === null) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_MISSING_START');
        $this->validateEntry($entry, $day);
        if ($entry['state'] !== 'started') {
            throw new RuntimeException('ANEX_RANGE_CHECKPOINT_ALREADY_TERMINAL');
        }
        $summary = self::sanitizeResult($result, $state);
        $entry['state'] = $state;
        $entry['terminal_at'] = time();
        $entry['result'] = $summary;
        self::writeJson($path, $entry, false);
    }

    private function resultFromEntry(array $entry): array
    {
        $result = is_array($entry['result'] ?? null) ? $entry['result'] : [];
        $result['status'] = $entry['state'];
        $result['requested_date_range'] = ['from' => $entry['date'], 'to' => $entry['date']];
        $result['selection_authority'] = false;
        $result['checkpoint_reused'] = true;
        return $result;
    }

    private static function sanitizeResult(array $result, string $state): array
    {
        $out = [
            'source' => 'anex-range-checkpoint-v1',
            'status' => $state,
            'selection_authority' => false,
        ];
        if ($state === 'supplier_error') {
            $code = $result['error_code'] ?? null;
            if (is_string($code) && in_array($code, ['ANEX_SUPPLIER_ERROR', 'ANEX_HTTP_ERROR'], true)) {
                $out['error_code'] = $code;
            }
        }
        foreach ([
            'search_hotels','grouped_candidates','expand_calls','charter_concrete_candidates',
            'regular_concrete_candidates','apd_batch_items','apd_batch_calls',
            'apd_complete_offers','final_price_ready_offers','retryable_offers',
        ] as $key) {
            $value = $result[$key] ?? null;
            if (is_int($value) && $value >= 0 && $value <= 100000000) $out[$key] = $value;
        }
        foreach (['grouped_drained','concrete_drained','discovered_set_drained'] as $key) {
            if (is_bool($result[$key] ?? null)) $out[$key] = $result[$key];
        }
        return $out;
    }

    private function validateEntry(array $entry, string $day): void
    {
        if (($entry['version'] ?? null) !== 1
            || ($entry['checkpoint_id'] ?? null) !== $this->checkpointId
            || ($entry['scope_digest'] ?? null) !== $this->scopeDigest
            || ($entry['date'] ?? null) !== $day
            || !in_array($entry['state'] ?? null, ['started','complete','supplier_error','unknown_no_replay'], true)
            || !is_int($entry['started_at'] ?? null) || $entry['started_at'] < 1
            || !(($entry['terminal_at'] ?? null) === null || is_int($entry['terminal_at']))
            || !(($entry['result'] ?? null) === null || is_array($entry['result']))) {
            throw new RuntimeException('ANEX_RANGE_CHECKPOINT_INVALID');
        }
    }

    private function dayPath(string $day): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . $day . '.json';
    }

    private static function validDay(string $day): void
    {
        if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $day, $m)
            || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            throw new InvalidArgumentException('ANEX_RANGE_CHECKPOINT_DATE');
        }
    }

    private static function ensureDir(string $path): void
    {
        if (is_link($path)) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_SYMLINK');
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('ANEX_RANGE_CHECKPOINT_DIRECTORY');
        }
        if (is_link($path) || !is_dir($path)) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_DIRECTORY');
        @chmod($path, 0700);
    }

    private static function readJson(string $path): ?array
    {
        if (!file_exists($path)) return null;
        if (!is_file($path) || is_link($path)) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_FILE');
        $size = @filesize($path);
        if (!is_int($size) || $size < 2 || $size > 32768) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_FILE');
        $raw = @file_get_contents($path);
        if (!is_string($raw) || strlen($raw) !== $size) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_FILE');
        try {
            $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('ANEX_RANGE_CHECKPOINT_FILE');
        }
        if (!is_array($value)) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_FILE');
        return $value;
    }

    private static function writeJson(string $path, array $value, bool $exclusive): void
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!is_string($json) || strlen($json) > 32768) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_WRITE');
        if ($exclusive) {
            $h = @fopen($path, 'xb');
            if ($h === false) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_EXISTS');
            $ok = false;
            try {
                @chmod($path, 0600);
                $written = fwrite($h, $json);
                if ($written !== strlen($json) || !fflush($h) || (function_exists('fsync') && !fsync($h))) {
                    throw new RuntimeException('ANEX_RANGE_CHECKPOINT_WRITE');
                }
                $ok = true;
            } finally {
                fclose($h);
                if (!$ok) @unlink($path);
            }
            return;
        }
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
        $h = @fopen($tmp, 'xb');
        if ($h === false) throw new RuntimeException('ANEX_RANGE_CHECKPOINT_WRITE');
        $ok = false;
        try {
            @chmod($tmp, 0600);
            $written = fwrite($h, $json);
            if ($written !== strlen($json) || !fflush($h) || (function_exists('fsync') && !fsync($h))) {
                throw new RuntimeException('ANEX_RANGE_CHECKPOINT_WRITE');
            }
            $ok = true;
        } finally {
            fclose($h);
            if (!$ok) @unlink($tmp);
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('ANEX_RANGE_CHECKPOINT_WRITE');
        }
        @chmod($path, 0600);
    }
}

