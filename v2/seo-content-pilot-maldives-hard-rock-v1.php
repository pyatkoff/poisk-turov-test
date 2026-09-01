<?php
function v2_seo_content_pilot_maldives_hard_rock(): array
{
    return [
        'id' => 'hotel_tours.maldives.hard-rock-hotel-maldives-66197.v1',
        'status' => 'review',
        'path' => '/country/maldives/hotel/hard-rock-hotel-maldives-66197/',
        'type' => 'hotel_tours',
        'data' => [
            'name' => 'Hard Rock Hotel Maldives',
            'title' => 'Туры в Hard Rock Hotel Maldives — цены и подбор | AnyTour',
            'description' => 'Подберите тур в Hard Rock Hotel Maldives: сравните даты, продолжительность, питание и состав пакета, а актуальную стоимость и доступность проверьте в поиске AnyTour.',
            'h1' => 'Туры в Hard Rock Hotel Maldives',
            'eyebrow' => 'AnyTour · Мальдивы · туры в отель',
            'intro' => 'На странице собраны ориентиры для выбора пакетного тура в Hard Rock Hotel Maldives. Сравнивайте конкретные варианты по датам, продолжительности, городу вылета, питанию и итоговой стоимости.',
            'breadcrumbs' => [
                ['label' => 'Главная', 'href' => '/'],
                ['label' => 'Мальдивы', 'href' => '/country/maldives/'],
                ['label' => 'Туры в Hard Rock Hotel Maldives'],
            ],
            'sections' => [
                ['id'=>'compare','title'=>'Что сравнивать в турах в Hard Rock Hotel Maldives','paragraphs'=>['Для одного отеля могут одновременно быть доступны разные пакетные туры с отличающимися датами, количеством ночей, городом вылета и питанием. Сравнивайте предложения на сопоставимые параметры.','Актуальную стоимость и доступность нужно повторно проверить в поиске AnyTour непосредственно перед заявкой.']],
                ['id'=>'meal','title'=>'Питание и вариант размещения','paragraphs'=>['Тип питания и вариант размещения могут влиять на стоимость и состав поездки. Ориентируйтесь на параметры конкретного предложения, а не на общее описание отеля.','Если важен определённый формат номера или питания, его нужно подтвердить в выбранном туре.']],
                ['id'=>'package','title'=>'Перелёт, трансфер и пакет','paragraphs'=>['Доступные сведения о перелёте, багаже, трансфере и других услугах относятся к конкретному пакету и могут отличаться между предложениями.','Перед заявкой проверьте финальные условия в карточке выбранного тура.']],
                ['id'=>'search','title'=>'Как подобрать тур в Hard Rock Hotel Maldives','paragraphs'=>['Откройте поиск с уже выбранным отелем, задайте город вылета, даты и состав туристов, после чего сравните доступные варианты.','Если вариантов нет, попробуйте другой период или город вылета, сохранив выбранный отель.']],
            ],
            'related_title' => 'Мальдивы',
            'related' => [['label'=>'Все туры на Мальдивы','href'=>'/country/maldives/']],
            'internal_links' => [['title'=>'Подбор тура','links'=>[['label'=>'Поиск туров AnyTour','href'=>'/poisk-turov/']]]],
            'search_state' => ['country'=>8,'hotel'=>66197],
        ],
        'content_notes' => [
            'Hotel ID 66197 and slug hard-rock-hotel-maldives-66197 were verified in the synchronized production catalog.',
            'A fresh production hotel snapshot was observed on 2026-09-01; volatile price and availability are rendered only from unexpired snapshots.',
            'No atoll or subregion identity is invented; search handoff uses country=8 and hotel=66197 only.',
            'Review status is always noindex and does not emit sitemap entries.',
        ],
    ];
}
