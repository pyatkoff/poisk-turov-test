<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_juniper(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 16777,
        'hotel_slug' => 'juniper-adults-only-16-16777',
        'hotel_name' => 'JUNIPER ADULTS ONLY 16+',
    ], [
        'title' => 'Туры в JUNIPER ADULTS ONLY 16+ — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в JUNIPER ADULTS ONLY 16+: сравните текущие даты, продолжительность и состав предложений в поиске AnyTour.',
        'intro' => 'Страница JUNIPER ADULTS ONLY 16+ фиксирует только проверенную идентичность отеля и ведёт к текущей выдаче. Цена и наличие всегда проверяются в конкретном туре.',
        'sections' => [
            ['id'=>'dates','title'=>'Подберите даты и длительность','paragraphs'=>['Сравните несколько подходящих дат и вариантов продолжительности поездки.','Доступность пакетных туров может меняться даже при небольшом сдвиге дат.']],
            ['id'=>'stay','title'=>'Сверьте условия проживания','paragraphs'=>['Тип номера и питание задаются конкретным предложением.','Перед заявкой проверьте выбранные условия размещения в карточке тура.']],
            ['id'=>'travel','title'=>'Проверьте транспорт и услуги','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги зависят от состава пакета.','Сравнивайте варианты по полному набору условий.']],
            ['id'=>'confirm','title'=>'Подтвердите актуальность','paragraphs'=>['Стоимость и доступность не закрепляются на этой странице.','Финальные параметры подтверждайте по свежему результату поиска AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 16777 and catalog slug juniper-adults-only-16-16777 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
