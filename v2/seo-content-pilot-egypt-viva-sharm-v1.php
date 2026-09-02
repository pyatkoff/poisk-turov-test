<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_viva_sharm(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 9377,
        'hotel_slug' => 'viva-sharm-ex-top-choice-viva-sharm-9377',
        'hotel_name' => 'VIVA SHARM (EX. TOP CHOICE VIVA SHARM)',
    ], [
        'title' => 'Туры в VIVA SHARM — актуальные пакетные предложения | AnyTour',
        'description' => 'Найдите пакетные туры в VIVA SHARM: задайте параметры поездки, сравните доступные варианты и подтвердите текущие условия выбранного предложения в AnyTour.',
        'intro' => 'Эта страница ведёт к актуальному поиску туров именно в VIVA SHARM. Коммерческие параметры — цена, наличие, питание и вариант размещения — проверяются только для конкретного найденного пакета.',
        'sections' => [
            ['id'=>'request','title'=>'Сформируйте запрос на поездку','paragraphs'=>['Начните с города вылета, дат и количества туристов, сохранив выбранный отель.','При ограниченном выборе можно расширить даты или продолжительность поездки.']],
            ['id'=>'options','title'=>'Сопоставьте условия проживания','paragraphs'=>['Один отель может быть представлен пакетами с разными типами номера и питания.','Сверяйте эти различия непосредственно в найденных предложениях.']],
            ['id'=>'travel','title'=>'Проверьте дорогу и услуги','paragraphs'=>['Перелёт, багаж, трансфер и другие компоненты зависят от выбранного тура.','Корректное сравнение требует учитывать полный состав пакета.']],
            ['id'=>'current','title'=>'Используйте только свежие данные','paragraphs'=>['Доступность и стоимость предложений меняются после обновлений.','Перед передачей заявки подтвердите выбранный тур в актуальной выдаче AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 9377 and catalog slug viva-sharm-ex-top-choice-viva-sharm-9377 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
