<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_club_wasa(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 81900,
        'hotel_slug' => 'club-wasa-holiday-village-ex-larissa-holiday-beach-club-81900',
        'hotel_name' => 'CLUB WASA HOLIDAY VILLAGE (EX. LARISSA HOLIDAY BEACH CLUB)',
    ], [
        'title' => 'Туры в CLUB WASA HOLIDAY VILLAGE — подбор тура | AnyTour',
        'description' => 'Найдите пакетные туры в CLUB WASA HOLIDAY VILLAGE: сравните даты, длительность и состав предложений, а актуальную цену и наличие проверьте в поиске AnyTour.',
        'intro' => 'Эта review-страница помогает перейти к актуальным предложениям CLUB WASA HOLIDAY VILLAGE без закрепления цен и наличия. Выбирайте тур по параметрам конкретного найденного пакета.',
        'sections' => [
            ['id'=>'parameters','title'=>'Параметры поиска тура','paragraphs'=>['Укажите город вылета, даты и состав туристов, чтобы получить предложения именно под вашу поездку.','Если даты гибкие, проверьте соседние диапазоны и несколько вариантов продолжительности.']],
            ['id'=>'stay','title'=>'Размещение в конкретном предложении','paragraphs'=>['Категория номера и питание могут отличаться у разных пакетов одного отеля.','Сверяйте условия размещения в выбранном туре перед заявкой.']],
            ['id'=>'package','title'=>'Состав пакетного тура','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги зависят от оператора и конкретного предложения.','Сравнивайте полный состав пакета, если отдельные услуги для вас принципиальны.']],
            ['id'=>'availability','title'=>'Актуальность выбранного варианта','paragraphs'=>['Наличие и стоимость меняются, поэтому не полагайтесь на ранее открытый вариант без повторной проверки.','Перед отправкой заявки убедитесь, что выбранное предложение всё ещё доступно.']],
        ],
        'content_notes' => ['Hotel ID 81900 and catalog slug club-wasa-holiday-village-ex-larissa-holiday-beach-club-81900 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
