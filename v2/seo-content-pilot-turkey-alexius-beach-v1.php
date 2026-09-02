<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_alexius_beach(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 1039,
        'hotel_slug' => 'alexius-beach-hotel-ex-beldiana-club-1039',
        'hotel_name' => 'ALEXIUS BEACH HOTEL (EX. BELDIANA CLUB)',
    ], [
        'title' => 'Туры в ALEXIUS BEACH HOTEL — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в ALEXIUS BEACH HOTEL: сравните даты, продолжительность и состав предложений, а актуальные условия проверьте в поиске AnyTour.',
        'intro' => 'Review-страница помогает перейти к актуальному подбору туров в ALEXIUS BEACH HOTEL без фиксации меняющихся цен и наличия. Финальные условия проверяйте в конкретном найденном пакете.',
        'sections' => [
            ['id'=>'search','title'=>'Параметры поиска ALEXIUS BEACH HOTEL','paragraphs'=>['Укажите город вылета, даты и состав туристов, чтобы получить релевантные предложения.','При небольшом выборе попробуйте соседние даты или другую продолжительность отдыха.']],
            ['id'=>'stay','title'=>'Размещение и питание','paragraphs'=>['Категория номера и тип питания зависят от конкретного пакетного предложения.','Проверяйте эти параметры в карточке выбранного тура перед заявкой.']],
            ['id'=>'package','title'=>'Состав турпакета','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги могут отличаться между вариантами.','Сравнивайте полный состав предложения, а не только название отеля.']],
            ['id'=>'verify','title'=>'Проверка перед заявкой','paragraphs'=>['Стоимость и наличие меняются, поэтому выбранный вариант необходимо перепроверить перед отправкой заявки.','Если условия изменились, вернитесь к свежей выдаче поиска.']],
        ],
        'content_notes' => ['Hotel ID 1039 and catalog slug alexius-beach-hotel-ex-beldiana-club-1039 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
