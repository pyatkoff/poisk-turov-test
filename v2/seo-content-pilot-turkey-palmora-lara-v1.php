<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_palmora_lara(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 65770,
        'hotel_slug' => 'palmora-lara-hotel-ex-jura-hotels-lara-resort-65770',
        'hotel_name' => 'PALMORA LARA HOTEL (EX. JURA HOTELS LARA RESORT)',
    ], [
        'title' => 'Туры в PALMORA LARA HOTEL — поиск пакетных предложений | AnyTour',
        'description' => 'Найдите пакетный тур в PALMORA LARA HOTEL: сравните подходящие даты, продолжительность и условия конкретных предложений в актуальном поиске AnyTour.',
        'intro' => 'Review-страница PALMORA LARA HOTEL не хранит постоянную цену или наличие. Она фиксирует проверенную идентичность отеля и передаёт её в поиск для сравнения текущих пакетных туров.',
        'sections' => [
            ['id'=>'search','title'=>'Поиск подходящих вариантов','paragraphs'=>['Укажите город вылета, даты и состав туристов, затем сравните доступные предложения для этого отеля.','Если вариантов мало, полезно проверить соседние даты и другую продолжительность поездки.']],
            ['id'=>'conditions','title'=>'Условия размещения','paragraphs'=>['Питание и категория номера зависят от выбранного предложения.','Проверяйте точное название варианта размещения перед переходом к заявке.']],
            ['id'=>'details','title'=>'Детали турпакета','paragraphs'=>['Параметры перелёта, багажа и трансфера относятся к конкретному пакету и могут меняться.','Сопоставляйте предложения по полному составу, а не по одному показателю.']],
            ['id'=>'current','title'=>'Используйте актуальную выдачу','paragraphs'=>['Наличие и стоимость не являются постоянными характеристиками страницы.','Финальные условия подтверждайте в свежем результате поиска непосредственно перед заявкой.']],
        ],
        'content_notes' => ['Hotel ID 65770 and catalog slug palmora-lara-hotel-ex-jura-hotels-lara-resort-65770 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
