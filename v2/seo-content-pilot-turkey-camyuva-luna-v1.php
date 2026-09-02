<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_camyuva_luna(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 1454,
        'hotel_slug' => 'camyuva-luna-ex-larissa-inn-1454',
        'hotel_name' => 'CAMYUVA LUNA (EX. LARISSA INN)',
    ], [
        'title' => 'Туры в CAMYUVA LUNA — подбор пакетного тура | AnyTour',
        'description' => 'Подберите пакетный тур в CAMYUVA LUNA: сравните подходящие даты, продолжительность и условия найденных предложений в актуальном поиске AnyTour.',
        'intro' => 'Страница CAMYUVA LUNA используется для точного перехода к предложениям выбранного отеля. Изменяемые цены, наличие и характеристики пакетов проверяются только в текущей выдаче.',
        'sections' => [
            ['id'=>'dates','title'=>'Сравните несколько дат','paragraphs'=>['Проверьте выбранный период и близкие даты, если хотите расширить список вариантов.','Состав туристов и продолжительность также влияют на доступную выдачу.']],
            ['id'=>'stay','title'=>'Уточните проживание и питание','paragraphs'=>['Варианты размещения зависят от конкретного турпакета.','Сверяйте номер и питание в карточке выбранного предложения.']],
            ['id'=>'flight','title'=>'Проверьте дорогу и включённые услуги','paragraphs'=>['Условия перелёта, багажа и трансфера не являются постоянными для отеля.','Перед выбором сравните состав нескольких подходящих пакетов.']],
            ['id'=>'verify','title'=>'Перепроверьте предложение','paragraphs'=>['Актуальные цена и наличие могут измениться.','Финальный вариант подтверждайте по свежему поиску перед заявкой.']],
        ],
        'content_notes' => ['Hotel ID 1454 and catalog slug camyuva-luna-ex-larissa-inn-1454 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
