<?php
function v2_seo_content_pilot_maldives_velassaru(): array
{
    return [
        'id' => 'hotel_tours.maldives.velassaru-maldives-2487.v1',
        'status' => 'review',
        'path' => '/country/maldives/hotel/velassaru-maldives-2487/',
        'type' => 'hotel_tours',
        'data' => [
            'name' => 'Velassaru Maldives',
            'title' => 'Туры в Velassaru Maldives — цены и подбор тура | AnyTour',
            'description' => 'Подберите тур в Velassaru Maldives: сравните даты, продолжительность, питание и состав пакета, а актуальную стоимость и доступность проверьте в поиске AnyTour.',
            'h1' => 'Туры в Velassaru Maldives',
            'eyebrow' => 'AnyTour · Мальдивы · туры в отель',
            'intro' => 'Сравнивайте пакетные туры в Velassaru Maldives по датам, продолжительности, питанию, городу вылета и итоговой стоимости. Актуальные условия конкретного предложения нужно перепроверять перед заявкой.',
            'breadcrumbs' => [
                ['label' => 'Главная', 'href' => '/'],
                ['label' => 'Мальдивы', 'href' => '/country/maldives/'],
                ['label' => 'Туры в Velassaru Maldives'],
            ],
            'sections' => [
                ['id'=>'compare','title'=>'Как сравнивать туры в Velassaru Maldives','paragraphs'=>['Один и тот же отель может быть доступен в разных пакетах с отличающимися датами, продолжительностью, городом вылета и питанием. Сравнивайте предложения на одинаковые параметры поездки.','Цена и доступность меняются, поэтому перед заявкой повторно проверьте выбранный вариант в поиске AnyTour.']],
                ['id'=>'meal','title'=>'Питание и размещение','paragraphs'=>['Проверяйте указанные в конкретном туре питание и вариант размещения: они могут влиять на итоговую стоимость и состав пакета.','Если важен определённый формат номера или питания, уточняйте его в выбранном предложении перед заявкой.']],
                ['id'=>'package','title'=>'Состав турпакета','paragraphs'=>['Сведения о перелёте, багаже, трансфере и других услугах зависят от конкретного предложения и не должны считаться постоянной характеристикой страницы отеля.','Финальные детали нужно смотреть после выбора тура в поиске.']],
                ['id'=>'search','title'=>'Как найти тур в Velassaru Maldives','paragraphs'=>['Перейдите в поиск с уже выбранным отелем, укажите город вылета, даты и состав туристов, затем сравните доступные варианты.','Если подходящих туров нет, измените период или город вылета, сохранив выбранный отель.']],
            ],
            'related_title' => 'Мальдивы',
            'related' => [['label'=>'Все туры на Мальдивы','href'=>'/country/maldives/']],
            'internal_links' => [['title'=>'Подбор тура','links'=>[['label'=>'Поиск туров AnyTour','href'=>'/poisk-turov/']]]],
            'search_state' => ['country'=>8,'hotel'=>2487],
        ],
        'content_notes' => [
            'Hotel ID 2487 and slug velassaru-maldives-2487 were verified in the synchronized production catalog.',
            'A fresh production hotel snapshot was observed on 2026-09-01; volatile price and availability are rendered only from unexpired snapshots.',
            'No atoll or subregion identity is invented; search handoff uses country=8 and hotel=2487 only.',
            'Review status is always noindex and does not emit sitemap entries.',
        ],
    ];
}
