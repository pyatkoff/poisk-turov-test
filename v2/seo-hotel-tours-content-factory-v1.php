<?php
/**
 * Structural factory for review-only "Туры в отель" editorial records.
 *
 * The factory deliberately does not generate editorial copy. Every caller must
 * supply its own title, description, intro and sections so hotel pages cannot
 * degrade into name-swapped doorway content. It only centralizes stable URL,
 * breadcrumb, handoff and review/noindex metadata.
 */
function v2_seo_hotel_tours_content_record(array $hotel, array $editorial): array
{
    $countryId = (int)($hotel['country_id'] ?? 0);
    $hotelId = (int)($hotel['hotel_id'] ?? 0);
    $countrySlug = trim((string)($hotel['country_slug'] ?? ''));
    $countryName = trim((string)($hotel['country_name'] ?? ''));
    $hotelSlug = trim((string)($hotel['hotel_slug'] ?? ''));
    $hotelName = trim((string)($hotel['hotel_name'] ?? ''));

    if ($countryId <= 0 || $hotelId <= 0 || $countrySlug === '' || $countryName === '' || $hotelSlug === '' || $hotelName === '') {
        throw new InvalidArgumentException('SEO hotel-tour content requires verified country/hotel identity');
    }
    foreach ([$countrySlug, $hotelSlug] as $slug) {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException('SEO hotel-tour content requires stable lowercase slugs');
        }
    }
    if (!str_ends_with($hotelSlug, '-' . $hotelId)) {
        throw new InvalidArgumentException('SEO hotel-tour slug must end with its verified hotel ID');
    }

    $title = trim((string)($editorial['title'] ?? ''));
    $description = trim((string)($editorial['description'] ?? ''));
    $intro = trim((string)($editorial['intro'] ?? ''));
    $sections = is_array($editorial['sections'] ?? null) ? $editorial['sections'] : [];
    if ($title === '' || mb_strlen($description) < 20 || $intro === '' || count($sections) < 2) {
        throw new InvalidArgumentException('SEO hotel-tour content requires distinct editorial title, description, intro and sections');
    }

    $path = '/country/' . $countrySlug . '/hotel/' . $hotelSlug . '/';
    $h1 = trim((string)($editorial['h1'] ?? ('Туры в ' . $hotelName)));
    $contentNotes = is_array($editorial['content_notes'] ?? null) ? array_values($editorial['content_notes']) : [];

    return [
        'id' => 'hotel_tours.' . $countrySlug . '.' . $hotelSlug . '.v1',
        'status' => 'review',
        'path' => $path,
        'type' => 'hotel_tours',
        'data' => [
            'name' => $hotelName,
            'title' => $title,
            'description' => $description,
            'h1' => $h1,
            'eyebrow' => 'AnyTour · ' . $countryName . ' · туры в отель',
            'intro' => $intro,
            'breadcrumbs' => [
                ['label' => 'Главная', 'href' => '/'],
                ['label' => $countryName, 'href' => '/country/' . $countrySlug . '/'],
                ['label' => $h1],
            ],
            'sections' => $sections,
            'related_title' => $countryName,
            'related' => [
                ['label' => 'Все туры: ' . $countryName, 'href' => '/country/' . $countrySlug . '/'],
            ],
            'internal_links' => [
                ['title' => 'Подбор тура', 'links' => [
                    ['label' => 'Поиск туров AnyTour', 'href' => '/poisk-turov/'],
                ]],
            ],
            'search_state' => ['country' => $countryId, 'hotel' => $hotelId],
        ],
        'content_notes' => array_merge([
            'Hotel ID ' . $hotelId . ' and slug ' . $hotelSlug . ' must be verified in the synchronized production catalog before this record is used.',
            'Volatile price and availability are rendered only from fresh materialized snapshots.',
            'No unverified region, atoll or subregion identity is introduced by the factory.',
            'Review status is always noindex and does not emit sitemap entries.',
        ], $contentNotes),
    ];
}
