<?php
/**
 * Editorial SEO pilot: package tours to Kurumba Maldives.
 * Hotel identity is backed by the synchronized production catalog; this record
 * remains review-only and does not enable indexation or sitemap publication.
 */
function v2_seo_content_pilot_maldives_kurumba(): array
{
    return [
        'id' => 'hotel_tours.maldives.kurumba-maldives-2461.v1',
        'status' => 'review',
        'path' => '/country/maldives/hotel/kurumba-maldives-2461/',
        'type' => 'hotel_tours',
        'data' => [
            'name' => 'Kurumba Maldives',
            'title' => 'Туры в Kurumba Maldives — цены и подбор тура | AnyTour',
            'description' => 'Подберите тур в Kurumba Maldives: сравните даты, продолжительность, питание и состав пакета, а актуальную стоимость и доступность проверьте в поиске AnyTour.',
            'h1' => 'Туры в Kurumba Maldives',
            'eyebrow' => 'AnyTour · Мальдивы · туры в отель',
            'intro' => 'На этой странице собран ориентир для выбора пакетного тура именно в Kurumba Maldives. Сравнивайте не только цену, но и даты, продолжительность, питание и состав конкретного предложения; актуальные условия нужно перепроверить перед заявкой.',
            'breadcrumbs' => [
                ['label' => 'Главная', 'href' => '/'],
                ['label' => 'Мальдивы', 'href' => '/country/maldives/'],
                ['label' => 'Туры в Kurumba Maldives'],
            ],
            'sections' => [
                [
                    'id' => 'compare-packages',
                    'title' => 'Что сравнивать в турах в Kurumba Maldives',
                    'paragraphs' => [
                        'Для одного и того же отеля предложения могут отличаться городом и датой вылета, продолжительностью поездки, питанием и другими параметрами пакета. Поэтому корректнее сравнивать конкретные варианты тура, а не ориентироваться на одну цену.',
                        'Перед заявкой проверьте выбранные даты, количество ночей и итоговую стоимость в поиске AnyTour — эти параметры могут меняться по мере обновления предложений.',
                    ],
                ],
                [
                    'id' => 'room-and-meal',
                    'title' => 'Питание и размещение',
                    'paragraphs' => [
                        'При выборе тура обращайте внимание на указанные в конкретном предложении тип питания и вариант размещения. Разные комбинации могут заметно менять стоимость поездки и то, что входит в пакет.',
                        'Если важен определённый формат номера или питания, его лучше проверять уже в выбранном туре перед передачей заявки менеджеру.',
                    ],
                ],
                [
                    'id' => 'package-details',
                    'title' => 'Перелёт, трансфер и состав пакета',
                    'paragraphs' => [
                        'Состав пакетного тура нужно оценивать целиком. Доступные сведения о перелёте, багаже, трансфере и других услугах зависят от конкретного предложения и требуют проверки перед бронированием.',
                        'Страница не фиксирует условия одного тура как постоянные характеристики отеля: актуальные детали показывает поисковик для выбранного варианта.',
                    ],
                ],
                [
                    'id' => 'how-to-search',
                    'title' => 'Как подобрать тур в Kurumba Maldives',
                    'paragraphs' => [
                        'Перейдите в поиск с уже выбранным отелем, задайте подходящие даты и параметры поездки, затем сравните доступные варианты. Если свежих предложений временно нет, можно изменить даты или город вылета, не меняя сам отель.',
                        'Финальную стоимость и доступность следует перепроверить непосредственно перед заявкой.',
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
            'search_state' => ['country' => 8, 'hotel' => 2461],
        ],
        'content_notes' => [
            'Hotel ID 2461 and slug kurumba-maldives-2461 were verified in the synchronized production catalog.',
            'A fresh production hotel snapshot was observed on 2026-09-01; volatile price and availability are rendered only from unexpired snapshots.',
            'No atoll or subregion identity is invented; search handoff uses country=8 and hotel=2461 only.',
            'Review status is always noindex and does not emit sitemap entries.',
        ],
    ];
}
