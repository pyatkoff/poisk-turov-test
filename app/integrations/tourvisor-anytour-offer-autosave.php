<?php
declare(strict_types=1);

require_once __DIR__ . '/three-provider-operator.php';
require_once __DIR__ . '/three-provider-availability.php';
require_once __DIR__ . '/three-provider-flight-details.php';
require_once __DIR__ . '/three-provider-meal-family.php';
require_once __DIR__ . '/three-provider-room-placement.php';
require_once __DIR__ . '/three-provider-money-facts.php';
require_once __DIR__ . '/three-provider-offer-contract.php';
require_once __DIR__ . '/three-provider-offer-context.php';
require_once __DIR__ . '/three-provider-search-handoff.php';
require_once __DIR__ . '/anytour-offer-snapshot-producer.php';
require_once dirname(__DIR__, 2) . '/v2/data/db-v1.php';
require_once dirname(__DIR__, 2) . '/v2/data/anytour-search-scope-v1.php';
require_once dirname(__DIR__, 2) . '/v2/data/anytour-canonical-catalog-v1.php';
require_once dirname(__DIR__, 2) . '/v2/data/anytour-offer-snapshot-ingest-v1.php';

/**
 * Best-effort Tourvisor -> AnyTour offer-store bridge for the existing Search3 gateway.
 *
 * No supplier I/O and no price/fuel arithmetic live here. Search price stays the
 * supplier search amount. Presence of Tourvisor fuelCharge is only a readiness gate;
 * the protected finalPriceReady handoff remains the sole listing-price authority.
 *
 * Persistence is intentionally source-routed: Tourvisor owns only PEGAS, Coral and
 * Sunmar offers. ANEX is direct-ANEX-owned; other operators remain SAMO/Andromeda-owned.
 */
final class AnyTourTourvisorOfferAutosaveV1
{
    private const STATE_VERSION = 1;
    private const STATE_TTL = 3600;
    private const CONTEXT_TTL = 900;
    private const FINAL_RESULTS_LIMIT = 100;
    private const MAX_HOTELS = 100;
    private const MAX_OFFERS = 5000;

    public static function captureSearchStart(array $scope, array $response, DateTimeImmutable $now): array
    {
        AnyTourSearchScopeV1::fromParams($scope);
        $searchId = self::extractSearchId($response);
        if ($searchId === null) return self::receipt(false, 'search_id_missing');

        $state = [
            'version' => self::STATE_VERSION,
            'search_id' => $searchId,
            'scope' => $scope,
            'started_at' => $now->getTimestamp(),
            'terminal_at' => null,
            'saved_at' => null,
        ];
        self::writeState($searchId, $state);
        return self::receipt(true, null, ['searchId' => $searchId]);
    }

    public static function captureSearchStatus(int $searchId, array $response, DateTimeImmutable $now): array
    {
        if ($searchId < 1) return self::receipt(false, 'search_id_invalid');
        $state = self::readState($searchId, $now);
        if ($state === null) return self::receipt(false, 'state_missing');
        if (!self::isTerminal($response)) return self::receipt(false, 'not_terminal');

        $state['terminal_at'] = $now->getTimestamp();
        self::writeState($searchId, $state);
        return self::receipt(true, null, ['searchId' => $searchId]);
    }

