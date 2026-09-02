<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';
function v2_seo_content_pilot_egypt_parrotel_beach(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id'=>1,'country_slug'=>'egypt','country_name'=>'Египет','hotel_id'=>365,
        'hotel_slug'=>'parrotel-beach-resort-ex-radisson-blu-resort-365','hotel_name'=>'PARROTEL BEACH RESORT (EX. RADISSON BLU RESORT)',
    ], [
        'title'=>'Туры в PARROTEL BEACH RESORT — актуальный подбор тура | AnyTour',
        'description'=>'Подберите пакетный тур в PARROTEL BEACH RESORT: задайте параметры поездки, сравните текущие предложения и подтвердите условия выбранного варианта в AnyTour.',
        'intro'=>'Страница использует подтверждённый hotel ID PARROTEL BEACH RESORT для перехода к свежему поиску. Стоимость, наличие, питание и категория номера относятся к конкретному предложению.',
        'sections'=>[
            ['id'=>'setup','title'=>'Настройте поиск тура','paragraphs'=>['Укажите город вылета, даты и состав туристов, сохранив отель.','При узкой выдаче расширьте диапазон дат или длительность.']],
            ['id'=>'stay','title'=>'Сверьте условия проживания','paragraphs'=>['Категория номера и питание могут различаться между пакетами.','Смотрите их в карточке выбранного тура.']],
            ['id'=>'bundle','title'=>'Проверьте пакет целиком','paragraphs'=>['Перелёт, багаж, трансфер и прочие услуги зависят от конкретного предложения.','Сравнивайте полный набор условий.']],
            ['id'=>'final','title'=>'Перепроверьте перед заявкой','paragraphs'=>['Доступность и цена могут измениться после обновления данных.','Перед заявкой подтвердите выбранный вариант в свежей выдаче AnyTour.']],
        ],
        'content_notes'=>['Hotel ID 365 and catalog slug parrotel-beach-resort-ex-radisson-blu-resort-365 were verified by production identity snapshot evidence on 2026-09-02 (evidence_epoch=1788328866, freshness_seconds=28134).'],
    ]);
}
