<?php
declare(strict_types=1);

/**
 * Opt-in private CLI bridge, not a public endpoint or a quote/booking permission.
 * Caller supplies the configured private searches directory, current mapping
 * reader and existing HTTPS transport with package support. Existing API atomic
 * writer/monthly counter and pinned package client/store must already be loaded.
 * No search, login, filesystem scan or automatic retry is performed here.
 */
function anytour_andromeda_capture_saved_package(string $directory, array $context,
    string $source, callable $mappingAllows, callable $transport, bool $enabled = false,
    ?callable $clock = null): array
{
    if (!$enabled || PHP_SAPI !== 'cli') throw new RuntimeException('ANDROMEDA_PACKAGE_DISABLED');
    require_once __DIR__ . '/andromeda-package-capture.php';
    if (!function_exists('anytour_andromeda_search3_save') || !function_exists('anytour_andromeda_search3_budget')) {
        throw new RuntimeException('ANDROMEDA_PACKAGE_RUNTIME_MISSING');
    }
    $ref = $context['search_ref'] ?? null;
    $offerRef = $context['offer_ref'] ?? null;
    $page = $context['page'] ?? null;
    if (!is_string($ref) || !preg_match('/^[a-f0-9]{64}$/D', $ref)
        || !is_string($offerRef) || !preg_match('/^offer_[a-f0-9]{64}$/D', $offerRef)
        || !is_int($page) || $page < 1 || !preg_match('/^[a-f0-9]{40}$/D', $source)
        || !is_dir($directory) || is_link($directory) || basename($directory) !== 'searches') {
        throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
    }
    $clock = $clock ?? static fn() => time();
    $read = static function(string $path, bool $optional = false): array {
        if (is_link($path)) throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_INVALID');
        if (!file_exists($path) && $optional) return [];
        if (!is_file($path) || filesize($path) > 3000000) throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_INVALID');
        $data = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_INVALID');
        return $data;
    };
    $lockPath = $directory . '/' . $ref . '.lock';
    if (is_link($lockPath)) throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_INVALID');
    $lock = fopen($lockPath, 'c');
    if (!$lock) throw new RuntimeException('ANDROMEDA_PACKAGE_LOCK_FAILED');
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('ANDROMEDA_PACKAGE_LOCK_FAILED');
        $first = $read($directory . '/' . $ref . '-1.json');
        if (!in_array($first['status'] ?? null, ['complete', 'partial'], true)) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
        }
        $firstStore = $first['store'] ?? [];
        (new AnyTourAndromedaOfferStore($firstStore, true))->projection($ref, $context['generation'], $clock());
        $created = $firstStore['created_at'];
        $state = $page === 1 ? $first : $read($directory . '/' . $ref . '-' . $created . '-' . $page . '.json');
        if (!in_array($state['status'] ?? null, ['complete', 'partial'], true)) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
        }
        $storeState = $state['store'] ?? [];
        $store = new AnyTourAndromedaOfferStore($storeState, true);
        $path = $directory . '/' . $ref . '-' . $created . '-' . $page . '-' . $offerRef . '-package.json';
        $envelope = $read($path, true);
        if (file_exists($path) && (($envelope['source'] ?? null) !== $source
            || !is_array($envelope['record'] ?? null) || $envelope['record'] === [])) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_INVALID');
        }
        $record = $envelope['record'] ?? [];
        $persist = static function(array $next, array $expected) use ($read, $path, $source): array {
            $disk = $read($path, true);
            if (($disk['record'] ?? []) !== $expected
                || (file_exists($path) && (($disk['source'] ?? null) !== $source || ($disk['record'] ?? []) === []))) {
                throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_CHANGED');
            }
            anytour_andromeda_search3_save($path, ['source' => $source, 'record' => $next]);
            $written = $read($path);
            if (($written['source'] ?? null) !== $source || ($written['record'] ?? null) !== $next) {
                throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_FAILED');
            }
            return $written['record'];
        };
        $capture = new AnyTourAndromedaPackageCapture($record, $persist, $mappingAllows, true, $clock);
        $reused = $record !== [];
        if ($reused) {
            // Captured is read locally; reserved/unknown/stale never reissue a call.
            $result = $capture->read($store, $context);
        } else {
            AnyTourAndromedaSelectedOffer::resolve($store, $context, $mappingAllows, $clock());
            $auth = $read($directory . '/' . $ref . '-auth.json');
            if (($auth['created_at'] ?? null) !== $created) throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
            $attempted = false;
            $client = new AnyTourAndromedaClient(static function($url, $options) use (&$attempted, $transport, $directory) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                if ($attempted || ($query['action'] ?? null) !== 'broninit') {
                    throw new RuntimeException('ANDROMEDA_PACKAGE_REPLAY_REFUSED');
                }
                $attempted = true;
                anytour_andromeda_search3_budget(dirname($directory));
                return $transport($url, $options);
            }, true, true);
            $client->restorePrivateSession($auth['session'] ?? []);
            $result = $capture->capture($store, $client, $context);
        }
        // Raw claim stays in the private checkpoint. Only receipt metadata exits.
        return ['status' => 'captured', 'source' => $source, 'reused' => $reused,
            'context' => $result['context'], 'package_sha256' => $result['package_sha256'],
            'identity_verified' => false, 'quote_verified' => false, 'selection_enabled' => false];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}


/**
 * Private invocation for an existing publicSelection DTO, not an HTTP action.
 * Config/catalog/DB are loaded by the existing trusted caller, never browser input.
 * No credential reads, lookup by price, new search, login or retry are introduced.
 */
function anytour_andromeda_capture_selected_package(array $config, array $catalog,
    array $selection, PDO $pdo, string $source, bool $enabled = false,
    ?callable $transport = null, ?callable $clock = null): array
{
    if (!$enabled || PHP_SAPI !== 'cli') throw new RuntimeException('ANDROMEDA_PACKAGE_DISABLED');
    $country = $catalog['local_country_id'] ?? null;
    $localId = $selection['local_id'] ?? null;
    $catalogPath = $config['catalog_path'] ?? null;
    $keys = ['provider', 'search_ref', 'generation', 'page', 'offer_ref', 'hotel_scope', 'operator_ref'];
    if (!is_string($catalogPath) || $catalogPath === '' || !is_int($country) || $country < 1
        || !is_int($localId) || $localId < 1 || array_diff($keys, array_keys($selection))) {
        throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
    }
    if (!function_exists('anytour_andromeda_search3_mapping_allows')) {
        throw new RuntimeException('ANDROMEDA_PACKAGE_RUNTIME_MISSING');
    }
    if ($transport === null) {
        // An old installed transport ignores the second constructor argument.
        // Refuse before reservation rather than turning a missing dependency into unknown.
        $constructor = new ReflectionMethod(AnyTourAndromedaTransport::class, '__construct');
        if ($constructor->getNumberOfParameters() < 2) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_TRANSPORT_MISSING');
        }
        $transport = new AnyTourAndromedaTransport(false, true);
    }
    $context = array_intersect_key($selection, array_flip($keys));
    $allows = static function(array $offer) use ($pdo, $country, $localId): bool {
        return ($offer['local_hotel_id'] ?? null) === $localId
            && anytour_andromeda_search3_mapping_allows($pdo, $country, $offer);
    };
    return anytour_andromeda_capture_saved_package(dirname($catalogPath) . '/searches',
        $context, $source, $allows, $transport, true, $clock);
}