    public static function autosaveSearchResults(
        int $searchId,
        int $limit,
        array $response,
        DateTimeImmutable $now
    ): array {
        if ($searchId < 1) return self::receipt(false, 'search_id_invalid');
        if ($limit !== self::FINAL_RESULTS_LIMIT) return self::receipt(false, 'not_final_bounded_fetch');
        if (!array_is_list($response)) return self::receipt(false, 'results_not_list');
        if ($response === []) return self::receipt(false, 'empty_not_authoritative');
        if (count($response) > self::MAX_HOTELS) return self::receipt(false, 'too_many_hotels');

        $state = self::readState($searchId, $now);
        if ($state === null) return self::receipt(false, 'state_missing');
        if (!is_int($state['terminal_at'] ?? null)) return self::receipt(false, 'search_not_terminal');
        if (is_int($state['saved_at'] ?? null)) return self::receipt(false, 'already_saved');
        $scope = $state['scope'] ?? null;
        if (!is_array($scope)) return self::receipt(false, 'scope_missing');
        AnyTourSearchScopeV1::fromParams($scope);

        $rawOfferCount = 0;
        $legacyIds = [];
        $routedResponse = [];
        foreach ($response as $hotel) {
            if (!is_array($hotel)) return self::receipt(false, 'malformed_hotel');
            $legacyId = self::positiveInt($hotel['id'] ?? null);
            if ($legacyId === null) return self::receipt(false, 'hotel_id_missing');
            $tours = $hotel['tours'] ?? null;
            if (!is_array($tours) || !array_is_list($tours)) return self::receipt(false, 'hotel_tours_missing');

            $routedTours = [];
            foreach ($tours as $tour) {
                if (!is_array($tour)) return self::receipt(false, 'malformed_tour');
                $operatorRaw = self::firstText($tour, ['operatorName', 'operator']);
                if (self::ownedOperatorFamily($operatorRaw) === null) continue;
                $routedTours[] = $tour;
                ++$rawOfferCount;
                if ($rawOfferCount > self::MAX_OFFERS) return self::receipt(false, 'too_many_offers');
            }
            if ($routedTours === []) continue;
            $legacyIds[$legacyId] = $legacyId;
            $hotel['tours'] = $routedTours;
            $routedResponse[] = $hotel;
        }
        if ($rawOfferCount === 0 || $routedResponse === []) {
            return self::receipt(false, 'no_routed_offers');
        }
        $response = $routedResponse;

        $db = v2_data_db();
        $catalog = new AnyTourCanonicalCatalog($db);
        $targets = $catalog->legacyTargets(array_values($legacyIds));

        $entries = [];
        $observedAt = $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $adults = (int)$scope['adults'];
        $childAges = array_map('intval', $scope['childs']);
        $children = count($childAges);

        foreach ($response as $hotel) {
            $legacyId = (int)$hotel['id'];
            $ownId = $targets[$legacyId] ?? null;
            foreach ($hotel['tours'] as $tour) {
                $entry = self::entryFromTour(
                    $searchId,
                    $legacyId,
                    is_int($ownId) ? $ownId : null,
                    $tour,
                    $adults,
                    $children,
                    $childAges,
                    $observedAt,
                    $now
                );
                if ($entry === null) return self::receipt(false, 'tour_contract_incomplete');
                $entries[] = $entry;
            }
        }
        if ($entries === []) return self::receipt(false, 'no_contract_rows');

        $result = AnyTourIntOfferSnapshotProducerV1::produce(
            'tourvisor',
            $scope,
            ['complete' => true, 'authoritative_empty' => false, 'offers' => $entries],
            $now,
            static function (string $provider, array $searchParams, array $rows, DateTimeImmutable $at) use ($db): array {
                return AnyTourOfferSnapshotIngestV1::replaceCompleteSnapshot($db, $provider, $searchParams, $rows, $at);
            }
        );

        if (($result['published'] ?? null) === true) {
            $state['saved_at'] = $now->getTimestamp();
            self::writeState($searchId, $state);
        }
        return $result;
    }

