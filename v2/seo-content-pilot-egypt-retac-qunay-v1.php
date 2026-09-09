<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_retac_qunay(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>295,
        'hotel_slug'=>'retac-qunay-resort-spa-ex-le-meridien-dahab-resort-295','hotel_name'=>'RETAC QUNAY RESORT & SPA (EX. LE MERIDIEN DAHAB RESORT)',
    ], [
        'title'=>'Туры в RETAC QUNAY RESORT & SPA — поиск пакетного тура | AnyTour',
        'description'=>'Найдите пакетный тур в RETAC QUNAY RESORT & SPA: задайте вылет и даты, сравните актуальные предложения и перепроверьте параметры перед заявкой.',
        'intro'=>'Эта страница связывает проверенный hotel ID RETAC QUNAY RESORT & SPA с текущим поиском AnyTour. Меняющиеся коммерческие условия подтверждаются только в конкретном туре.',
        'sections'=>[
            ['id'=>'request','title'=>'Сформируйте запрос','paragraphs'=>['Выберите город вылета, даты и состав туристов.','Если вариантов мало, расширьте даты или длительность.']],
            ['id'=>'options','title'=>'Сравните проживание','paragraphs'=>['Питание и тип номера могут отличаться между предложениями.','Проверяйте эти параметры в каждой карточке тура.']],
            ['id'=>'included','title'=>'Уточните состав предложения','paragraphs'=>['Перелёт, багаж, трансфер и другие услуги зависят от пакета.','Сравнивайте полный состав туров.']],
            ['id'=>'fresh','title'=>'Используйте свежие данные','paragraphs'=>['Наличие и стоимость меняются по мере обновления данных.','Перед заявкой подтвердите выбранный вариант в AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 295 and catalog slug retac-qunay-resort-spa-ex-le-meridien-dahab-resort-295 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
