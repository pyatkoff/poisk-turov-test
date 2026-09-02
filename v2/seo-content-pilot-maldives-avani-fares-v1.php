<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_avani_fares(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 82538,
        'hotel_slug' => 'avani-fares-maldives-resort-82538',
        'hotel_name' => 'Avani+ Fares Maldives Resort',
    ], [
        'title' => 'Туры в Avani+ Fares Maldives Resort — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Avani+ Fares Maldives Resort: сравните даты, продолжительность, питание и состав предложения, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Эта страница помогает перейти от выбора Avani+ Fares Maldives Resort к сравнению конкретных пакетных туров. Сопоставляйте варианты по городу вылета, датам, продолжительности, питанию и полной стоимости пакета.',
        'sections' => [
            ['id'=>'compare','title'=>'Как сравнивать туры в Avani+ Fares Maldives Resort','paragraphs'=>['Сравнивайте предложения на близкие даты и одинаковую продолжительность, чтобы разница в стоимости была понятнее.','Перед заявкой перепроверьте цену и доступность выбранного варианта в поиске.']],
            ['id'=>'stay','title'=>'Размещение и питание','paragraphs'=>['Категория размещения и тип питания относятся к конкретному пакету и могут отличаться между предложениями.','Проверьте их в карточке выбранного тура перед бронированием.']],
            ['id'=>'package','title'=>'Состав пакета','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги определяются конкретным предложением.','Окончательный состав тура нужно сверять непосредственно перед передачей заявки.']],
            ['id'=>'search','title'=>'Как найти подходящий тур','paragraphs'=>['Откройте поиск с выбранным отелем и задайте город вылета, даты и состав туристов.','Если вариантов мало, расширьте диапазон дат или сравните вылет из другого доступного города.']],
        ],
        'content_notes' => ['Hotel ID 82538 and catalog slug were verified by production hotel snapshot evidence observed on 2026-09-01 and inspected on 2026-09-02.'],
    ]);
}
