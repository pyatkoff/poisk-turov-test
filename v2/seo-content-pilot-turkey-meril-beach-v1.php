<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_meril_beach(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 42916,
        'hotel_slug' => 'meril-beach-hotel-turunc-adults-only-16-42916',
        'hotel_name' => 'MERIL BEACH HOTEL TURUNC ADULTS ONLY 16+',
    ], [
        'title' => 'Туры в MERIL BEACH HOTEL TURUNC ADULTS ONLY 16+ — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в MERIL BEACH HOTEL TURUNC ADULTS ONLY 16+: сравните даты и состав предложений, а актуальные условия проверьте в поиске AnyTour.',
        'intro' => 'Страница ведёт к текущим пакетным предложениям MERIL BEACH HOTEL TURUNC ADULTS ONLY 16+ и не фиксирует меняющиеся цены или наличие. Перед заявкой сверяйте параметры выбранного тура.',
        'sections' => [
            ['id'=>'dates','title'=>'Сравнение дат поездки','paragraphs'=>['Проверьте несколько соседних дат и продолжительностей, чтобы увидеть доступный диапазон пакетных вариантов.','Сравнивать лучше предложения с одинаковым составом туристов и городом вылета.']],
            ['id'=>'room','title'=>'Номер и питание','paragraphs'=>['Тип размещения и питание относятся к конкретному найденному пакету.','Перед заявкой убедитесь, что выбранные параметры соответствуют вашим ожиданиям.']],
            ['id'=>'flight','title'=>'Перелёт и услуги в пакете','paragraphs'=>['Условия перелёта, багажа, трансфера и дополнительных услуг могут отличаться между предложениями.','Оценивайте итоговый пакет целиком, а не только его начальную стоимость.']],
            ['id'=>'final','title'=>'Финальная проверка','paragraphs'=>['Цена и доступность могут измениться между поиском и заявкой.','Перед передачей заявки перепроверьте актуальное предложение в поиске AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 42916 and catalog slug meril-beach-hotel-turunc-adults-only-16-42916 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
