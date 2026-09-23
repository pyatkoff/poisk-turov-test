<?php
/** Stable comparable-segment identity for AnyTour price history. */
declare(strict_types=1);

function v2_price_segment_room_type(mixed $value): string
{
    // Deliberately preserve the stored room label exactly (apart from the DB-sized
    // truncation). For price claims, false negatives are safer than merging two
    // room products whose names only look similar after normalization.
    return mb_substr((string)$value, 0, 180, 'UTF-8');
}

function v2_price_segment_date(mixed $value): string
{
    $raw = trim((string)$value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) || $date->format('Y-m-d') !== $raw) {
        throw new InvalidArgumentException('departure_date must use a real YYYY-MM-DD calendar date');
    }
    return $raw;
}

function v2_price_segment_fingerprint(array $row): string
{
    $parts = [
        'v1',
        (int)($row['departure_id'] ?? 0),
        (int)($row['hotel_id'] ?? 0),
        v2_price_segment_date($row['departure_date'] ?? ''),
        (int)($row['nights'] ?? 0),
        (int)($row['adults'] ?? 0),
        (int)($row['children_count'] ?? 0),
        trim((string)($row['child_ages_signature'] ?? '')),
        (int)($row['meal_id'] ?? 0),
        (int)($row['room_id'] ?? 0),
        v2_price_segment_room_type($row['room_type'] ?? ''),
        (int)($row['operator_id'] ?? 0),
        strtoupper(trim((string)($row['currency'] ?? 'RUB'))),
    ];

    if ($parts[1] <= 0 || $parts[2] <= 0 || $parts[4] <= 0 || $parts[5] <= 0 || $parts[12] === '') {
        throw new InvalidArgumentException('incomplete comparable price segment');
    }

    return hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

/**
 * Customer-facing comparable identity.
 *
 * This intentionally ignores tour operator. A traveller comparing the same hotel,
 * departure/date, stay, party, meal and room should see one price history even when
 * the cheapest operator changes. The strict fingerprint above remains authoritative
 * for operator-specific evidence.
 *
 * Current tour_price_* rows still carry Tourvisor legacy hotel IDs. Cross-provider
 * canonical AnyTour history is a separate persistence step; do not infer it here.
 */
function v2_price_consumer_segment_fingerprint(array $row): string
{
    $parts = [
        'consumer-v1',
        (int)($row['departure_id'] ?? 0),
        (int)($row['hotel_id'] ?? 0),
        v2_price_segment_date($row['departure_date'] ?? ''),
        (int)($row['nights'] ?? 0),
        (int)($row['adults'] ?? 0),
        (int)($row['children_count'] ?? 0),
        trim((string)($row['child_ages_signature'] ?? '')),
        (int)($row['meal_id'] ?? 0),
        (int)($row['room_id'] ?? 0),
        v2_price_segment_room_type($row['room_type'] ?? ''),
        strtoupper(trim((string)($row['currency'] ?? 'RUB'))),
    ];

    if ($parts[1] <= 0 || $parts[2] <= 0 || $parts[4] <= 0 || $parts[5] <= 0 || $parts[11] === '') {
        throw new InvalidArgumentException('incomplete consumer comparable price segment');
    }

    return hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
