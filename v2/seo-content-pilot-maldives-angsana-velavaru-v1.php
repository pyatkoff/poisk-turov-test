<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_angsana_velavaru(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 74701,
        'hotel_slug' => 'angsana-velavaru-74701',
        'hotel_name' => 'Angsana Velavaru',
    ], [
        'title' => 'Туры в Angsana Velavaru — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Angsana Velavaru: сравните даты, количество ночей, питание и состав предложения, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Страница помогает сравнивать пакетные туры именно в Angsana Velavaru. Для корректного выбора сопоставляйте город вылета, даты, продолжительность, питание и итоговую стоимость конкретного пакета.',
        'sections' => [
            ['id'=>'compare','title'=>'Сравнение туров в Angsana Velavaru','paragraphs'=>['Сопоставляйте предложения с одинаковым городом вылета, близкими датами и одинаковой продолжительностью.','Перед заявкой проверяйте актуальную стоимость и доступность выбранного варианта.']],
            ['id'=>'stay','title'=>'Параметры размещения и питания','paragraphs'=>['Размещение и питание относятся к конкретному предложению и могут различаться между пакетами.','Проверьте выбранные параметры в карточке тура перед бронированием.']],
            ['id'=>'package','title'=>'Условия конкретного пакета','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги определяются конкретным предложением.','Перед передачей заявки сверяйте полный состав выбранного тура.']],
            ['id'=>'search','title'=>'Как найти тур в Angsana Velavaru','paragraphs'=>['Откройте поиск с выбранным отелем, задайте город вылета, даты и состав туристов и сравните результаты.','Если подходящих вариантов мало, расширьте диапазон дат или проверьте другой город вылета.']],
        ],
        'content_notes' => ['Hotel ID 74701 and catalog slug were verified by production hotel snapshot evidence observed on 2026-09-01 and inspected on 2026-09-02.'],
    ]);
}
