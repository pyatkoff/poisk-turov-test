<?php
/** Read fresh resort offer snapshots for SEO pages without issuing Tourvisor requests. */
declare(strict_types=1);

require_once __DIR__ . '/data/db-v1.php';

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
