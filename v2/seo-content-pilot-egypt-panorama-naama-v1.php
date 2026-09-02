<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_panorama_naama(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>350,
        'hotel_slug'=>'panorama-naama-heights-350','hotel_name'=>'PANORAMA NAAMA HEIGHTS',
    ], [
        'title'=>'Туры в PANORAMA NAAMA HEIGHTS — актуальный подбор тура | AnyTour',
        'description'=>'Подберите тур в PANORAMA NAAMA HEIGHTS: задайте параметры поездки, сравните свежие пакетные предложения и перепроверьте условия перед заявкой в AnyTour.',
        'intro'=>'Страница использует только подтверждённый hotel ID PANORAMA NAAMA HEIGHTS и ведёт к актуальному поиску. Цена, наличие, питание и тип номера зависят от выбранного предложения.',
        'sections'=>[
            ['id'=>'setup','title'=>'Настройте исходные параметры','paragraphs'=>['Укажите город вылета, даты, продолжительность и туристов.','Если вариантов мало, расширьте даты, сохранив выбранный отель.']],
            ['id'=>'stay','title'=>'Проверьте вариант размещения','paragraphs'=>['Категория номера и питание могут отличаться между предложениями.','Смотрите эти условия в карточке конкретного тура.']],
            ['id'=>'bundle','title'=>'Сопоставьте турпакеты','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги определяются конкретным пакетом.','Сравнивайте предложения по полному набору условий.']],
            ['id'=>'final','title'=>'Финальная проверка','paragraphs'=>['Доступность и стоимость меняются со временем.','Перед отправкой заявки подтвердите выбранный тур в свежей выдаче AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 350 and catalog slug panorama-naama-heights-350 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
