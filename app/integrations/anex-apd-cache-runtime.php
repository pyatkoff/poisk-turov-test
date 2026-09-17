<?php
declare(strict_types=1);

require_once __DIR__ . '/anex-program-apd-store.php';
require_once __DIR__ . '/anex-apd-prewarm.php';

/**
 * INT-owned DB-first cache adapter for sanitized AdditionalPricesDaily evidence.
 * No supplier transport and no listing arithmetic live here.
 */
final class AnyTourAnexApdCacheRuntimeV1
{
    private const SESSION_KEY = 'anex_apd_cache_persisted';

    public static function read(PDO $db, array $context, DateTimeImmutable $now): array
    {
        $criteria = self::criteria($context);
        $saved = AnyTourAnexProgramApdStoreV1::readApd($db, $criteria, $now);
        if (($saved['fresh'] ?? false) !== true) {
            return ['hit'=>false, 'state'=>$saved['state'] ?? 'missing', 'evidence'=>null];
        }
        $state = $saved['state'] ?? null;
        if (!in_array($state, ['rate','empty','ambiguous'], true)) {
            return ['hit'=>false, 'state'=>'invalid', 'evidence'=>null];
        }

        $rows = [];
        if ($state === 'rate') {
            $rates = $saved['rates'] ?? null;
            if (!is_array($rates)
                || !is_string($rates['native_adult'] ?? null)
                || !is_string($rates['native_child'] ?? null)) {
                return ['hit'=>false, 'state'=>'invalid', 'evidence'=>null];
            }
            $rows[] = [
                'price_adult'=>$rates['native_adult'],
                'price_chd'=>$rates['native_child'],
                'cashrate'=>is_string($rates['cashrate'] ?? null) ? $rates['cashrate'] : null,
                'price_converted_adult'=>is_string($rates['adult'] ?? null) ? $rates['adult'] : null,
                'price_converted_chd'=>is_string($rates['child'] ?? null) ? $rates['child'] : null,
            ];
        }

        $total = $state === 'empty' ? 0 : (int)($saved['total_count'] ?? 0);
        $rowCount = $state === 'rate' ? 1 : 0;
        $observed = self::sqlToIso($saved['observed_at'] ?? null);
        $expires = self::sqlToIso($saved['expires_at'] ?? null);
        if ($observed === null || $expires === null) {
            return ['hit'=>false, 'state'=>'invalid', 'evidence'=>null];
        }

        return [
            'hit'=>true,
            'state'=>$state,
            'evidence'=>[
                'source'=>'anex_b2b_additional_prices_daily_cache',
                'cache_source'=>'anytour_anex_apd_rates',
                'rows'=>$rows,
                'total_count'=>$total,
                'truncated'=>$total > $rowCount,
                'scope'=>'tour_program_date_nights_currency',
                'offer_specific'=>false,
                'currency'=>null,
                'converted_currency'=>null,
                'per_person_or_package'=>'unknown',
                'fuel_equivalence_verified'=>false,
                'included_in_search_price'=>'unknown',
                'arithmetic_applied'=>false,
                'final_price_verified'=>false,
                'observed_at'=>$observed,
                'cache_expires_at'=>$expires,
            ],
        ];
    }

    public static function persist(
        PDO $db,
        array $context,
        array $evidence,
        array &$sessionState,
        DateTimeImmutable $now
    ): array {
        $criteria = self::criteria($context);
        $rows = $evidence['rows'] ?? null;
        $total = $evidence['total_count'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)
            || !is_int($total) || $total < 0 || $total < count($rows)) {
            throw new InvalidArgumentException('ANEX_APD_CACHE_EVIDENCE');
        }

