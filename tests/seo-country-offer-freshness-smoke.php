<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/country-page-v1.php';

function country_freshness_fail(string $message): never
{
    fwrite(STDERR, "SEO_COUNTRY_OFFER_FRESHNESS_FAIL {$message}\n");
    exit(1);
}

$offers = [
    ['snapshotObservedAt' => '2026-09-08 23:59:59'],
    ['snapshotObservedAt' => '2026-09-09 07:12:00'],
    ['snapshotObservedAt' => 'not-a-date'],
    ['snapshotObservedAt' => '2026-02-31 00:00:00'],
];

if (cp_offer_freshness_label($offers) !== 'Цены обновлены 09.09.2026') {
    country_freshness_fail('latest_valid_date');
}
if (cp_offer_freshness_label([['snapshotObservedAt' => '2026-01-05T10:00:00Z']]) !== 'Цены обновлены 05.01.2026') {
    country_freshness_fail('iso_separator');
}
if (cp_offer_freshness_label([[], 'bad-row', ['snapshotObservedAt' => '']]) !== '') {
    country_freshness_fail('invalid_rows_must_hide_label');
}

$source = (string)file_get_contents(__DIR__ . '/../v2/country-page-v1.php');
foreach (['cp_offer_freshness_label($offers)', 'data-country-offer-freshness', 'sp_e($offerFreshnessLabel)'] as $needle) {
    if (!str_contains($source, $needle)) country_freshness_fail('render_contract_' . $needle);
}

echo "SEO_COUNTRY_OFFER_FRESHNESS_OK latest=1 invalidHidden=1 escaped=1\n";
