<?php
/**
 * Editorial SEO pilot: Maldives country page.
 * Review-only content; production indexation depends on a separate launch decision.
 */
function v2_seo_content_pilot_maldives(): array
{
    return [
        'id' => 'country.maldives.v1',
        'status' => 'review',
        'path' => '/country/maldives/',
        'type' => 'country',
        'data' => [
            'name' => 'Мальдивы',
            'title' => 'Туры на Мальдивы — подбор острова и отеля | AnyTour',
            'description' => 'Подберите тур на Мальдивы: сравните острова, атоллы, отели, питание и формат трансфера, а актуальные цены, даты и доступность проверьте в поиске AnyTour.',
            'h1' => 'Туры на Мальдивы',
            'eyebrow' => 'Направления AnyTour',
            'intro' => 'На Мальдивах выбор конкретного острова и отеля определяет большую часть впечатлений от поездки. Размер острова, пляж, домашний риф, питание, категория номера и трансфер могут быть важнее привычного сравнения курортов. Поэтому тур лучше оценивать как единый пакет, а актуальные цены, даты и доступность проверять непосредственно перед заявкой.',
            'breadcrumbs' => [
                ['label' => 'Главная', 'href' => '/'],
                ['label' => 'Мальдивы'],
            ],
            'sections' => [
                [
                    'id' => 'island-choice',
                    'title' => 'Как выбрать остров и атолл',
                    'paragraphs' => [
                        'Северный и Южный Мале, Ари, Баа, Раа и другие атоллы объединяют разные по формату острова и отели. Для первого отбора полезнее определить желаемый сценарий отдыха, чем выбирать только по названию атолла.',
                        'Обратите внимание на размер острова, характер пляжа, наличие рифа, инфраструктуру и формат размещения. Даже близкие по цене варианты могут давать совершенно разный отдых.'
                    ],
                ],
                [
                    'id' => 'hotel-choice',
                    'title' => 'Отель, питание и категория номера',
                    'paragraphs' => [
                        'На островном курорте возможности за пределами отеля ограничены, поэтому питание и инфраструктура комплекса особенно важны. Сравнивайте не только звёздность, но и конкретную концепцию отеля и состав включённых услуг.',
                        'Категория номера тоже существенно влияет на стоимость и формат поездки: варианты на пляже и над водой могут отличаться не только расположением, но и условиями размещения.'
                    ],
                ],
                [
                    'id' => 'transfer',
                    'title' => 'Трансфер — часть выбора тура',
                    'paragraphs' => [
                        'Способ и условия трансфера зависят от конкретного острова и предложения. При сравнении туров важно смотреть пакет целиком и проверять доступные сведения о перелёте, трансфере и размещении.',
                        'Не стоит делать вывод об итоговой стоимости только по цене отеля: перед заявкой нужно проверить состав конкретного турпакета.'
                    ],
                ],
                [
                    'id' => 'search',
                    'title' => 'Как подобрать тур на Мальдивы',
                    'paragraphs' => [
                        'Начните с города вылета, дат, продолжительности и бюджета, затем сравните подходящие острова и отели. После этого уточняйте питание, категорию номера и условия конкретного предложения.',
                        'Цены и доступность меняются, поэтому финальные параметры тура нужно перепроверить в поиске AnyTour перед передачей заявки.'
                    ],
                ],
            ],
            'related_title' => 'Туры в отели на Мальдивах',
            'related' => [
                ['label' => 'Туры в Kurumba Maldives', 'href' => '/country/maldives/hotel/kurumba-maldives-2461/'],
                ['label' => 'Туры в Kandima Maldives', 'href' => '/country/maldives/hotel/kandima-maldives-49820/'],
                ['label' => 'Туры в Sheraton Maldives Full Moon Resort & Spa', 'href' => '/country/maldives/hotel/sheraton-maldives-full-moon-resort-spa-2479/'],
                ['label' => 'Туры в Velassaru Maldives', 'href' => '/country/maldives/hotel/velassaru-maldives-2487/'],
                ['label' => 'Туры в Hard Rock Hotel Maldives', 'href' => '/country/maldives/hotel/hard-rock-hotel-maldives-66197/'],
                ['label' => 'Туры в SAii Lagoon Maldives', 'href' => '/country/maldives/hotel/saii-lagoon-maldives-65938/'],
                ['label' => 'Туры в LUX* South Ari Atoll Resorts & Villas', 'href' => '/country/maldives/hotel/lux-south-ari-atoll-resorts-villas-12126/'],
                ['label' => 'Туры в Villa Park Sun Island', 'href' => '/country/maldives/hotel/villa-park-sun-island-2482/'],
                ['label' => 'Туры в Barcelo Nasandhura Male', 'href' => '/country/maldives/hotel/barcelo-nasandhura-male-126556/'],
                ['label' => 'Туры в NOOE Maldives Kunaavashi', 'href' => '/country/maldives/hotel/nooe-maldives-kunaavashi-101694/'],
                ['label' => 'Туры в NH Collection Reethi Maldives', 'href' => '/country/maldives/hotel/nh-collection-reethi-maldives-ex-reethi-beach-resort-2475/'],
            ],
            'internal_links' => [
                ['title' => 'Подбор тура', 'links' => [
                    ['label' => 'Поиск туров AnyTour', 'href' => '/poisk-turov/'],
                ]],
            ],
            'search_state' => ['country' => 8],
        ],
        'content_notes' => [
            'Evergreen editorial copy only.',
            'Tourvisor countryId=8 was verified against the synchronized production catalog on 2026-09-01.',
            'Resort/region IDs remain unbound until separately verified.',
            'All linked hotel-tour routes use hotel IDs/slugs verified against fresh production hotel snapshot evidence; volatile offer data remains snapshot-driven.',
            'Review status does not enable indexation or sitemap emission.',
        ],
    ];
}
