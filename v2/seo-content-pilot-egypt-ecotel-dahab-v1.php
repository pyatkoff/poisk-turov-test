<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_ecotel_dahab(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 322,
        'hotel_slug' => 'ecotel-dahab-bay-view-resort-322',
        'hotel_name' => 'ECOTEL DAHAB BAY VIEW RESORT',
    ], [
        'title' => 'Туры в ECOTEL DAHAB BAY VIEW RESORT — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в ECOTEL DAHAB BAY VIEW RESORT: укажите вылет и даты, сравните текущие варианты и перепроверьте условия выбранного пакета в AnyTour.',
        'intro' => 'Страница предназначена для перехода к свежей выдаче туров в ECOTEL DAHAB BAY VIEW RESORT. Цена, наличие, питание и тип номера не являются постоянными данными страницы и подтверждаются в конкретном предложении.',
        'sections' => [
            ['id'=>'setup','title'=>'Настройте параметры поиска','paragraphs'=>['Укажите город вылета, даты и состав туристов перед сравнением вариантов.','Если выдача узкая, сначала измените диапазон дат или продолжительность, сохраняя выбранный отель.']],
            ['id'=>'stay','title'=>'Уточните размещение','paragraphs'=>['В найденных пакетах могут различаться категория номера и питание.','Проверяйте эти параметры отдельно в каждом подходящем предложении.']],
            ['id'=>'included','title'=>'Смотрите, что входит в пакет','paragraphs'=>['Условия перелёта, багажа, трансфера и дополнительных услуг зависят от конкретного тура.','Сравнивайте предложения по полному набору условий.']],
            ['id'=>'verify','title'=>'Перепроверьте перед заявкой','paragraphs'=>['Коммерческие условия меняются вместе с доступностью у туроператоров.','Перед отправкой заявки ещё раз подтвердите параметры выбранного пакета в AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 322 and catalog slug ecotel-dahab-bay-view-resort-322 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
