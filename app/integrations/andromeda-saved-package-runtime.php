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
    ?callable $clock = null, bool $withSurcharge = false, ?callable $flightRequest = null): array
{
    if (!$enabled || PHP_SAPI !== 'cli') throw new RuntimeException('ANDROMEDA_PACKAGE_DISABLED');
    require_once __DIR__ . '/andromeda-package-capture.php';
    require_once __DIR__ . '/andromeda-package-attempt-state.php';
    if ($withSurcharge) {
        require_once __DIR__ . '/andromeda-claim-actions.php';
        require_once __DIR__ . '/andromeda-search-surcharge.php';
        require_once __DIR__ . '/andromeda-selected-quote.php';
    }
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
        $stem = $directory . '/' . $ref . '-' . $created . '-' . $page . '-' . $offerRef;
        $path = $stem . '-package.json';
        $attemptPath = $stem . '-package-attempt-v2.json';
        $envelope = $read($path, true);
        if (file_exists($path) && (($envelope['source'] ?? null) !== $source
            || !is_array($envelope['record'] ?? null) || $envelope['record'] === [])) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_INVALID');
        }
        $attemptEnvelope = $read($attemptPath, true);
        if (file_exists($attemptPath) && (($attemptEnvelope['source'] ?? null) !== $source
            || !is_array($attemptEnvelope['state'] ?? null) || $attemptEnvelope['state'] === [])) {
            throw new RuntimeException('ANDROMEDA_PACKAGE_CHECKPOINT_INVALID');
        }
        $record = $envelope['record'] ?? [];
        $attemptState = $attemptEnvelope['state'] ?? [];
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
        $persistAttempt = static function(array $next, array $expected) use ($read, $attemptPath, $source): array {
            $disk = $read($attemptPath, true);
            if (($disk['state'] ?? []) !== $expected
                || (file_exists($attemptPath) && (($disk['source'] ?? null) !== $source || ($disk['state'] ?? []) === []))) {
                throw new RuntimeException('ANDROMEDA_PACKAGE_ATTEMPT_CHECKPOINT_CHANGED');
            }
            anytour_andromeda_search3_save($attemptPath, ['source' => $source, 'state' => $next]);
            $written = $read($attemptPath);
            if (($written['source'] ?? null) !== $source || ($written['state'] ?? null) !== $next) {
                throw new RuntimeException('ANDROMEDA_PACKAGE_ATTEMPT_CHECKPOINT_FAILED');
            }
            return $written['state'];
        };
        $capture = new AnyTourAndromedaPackageCapture($record, $persist, $mappingAllows, true, $clock);
        $reused = $record !== [];
        if ($reused) {
            // Captured is read locally; reserved/unknown/stale never reissue a call.
            $result = $capture->read($store, $context);
        } else {
            // This package only records v2 provenance for a first attempt. A pre-existing
            // sidecar is never consumed as permission to repeat a supplier operation.
            if ($attemptState !== []) throw new RuntimeException('ANDROMEDA_PACKAGE_REPLAY_REFUSED');
            $resolved = AnyTourAndromedaSelectedOffer::resolve($store, $context, $mappingAllows, $clock());
            $auth = $read($directory . '/' . $ref . '-auth.json');
            if (($auth['created_at'] ?? null) !== $created) throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
            $contextSha256 = hash('sha256', json_encode($resolved['context'], JSON_THROW_ON_ERROR));
            $operationSha256 = hash('sha256', 'andromeda-package-refresh-v2|' . $source);
            $attemptState = AnyTourAndromedaPackageAttemptState::reserveFirst(
                $resolved['supplier_offer_id'], $contextSha256, $operationSha256);
            $attemptState = $persistAttempt($attemptState, []);
            $attempted = false;
            $client = new AnyTourAndromedaClient(static function($url, $options) use (
                &$attempted, $transport, $directory, &$attemptState, $persistAttempt
            ) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                if ($attempted || ($query['action'] ?? null) !== 'broninit') {
                    throw new RuntimeException('ANDROMEDA_PACKAGE_REPLAY_REFUSED');
                }
                $attempted = true;
                anytour_andromeda_search3_budget(dirname($directory));
                try {
                    return $transport($url, $options);
                } catch (Throwable $error) {
                    if (($attemptState['status'] ?? null) === 'reserved') {
                        $next = AnyTourAndromedaPackageAttemptState::failed($attemptState, $error);
                        $attemptState = $persistAttempt($next, $attemptState);
                    }
                    throw $error;
                }
            }, true, true);
            $client->restorePrivateSession($auth['session'] ?? []);
            try {
                $result = $capture->capture($store, $client, $context);
            } catch (Throwable $error) {
                // HTTP/supplier/schema/client failures occur after the transport wrapper.
                // If no typed transport failure was recorded, seal them unclassified.
                if (($attemptState['status'] ?? null) === 'reserved') {
                    $next = AnyTourAndromedaPackageAttemptState::failed($attemptState, $error);
                    $attemptState = $persistAttempt($next, $attemptState);
                }
                throw $error;
            }
            if (($attemptState['status'] ?? null) !== 'reserved') {
                throw new RuntimeException('ANDROMEDA_PACKAGE_ATTEMPT_INVALID');
            }
            $attemptState = $persistAttempt(
                AnyTourAndromedaPackageAttemptState::succeeded($attemptState), $attemptState);
        }
        // Raw claim stays in the private checkpoint. Only receipt metadata exits.
        $receipt = ['status' => 'captured', 'source' => $source, 'reused' => $reused,
            'context' => $result['context'], 'package_sha256' => $result['package_sha256'],
            'identity_verified' => false, 'quote_verified' => false, 'selection_enabled' => false];
        if ($withSurcharge) {
            $receipt['surcharge'] = anytour_andromeda_saved_package_surcharge(
                $directory, $stem . '-surcharge-v1.json', $source, $result, $storeState,
                $created, $context, $mappingAllows, $read, $clock, $flightRequest);
        }
        return $receipt;
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
    ?callable $transport = null, ?callable $clock = null, bool $withSurcharge = false,
    ?callable $flightRequest = null): array
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
        $context, $source, $allows, $transport, true, $clock, $withSurcharge, $flightRequest);
}

