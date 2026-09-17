<?php
declare(strict_types=1);

require_once __DIR__ . '/anex-program-apd-store.php';

/**
 * INT-owned best-effort runtime persistence for program coverage learned from
 * already-retained direct-ANEX search/expand offers. No supplier I/O.
 */
final class AnyTourAnexProgramObservationRuntimeV1
{
    private const MAX_SAVED_OFFERS = 300;
    private const MAX_SESSION_CONTEXTS = 1200;

    public static function record(PDO $db, array &$state, DateTimeImmutable $now): array
    {
        $params = $state['params'] ?? null;
        $saved = $state['gateway']['saved_offers']['offers'] ?? null;
        if (!is_array($params) || !is_array($saved)) {
            return self::receipt('not_applicable', 0, 0, 0, 0);
        }

        $departure = self::positiveInt($params['departureId'] ?? null);
        $country = self::positiveInt($params['countryId'] ?? null);
        if ($departure === null || $country === null) {
            return self::receipt('invalid_scope', 0, 0, 0, 0);
        }

        $runtime = $state['anex_program_observations'] ?? null;
        if (!is_array($runtime) || !is_array($runtime['contexts'] ?? null)) {
            $runtime = ['contexts' => []];
        }
        $seen = $runtime['contexts'];
        $persisted = 0;
        $skippedSeen = 0;
        $skippedInvalid = 0;
        $examined = 0;

        foreach (array_slice($saved, 0, self::MAX_SAVED_OFFERS, true) as $entry) {
            ++$examined;
            if (!is_array($entry) || !is_array($entry['offer'] ?? null)) {
                ++$skippedInvalid;
                continue;
            }
            $offer = $entry['offer'];
            $program = self::positiveInt($entry['supplier_tour_program_id'] ?? null);
            $currency = self::positiveInt($entry['supplier_currency_id'] ?? null);
            $date = $offer['checkin'] ?? null;
            $nights = $offer['nights'] ?? null;
            if ($program === null || $currency === null
                || !is_string($date) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $date)
                || !is_int($nights) || $nights < 1 || $nights > 60) {
                ++$skippedInvalid;
                continue;
            }
            $flight = $offer['flight_type'] ?? null;
            if (!in_array($flight, ['regular', 'charter'], true)) $flight = 'unknown';

            $contextDigest = hash('sha256', implode("\0", [
                (string)$program, (string)$departure, (string)$country, (string)$currency,
                $date, (string)$nights, $flight,
            ]));
            if (isset($seen[$contextDigest])) {
                ++$skippedSeen;
                continue;
            }

            $observedAt = $now;
            $observed = $entry['observed_at'] ?? null;
            if (is_int($observed) && $observed > 0 && $observed <= $now->getTimestamp()) {
                $observedAt = (new DateTimeImmutable('@' . $observed))->setTimezone(new DateTimeZone('UTC'));
            }

            AnyTourAnexProgramApdStoreV1::recordProgram($db, [
                'supplier_program_id' => $program,
                'departure_id' => $departure,
                'country_id' => $country,
                'supplier_currency_id' => $currency,
                'flight_class' => $flight,
                'departure_date' => $date,
                'nights' => $nights,
            ], $observedAt);
            $seen[$contextDigest] = true;
            ++$persisted;
        }

        if (count($seen) > self::MAX_SESSION_CONTEXTS) {
            $seen = array_slice($seen, -self::MAX_SESSION_CONTEXTS, null, true);
        }
        $state['anex_program_observations'] = ['contexts' => $seen];

        return self::receipt('complete', $examined, $persisted, $skippedSeen, $skippedInvalid);
    }

    private static function positiveInt(mixed $value): ?int
    {
        if ((!is_int($value) && !is_string($value))
            || !preg_match('/\A[1-9][0-9]{0,8}\z/D', (string)$value)) return null;
        $number = (int)$value;
        return $number > 0 && $number <= 999999999 ? $number : null;
    }

    private static function receipt(string $status, int $examined, int $persisted, int $seen, int $invalid): array
    {
        return [
            'source' => 'anex-program-observation-runtime-v1',
            'status' => $status,
            'examined' => $examined,
            'persisted' => $persisted,
            'skipped_seen' => $seen,
            'skipped_invalid' => $invalid,
            'supplier_calls' => 0,
        ];
    }
}

/**
 * Persistence is intentionally non-blocking for the customer search path.
 * A missing migration or transient DB write failure is logged and contained.
 */
function anytour_anex_program_observation_runtime(PDO $db, array &$state): array
{
    try {
        return AnyTourAnexProgramObservationRuntimeV1::record(
            $db,
            $state,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    } catch (Throwable $error) {
        error_log('ANEX_PROGRAM_OBSERVATION_FAILED ' . preg_replace(
            '/[^A-Z0-9_:-]+/i',
            '_',
            substr($error->getMessage(), 0, 120)
        ));
        return [
            'source' => 'anex-program-observation-runtime-v1',
            'status' => 'storage_unavailable',
            'examined' => 0,
            'persisted' => 0,
            'skipped_seen' => 0,
            'skipped_invalid' => 0,
            'supplier_calls' => 0,
        ];
    }
}
