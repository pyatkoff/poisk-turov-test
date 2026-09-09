<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_afytos_bodrum_city(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 71506,
        'hotel_slug' => 'afytos-bodrum-city-71506',
        'hotel_name' => 'AFYTOS BODRUM CITY',
    ], [
        'title' => 'Туры в AFYTOS BODRUM CITY — подбор пакетных предложений | AnyTour',
        'description' => 'Подберите пакетный тур в AFYTOS BODRUM CITY: сравните текущие варианты по датам, длительности, размещению и составу предложения в AnyTour.',
        'intro' => 'Страница AFYTOS BODRUM CITY не подменяет актуальную выдачу статичными условиями. Она передаёт проверенный отель в поиск, где можно сравнить доступные сейчас пакетные варианты.',
        'sections' => [
            ['id'=>'window','title'=>'Выберите диапазон поездки','paragraphs'=>['Начните с подходящего диапазона дат и продолжительности отдыха.','Соседние даты могут дать другой набор доступных пакетных предложений.']],
            ['id'=>'accommodation','title'=>'Сопоставьте варианты проживания','paragraphs'=>['Тип номера и питание зависят от конкретного тура, найденного в текущей выдаче.','Проверяйте эти параметры отдельно для каждого варианта.']],
            ['id'=>'package','title'=>'Сравните полный пакет','paragraphs'=>['Условия перелёта, багажа, трансфера и дополнительных услуг могут различаться.','Корректнее сравнивать предложения по итоговому составу, а не по одному полю.']],
            ['id'=>'latest','title'=>'Проверяйте свежий результат','paragraphs'=>['Стоимость и наличие изменяются, поэтому страница не фиксирует их как постоянные характеристики.','Перед заявкой используйте текущую выдачу поиска AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 71506 and catalog slug afytos-bodrum-city-71506 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
