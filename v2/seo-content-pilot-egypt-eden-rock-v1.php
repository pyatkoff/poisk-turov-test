<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_eden_rock(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>198,
        'hotel_slug'=>'eden-rock-hotel-198','hotel_name'=>'EDEN ROCK HOTEL',
    ], [
        'title'=>'Туры в EDEN ROCK HOTEL — поиск пакетного тура | AnyTour',
        'description'=>'Найдите пакетный тур в EDEN ROCK HOTEL: выберите вылет и даты, сравните текущие предложения и подтвердите параметры выбранного варианта в AnyTour.',
        'intro'=>'Эта страница передаёт в поиск проверенную идентичность EDEN ROCK HOTEL. Стоимость, доступность, питание и категория номера не считаются постоянными характеристиками страницы.',
        'sections'=>[
            ['id'=>'request','title'=>'Задайте параметры поездки','paragraphs'=>['Начните с города вылета, дат и состава туристов.','При узкой выдаче расширьте диапазон дат или продолжительность.']],
            ['id'=>'compare','title'=>'Сравните варианты проживания','paragraphs'=>['Питание и тип номера могут отличаться в разных пакетах.','Сверяйте эти параметры для каждого найденного предложения.']],
            ['id'=>'included','title'=>'Уточните включённые услуги','paragraphs'=>['Условия перелёта, багажа и трансфера относятся к конкретному туру.','Сравнивайте предложения по полному составу.']],
            ['id'=>'confirm','title'=>'Проверьте перед заявкой','paragraphs'=>['Наличие и цена обновляются вместе с данными поставщиков.','Финальные условия подтвердите в свежей выдаче AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 198 and catalog slug eden-rock-hotel-198 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
