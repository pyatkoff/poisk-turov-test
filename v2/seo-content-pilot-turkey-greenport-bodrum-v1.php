<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_greenport_bodrum(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 1600,
        'hotel_slug' => 'greenport-hotel-bodrum-ex-aegean-garden-1600',
        'hotel_name' => 'GREENPORT HOTEL BODRUM (EX. AEGEAN GARDEN)',
    ], [
        'title' => 'Туры в GREENPORT HOTEL BODRUM — подбор тура | AnyTour',
        'description' => 'Найдите пакетные туры в GREENPORT HOTEL BODRUM: сравните даты, длительность и состав предложений, а актуальную цену и наличие проверьте в поиске AnyTour.',
        'intro' => 'Страница ведёт к актуальному подбору туров в GREENPORT HOTEL BODRUM и не закрепляет меняющиеся коммерческие условия. Сравнивайте конкретные пакеты по одинаковым исходным параметрам.',
        'sections' => [
            ['id'=>'dates','title'=>'Даты и продолжительность','paragraphs'=>['Задайте удобный диапазон вылета и продолжительность отдыха, чтобы поиск показал подходящие варианты.','Если даты гибкие, сравните несколько соседних периодов.']],
            ['id'=>'stay','title'=>'Условия размещения','paragraphs'=>['Категория номера и питание относятся к конкретному найденному предложению.','Сверяйте их непосредственно в карточке выбранного тура.']],
            ['id'=>'services','title'=>'Перелёт и услуги','paragraphs'=>['Рейс, багаж, трансфер и дополнительные услуги зависят от конкретного пакета.','Проверяйте полный состав предложения до передачи заявки.']],
            ['id'=>'final','title'=>'Финальная проверка','paragraphs'=>['Цена и наличие меняются, поэтому выбранный тур нужно перепроверить непосредственно перед заявкой.','Если предложение исчезло, используйте свежую выдачу поиска.']],
        ],
        'content_notes' => ['Hotel ID 1600 and catalog slug greenport-hotel-bodrum-ex-aegean-garden-1600 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
