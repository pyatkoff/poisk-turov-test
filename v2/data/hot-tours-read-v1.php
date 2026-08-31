<?php
/** Read fresh hot-tour snapshot rows from the first-party AnyTour DB. */
declare(strict_types=1);

require_once __DIR__ . '/db-v1.php';

function v2_data_hot_tours(array $filters = []): array
{
    $limit = filter_var($filters['limit'] ?? 12, FILTER_VALIDATE_INT);
    $limit = $limit === false ? 12 : max(1, min(50, (int)$limit));
    $departureId = filter_var($filters['departureId'] ?? null, FILTER_VALIDATE_INT);
    $countryId = filter_var($filters['countryId'] ?? null, FILTER_VALIDATE_INT);

    $where = ['h.expires_at > NOW()', 'h.price > 0', 'h.departure_date >= CURDATE()'];
    $params = [];
    if ($departureId !== false && (int)$departureId > 0) {
        $where[] = 'h.departure_id = :departure_id';
        $params['departure_id'] = (int)$departureId;
    }
    if ($countryId !== false && (int)$countryId > 0) {
        $where[] = 'h.country_id = :country_id';
        $params['country_id'] = (int)$countryId;
    }

    $pdo = v2_data_db();
    $sql = "WITH fresh AS (
        SELECT h.*,
               ROW_NUMBER() OVER (
                 PARTITION BY h.departure_id,h.hotel_id,h.departure_date,h.nights
                 ORDER BY h.price ASC,h.fetched_at DESC
               ) AS rn
          FROM hot_tours_current h
         WHERE " . implode(' AND ', $where) . "
    )
    SELECT snapshot_key,tour_id,departure_id,departure_name,country_id,country_name,region_id,region_name,
           subregion_id,subregion_name,hotel_id,hotel_name,hotel_category,hotel_rating,picture_url,
           departure_date,nights,meal_id,meal_name,operator_id,operator_name,price,old_price,currency,fetched_at,expires_at
      FROM fresh
     WHERE rn=1
     ORDER BY departure_date ASC,price ASC,fetched_at DESC
     LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
