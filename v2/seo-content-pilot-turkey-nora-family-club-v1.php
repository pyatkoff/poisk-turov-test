<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_nora_family_club(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 71071,
        'hotel_slug' => 'the-nora-hotels-family-club-ex-scylax-family-club-71071',
        'hotel_name' => 'THE NORA HOTELS FAMILY CLUB (EX. SCYLAX FAMILY CLUB)',
    ], [
        'title' => 'Туры в THE NORA HOTELS FAMILY CLUB — подбор пакетного тура | AnyTour',
        'description' => 'Подберите пакетный тур в THE NORA HOTELS FAMILY CLUB: задайте параметры поездки, сравните найденные варианты и проверьте актуальные условия в AnyTour.',
        'intro' => 'Для THE NORA HOTELS FAMILY CLUB страница сохраняет только стабильную идентичность отеля и ведёт к актуальному поиску. Цена, наличие и состав услуг берутся из конкретного предложения.',
        'sections' => [
            ['id'=>'request','title'=>'Задайте исходные параметры','paragraphs'=>['Начните с города вылета, дат, продолжительности и состава туристов.','Чем точнее исходные параметры, тем корректнее сравнение найденных пакетных предложений.']],
            ['id'=>'options','title'=>'Сравните варианты размещения','paragraphs'=>['Доступные категории номера и типы питания определяются конкретными турами.','Не переносите условия одного предложения на другое без повторной проверки.']],
            ['id'=>'package','title'=>'Что входит в конкретный пакет','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги нужно проверять в карточке выбранного тура.','При похожей цене состав двух пакетов может различаться.']],
            ['id'=>'recheck','title'=>'Перепроверьте перед заявкой','paragraphs'=>['Доступность и итоговая стоимость меняются во времени.','Используйте свежую выдачу AnyTour непосредственно перед отправкой заявки.']],
        ],
        'content_notes' => ['Hotel ID 71071 and catalog slug the-nora-hotels-family-club-ex-scylax-family-club-71071 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