        $evidenceDigest = hash('sha256', json_encode(
            ['criteria'=>$criteria,'rows'=>$rows,'total_count'=>$total],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
        $contextDigest = self::contextDigest($context);
        $persisted = $sessionState[self::SESSION_KEY] ?? null;
        if (!is_array($persisted)) $persisted = [];
        if (($persisted[$contextDigest] ?? null) === $evidenceDigest) {
            return ['stored'=>false,'reason'=>'already_persisted','state'=>null];
        }

        $observedAt = self::isoTime($evidence['observed_at'] ?? null) ?? $now;
        if ($observedAt > $now->modify('+60 seconds')) {
            throw new InvalidArgumentException('ANEX_APD_CACHE_OBSERVED_AT');
        }
        $expiresAt = $observedAt->modify(
            '+' . AnyTourAnexApdPrewarmV1::ttlSeconds($criteria['date_beg'], $observedAt) . ' seconds'
        );
        $saved = AnyTourAnexProgramApdStoreV1::recordApd(
            $db,
            $criteria,
            ['data'=>$rows,'totalCount'=>$total],
            $observedAt,
            $expiresAt
        );
        $persisted[$contextDigest] = $evidenceDigest;
        if (count($persisted) > 256) $persisted = array_slice($persisted, -256, null, true);
        $sessionState[self::SESSION_KEY] = $persisted;

        return [
            'stored'=>true,
            'reason'=>null,
            'state'=>$saved['state'] ?? null,
            'expires_at'=>$saved['expires_at'] ?? null,
        ];
    }

    private static function criteria(array $context): array
    {
        $program = self::positive($context['supplier_tour_program_id'] ?? null);
        $currency = self::positive($context['supplier_currency_id'] ?? null);
        $date = $context['checkin'] ?? null;
        $nights = $context['nights'] ?? null;
        if ($program === null || $currency === null
            || !is_string($date)
            || !preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $date, $m)
            || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])
            || !is_int($nights) || $nights < 1 || $nights > 60) {
            throw new InvalidArgumentException('ANEX_APD_CACHE_CONTEXT');
        }
        $digest = $context['context_digest'] ?? null;
        if (!is_string($digest) || !hash_equals(self::contextDigest($context, false), $digest)) {
            throw new InvalidArgumentException('ANEX_APD_CACHE_CONTEXT');
        }
        return [
            'supplier_program_id'=>$program,
            'date_beg'=>$date,
            'nights'=>$nights,
            'supplier_currency_id'=>$currency,
        ];
    }

    private static function contextDigest(array $context, bool $validate = true): string
    {
        $program = self::positive($context['supplier_tour_program_id'] ?? null);
        $currency = self::positive($context['supplier_currency_id'] ?? null);
        $date = $context['checkin'] ?? null;
        $nights = $context['nights'] ?? null;
        if ($program === null || $currency === null || !is_string($date) || !is_int($nights)) {
            if ($validate) throw new InvalidArgumentException('ANEX_APD_CACHE_CONTEXT');
            return '';
        }
        return hash('sha256', implode("\0", [(string)$program,(string)$currency,$date,(string)$nights]));
    }

    private static function positive(mixed $value): ?int
    {
        if ((!is_int($value) && !is_string($value))
            || !preg_match('/\A[1-9][0-9]{0,8}\z/D', (string)$value)) return null;
        $n=(int)$value;
        return $n > 0 && $n <= 999999999 ? $n : null;
    }

    private static function isoTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) return null;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z',$value,new DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d\\TH:i:s\\Z')===$value ? $date : null;
    }

    private static function sqlToIso(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d H:i:s')===$value
            ? $date->format('Y-m-d\\TH:i:s\\Z') : null;
    }
}

function anytour_anex_apd_cache_read_runtime(array $context): array
{
    try {
        if (!function_exists('v2_data_db')) return ['hit'=>false,'state'=>'db_unavailable','evidence'=>null];
        $db=v2_data_db();
        if (!$db instanceof PDO) return ['hit'=>false,'state'=>'db_unavailable','evidence'=>null];
        return AnyTourAnexApdCacheRuntimeV1::read(
            $db,
            $context,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    } catch (Throwable $error) {
        error_log('ANEX_APD_CACHE_READ_FAILED '.preg_replace('/[^A-Z0-9_:-]+/i','_',substr($error->getMessage(),0,120)));
        return ['hit'=>false,'state'=>'storage_unavailable','evidence'=>null];
    }
}

function anytour_anex_apd_cache_write_runtime(array $context,array $evidence,array &$state): array
{
    try {
        if (!function_exists('v2_data_db')) return ['stored'=>false,'reason'=>'db_unavailable'];
        $db=v2_data_db();
        if (!$db instanceof PDO) return ['stored'=>false,'reason'=>'db_unavailable'];
        return AnyTourAnexApdCacheRuntimeV1::persist(
            $db,
            $context,
            $evidence,
            $state,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    } catch (Throwable $error) {
        error_log('ANEX_APD_CACHE_WRITE_FAILED '.preg_replace('/[^A-Z0-9_:-]+/i','_',substr($error->getMessage(),0,120)));
        return ['stored'=>false,'reason'=>'storage_unavailable'];
    }
}
