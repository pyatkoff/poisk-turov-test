<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/form-defaults.php';
require_once dirname(__DIR__) . '/seo-page-primitives-v1.php';

/** Prefill the existing search form; this editorial page cannot resume an offer. */
function jaz_makadi_saraya_handoff(array $query): array
{
    $state = ['country' => 1, 'region' => 5, 'hotel' => 268];
    $extra = ['search3_restore' => 1];
    $notices = [];
    $invalid = false;

    $integers = [
        'from' => [['from'], 1, PHP_INT_MAX],
        'daysFrom' => [['days_from', 'daysFrom'], 1, 28],
        'daysTill' => [['days_till', 'daysTill'], 1, 28],
        'count_people' => [['count_people'], 1, 6],
        'operator' => [['operator'], 0, PHP_INT_MAX],
        'arrival' => [['arrival'], 1, PHP_INT_MAX],
        'hotel_type' => [['hotel_type'], 1, PHP_INT_MAX],
        'food' => [['food'], 0, PHP_INT_MAX],
        'stars' => [['stars'], 0, 5],
    ];
    foreach ($integers as $key => [$aliases, $min, $max]) {
        $raw = v2_query_alias($query, $aliases);
        if ($raw === null || $raw === '') continue;
        $value = is_scalar($raw) ? filter_var($raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max],
        ]) : false;
        if ($value === false) $invalid = true;
        elseif (in_array($key, ['arrival', 'hotel_type'], true)) $extra[$key] = $value;
        else $state[$key] = $value;
    }

    foreach (['dateFrom' => ['date_from', 'dateFrom'], 'dateTo' => ['date_till', 'dateTo']] as $key => $aliases) {
        $raw = v2_query_alias($query, $aliases);
        if ($raw === null || $raw === '') continue;
        $date = is_string($raw) && !str_contains($raw, "\0")
            ? DateTimeImmutable::createFromFormat('!Y-m-d', trim($raw)) : false;
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
            $invalid = true;
        } else {
            $state[$key] = $date->format('Y-m-d');
        }
    }
    if (isset($state['dateFrom']) && $state['dateFrom'] < (new DateTimeImmutable('today'))->format('Y-m-d')) {
        $notices[] = 'Даты в ссылке уже прошли. Выберите новые даты в поиске.';
    }
    if ((isset($state['dateFrom'], $state['dateTo']) && $state['dateTo'] < $state['dateFrom'])
        || (isset($state['daysFrom'], $state['daysTill']) && $state['daysTill'] < $state['daysFrom'])) {
        $notices[] = 'Проверьте диапазон дат и количество ночей: в ссылке конец диапазона указан раньше начала.';
    }

    foreach (['rating', 'price_from', 'price_till'] as $key) {
        if (!isset($query[$key]) || $query[$key] === '') continue;
        $value = is_scalar($query[$key]) ? filter_var($query[$key], FILTER_VALIDATE_FLOAT) : false;
        if ($value === false || !is_finite($value) || $value < 0 || ($key === 'rating' && $value > 10)) {
            $invalid = true;
        } elseif ($key === 'rating') {
            $state[$key] = $value;
        } else {
            // A requested budget is a filter, never a verified hotel price.
            $extra[$key] = $value;
        }
    }
    foreach (['onlyDirect' => ['onlyDirect', 'only_direct'], 'onlyCharter' => ['onlyCharter', 'only_charter']] as $key => $aliases) {
        $raw = v2_query_alias($query, $aliases);
        if ($raw === null || $raw === '') continue;
        $value = is_scalar($raw) ? filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        if ($value === null) $invalid = true;
        else $state[$key] = $value ? 1 : 0;
    }

    $ages = [];
    if (array_key_exists('child_age', $query) || array_key_exists('child_count', $query)) {
        $rawAges = $query['child_age'] ?? [];
        if (!is_array($rawAges)) $rawAges = [$rawAges];
        $count = array_key_exists('child_count', $query)
            ? filter_var($query['child_count'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3]])
            : count($rawAges);
        $complete = $count !== false && $count <= 3 && $count === count($rawAges);
        foreach ($rawAges as $rawAge) {
            $age = is_scalar($rawAge) ? filter_var($rawAge, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 17]]) : false;
            if ($age === false) $complete = false;
            else $ages[] = $age;
        }
        if ($complete) {
            $extra['child_count'] = $count;
        } else {
            // The consumer defaults missing ages to 7. Never create a partial family.
            $ages = [];
            $notices[] = 'Состав детей в ссылке указан не полностью или с ошибкой. Укажите его заново в поиске: возраст каждого ребёнка нужен для подбора тура.';
        }
    }
    $services = [];
    if (isset($query['hotel_service']) && $query['hotel_service'] !== '') {
        $rawServices = is_array($query['hotel_service']) ? $query['hotel_service'] : [$query['hotel_service']];
        foreach ($rawServices as $rawService) {
            $service = is_scalar($rawService) ? filter_var($rawService, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
            if ($service === false) $invalid = true;
            else $services[] = $service;
        }
    }
    if (!empty($query['subregion'])) $notices[] = 'Фильтр района из ссылки не перенесён: для этой страницы выбран JAZ Makadi Saraya. При необходимости уточните фильтры в поиске.';
    if ($invalid) $notices[] = 'Часть условий в ссылке указана некорректно и не перенесена. Проверьте параметры поездки и фильтры в поиске.';

    $url = v2_seo_search_handoff_url('/poisk-turov/', $state);
    $url .= '&' . http_build_query($extra, '', '&', PHP_QUERY_RFC3986);
    foreach ($ages as $age) {
        // The real consumer reads literal child_age[] keys, not child_age[0].
        $url .= '&child_age%5B%5D=' . rawurlencode((string)$age);
    }
    foreach ($services as $service) $url .= '&hotel_service%5B%5D=' . rawurlencode((string)$service);
    return ['url' => $url, 'notices' => $notices];
}
