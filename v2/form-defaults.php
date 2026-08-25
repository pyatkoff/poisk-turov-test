<?php
/** Pure V2 form defaults. No legacy Tourvisor SDK or database catalogs. */
function v2_positive_int($value, int $fallback, int $max = PHP_INT_MAX): int
{
    $n = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
    return $n === false ? $fallback : (int)$n;
}

function v2_date_value($value, DateTimeImmutable $fallback): string
{
    $raw = trim((string)$value);
    if ($raw === '') return $fallback->format('Y-m-d');

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
        return $fallback->format('Y-m-d');
    }

    return $date->format('Y-m-d');
}

function v2_form_defaults(array $query = [], array $siteParams = []): array
{
    $from = v2_positive_int($query['from'] ?? ($siteParams['TV_CITY'] ?? 1), 1);
    $country = v2_positive_int($query['country'] ?? 4, 4);
    $today = new DateTimeImmutable('today');

    $dateFrom = v2_date_value($query['dateFrom'] ?? null, $today->modify('+1 day'));
    $dateTill = v2_date_value($query['dateTo'] ?? null, $today->modify('+14 days'));
    if ($dateTill < $dateFrom) $dateTill = $dateFrom;

    $nightsFrom = v2_positive_int($query['daysFrom'] ?? 5, 5, 28);
    $nightsTill = v2_positive_int($query['daysTill'] ?? 14, 14, 28);
    if ($nightsTill < $nightsFrom) $nightsTill = $nightsFrom;

    return [
        'from' => $from,
        'country' => $country,
        'date_from' => $dateFrom,
        'date_till' => $dateTill,
        'nights_from' => $nightsFrom,
        'nights_till' => $nightsTill,
        'count_people' => v2_positive_int($query['count_people'] ?? 2, 2, 6),
    ];
}
