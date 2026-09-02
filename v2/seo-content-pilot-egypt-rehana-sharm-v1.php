<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_rehana_sharm(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>380,
        'hotel_slug'=>'rehana-sharm-resort-aqua-park-spa-380','hotel_name'=>'REHANA SHARM RESORT AQUA PARK & SPA',
    ], [
        'title'=>'Туры в REHANA SHARM RESORT AQUA PARK & SPA — подбор тура | AnyTour',
        'description'=>'Подберите пакетный тур в REHANA SHARM RESORT AQUA PARK & SPA: укажите параметры поездки, сравните свежие предложения и подтвердите выбранный пакет в AnyTour.',
        'intro'=>'Страница передаёт в поиск подтверждённый hotel ID REHANA SHARM RESORT AQUA PARK & SPA. Цена, доступность, питание и тип номера относятся только к конкретному предложению.',
        'sections'=>[
            ['id'=>'search','title'=>'Задайте параметры поиска','paragraphs'=>['Укажите город вылета, даты и состав туристов.','При малой выдаче расширьте даты или продолжительность, сохранив отель.']],
            ['id'=>'stay','title'=>'Проверьте размещение','paragraphs'=>['Тип номера и питание могут различаться между пакетами.','Сверяйте их в карточке каждого тура.']],
            ['id'=>'package','title'=>'Сравните пакет целиком','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги определяются конкретным предложением.','Сопоставляйте полный набор условий.']],
            ['id'=>'verify','title'=>'Подтвердите актуальность','paragraphs'=>['Стоимость и наличие меняются после обновления данных.','Перед заявкой повторно проверьте выбранный тур в AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 380 and catalog slug rehana-sharm-resort-aqua-park-spa-380 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
