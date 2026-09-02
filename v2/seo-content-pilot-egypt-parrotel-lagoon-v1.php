<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_parrotel_lagoon(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>71147,
        'hotel_slug'=>'parrotel-lagoon-waterpark-resort-71147','hotel_name'=>'PARROTEL LAGOON WATERPARK RESORT',
    ], [
        'title'=>'Туры в PARROTEL LAGOON WATERPARK RESORT — поиск тура | AnyTour',
        'description'=>'Найдите пакетные туры в PARROTEL LAGOON WATERPARK RESORT: выберите вылет и даты, сравните свежие предложения и перепроверьте условия перед заявкой.',
        'intro'=>'Эта страница связывает подтверждённый hotel ID PARROTEL LAGOON WATERPARK RESORT с актуальным поиском AnyTour. Меняющиеся параметры тура проверяются только в найденном предложении.',
        'sections'=>[
            ['id'=>'start','title'=>'Настройте поиск','paragraphs'=>['Задайте город вылета, даты и состав туристов, сохранив выбранный отель.','При небольшом числе вариантов расширьте даты или длительность.']],
            ['id'=>'room','title'=>'Сравните условия проживания','paragraphs'=>['Тип номера и питание могут различаться между доступными пакетами.','Проверяйте эти условия отдельно для каждого тура.']],
            ['id'=>'included','title'=>'Уточните услуги в пакете','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от конкретного предложения.','Сопоставляйте предложения по полному составу.']],
            ['id'=>'final','title'=>'Финальная проверка','paragraphs'=>['Доступность и цена меняются по мере обновления данных.','Перед заявкой подтвердите актуальные параметры в AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 71147 and catalog slug parrotel-lagoon-waterpark-resort-71147 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
