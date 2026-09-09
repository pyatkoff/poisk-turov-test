<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_parrotel_aqua(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>352,
        'hotel_slug'=>'parrotel-aqua-park-resort-ex-park-inn-by-radisson-352','hotel_name'=>'PARROTEL AQUA PARK RESORT (EX. PARK INN BY RADISSON)',
    ], [
        'title'=>'Туры в PARROTEL AQUA PARK RESORT — актуальный подбор тура | AnyTour',
        'description'=>'Подберите пакетный тур в PARROTEL AQUA PARK RESORT: задайте параметры поездки, сравните актуальную выдачу и подтвердите условия выбранного предложения.',
        'intro'=>'Страница использует проверенный hotel ID PARROTEL AQUA PARK RESORT и передаёт его в поиск AnyTour. Цена, наличие, питание и категория номера относятся только к конкретному туру.',
        'sections'=>[
            ['id'=>'setup','title'=>'Укажите параметры поездки','paragraphs'=>['Выберите город вылета, даты, продолжительность и состав туристов.','При узкой выдаче расширьте даты, сохранив фильтр выбранного отеля.']],
            ['id'=>'stay','title'=>'Проверьте размещение','paragraphs'=>['Категория номера и питание могут различаться между пакетами.','Сверяйте эти параметры непосредственно в найденном туре.']],
            ['id'=>'bundle','title'=>'Сравните пакет целиком','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги определяются конкретным предложением.','Не делайте выбор только по первой видимой стоимости.']],
            ['id'=>'verify','title'=>'Перепроверьте перед заявкой','paragraphs'=>['Коммерческие условия меняются после обновлений.','Финальные параметры подтвердите в свежей выдаче AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 352 and catalog slug parrotel-aqua-park-resort-ex-park-inn-by-radisson-352 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
