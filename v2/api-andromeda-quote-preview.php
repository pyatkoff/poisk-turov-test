<?php
declare(strict_types=1);

require_once __DIR__ . '/api-andromeda-search3-preview.php';
$quoteApp = is_file(__DIR__.'/app/integrations/andromeda-selected-quote.php')
    ? __DIR__.'/app/integrations' : __DIR__.'/../app/integrations';
require_once $quoteApp . '/andromeda-selected-offer.php';
require_once $quoteApp . '/andromeda-claim-actions.php';
require_once $quoteApp . '/andromeda-selected-quote.php';
require_once $quoteApp . '/andromeda-quote-attempt-state.php';
require_once $quoteApp . '/andromeda-operator-config.php';

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
        $resolved['expires_at'] = min($first['store']['expires_at'], $state['store']['expires_at']);
        $resolved['listing_price_receipt'] = AnyTourAndromedaPriceObservation::resolveServed(
            $listingPrices, $request['listing_price_ref'] ?? null, $resolved,
            $state['store']['created_at'], $state['store']['expires_at'], $now);
        return $resolved;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

/** Keep public quotes and flight choices within the existing retained search lifetime. */
function anytour_andromeda_quote_with_expiry(array $result, array $resolved, ?int $now = null): array
{
    $expires = $resolved['expires_at'] ?? null;
    if (!is_int($expires) || ($now ?? time()) >= $expires) throw new DomainException('offer_expired');
    // This is an absolute search deadline: cache reads never renew it.
    $result['expires_at'] = $expires;
    return $result;
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

function anytour_andromeda_quote_reprice_persist(string $path, array $next, array $expected): array
{
    AnyTourAndromedaFlightRepricingState::validate($next);
    $disk=anytour_andromeda_quote_read($path,3000000,true);
    if (($disk['state'] ?? [])!==$expected || ($disk!==[] && array_keys($disk)!==['state'])
        || strlen(json_encode(['state'=>$next],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))>3000000) {
        throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_CHANGED');
    }
    anytour_andromeda_search3_save($path,['state'=>$next]);
    $written=anytour_andromeda_quote_read($path,3000000);
    if (array_keys($written)!==['state'] || $written['state']!==$next) {
        throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_FAILED');
    }
    return $written['state'];
}

/** Only a fresh initial run can issue this private capability. Never promote a replay. */
function anytour_andromeda_quote_reprice_projection(array $result, array $resolved, array $meta): array
{
    if (!array_key_exists('repricing',$result)) return anytour_andromeda_quote_with_expiry($result,$resolved);
    if ($result['repricing']!==['enabled'=>true,'max_pairs'=>3,'used_pairs'=>0,'remaining_pairs'=>3]) {
        throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
    }
    $envelope=anytour_andromeda_quote_read($meta['prefix'].'-quote-flight-context-v2.json',3000000);
    if (array_keys($envelope)!==['state']) throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
    $state=$envelope['state'];
    AnyTourAndromedaFlightRepricingState::current($state,$meta['context_sha256'],time());
    if ($state['expires_at']!==$resolved['expires_at']) throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
    $executionPath=$meta['prefix'].'-quote-flight-context-v2.lock';
    if (is_link($executionPath)) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
    if (is_file($executionPath)) {
        $marker=file_get_contents($executionPath,false,null,0,33);
        if (!is_string($marker) || strlen($marker)>32) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
        if ($marker!=='') $state['status']='unknown';
    }
    if ($state['status']!=='ready') {
        $result['state']='quote_unknown'; $result['quote_state']='unverified';
        $result['final_price']=null; $result['final_price_verified']=false;
        $result['flight_selection_required']=false; $result['price_observation']=null;
        $result['served_price_observation']=null;
    }
    $result['repricing']=AnyTourAndromedaFlightRepricingState::metadata($state);
    return anytour_andromeda_quote_with_expiry($result,$resolved);
}

/** Called by the action's one existing reservation hook before transport dispatch. */
function anytour_andromeda_quote_reprice_spend(string $path, string $context, string $token,
    string $budgetDirectory, ?int $nowMs=null): int
{
    $envelope=anytour_andromeda_quote_read($path,3000000);
    if (array_keys($envelope)!==['state']) throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
    $state=$envelope['state'];
    AnyTourAndromedaFlightRepricingState::current($state,$context,time());
    $spent=AnyTourAndromedaFlightRepricingState::spend($state,$token,$nowMs ?? (int)floor(microtime(true)*1000));
    anytour_andromeda_quote_reprice_persist($path,$spent['state'],$state);
    anytour_andromeda_search3_budget($budgetDirectory);
    return $spent['wait_ms'];
}

function anytour_andromeda_quote_reprice_actions(array $state, string $path, string $token, array $config): AnyTourAndromedaClaimActions
{
    return new AnyTourAndromedaClaimActions($state['session_sid'],
        static function() use($state,$path,$token,$config): void {
            $wait=anytour_andromeda_quote_reprice_spend($path,$state['context_sha256'],$token,dirname($config['catalog_path']));
            if ($wait>0) usleep($wait*1000);
            AnyTourAndromedaFlightRepricingState::current(
                anytour_andromeda_quote_read($path,3000000)['state'],$state['context_sha256'],time());
        });
}

function anytour_andromeda_quote_reprice_continue(array $resolved, array $meta, array $initialResult,
    array $flightState, array $selection, array $selected, array $config): array
{
    $path=$meta['prefix'].'-quote-flight-context-v2.json';
    $executionPath=$meta['prefix'].'-quote-flight-context-v2.lock';
    if (is_link($executionPath)) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
    $execution=fopen($executionPath,'c+');
    if (!$execution) throw new RuntimeException('ANDROMEDA_QUOTE_LOCK_FAILED');
    if (!flock($execution,LOCK_EX|LOCK_NB)) { fclose($execution); throw new RuntimeException('ANDROMEDA_QUOTE_REPLAY_REFUSED'); }
    $reserved=false; $token=bin2hex(random_bytes(16));
    try {
        $envelope=anytour_andromeda_quote_read($path,3000000);
        if (array_keys($envelope)!==['state']) throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
        $state=$envelope['state'];
        AnyTourAndromedaFlightRepricingState::current($state,$meta['context_sha256'],time());
        if ($state['expires_at']!==$resolved['expires_at']
            || !hash_equals($state['flight_state_sha256'],hash('sha256',json_encode($flightState,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)))) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
        }
        $marker=stream_get_contents($execution,33);
        if (!is_string($marker) || strlen($marker)>32) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_INVALID');
        if ($marker!=='') return anytour_andromeda_quote_reprice_projection($initialResult,$resolved,$meta);
        if ($state['status']!=='ready') return anytour_andromeda_quote_reprice_projection($initialResult,$resolved,$meta);
        $begun=AnyTourAndromedaFlightRepricingState::begin($state,$meta['context_sha256'],$selection,$selected,$token,time());
        if (is_array($begun['replay'])) {
            $result=$begun['replay']; $result['repricing']=AnyTourAndromedaFlightRepricingState::metadata($state);
            return anytour_andromeda_quote_with_expiry($result,$resolved);
        }
        $state=anytour_andromeda_quote_reprice_persist($path,$begun['state'],$state); $reserved=true;
        // This durable marker survives a crash or an unacknowledged final write,
        // even if a completed envelope happened to reach disk before readback failed.
        if (fwrite($execution,$token)!==32 || !fflush($execution)) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_FAILED');
        $actions=anytour_andromeda_quote_reprice_actions($state,$path,$token,$config);
        $calculated=null;
        $result=AnyTourAndromedaSelectedQuote::continueWithFlights(
            $resolved,$state['claim'],$selected,$actions,$state['initial_pricing'],
            static function(array $claim) use(&$calculated): void { $calculated=$claim; });
        if (!is_array($calculated) || count($result['flights'])!==2
            || ($result['flights'][0]['direction'] ?? null)!=='0' || ($result['flights'][1]['direction'] ?? null)!=='1') {
            throw new RuntimeException('ANDROMEDA_SELECTED_FLIGHTS_INVALID', 107);
        }
        $result['flights'][0]['flight_ref']=$selection['outbound_ref'];
        $result['flights'][1]['flight_ref']=$selection['return_ref'];
        $result['verified_at']=time();
        $result=anytour_andromeda_quote_with_expiry($result,$resolved);
        $result['served_price_observation']=AnyTourAndromedaPriceObservation::compareServed(
            $resolved['listing_price_receipt'] ?? null,$result,time());
        $current=anytour_andromeda_quote_read($path,3000000)['state'];
        $completed=AnyTourAndromedaFlightRepricingState::completed($current,$token,$calculated,$result,time());
        $completed=anytour_andromeda_quote_reprice_persist($path,$completed,$current);
        $result['repricing']=AnyTourAndromedaFlightRepricingState::metadata($completed);
        $result=anytour_andromeda_quote_with_expiry($result,$resolved);
        if (!ftruncate($execution,0)) throw new RuntimeException('ANDROMEDA_QUOTE_CHECKPOINT_FAILED');
        return $result;
    } catch (Throwable $error) {
        if (!$reserved) {
            if ($error->getMessage()!=='ANDROMEDA_FLIGHT_REPRICE_BUDGET') throw $error;
            $result=anytour_andromeda_quote_reprice_projection($initialResult,$resolved,$meta);
            return $result+array_diff_key(anytour_andromeda_quote_supplier_failure($error, 'flight_continuation'),['ok'=>true,'error'=>true]);
        }
        try {
            $current=anytour_andromeda_quote_read($path,3000000)['state'];
            anytour_andromeda_quote_reprice_persist($path,AnyTourAndromedaFlightRepricingState::unknown($current,$token),$current);
        } catch (Throwable $ignored) { /* A surviving reserved envelope also seals the context. */ }
        $result=anytour_andromeda_quote_reprice_projection($initialResult,$resolved,$meta);
        return $result+array_diff_key(anytour_andromeda_quote_supplier_failure($error, 'flight_continuation'),['ok'=>true,'error'=>true]);
    } finally { flock($execution,LOCK_UN); fclose($execution); }
}

