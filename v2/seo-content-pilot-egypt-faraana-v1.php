<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_faraana(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 1968,
        'hotel_slug' => 'faraana-heights-aqua-park-1968',
        'hotel_name' => 'FARAANA HEIGHTS AQUA PARK',
    ], [
        'title' => 'Туры в FARAANA HEIGHTS AQUA PARK — поиск тура | AnyTour',
        'description' => 'Подберите тур в FARAANA HEIGHTS AQUA PARK: выберите город вылета и даты, сравните найденные пакеты и перепроверьте актуальные условия перед отправкой заявки.',
        'intro' => 'Страница помогает перейти к свежей выдаче пакетных туров в FARAANA HEIGHTS AQUA PARK. Она не подменяет текущие данные постоянными утверждениями о цене, наличии, питании или категории номера.',
        'sections' => [
            ['id'=>'search','title'=>'Начните с исходных параметров','paragraphs'=>['Укажите вылет, даты и состав туристов, чтобы сравнивать предложения на одинаковой основе.','При узкой выдаче сначала расширьте даты или длительность поездки.']],
            ['id'=>'room','title'=>'Сверьте условия размещения','paragraphs'=>['В найденных пакетах могут различаться тип номера и питание.','Выбирайте вариант только после проверки этих полей в конкретном предложении.']],
            ['id'=>'package','title'=>'Оцените турпакет целиком','paragraphs'=>['Перелёт, багаж, трансфер и другие включённые услуги зависят от условий тура.','Сопоставляйте подходящие пакеты по полному набору параметров.']],
            ['id'=>'fresh','title'=>'Используйте актуальные данные','paragraphs'=>['Предложения меняются по мере обновления доступности у туроператоров.','Перед заявкой ещё раз подтвердите параметры выбранного тура в свежей выдаче AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 1968 and catalog slug faraana-heights-aqua-park-1968 were verified by fresh production identity snapshot evidence on 2026-09-02.'],
    ]);
}
