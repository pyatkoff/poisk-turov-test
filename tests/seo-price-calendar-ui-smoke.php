<?php
declare(strict_types=1);

function sp_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

require_once __DIR__ . '/../v2/seo-price-calendar-v1.php';

function cal_fail(string $message): never
{
    fwrite(STDERR, "SEO_PRICE_CALENDAR_UI_FAIL:$message\n");
    exit(1);
}
function cal_assert(bool $condition, string $message): void
{
    if (!$condition) cal_fail($message);
}

$offers = [
    ['departureId'=>1,'departureName'=>'Москва','departureDate'=>'2026-09-05','nights'=>7],
    ['departureId'=>1,'departureName'=>'Москва','departureDate'=>'2026-09-06','nights'=>7],
    ['departureId'=>1,'departureName'=>'Москва','departureDate'=>'2026-09-07','nights'=>7],
    ['departureId'=>1,'departureName'=>'Москва','departureDate'=>'2026-09-08','nights'=>10],
    ['departureId'=>2,'departureName'=>'Санкт-Петербург','departureDate'=>'2026-09-05','nights'=>7],
    ['departureId'=>1,'departureName'=>'Москва','departureDate'=>'2026-10-01','nights'=>7],
];
$today = new DateTimeImmutable('2026-09-03', new DateTimeZone('Europe/Moscow'));
$plan = v2_seo_price_calendar_plan($offers, 14, $today, '2026-09-01', '2026-09-30');
cal_assert(is_array($plan), 'plan_missing');
cal_assert(($plan['departureId'] ?? 0) === 1, 'dominant_departure');
cal_assert(($plan['nights'] ?? 0) === 7, 'dominant_nights');
cal_assert(($plan['dateFrom'] ?? '') === '2026-09-05', 'earliest_observed_date');
cal_assert(($plan['dateTo'] ?? '') === '2026-09-18', 'fourteen_day_window');
cal_assert(!str_starts_with((string)($plan['dateTo'] ?? ''), '2026-10'), 'seasonal_scope_crossed_month');

$endOfMonthOffers = [
    ['departureId'=>1,'departureName'=>'Москва','departureDate'=>'2026-09-27','nights'=>7],
    ['departureId'=>1,'departureName'=>'Москва','departureDate'=>'2026-09-28','nights'=>7],
    ['departureId'=>1,'departureName'=>'Москва','departureDate'=>'2026-09-29','nights'=>7],
];
$endPlan = v2_seo_price_calendar_plan($endOfMonthOffers, 14, $today, '2026-09-01', '2026-09-30');
cal_assert(($endPlan['dateFrom'] ?? '') === '2026-09-27', 'month_end_from');
cal_assert(($endPlan['dateTo'] ?? '') === '2026-09-30', 'month_end_clamp');
cal_assert(($endPlan['days'] ?? 0) === 4, 'month_end_span');

$rows = [
    ['departure_date'=>'2026-09-05','min_price'=>118400,'hotel_count'=>9,'independent_search_count'=>2,'latest_observed_at'=>'2026-09-03 09:00:00'],
    ['departure_date'=>'2026-09-07','min_price'=>107500,'hotel_count'=>12,'independent_search_count'=>3,'latest_observed_at'=>'2026-09-03 10:00:00'],
    ['departure_date'=>'2026-09-08','min_price'=>111200,'hotel_count'=>8,'independent_search_count'=>2,'latest_observed_at'=>'2026-09-03 10:30:00'],
];
$calendar = v2_price_calendar_build($rows, '2026-09-05', '2026-09-08') + [
    'departureId'=>1,
    'departureName'=>'Москва',
    'nights'=>7,
    'countryId'=>4,
    'regionId'=>20,
    'currency'=>'RUB',
    'observationWindowHours'=>72,
    'cachedPriceIsFinal'=>false,
];
cal_assert(($calendar['observedDays'] ?? 0) === 3, 'observed_days');
cal_assert(($calendar['missingDays'] ?? 0) === 1, 'missing_days');
cal_assert(($calendar['bestDate'] ?? '') === '2026-09-07', 'best_date');
cal_assert(($calendar['missing_semantics'] ?? '') === 'unknown_not_zero', 'unknown_semantics');

