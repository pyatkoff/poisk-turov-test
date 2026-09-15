<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-claim-actions.php';
require_once __DIR__ . '/andromeda-search-surcharge.php';
require_once __DIR__ . '/andromeda-price-observation.php';

/** Read-only selected-tour quote flow. This class has no booking operation. */
final class AnyTourAndromedaSelectedQuote
{
    public static function run(array $resolved, AnyTourAndromedaClient $client,
        AnyTourAndromedaClaimActions $actions, ?callable $retainFlightChoices = null): array
    {
        if (!is_string($resolved['supplier_offer_id'] ?? null)
            || !isset($resolved['offer']) || !is_array($resolved['offer'])) {
            throw new InvalidArgumentException('ANDROMEDA_QUOTE_SELECTION_INVALID');
        }
        $offer = $resolved['offer'];
        $package = $client->package($resolved['supplier_offer_id']);
        $doc = self::document($package);
        if (($doc['condition'] ?? null) !== 'ccOffer') {
            throw new RuntimeException('ANDROMEDA_QUOTE_NOT_OFFER');
        }
        $packagePrice = self::touristPrice($package);
        $claim = $package;
        $selectedFlights = [];
        $searchPriceEstimate = null;

        if ((int)($doc['freightExternal'] ?? 0) > 0) {
            $claim = $actions->getFlights($claim);
            $searchPriceEstimate = self::searchPriceEstimate($claim, $offer);
            $choice = self::flightChoice($claim);
            if ($choice['state'] !== 'unambiguous') {
                $public = $choice['public'];
                if ($retainFlightChoices !== null) {
                    $refs = $retainFlightChoices($claim, $choice['options']);
                    $public = self::attachFlightRefs($choice['options'], $refs);
                }
                return self::base($resolved, $packagePrice) + [
                    'search_price_estimate' => $searchPriceEstimate,
                    'price_observation' => null,
                    'state' => 'flight_selection_required',
                    'quote_state' => 'unverified',
                    'final_price' => null,
                    'final_price_verified' => false,
                    'flight_selection_required' => true,
                    'flights' => $public,
                    'fuel_surcharges_reported' => [],
                    'operator_currency_rates_reported' => self::operatorCurrencyRates($claim),
                    'calc_money_facts_reported' => [],
                    'booking_enabled' => false,
                ];
            }
            foreach (['0', '1'] as $direction) {
                $item = $choice['private'][$direction];
                $claim = self::applyTransportSelection($claim, $item, $actions);
            }
            $selectedFlights = self::selectedFlights($claim);
            if (array_keys($selectedFlights) !== [0, 1]) {
                throw new RuntimeException('ANDROMEDA_SELECTED_FLIGHTS_INVALID');
            }
        } else {
            $selectedFlights = self::selectedFlights($claim);
        }

        return self::finalize($resolved, $claim, $packagePrice, $searchPriceEstimate, $selectedFlights, $actions);
    }

