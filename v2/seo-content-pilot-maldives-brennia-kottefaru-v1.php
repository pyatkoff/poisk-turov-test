<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_brennia_kottefaru(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 72602,
        'hotel_slug' => 'brennia-kottefaru-72602',
        'hotel_name' => 'Brennia Kottefaru',
    ], [
        'title' => 'Туры в Brennia Kottefaru — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Brennia Kottefaru: сравните даты, продолжительность, питание и состав предложения, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Страница предназначена для сравнения пакетных туров именно в Brennia Kottefaru. Сопоставляйте конкретные предложения по городу вылета, датам, количеству ночей, питанию и полной стоимости пакета.',
        'sections' => [
            ['id'=>'compare','title'=>'Как сравнивать туры в Brennia Kottefaru','paragraphs'=>['Сравнивайте варианты с близкими датами и одинаковой продолжительностью, чтобы корректно оценивать различия между пакетами.','Перед заявкой перепроверьте актуальную стоимость и доступность выбранного предложения.']],
            ['id'=>'stay','title'=>'Размещение и питание','paragraphs'=>['Вариант размещения и питание относятся к конкретному туру и могут различаться между предложениями.','Проверьте выбранные параметры непосредственно в карточке пакета.']],
            ['id'=>'package','title'=>'Состав конкретного тура','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги зависят от выбранного предложения.','Перед передачей заявки сверяйте полный состав пакета.']],
            ['id'=>'search','title'=>'Как найти тур в Brennia Kottefaru','paragraphs'=>['Откройте поиск с выбранным отелем и укажите город вылета, даты и состав туристов.','Если вариантов мало, расширьте диапазон дат или проверьте другой доступный город вылета.']],
        ],
        'content_notes' => ['Hotel ID 72602 and catalog slug were verified by production hotel snapshot evidence observed on 2026-09-01 and inspected on 2026-09-02.'],
    ]);
}
