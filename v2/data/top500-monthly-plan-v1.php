<?php
/** Calendar-wide TOP500 queue. No network, DB mutations or page-publication policy. */
declare(strict_types=1);
require_once __DIR__.'/top500-daily-plan-v1.php';

function v2_top500_monthly_plan(array $ids, array $hotels, array $departures, string $today, int $months = 12, int $departureId = 1): array
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if (!$date || $date->format('Y-m-d') !== $today || $months < 1 || $months > 18) throw new InvalidArgumentException('invalid monthly horizon');
    $first = $date->modify('+1 day');
    $base = v2_top500_daily_plan($ids, $hotels, $departures, $first->format('Y-m-d'), $first->format('Y-m-d'), $departureId);
    $anchor = $date->modify('first day of this month');
    $periods = []; $windows = [];
    for ($m = 0; $m < $months; $m++) {
        $start = $anchor->modify('+'.$m.' months');
        $end = $start->modify('last day of this month');
        $period = $start->format('Y-m'); $periods[] = $period;
        for ($d = 0, $w = 0; $d < (int)$end->format('j'); $d += 7, $w++) {
            $from = $start->modify('+'.$d.' days');
            $to = min($from->modify('+6 days'), $end);
            if ($to < $first) continue;
            $from = max($from, $first);
            $windows[$w][$period] = [$from->format('Y-m-d'), $to->format('Y-m-d')];
        }
    }
    // Cycle all months within each country/batch, then advance the country.
    // A large country catalog must not consume a whole worker pass on one month.
    // Stable keys and existing attempts are independent of this ordering.
    $countryBatches = [];
    foreach ($base['targets'] as $batch) $countryBatches[$batch['country_id']][] = $batch;
    $rounds = [];
    foreach ($countryBatches as $batches) foreach ($batches as $i => $batch) $rounds[$i][] = $batch;
    ksort($rounds);
    $targets = [];
    foreach ($windows as $byMonth) {
        foreach ($rounds as $round) {
            foreach ($round as $batch) {
                foreach ($byMonth as $period => [$from, $to]) {
                    $hotelIds = $batch['hotel_ids']; sort($hotelIds, SORT_NUMERIC);
                    $digest = substr(hash('sha256', implode(',', $hotelIds)), 0, 16);
                    $target = $batch;
                    $target['target_key'] = 'monthly:'.$period.':'.$from.':'.$to.':'.$batch['departure_id'].':'.$batch['country_id'].':'.$digest;
                    $target['date_from'] = $from; $target['date_to'] = $to; $target['month'] = $period;
                    $targets[] = $target;
                }
            }
        }
    }
    $base['state'] = 'top500_monthly_plan'; $base['months'] = $periods;
    $base['window_days'] = 7; $base['targets'] = $targets;
    $base['batch_count'] = count($targets);
    $base['date_from'] = $first->format('Y-m-d');
    $base['date_to'] = $anchor->modify('+'.($months - 1).' months')->modify('last day of this month')->format('Y-m-d');
    return $base;
}

/** Latest attempt per stable batch key; empty != failed != never requested. */
function v2_top500_monthly_queue(array $targets, array $attempts, int $now, int $freshHours = 24): array
{
    if ($freshHours < 1 || $freshHours > 72) throw new InvalidArgumentException('invalid refresh interval');
    $latest = [];
    foreach ($attempts as $a) {
        $key = (string)($a['target_key'] ?? '');
        if ($key !== '' && (!isset($latest[$key]) || (int)($a['id'] ?? 0) > (int)($latest[$key]['id'] ?? 0))) $latest[$key] = $a;
    }
    $due = []; $fresh = 0; $deferred = 0; $months = [];
    foreach ($targets as $i => $target) {
        $period = $target['month'];
        if (!isset($months[$period])) $months[$period] = ['total'=>0,'fresh'=>0,'pending'=>0];
        $months[$period]['total']++;
        $a = $latest[$target['target_key']] ?? [];
        $status = (string)($a['status'] ?? '');
        $started = (int)($a['started_epoch'] ?? 0); $finished = (int)($a['finished_epoch'] ?? 0);
        if (in_array($status, ['success','empty'], true) && $finished > 0 && $finished >= $now - $freshHours * 3600) {
            $fresh++; $months[$period]['fresh']++; continue;
        }
        $months[$period]['pending']++;
        $searchId = (int)($a['search_id'] ?? 0);
        if (($status === 'started' && $searchId <= 0 && $started > $now - 3600) || ($status === 'failure' && $finished > $now - 900)) {
            $deferred++; continue;
        }
        // Reuse a known incomplete search instead of issuing the same new search
        // after a time budget/deploy interruption. Expired old sessions may restart.
        $resume = in_array($status, ['started','timeout','failure'], true) && $searchId > 0 && $started >= $now - 21600;
        $target['resume_search_id'] = $resume ? $searchId : null;
        $target['resume_attempt_id'] = $resume ? (int)$a['id'] : null;
        $target['_order'] = $i; $target['_age'] = $started;
        $due[] = $target;
    }
    usort($due, static fn(array $a, array $b): int =>
        ((int)($b['resume_search_id'] !== null) <=> (int)($a['resume_search_id'] !== null))
        ?: ($a['_age'] <=> $b['_age']) ?: ($a['_order'] <=> $b['_order']));
    return ['targets'=>$due,'fresh'=>$fresh,'pending'=>count($due)+$deferred,'deferred'=>$deferred,'months'=>$months];
}