    /**
     * Continue an already retained post-get_flights claim after the browser chooses
     * exactly one outbound and one return. This method cannot call package/get_flights.
     */
    public static function continueWithFlights(array $resolved, array $claim, array $selected,
        AnyTourAndromedaClaimActions $actions): array
    {
        if (!isset($resolved['offer']) || !is_array($resolved['offer'])) {
            throw new InvalidArgumentException('ANDROMEDA_QUOTE_SELECTION_INVALID');
        }
        $doc = self::document($claim);
        if (($doc['condition'] ?? null) !== 'ccOffer') {
            throw new RuntimeException('ANDROMEDA_QUOTE_NOT_OFFER');
        }
        if (array_keys($selected) !== [0, 1]) {
            throw new InvalidArgumentException('ANDROMEDA_FLIGHT_SELECTION_INVALID');
        }
        $packagePrice = self::touristPrice($claim);
        $searchPriceEstimate = self::searchPriceEstimate($claim, $resolved['offer']);
        foreach (['0', '1'] as $direction) {
            $item = $selected[$direction] ?? null;
            if (!is_array($item)
                || (string)($item['direction'] ?? '') !== $direction
                || !is_string($item['uid'] ?? null)
                || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $item['uid']) !== 1) {
                throw new InvalidArgumentException('ANDROMEDA_FLIGHT_SELECTION_INVALID');
            }
            $claim = self::applyTransportSelection($claim, $item, $actions);
        }
        $selectedFlights = self::selectedFlights($claim);
        if (array_keys($selectedFlights) !== [0, 1]) {
            throw new RuntimeException('ANDROMEDA_SELECTED_FLIGHTS_INVALID');
        }
        return self::finalize($resolved, $claim, $packagePrice, $searchPriceEstimate, $selectedFlights, $actions);
    }

    private static function finalize(array $resolved, array $claim, ?array $packagePrice,
        ?array $searchPriceEstimate, array $selectedFlights, AnyTourAndromedaClaimActions $actions): array
    {
        $calculated = $actions->calc($claim);
        $finalPrice = self::touristPrice($calculated);
        if ($finalPrice === null) throw new RuntimeException('ANDROMEDA_FINAL_PRICE_MISSING');
        if (!$selectedFlights) $selectedFlights = self::selectedFlights($calculated);
        $priceObservation = AnyTourAndromedaPriceObservation::build($searchPriceEstimate, $finalPrice);

        return self::base($resolved, $packagePrice) + [
            'search_price_estimate' => $searchPriceEstimate,
            'price_observation' => $priceObservation,
            'state' => 'quote_verified',
            'quote_state' => 'verified',
            'final_price' => $finalPrice,
            'final_price_verified' => true,
            'flight_selection_required' => false,
            'flights' => array_values(array_map([self::class, 'publicFlight'], $selectedFlights)),
            'fuel_surcharges_reported' => self::fuelSurcharges($calculated),
            'operator_currency_rates_reported' => self::operatorCurrencyRates($calculated),
            'calc_money_facts_reported' => self::calcMoneyFacts($calculated),
            'booking_enabled' => false,
        ];
    }

    private static function searchPriceEstimate(array $claim, array $offer): ?array
    {
        $estimate = AnyTourAndromedaSearchSurcharge::estimate($claim, $offer['price'] ?? []);
        return ($estimate['state'] ?? null) === 'estimated'
            && is_array($estimate['search_price_with_surcharge'] ?? null)
            ? $estimate['search_price_with_surcharge'] : null;
    }

    private static function base(array $resolved, ?array $packagePrice): array
    {
        $offer = $resolved['offer'];
        $searchPrice = null;
        if (is_array($offer['price'] ?? null)) {
            $amount = self::moneyFactValue($offer['price']['amount'] ?? null);
            $currency = $offer['price']['currency'] ?? null;
            if ($amount !== null && preg_match('/[1-9]/', $amount) === 1
                && is_string($currency) && preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) === 1) {
                $searchPrice = ['amount' => $amount, 'currency' => $currency];
            }
        }
        return [
            'schema_version' => 1,
            'provider' => 'andromeda',
            'selection_enabled' => true,
            'booking_enabled' => false,
            'local_id' => $offer['local_hotel_id'] ?? null,
            'operator' => $offer['operator'] ?? null,
            'search_price' => $searchPrice,
            'package_price' => $packagePrice,
        ];
    }

    private static function document(array $claim): array
    {
        if (!is_array($claim['claimDocument'] ?? null) || array_keys($claim['claimDocument']) !== [0]
            || !is_array($claim['claimDocument'][0])) {
            throw new RuntimeException('ANDROMEDA_CLAIM_SHAPE_INVALID');
        }
        return $claim['claimDocument'][0];
    }

    private static function touristPrice(array $claim): ?array
    {
        $doc = self::document($claim);
        $rows = [];
        foreach (($doc['buyerMoneys'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['buyerClaimMoney'] ?? null)) continue;
            foreach ($block['buyerClaimMoney'] as $money) if (is_array($money)) $rows[] = $money;
        }
        if (count($rows) !== 1) return null;
        $amount = self::moneyFactValue($rows[0]['net'] ?? null);
        $currency = $rows[0]['currency'] ?? null;
        if ($amount === null
            || preg_match('/[1-9]/', $amount) !== 1
            || !is_string($currency) || !preg_match('/^[A-Z0-9_]{2,8}$/D', $currency)) return null;
        return ['amount' => $amount, 'currency' => $currency];
    }

    /** Supplier-reported operator conversion rows; evidence only, never applied here. */
    private static function operatorCurrencyRates(array $claim): array
    {
        $doc = self::document($claim);
        $out = [];
        foreach (($doc['moneys'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['money'] ?? null)) continue;
            foreach ($block['money'] as $money) {
                if (!is_array($money)) continue;
                $currency = $money['currency'] ?? null;
                $rate = $money['rate'] ?? null;
                if (is_int($rate) || (is_float($rate) && is_finite($rate))) $rate = (string)$rate;
                if (!is_string($currency) || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1
                    || !is_string($rate) || preg_match('/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,6})?$/D', $rate) !== 1
                    || preg_match('/[1-9]/', $rate) !== 1) continue;
                $isClaimCurrency = (string)($money['isClaimCurrency'] ?? '');
                $row = [
                    'currency' => $currency,
                    'rate' => $rate,
                    'is_claim_currency' => $isClaimCurrency === 'true' ? true : ($isClaimCurrency === 'false' ? false : null),
                    'source' => 'andromeda_claim_money',
                    'arithmetic_applied' => false,
                ];
                $out[$currency . "\0" . $rate . "\0" . var_export($row['is_claim_currency'], true)] = $row;
            }
        }
        return array_values($out);
    }

    private static function moneyFactValue($value): ?string
    {
        if (is_int($value) || (is_float($value) && is_finite($value))) $value = (string)$value;
        if (!is_string($value)
            || preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $value) !== 1) return null;
        return $value;
    }

    /** Raw calc money rows are supplier facts only; no totals, rates or differences are derived here. */
    private static function calcMoneyFacts(array $claim): array
    {
        $doc = self::document($claim);
        $out = [];
        foreach (($doc['moneys'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['money'] ?? null)) continue;
            foreach ($block['money'] as $money) {
                if (!is_array($money)) continue;
                $currency = $money['currency'] ?? null;
                $gross = self::moneyFactValue($money['price'] ?? null);
                if (!is_string($currency) || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1
                    || $gross === null || preg_match('/[1-9]/', $gross) !== 1) continue;
                $row = [
                    'currency' => $currency,
                    'gross_amount' => $gross,
                    'net_amount' => self::moneyFactValue($money['net'] ?? null),
                    'commissionable_amount' => self::moneyFactValue($money['priceForCommiss'] ?? null),
                    'commission_amount' => self::moneyFactValue($money['sumCommission'] ?? null),
                    'source' => 'andromeda_calc_money',
                    'arithmetic_applied' => false,
                ];
                $out[$currency . "\0" . implode("\0", array_map(static fn($v): string => $v === null ? '' : (string)$v,
                    [$row['gross_amount'], $row['net_amount'], $row['commissionable_amount'], $row['commission_amount']]))] = $row;
            }
        }
        return array_values($out);
    }

    /** Supplier-reported fuel services are evidence only; do not aggregate or apply them to prices here. */
    private static function fuelSurcharges(array $claim): array
    {
        $doc = self::document($claim);
        $out = [];
        foreach (($doc['services'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['service'] ?? null)) continue;
            foreach ($block['service'] as $service) {
                if (!is_array($service)
                    || (string)($service['servicetype'] ?? '') !== '8'
                    || (string)($service['servicecategoryName'] ?? '') !== 'Топливный сбор') continue;
                $amount = self::moneyFactValue($service['price'] ?? null);
                $currency = $service['currencyAlias'] ?? null;
                if ($amount === null
                    || !is_string($currency) || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1) continue;
                $route = (string)($service['routeIndex'] ?? '');
                $out[] = [
                    'amount' => $amount,
                    'currency' => $currency,
                    'route_index' => in_array($route, ['0', '1'], true) ? $route : null,
                    'source' => 'andromeda_claim_service',
                ];
            }
        }
        return $out;
    }

    /** Launch path: auto-select only exactly one required outbound and one required return. */
    private static function flightChoice(array $claim): array
    {
        $required = [];
        foreach (($claim['groups'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['group'] ?? null)) continue;
            foreach ($block['group'] as $group) {
                if (!is_array($group)) continue;
                if ((string)($group['required'] ?? '') === 'true' && (string)($group['oneItem'] ?? '') === 'true') {
                    $id = (string)($group['id'] ?? '');
                    if ($id !== '') $required[$id] = true;
                }
            }
        }
        $options = ['0' => [], '1' => []];
        foreach (($claim['variants'] ?? []) as $variant) {
            if (!is_array($variant)) continue;
            foreach (($variant['transports'] ?? []) as $block) {
                if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
                foreach ($block['transport'] as $item) {
                    if (!is_array($item) || ($item['type'] ?? null) !== 'ttAvia') continue;
                    $direction = (string)($item['direction'] ?? '');
                    $group = (string)($item['groupId'] ?? '');
                    if (!isset($options[$direction]) || !isset($required[$group])) continue;
                    if (!is_string($item['uid'] ?? null) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $item['uid'])) {
                        throw new RuntimeException('ANDROMEDA_FLIGHT_UID_INVALID');
                    }
                    $options[$direction][] = $item;
                }
            }
        }
        $public = self::attachFlightRefs($options, null);
        if (count($options['0']) !== 1 || count($options['1']) !== 1) {
            return ['state' => 'choice_required', 'private' => [], 'options' => $options, 'public' => $public];
        }
        return [
            'state' => 'unambiguous',
            'private' => ['0' => $options['0'][0], '1' => $options['1'][0]],
            'options' => $options,
            'public' => $public,
        ];
    }

    private static function attachFlightRefs(array $options, ?array $refs): array
    {
        $public = [];
        foreach (['0', '1'] as $direction) {
            if (!is_array($options[$direction] ?? null)) {
                throw new RuntimeException('ANDROMEDA_FLIGHT_OPTIONS_INVALID');
            }
            if ($refs !== null && (!is_array($refs[$direction] ?? null)
                || count($refs[$direction]) !== count($options[$direction]))) {
                throw new RuntimeException('ANDROMEDA_FLIGHT_REFS_INVALID');
            }
            foreach ($options[$direction] as $index => $item) {
                if (!is_array($item)) throw new RuntimeException('ANDROMEDA_FLIGHT_OPTIONS_INVALID');
                $row = self::publicFlight($item);
                if ($refs !== null) {
                    $ref = $refs[$direction][$index] ?? null;
                    if (!is_string($ref) || preg_match('/^flight_[a-f0-9]{32}$/D', $ref) !== 1) {
                        throw new RuntimeException('ANDROMEDA_FLIGHT_REFS_INVALID');
                    }
                    $row['flight_ref'] = $ref;
                }
                $public[] = $row;
            }
        }
        return $public;
    }

    /** Apply official Andromeda add/replace semantics for one selected flight direction. */
    private static function applyTransportSelection(array $claim, array $item,
        AnyTourAndromedaClaimActions $actions): array
    {
        $doc = self::document($claim);
        $selected = [];
        $sameDirection = [];
        $direction = (string)($item['direction'] ?? '');
        foreach (($doc['transports'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
            foreach ($block['transport'] as $transport) {
                if (!is_array($transport)) continue;
                $selected[] = $transport;
                $index = count($selected) - 1;
                if (($transport['type'] ?? null) === 'ttAvia'
                    && (string)($transport['direction'] ?? '') === $direction) {
                    $sameDirection[] = $index;
                }
            }
        }
        if (count($sameDirection) > 1) {
            throw new RuntimeException('ANDROMEDA_SELECTED_FLIGHTS_INVALID');
        }
        if ($sameDirection !== []) {
            $index = $sameDirection[0];
            $oldUid = $selected[$index]['uid'] ?? null;
            if (!is_string($oldUid) || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $oldUid) !== 1) {
                throw new RuntimeException('ANDROMEDA_SELECTED_FLIGHTS_INVALID');
            }
            if ($oldUid === $item['uid']) return $claim;
            $selected[$index] = $item;
            $claim['claimDocument'][0]['transports'] = [['transport' => array_values($selected)]];
            return $actions->changeService($claim, $item['uid'], $oldUid);
        }
        $selected[] = $item;
        $claim['claimDocument'][0]['transports'] = [['transport' => array_values($selected)]];
        return $actions->changeService($claim, $item['uid']);
    }

    private static function selectedFlights(array $claim): array
    {
        $doc = self::document($claim);
        $out = [];
        foreach (($doc['transports'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
            foreach ($block['transport'] as $item) {
                if (!is_array($item) || ($item['type'] ?? null) !== 'ttAvia') continue;
                $direction = (string)($item['direction'] ?? '');
                if (!in_array($direction, ['0', '1'], true) || isset($out[$direction])) continue;
                $out[$direction] = $item;
            }
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    /** Preserve a single supplier markup fact for one flight; cross-leg aggregation is intentionally unknown. */
    private static function transportMarkup(array $flight): ?array
    {
        $facts = [];
        foreach (($flight['details'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['detail'] ?? null)) continue;
            foreach ($block['detail'] as $detail) {
                if (!is_array($detail)) continue;
                $amount = self::moneyFactValue($detail['markup'] ?? null);
                $currency = $detail['currency'] ?? null;
                if ($amount === null
                    || !is_string($currency) || preg_match('/^[A-Z0-9_]{2,8}$/D', $currency) !== 1) continue;
                $facts[$amount . "\0" . $currency] = ['amount' => $amount, 'currency' => $currency];
            }
        }
        if (count($facts) !== 1) return null;
        $fact = array_values($facts)[0];
        return $fact + [
            'source' => 'andromeda_transport_detail',
            'aggregation' => 'unknown',
        ];
    }

    private static function publicFlight(array $flight): array
    {
        $out = [
            'direction' => in_array((string)($flight['direction'] ?? ''), ['0', '1'], true) ? (string)$flight['direction'] : null,
            'name' => is_string($flight['name'] ?? null) ? substr($flight['name'], 0, 160) : null,
            'datebeg' => is_string($flight['datebeg'] ?? null) ? $flight['datebeg'] : null,
            'dateend' => is_string($flight['dateend'] ?? null) ? $flight['dateend'] : null,
            'class' => is_string($flight['onlineClass'] ?? null) ? substr($flight['onlineClass'], 0, 80)
                : (is_string($flight['class'] ?? null) ? substr($flight['class'], 0, 80) : null),
            'transport_markup_reported' => self::transportMarkup($flight),
        ];
        foreach (['departure', 'arrival'] as $side) {
            $point = null;
            $rows = $flight[$side] ?? null;
            if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
                $row = $rows[0];
                $point = [
                    'state' => is_string($row['state'] ?? null) ? substr($row['state'], 0, 100) : null,
                    'town' => is_string($row['town'] ?? null) ? substr($row['town'], 0, 100) : null,
                    'port' => is_string($row['port'] ?? null) ? substr($row['port'], 0, 100) : null,
                ];
            }
            $out[$side] = $point;
        }
        return $out;
    }
}

/**
 * Private retained state for one ambiguous Andromeda flight choice.
 * Raw supplier UIDs/claim stay server-side; the browser sees only opaque refs.
 */
final class AnyTourAndromedaFlightSelection
{
    public static function buildState(array $claim, array $options, string $contextSha256,
        ?callable $refFactory = null): array
    {
        self::assertDigest($contextSha256);
        if (array_keys($options) !== [0, 1]) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_OPTIONS_INVALID');
        }
        $items = [];
        $refs = ['0' => [], '1' => []];
        foreach (['0', '1'] as $direction) {
            if (!is_array($options[$direction]) || $options[$direction] === []) {
                throw new RuntimeException('ANDROMEDA_FLIGHT_OPTIONS_INVALID');
            }
            foreach ($options[$direction] as $index => $item) {
                self::assertItem($item, $direction);
                $ref = $refFactory === null
                    ? 'flight_' . bin2hex(random_bytes(16))
                    : $refFactory($direction, $index, $item);
                if (!is_string($ref) || preg_match('/^flight_[a-f0-9]{32}$/D', $ref) !== 1
                    || isset($items[$ref])) {
                    throw new RuntimeException('ANDROMEDA_FLIGHT_REF_INVALID');
                }
                $items[$ref] = ['direction' => $direction, 'item' => $item];
                $refs[$direction][] = $ref;
            }
        }
        return [
            'state' => [
                'version' => 1,
                'provider' => 'andromeda',
                'context_sha256' => $contextSha256,
                'claim' => $claim,
                'items' => $items,
            ],
            'refs' => $refs,
        ];
    }

    /** Resolve one outbound + one return without exposing or accepting supplier UIDs. */
    public static function select(array $state, string $contextSha256, array $selection): array
    {
        self::assertDigest($contextSha256);
        if (($state['version'] ?? null) !== 1 || ($state['provider'] ?? null) !== 'andromeda'
            || !is_string($state['context_sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $state['context_sha256']) !== 1
            || !hash_equals($state['context_sha256'], $contextSha256)
            || !is_array($state['claim'] ?? null) || !is_array($state['items'] ?? null)) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_STATE_INVALID');
        }
        $keys = array_keys($selection);
        sort($keys);
        if ($keys !== ['outbound_ref', 'provider', 'return_ref']
            || ($selection['provider'] ?? null) !== 'andromeda') {
            throw new InvalidArgumentException('ANDROMEDA_FLIGHT_SELECTION_INVALID');
        }
        $wanted = ['0' => $selection['outbound_ref'] ?? null, '1' => $selection['return_ref'] ?? null];
        $selected = [];
        foreach ($wanted as $direction => $ref) {
            $direction = (string)$direction;
            if (!is_string($ref) || preg_match('/^flight_[a-f0-9]{32}$/D', $ref) !== 1
                || !isset($state['items'][$ref]) || !is_array($state['items'][$ref])) {
                throw new InvalidArgumentException('ANDROMEDA_FLIGHT_SELECTION_INVALID');
            }
            $record = $state['items'][$ref];
            if (($record['direction'] ?? null) !== $direction || !is_array($record['item'] ?? null)) {
                throw new InvalidArgumentException('ANDROMEDA_FLIGHT_SELECTION_INVALID');
            }
            self::assertItem($record['item'], $direction);
            $selected[$direction] = $record['item'];
        }
        return ['claim' => $state['claim'], 'selected' => $selected];
    }

    private static function assertItem(mixed $item, string $direction): void
    {
        if (!is_array($item)
            || (string)($item['direction'] ?? '') !== $direction
            || ($item['type'] ?? null) !== 'ttAvia'
            || !is_string($item['uid'] ?? null)
            || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $item['uid']) !== 1) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_OPTIONS_INVALID');
        }
    }

    private static function assertDigest(string $value): void
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) {
            throw new RuntimeException('ANDROMEDA_FLIGHT_CONTEXT_INVALID');
        }
    }
}
