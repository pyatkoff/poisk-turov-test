<?php
/** Read fresh materialized offer snapshots for SEO pages without issuing Tourvisor requests. */
declare(strict_types=1);

require_once __DIR__ . '/data/db-v1.php';
require_once __DIR__ . '/data/price-intelligence-v1.php';

/**
 * Attach read-only price intelligence to the small set of offers that will
 * actually be rendered. This remains DB-only and fails open to the plain
 * current-price card when exact history is unavailable.
 */
function v2_seo_enrich_offer_prices(array $offers, int $historyDays = 30): array
{
    if ($offers === []) return [];
    $historyDays = max(7, min(90, $historyDays));
    $segments = [];
    foreach ($offers as $offer) {
        if (!is_array($offer)) continue;
        $segment = strtolower(trim((string)($offer['segmentFingerprint'] ?? '')));
        $price = (float)($offer['price'] ?? 0);
        if (preg_match('/^[a-f0-9]{64}$/', $segment) && $price > 0) $segments[$segment] = true;
    }
    if ($segments === []) return $offers;

    try {
        $pdo = v2_data_db();
        $ids = array_keys($segments);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $fromDate = (new DateTimeImmutable('today'))->modify('-' . ($historyDays - 1) . ' days')->format('Y-m-d');
        $stmt = $pdo->prepare(
            "SELECT segment_fingerprint,price_date,min_price,median_price,max_price,observation_count,independent_search_count
               FROM tour_price_daily_exact
              WHERE segment_fingerprint IN ({$placeholders})
                AND price_date>=?
              ORDER BY segment_fingerprint,price_date"
        );
        $stmt->execute(array_merge($ids, [$fromDate]));
        $history = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $segment = strtolower((string)($row['segment_fingerprint'] ?? ''));
            if (isset($segments[$segment])) $history[$segment][] = $row;
        }
    } catch (Throwable $e) {
        return $offers;
    }

    foreach ($offers as &$offer) {
        if (!is_array($offer)) continue;
        $segment = strtolower(trim((string)($offer['segmentFingerprint'] ?? '')));
        $price = (float)($offer['price'] ?? 0);
        if (!isset($segments[$segment]) || $price <= 0) continue;
        $summary = v2_price_intelligence_summary($history[$segment] ?? [], $price);
        if (($summary['ok'] ?? false) === true) $offer['priceIntelligence'] = $summary;
    }
    unset($offer);
    return $offers;
}

/**
 * Server-render the commercial price block. A crossed reference is always an
 * actually observed price for the exact comparable segment, never a synthetic
 * MSRP or raw maximum from a mixed search result.
 */
