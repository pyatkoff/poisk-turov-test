<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_verginia(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 447,
        'hotel_slug' => 'verginia-sharm-resort-aqua-park-eh-verginia-sharm-sol-verginia-447',
        'hotel_name' => 'VERGINIA SHARM RESORT & AQUA PARK (ЕХ. VERGINIA SHARM, SOL VERGINIA)',
    ], [
        'title' => 'Туры в VERGINIA SHARM RESORT & AQUA PARK — подбор тура | AnyTour',
        'description' => 'Подберите пакетные туры в VERGINIA SHARM RESORT & AQUA PARK: задайте параметры поездки, сравните свежую выдачу и проверьте условия выбранного предложения в AnyTour.',
        'intro' => 'Страница использует только подтверждённую идентичность VERGINIA SHARM RESORT & AQUA PARK и передаёт её в поиск туров. Цена, наличие, питание и характеристики размещения не закрепляются в редакционном тексте.',
        'sections' => [
            ['id'=>'parameters','title'=>'Уточните исходные параметры','paragraphs'=>['Укажите вылет, даты, продолжительность и состав туристов перед сравнением доступных пакетов.','Если поиск слишком узкий, расширьте диапазон дат без смены выбранного отеля.']],
            ['id'=>'stay','title'=>'Проверьте условия размещения','paragraphs'=>['Тип номера и питание относятся к конкретному турпакету и могут различаться.','Смотрите эти параметры в актуальной карточке предложения.']],
            ['id'=>'flight','title'=>'Сопоставьте транспорт и услуги','paragraphs'=>['Перелёт, багаж, трансфер и дополнительные услуги зависят от выбранного варианта.','Сравнивайте предложения по полному набору условий.']],
            ['id'=>'final','title'=>'Подтвердите тур перед отправкой заявки','paragraphs'=>['Доступность и стоимость меняются по мере обновления данных.','Перед заявкой ещё раз проверьте выбранный пакет в AnyTour.']],
        ],
        'content_notes' => ['Hotel ID 447 and catalog slug verginia-sharm-resort-aqua-park-eh-verginia-sharm-sol-verginia-447 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
