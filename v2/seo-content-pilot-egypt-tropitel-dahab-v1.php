<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_tropitel_dahab(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 512,
        'hotel_slug' => 'tropitel-dahab-oasis-512',
        'hotel_name' => 'TROPITEL DAHAB OASIS',
    ], [
        'title' => 'Туры в TROPITEL DAHAB OASIS — подбор пакетного тура | AnyTour',
        'description' => 'Подберите пакетный тур в TROPITEL DAHAB OASIS: укажите вылет и даты, сравните доступные варианты и перепроверьте условия конкретного предложения в AnyTour.',
        'intro' => 'Страница предназначена для перехода к свежему поиску туров в TROPITEL DAHAB OASIS. Меняющиеся параметры — стоимость, доступность, питание и категория номера — подтверждаются только для выбранного турпакета.',
        'sections' => [
            ['id'=>'setup','title'=>'Настройте поиск под поездку','paragraphs'=>['Укажите город вылета, даты и количество туристов, после чего сравнивайте предложения выбранного отеля.','Для расширения выбора сначала меняйте диапазон дат или длительность поездки.']],
            ['id'=>'stay','title'=>'Сверьте размещение в каждом пакете','paragraphs'=>['Название отеля не заменяет проверку типа номера и питания.','Одинаковый отель может быть представлен несколькими пакетами с разными условиями проживания.']],
            ['id'=>'included','title'=>'Посмотрите включённые услуги','paragraphs'=>['Детали перелёта, багажа, трансфера и других услуг зависят от конкретного предложения.','Сопоставляйте подходящие варианты по полному набору условий.']],
            ['id'=>'final-check','title'=>'Финальная проверка тура','paragraphs'=>['Цена и наличие могут измениться после обновления выдачи.','Непосредственно перед заявкой подтвердите текущие параметры выбранного пакета в поиске AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 512 and catalog slug tropitel-dahab-oasis-512 were verified by fresh production identity snapshot evidence on 2026-09-02.'],
    ]);
}
