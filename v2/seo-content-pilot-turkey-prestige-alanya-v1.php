<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_prestige_alanya(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 1236,
        'hotel_slug' => 'prestige-alanya-ex-euphoria-comfort-beach-alanya-1236',
        'hotel_name' => 'PRESTIGE ALANYA (EX.EUPHORIA COMFORT BEACH ALANYA)',
    ], [
        'title' => 'Туры в PRESTIGE ALANYA — поиск пакетного тура | AnyTour',
        'description' => 'Найдите пакетный тур в PRESTIGE ALANYA: сравните актуальные варианты по датам, длительности и составу конкретного предложения в AnyTour.',
        'intro' => 'Review-страница PRESTIGE ALANYA сохраняет проверенный hotel ID и slug, чтобы открыть поиск нужного отеля. Условия поездки не считаются постоянными и проверяются в конкретном туре.',
        'sections' => [
            ['id'=>'query','title'=>'Сформируйте запрос','paragraphs'=>['Укажите город вылета, даты, длительность и состав туристов.','Для сравнения используйте одинаковые исходные параметры у нескольких предложений.']],
            ['id'=>'stay','title'=>'Сверьте проживание','paragraphs'=>['Доступные категории номера и питание относятся к текущим пакетным предложениям.','Проверяйте выбранное размещение непосредственно перед заявкой.']],
            ['id'=>'package','title'=>'Проверьте пакет целиком','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от конкретного варианта.','Учитывайте их при сравнении итоговых условий поездки.']],
            ['id'=>'final','title'=>'Финальная актуализация','paragraphs'=>['Цена и наличие могут измениться после первого просмотра.','Перед заявкой повторно откройте актуальное предложение в поиске AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 1236 and catalog slug prestige-alanya-ex-euphoria-comfort-beach-alanya-1236 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
