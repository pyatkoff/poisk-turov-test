<?php
/** Pure batching of trusted result rows; no supplier calls or global tour cap. */
declare(strict_types=1);

function v2_observe_search_result_rows(array $payload): array
{
    if (array_is_list($payload)) return $payload;
    foreach (['hotels', 'items', 'results'] as $key) {
        if (isset($payload[$key]) && is_array($payload[$key]) && array_is_list($payload[$key])) return $payload[$key];
    }
    throw new RuntimeException('Unrecognized search results; not an empty result');
}

/** Every tour in the returned hotels is visited in bounded writer calls. */
function v2_observe_search_batches(array $hotels, array $context, callable $write): array
{
    $totals = ['written'=>0, 'ignored'=>0, 'seen'=>0, 'imagesCaptured'=>0, 'rows'=>0];
    foreach ($hotels as $hotel) {
        if (!is_array($hotel)) continue;
        $id = is_array($hotel['id'] ?? null) ? ($hotel['id']['id'] ?? null) : ($hotel['id'] ?? null);
        if (filter_var($id, FILTER_VALIDATE_INT) === false || (int)$id <= 0) throw new RuntimeException('Missing trusted hotel identity');
        $country = $hotel['country'] ?? null;
        $country = is_array($country) ? ($country['id'] ?? null) : $country;
        if (is_numeric($country) && (int)$country > 0 && (int)$country !== (int)$context['countryId']) continue;
        if (!isset($hotel['tours']) || !is_array($hotel['tours']) || !array_is_list($hotel['tours'])) throw new RuntimeException('Missing trusted hotel tours');
        $totals['rows']++;
        // Preserve the existing low-level writer and idempotent fingerprints.
        // Its 400-tour bound applies to a chunk, no longer the entire search.
        $chunks = array_chunk($hotel['tours'], 400);
        if ($chunks === []) $chunks = [[]];
        foreach ($chunks as $tours) {
            $row = $hotel; $row['tours'] = $tours;
            $result = $write([$row], array_replace($context, ['source'=>'user_search', 'maxHotels'=>1, 'maxTours'=>400]));
            if (!is_array($result) || isset($result['reason']) || (int)($result['seen'] ?? -1) !== count($tours)) throw new RuntimeException('Incomplete price writer result');
            foreach (['written','ignored','seen','imagesCaptured'] as $key) $totals[$key] += (int)($result[$key] ?? 0);
        }
    }
    return $totals;
}
