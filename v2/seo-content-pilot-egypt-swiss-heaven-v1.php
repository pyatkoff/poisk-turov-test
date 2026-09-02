<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_swiss_heaven(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 453,
        'hotel_slug' => 'swiss-heaven-sharming-inn-453',
        'hotel_name' => 'SWISS HEAVEN SHARMING INN',
    ], [
        'title' => 'Туры в SWISS HEAVEN SHARMING INN — поиск пакетного тура | AnyTour',
        'description' => 'Найдите пакетные туры в SWISS HEAVEN SHARMING INN: задайте вылет и даты, сравните текущие предложения и перепроверьте параметры выбранного пакета перед заявкой.',
        'intro' => 'Эта страница связывает проверенную идентичность SWISS HEAVEN SHARMING INN с актуальным поиском туров. Стоимость, наличие, питание и вариант номера подтверждаются только для конкретного предложения.',
        'sections' => [
            ['id'=>'request','title'=>'Сформируйте запрос','paragraphs'=>['Начните с города вылета, дат и состава туристов, не меняя выбранный отель.','При узкой выдаче сначала расширьте диапазон дат или длительность поездки.']],
            ['id'=>'compare','title'=>'Сравните варианты размещения','paragraphs'=>['Один отель может продаваться с разными типами номера и питания.','Сверяйте эти условия в каждом подходящем пакете перед выбором.']],
            ['id'=>'included','title'=>'Уточните включённые услуги','paragraphs'=>['Детали перелёта, багажа и трансфера относятся к конкретному туру.','Для корректного сравнения рассматривайте полный состав предложения.']],
            ['id'=>'fresh','title'=>'Используйте свежую выдачу','paragraphs'=>['Доступность и стоимость могут меняться после обновления данных.','Финальные условия выбранного тура перепроверяются в AnyTour непосредственно перед заявкой.']],
        ],
        'content_notes' => ['Hotel ID 453 and catalog slug swiss-heaven-sharming-inn-453 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
