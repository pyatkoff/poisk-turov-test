<?php
/** Build an exact once-per-day collection plan for owner priority hotels. */
declare(strict_types=1);

function v2_top500_daily_plan(array $priorityHotelIds, array $hotelRows, array $departureCountryRows, string $dateFrom, string $dateTo, int $preferredDepartureId = 1): array
{
    $priority = array_values(array_unique(array_filter(array_map('intval', $priorityHotelIds), static fn(int $id): bool => $id > 0)));
    if ($priority === []) throw new InvalidArgumentException('priority hotel list is empty');
    if (count($priority) !== count($priorityHotelIds)) throw new InvalidArgumentException('priority hotel list must contain unique positive ids');

    $from = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFrom);
    $to = DateTimeImmutable::createFromFormat('!Y-m-d', $dateTo);
    if (!$from || !$to || $to < $from || $from->diff($to)->days > 20) throw new InvalidArgumentException('daily window must be a valid <=21 day range');

    $prioritySet = array_fill_keys($priority, true);
    $hotelById = [];
    foreach ($hotelRows as $row) {
        if (!is_array($row)) continue;
        $id = (int)($row['id'] ?? 0);
        if (!isset($prioritySet[$id])) continue;
        $country = (int)($row['country_id'] ?? 0);
        $active = (int)($row['is_active'] ?? 0) === 1;
        if ($country <= 0 || !$active) continue;
        $hotelById[$id] = [
            'id' => $id,
            'country_id' => $country,
            'country_name' => trim((string)($row['country_name'] ?? '')),
        ];
    }

    $missing = [];
    foreach ($priority as $id) if (!isset($hotelById[$id])) $missing[] = $id;
    if ($missing !== []) throw new RuntimeException('priority hotels missing/inactive in catalog: '.implode(',', $missing));

    $departuresByCountry = [];
    foreach ($departureCountryRows as $row) {
        if (!is_array($row) || (int)($row['is_active'] ?? 1) !== 1) continue;
        $country = (int)($row['country_id'] ?? 0);
        $departure = (int)($row['departure_id'] ?? 0);
        if ($country <= 0 || $departure <= 0) continue;
        $departuresByCountry[$country][] = $departure;
    }
    foreach ($departuresByCountry as &$ids) {
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        if (in_array($preferredDepartureId, $ids, true)) {
            $ids = array_values(array_merge([$preferredDepartureId], array_filter($ids, static fn(int $id): bool => $id !== $preferredDepartureId)));
        }
    }
    unset($ids);

    $byCountry = [];
    foreach ($priority as $id) {
        $hotel = $hotelById[$id];
        $country = $hotel['country_id'];
        if (!isset($departuresByCountry[$country][0])) throw new RuntimeException('no active departure for country '.$country.' hotel '.$id);
        if (!isset($byCountry[$country])) $byCountry[$country] = ['country_name'=>$hotel['country_name'], 'ids'=>[]];
        $byCountry[$country]['ids'][] = $id;
    }

    ksort($byCountry, SORT_NUMERIC);
    $targets = [];
    $covered = [];
    foreach ($byCountry as $countryId => $group) {
        $departureId = $departuresByCountry[$countryId][0];
        foreach (array_chunk($group['ids'], 30) as $index => $batch) {
            if (count($batch) > 30) throw new RuntimeException('hotel batch exceeds 30');
            foreach ($batch as $id) {
                if (isset($covered[$id])) throw new RuntimeException('duplicate hotel in daily plan: '.$id);
                $covered[$id] = true;
            }
            $targets[] = [
                'criterion' => 'hotel_batch',
                'target_key' => 'daily:'.$dateFrom.':'.$departureId.':'.$countryId.':b'.($index + 1),
                'departure_id' => $departureId,
                'country_id' => (int)$countryId,
                'country_name' => (string)$group['country_name'],
                'region_id' => null,
                'hotel_ids' => array_values($batch),
                'priority' => true,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];
        }
    }

    if (count($covered) !== count($priority)) throw new RuntimeException('daily plan does not cover every priority hotel');
    return [
        'state' => 'top500_daily_exact_plan',
        'hotel_count' => count($priority),
        'batch_count' => count($targets),
        'max_batch_size' => $targets === [] ? 0 : max(array_map(static fn(array $x): int => count($x['hotel_ids']), $targets)),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'targets' => $targets,
    ];
}
