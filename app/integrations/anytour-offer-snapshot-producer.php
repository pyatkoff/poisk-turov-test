<?php
declare(strict_types=1);

require_once __DIR__ . '/three-provider-search-handoff.php';

/**
 * INT-owned bridge from one complete provider refresh to the LOCAL snapshot ingestor.
 *
 * This layer performs no supplier I/O and no price/fuel arithmetic. It accepts the
 * provider-normalized offer/context plus the already-computed protected priced-money
 * facts and lets the existing INT handoff validate finalPriceReady before persistence.
 */
final class AnyTourIntOfferSnapshotProducerV1
{
    private const PROVIDERS = ['anex', 'andromeda', 'tourvisor'];
    private const REFRESH_KEYS = ['complete', 'authoritative_empty', 'offers'];
    private const OFFER_KEYS = ['anytour_hotel_id', 'offer', 'retained', 'current', 'priced_money'];
    private const VERIFIED_OFFER_KEYS = [
        'anytour_hotel_id', 'offer', 'retained', 'current', 'priced_money', 'verified_quote'
    ];
    private const CONFIRMATION_OFFER_KEYS = [
        'anytour_hotel_id', 'offer', 'retained', 'current', 'priced_money', 'confirmation_required'
    ];

    /**
     * @param callable(string,array,array,DateTimeImmutable):array $ingest
     */
    public static function produce(
        string $provider,
        array $searchParams,
        array $refresh,
        DateTimeImmutable $now,
        callable $ingest
    ): array {
        if (!in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_PROVIDER');
        }
        if (!self::exactKeys($refresh, self::REFRESH_KEYS)
            || ($refresh['complete'] ?? null) !== true
            || !is_bool($refresh['authoritative_empty'] ?? null)
            || !is_array($refresh['offers'] ?? null)
            || !array_is_list($refresh['offers'])) {
            throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_REFRESH_INCOMPLETE');
        }
        if (count($refresh['offers']) > 5000) {
            throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_TOO_MANY_OFFERS');
        }
        if ($refresh['offers'] === [] && $refresh['authoritative_empty'] !== true) {
            throw new DomainException('ANYTOUR_INT_SNAPSHOT_EMPTY_NOT_AUTHORITATIVE');
        }
        if ($refresh['offers'] !== [] && $refresh['authoritative_empty'] === true) {
            throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_EMPTY_CONTRACT');
        }

        $rows = [];
        $seen = [];
        $unresolved = 0;
        $notReady = 0;
        $confirmationCount = 0;
        $nowTs = $now->getTimestamp();

        foreach ($refresh['offers'] as $entry) {
            $verifiedShape = is_array($entry) && self::exactKeys($entry, self::VERIFIED_OFFER_KEYS);
            $confirmationShape = is_array($entry) && self::exactKeys($entry, self::CONFIRMATION_OFFER_KEYS);
            if (!is_array($entry)
                || (!self::exactKeys($entry, self::OFFER_KEYS) && !$verifiedShape && !$confirmationShape)) {
                throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_OFFER');
            }
            $offer = $entry['offer'];
            $retained = $entry['retained'];
            $current = $entry['current'];
            $pricedMoney = $entry['priced_money'];
            $verifiedQuote = $verifiedShape ? $entry['verified_quote'] : null;
            $confirmationRequired = $confirmationShape ? $entry['confirmation_required'] : false;
            if (!is_array($offer) || ($offer['provider'] ?? null) !== $provider
                || !is_array($retained) || !is_array($current)
                || ($pricedMoney !== null && !is_array($pricedMoney))
                || ($verifiedShape && !is_array($verifiedQuote))
                || ($verifiedShape && $pricedMoney !== null)
                || ($confirmationShape && ($confirmationRequired !== true || $pricedMoney !== null))
                || ($verifiedShape && $confirmationShape)) {
                throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_OFFER');
            }

            $ownId = $entry['anytour_hotel_id'];
            if ($ownId === null) {
                ++$unresolved;
                continue;
            }
            if (!is_int($ownId) || $ownId < 1) {
                throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_HOTEL');
            }

            if ($verifiedQuote !== null) {
                $dto = AnyTourThreeProviderSearchHandoff::fromVerifiedQuote(
                    $offer,
                    $retained,
                    $current,
                    $verifiedQuote,
                    $nowTs
                );
                $verifiedAmount = self::readyRubAmount($verifiedQuote['final_price'] ?? null);
                if ($verifiedAmount === null) {
                    ++$notReady;
                    continue;
                }
                $dto['finalPriceReady'] = true;
                $dto['finalPrice'] = $verifiedAmount;
                $dto['price'] = $verifiedAmount;
                $dto['currency'] = 'RUB';
            } elseif ($confirmationRequired === true) {
                $dto = AnyTourThreeProviderSearchHandoff::fromConfirmationRequiredSearchOffer(
                    $offer,
                    $retained,
                    $current,
                    $nowTs
                );
            } else {
                $dto = AnyTourThreeProviderSearchHandoff::fromCustomerSearchOffer(
                    $offer,
                    $retained,
                    $current,
                    $nowTs,
                    $pricedMoney
                );
            }
            $ready = ($dto['finalPriceReady'] ?? null) === true
                && is_string($dto['finalPrice'] ?? null)
                && ($dto['price'] ?? null) === $dto['finalPrice']
                && ($dto['currency'] ?? null) === 'RUB';
            $confirmation = ($dto['finalPriceReady'] ?? null) === false
                && ($dto['finalPrice'] ?? null) === null
                && is_string($dto['price'] ?? null)
                && ($dto['currency'] ?? null) === 'RUB'
                && $confirmationRequired === true;
            if (!$ready && !$confirmation) {
                ++$notReady;
                continue;
            }

            $identity = $dto['identity'] ?? null;
            if (!is_array($identity)) {
                throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_IDENTITY');
            }
            $identityJson = json_encode(
                self::canonical(['provider' => $provider, 'identity' => $identity]),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            $identityDigest = hash('sha256', $identityJson);
            if (isset($seen[$identityDigest])) {
                throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_DUPLICATE_IDENTITY');
            }
            $seen[$identityDigest] = true;

            $expires = $dto['context']['expires_at'] ?? null;
            if (!is_int($expires) || $expires <= $nowTs || $expires - $nowTs > 900) {
                throw new InvalidArgumentException('ANYTOUR_INT_SNAPSHOT_CONTEXT_EXPIRY');
            }
            $rows[] = [
                'anytour_hotel_id' => $ownId,
                'dto' => $dto,
                'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $expires),
            ];
            if ($confirmation) ++$confirmationCount;
        }

        if ($refresh['offers'] !== [] && $rows === []) {
            return [
                'source' => 'anytour-int-offer-snapshot-producer-v1',
                'provider' => $provider,
                'published' => false,
                'reason' => 'no_final_price_ready_resolved_offers',
                'inputOfferCount' => count($refresh['offers']),
                'readyOfferCount' => 0,
                'confirmationRequiredOfferCount' => 0,
                'unresolvedHotelCount' => $unresolved,
                'notReadyCount' => $notReady,
                'selectionAuthority' => false,
            ];
        }

        $receipt = $ingest($provider, $searchParams, $rows, $now);
        if (!is_array($receipt)) {
            throw new RuntimeException('ANYTOUR_INT_SNAPSHOT_INGEST_RECEIPT');
        }

        return [
            'source' => 'anytour-int-offer-snapshot-producer-v1',
            'provider' => $provider,
            'published' => true,
            'reason' => null,
            'inputOfferCount' => count($refresh['offers']),
            'readyOfferCount' => count($rows) - $confirmationCount,
            'confirmationRequiredOfferCount' => $confirmationCount,
            'unresolvedHotelCount' => $unresolved,
            'notReadyCount' => $notReady,
            'selectionAuthority' => false,
            'ingest' => $receipt,
        ];
    }

    private static function readyRubAmount(mixed $money): ?string
    {
        if (!is_array($money) || ($money['currency'] ?? null) !== 'RUB') return null;
        $amount = $money['amount'] ?? null;
        return is_string($amount)
            && preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $amount)
            && preg_match('/[1-9]/', $amount)
            ? $amount : null;
    }

    private static function exactKeys(array $value, array $expected): bool
    {
        return count($value) === count($expected)
            && array_diff($expected, array_keys($value)) === []
            && array_diff(array_keys($value), $expected) === [];
    }

    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map([self::class, 'canonical'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = self::canonical($item);
        return $value;
    }
}
