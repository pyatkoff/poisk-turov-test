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
        'title' => 'Туры в VIVA SHARM — актуальный поиск тура | AnyTour',
        'description' => 'Найдите пакетные туры в VIVA SHARM: выберите город вылета и даты, сравните текущие предложения и подтвердите параметры подходящего пакета перед заявкой.',
        'intro' => 'Эта страница ведёт к предложениям, связанным с проверенным hotel ID VIVA SHARM. Меняющиеся условия — стоимость, наличие, питание и тип номера — проверяются только в конкретном найденном туре.',
        'sections' => [
            ['id'=>'start','title'=>'Начните с параметров поездки','paragraphs'=>['Задайте город вылета, даты и состав туристов, не снимая фильтр выбранного отеля.','Для более широкой выдачи можно увеличить диапазон дат или продолжительность.']],
            ['id'=>'options','title'=>'Сопоставьте варианты проживания','paragraphs'=>['Тип номера и питание могут различаться между предложениями одного отеля.','Сверяйте эти параметры отдельно для каждого найденного пакета.']],
            ['id'=>'details','title'=>'Проверьте детали поездки','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги определяются условиями конкретного тура.','Не сравнивайте предложения только по первой видимой цене.']],
            ['id'=>'verify','title'=>'Перепроверьте перед заявкой','paragraphs'=>['Коммерческие условия обновляются и могут измениться после первого просмотра.','Финальные параметры выбранного тура подтвердите в актуальной выдаче AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 9377 and catalog slug viva-sharm-ex-top-choice-viva-sharm-9377 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
