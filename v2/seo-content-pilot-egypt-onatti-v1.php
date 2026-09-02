<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_onatti(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>1974,
        'hotel_slug'=>'onatti-beach-resort-ex-otium-senses-onatti-1974','hotel_name'=>'ONATTI BEACH RESORT (EX. OTIUM SENSES ONATTI)',
    ], [
        'title'=>'Туры в ONATTI BEACH RESORT — поиск пакетного тура | AnyTour',
        'description'=>'Найдите пакетный тур в ONATTI BEACH RESORT: выберите город вылета и даты, сравните свежие предложения и перепроверьте параметры перед заявкой.',
        'intro'=>'Эта страница использует только проверенную идентичность ONATTI BEACH RESORT для актуального поиска AnyTour. Коммерческие условия подтверждаются в конкретном найденном пакете.',
        'sections'=>[
            ['id'=>'start','title'=>'Сформируйте запрос','paragraphs'=>['Задайте город вылета, даты и состав туристов, сохранив выбранный отель.','Для более широкой выдачи увеличьте диапазон дат или длительность.']],
            ['id'=>'options','title'=>'Сверьте размещение','paragraphs'=>['Питание и тип номера могут различаться между пакетами.','Смотрите эти условия в карточке каждого предложения.']],
            ['id'=>'included','title'=>'Проверьте включённые услуги','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от выбранного тура.','Сопоставляйте предложения по полному составу.']],
            ['id'=>'verify','title'=>'Подтвердите актуальность','paragraphs'=>['Доступность и стоимость обновляются вместе с данными поставщиков.','Перед заявкой повторно проверьте выбранный пакет в AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 1974 and catalog slug onatti-beach-resort-ex-otium-senses-onatti-1974 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
