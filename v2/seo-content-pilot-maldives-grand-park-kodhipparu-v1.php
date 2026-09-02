<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_grand_park_kodhipparu(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 48640,
        'hotel_slug' => 'grand-park-kodhipparu-maldives-48640',
        'hotel_name' => 'Grand Park Kodhipparu Maldives',
    ], [
        'title' => 'Туры в Grand Park Kodhipparu Maldives — цены и подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Grand Park Kodhipparu Maldives: сравните даты, количество ночей, питание и состав пакета, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Страница помогает перейти от выбора Grand Park Kodhipparu Maldives к сравнению конкретных пакетных туров. Для корректного выбора сопоставляйте город вылета, даты, продолжительность, питание и полную стоимость предложения.',
        'sections' => [
            ['id'=>'compare','title'=>'Сравнение предложений в Grand Park Kodhipparu Maldives','paragraphs'=>['Пакеты на один отель отличаются датами, продолжительностью и условиями, поэтому сравнивайте предложения на одинаковые исходные параметры.','Финальную цену и доступность перепроверьте в поиске перед заявкой.']],
            ['id'=>'stay','title'=>'Размещение и питание','paragraphs'=>['Вариант размещения и питание относятся к выбранному туру и могут отличаться между предложениями.','Проверьте эти параметры в карточке конкретного пакета.']],
            ['id'=>'package','title'=>'Перелёт, трансфер и услуги','paragraphs'=>['Состав перелёта, багажа, трансфера и других услуг определяется конкретным предложением.','Не переносите условия одного пакета на другие варианты без проверки.']],
            ['id'=>'search','title'=>'Как найти тур в Grand Park Kodhipparu Maldives','paragraphs'=>['Откройте поиск с уже выбранным отелем и задайте город вылета, даты и состав туристов.','Если подходящих вариантов нет, расширьте диапазон дат или проверьте альтернативный город вылета.']],
        ],
        'content_notes' => ['Hotel ID 48640 and catalog slug were verified by fresh production hotel snapshot evidence on 2026-09-01.'],
    ]);
}
