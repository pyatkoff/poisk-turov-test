<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_viva_blue(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>64291,
        'hotel_slug'=>'viva-blue-resort-and-diving-sport-64291','hotel_name'=>'VIVA BLUE RESORT AND DIVING SPORT',
    ], [
        'title'=>'Туры в VIVA BLUE RESORT AND DIVING SPORT — актуальный подбор | AnyTour',
        'description'=>'Подберите пакетный тур в VIVA BLUE RESORT AND DIVING SPORT: задайте параметры поездки, сравните актуальные предложения и подтвердите условия перед заявкой.',
        'intro'=>'Страница связывает проверенный hotel ID VIVA BLUE RESORT AND DIVING SPORT с текущей выдачей AnyTour. Цена, наличие, питание и вариант размещения относятся только к конкретному туру.',
        'sections'=>[
            ['id'=>'setup','title'=>'Настройте поиск','paragraphs'=>['Укажите город вылета, даты, продолжительность и состав туристов.','При ограниченной выдаче расширьте даты без смены выбранного отеля.']],
            ['id'=>'stay','title'=>'Проверьте условия проживания','paragraphs'=>['Тип номера и питание могут различаться между предложениями.','Сверяйте их в карточке конкретного пакета.']],
            ['id'=>'bundle','title'=>'Сравните турпакеты','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от предложения.','Сравнивайте туры по полному набору условий.']],
            ['id'=>'fresh','title'=>'Используйте свежую выдачу','paragraphs'=>['Коммерческие условия меняются по мере обновления данных.','Перед заявкой подтвердите выбранный вариант в AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 64291 and catalog slug viva-blue-resort-and-diving-sport-64291 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
