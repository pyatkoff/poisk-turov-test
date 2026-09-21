<?php
declare(strict_types=1);

/**
 * Provider-neutral gate between a CURRENT stored-offer handle and the existing
 * Andromeda package/quote owner. Pure: no supplier, DB or filesystem I/O.
 */
function anytour_stored_quote_gate(array $stored, ?array $quoteAttempt, int $now): array
{
    if (($stored['source'] ?? null) !== 'andromeda-stored-offer-v1'
        || ($stored['provider'] ?? null) !== 'andromeda'
        || !is_string($stored['handle'] ?? null)
        || preg_match('/\Astored_[a-f0-9]{64}\z/D', $stored['handle']) !== 1
        || !is_int($stored['anytourHotelId'] ?? null) || $stored['anytourHotelId'] < 1
        || !is_string($stored['scopeDigest'] ?? null)
        || preg_match('/\A[a-f0-9]{64}\z/D', $stored['scopeDigest']) !== 1
        || !is_string($stored['expiresAt'] ?? null)
        || $now < 1) {
        throw new InvalidArgumentException('STORED_QUOTE_CONTEXT_INVALID');
    }

    $nativeExpires = strtotime($stored['expiresAt']);
    if ($nativeExpires === false) throw new InvalidArgumentException('STORED_QUOTE_CONTEXT_INVALID');

    $quote = $stored['quote'] ?? null;
    if (is_array($quote)
        && ($quote['state'] ?? null) === 'verified'
        && is_array($quote['finalPrice'] ?? null)
        && ($quote['finalPrice']['currency'] ?? null) === 'RUB'
        && is_string($quote['finalPrice']['amount'] ?? null)
        && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $quote['finalPrice']['amount']) === 1
        && preg_match('/[1-9]/', $quote['finalPrice']['amount']) === 1) {
        return [
            'state' => 'final_price_ready',
            'finalPriceReady' => true,
            'finalPrice' => $quote['finalPrice'],
            'supplierCallAllowed' => false,
            'sameCriteria' => true,
        ];
    }

    if ($nativeExpires <= $now) {
        return [
            'state' => 'refresh_required',
            'finalPriceReady' => false,
            'finalPrice' => null,
            'supplierCallAllowed' => false,
            'sameCriteria' => true,
        ];
    }

    if ($quoteAttempt !== null) {
        $status = $quoteAttempt['status'] ?? null;
        if ($status === 'completed' && is_array($quoteAttempt['result'] ?? null)) {
            $result = $quoteAttempt['result'];
            $ready = ($result['state'] ?? null) === 'quote_verified'
                && ($result['quote_state'] ?? null) === 'verified'
                && ($result['final_price_verified'] ?? null) === true
                && ($result['flight_selection_required'] ?? null) === false
                && is_array($result['final_price'] ?? null)
                && ($result['final_price']['currency'] ?? null) === 'RUB';
            return [
                'state' => $ready ? 'final_price_ready' : 'confirmation_required',
                'finalPriceReady' => $ready,
                'finalPrice' => $ready ? $result['final_price'] : null,
                'supplierCallAllowed' => false,
                'sameCriteria' => true,
            ];
        }
        if (in_array($status, ['reserved','unknown'], true)) {
            return [
                'state' => 'attempt_sealed',
                'finalPriceReady' => false,
                'finalPrice' => null,
                'supplierCallAllowed' => false,
                'sameCriteria' => true,
            ];
        }
        throw new RuntimeException('STORED_QUOTE_ATTEMPT_INVALID');
    }

    return [
        'state' => 'actualization_required',
        'finalPriceReady' => false,
        'finalPrice' => null,
        'supplierCallAllowed' => true,
        'sameCriteria' => true,
    ];
}


/**
 * Execute the only state allowed to enter the existing package/quote owner.
 * The browser contributes no native IDs: exact private context comes from the
 * server-side viewer session after CURRENT stored-offer revalidation.
 *
 * $capture is the trusted runtime owner seam; production passes the existing
 * anytour_andromeda_capture_saved_package wrapper, tests pass a local closure.
 */