/** Internal to the opt-in capture owner; its existing exclusive search lock is held. */
function anytour_andromeda_saved_package_surcharge(string $directory, string $path, string $source,
    array $package, array $storeState, int $created, array $context, callable $mappingAllows,
    callable $read, callable $clock, ?callable $flightRequest): array
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('ANDROMEDA_PACKAGE_DISABLED');
    $store = new AnyTourAndromedaOfferStore($storeState, true);
    $now = $clock();
    $resolved = AnyTourAndromedaSelectedOffer::resolve($store, $context, $mappingAllows, $now);
    $disk = $read($path, true);
    if (file_exists($path)) {
        // Every existing outcome, including malformed/reserved/unknown, forbids another call.
        $fact = anytour_andromeda_saved_surcharge_public($disk, $resolved, $storeState, $created, $now);
        $verified = anytour_andromeda_saved_verified_quote_private(
            $disk, $resolved, $storeState, $created, $now
        );
        return [
            'status' => ($fact !== null || $verified !== null) ? 'complete' : 'unavailable',
            'reused' => true,
            'fact' => $fact,
            'final_price_verified' => $verified !== null,
        ];
    }
    $auth = $read($directory . '/' . $context['search_ref'] . '-auth.json');
    if (($auth['created_at'] ?? null) !== $created) throw new RuntimeException('ANDROMEDA_PACKAGE_CONTEXT_MISMATCH');
    // Reuse existing session validation, without login or a new credential reader.
    $sessionCheck = new AnyTourAndromedaClient(static function() { throw new LogicException('NO_NETWORK'); }, true);
    $sessionCheck->restorePrivateSession($auth['session'] ?? []);
    $reserved = ['version' => 1, 'status' => 'reserved', 'source' => $source,
        'implementation_sha256' => hash_file('sha256', __FILE__),
        'context' => $resolved['context'], 'criteria_sha256' => $resolved['criteria_sha256'],
        'supplier_offer_sha256' => $resolved['supplier_offer_sha256'],
        'package_sha256' => $package['package_sha256'], 'snapshot_created_at' => $created,
        'observed_at' => $now, 'expires_at' => min($storeState['expires_at'], $now + 300)];
    $save = static function(array $next, array $expected) use ($path, $read): void {
        if ($read($path, true) !== $expected || ($expected === [] && file_exists($path))) {
            throw new RuntimeException('ANDROMEDA_SURCHARGE_CHECKPOINT_CHANGED');
        }
        if (strlen(json_encode($next, JSON_THROW_ON_ERROR)) > 16384) {
            throw new RuntimeException('ANDROMEDA_SURCHARGE_CHECKPOINT_INVALID');
        }
        anytour_andromeda_search3_save($path, $next);
        if ($read($path) !== $next) throw new RuntimeException('ANDROMEDA_SURCHARGE_CHECKPOINT_FAILED');
    };
    $save($reserved, []);
    $actionCount = 0;
    $received = false;
    $next = $reserved;
    try {
        $actions = new AnyTourAndromedaClaimActions($auth['session']['sid'],
            static function() use (&$actionCount, $directory): void {
                if ($actionCount >= 4) {
                    throw new RuntimeException('ANDROMEDA_SURCHARGE_REQUEST_BUDGET');
                }
                ++$actionCount;
                anytour_andromeda_search3_budget(dirname($directory));
            }, $flightRequest);
        $privatePackage = $package['private_package'];
        $packageDoc = is_array($privatePackage['claimDocument'] ?? null)
            && array_keys($privatePackage['claimDocument']) === [0]
            && is_array($privatePackage['claimDocument'][0])
                ? $privatePackage['claimDocument'][0] : null;
        if (!is_array($packageDoc)) throw new RuntimeException('ANDROMEDA_CLAIM_SHAPE_INVALID');
        $freightExternal = $packageDoc['freightExternal'] ?? null;
        if (is_string($freightExternal) && preg_match('/^0$/D', $freightExternal) === 1) {
            $freightExternal = 0;
        }

        if ($freightExternal === 0) {
            // Supplier package itself proves this is not external/GDS. Do not call
            // get_flights and do not infer zero surcharge; calc owns the verified total.
            $next['status'] = 'complete';
            $next['actualization'] = [
                'state' => 'attempting',
                'strategy' => 'non_external_package_supplier_calc',
                'actions_used' => $actionCount,
            ];
            try {
                $quote = AnyTourAndromedaSelectedQuote::continueWithoutExternalFlights(
                    $resolved, $privatePackage, $actions
                );
                $received = true;
                $current = AnyTourAndromedaSelectedOffer::resolve(
                    $store, $context, $mappingAllows, $clock()
                );
                if ($current['context'] !== $resolved['context']
                    || $clock() >= $reserved['expires_at']) {
                    throw new RuntimeException('ANDROMEDA_SURCHARGE_CONTEXT_STALE');
                }
                $next['verified_quote'] = $quote;
                $next['actualization']['state'] = 'verified';
                $next['actualization']['actions_used'] = $actionCount;
            } catch (Throwable $actualizationError) {
                $message = $actualizationError->getMessage();
                $next['actualization']['state'] = 'failed';
                $next['actualization']['actions_used'] = $actionCount;
                $next['actualization']['failure_class'] =
                    is_string($message) && preg_match('/^[A-Z0-9_:-]{1,96}$/D', $message)
                        ? $message : get_class($actualizationError);
            }
        } else {
            $flights = $actions->getFlights($privatePackage);
            $received = true;
            $again = AnyTourAndromedaSelectedOffer::resolve($store, $context, $mappingAllows, $clock());
            if ($again['context'] !== $resolved['context'] || $clock() >= $reserved['expires_at']) {
                throw new RuntimeException('ANDROMEDA_SURCHARGE_CONTEXT_STALE');
            }
            $next['status'] = 'complete';
            $next['fact'] = AnyTourAndromedaSearchSurcharge::estimate($flights, $resolved['offer']['price']);
            $next['transport_money_diagnostic'] = AnyTourAndromedaSearchSurcharge::diagnostic($flights);

            if (($next['fact']['state'] ?? null) === 'unknown') {
                $selection = AnyTourAndromedaSearchSurcharge::cheapestRequiredFlightSelection(
                    $flights, $resolved['offer']['price']
                );
                if ($selection !== null) {
                    $next['actualization'] = [
                        'state' => 'attempting',
                        'strategy' => 'lowest_supplier_reported_transport_markup_then_calc',
                        'candidate_counts' => $selection['candidate_counts'],
                        'target_currency' => $selection['target_currency'],
                        'actions_used' => $actionCount,
                    ];
                    try {
                        $quote = AnyTourAndromedaSelectedQuote::continueWithFlights(
                            $resolved, $flights, $selection['selected'], $actions
                        );
                        $current = AnyTourAndromedaSelectedOffer::resolve(
                            $store, $context, $mappingAllows, $clock()
                        );
                        if ($current['context'] !== $resolved['context']
                            || $clock() >= $reserved['expires_at']) {
                            throw new RuntimeException('ANDROMEDA_SURCHARGE_CONTEXT_STALE');
                        }
                        $next['verified_quote'] = $quote;
                        $next['actualization']['state'] = 'verified';
                        $next['actualization']['actions_used'] = $actionCount;
                    } catch (Throwable $actualizationError) {
                        $message = $actualizationError->getMessage();
                        $next['actualization']['state'] = 'failed';
                        $next['actualization']['actions_used'] = $actionCount;
                        $next['actualization']['failure_class'] =
                            is_string($message) && preg_match('/^[A-Z0-9_:-]{1,96}$/D', $message)
                                ? $message : get_class($actualizationError);
                    }
                }
            }
        }
        if (strlen(json_encode($next, JSON_THROW_ON_ERROR)) > 16384) {
            throw new RuntimeException('ANDROMEDA_SURCHARGE_CHECKPOINT_INVALID');
        }
    } catch (Throwable $error) {
        $message = $error->getMessage();
        $next = $received && isset($next['transport_money_diagnostic']) ? $next : $reserved;
        $next['status'] = $received ? 'stale' : 'unknown';
        $next['failure_class'] =
            is_string($message) && preg_match('/^[A-Z0-9_:-]{1,96}$/D', $message)
                ? $message : get_class($error);
    }
    $save($next, $reserved);
    $fact = anytour_andromeda_saved_surcharge_public($next, $resolved, $storeState, $created, $clock());
    $verified = anytour_andromeda_saved_verified_quote_private(
        $next, $resolved, $storeState, $created, $clock()
    );
    return [
        'status' => ($fact !== null || $verified !== null) ? 'complete' : 'unavailable',
        'reused' => false,
        'fact' => $fact,
        'final_price_verified' => $verified !== null,
    ];
}

