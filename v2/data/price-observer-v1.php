<?php
/** Best-effort persistence of trusted Tourvisor search-result rows. */
declare(strict_types=1);

require_once __DIR__ . '/db-v1.php';

function v2_price_observer_date($value): ?DateTimeImmutable
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    foreach (['!Y-m-d', '!d.m.Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $raw);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))) return $date;
    }
    return null;
}

function v2_price_observer_id($value): ?int
{
    if (is_array($value)) $value = $value['id'] ?? null;
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && (int)$id > 0 ? (int)$id : null;
}

function v2_price_observer_text($value, int $max = 255): string
{
    if (is_array($value)) $value = $value['name'] ?? $value['russianName'] ?? '';
    return mb_substr(trim((string)$value), 0, $max, 'UTF-8');
}

function v2_price_observer_child_signature(array $ages): string
{
    $clean = [];
    foreach (array_slice($ages, 0, 3) as $age) {
        $n = filter_var($age, FILTER_VALIDATE_INT);
        if ($n !== false && (int)$n >= 0 && (int)$n <= 17) $clean[] = (int)$n;
    }
    return implode(',', $clean);
}

function v2_price_observer_source($value): string
{
    $source = trim((string)$value);
    return in_array($source, ['user_search', 'scheduled_monitor', 'hot_tours'], true) ? $source : 'user_search';
}

function v2_data_observe_search_results(array $hotels, array $context): array
{
    $searchId = v2_price_observer_id($context['searchId'] ?? null);
    $departureId = v2_price_observer_id($context['departureId'] ?? null);
    $contextCountryId = v2_price_observer_id($context['countryId'] ?? null);
    $adults = (int)($context['adults'] ?? 2);
    if ($searchId === null || $departureId === null || $contextCountryId === null || $adults < 1 || $adults > 6) {
        return ['written' => 0, 'ignored' => 0, 'reason' => 'missing_context'];
    }

    $childs = is_array($context['childs'] ?? null) ? $context['childs'] : [];
    $childSignature = v2_price_observer_child_signature($childs);
    $childrenCount = $childSignature === '' ? 0 : count(explode(',', $childSignature));
    $currencyDefault = strtoupper(v2_price_observer_text($context['currency'] ?? 'RUB', 8)) ?: 'RUB';
    $source = v2_price_observer_source($context['source'] ?? 'user_search');
    $observedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $pdo = v2_data_db();
    $stmt = $pdo->prepare("INSERT IGNORE INTO tour_price_observations (
        fingerprint,observed_at,source,search_id,departure_id,country_id,region_id,subregion_id,hotel_id,tour_id,
        departure_date,departure_year,departure_month,nights,adults,children_count,child_ages_signature,
        meal_id,room_id,room_type,operator_id,price,fuel_charge,currency
    ) VALUES (
        :fingerprint,:observed_at,:source,:search_id,:departure_id,:country_id,:region_id,:subregion_id,:hotel_id,:tour_id,
        :departure_date,:departure_year,:departure_month,:nights,:adults,:children_count,:child_ages_signature,
        :meal_id,:room_id,:room_type,:operator_id,:price,:fuel_charge,:currency
    )");

    $written = 0;
    $ignored = 0;
    $seenTours = 0;
    foreach (array_slice($hotels, 0, 100) as $hotel) {
        if (!is_array($hotel)) continue;
        $hotelId = v2_price_observer_id($hotel['id'] ?? null);
        if ($hotelId === null) continue;
        $countryId = v2_price_observer_id($hotel['country'] ?? null) ?? $contextCountryId;
        $regionId = v2_price_observer_id($hotel['region'] ?? null);
        $subregionId = v2_price_observer_id($hotel['subRegion'] ?? $hotel['subregion'] ?? null);
        $tours = is_array($hotel['tours'] ?? null) ? $hotel['tours'] : [];

        foreach ($tours as $tour) {
            if (!is_array($tour) || ++$seenTours > 400) break 2;
            $date = v2_price_observer_date($tour['date'] ?? null);
            $nights = (int)($tour['nights'] ?? 0);
            $price = (float)($tour['price'] ?? 0);
            if (!$date || $nights < 1 || $nights > 28 || $price <= 0) { $ignored++; continue; }

            $tourId = v2_price_observer_text($tour['id'] ?? $tour['tourId'] ?? '', 220);
            $mealId = v2_price_observer_id($tour['meal'] ?? null);
            $roomId = v2_price_observer_id($tour['room'] ?? $tour['roomId'] ?? null);
            $roomType = v2_price_observer_text($tour['roomType'] ?? '', 255);
            $operatorId = v2_price_observer_id($tour['operator'] ?? null);
            $fuel = isset($tour['fuelCharge']) && is_numeric($tour['fuelCharge']) ? (float)$tour['fuelCharge'] : null;
            $currency = strtoupper(v2_price_observer_text($tour['currency'] ?? $currencyDefault, 8)) ?: $currencyDefault;
            $dateIso = $date->format('Y-m-d');
            $fingerprint = hash('sha256', implode('|', [
                $source,$searchId,$departureId,$countryId,$hotelId,$tourId,$dateIso,$nights,$adults,$childSignature,
                $mealId ?? '',$roomId ?? '',$roomType,$operatorId ?? '',number_format($price,2,'.',''),$currency,
            ]));

            $stmt->execute([
                'fingerprint' => $fingerprint,
                'observed_at' => $observedAt,
                'source' => $source,
                'search_id' => $searchId,
                'departure_id' => $departureId,
                'country_id' => $countryId,
                'region_id' => $regionId,
                'subregion_id' => $subregionId,
                'hotel_id' => $hotelId,
                'tour_id' => $tourId !== '' ? $tourId : null,
                'departure_date' => $dateIso,
                'departure_year' => (int)$date->format('Y'),
                'departure_month' => (int)$date->format('n'),
                'nights' => $nights,
                'adults' => $adults,
                'children_count' => $childrenCount,
                'child_ages_signature' => $childSignature,
                'meal_id' => $mealId,
                'room_id' => $roomId,
                'room_type' => $roomType !== '' ? $roomType : null,
                'operator_id' => $operatorId,
                'price' => $price,
                'fuel_charge' => $fuel,
                'currency' => $currency,
            ]);
            $written += $stmt->rowCount() > 0 ? 1 : 0;
        }
    }

    return ['written' => $written, 'ignored' => $ignored, 'seen' => $seenTours, 'source' => $source];
}