function anytour_andromeda_quote_supplier(array $config): array
{
    [$operatorLogin, $operatorPassword] = anytour_andromeda_operator_credentials_from_config($config);
    $budgetDirectory = dirname($config['catalog_path']);
    $transport = new AnyTourAndromedaTransport(false, true);
    $client = new AnyTourAndromedaClient(
        static function (string $url, array $options) use ($transport, $budgetDirectory): array {
            anytour_andromeda_search3_budget($budgetDirectory);
            return $transport($url, $options);
        }, true, true, $operatorLogin, $operatorPassword
    );
    $client->login($config['username'], $config['password']);
    $sid = $client->privateSession()['sid'] ?? null;
    if (!is_string($sid)) throw new RuntimeException('ANDROMEDA_LOGIN_REQUIRED');
    return [$client, new AnyTourAndromedaClaimActions($sid,
        static fn() => anytour_andromeda_search3_budget($budgetDirectory))];
}

function anytour_andromeda_quote_run(array $request, PDO $pdo, array $saved, array $config, string $session,
    array $listingPrices = [], ?string &$failurePhase = null): array
{
    $failurePhase = 'quote_resolve';
    $resolved = anytour_andromeda_quote_resolve($request, $pdo, $saved, $config, $session, $listingPrices);
    $failurePhase = 'quote_reserve';
    $meta = anytour_andromeda_quote_meta($resolved, $config);
    $checkpoint = $meta['prefix'] . '-quote-v1.json';
    $flightState = $meta['prefix'] . '-quote-flight-state-v1.json';
    $operationSha256 = hash('sha256', 'andromeda-selected-quote-v1');
    $reserved = anytour_andromeda_quote_reserve(
        $checkpoint, $meta['lock_path'], $meta['context_sha256'], $operationSha256);
    if (is_array($reserved['replay'])) return anytour_andromeda_quote_reprice_projection($reserved['replay'], $resolved, $meta);
    $attempt = $reserved['attempt'];

    try {
        $failurePhase = 'quote_bootstrap';
        [$client, $actions] = anytour_andromeda_quote_supplier($config);
        $repriceClaim=null; $retainedState=null;
        $retain = static function(array $claim, array $options) use ($flightState, $meta, &$repriceClaim, &$retainedState, &$failurePhase): array {
            $failurePhase = 'flight_state';
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
            $repriceClaim=$claim; $retainedState=$built['state'];
            $failurePhase = 'quote_bootstrap';
            return $built['refs'];
        };
        $result = AnyTourAndromedaSelectedQuote::run($resolved, $client, $actions, $retain);
        $failurePhase = 'quote_validate';
        $result = anytour_andromeda_quote_with_expiry($result, $resolved);
        if (is_array($repriceClaim) && is_array($retainedState) && $result['state']==='flight_selection_required') {
            $failurePhase = 'flight_state';
            $privateSession=$client->privateSession();
            $state=AnyTourAndromedaFlightRepricingState::create($meta['context_sha256'],
                hash('sha256',json_encode($retainedState,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),
                $privateSession['sid'] ?? '',$repriceClaim,$result,$resolved['expires_at']);
            $state=anytour_andromeda_quote_reprice_persist($meta['prefix'].'-quote-flight-context-v2.json',$state,[]);
            $result['repricing']=AnyTourAndromedaFlightRepricingState::metadata($state);
            $failurePhase = 'quote_validate';
        }
        $result['served_price_observation'] = AnyTourAndromedaPriceObservation::compareServed(
            $resolved['listing_price_receipt'] ?? null, $result, time());
        $failurePhase = 'quote_checkpoint';
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

    if (array_key_exists('repricing',$initialResult)) {
        if ($initialResult['repricing']!==['enabled'=>true,'max_pairs'=>3,'used_pairs'=>0,'remaining_pairs'=>3]) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
        }
        return anytour_andromeda_quote_reprice_continue($resolved,$meta,$initialResult,
            $flightEnvelope['state'],$selection,$resolvedSelection['selected'],$config);
    }
    if (file_exists($meta['prefix'].'-quote-flight-context-v2.json') || is_link($meta['prefix'].'-quote-flight-context-v2.json')) {
        throw new RuntimeException('ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID');
    }

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
    if (is_array($reserved['replay'])) return anytour_andromeda_quote_with_expiry($reserved['replay'], $resolved);
    $attempt = $reserved['attempt'];

    try {
        [, $actions] = anytour_andromeda_quote_supplier($config);
        $result = AnyTourAndromedaSelectedQuote::continueWithFlights(
            $resolved, $resolvedSelection['claim'], $resolvedSelection['selected'], $actions);
        // The calc has already verified the selected supplier UIDs. Preserve the
        // accepted opaque pair in its public result and completed replay.
        if (count($result['flights']) !== 2
            || ($result['flights'][0]['direction'] ?? null) !== '0'
            || ($result['flights'][1]['direction'] ?? null) !== '1') {
            throw new RuntimeException('ANDROMEDA_SELECTED_FLIGHTS_INVALID', 107);
        }
        $result['flights'][0]['flight_ref'] = $selection['outbound_ref'];
        $result['flights'][1]['flight_ref'] = $selection['return_ref'];
        $result = anytour_andromeda_quote_with_expiry($result, $resolved);
        $result['served_price_observation'] = AnyTourAndromedaPriceObservation::compareServed(
            $resolved['listing_price_receipt'] ?? null, $result, time());
        anytour_andromeda_quote_finish($continuationCheckpoint, $meta['lock_path'], $attempt, $result);
        return $result;
    } catch (Throwable $error) {
        anytour_andromeda_quote_unknown($continuationCheckpoint, $meta['lock_path'], $attempt);
        throw $error;
    }
}

/** Map private exception states to one fixed browser-safe category; never expose exception details. */
function anytour_andromeda_quote_failure_category(Throwable $error): string
{
    static $map = [
        'ANDROMEDA_TRANSPORT_ERROR' => 'supplier_transport',
        'ANDROMEDA_NETWORK_TRANSPORT_FAILURE' => 'supplier_transport',
        'ANDROMEDA_CURL_REQUIRED' => 'supplier_transport',
        'ANDROMEDA_HTTP_ERROR' => 'supplier_http',
        'ANDROMEDA_SUPPLIER_ERROR' => 'supplier_rejected',
        'ANDROMEDA_INVALID_RESPONSE' => 'supplier_response',
        'ANDROMEDA_INVALID_CLAIM_RESPONSE' => 'supplier_response',
        'ANDROMEDA_INVALID_PACKAGE_RESPONSE' => 'supplier_response',
        'ANDROMEDA_RESPONSE_TOO_LARGE' => 'supplier_response',
        'ANDROMEDA_SECRET_ECHO' => 'supplier_response',
        'ANDROMEDA_LOGIN_REQUIRED' => 'supplier_auth',
        'ANDROMEDA_CREDENTIALS_REQUIRED' => 'supplier_auth',
        'ANDROMEDA_CLAIM_SESSION_INVALID' => 'supplier_auth',
        'ANDROMEDA_OPERATOR_CREDENTIALS_PAIR_REQUIRED' => 'supplier_auth',
        'ANDROMEDA_OPERATOR_CREDENTIALS_INVALID' => 'supplier_auth',
        'ANDROMEDA_QUOTE_CONTEXT_MISMATCH' => 'quote_state',
        'ANDROMEDA_SELECTION_CONTEXT_MISMATCH' => 'quote_state',
        'ANDROMEDA_SELECTION_MAPPING_UNAVAILABLE' => 'quote_state',
        'ANDROMEDA_QUOTE_ATTEMPT_INVALID' => 'quote_state',
        'ANDROMEDA_QUOTE_RESULT_INVALID' => 'quote_state',
        'ANDROMEDA_QUOTE_MONEY_INVALID' => 'quote_state',
        'ANDROMEDA_QUOTE_PRIVATE_STATE' => 'quote_state',
        'ANDROMEDA_QUOTE_PROVENANCE_INVALID' => 'quote_state',
        'ANDROMEDA_QUOTE_REPLAY_REFUSED' => 'quote_state',
        'ANDROMEDA_FLIGHT_REPRICE_BUDGET' => 'quote_state',
        'ANDROMEDA_FLIGHT_REPRICE_STATE_INVALID' => 'quote_state',
        'ANDROMEDA_FLIGHT_REPRICE_CURRENCY' => 'quote_state',
        'ANDROMEDA_QUOTE_CHECKPOINT_INVALID' => 'quote_state',
        'ANDROMEDA_QUOTE_CHECKPOINT_CHANGED' => 'quote_state',
        'ANDROMEDA_QUOTE_CHECKPOINT_FAILED' => 'quote_state',
        'ANDROMEDA_QUOTE_LOCK_FAILED' => 'quote_state',
        'ANDROMEDA_QUOTE_NOT_OFFER' => 'quote_state',
        'ANDROMEDA_FLIGHT_STATE_CHANGED' => 'quote_state',
        'ANDROMEDA_FLIGHT_STATE_FAILED' => 'quote_state',
        'ANDROMEDA_FLIGHT_STATE_INVALID' => 'quote_state',
        'ANDROMEDA_FLIGHT_SELECTION_INVALID' => 'quote_state',
        'ANDROMEDA_FLIGHT_UID_INVALID' => 'quote_state',
        'ANDROMEDA_FLIGHT_OPTIONS_INVALID' => 'quote_state',
        'ANDROMEDA_FLIGHT_REF_INVALID' => 'quote_state',
        'ANDROMEDA_FLIGHT_REFS_INVALID' => 'quote_state',
        'ANDROMEDA_FLIGHT_CONTEXT_INVALID' => 'quote_state',
        'ANDROMEDA_FLIGHT_ALREADY_SELECTED' => 'quote_state',
        'ANDROMEDA_SELECTED_FLIGHTS_INVALID' => 'quote_state',
        'ANDROMEDA_FINAL_PRICE_MISSING' => 'quote_state',
        'ANDROMEDA_CLAIM_SHAPE_INVALID' => 'quote_state',
        'ANDROMEDA_CLAIM_TOO_LARGE' => 'quote_state',
        'ANDROMEDA_CLAIM_REQUEST_BUDGET' => 'quote_state',
        'ANDROMEDA_CLAIM_ACTION_NOT_ALLOWED' => 'quote_state',
        'ANDROMEDA_PACKAGE_DISABLED' => 'quote_state',
        'ANDROMEDA_PACKAGE_REPLAY_REFUSED' => 'quote_state',
        'ANDROMEDA_INVALID_PACKAGE_ID' => 'quote_state',
    ];
    return $map[$error->getMessage()] ?? 'internal';
}

function anytour_andromeda_quote_supplier_failure(Throwable $error, ?string $failurePhase = null): array
{
    $response = [
        'ok' => false,
        'error' => 'supplier_unavailable',
        'failure_category' => anytour_andromeda_quote_failure_category($error),
    ];
    // Diagnostic only: neither arbitrary exception text nor private state is a phase.
    if (in_array($failurePhase, ['request', 'database', 'catalog', 'criteria', 'quote_resolve',
        'quote_reserve', 'quote_bootstrap', 'flight_state', 'quote_validate', 'quote_checkpoint',
        'flight_continuation'], true)) {
        $response['failure_phase'] = $failurePhase;
    }
    // These categories are reachable only through the exact fixed-message map
    // above. Its five supplier_response reasons expose no raw exception details.
    if (in_array($response['failure_category'], ['quote_state', 'supplier_response'], true)) {
        $response['failure_reason'] = $error->getMessage();
    }
    // Fixed local predicate labels only; exception codes and private causes stay private.
    static $failureDetails = [
        101 => 'selected_pair_missing',
        102 => 'existing_direction_duplicate',
        103 => 'existing_uid_invalid',
        104 => 'returned_direction_invalid',
        105 => 'selected_directions_mismatch',
        106 => 'selected_uid_mismatch',
        107 => 'public_pair_invalid',
    ];
    $detailCode = $error->getCode();
    if (get_class($error) === RuntimeException::class
        && $error->getMessage() === 'ANDROMEDA_SELECTED_FLIGHTS_INVALID'
        && is_int($detailCode) && array_key_exists($detailCode, $failureDetails)) {
        $response['failure_detail'] = $failureDetails[$detailCode];
    }
    static $packageFailureDetails = [
        201 => 'package_document_invalid',
        202 => 'package_document_layout_invalid',
        203 => 'package_document_item_invalid',
        204 => 'package_catalog_key_invalid',
        205 => 'package_catalog_key_empty',
    ];
    if (get_class($error) === RuntimeException::class
        && $error->getMessage() === 'ANDROMEDA_INVALID_PACKAGE_RESPONSE'
        && is_int($detailCode) && array_key_exists($detailCode, $packageFailureDetails)) {
        $response['failure_detail'] = $packageFailureDetails[$detailCode];
    }
    if (method_exists($error, 'diagnosticFacts')) {
        $facts = $error->diagnosticFacts();
        $action = is_array($facts) ? ($facts['action'] ?? null) : null;
        if (is_string($action) && in_array($action, ['broninit', 'get_flights', 'changeservice', 'calc'], true)) {
            $response['failure_stage'] = $action;
        }
        $code = is_array($facts) ? ($facts['code'] ?? null) : null;
        if (is_string($code) && preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D', $code) === 1) {
            $response['supplier_code'] = $code;
        }
    }
    return $response;
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

    $failurePhase = 'request';
    try {
        $request = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($request)
            || !in_array($request['action'] ?? null, ['quote', 'quote_select_flights'], true)) {
            throw new InvalidArgumentException();
        }
        $failurePhase = 'database';
        $root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException();
        require_once $root . (is_file($root.'/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
        $pdo = v2_data_db();
        $failurePhase = 'catalog';
        $saved = anytour_andromeda_search3_catalog($config, $request);
        $saved['excluded_operator_ids'] = $config['excluded_operator_ids'] ?? [];
        $failurePhase = 'criteria';
        anytour_andromeda_search3_params($request, $pdo, $saved);
        if ($request['action'] === 'quote_select_flights') {
            $failurePhase = 'flight_continuation';
            $data = anytour_andromeda_quote_continue($request, $pdo, $saved, $config, $session, $listingPrices);
        } else {
            $data = anytour_andromeda_quote_run($request, $pdo, $saved, $config, $session, $listingPrices, $failurePhase);
        }
        anytour_anex_search3_out(['ok'=>true,'data'=>$data],200);
    } catch (OverflowException $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'monthly_quota_exhausted'],429);
    } catch (DomainException $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'quote_not_available'],422);
    } catch (InvalidArgumentException $e) { anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    } catch (Throwable $e) { anytour_anex_search3_out(anytour_andromeda_quote_supplier_failure($e, $failurePhase),502); }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) anytour_andromeda_quote_http();
