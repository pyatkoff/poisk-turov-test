<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_dedeman_kemer(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 28476,
        'hotel_slug' => 'dedeman-kemer-resort-ex-club-jovia-kemer-28476',
        'hotel_name' => 'DEDEMAN KEMER RESORT (EX. CLUB JOVIA KEMER)',
    ], [
        'title' => 'Туры в DEDEMAN KEMER RESORT — подбор пакетного тура | AnyTour',
        'description' => 'Подберите пакетный тур в DEDEMAN KEMER RESORT: сравните даты, продолжительность и условия конкретных предложений, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Эта review-страница ведёт к актуальным пакетным турам в DEDEMAN KEMER RESORT (EX. CLUB JOVIA KEMER). Она не фиксирует меняющиеся коммерческие условия: сравнивайте конкретные предложения в поиске AnyTour.',
        'sections' => [
            ['id'=>'dates','title'=>'Сначала сравните даты и длительность','paragraphs'=>['Итоговый выбор удобнее начинать с одинакового города вылета, диапазона дат и продолжительности поездки. Так различия между найденными пакетами становятся понятнее.','Если подходящих вариантов мало, расширение диапазона дат часто полезнее, чем сравнение несопоставимых предложений.']],
            ['id'=>'room-meal','title'=>'Проверьте номер и питание','paragraphs'=>['Категория номера и тип питания относятся к конкретному туру и могут отличаться даже для одного отеля.','Перед заявкой сверяйте эти параметры в карточке выбранного предложения.']],
            ['id'=>'flight','title'=>'Сверьте перелёт и состав пакета','paragraphs'=>['Рейс, багаж, трансфер и другие включённые услуги зависят от конкретного турпакета.','Окончательные условия нужно проверять непосредственно перед передачей заявки.']],
            ['id'=>'search','title'=>'Перейдите к актуальным предложениям','paragraphs'=>['Поиск откроется с выбранным отелем; останется задать город вылета, даты и состав туристов.','Цена и наличие не зашиваются в эту страницу и берутся только из актуального поиска.']],
        ],
        'content_notes' => ['Hotel ID 28476 and catalog slug dedeman-kemer-resort-ex-club-jovia-kemer-28476 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
