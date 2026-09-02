<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_fortuna_marmaris(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 42576,
        'hotel_slug' => 'fortuna-marmaris-42576',
        'hotel_name' => 'FORTUNA MARMARIS',
    ], [
        'title' => 'Туры в FORTUNA MARMARIS — актуальные пакетные варианты | AnyTour',
        'description' => 'Найдите пакетный тур в FORTUNA MARMARIS: задайте город вылета, даты и состав туристов и сравните текущие предложения в AnyTour.',
        'intro' => 'Review-страница FORTUNA MARMARIS сохраняет проверенную связь с конкретным отелем, но не фиксирует меняющиеся условия. Все параметры поездки берутся из актуальной выдачи.',
        'sections' => [
            ['id'=>'start','title'=>'Начните с параметров поездки','paragraphs'=>['Задайте даты, длительность, город вылета и состав туристов.','Для более широкого выбора можно проверить соседние даты.']],
            ['id'=>'room','title'=>'Проверьте вариант размещения','paragraphs'=>['Номера и питание зависят от конкретного найденного пакета.','Сверяйте условия каждого предложения отдельно.']],
            ['id'=>'bundle','title'=>'Оцените весь турпакет','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги могут различаться.','Сравнение полного состава помогает избежать неверного вывода только по стартовой цене.']],
            ['id'=>'current','title'=>'Используйте текущую выдачу','paragraphs'=>['Цена и доступность меняются во времени.','Перед отправкой заявки перепроверьте выбранный вариант в AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 42576 and catalog slug fortuna-marmaris-42576 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
