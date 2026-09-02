<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_uni_sharm(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 516,
        'hotel_slug' => 'uni-sharm-aqua-park-ex-karma-eastotels-516',
        'hotel_name' => 'UNI SHARM AQUA PARK (EX. KARMA EASTOTELS)',
    ], [
        'title' => 'Туры в UNI SHARM AQUA PARK — подбор пакетного тура | AnyTour',
        'description' => 'Подберите пакетный тур в UNI SHARM AQUA PARK: задайте параметры поездки, сравните свежие предложения и перепроверьте условия выбранного варианта в AnyTour.',
        'intro' => 'Страница связывает проверенный hotel ID UNI SHARM AQUA PARK с актуальной выдачей туров. Цена, доступность, питание и категория размещения не фиксируются здесь и относятся только к конкретному пакету.',
        'sections' => [
            ['id'=>'query','title'=>'Сформируйте параметры поиска','paragraphs'=>['Укажите город вылета, даты, продолжительность и состав туристов, сохранив выбранный отель.','Если предложений немного, сначала расширьте диапазон дат или длительность поездки.']],
            ['id'=>'room','title'=>'Сравните размещение и питание','paragraphs'=>['Предложения в одном отеле могут различаться типом номера и вариантом питания.','Проверяйте эти параметры непосредственно в карточке каждого тура.']],
            ['id'=>'package','title'=>'Сверьте состав турпакета','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от конкретного предложения.','Сопоставляйте полные условия пакетов перед выбором.']],
            ['id'=>'current','title'=>'Проверьте актуальные условия','paragraphs'=>['Наличие и стоимость меняются при обновлении данных туроператоров.','Перед заявкой повторно откройте выбранный вариант в свежей выдаче AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 516 and catalog slug uni-sharm-aqua-park-ex-karma-eastotels-516 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
