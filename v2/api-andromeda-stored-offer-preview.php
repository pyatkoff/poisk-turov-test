<?php
declare(strict_types=1);

/** Same-offer HTTP read: CURRENT LOCAL row -> locked retained SAMO source -> private viewer handle.
 * No supplier operations or selection/booking authority. Reuses #3342 and the native pricing reader.
 */
function anytour_stored_samo_identity(mixed $identity): array
{
    $keys = ['search_ref_digest', 'offer_ref_digest', 'provider_hotel_ref_digest'];
    if (!is_array($identity) || count($identity) !== 3 || array_diff($keys, array_keys($identity))) {
        throw new InvalidArgumentException('Invalid identity');
    }
    foreach ($identity as $value) {
        if (!is_string($value) || !preg_match('/\A[a-f0-9]{64}\z/D', $value)) throw new InvalidArgumentException('Invalid identity');
    }
    return array_replace(array_fill_keys($keys, ''), $identity);
}

function anytour_stored_samo_json(string $path, int &$remaining): array
{
    clearstatcache(true, $path);
    if (is_link($path) || !is_file($path)) throw new DomainException('Stored source unavailable');
    $size = filesize($path);
    if (!is_int($size) || $size < 2 || $size > 3000000 || $size > $remaining) throw new DomainException('Stored source unavailable');
    $file = fopen($path, 'rb');
    if ($file === false) throw new DomainException('Stored source unavailable');
    try {
        $stat = fstat($file); $named = lstat($path);
        if (!$stat || !$named || ($stat['mode'] & 0170000) !== 0100000
            || $stat['ino'] !== $named['ino'] || $stat['dev'] !== $named['dev']) throw new DomainException('Stored source unavailable');
        $raw = stream_get_contents($file, $size + 1);
        if (!is_string($raw) || strlen($raw) !== $size) throw new DomainException('Stored source unavailable');
        $remaining -= $size;
        $value = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new DomainException('Stored source unavailable');
        return $value;
    } finally { fclose($file); }
}

