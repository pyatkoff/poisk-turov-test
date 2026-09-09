<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_rosella(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 21814,
        'hotel_slug' => 'rosella-apart-hotel-21814',
        'hotel_name' => 'ROSELLA APART & HOTEL',
    ], [
        'title' => 'Туры в ROSELLA APART & HOTEL — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в ROSELLA APART & HOTEL: сравните даты, продолжительность и состав конкретных предложений, а актуальные условия проверьте в поиске AnyTour.',
        'intro' => 'Страница помогает перейти к актуальным пакетным предложениям ROSELLA APART & HOTEL без закрепления меняющихся цен и наличия. Финальные параметры всегда проверяйте в выбранном туре.',
        'sections' => [
            ['id'=>'start','title'=>'Настройка поиска ROSELLA APART & HOTEL','paragraphs'=>['Укажите город вылета, диапазон дат и состав туристов, чтобы получить подходящие пакетные варианты.','Если предложений мало, расширьте даты или измените продолжительность отдыха.']],
            ['id'=>'stay','title'=>'Размещение в выбранном туре','paragraphs'=>['Категория номера и питание могут различаться между доступными пакетами.','Сверяйте их непосредственно в карточке конкретного предложения.']],
            ['id'=>'package','title'=>'Перелёт и дополнительные услуги','paragraphs'=>['Рейс, багаж, трансфер и дополнительные услуги определяются конкретным турпакетом.','Сравнивайте полный состав предложения, а не только название отеля.']],
            ['id'=>'verify','title'=>'Что проверить перед заявкой','paragraphs'=>['Перед отправкой заявки повторно сверяйте цену, даты и наличие выбранного варианта.','Если условия изменились, вернитесь к свежей выдаче поиска.']],
        ],
        'content_notes' => ['Hotel ID 21814 and catalog slug rosella-apart-hotel-21814 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