function anytour_stored_quote_actualize(
    array $stored,
    array $viewer,
    array $config,
    string $directory,
    callable $mappingAllows,
    callable $capture,
    int $now,
    ?array $quoteAttempt = null
): array {
    $gate = anytour_stored_quote_gate($stored, $quoteAttempt, $now);
    if (($gate['state'] ?? null) !== 'actualization_required'
        || ($gate['supplierCallAllowed'] ?? null) !== true) {
        return $gate;
    }

    $context = $viewer['private_context'] ?? null;
    if (!is_array($context)
        || ($context['provider'] ?? null) !== 'andromeda'
        || !is_string($context['search_ref'] ?? null)
        || preg_match('/\A[a-f0-9]{64}\z/D', $context['search_ref']) !== 1
        || !is_string($context['offer_ref'] ?? null)
        || preg_match('/\Aoffer_[a-f0-9]{64}\z/D', $context['offer_ref']) !== 1
        || !is_int($context['generation'] ?? null) || $context['generation'] < 1
        || !is_int($context['page'] ?? null) || $context['page'] < 1
        || ($viewer['expires_at'] ?? 0) <= $now
        || !is_string($config['catalog_path'] ?? null)
        || dirname($config['catalog_path']) . '/searches' !== $directory) {
        throw new DomainException('Stored quote unavailable');
    }

    // Bind browser-safe response back to the same CURRENT server-side context.
    $identity = $stored['identity'] ?? null;
    if (!is_array($identity)
        || !is_string($identity['search_ref_digest'] ?? null)
        || !hash_equals($identity['search_ref_digest'], hash('sha256', $context['search_ref']))
        || !is_string($identity['offer_ref_digest'] ?? null)
        || !hash_equals($identity['offer_ref_digest'], hash('sha256', $context['offer_ref']))) {
        throw new DomainException('Stored quote unavailable');
    }

    $runtimePath = is_file(__DIR__ . '/app/integrations/andromeda-saved-package-runtime.php')
        ? __DIR__ . '/app/integrations/andromeda-saved-package-runtime.php'
        : __DIR__ . '/../app/integrations/andromeda-saved-package-runtime.php';
    $source = sha1_file($runtimePath);
    if (!is_string($source) || preg_match('/\A[a-f0-9]{40}\z/D', $source) !== 1) {
        throw new RuntimeException('Stored quote runtime unavailable');
    }

    $capability = AnyTourAndromedaPrivateRuntimeCapability::fromTrustedConfig($config);
    $transport = new AnyTourAndromedaTransport(false, true);
    $receipt = $capture(
        $directory, $context, $source, $mappingAllows, $transport, true,
        static fn(): int => time(), true, null, $config, $capability
    );
    if (!is_array($receipt)
        || ($receipt['status'] ?? null) !== 'captured'
        || ($receipt['context'] ?? null) !== $context
        || !is_bool($receipt['reused'] ?? null)
        || !is_array($receipt['surcharge'] ?? null)) {
        throw new RuntimeException('Stored quote actualization unavailable');
    }

    return [
        'state' => ($receipt['surcharge']['final_price_verified'] ?? false) === true
            ? 'actualized_verified' : 'actualized_unverified',
        'finalPriceReady' => false,
        'finalPrice' => null,
        'supplierCallAllowed' => false,
        'sameCriteria' => true,
        'reused' => $receipt['reused'],
    ];
}


