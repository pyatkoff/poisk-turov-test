<?php
/**
 * Editorial SEO pilot: package tours to Sheraton Maldives Full Moon Resort & Spa.
 * Hotel identity is backed by the synchronized production catalog; this record
 * remains review-only and does not enable indexation or sitemap publication.
 */
function v2_seo_content_pilot_maldives_sheraton(): array
{
    return [
        'id' => 'hotel_tours.maldives.sheraton-maldives-full-moon-resort-spa-2479.v1',
        'status' => 'review',
        'path' => '/country/maldives/hotel/sheraton-maldives-full-moon-resort-spa-2479/',
        'type' => 'hotel_tours',
        'data' => [
            'name' => 'Sheraton Maldives Full Moon Resort & Spa',
            'title' => 'Туры в Sheraton Maldives Full Moon Resort & Spa — цены | AnyTour',
            'description' => 'Подберите тур в Sheraton Maldives Full Moon Resort & Spa: сравните даты, продолжительность, питание и состав пакета, а актуальную стоимость и доступность проверьте в поиске AnyTour.',
            'h1' => 'Туры в Sheraton Maldives Full Moon Resort & Spa',
            'eyebrow' => 'AnyTour · Мальдивы · туры в отель',
            'intro' => 'Эта страница помогает сравнить пакетные туры именно в Sheraton Maldives Full Moon Resort & Spa. Для выбора смотрите на дату и город вылета, количество ночей, питание и итоговую стоимость конкретного предложения.',
            'breadcrumbs' => [
                ['label' => 'Главная', 'href' => '/'],
                ['label' => 'Мальдивы', 'href' => '/country/maldives/'],
                ['label' => 'Туры в Sheraton Maldives Full Moon Resort & Spa'],
            ],
            'sections' => [
                [
                    'id' => 'compare-packages',
                    'title' => 'Что сравнивать в турах в Sheraton Maldives',
                    'paragraphs' => [
                        'Даже для одного отеля пакетные туры могут заметно различаться по датам, продолжительности, городу вылета и питанию. Сравнивать стоит конкретные предложения на одинаковые параметры поездки.',
                        'Цена и доступность не фиксируются на странице навсегда: перед заявкой они перепроверяются в поиске AnyTour.',
                    ],
                ],
                [
                    'id' => 'meal-and-room',
                    'title' => 'Питание и размещение',
                    'paragraphs' => [
                        'При выборе тура проверяйте указанные в предложении питание и вариант размещения. Разные комбинации могут менять итоговую стоимость и набор включённых услуг.',
                        'Если нужен определённый формат номера или питания, сравнивайте предложения именно по этому параметру перед заявкой.',
                    ],
                ],
                [
                    'id' => 'package-details',
                    'title' => 'Перелёт, трансфер и другие условия',
                    'paragraphs' => [
                        'Сведения о перелёте, багаже, трансфере и прочих услугах зависят от конкретного пакета. Их нельзя переносить с одного предложения на другое без проверки.',
                        'Финальные детали тура следует смотреть в поиске после выбора дат и города вылета.',
                    ],
                ],
                [
                    'id' => 'search',
                    'title' => 'Как подобрать тур в Sheraton Maldives',
                    'paragraphs' => [
                        'Перейдите в поиск с уже выбранным отелем, задайте даты, продолжительность и состав туристов, затем сравните доступные варианты по цене и параметрам пакета.',
                        'Если вариантов мало, расширьте период поиска или измените город вылета, сохранив выбранный отель.',
                    ],
                ],
            ],
            'related_title' => 'Мальдивы',
            'related' => [
                ['label' => 'Все туры на Мальдивы', 'href' => '/country/maldives/'],
            ],
            'internal_links' => [
                ['title' => 'Подбор тура', 'links' => [
                    ['label' => 'Поиск туров AnyTour', 'href' => '/poisk-turov/'],
                ]],
            ],
            'search_state' => ['country' => 8, 'hotel' => 2479],
        ],
        'content_notes' => [
            'Hotel ID 2479 and slug sheraton-maldives-full-moon-resort-spa-2479 were verified in the synchronized production catalog.',
            'A fresh production hotel snapshot was observed on 2026-09-01; volatile price and availability are rendered only from unexpired snapshots.',
            'No atoll or subregion identity is invented; search handoff uses country=8 and hotel=2479 only.',
            'Review status is always noindex and does not emit sitemap entries.',
        ],
    ];
}