/**
 * Private verified quote reader for autosave only. This never grants selection or
 * booking authority; the provider-neutral quote envelope validates full binding later.
 */
function anytour_andromeda_saved_verified_quote_private(
    array $record,
    array $resolved,
    array $storeState,
    int $created,
    int $now
): ?array {
    static $implementation = null;
    $implementation ??= hash_file('sha256', __FILE__);
    if (($record['version'] ?? null) !== 1 || ($record['status'] ?? null) !== 'complete'
        || ($record['implementation_sha256'] ?? null) !== $implementation
        || !is_string($record['source'] ?? null) || !preg_match('/^[a-f0-9]{40}$/D', $record['source'])
        || ($record['context'] ?? null) !== $resolved['context']
        || ($record['criteria_sha256'] ?? null) !== $resolved['criteria_sha256']
        || ($record['supplier_offer_sha256'] ?? null) !== $resolved['supplier_offer_sha256']
        || !is_string($record['package_sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $record['package_sha256'])
        || ($record['snapshot_created_at'] ?? null) !== $created
        || !is_int($record['observed_at'] ?? null) || !is_int($record['expires_at'] ?? null)
        || $record['observed_at'] < $storeState['created_at'] || $now < $record['observed_at']
        || $now >= $record['expires_at'] || $record['expires_at'] > $record['observed_at'] + 300
        || $record['expires_at'] > $storeState['expires_at']) return null;

    $quote = $record['verified_quote'] ?? null;
    if (!is_array($quote)
        || ($quote['schema_version'] ?? null) !== 1
        || ($quote['provider'] ?? null) !== 'andromeda'
        || ($quote['state'] ?? null) !== 'quote_verified'
        || ($quote['quote_state'] ?? null) !== 'verified'
        || ($quote['final_price_verified'] ?? null) !== true
        || ($quote['flight_selection_required'] ?? null) !== false
        || ($quote['booking_enabled'] ?? null) !== false
        || ($quote['local_id'] ?? null) !== ($resolved['offer']['local_hotel_id'] ?? null)
        || ($quote['operator'] ?? null) !== ($resolved['offer']['operator'] ?? null)
        || !is_array($resolved['offer']['price'] ?? null)
        || ($quote['search_price']['amount'] ?? null) !== ($resolved['offer']['price']['amount'] ?? null)
        || ($quote['search_price']['currency'] ?? null) !== ($resolved['offer']['price']['currency'] ?? null)
        || !is_array($quote['final_price'] ?? null)
        || !is_string($quote['final_price']['amount'] ?? null)
        || !preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $quote['final_price']['amount'])
        || !preg_match('/[1-9]/', $quote['final_price']['amount'])
        || !is_string($quote['final_price']['currency'] ?? null)
        || !preg_match('/^[A-Z0-9_]{2,8}$/D', $quote['final_price']['currency'])
        || str_contains(json_encode($quote, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), '"uid"')) {
        return null;
    }
    return $quote;
}

/** Strict browser projection of a retained fact; no raw claim, rate rows or private identity. */
function anytour_andromeda_saved_surcharge_public(array $record, array $resolved, array $storeState,
    int $created, int $now): ?array
{
    static $implementation = null;
    $implementation ??= hash_file('sha256', __FILE__);
    if (($record['version'] ?? null) !== 1 || ($record['status'] ?? null) !== 'complete'
        || ($record['implementation_sha256'] ?? null) !== $implementation
        || !is_string($record['source'] ?? null) || !preg_match('/^[a-f0-9]{40}$/D', $record['source'])
        || ($record['context'] ?? null) !== $resolved['context']
        || ($record['criteria_sha256'] ?? null) !== $resolved['criteria_sha256']
        || ($record['supplier_offer_sha256'] ?? null) !== $resolved['supplier_offer_sha256']
        || !is_string($record['package_sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $record['package_sha256'])
        || ($record['snapshot_created_at'] ?? null) !== $created
        || !is_int($record['observed_at'] ?? null) || !is_int($record['expires_at'] ?? null)
        || $record['observed_at'] < $storeState['created_at'] || $now < $record['observed_at']
        || $now >= $record['expires_at'] || $record['expires_at'] > $record['observed_at'] + 300
        || $record['expires_at'] > $storeState['expires_at']) return null;
    $fact = $record['fact'] ?? null;
    $base = ['amount' => (string)$resolved['offer']['price']['amount'], 'currency' => $resolved['offer']['price']['currency']];
    if (!is_array($fact) || ($fact['schema_version'] ?? null) !== 1 || ($fact['provider'] ?? null) !== 'andromeda'
        || ($fact['state'] ?? null) !== 'estimated' || ($fact['arithmetic_applied'] ?? null) !== true
        || ($fact['final_price_verified'] ?? null) !== false || ($fact['surcharge_scope'] ?? null) !== 'party'
        || ($fact['search_price'] ?? null) !== $base) return null;
    $surcharge = $fact['party_surcharge'] ?? null;
    $total = $fact['search_price_with_surcharge'] ?? null;
    if (!is_array($surcharge) || !is_array($total)
        || !in_array($surcharge['source'] ?? null, ['andromeda_get_flights_transport', 'andromeda_get_flights_transport_converted'], true)
        || ($total['source'] ?? null) !== 'derived_search_estimate') return null;
    $units = [];
    foreach ([$base, $surcharge, $total] as $money) {
        if (!is_string($money['amount'] ?? null) || !preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $money['amount'])
            || !is_string($money['currency'] ?? null) || !preg_match('/^[A-Z0-9_]{2,8}$/D', $money['currency'])
            || $money['currency'] !== $base['currency']) return null;
        $parts = explode('.', $money['amount']);
        $units[] = (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
    }
    if ($units[0] <= 0 || $units[0] + $units[1] !== $units[2]) return null;
    return ['schema_version' => 1, 'provider' => 'andromeda', 'state' => 'estimated',
        'search_price' => $base,
        'party_surcharge' => ['amount' => $surcharge['amount'], 'currency' => $base['currency'], 'source' => $surcharge['source']],
        'search_price_with_surcharge' => ['amount' => $total['amount'], 'currency' => $base['currency'], 'source' => 'derived_search_estimate'],
        'surcharge_scope' => 'party', 'arithmetic_applied' => true, 'final_price_verified' => false];
}

/**
 * Server-only pricing consumer for AnyTour autosave. Estimated listing money and
 * supplier-verified quote money are separate states; neither enables booking.
 */
function anytour_andromeda_read_saved_pricing(
    string $directory,
    array $storeState,
    int $created,
    array $context,
    callable $mappingAllows,
    int $now
): ?array {
    try {
        $ref = $context['search_ref'] ?? null;
        if (!is_string($ref) || !preg_match('/^[a-f0-9]{64}$/D', $ref)
            || !is_dir($directory) || is_link($directory) || basename($directory) !== 'searches'
            || $created < 1) return null;
        require_once __DIR__ . '/andromeda-selected-offer.php';
        $store = new AnyTourAndromedaOfferStore($storeState, true);
        $resolved = AnyTourAndromedaSelectedOffer::resolve(
            $store, $context, $mappingAllows, $now
        );
        $path = $directory . '/' . $ref . '-' . $created . '-' . $context['page']
            . '-' . $context['offer_ref'] . '-surcharge-v1.json';
        if (is_link($path) || !is_file($path) || filesize($path) > 16384) return null;
        $record = json_decode(file_get_contents($path), true, 20, JSON_THROW_ON_ERROR);
        if (!is_array($record)) return null;

        $quote = anytour_andromeda_saved_verified_quote_private(
            $record, $resolved, $storeState, $created, $now
        );
        if ($quote !== null) {
            return ['state' => 'verified', 'fact' => null, 'verified_quote' => $quote];
        }
        $fact = anytour_andromeda_saved_surcharge_public(
            $record, $resolved, $storeState, $created, $now
        );
        return $fact === null
            ? null
            : ['state' => 'estimated', 'fact' => $fact, 'verified_quote' => null];
    } catch (Throwable $ignored) {
        return null;
    }
}

/** Read-only listing consumer. Caller holds the existing search lock and validates current mappings. */
function anytour_andromeda_read_saved_surcharge(string $directory, array $storeState, int $created,
    array $context, callable $mappingAllows, int $now): ?array
{
    try {
        $ref = $context['search_ref'] ?? null;
        if (!is_string($ref) || !preg_match('/^[a-f0-9]{64}$/D', $ref)
            || !is_dir($directory) || is_link($directory) || basename($directory) !== 'searches' || $created < 1) return null;
        require_once __DIR__ . '/andromeda-selected-offer.php';
        $store = new AnyTourAndromedaOfferStore($storeState, true);
        $resolved = AnyTourAndromedaSelectedOffer::resolve($store, $context, $mappingAllows, $now);
        $path = $directory . '/' . $ref . '-' . $created . '-' . $context['page'] . '-' . $context['offer_ref'] . '-surcharge-v1.json';
        if (is_link($path) || !is_file($path) || filesize($path) > 16384) return null;
        $record = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        return is_array($record) ? anytour_andromeda_saved_surcharge_public($record, $resolved, $storeState, $created, $now) : null;
    } catch (Throwable $ignored) { return null; }
}