$html = v2_seo_render_price_calendar($calendar, ['country'=>4,'region'=>20], 'Цены в Анталью по датам');
cal_assert(str_contains($html, 'data-seo-price-calendar'), 'calendar_marker');
cal_assert(str_contains($html, 'данные ≤ 72 ч'), 'freshness_copy');
cal_assert(str_contains($html, 'нет свежих данных'), 'missing_copy');
cal_assert(str_contains($html, 'лучшая цена'), 'best_badge');
cal_assert(str_contains($html, 'от 107 500 ₽'), 'best_price');
cal_assert(preg_match('/>\s*(?:от\s+)?0\s*₽(?:\s*<|$)/u', $html) !== 1, 'zero_price_must_never_render');
cal_assert(str_contains($html, 'dateFrom=2026-09-07'), 'handoff_date_from');
cal_assert(str_contains($html, 'dateTo=2026-09-07'), 'handoff_date_to');
cal_assert(str_contains($html, 'daysFrom=7'), 'handoff_nights_from');
cal_assert(str_contains($html, 'daysTill=7'), 'handoff_nights_till');
cal_assert(str_contains($html, 'country=4'), 'handoff_country');
cal_assert(str_contains($html, 'region=20'), 'handoff_region');
cal_assert(str_contains($html, 'from=1'), 'handoff_departure');
cal_assert(str_contains($html, 'count_people=2'), 'handoff_people');

$css = file_get_contents(__DIR__ . '/../v2/seo-price-calendar-v1.css');
$shell = file_get_contents(__DIR__ . '/../v2/site-page-shell-v1.php');
$country = file_get_contents(__DIR__ . '/../v2/country-page-v1.php');
$resort = file_get_contents(__DIR__ . '/../v2/seo-resort-page-v1.php');
$seasonal = file_get_contents(__DIR__ . '/../v2/seo-seasonal-page-v1.php');
foreach (['css'=>$css,'shell'=>$shell,'country'=>$country,'resort'=>$resort,'seasonal'=>$seasonal] as $name=>$source) {
    cal_assert(is_string($source) && $source !== '', 'source_'.$name);
}
cal_assert(str_contains($css, '.sp-price-calendar__grid'), 'css_grid');
cal_assert(str_contains($css, '.sp-price-day--best'), 'css_best');
cal_assert(str_contains($css, '.sp-price-day--unknown'), 'css_unknown');
cal_assert(str_contains($shell, "sp_inline_css('seo-price-calendar-v1.css')"), 'shell_css');
cal_assert(str_contains($country, "v2_seo_country_snapshot_offers(\$countryId, 12)"), 'country_candidates');
cal_assert(str_contains($country, "array_slice(\$offerCandidates, 0, 6)"), 'country_visible_six');
cal_assert(str_contains($country, 'v2_seo_render_price_calendar'), 'country_calendar');
cal_assert(str_contains($resort, "v2_seo_resort_snapshot_offers(\$countryId, \$regionId, 12)"), 'resort_candidates');
cal_assert(str_contains($resort, "array_slice(\$offerCandidates, 0, 6)"), 'resort_visible_six');
cal_assert(str_contains($resort, 'v2_seo_render_price_calendar'), 'resort_calendar');
cal_assert(str_contains($seasonal, "v2_seo_seasonal_snapshot_offers(\$pageKey,6)"), 'seasonal_visible_six_unchanged');
cal_assert(str_contains($seasonal, "v2_seo_seasonal_snapshot_offers(\$pageKey,12)"), 'seasonal_calendar_candidates');
cal_assert(str_contains($seasonal, "modify('last day of this month')"), 'seasonal_month_clamp');
cal_assert(str_contains($seasonal, 'v2_seo_render_price_calendar'), 'seasonal_calendar');

echo "SEO_PRICE_CALENDAR_UI_OK unknown_not_zero=1 seasonal_month_clamp=1 server_rendered=1 search_handoff=1\n";
