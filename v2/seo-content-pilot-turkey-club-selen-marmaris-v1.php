<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_club_selen_marmaris(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 9332,
        'hotel_slug' => 'club-selen-marmaris-ex-selen-hotel-9332',
        'hotel_name' => 'CLUB SELEN MARMARIS (EX. SELEN HOTEL)',
    ], [
        'title' => 'Туры в CLUB SELEN MARMARIS — актуальный подбор | AnyTour',
        'description' => 'Найдите пакетный тур в CLUB SELEN MARMARIS: укажите параметры поездки и сравните текущие предложения по размещению и составу пакета в AnyTour.',
        'intro' => 'Для CLUB SELEN MARMARIS эта страница служит стабильной точкой входа в поиск конкретного отеля. Меняющиеся условия не закрепляются в тексте и проверяются в найденном туре.',
        'sections' => [
            ['id'=>'parameters','title'=>'Настройте параметры поездки','paragraphs'=>['Укажите город вылета, даты, длительность и состав туристов.','При изменении любого из этих параметров доступные пакетные варианты могут заметно отличаться.']],
            ['id'=>'room','title'=>'Выберите подходящее размещение','paragraphs'=>['Состав доступных номеров и питания определяется текущей выдачей.','Перед заявкой внимательно сверяйте выбранную комбинацию размещения и питания.']],
            ['id'=>'transport','title'=>'Сверьте транспортную часть','paragraphs'=>['Перелёт, багаж и трансфер относятся к конкретному пакетному предложению.','Для сравнения нескольких вариантов учитывайте все включённые услуги.']],
            ['id'=>'confirm','title'=>'Подтвердите текущие условия','paragraphs'=>['Условия предложения могут обновляться после первоначального поиска.','Финальную цену и доступность проверяйте непосредственно перед отправкой заявки.']],
        ],
        'content_notes' => ['Hotel ID 9332 and catalog slug club-selen-marmaris-ex-selen-hotel-9332 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
