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
        'description' => 'Подберите пакетный тур в UNI SHARM AQUA PARK: задайте вылет, даты и состав туристов, сравните текущие предложения и проверьте выбранный пакет перед заявкой.',
        'intro' => 'Страница связывает проверенную идентичность UNI SHARM AQUA PARK с актуальной выдачей туров. Цена, доступность, питание и категория размещения относятся к конкретному предложению и не фиксируются здесь.',
        'sections' => [
            ['id'=>'params','title'=>'Задайте исходные параметры','paragraphs'=>['Укажите город вылета, диапазон дат и состав туристов, чтобы сравнивать варианты на одинаковой основе.','Если предложений мало, сначала расширьте даты или длительность поездки.']],
            ['id'=>'stay','title'=>'Проверьте вариант проживания','paragraphs'=>['Пакеты одного отеля могут отличаться номером и питанием.','Перед выбором сверяйте эти параметры в карточке конкретного тура.']],
            ['id'=>'package','title'=>'Сравните состав предложения','paragraphs'=>['Условия перелёта, багажа, трансфера и других услуг зависят от турпакета.','Оценивайте подходящие варианты после проверки всего состава поездки.']],
            ['id'=>'final','title'=>'Подтвердите актуальные условия','paragraphs'=>['Наличие и стоимость меняются по мере обновления данных туроператоров.','Перед заявкой повторно проверьте выбранный вариант в свежем поиске AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 516 and catalog slug uni-sharm-aqua-park-ex-karma-eastotels-516 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
