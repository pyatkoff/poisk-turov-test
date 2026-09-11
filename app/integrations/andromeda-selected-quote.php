<?php
declare(strict_types=1);

require_once __DIR__ . '/andromeda-claim-actions.php';

/** Read-only selected-tour quote flow. This class has no booking operation. */
final class AnyTourAndromedaSelectedQuote
{
    public static function run(array $resolved, AnyTourAndromedaClient $client,
        AnyTourAndromedaClaimActions $actions): array
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

        if ((int)($doc['freightExternal'] ?? 0) > 0) {
            $claim = $actions->getFlights($claim);
            $choice = self::flightChoice($claim);
            if ($choice['state'] !== 'unambiguous') {
                return self::base($resolved, $packagePrice) + [
                    'state' => 'flight_selection_required',
                    'quote_state' => 'unverified',
                    'final_price' => null,
                    'final_price_verified' => false,
                    'flight_selection_required' => true,
                    'flights' => $choice['public'],
                    'booking_enabled' => false,
                ];
            }
            foreach (['0', '1'] as $direction) {
                $item = $choice['private'][$direction];
                $claim = self::appendTransport($claim, $item);
                $claim = $actions->changeService($claim, $item['uid']);
            }
            $selectedFlights = self::selectedFlights($claim);
            if (array_keys($selectedFlights) !== ['0', '1']) {
                throw new RuntimeException('ANDROMEDA_SELECTED_FLIGHTS_INVALID');
            }
        } else {
            $selectedFlights = self::selectedFlights($claim);
        }

        $calculated = $actions->calc($claim);
        $finalPrice = self::touristPrice($calculated);
        if ($finalPrice === null) throw new RuntimeException('ANDROMEDA_FINAL_PRICE_MISSING');
        if (!$selectedFlights) $selectedFlights = self::selectedFlights($calculated);

        return self::base($resolved, $packagePrice) + [
            'state' => 'quote_verified',
            'quote_state' => 'verified',
            'final_price' => $finalPrice,
            'final_price_verified' => true,
            'flight_selection_required' => false,
            'flights' => array_values(array_map([self::class, 'publicFlight'], $selectedFlights)),
            'booking_enabled' => false,
        ];
    }

    private static function base(array $resolved, ?array $packagePrice): array
    {
        $offer = $resolved['offer'];
        return [
            'schema_version' => 1,
            'provider' => 'andromeda',
            'selection_enabled' => true,
            'booking_enabled' => false,
            'local_id' => $offer['local_hotel_id'] ?? null,
            'operator' => $offer['operator'] ?? null,
            'search_price' => $offer['price'] ?? null,
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
        $amount = (string)($rows[0]['net'] ?? '');
        $currency = $rows[0]['currency'] ?? null;
        if (!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $amount)
            || preg_match('/[1-9]/', $amount) !== 1
            || !is_string($currency) || !preg_match('/^[A-Z0-9_]{2,8}$/D', $currency)) return null;
        return ['amount' => $amount, 'currency' => $currency];
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
        $public = [];
        foreach (['0', '1'] as $direction) {
            foreach ($options[$direction] as $item) $public[] = self::publicFlight($item);
        }
        if (count($options['0']) !== 1 || count($options['1']) !== 1) {
            return ['state' => 'choice_required', 'private' => [], 'public' => $public];
        }
        return ['state' => 'unambiguous', 'private' => ['0' => $options['0'][0], '1' => $options['1'][0]], 'public' => $public];
    }

    private static function appendTransport(array $claim, array $item): array
    {
        $doc = self::document($claim);
        $selected = [];
        foreach (($doc['transports'] ?? []) as $block) {
            if (!is_array($block) || !is_array($block['transport'] ?? null)) continue;
            foreach ($block['transport'] as $transport) if (is_array($transport)) $selected[] = $transport;
        }
        foreach ($selected as $transport) {
            if ((string)($transport['direction'] ?? '') === (string)($item['direction'] ?? '')) {
                throw new RuntimeException('ANDROMEDA_FLIGHT_ALREADY_SELECTED');
            }
        }
        $selected[] = $item;
        $claim['claimDocument'][0]['transports'] = [['transport' => $selected]];
        return $claim;
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

    private static function publicFlight(array $flight): array
    {
        $out = [
            'direction' => in_array((string)($flight['direction'] ?? ''), ['0', '1'], true) ? (string)$flight['direction'] : null,
            'name' => is_string($flight['name'] ?? null) ? substr($flight['name'], 0, 160) : null,
            'datebeg' => is_string($flight['datebeg'] ?? null) ? $flight['datebeg'] : null,
            'dateend' => is_string($flight['dateend'] ?? null) ? $flight['dateend'] : null,
            'class' => is_string($flight['onlineClass'] ?? null) ? substr($flight['onlineClass'], 0, 80)
                : (is_string($flight['class'] ?? null) ? substr($flight['class'], 0, 80) : null),
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
