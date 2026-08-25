<?php
/** Pure V2 form defaults. No legacy Tourvisor SDK or database catalogs. */
function v2_positive_int($value, int $fallback): int
{
    $n = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $n === false ? $fallback : (int)$n;
}

function v2_form_defaults(array $query = [], array $siteParams = []): array
{
    $from = v2_positive_int($query['from'] ?? ($siteParams['TV_CITY'] ?? 1), 1);
    $country = v2_positive_int($query['country'] ?? 4, 4);
    $today = new DateTimeImmutable('today');

    return [
        'from' => $from,
        'country' => $country,
        'date_from' => $today->modify('+1 day')->format('Y-m-d'),
        'date_till' => $today->modify('+14 days')->format('Y-m-d'),
        'nights_from' => 5,
        'nights_till' => 14,
        'count_people' => 2,
    ];
}