/** Filename discovery never reads auth/package files and is bounded; read handles avoid the scan. */
function anytour_stored_samo_snapshot(array $row, string $directory, ?array $locator,
    callable $canonicalAllows, callable $mappingAllows, callable $consume, int $now): array
{
    if (is_link($directory) || !is_dir($directory) || basename($directory) !== 'searches') throw new DomainException('Stored source unavailable');
    $directory = realpath($directory);
    if ($directory === false) throw new DomainException('Stored source unavailable');
    $identity = anytour_stored_samo_identity($row['offer']['identity'] ?? null);
    $ref = $locator['search_ref'] ?? null;
    if ($locator === null) {
        $entries = 0;
        foreach (new DirectoryIterator($directory) as $entry) {
            if (++$entries > 50000) throw new DomainException('Stored source unavailable');
            if (!preg_match('/\A([a-f0-9]{64})-1\.json\z/D', $entry->getFilename(), $match)) continue;
            if (!hash_equals($identity['search_ref_digest'], hash('sha256', $match[1]))) continue;
            if ($ref !== null) throw new DomainException('Ambiguous stored source');
            $ref = $match[1];
        }
    }
    if (!is_string($ref) || !preg_match('/\A[a-f0-9]{64}\z/D', $ref)
        || !hash_equals($identity['search_ref_digest'], hash('sha256', $ref))) throw new DomainException('Stored source unavailable');
    $lockPath = $directory . '/' . $ref . '.lock';
    // Never create a missing lock or renew a background operation.
    if (is_link($lockPath) || !is_file($lockPath)) throw new DomainException('Stored source unavailable');
    $lock = fopen($lockPath, 'rb');
    if ($lock === false) throw new DomainException('Stored source unavailable');
    try {
        $stat = fstat($lock); $named = lstat($lockPath);
        if (!$stat || !$named || $stat['ino'] !== $named['ino'] || $stat['dev'] !== $named['dev']
            || ($stat['mode'] & 0170000) !== 0100000 || !flock($lock, LOCK_SH | LOCK_NB)) throw new DomainException('Stored source unavailable');
        $remaining = 16 * 1024 * 1024;
        $first = anytour_stored_samo_json($directory . '/' . $ref . '-1.json', $remaining);
        $store = $first['store'] ?? [];
        $created = $store['created_at'] ?? null; $generation = $store['generation'] ?? null;
        $pages = $store['snapshot']['pages_count'] ?? null;
        if (!in_array($first['status'] ?? null, ['complete', 'partial'], true)
            || ($store['version'] ?? null) !== 1 || ($store['snapshot']['page'] ?? null) !== 1 || ($store['criteria']['PAGE'] ?? 1) !== 1
            || !is_int($created) || !is_int($generation) || $generation < 1
            || ($store['search_ref'] ?? null) !== $ref || ($store['expires_at'] ?? null) !== $created + 900
            || $now < $created || $now >= $created + 900 || !is_int($pages) || $pages < 1 || $pages > 1000) throw new DomainException('Stored source unavailable');
        if ($locator !== null && (($locator['created_at'] ?? null) !== $created
            || ($locator['generation'] ?? null) !== $generation || !is_int($locator['page'] ?? null)
            || $locator['page'] < 1 || $locator['page'] > $pages)) throw new DomainException('Stored source unavailable');
        $selected = null;
        $numbers = $locator === null ? range(1, $pages) : [$locator['page']];
        foreach ($numbers as $number) {
            $state = $number === 1 ? $first : anytour_stored_samo_json($directory . '/' . $ref . '-' . $created . '-' . $number . '.json', $remaining);
            $page = $state['store'] ?? null;
            if (!in_array($state['status'] ?? null, ['complete', 'partial'], true) || !is_array($page)
                || ($page['search_ref'] ?? null) !== $ref || ($page['generation'] ?? null) !== $generation
                || ($page['created_at'] ?? null) !== $created || ($page['expires_at'] ?? null) !== $created + 900
                || ($page['snapshot']['page'] ?? null) !== $number || !is_array($page['snapshot']['offers'] ?? null)
                || count($page['snapshot']['offers']) > 5000) throw new DomainException('Stored source unavailable');
            foreach ($page['snapshot']['offers'] as $offer) {
                $offerRef = $offer['offer_ref'] ?? null;
                if (!is_string($offerRef)) throw new DomainException('Stored source unavailable');
                if (!hash_equals($identity['offer_ref_digest'], hash('sha256', $offerRef))) continue;
                if ($selected !== null) throw new DomainException('Ambiguous stored offer');
                $selected = $page;
            }
        }
        if ($selected === null) throw new DomainException('Stored offer unavailable');
        $resolved = AnyTourStoredProviderOfferContext::resolveAndromeda($row, $selected, $canonicalAllows, $mappingAllows, $now);
        $located = ['search_ref' => $ref, 'created_at' => $created, 'generation' => $generation, 'page' => $selected['snapshot']['page']];
        return $consume($selected, $resolved, $located, $created + 900);
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

/** Read existing verified evidence only; never call package/calc to fill a missing quote. */
function anytour_stored_samo_pricing(string $directory, array $state, array $resolved,
    callable $mappingAllows, int $now): ?array
{
    $context = array_intersect_key($resolved['context'], array_flip(['provider','search_ref','generation','page','offer_ref']));
    $pricing = anytour_andromeda_read_saved_pricing($directory, $state, $state['created_at'], $context, $mappingAllows, $now);
    if (($pricing['state'] ?? null) !== 'verified') return null;
    $remaining = 16384;
    $path = $directory . '/' . $context['search_ref'] . '-' . $state['created_at'] . '-' . $context['page'] . '-' . $context['offer_ref'] . '-surcharge-v1.json';
    $record = anytour_stored_samo_json($path, $remaining);
    if (($record['verified_quote'] ?? null) !== ($pricing['verified_quote'] ?? null)) return null;
    return ['value' => $pricing['verified_quote'], 'expires_at' => $record['expires_at'] ?? null];
}

/** Request processor: only runtime-supplied readers may supply a row, mapping or price. */
function anytour_stored_samo_request(array $request, array &$handles, string $directory,
    callable $readLocal, callable $canonicalAllows, callable $mappingAllows, int $now,
    ?callable $readPricing = null): array
{
    $action = $request['action'] ?? null;
    $keys = $action === 'prepare' ? ['action','params','anytourHotelId','identity'] : ['action','handle'];
    if (!in_array($action, ['prepare','read'], true) || count($request) !== count($keys) || array_diff($keys, array_keys($request))) throw new InvalidArgumentException('Invalid request');
    $handle = null; $previous = null;
    if ($action === 'read') {
        $handle = $request['handle'];
        if (!is_string($handle) || !preg_match('/\Astored_[a-f0-9]{64}\z/D', $handle)) throw new InvalidArgumentException('Invalid handle');
        $previous = $handles[$handle] ?? null;
        if (!is_array($previous) || ($previous['expires_at'] ?? 0) <= $now) throw new DomainException('Stored offer unavailable');
        $request = $previous['request'];
    }
    if (!is_array($request['params'] ?? null) || !is_int($request['anytourHotelId'] ?? null)
        || $request['anytourHotelId'] < 1) throw new InvalidArgumentException('Invalid request');
    $identity = anytour_stored_samo_identity($request['identity'] ?? null);
    // This is a fresh CURRENT server read on prepare AND every subsequent read.
    $current = $readLocal($request['params']);
    if (($current['source'] ?? null) !== 'anytour-db-first-results-v1' || ($current['scopeVersion'] ?? null) !== 1
        || ($current['selectionAuthority'] ?? null) !== false || !is_array($current['hotels'] ?? null)
        || !is_string($current['scopeDigest'] ?? null) || !preg_match('/\A[a-f0-9]{64}\z/D', $current['scopeDigest'])) throw new RuntimeException('Local reader unavailable');
    if ($previous !== null && ($previous['scope_digest'] ?? null) !== $current['scopeDigest']) throw new DomainException('Stored offer unavailable');
    $row = null; $hotel = null;
    foreach ($current['hotels'] as $group) {
        if (($group['anytourHotelId'] ?? null) !== $request['anytourHotelId']) continue;
        foreach ($group['offers'] ?? [] as $item) {
            if (($item['provider'] ?? null) !== 'andromeda' || ($item['listing']['identity'] ?? null) === null) continue;
            if (anytour_stored_samo_identity($item['listing']['identity']) !== $identity) continue;
            if ($row !== null) throw new DomainException('Ambiguous stored offer');
            $row = array_replace($item, ['anytourHotelId' => $group['anytourHotelId'], 'offer' => $item['listing']]);
            $hotel = $group['hotel'] ?? null;
        }
    }
    if ($row === null || !is_array($hotel) || ($hotel['catalog'] ?? null) !== 'anytour'
        || ($hotel['id'] ?? null) !== $request['anytourHotelId'] || !is_string($hotel['name'] ?? null)) throw new DomainException('Stored offer unavailable');
    $readPricing ??= 'anytour_stored_samo_pricing';
    $located = anytour_stored_samo_snapshot($row, $directory, $previous['locator'] ?? null, $canonicalAllows, $mappingAllows,
        static function (array $state, array $resolved, array $locator, int $expires) use ($directory, $readPricing, $mappingAllows, $now): array {
            return ['locator' => $locator, 'expires_at' => $expires, 'pricing' => $readPricing($directory, $state, $resolved, $mappingAllows, $now)];
        }, $now);
    $quote = ['state' => 'confirmation_required', 'finalPrice' => null, 'expiresAt' => null];
    $evidence = $located['pricing']; $verified = $evidence['value'] ?? null; $expires = $evidence['expires_at'] ?? null;
    if (is_array($verified) && ($verified['provider'] ?? null) === 'andromeda'
        && ($verified['state'] ?? null) === 'quote_verified' && ($verified['quote_state'] ?? null) === 'verified'
        && ($verified['final_price_verified'] ?? null) === true && ($verified['flight_selection_required'] ?? null) === false
        && ($verified['booking_enabled'] ?? null) === false && is_int($expires) && $expires > $now
        && $expires <= $located['expires_at'] && $expires <= $now + 300
        && ($verified['final_price']['currency'] ?? null) === 'RUB' && is_string($verified['final_price']['amount'] ?? null)
        && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $verified['final_price']['amount'])
        && preg_match('/[1-9]/', $verified['final_price']['amount'])) {
        $quote = ['state' => 'verified', 'finalPrice' => ['amount' => $verified['final_price']['amount'], 'currency' => 'RUB'], 'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $expires)];
    }
    if ($handle === null) {
        foreach ($handles as $key => $entry) if (!is_array($entry) || ($entry['expires_at'] ?? 0) <= $now) unset($handles[$key]);
        while (count($handles) >= 12) unset($handles[array_key_first($handles)]);
        $handle = 'stored_' . bin2hex(random_bytes(32));
        $handles[$handle] = ['request' => $request, 'scope_digest' => $current['scopeDigest'], 'locator' => $located['locator'], 'expires_at' => $located['expires_at']];
    }
    return ['source' => 'andromeda-stored-offer-v1', 'provider' => 'andromeda', 'handle' => $handle,
        'anytourHotelId' => $row['anytourHotelId'], 'scopeDigest' => $current['scopeDigest'], 'identity' => $identity,
        'hotel' => ['id' => $hotel['id'], 'name' => $hotel['name']],
        'tour' => array_intersect_key($row['offer']['tour'], array_flip(['checkin','nights','party','meal','room','placement'])),
        'listingPrice' => ['amount' => $row['price'], 'currency' => $row['currency']], 'quote' => $quote,
        'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', $located['expires_at']), 'selectionEnabled' => false, 'bookingEnabled' => false];
}

function anytour_stored_samo_http_guard(array $server, string $raw): array
{
    if (($server['REQUEST_METHOD'] ?? '') !== 'POST') return [405, 'method_not_allowed'];
    if (($server['HTTP_X_REQUESTED_WITH'] ?? '') !== 'AnyTourSearch3'
        || !in_array($server['HTTP_SEC_FETCH_SITE'] ?? 'same-origin', ['same-origin','none'], true)
        || (isset($server['HTTP_ORIGIN']) && $server['HTTP_ORIGIN'] !== 'https://anytoour.ru')) return [403, 'forbidden'];
    if (strtolower(trim(explode(';', $server['CONTENT_TYPE'] ?? '')[0])) !== 'application/json'
        || strlen($raw) < 2 || strlen($raw) > 16384) return [400, 'invalid_request'];
    return [200, 'ok'];
}

function anytour_stored_samo_http(): never
{
    $out = static function(array $data, int $status): never {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); exit;
    };
    if (($_SERVER['SCRIPT_NAME'] ?? '') !== '/_preview/search3-anex-candidate/api-andromeda-stored-offer-preview.php'
        || is_link(__DIR__ . '/.andromeda-private.php') || !is_file(__DIR__ . '/.andromeda-private.php')) $out(['ok'=>false,'error'=>'not_found'],404);
    $config = require __DIR__ . '/.andromeda-private.php';
    if (!is_array($config) || ($config['enabled'] ?? false) !== true) $out(['ok'=>false,'error'=>'not_found'],404);
    $raw = (string)file_get_contents('php://input', false, null, 0, 16385);
    [$status, $error] = anytour_stored_samo_http_guard($_SERVER, $raw);
    if ($status !== 200) $out(['ok'=>false,'error'=>$error],$status);
    try {
        $request = json_decode($raw, true, 24, JSON_THROW_ON_ERROR);
        if (!is_array($request) || (($request['action'] ?? null) === 'read' && !is_string($request['handle'] ?? null))) throw new InvalidArgumentException();
        $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException();
        require_once __DIR__ . '/api-andromeda-search3-preview.php';
        $app = is_file(__DIR__.'/app/integrations/stored-provider-offer-context.php') ? __DIR__.'/app/integrations' : __DIR__.'/../app/integrations';
        require_once $app . '/stored-provider-offer-context.php';
        require_once $app . '/andromeda-saved-package-runtime.php';
        require_once $root . '/_preview/search3-local-candidate/data/search3-local-results-read-v1.php';
        $pdo = v2_data_db();
        if (!is_string($config['catalog_path'] ?? null)) throw new RuntimeException();
        $directory = dirname($config['catalog_path']) . '/searches';
        session_name('ANYTOUR_ANDROMEDA_SEARCH3');
        ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
        session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/_preview/search3-anex-candidate/']);
        if (!session_start()) throw new RuntimeException();
        try {
            $handles = is_array($_SESSION['andromeda_stored_offers_v1'] ?? null) ? $_SESSION['andromeda_stored_offers_v1'] : [];
            $readLocal = static fn(array $params): array => search3_local_results_build($pdo, $params, new DateTimeImmutable('now', new DateTimeZone('UTC')));
            $canonical = static fn(string $provider,string $digest,int $legacy,int $own): bool => AnyTourProviderIdentityBridgeV1::allowsOffer($pdo,$provider,$digest,$legacy,$own);
            // The country comes from CURRENT server-read canonical scope, never a native ID from the browser.
            $params = ($request['action'] ?? null) === 'read' ? ($handles[$request['handle'] ?? '']['request']['params'] ?? []) : ($request['params'] ?? []);
            $scope = AnyTourSearchScopeV1::fromParams(is_array($params) ? $params : []);
            $country = (int)$scope['params']['countryId'];
            $mapping = static fn(array $offer): bool => anytour_andromeda_search3_mapping_allows($pdo,$country,$offer);
            $result = anytour_stored_samo_request($request,$handles,$directory,$readLocal,$canonical,$mapping,time());
            $_SESSION['andromeda_stored_offers_v1'] = $handles;
        } finally { session_write_close(); }
        $out(['ok'=>true,'data'=>$result],200);
    } catch (JsonException | InvalidArgumentException $e) { $out(['ok'=>false,'error'=>'invalid_request'],400);
    } catch (DomainException $e) { $out(['ok'=>false,'error'=>'stored_offer_unavailable'],409);
    } catch (Throwable $e) { $out(['ok'=>false,'error'=>'stored_offer_unavailable'],502); }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) anytour_stored_samo_http();
