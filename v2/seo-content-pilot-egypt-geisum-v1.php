<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_geisum(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>1969,
        'hotel_slug'=>'geisum-village-1969','hotel_name'=>'GEISUM VILLAGE',
    ], [
        'title'=>'Туры в GEISUM VILLAGE — подбор пакетного тура | AnyTour',
        'description'=>'Подберите пакетный тур в GEISUM VILLAGE: задайте параметры поездки, сравните актуальные предложения и подтвердите выбранные условия в AnyTour.',
        'intro'=>'Страница связывает подтверждённый hotel ID GEISUM VILLAGE с актуальным поиском туров. Цена, наличие, питание и категория номера относятся только к конкретному пакету.',
        'sections'=>[
            ['id'=>'query','title'=>'Задайте параметры поездки','paragraphs'=>['Укажите город вылета, даты и состав туристов.','Если вариантов мало, расширьте диапазон дат или продолжительность.']],
            ['id'=>'stay','title'=>'Сравните проживание','paragraphs'=>['Тип номера и питание могут различаться между предложениями.','Проверяйте эти параметры в карточке каждого тура.']],
            ['id'=>'package','title'=>'Уточните состав пакета','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от конкретного предложения.','Сравнивайте полный набор условий.']],
            ['id'=>'final','title'=>'Проверьте перед заявкой','paragraphs'=>['Данные о наличии и стоимости меняются со временем.','Перед заявкой подтвердите выбранный тур в свежей выдаче AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 1969 and catalog slug geisum-village-1969 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