    private static function entryFromTour(
        int $searchId,
        int $legacyId,
        ?int $ownId,
        array $tour,
        int $adults,
        int $children,
        array $childAges,
        string $observedAt,
        DateTimeImmutable $now
    ): ?array {
        $tourId = self::opaqueId($tour['id'] ?? $tour['tourId'] ?? null, 220);
        $checkin = self::date($tour['date'] ?? $tour['checkin'] ?? null);
        $nights = self::positiveInt($tour['nights'] ?? null);
        $price = self::moneyAmount($tour['price'] ?? null, false);
        $currency = strtoupper(self::text($tour['currency'] ?? 'RUB', 8));
        $mealRaw = self::firstText($tour, ['mealName', 'mealType', 'meal', 'pansion']);
        $roomRaw = self::firstText($tour, ['roomType', 'roomName', 'room']);
        $placementRaw = self::firstText($tour, ['placement', 'accommodation']);
        if ($placementRaw === '') $placementRaw = null;

        if ($tourId === null || $checkin === null || $nights === null || $nights > 30
            || $price === null || $currency !== 'RUB' || $mealRaw === '' || $roomRaw === '') {
            return null;
        }

        $operatorRaw = self::firstText($tour, ['operatorName', 'operator']);
        if (self::ownedOperatorFamily($operatorRaw) === null) return null;

        $fuel = null;
        if (array_key_exists('fuelCharge', $tour)) {
            $fuelAmount = self::moneyAmount($tour['fuelCharge'], true);
            if ($fuelAmount !== null) {
                $fuel = ['amount' => $fuelAmount, 'currency' => 'RUB', 'source' => 'tourvisor_fuel'];
            }
        }

        try {
            $meal = AnyTourThreeProviderMealFamily::normalize($mealRaw);
            $roomPlacement = AnyTourThreeProviderRoomPlacement::normalize('tourvisor', $roomRaw, $placementRaw);
            $offer = AnyTourThreeProviderOfferContract::fromSearch([
                'provider' => 'tourvisor',
                'operator' => $operatorRaw,
                'local_hotel_id' => $legacyId,
                'provider_hotel_ref' => 'tourvisor:hotel:' . $legacyId,
                'search_ref' => 'tourvisor:search:' . $searchId,
                'offer_ref' => 'tourvisor:tour:' . $tourId,
                'checkin' => $checkin,
                'nights' => $nights,
                'adults' => $adults,
                'children' => $children,
                'child_ages' => $childAges,
                'meal' => [
                    'raw' => $meal['raw'],
                    'family' => $meal['family'],
                    'qualifiers' => $meal['qualifiers'],
                ],
                'room' => [
                    'raw' => $roomPlacement['room']['raw'],
                    'normalized' => $roomPlacement['room']['normalized'],
                ],
                'placement' => $roomPlacement['placement'] === null ? null : [
                    'raw' => $roomPlacement['placement']['raw'],
                    'normalized' => $roomPlacement['placement']['normalized'],
                ],
                'availability' => [
                    'hotel' => null,
                    'flight_outbound_economy' => null,
                    'flight_return_economy' => null,
                ],
                'search_price' => ['amount' => $price, 'currency' => 'RUB', 'source' => 'tourvisor_search'],
                'fuel_charge_reported' => $fuel,
                'additional_prices_reported' => [],
                'observed_at' => $observedAt,
            ]);

            $issued = $now->getTimestamp();
            $retained = AnyTourThreeProviderOfferContext::retain($offer, 1, 1, $issued, self::CONTEXT_TTL);
            $current = [
                'provider' => $retained['provider'],
                'operator' => $retained['operator'],
                'local_hotel_id' => $retained['local_hotel_id'],
                'identity' => $retained['identity'],
                'generation' => $retained['generation'],
                'page' => $retained['page'],
            ];
            return [
                'anytour_hotel_id' => $ownId,
                'offer' => $offer,
                'retained' => $retained,
                'current' => $current,
                'priced_money' => $offer['money'],
            ];
        } catch (Throwable $error) {
            return null;
        }
    }

    private static function ownedOperatorFamily(string $raw): ?string
    {
        $value = trim($raw);
        if ($value === '') return null;
        $value = mb_strtolower(str_replace('ё', 'е', $value), 'UTF-8');
        $compact = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
        if (in_array($compact, ['pegas', 'pegastouristik', 'pegastouristic', 'пегас', 'пегастуристик'], true)) {
            return 'pegas';
        }
        if (in_array($compact, ['coral', 'coraltravel', 'корал', 'коралтревел'], true)) {
            return 'coral';
        }
        if (in_array($compact, ['sunmar', 'sunmartour', 'sunmartravel', 'санмар', 'санмартур'], true)) {
            return 'sunmar';
        }
        return null;
    }

    private static function extractSearchId(array $response): ?int
    {
        foreach ([$response, $response['result'] ?? null, $response['data'] ?? null] as $value) {
            if (!is_array($value)) continue;
            foreach (['searchId', 'search_id', 'id'] as $key) {
                $id = self::positiveInt($value[$key] ?? null);
                if ($id !== null) return $id;
            }
        }
        return null;
    }

