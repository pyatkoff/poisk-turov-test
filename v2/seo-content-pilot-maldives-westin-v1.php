<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_westin(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 65108,
        'hotel_slug' => 'the-westin-maldives-miriandhoo-resort-65108',
        'hotel_name' => 'The Westin Maldives Miriandhoo Resort',
    ], [
        'title' => 'Туры в The Westin Maldives Miriandhoo Resort — цены и подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в The Westin Maldives Miriandhoo Resort: сравните даты, продолжительность, питание и состав предложения, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Эта страница собрана для подбора пакетных туров именно в The Westin Maldives Miriandhoo Resort. Сравнивайте предложения по городу вылета, датам, продолжительности, питанию и итоговой стоимости пакета.',
        'sections' => [
            ['id'=>'compare','title'=>'Как сравнивать туры в The Westin Maldives Miriandhoo Resort','paragraphs'=>['Стоимость поездки зависит от конкретных дат, города вылета, продолжительности и состава турпакета. Поэтому корректнее сравнивать предложения с одинаковыми исходными параметрами.','Цена и доступность меняются, поэтому перед заявкой перепроверьте выбранный вариант в поиске AnyTour.']],
            ['id'=>'stay','title'=>'Размещение и питание в выбранном туре','paragraphs'=>['Вариант размещения и тип питания относятся к конкретному предложению и могут различаться между доступными пакетами.','Перед бронированием проверьте эти параметры в карточке выбранного тура.']],
            ['id'=>'package','title'=>'Проверка состава турпакета','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от конкретного предложения. Страница не подменяет состав тура постоянными характеристиками.','Окончательные условия нужно проверять непосредственно в выбранном пакете перед передачей заявки.']],
            ['id'=>'search','title'=>'Как подобрать тур в The Westin Maldives Miriandhoo Resort','paragraphs'=>['Перейдите в поиск с уже выбранным отелем, задайте город вылета, даты и состав туристов и сравните найденные варианты.','При небольшом количестве предложений расширьте диапазон дат или проверьте другой город вылета.']],
        ],
        'content_notes' => ['Hotel ID 65108 and catalog slug were verified by fresh production hotel snapshot evidence on 2026-09-01.'],
    ]);
}
