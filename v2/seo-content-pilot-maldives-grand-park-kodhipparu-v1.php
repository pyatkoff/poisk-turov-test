<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_grand_park_kodhipparu(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 56835,
        'hotel_slug' => 'grand-park-kodhipparu-56835',
        'hotel_name' => 'Grand Park Kodhipparu',
    ], [
        'title' => 'Туры в Grand Park Kodhipparu — цены и подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Grand Park Kodhipparu: сравните даты, количество ночей, питание и состав пакета, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Страница помогает перейти от выбора Grand Park Kodhipparu к сравнению конкретных пакетных туров. Для корректного выбора сопоставляйте город вылета, даты, продолжительность, питание и полную стоимость предложения.',
        'sections' => [
            ['id'=>'compare','title'=>'Сравнение предложений в Grand Park Kodhipparu','paragraphs'=>['Пакеты на один отель отличаются датами, продолжительностью и условиями, поэтому сравнивайте предложения на одинаковые исходные параметры.','Финальную цену и доступность перепроверьте в поиске перед заявкой.']],
            ['id'=>'stay','title'=>'Размещение и питание','paragraphs'=>['Вариант размещения и питание относятся к выбранному туру и могут отличаться между предложениями.','Проверьте эти параметры в карточке конкретного пакета.']],
            ['id'=>'package','title'=>'Перелёт, трансфер и услуги','paragraphs'=>['Состав перелёта, багажа, трансфера и других услуг определяется конкретным предложением.','Не переносите условия одного пакета на другие варианты без проверки.']],
            ['id'=>'search','title'=>'Как найти тур в Grand Park Kodhipparu','paragraphs'=>['Откройте поиск с уже выбранным отелем и задайте город вылета, даты и состав туристов.','Если подходящих вариантов нет, расширьте диапазон дат или проверьте альтернативный город вылета.']],
        ],
        'content_notes' => ['Hotel ID 56835 and catalog slug were verified by production hotel snapshot evidence observed on 2026-09-01 and inspected on 2026-09-02.'],
    ]);
}
