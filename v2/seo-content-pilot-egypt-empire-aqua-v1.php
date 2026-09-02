<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_egypt_empire_aqua(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 1,
        'country_slug' => 'egypt',
        'country_name' => 'Египет',
        'hotel_id' => 502,
        'hotel_slug' => 'empire-hotel-aqua-park-ex-the-three-corners-triton-empire-502',
        'hotel_name' => 'EMPIRE HOTEL AQUA PARK (EX. THE THREE CORNERS TRITON EMPIRE)',
    ], [
        'title' => 'Туры в EMPIRE HOTEL AQUA PARK — подбор тура | AnyTour',
        'description' => 'Найдите пакетный тур в EMPIRE HOTEL AQUA PARK: сравните доступные даты, длительность и условия конкретных пакетов, а финальные параметры проверьте в актуальном поиске AnyTour.',
        'intro' => 'Здесь собран вход в подбор пакетных туров для EMPIRE HOTEL AQUA PARK. Постоянные рекламные обещания о цене, наличии или составе услуг не используются: итоговые условия зависят от выбранного предложения.',
        'sections' => [
            ['id'=>'params','title'=>'Параметры для начала поиска','paragraphs'=>['Укажите город вылета, даты и туристов, чтобы сузить выдачу до подходящих пакетов в выбранный отель.','После первого поиска сравните продолжительность поездки и условия вариантов с одинаковыми датами.']],
            ['id'=>'offer','title'=>'Чем могут отличаться предложения','paragraphs'=>['Даже для одного отеля пакетные туры могут различаться типом размещения, питанием и составом включённых услуг.','Сверяйте параметры непосредственно в карточке каждого найденного предложения.']],
            ['id'=>'flight','title'=>'Перелёт и дополнительные услуги','paragraphs'=>['Данные о перелёте, багаже, трансфере и других услугах относятся к конкретному пакету.','Не переносите условия одного найденного тура на другой вариант без отдельной проверки.']],
            ['id'=>'verify','title'=>'Проверка перед заявкой','paragraphs'=>['Коммерческие условия меняются, поэтому выбранный вариант нужно открыть заново перед заявкой.','Если предложение стало недоступно, вернитесь к свежей выдаче и сравните актуальные альтернативы.']],
        ],
        'content_notes' => ['Hotel ID 502 and catalog slug empire-hotel-aqua-park-ex-the-three-corners-triton-empire-502 were verified by fresh production identity snapshot evidence on 2026-09-02.'],
    ]);
}
