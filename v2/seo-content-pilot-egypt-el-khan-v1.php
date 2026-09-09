<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_el_khan(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 81245,
        'hotel_slug' => 'el-khan-sharm-hotel-81245',
        'hotel_name' => 'EL KHAN SHARM HOTEL',
    ], [
        'title' => 'Туры в EL KHAN SHARM HOTEL — подбор пакетного тура | AnyTour',
        'description' => 'Подберите пакетный тур в EL KHAN SHARM HOTEL: укажите параметры поездки, сравните актуальные предложения и подтвердите условия выбранного пакета в AnyTour.',
        'intro' => 'Страница ведёт к свежей выдаче туров именно в EL KHAN SHARM HOTEL. Цена, наличие, питание и категория размещения относятся к конкретному предложению и не фиксируются редакционным текстом.',
        'sections' => [
            ['id'=>'start','title'=>'Задайте параметры поездки','paragraphs'=>['Укажите город вылета, диапазон дат и состав туристов перед сравнением предложений.','Если вариантов мало, расширьте даты или продолжительность, сохранив выбранный отель.']],
            ['id'=>'stay','title'=>'Сверьте условия проживания','paragraphs'=>['Пакеты одного отеля могут отличаться типом номера и питанием.','Эти параметры необходимо проверять в карточке каждого найденного тура.']],
            ['id'=>'package','title'=>'Проверьте состав пакета','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги определяются конкретным предложением.','Сравнивайте туры по полному составу, а не только по первой отображаемой стоимости.']],
            ['id'=>'verify','title'=>'Подтвердите актуальность','paragraphs'=>['Коммерческие условия меняются по мере обновления данных туроператоров.','Перед заявкой ещё раз проверьте выбранный тур в актуальной выдаче AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 81245 and catalog slug el-khan-sharm-hotel-81245 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
