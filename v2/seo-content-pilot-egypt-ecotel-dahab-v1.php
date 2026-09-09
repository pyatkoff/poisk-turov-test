<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_ecotel_dahab(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>322,
        'hotel_slug'=>'ecotel-dahab-bay-view-resort-322','hotel_name'=>'ECOTEL DAHAB BAY VIEW RESORT',
    ], [
        'title'=>'Туры в ECOTEL DAHAB BAY VIEW RESORT — подбор тура | AnyTour',
        'description'=>'Подберите пакетный тур в ECOTEL DAHAB BAY VIEW RESORT: задайте вылет и даты, сравните актуальные предложения и перепроверьте выбранный пакет перед заявкой.',
        'intro'=>'Страница связывает подтверждённый hotel ID ECOTEL DAHAB BAY VIEW RESORT с текущим поиском AnyTour. Цена, наличие, питание и вариант размещения определяются только конкретным предложением.',
        'sections'=>[
            ['id'=>'search','title'=>'Настройте поиск','paragraphs'=>['Укажите город вылета, даты и состав туристов, сохранив выбранный отель.','При малой выдаче расширьте даты или продолжительность поездки.']],
            ['id'=>'stay','title'=>'Сверьте размещение','paragraphs'=>['Тип номера и питание могут различаться между пакетами одного отеля.','Проверяйте эти параметры в карточке каждого тура.']],
            ['id'=>'package','title'=>'Сравните состав пакета','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от конкретного предложения.','Сопоставляйте полные условия, а не только стоимость.']],
            ['id'=>'fresh','title'=>'Подтвердите актуальность','paragraphs'=>['Коммерческие условия меняются после обновления данных.','Перед заявкой повторно проверьте выбранный вариант в AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 322 and catalog slug ecotel-dahab-bay-view-resort-322 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