function anytour_stored_quote_http(): never
{
    $out = static function(array $data, int $status): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    };
    if (($_SERVER['SCRIPT_NAME'] ?? '') !== '/_preview/search3-anex-candidate/api-andromeda-stored-quote-preview.php'
        || is_link(__DIR__ . '/.andromeda-private.php') || !is_file(__DIR__ . '/.andromeda-private.php')) {
        $out(['ok'=>false,'error'=>'not_found'],404);
    }
    $config = require __DIR__ . '/.andromeda-private.php';
    if (!is_array($config) || ($config['enabled'] ?? false) !== true) {
        $out(['ok'=>false,'error'=>'not_found'],404);
    }
    $raw = (string)file_get_contents('php://input', false, null, 0, 4097);
    require_once __DIR__ . '/api-andromeda-stored-offer-preview.php';
    [$status,$error] = anytour_stored_samo_http_guard($_SERVER,$raw);
    if ($status !== 200) $out(['ok'=>false,'error'=>$error],$status);
    try {
        $request=json_decode($raw,true,12,JSON_THROW_ON_ERROR);
        if (!is_array($request) || array_keys($request)!==['action','handle']
            || ($request['action'] ?? null)!=='quote'
            || !is_string($request['handle'] ?? null)
            || preg_match('/\Astored_[a-f0-9]{64}\z/D',$request['handle'])!==1) {
            throw new InvalidArgumentException();
        }
        $root=realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if (!$root || basename($root)!=='anytoour.ru') throw new RuntimeException();
        require_once __DIR__ . '/api-andromeda-search3-preview.php';
        $app=is_file(__DIR__.'/app/integrations/stored-provider-offer-context.php')
            ? __DIR__.'/app/integrations' : __DIR__.'/../app/integrations';
        require_once $app.'/stored-provider-offer-context.php';
        require_once $app.'/andromeda-saved-package-runtime.php';
        require_once $app.'/andromeda-transport.php';
        require_once $root.'/_preview/search3-local-candidate/data/search3-local-results-read-v1.php';
        $pdo=v2_data_db();
        if (!is_string($config['catalog_path'] ?? null)) throw new RuntimeException();
        $directory=dirname($config['catalog_path']).'/searches';

        session_name('ANYTOUR_ANDROMEDA_SEARCH3');
        ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
        session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/_preview/search3-anex-candidate/']);
        if (!session_start()) throw new RuntimeException();
        try {
            $handles=is_array($_SESSION['andromeda_stored_offers_v1'] ?? null)
                ? $_SESSION['andromeda_stored_offers_v1'] : [];
            $handle=$request['handle'];
            $viewer=$handles[$handle] ?? null;
            if (!is_array($viewer) || !is_array($viewer['request']['params'] ?? null)) {
                throw new DomainException('Stored quote unavailable');
            }
            $params=$viewer['request']['params'];
            $scope=AnyTourSearchScopeV1::fromParams($params);
            $country=(int)$scope['params']['countryId'];
            $readLocal=static fn(array $p):array => search3_local_results_build(
                $pdo,$p,new DateTimeImmutable('now',new DateTimeZone('UTC')));
            $canonical=static fn(string $provider,string $digest,int $legacy,int $own):bool =>
                AnyTourProviderIdentityBridgeV1::allowsOffer($pdo,$provider,$digest,$legacy,$own);
            $mapping=static fn(array $offer):bool =>
                anytour_andromeda_search3_mapping_allows($pdo,$country,$offer);

            // CURRENT reread first. It must reproduce the exact private context stored at prepare.
            $stored=anytour_stored_samo_request(
                ['action'=>'read','handle'=>$handle],$handles,$directory,$readLocal,$canonical,$mapping,time());
            $viewer=$handles[$handle] ?? null;
            if (!is_array($viewer)) throw new DomainException('Stored quote unavailable');
            $decision=anytour_stored_quote_actualize(
                $stored,$viewer,$config,$directory,$mapping,
                'anytour_andromeda_capture_saved_package',time());

            if (in_array($decision['state'] ?? null,['actualized_verified','actualized_unverified'],true)) {
                // Read only the persisted evidence after the owner returns; never trust its receipt as money.
                $stored=anytour_stored_samo_request(
                    ['action'=>'read','handle'=>$handle],$handles,$directory,$readLocal,$canonical,$mapping,time());
                $decision=anytour_stored_quote_gate($stored,null,time());
            }
            $_SESSION['andromeda_stored_offers_v1']=$handles;
        } finally { session_write_close(); }

        $out(['ok'=>true,'data'=>[
            'state'=>$decision['state'],
            'finalPriceReady'=>$decision['finalPriceReady'],
            'finalPrice'=>$decision['finalPrice'],
            'sameCriteria'=>$decision['sameCriteria'],
            'anytourHotelId'=>$stored['anytourHotelId'],
            'handle'=>$stored['handle'],
            'bookingEnabled'=>false,
        ]],200);
    } catch (JsonException|InvalidArgumentException $e) {
        $out(['ok'=>false,'error'=>'invalid_request'],400);
    } catch (DomainException $e) {
        $out(['ok'=>false,'error'=>'stored_offer_unavailable'],409);
    } catch (Throwable $e) {
        $out(['ok'=>false,'error'=>'quote_unavailable'],502);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) anytour_stored_quote_http();
