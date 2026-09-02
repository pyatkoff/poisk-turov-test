<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_kagi(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 72939,
        'hotel_slug' => 'kagi-maldives-spa-island-72939',
        'hotel_name' => 'Kagi Maldives Spa Island',
    ], [
        'title' => 'Туры в Kagi Maldives Spa Island — цены и подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Kagi Maldives Spa Island: сравните даты, продолжительность, питание и состав предложения, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Здесь собран путь для подбора пакетных туров именно в Kagi Maldives Spa Island. Сравнивайте доступные варианты по датам, количеству ночей, городу вылета, питанию и итоговой цене пакета.',
        'sections' => [
            ['id'=>'dates','title'=>'Даты и продолжительность тура','paragraphs'=>['Цена одного и того же отеля может заметно различаться для соседних дат и разной продолжительности поездки.','Сравнивайте предложения на сопоставимые параметры и проверяйте их актуальность перед заявкой.']],
            ['id'=>'meal','title'=>'Питание и вариант размещения','paragraphs'=>['Питание и размещение задаются конкретным предложением и не должны восприниматься как постоянные параметры страницы.','Перед бронированием проверьте их в выбранной карточке тура.']],
            ['id'=>'package','title'=>'Состав конкретного пакета','paragraphs'=>['Условия перелёта, багажа, трансфера и дополнительных услуг зависят от предложения.','Итоговый состав пакета нужно сверять непосредственно перед передачей заявки.']],
            ['id'=>'search','title'=>'Как подобрать тур в Kagi Maldives Spa Island','paragraphs'=>['Перейдите в поиск с выбранным отелем, затем укажите город вылета, даты и туристов.','При отсутствии подходящего варианта попробуйте соседние даты или другой город вылета.']],
        ],
        'content_notes' => ['Hotel ID 72939 and catalog slug were verified by production hotel snapshot evidence observed on 2026-09-01 and inspected on 2026-09-02.'],
    ]);
}
