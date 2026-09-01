<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_furaveri(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 46068,
        'hotel_slug' => 'furaveri-island-resort-spa-46068',
        'hotel_name' => 'Furaveri Island Resort & Spa',
    ], [
        'title' => 'Туры в Furaveri Island Resort & Spa — цены и подбор | AnyTour',
        'description' => 'Подберите тур в Furaveri Island Resort & Spa: сравните даты, количество ночей, питание и состав пакета, а актуальную стоимость и доступность проверьте в AnyTour.',
        'intro' => 'Страница помогает сравнивать именно пакетные туры в Furaveri Island Resort & Spa. Оценивайте вместе дату и город вылета, длительность, питание, размещение и итоговую стоимость конкретного предложения.',
        'sections' => [
            ['id'=>'price','title'=>'Как читать цену тура','paragraphs'=>['Цена относится к конкретному сочетанию дат, города вылета и продолжительности. Поэтому сравнивать варианты корректно только при близких параметрах поездки.','Стоимость и доступность обновляются, и перед заявкой их нужно повторно проверить в поиске.']],
            ['id'=>'details','title'=>'Что входит в конкретный пакет','paragraphs'=>['Питание, размещение, перелёт, багаж, трансфер и другие услуги могут различаться между предложениями. Страница не закрепляет их как постоянные свойства тура в отель.','Проверяйте финальный состав пакета после выбора конкретного варианта.']],
            ['id'=>'search','title'=>'Поиск тура в Furaveri Island Resort & Spa','paragraphs'=>['Перейдите в поиск AnyTour с выбранным отелем, укажите даты, город вылета и состав туристов, затем сравните доступные предложения.','Если вариантов мало, измените период или город вылета, сохранив выбранный отель.']],
        ],
        'content_notes' => ['Hotel ID 46068 and slug furaveri-island-resort-spa-46068 were verified by fresh production snapshot inspection on 2026-09-01.'],
    ]);
}
