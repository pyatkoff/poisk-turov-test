<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_banyan_vabbinfaru(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 2423,
        'hotel_slug' => 'banyan-tree-maldives-vabbinfaru-2423',
        'hotel_name' => 'Banyan Tree Maldives Vabbinfaru',
    ], [
        'title' => 'Туры в Banyan Tree Maldives Vabbinfaru — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Banyan Tree Maldives Vabbinfaru: сравните даты, продолжительность, питание и состав предложения, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Страница связывает выбор Banyan Tree Maldives Vabbinfaru с актуальным поиском пакетных туров. Сравнивайте конкретные предложения по датам, городу вылета, продолжительности, питанию и полной стоимости пакета.',
        'sections' => [
            ['id'=>'compare','title'=>'Сравнение туров в Banyan Tree Maldives Vabbinfaru','paragraphs'=>['Для корректного сравнения используйте одинаковый город вылета, близкие даты и одинаковую продолжительность.','Финальную стоимость и наличие предложения всегда перепроверяйте перед заявкой.']],
            ['id'=>'stay','title'=>'Параметры размещения','paragraphs'=>['Категория размещения и питание относятся к выбранному туру и не являются постоянными параметрами страницы.','Проверьте их непосредственно в карточке конкретного предложения.']],
            ['id'=>'package','title'=>'Условия конкретного тура','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от состава выбранного пакета.','Перед заявкой сверяйте все включённые услуги и условия конкретного варианта.']],
            ['id'=>'search','title'=>'Переход к актуальным предложениям','paragraphs'=>['Откройте поиск с выбранным отелем и задайте даты, город вылета и состав туристов.','Если выбор ограничен, расширьте диапазон дат или проверьте альтернативный город вылета.']],
        ],
        'content_notes' => ['Hotel ID 2423 and catalog slug were verified by production hotel snapshot evidence observed on 2026-09-01 and inspected on 2026-09-02.'],
    ]);
}
