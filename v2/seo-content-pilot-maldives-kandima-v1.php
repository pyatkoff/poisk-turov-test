<?php
/**
 * Editorial SEO pilot: package tours to Kandima Maldives.
 * Hotel identity is backed by the synchronized production catalog; this record
 * remains review-only and does not enable indexation or sitemap publication.
 */
function v2_seo_content_pilot_maldives_kandima(): array
{
    return [
        'id' => 'hotel_tours.maldives.kandima-maldives-49820.v1',
        'status' => 'review',
        'path' => '/country/maldives/hotel/kandima-maldives-49820/',
        'type' => 'hotel_tours',
        'data' => [
            'name' => 'Kandima Maldives',
            'title' => 'Туры в Kandima Maldives — цены и подбор тура | AnyTour',
            'description' => 'Подберите тур в Kandima Maldives: сравните даты, продолжительность, питание и состав пакета, а актуальную стоимость и доступность проверьте в поиске AnyTour.',
            'h1' => 'Туры в Kandima Maldives',
            'eyebrow' => 'AnyTour · Мальдивы · туры в отель',
            'intro' => 'На странице собраны ориентиры для выбора пакетного тура именно в Kandima Maldives. Сравнивайте конкретные варианты по датам, продолжительности, питанию и итоговой цене, а актуальные условия проверяйте перед заявкой.',
            'breadcrumbs' => [
                ['label' => 'Главная', 'href' => '/'],
                ['label' => 'Мальдивы', 'href' => '/country/maldives/'],
                ['label' => 'Туры в Kandima Maldives'],
            ],
            'sections' => [
                [
                    'id' => 'compare-packages',
                    'title' => 'Как сравнивать туры в Kandima Maldives',
                    'paragraphs' => [
                        'Предложения в один отель могут отличаться датой и городом вылета, количеством ночей, питанием и другими параметрами пакета. Поэтому ориентироваться только на одну найденную цену недостаточно.',
                        'Перед заявкой стоит повторно проверить выбранные даты и итоговую стоимость в поиске AnyTour: доступность и цена меняются по мере обновления предложений.',
                    ],
                ],
                [
                    'id' => 'meal-and-stay',
                    'title' => 'Питание и вариант размещения',
                    'paragraphs' => [
                        'Проверяйте тип питания и вариант размещения внутри конкретного тура. Эти параметры могут влиять и на стоимость, и на состав поездки.',
                        'Если нужен определённый номер или формат питания, зафиксируйте это при сравнении выбранных предложений перед передачей заявки менеджеру.',
                    ],
                ],
                [
                    'id' => 'package',
                    'title' => 'Что входит в пакет',
                    'paragraphs' => [
                        'Доступные сведения о перелёте, багаже, трансфере и других услугах зависят от конкретного предложения. Их нужно проверять для выбранного тура, а не считать постоянными условиями страницы отеля.',
                        'Актуальная карточка предложения в поиске остаётся источником финальной информации перед заявкой.',
                    ],
                ],
                [
                    'id' => 'search',
                    'title' => 'Как найти тур в Kandima Maldives',
                    'paragraphs' => [
                        'Перейдите в поиск с уже выбранным отелем, укажите город вылета, даты и параметры поездки, затем сравните доступные варианты.',
                        'Если предложений на выбранные даты нет, попробуйте другой период или город вылета, сохранив выбранный отель.',
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
            'search_state' => ['country' => 8, 'hotel' => 49820],
        ],
        'content_notes' => [
            'Hotel ID 49820 and slug kandima-maldives-49820 were verified in the synchronized production catalog.',
            'A fresh production hotel snapshot was observed on 2026-09-01; volatile price and availability are rendered only from unexpired snapshots.',
            'No atoll or subregion identity is invented; search handoff uses country=8 and hotel=49820 only.',
            'Review status is always noindex and does not emit sitemap entries.',
        ],
    ];
}
