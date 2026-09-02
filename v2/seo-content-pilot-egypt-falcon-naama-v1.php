<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_falcon_naama(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>210,
        'hotel_slug'=>'falcon-naama-star-210','hotel_name'=>'FALCON NAAMA STAR',
    ], [
        'title'=>'Туры в FALCON NAAMA STAR — подбор пакетного тура | AnyTour',
        'description'=>'Подберите пакетный тур в FALCON NAAMA STAR: задайте вылет и даты, сравните актуальные предложения и подтвердите условия выбранного пакета в AnyTour.',
        'intro'=>'Страница ведёт к свежей выдаче туров по проверенному hotel ID FALCON NAAMA STAR. Цена, наличие, питание и тип номера определяются конкретным предложением и не фиксируются в тексте.',
        'sections'=>[
            ['id'=>'query','title'=>'Задайте параметры поездки','paragraphs'=>['Укажите город вылета, даты и состав туристов перед сравнением вариантов.','Если выдача узкая, расширьте диапазон дат или продолжительность.']],
            ['id'=>'stay','title'=>'Сверьте проживание','paragraphs'=>['Питание и категория номера могут отличаться между пакетами.','Проверяйте их в карточке конкретного предложения.']],
            ['id'=>'package','title'=>'Проверьте состав пакета','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от выбранного тура.','Сравнивайте полный набор условий.']],
            ['id'=>'current','title'=>'Подтвердите актуальность','paragraphs'=>['Данные о стоимости и наличии меняются со временем.','Перед заявкой ещё раз проверьте выбранный тур в AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 210 and catalog slug falcon-naama-star-210 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
