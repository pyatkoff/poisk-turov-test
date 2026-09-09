<?php
/** Resolve a departure city name for user-facing phrases. */
declare(strict_types=1);

function v2_departure_name_for_phrase(array $departure): string
{
    $genitive = trim((string)($departure['name_genitive'] ?? ''));
    if ($genitive !== '') return $genitive;
    return trim((string)($departure['name'] ?? ''));
}
