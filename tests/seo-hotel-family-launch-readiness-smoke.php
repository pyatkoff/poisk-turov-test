<?php
require_once __DIR__ . '/../v2/seo-hotel-family-launch-readiness-v1.php';

function family_summary_fail(string $message): void
{
    fwrite(STDERR, "SEO_HOTEL_FAMILY_SUMMARY_FAIL:$message\n");
    exit(1);
}

function family_catalog(int $countryId, string $countrySlug, int $hotelId, string $hotelSlug, bool $deep = true): array
{
    $path = '/country/' . $countrySlug . '/hotel/' . $hotelSlug . '/';
    $countryPath = '/country/' . $countrySlug . '/';
    $sections = [
        ['title' => 'Как сравнить туры', 'paragraphs' => ['Сравните даты, длительность и состав пакетного предложения.']],
        ['title' => 'Что проверить', 'paragraphs' => ['Перед заявкой перепроверьте актуальные параметры тура в поиске AnyTour.']],
    ];
    if ($deep) $sections[] = ['title' => 'Как продолжить подбор', 'paragraphs' => ['Используйте поиск для проверки доступных вариантов на выбранные даты.']];

    $records = [[
        'id' => 'hotel_tours.' . $countrySlug . '.' . $hotelSlug . '.v1',
        'status' => 'review',
        'path' => $path,
        'type' => 'hotel_tours',
        'data' => [
            'name' => 'Test Hotel ' . $hotelId,
            'title' => 'Туры в Test Hotel ' . $hotelId . ' — цены и подбор тура | AnyTour',
            'description' => 'Сравните пакетные туры в Test Hotel ' . $hotelId . ', проверьте даты, длительность поездки и актуальные параметры перед заявкой в AnyTour.',
            'h1' => 'Туры в Test Hotel ' . $hotelId,
            'intro' => $deep
                ? 'Страница помогает сравнить пакетные туры в выбранный отель и перейти к актуальному поиску по проверенной идентичности отеля.'
                : 'Короткое введение.',
            'breadcrumbs' => [
                ['label' => 'Главная', 'href' => '/'],
                ['label' => ucfirst($countrySlug), 'href' => $countryPath],
                ['label' => 'Туры в Test Hotel ' . $hotelId],
            ],
            'sections' => $sections,
            'related' => [['label' => ucfirst($countrySlug), 'href' => $countryPath]],
            'internal_links' => [['title' => 'Подбор тура', 'links' => [['label' => 'Поиск туров AnyTour', 'href' => '/poisk-turov/']]]],
            'search_state' => ['country' => $countryId, 'hotel' => $hotelId],
        ],
    ]];

    return v2_seo_content_catalog($records, [$path => ['parent' => $countryPath]]);
}

$now = 1800000000;
$families = [
    [
        'key' => 'turkey',
        'catalog' => family_catalog(4, 'turkey', 1001, 'test-hotel-1001'),
        'evidence' => [[
            'country_id' => 4,
            'hotel_id' => 1001,
            'hotel_slug' => 'test-hotel-1001',
            'evidence_epoch' => $now,
            'freshness_seconds' => 600,
        ]],
    ],
    [
        'key' => 'egypt',
        'catalog' => family_catalog(1, 'egypt', 2002, 'test-hotel-2002'),
        'evidence' => [[
            'country_id' => 1,
            'hotel_id' => 2002,
            'hotel_slug' => 'test-hotel-2002',
            'evidence_epoch' => $now - 1200,
            'freshness_seconds' => 600,
        ]],
    ],
];

$summary = v2_seo_hotel_family_launch_readiness_summary($families, $now);
if (count($summary) !== 2) family_summary_fail('family_count');
if (($summary[0]['key'] ?? '') !== 'egypt') family_summary_fail('deterministic_sort');
if (($summary[0]['ready_for_launch_review'] ?? -1) !== 0) family_summary_fail('stale_family_ready');
if (($summary[0]['blocked'] ?? -1) !== 1) family_summary_fail('stale_family_blocked');
if (($summary[0]['error_counts']['fresh_identity_evidence_required'] ?? 0) !== 1) family_summary_fail('stale_reason_missing');
if (($summary[1]['key'] ?? '') !== 'turkey') family_summary_fail('turkey_missing');
if (($summary[1]['ready_for_launch_review'] ?? -1) !== 1) family_summary_fail('fresh_family_not_ready');
if (($summary[1]['average_score'] ?? 0) !== 100.0) family_summary_fail('fresh_family_score');
if (($summary[1]['state'] ?? '') !== 'review_only_launch_readiness_summary') family_summary_fail('state');

try {
    v2_seo_hotel_family_launch_readiness_summary([$families[0], $families[0]], $now);
    family_summary_fail('duplicate_key_allowed');
} catch (InvalidArgumentException $e) {
    if (!str_contains($e->getMessage(), 'Duplicate')) family_summary_fail('duplicate_key_wrong_error');
}

echo "SEO_HOTEL_FAMILY_SUMMARY_OK families=2 ready=1 staleBlocked=1\n";
