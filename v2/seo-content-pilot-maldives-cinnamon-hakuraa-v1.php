<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_maldives_cinnamon_hakuraa(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 8,
        'country_slug' => 'maldives',
        'country_name' => 'Мальдивы',
        'hotel_id' => 2429,
        'hotel_slug' => 'cinnamon-hakuraa-huraa-maldives-ex-chaaya-lagoon-hakuraahuraa-2429',
        'hotel_name' => 'Cinnamon Hakuraa Huraa Maldives (Ex. Chaaya Lagoon HakuraaHuraa)',
    ], [
        'title' => 'Туры в Cinnamon Hakuraa Huraa Maldives — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в Cinnamon Hakuraa Huraa Maldives: сравните даты, продолжительность, питание и состав предложения, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Страница помогает сравнивать пакетные туры в Cinnamon Hakuraa Huraa Maldives. Оценивайте конкретные варианты по городу вылета, датам, продолжительности, питанию и полной стоимости выбранного пакета.',
        'sections' => [
            ['id'=>'compare','title'=>'Как сравнивать туры в Cinnamon Hakuraa Huraa Maldives','paragraphs'=>['Используйте одинаковый город вылета, близкие даты и сопоставимую продолжительность, чтобы сравнение предложений было корректным.','Перед заявкой перепроверьте актуальную стоимость и доступность выбранного тура.']],
            ['id'=>'stay','title'=>'Размещение и питание','paragraphs'=>['Категория размещения и питание относятся к конкретному предложению и могут различаться между пакетами.','Проверьте выбранные параметры в карточке тура перед бронированием.']],
            ['id'=>'package','title'=>'Состав турпакета','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги зависят от конкретного пакета.','Окончательные условия нужно сверять непосредственно перед передачей заявки.']],
            ['id'=>'search','title'=>'Переход к актуальному поиску','paragraphs'=>['Откройте поиск с выбранным отелем, задайте город вылета, даты и состав туристов и сравните найденные варианты.','Если выбор ограничен, расширьте диапазон дат или проверьте другой доступный город вылета.']],
        ],
        'content_notes' => ['Hotel ID 2429 and catalog slug were verified by production hotel snapshot evidence observed on 2026-09-01 and inspected on 2026-09-02.'],
    ]);
}
