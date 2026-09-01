<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_machchafushi(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 2427,
        'hotel_slug' => 'machchafushi-island-resort-spa-maldives-ex-centara-grand-island-resort-spa-maldives-2427',
        'hotel_name' => 'Machchafushi Island Resort & Spa Maldives',
    ], [
        'title' => 'Туры в Machchafushi Island Resort & Spa Maldives — цены | AnyTour',
        'description' => 'Подберите тур в Machchafushi Island Resort & Spa Maldives: сравните даты, ночи, питание и состав пакета, а актуальную цену и доступность проверьте в AnyTour.',
        'intro' => 'Туры в Machchafushi Island Resort & Spa Maldives стоит сравнивать как готовые пакеты: дата и город вылета, продолжительность, питание, размещение и итоговая стоимость могут различаться даже для одного отеля.',
        'sections' => [
            ['id'=>'compare','title'=>'Сравнение пакетных туров','paragraphs'=>['Для объективного сравнения выбирайте предложения на близкие даты и одинаковое количество ночей. Так проще понять, чем обусловлена разница в стоимости.','Минимальная цена относится к конкретному предложению и не является постоянной ценой тура в отель.']],
            ['id'=>'conditions','title'=>'Условия выбранного предложения','paragraphs'=>['Питание, размещение, перелёт, багаж и трансфер нужно проверять в карточке конкретного тура. Эти параметры могут отличаться между предложениями.','Перед заявкой перепроверьте состав пакета и итоговую стоимость в поиске AnyTour.']],
            ['id'=>'search','title'=>'Как найти подходящий тур','paragraphs'=>['Перейдите в поиск с уже выбранным отелем, задайте город вылета, даты и состав туристов и сравните доступные варианты.','Если подходящих предложений нет, попробуйте соседние даты или другой город вылета, сохранив выбранный отель.']],
        ],
        'content_notes' => ['Hotel ID 2427 and catalog slug were verified by fresh production snapshot inspection on 2026-09-01.'],
    ]);
}