function v2_seo_offer_price_markup(array $offer): string
{
    $price = (float)($offer['price'] ?? 0);
    $currency = (string)($offer['currency'] ?? 'RUB');
    $current = htmlspecialchars(v2_seo_offer_price_label($price, $currency), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $intel = is_array($offer['priceIntelligence'] ?? null) ? $offer['priceIntelligence'] : [];
    $promo = ($intel['showPromoDrop'] ?? false) === true;
    $strong = ($intel['showHistoricalDrop'] ?? false) === true;
    $good = ($intel['goodPrice'] ?? false) === true;

    if ($promo) {
        $referencePrice = (float)($intel['referencePrice'] ?? 0);
        $drop = (int)($intel['historicalDropPercent'] ?? 0);
        if ($referencePrice > $price && $drop >= 5) {
            $reference = htmlspecialchars(v2_seo_offer_price_label($referencePrice, $currency), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $referenceDate = trim((string)($intel['referenceDate'] ?? ''));
            $dateLabel = $referenceDate !== '' ? v2_seo_offer_date_label($referenceDate) : '';
            $title = 'Ранее наблюдавшаяся цена этого же тура' . ($dateLabel !== '' ? ' — ' . $dateLabel : '');
            $title = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $note = $strong ? 'Цена снизилась' : 'Выгоднее недавней цены';
            $mode = $strong ? 'guarded' : 'recent';
            return '<div class="sp-offer-price sp-offer-price--promo" data-price-promo="'.$mode.'">'
                .'<div class="sp-offer-price-flags"><span class="sp-offer-price-kicker">от</span><span class="sp-offer-discount-badge">−'.$drop.'%</span></div>'
                .'<div class="sp-offer-price-values"><del title="'.$title.'">'.$reference.'</del><strong>'.$current.'</strong></div>'
                .'<small class="sp-offer-price-note">'.$note.'</small></div>';
        }
    }

    return '<div class="sp-offer-price"><span>от</span><strong>'.$current.'</strong>'
        .($good ? '<small class="sp-offer-good-price">Хорошая цена</small>' : '')
        .'</div>';
}

/**
 * Read fresh package-tour snapshots for one country from first-party observations.
 * Fails closed when data is unavailable or stale and never calls Tourvisor.
 */
function v2_seo_country_snapshot_offers(int $countryId, int $limit = 6): array
{
    if ($countryId <= 0) return [];
    $limit = max(1, min(12, $limit));

    try {
        $pdo = v2_data_db();
        $stmt = $pdo->prepare(
            "SELECT s.departure_id,s.offers_json,s.observed_at,s.expires_at,COALESCE(d.name,'') departure_name
               FROM seo_offer_snapshots s
               LEFT JOIN catalog_departures d ON d.id=s.departure_id
              WHERE s.page_type='country'
                AND s.country_id=:country_id
                AND s.expires_at>=NOW()
                AND s.offer_count>0
                AND s.currency='RUB'
              ORDER BY s.observed_at DESC,s.min_price ASC
              LIMIT 24"
        );
        $stmt->execute(['country_id' => $countryId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $offers = [];
    $seen = [];
    foreach ($rows as $row) {
        $decoded = json_decode((string)($row['offers_json'] ?? ''), true);
        if (!is_array($decoded)) continue;
        foreach ($decoded as $offer) {
            if (!is_array($offer)) continue;
            $hotelId = (int)($offer['hotelId'] ?? 0);
            $price = (float)($offer['price'] ?? 0);
            $departureDate = trim((string)($offer['departureDate'] ?? ''));
            $nights = (int)($offer['nights'] ?? 0);
            if ($hotelId <= 0 || $price <= 0 || $departureDate === '' || $nights <= 0) continue;

            $departureId = (int)($row['departure_id'] ?? 0);
            $key = $departureId . ':' . $hotelId . ':' . $departureDate . ':' . $nights;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $offer['departureId'] = $departureId;
            $offer['departureName'] = trim((string)$row['departure_name']);
            $offer['snapshotObservedAt'] = (string)$row['observed_at'];
            $offers[] = $offer;
        }
    }

    usort($offers, static function (array $a, array $b): int {
        $price = ((float)$a['price']) <=> ((float)$b['price']);
        if ($price !== 0) return $price;
        return strcmp((string)$a['departureDate'], (string)$b['departureDate']);
    });

    return v2_seo_enrich_offer_prices(array_slice($offers, 0, $limit));
}

function v2_seo_resort_snapshot_offers(int $countryId, int $regionId, int $limit = 6): array
{
    if ($countryId <= 0 || $regionId <= 0) return [];
    $limit = max(1, min(12, $limit));

    try {
        $pdo = v2_data_db();
        $stmt = $pdo->prepare(
            "SELECT s.departure_id,s.offers_json,s.observed_at,s.expires_at,COALESCE(d.name,'') departure_name
               FROM seo_offer_snapshots s
               LEFT JOIN catalog_departures d ON d.id=s.departure_id
              WHERE s.page_type='resort'
                AND s.country_id=:country_id
                AND s.region_id=:region_id
                AND s.expires_at>=NOW()
                AND s.offer_count>0
                AND s.currency='RUB'
              ORDER BY s.observed_at DESC,s.min_price ASC
              LIMIT 24"
        );
        $stmt->execute(['country_id' => $countryId, 'region_id' => $regionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $offers = [];
    $seen = [];
    foreach ($rows as $row) {
        $decoded = json_decode((string)($row['offers_json'] ?? ''), true);
        if (!is_array($decoded)) continue;
        foreach ($decoded as $offer) {
            if (!is_array($offer)) continue;
            $hotelId = (int)($offer['hotelId'] ?? 0);
            $price = (float)($offer['price'] ?? 0);
            $departureDate = trim((string)($offer['departureDate'] ?? ''));
            $nights = (int)($offer['nights'] ?? 0);
            if ($hotelId <= 0 || $price <= 0 || $departureDate === '' || $nights <= 0) continue;

            $key = (int)$row['departure_id'] . ':' . $hotelId . ':' . $departureDate . ':' . $nights;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $offer['departureId'] = (int)$row['departure_id'];
            $offer['departureName'] = trim((string)$row['departure_name']);
            $offer['snapshotObservedAt'] = (string)$row['observed_at'];
            $offers[] = $offer;
        }
    }

    usort($offers, static function (array $a, array $b): int {
        $price = ((float)$a['price']) <=> ((float)$b['price']);
        if ($price !== 0) return $price;
        return strcmp((string)$a['departureDate'], (string)$b['departureDate']);
    });

    return v2_seo_enrich_offer_prices(array_slice($offers, 0, $limit));
}

/**
 * Read fresh package-tour snapshots for one verified hotel.
 * Fails closed when data is unavailable or stale and never calls Tourvisor.
 */
function v2_seo_hotel_snapshot_offers(int $countryId, int $hotelId, int $limit = 6): array
{
    if ($countryId <= 0 || $hotelId <= 0) return [];
    $limit = max(1, min(12, $limit));

    try {
        $pdo = v2_data_db();
        $stmt = $pdo->prepare(
            "SELECT s.departure_id,s.offers_json,s.observed_at,s.expires_at,COALESCE(d.name,'') departure_name
               FROM seo_offer_snapshots s
               LEFT JOIN catalog_departures d ON d.id=s.departure_id
              WHERE s.page_type='hotel'
                AND s.country_id=:country_id
                AND s.hotel_id=:hotel_id
                AND s.expires_at>=NOW()
                AND s.offer_count>0
                AND s.currency='RUB'
              ORDER BY s.observed_at DESC,s.min_price ASC
              LIMIT 24"
        );
        $stmt->execute(['country_id' => $countryId, 'hotel_id' => $hotelId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $offers = [];
    $seen = [];
    foreach ($rows as $row) {
        $decoded = json_decode((string)($row['offers_json'] ?? ''), true);
        if (!is_array($decoded)) continue;
        foreach ($decoded as $offer) {
            if (!is_array($offer) || (int)($offer['hotelId'] ?? 0) !== $hotelId) continue;
            $price = (float)($offer['price'] ?? 0);
            $departureDate = trim((string)($offer['departureDate'] ?? ''));
            $nights = (int)($offer['nights'] ?? 0);
            if ($price <= 0 || $departureDate === '' || $nights <= 0) continue;

            $departureId = (int)($row['departure_id'] ?? 0);
            $key = $departureId . ':' . $departureDate . ':' . $nights . ':' . $price;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $offer['departureId'] = $departureId;
            $offer['departureName'] = trim((string)$row['departure_name']);
            $offer['snapshotObservedAt'] = (string)$row['observed_at'];
            $offers[] = $offer;
        }
    }

    usort($offers, static function (array $a, array $b): int {
        $price = ((float)$a['price']) <=> ((float)$b['price']);
        if ($price !== 0) return $price;
        return strcmp((string)$a['departureDate'], (string)$b['departureDate']);
    });

    return array_slice($offers, 0, $limit);
}

function v2_seo_offer_price_label(float $price, string $currency): string
{
    $currency = strtoupper(trim($currency));
    $suffix = $currency === 'RUB' ? ' ₽' : ($currency !== '' ? ' ' . $currency : '');
    return number_format($price, 0, '.', ' ') . $suffix;
}

function v2_seo_offer_date_label(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed ? $parsed->format('d.m.Y') : $date;
}
