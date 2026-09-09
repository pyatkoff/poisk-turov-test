<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_perre_delta(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 55200,
        'hotel_slug' => 'perre-delta-ex-ganita-delta-resort-55200',
        'hotel_name' => 'PERRE DELTA (EX. GANITA DELTA RESORT)',
    ], [
        'title' => 'Туры в PERRE DELTA — подбор пакетного тура | AnyTour',
        'description' => 'Подберите пакетный тур в PERRE DELTA: сравните актуальные предложения по датам, продолжительности и составу пакета в поиске AnyTour.',
        'intro' => 'Страница PERRE DELTA хранит только стабильную идентичность отеля. Стоимость, наличие, питание и другие условия следует брать из текущего найденного турпакета.',
        'sections' => [
            ['id'=>'compare','title'=>'С чего начать сравнение','paragraphs'=>['Задайте одинаковые даты, город вылета и состав туристов для корректного сравнения вариантов.','Если выдача ограничена, проверьте соседние даты и продолжительность.']],
            ['id'=>'stay','title'=>'Сверьте размещение','paragraphs'=>['Категория номера и питание могут отличаться у разных пакетных предложений.','Проверяйте параметры выбранного варианта непосредственно в карточке тура.']],
            ['id'=>'included','title'=>'Проверьте состав пакета','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги зависят от конкретного предложения.','Сравнивайте пакет целиком перед выбором.']],
            ['id'=>'fresh','title'=>'Проверка актуальности','paragraphs'=>['Цена и доступность меняются, поэтому не используйте старый результат поиска как подтверждение.','Перед заявкой откройте актуальное предложение AnyTour повторно.']],
        ],
        'content_notes' => ['Hotel ID 55200 and catalog slug perre-delta-ex-ganita-delta-resort-55200 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