    private static function isTerminal(array $response): bool
    {
        foreach ([$response, $response['result'] ?? null, $response['data'] ?? null] as $value) {
            if (!is_array($value)) continue;
            $progress = $value['progress'] ?? null;
            if (is_numeric($progress) && (int)$progress === 100) return true;
            $status = strtolower(trim((string)($value['status'] ?? '')));
            if ($status === 'complete') return true;
        }
        return false;
    }

    private static function readState(int $searchId, DateTimeImmutable $now): ?array
    {
        $path = self::statePath($searchId);
        if (!is_file($path)) return null;
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') return null;
        $state = json_decode($raw, true);
        if (!is_array($state)
            || ($state['version'] ?? null) !== self::STATE_VERSION
            || ($state['search_id'] ?? null) !== $searchId
            || !is_int($state['started_at'] ?? null)
            || $state['started_at'] > $now->getTimestamp()
            || $now->getTimestamp() - $state['started_at'] > self::STATE_TTL) {
            @unlink($path);
            return null;
        }
        return $state;
    }

    private static function writeState(int $searchId, array $state): void
    {
        $path = self::statePath($searchId);
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('TOURVISOR_AUTOSAVE_STATE_DIR');
        $json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) throw new RuntimeException('TOURVISOR_AUTOSAVE_STATE_WRITE');
        @chmod($tmp, 0600);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('TOURVISOR_AUTOSAVE_STATE_RENAME');
        }
    }

    private static function statePath(int $searchId): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'anytour-tourvisor-offer-' . hash('sha256', (string)$searchId) . '.json';
    }

    private static function firstText(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) continue;
            $value = self::label($row[$key]);
            if ($value !== '') return $value;
        }
        return '';
    }

    private static function label(mixed $value): string
    {
        if (is_string($value)) return self::text($value, 180);
        if (!is_array($value)) return '';
        foreach (['russianName', 'name', 'title', 'label', 'code'] as $key) {
            if (!array_key_exists($key, $value)) continue;
            $text = self::text($value[$key], 180);
            if ($text !== '' && preg_match('/[\p{L}]/u', $text)) return $text;
        }
        if (array_is_list($value)) {
            $items = [];
            foreach (array_slice($value, 0, 4) as $item) {
                $text = self::label($item);
                if ($text !== '') $items[] = $text;
            }
            return self::text(implode(', ', $items), 180);
        }
        return '';
    }

    private static function text(mixed $value, int $max): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) return '';
        $value = trim((string)$value);
        if ($value === '' || preg_match('/[\x00-\x1f\x7f]/', $value)) return '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }

    private static function opaqueId(mixed $value, int $max): ?string
    {
        if (!is_string($value) && !is_int($value)) return null;
        $value = trim((string)$value);
        if ($value === '' || strlen($value) > $max || preg_match('/[\x00-\x20\x7f]/', $value)) return null;
        return $value;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_array($value)) $value = $value['id'] ?? null;
        $id = filter_var($value, FILTER_VALIDATE_INT);
        return $id !== false && (int)$id > 0 ? (int)$id : null;
    }

    private static function date(mixed $value): ?string
    {
        $raw = trim((string)$value);
        foreach (['!Y-m-d', '!d.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $raw, new DateTimeZone('UTC'));
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))) {
                return $date->format('Y-m-d');
            }
        }
        return null;
    }

    private static function moneyAmount(mixed $value, bool $allowZero): ?string
    {
        if (is_array($value)) $value = $value['value'] ?? $value['amount'] ?? null;
        if (!is_string($value) && !is_int($value) && !is_float($value)) return null;
        $raw = trim(str_replace(',', '.', (string)$value));
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $raw)) return null;
        if (!$allowZero && !preg_match('/[1-9]/', $raw)) return null;
        $parts = explode('.', $raw, 2);
        $whole = ltrim($parts[0], '0');
        if ($whole === '') $whole = '0';
        $fraction = rtrim($parts[1] ?? '', '0');
        return $whole . ($fraction === '' ? '' : '.' . $fraction);
    }

    private static function receipt(bool $ok, ?string $reason, array $extra = []): array
    {
        return ['source' => 'tourvisor-anytour-offer-autosave-v1', 'ok' => $ok, 'reason' => $reason] + $extra;
    }
}
