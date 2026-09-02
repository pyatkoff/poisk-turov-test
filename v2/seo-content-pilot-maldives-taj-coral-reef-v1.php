<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_taj_coral_reef(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 2439,
        'hotel_slug' => 'taj-coral-reef-resort-spa-maldives-2439',
        'hotel_name' => 'Taj Coral Reef Resort & Spa Maldives',
    ], [
        'title' => 'Туры в Taj Coral Reef Resort & Spa Maldives — цены и подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Taj Coral Reef Resort & Spa Maldives: сравните даты, продолжительность, питание и состав пакета, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Страница предназначена для сравнения пакетных туров именно в Taj Coral Reef Resort & Spa Maldives. Оценивайте предложения по городу вылета, датам, числу ночей, питанию и полной стоимости выбранного пакета.',
        'sections' => [
            ['id'=>'compare','title'=>'Как сравнивать туры в Taj Coral Reef Resort & Spa Maldives','paragraphs'=>['Сопоставляйте предложения с одинаковым городом вылета, близкими датами и продолжительностью поездки.','Актуальную стоимость и наличие мест перепроверяйте перед заявкой.']],
            ['id'=>'meal','title'=>'Питание и размещение в предложении','paragraphs'=>['Тип питания и вариант размещения относятся к конкретному туру и могут отличаться между пакетами.','Проверьте выбранные параметры в карточке тура перед бронированием.']],
            ['id'=>'package','title'=>'Что входит в турпакет','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от конкретного предложения.','Перед заявкой сверяйте полный состав выбранного пакета.']],
            ['id'=>'search','title'=>'Как найти тур в Taj Coral Reef Resort & Spa Maldives','paragraphs'=>['Откройте поиск с выбранным отелем и задайте город вылета, даты и состав туристов.','Если вариантов мало, расширьте диапазон дат или проверьте другой город вылета.']],
        ],
        'content_notes' => ['Hotel ID 2439 and catalog slug were verified by fresh production hotel snapshot evidence on 2026-09-01.'],
    ]);
}
