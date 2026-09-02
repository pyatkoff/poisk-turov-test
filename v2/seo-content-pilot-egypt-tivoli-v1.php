<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_tivoli(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 511,
        'hotel_slug' => 'tivoli-hotel-aqua-park-511',
        'hotel_name' => 'TIVOLI HOTEL AQUA PARK',
    ], [
        'title' => 'Туры в TIVOLI HOTEL AQUA PARK — подбор пакетного тура | AnyTour',
        'description' => 'Подберите пакетный тур в TIVOLI HOTEL AQUA PARK: задайте вылет, даты и состав туристов, сравните актуальные предложения и проверьте параметры выбранного тура в AnyTour.',
        'intro' => 'Страница связывает проверенную идентичность TIVOLI HOTEL AQUA PARK с текущей выдачей пакетных туров. Цена, наличие, питание и тип номера не закрепляются здесь и подтверждаются для конкретного предложения.',
        'sections' => [
            ['id'=>'start','title'=>'Задайте параметры поездки','paragraphs'=>['Начните с города вылета, дат и состава туристов, сохранив выбранный отель.','Если вариантов мало, расширьте даты или продолжительность и сравните выдачу заново.']],
            ['id'=>'stay','title'=>'Проверьте размещение и питание','paragraphs'=>['Пакеты одного отеля могут отличаться вариантом номера и типом питания.','Эти условия нужно сверять в карточке каждого подходящего тура.']],
            ['id'=>'bundle','title'=>'Сопоставьте состав турпакета','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги относятся к конкретному предложению.','Сравнивайте итоговые условия пакета, а не только отображаемую стоимость.']],
            ['id'=>'verify','title'=>'Перепроверьте выбранный вариант','paragraphs'=>['Доступность и коммерческие условия обновляются вместе с данными туроператоров.','Перед заявкой подтвердите текущие параметры выбранного тура в свежей выдаче AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 511 and catalog slug tivoli-hotel-aqua-park-511 were verified by fresh production identity snapshot evidence on 2026-09-02.'],
    ]);
}
