<?php
declare(strict_types=1);

/**
 * Direct-ANEX supplier-budget gate for the initial Search3 enrichment.
 *
 * One browser session + search generation may establish one initial date window.
 * The window is normalized to at most seven calendar days. Later search requests
 * in the same generation for a different window are skipped before supplier access.
 */
function anytour_anex_initial_week_gate(array &$state, array $request): array
{
    $generation = $request['generation'] ?? null;
    $params = $request['params'] ?? null;
    if (!is_int($generation) || $generation < 1 || $generation > 2147483647 || !is_array($params)) {
        throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    }
    $from = $params['dateFrom'] ?? null;
    $to = $params['dateTo'] ?? null;
    if (!is_string($from) || !is_string($to)
        || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $from)
        || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $to)) {
        throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    }
    try {
        $fromDate = new DateTimeImmutable($from);
        $toDate = new DateTimeImmutable($to);
    } catch (Throwable $ignored) {
        throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    }
    if ($fromDate->format('Y-m-d') !== $from || $toDate->format('Y-m-d') !== $to || $toDate < $fromDate) {
        throw new InvalidArgumentException('ANEX_INVALID_SEARCH');
    }
    $weekEnd = $fromDate->modify('+6 days');
    if ($toDate > $weekEnd) $toDate = $weekEnd;
    $normalized = [
        'generation' => $generation,
        'date_from' => $from,
        'date_to' => $toDate->format('Y-m-d'),
    ];

    if ($state === [] || ($state['generation'] ?? null) !== $generation) {
        $state = $normalized;
        return $normalized + ['allowed' => true, 'reason' => 'initial_window'];
    }
    if (!isset($state['generation'], $state['date_from'], $state['date_to'])
        || !is_int($state['generation']) || !is_string($state['date_from']) || !is_string($state['date_to'])) {
        throw new InvalidArgumentException('ANEX_INVALID_SESSION');
    }
    $same = $state['date_from'] === $normalized['date_from'] && $state['date_to'] === $normalized['date_to'];
    return $normalized + ['allowed' => $same, 'reason' => $same ? 'initial_window_retry' : 'later_window_skipped'];
}

function anytour_anex_initial_week_skipped_response(array $gate): array
{
    if (($gate['allowed'] ?? null) !== false || ($gate['reason'] ?? null) !== 'later_window_skipped'
        || !is_int($gate['generation'] ?? null) || !is_string($gate['date_from'] ?? null)
        || !is_string($gate['date_to'] ?? null)) {
        throw new InvalidArgumentException('ANEX_INVALID_SESSION');
    }
    return [
        'generation' => $gate['generation'],
        'provider' => 'anex',
        'date_range' => ['from' => $gate['date_from'], 'to' => $gate['date_to']],
        'hotels' => [],
        'external_search_pending' => false,
        'pages_read' => 0,
        'first_page_only' => true,
        'skipped' => true,
        'skip_reason' => 'initial_week_only',
    ];
}
