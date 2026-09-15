<?php
declare(strict_types=1);

require_once __DIR__ . '/api-andromeda-search3-preview.php';
$quoteApp = is_file(__DIR__.'/app/integrations/andromeda-selected-quote.php')
    ? __DIR__.'/app/integrations' : __DIR__.'/../app/integrations';
require_once $quoteApp . '/andromeda-selected-offer.php';
require_once $quoteApp . '/andromeda-claim-actions.php';
require_once $quoteApp . '/andromeda-selected-quote.php';
require_once $quoteApp . '/andromeda-quote-attempt-state.php';

/** Resolve a retained offer privately, under the same search/session authority as offer_detail. */
function anytour_andromeda_quote_resolve(array $request, PDO $pdo, array $saved, array $config, string $session, array $listingPrices = []): array
{
    $criteria = anytour_andromeda_search3_params($request, $pdo, $saved);
    $number = $criteria['PAGE'];
    $base = $criteria; unset($base['PAGE']);
    $ref = hash('sha256', 'paged-v1' . $session . json_encode($base));
    $context = $request['offer_context'] ?? [];
    if (($context['provider'] ?? null) !== 'andromeda' || ($context['search_ref'] ?? null) !== $ref
        || ($context['page'] ?? null) !== $number || !is_int($context['generation'] ?? null)
        || !is_string($context['offer_ref'] ?? null)) throw new InvalidArgumentException();

    $directory = dirname($config['catalog_path']) . '/searches';
    $firstPath = $directory . '/' . $ref . '-1.json';
    if (!is_file($firstPath) || is_link($firstPath)) throw new DomainException('offer_expired');
    $lock = fopen($directory . '/' . $ref . '.lock', 'c');
    if (!$lock || !flock($lock, LOCK_SH)) throw new RuntimeException();
    try {
        $first = json_decode(file_get_contents($firstPath), true, 32, JSON_THROW_ON_ERROR);
        $now = time();
        if (!in_array($first['status'] ?? null, ['complete', 'partial'], true)
            || $now >= ($first['store']['expires_at'] ?? 0)) throw new DomainException('offer_expired');
        $path = $number === 1 ? $firstPath : $directory . '/' . $ref . '-' . $first['store']['created_at'] . '-' . $number . '.json';
        if (!is_file($path) || is_link($path)) throw new DomainException('offer_expired');
        $state = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!in_array($state['status'] ?? null, ['complete', 'partial'], true)
            || ($state['store']['snapshot']['page'] ?? null) !== $number) throw new DomainException('offer_expired');
        $allows = static fn(array $offer): bool => anytour_andromeda_search3_mapping_allows(
            $pdo, (int)$request['params']['countryId'], $offer);
        $resolved = AnyTourAndromedaSelectedOffer::resolve(
            new AnyTourAndromedaOfferStore($state['store'], true), $context, $allows, $now);
        $resolved['listing_price_receipt'] = AnyTourAndromedaPriceObservation::resolveServed(
            $listingPrices, $request['listing_price_ref'] ?? null, $resolved,
            $state['store']['created_at'], $state['store']['expires_at'], $now);
        return $resolved;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function anytour_andromeda_quote_meta(array $resolved, array $config): array
{
    $context = $resolved['context'] ?? [];
    $ref = $context['search_ref'] ?? null;
    $generation = $context['generation'] ?? null;
    $page = $context['page'] ?? null;
    $offerRef = $context['offer_ref'] ?? null;
    if (!is_string($ref) || preg_match('/^[a-f0-9]{64}$/D', $ref) !== 1
        || !is_int($generation) || $generation < 1 || !is_int($page) || $page < 1
        || !is_string($offerRef) || preg_match('/^offer_[a-f0-9]{64}$/D', $offerRef) !== 1
        || !is_string($resolved['criteria_sha256'] ?? null)
        || !is_string($resolved['supplier_offer_sha256'] ?? null)) {
        throw new RuntimeException('ANDROMEDA_QUOTE_CONTEXT_MISMATCH');
    }
    $directory = dirname($config['catalog_path']) . '/searches';
    if (!is_dir($directory) || is_link($directory)) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
    $prefix = $directory . '/' . $ref . '-g' . $generation . '-p' . $page . '-' . $offerRef;
    $lockPath = $directory . '/' . $ref . '.lock';
    if (is_link($lockPath)) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
    return [
        'context_sha256' => hash('sha256', json_encode([
            'context' => $context,
            'criteria_sha256' => $resolved['criteria_sha256'],
            'supplier_offer_sha256' => $resolved['supplier_offer_sha256'],
        ], JSON_THROW_ON_ERROR)),
        'prefix' => $prefix,
        'lock_path' => $lockPath,
    ];
}

function anytour_andromeda_quote_read(string $path, int $maxBytes, bool $optional = false): array
{
    if (is_link($path)) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
    if (!file_exists($path) && $optional) return [];
    if (!is_file($path) || filesize($path) > $maxBytes) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
    $data = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
    return $data;
}

function anytour_andromeda_quote_persist(string $checkpoint, array $next, array $expected): array
{
    $disk = anytour_andromeda_quote_read($checkpoint, 131072, true);
    if (($disk['state'] ?? []) !== $expected
        || (file_exists($checkpoint) && array_keys($disk) !== ['state'])) {
        throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_CHANGED');
    }
    anytour_andromeda_search3_save($checkpoint, ['state' => $next]);
    $written = anytour_andromeda_quote_read($checkpoint, 131072);
    if (array_keys($written) !== ['state'] || ($written['state'] ?? null) !== $next) {
        throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_FAILED');
    }
    return $written['state'];
}

/** Reserve one supplier-side operation before any login/package/change/calc calls. */
function anytour_andromeda_quote_reserve(string $checkpoint, string $lockPath,
    string $contextSha256, string $operationSha256): array
{
    if (is_link($checkpoint)) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
    $lock = fopen($lockPath, 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('ANDROMEDA_QUOTE_LOCK_FAILED');
    try {
        $envelope = anytour_andromeda_quote_read($checkpoint, 131072, true);
        if ($envelope !== [] && array_keys($envelope) !== ['state']) {
            throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
        }
        $attempt = $envelope['state'] ?? [];
        if ($attempt !== []) {
            return ['replay' => AnyTourAndromedaQuoteAttemptState::replay(
                $attempt, $contextSha256, $operationSha256), 'attempt' => $attempt];
        }
        $attempt = anytour_andromeda_quote_persist($checkpoint,
            AnyTourAndromedaQuoteAttemptState::reserve($contextSha256, $operationSha256), []);
        return ['replay' => null, 'attempt' => $attempt];
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function anytour_andromeda_quote_finish(string $checkpoint, string $lockPath, array $attempt, array $result): array
{
    $lock = fopen($lockPath, 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('ANDROMEDA_QUOTE_LOCK_FAILED');
    try {
        return anytour_andromeda_quote_persist($checkpoint,
            AnyTourAndromedaQuoteAttemptState::completed($attempt, $result), $attempt);
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function anytour_andromeda_quote_unknown(string $checkpoint, string $lockPath, array $attempt): void
{
    try {
        $lock = fopen($lockPath, 'c');
        if ($lock && flock($lock, LOCK_EX)) {
            try {
                $disk = anytour_andromeda_quote_read($checkpoint, 131072);
                if (($disk['state'] ?? null) === $attempt && ($attempt['status'] ?? null) === 'reserved') {
                    anytour_andromeda_quote_persist($checkpoint,
                        AnyTourAndromedaQuoteAttemptState::unknown($attempt), $attempt);
                }
            } finally { flock($lock, LOCK_UN); fclose($lock); }
        }
    } catch (Throwable $ignored) {
        // A surviving reserved checkpoint is also sealed against semantic replay.
    }
}

function anytour_andromeda_quote_supplier(array $config): array
{
    $budgetDirectory = dirname($config['catalog_path']);
    $transport = new AnyTourAndromedaTransport(false, true);
    $client = new AnyTourAndromedaClient(
        static function (string $url, array $options) use ($transport, $budgetDirectory): array {
            anytour_andromeda_search3_budget($budgetDirectory);
            return $transport($url, $options);
        }, true, true
    );
    $client->login($config['username'], $config['password']);
    $sid = $client->privateSession()['sid'] ?? null;
    if (!is_string($sid)) throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');
    return [$client, new AnyTourAndromedaClaimActions($sid,
        static fn() => anytour_andromeda_search3_budget($budgetDirectory))];
}

function anytour_andromeda_quote_run(array $request, PDO $pdo, array $saved, array $config, string $session, array $listingPrices = []): array
{
    $resolved = anytour_andromeda_quote_resolve($request, $pdo, $saved, $config, $session, $listingPrices);
    $meta = anytour_andromeda_quote_meta($resolved, $config);
    $checkpoint = $meta['prefix'] . '-quote-v1.json';
    $flightState = $meta['prefix'] . '-quote-flight-state-v1.json';
    $operationSha256 = hash('sha256', 'andromeda-selected-quote-v1');
    $reserved = anytour_andromeda_quote_reserve(
        $checkpoint, $meta['lock_path'], $meta['context_sha256'], $operationSha256);
    if (is_array($reserved['replay'])) return $reserved['replay'];
    $attempt = $reserved['attempt'];

    try {
        [$client, $actions] = anytour_andromeda_quote_supplier($config);
        $retain = static function(array $claim, array $options) use ($flightState, $meta): array {
            if (file_exists($flightState) || is_link($flightState)) {
                throw new RuntimeException('ANDROMEDA_FLIGHT_STATE_CHANGED');
            }
            $built = AnyTourAndromedaFlightSelection::buildState(
                $claim, $options, $meta['context_sha256']);
            anytour_andromeda_search3_save($flightState, ['state' => $built['state']]);
            $written = anytour_andromeda_quote_read($flightState, 3000000);
            if (array_keys($written) !== ['state'] || ($written['state'] ?? null) !== $built['state']) {
                throw new RuntimeException('ANDROMEDA_FLIGHT_STATE_FAILED');
            }
            return $built['refs'];
        };
        $result = AnyTourAndromedaSelectedQuote::run($resolved, $client, $actions, $retain);
        $result['served_price_observation'] = AnyTourAndromedaPriceObservation::compareServed(
            $resolved['listing_price_receipt'] ?? null, $result, time());
        anytour_andromeda_quote_finish($checkpoint, $meta['lock_path'], $attempt, $result);
        return $result;
    } catch (Throwable $error) {
        anytour_andromeda_quote_unknown($checkpoint, $meta['lock_path'], $attempt);
        throw $error;
    }
}

function anytour_andromeda_quote_continue(array $request, PDO $pdo, array $saved, array $config,
    string $session, array $listingPrices = []): array
{
    $resolved = anytour_andromeda_quote_resolve($request, $pdo, $saved, $config, $session, $listingPrices);
    $meta = anytour_andromeda_quote_meta($resolved, $config);
    $initialCheckpoint = $meta['prefix'] . '-quote-v1.json';
    $flightStatePath = $meta['prefix'] . '-quote-flight-state-v1.json';
    $initial = anytour_andromeda_quote_read($initialCheckpoint, 131072);
    if (array_keys($initial) !== ['state']) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
    $initialResult = AnyTourAndromedaQuoteAttemptState::replay(
        $initial['state'], $meta['context_sha256'], hash('sha256', 'andromeda-selected-quote-v1'));
    if (($initialResult['state'] ?? null) !== 'flight_selection_required'
        || ($initialResult['final_price_verified'] ?? null) !== false) {
        throw new DomainException('flight_selection_not_available');
    }

    $flightEnvelope = anytour_andromeda_quote_read($flightStatePath, 3000000);
    if (array_keys($flightEnvelope) !== ['state'] || !is_array($flightEnvelope['state'] ?? null)) {
        throw new RuntimeException('ANDROMEDA_FLIGHT_STATE_INVALID');
    }
    $selection = $request['flight_selection'] ?? null;
    if (!is_array($selection)) throw new InvalidArgumentException('ANDROMEDA_FLIGHT_SELECTION_INVALID');
    $resolvedSelection = AnyTourAndromedaFlightSelection::select(
        $flightEnvelope['state'], $meta['context_sha256'], $selection);

    // One continuation attempt per initial quote. A different later selection cannot create a new supplier operation.
    $continuationCheckpoint = $meta['prefix'] . '-quote-flight-v1.json';
    $continuationContext = hash('sha256', json_encode([
        'quote_context_sha256' => $meta['context_sha256'],
        'provider' => 'andromeda',
        'outbound_ref' => $selection['outbound_ref'],
        'return_ref' => $selection['return_ref'],
    ], JSON_THROW_ON_ERROR));
    $operationSha256 = hash('sha256', 'andromeda-selected-quote-flight-selection-v1');
    $reserved = anytour_andromeda_quote_reserve(
        $continuationCheckpoint, $meta['lock_path'], $continuationContext, $operationSha256);
    if (is_array($reserved['replay'])) return $reserved['replay'];
    $attempt = $reserved['attempt'];

    try {
        [, $actions] = anytour_andromeda_quote_supplier($config);
        $result = AnyTourAndromedaSelectedQuote::continueWithFlights(
            $resolved, $resolvedSelection['claim'], $resolvedSelection['selected'], $actions);
        $result['served_price_observation'] = AnyTourAndromedaPriceObservation::compareServed(
            $resolved['listing_price_receipt'] ?? null, $result, time());
        anytour_andromeda_quote_finish($continuationCheckpoint, $meta['lock_path'], $attempt, $result);
        return $result;
    } catch (Throwable $error) {
        anytour_andromeda_quote_unknown($continuationCheckpoint, $meta['lock_path'], $attempt);
        throw $error;
    }
}

function anytour_andromeda_quote_http(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    if (!is_file(__DIR__.'/.andromeda-private.php')) anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    $config = require __DIR__.'/.andromeda-private.php';
    if (($config['enabled'] ?? false) !== true) anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') anytour_anex_search3_out(['ok'=>false,'error'=>'method_not_allowed'],405);
    if (!in_array($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin', ['same-origin','none'], true)) anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'AnyTourSearch3'
        || (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== 'https://anytoour.ru')) {
        anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
    }
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    }
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (strlen($raw) > 16384) anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);

    session_name('ANYTOUR_ANDROMEDA_SEARCH3');
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/_preview/search3-anex-candidate/']);
    if (!session_start()) anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],503);
    $session = session_id();
    $listingPrices = is_array($_SESSION['andromeda_listing_prices_v1'] ?? null)
        ? $_SESSION['andromeda_listing_prices_v1'] : [];
    session_write_close();

    try {
        $request = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($request)
            || !in_array($request['action'] ?? null, ['quote', 'quote_select_flights'], true)) {
            throw new InvalidArgumentException();
        }
        $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException();
        require_once $root . (is_file($root.'/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
        $pdo = v2_data_db();
        $saved = anytour_andromeda_search3_catalog($config, $request);
        $saved['excluded_operator_ids'] = $config['excluded_operator_ids'] ?? [];
        anytour_andromeda_search3_params($request, $pdo, $saved);
        $data = ($request['action'] === 'quote_select_flights')
            ? anytour_andromeda_quote_continue($request, $pdo, $saved, $config, $session, $listingPrices)
            : anytour_andromeda_quote_run($request, $pdo, $saved, $config, $session, $listingPrices);
        anytour_anex_search3_out(['ok'=>true,'data'=>$data],200);
    } catch (OverflowException $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'monthly_quota_exhausted'],429);
    } catch (DomainException $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'quote_not_available'],422);
    } catch (InvalidArgumentException $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    } catch (Throwable $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],502); }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) anytour_andromeda_quote_http();
