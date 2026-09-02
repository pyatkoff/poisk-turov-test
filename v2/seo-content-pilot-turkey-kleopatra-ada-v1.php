<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_kleopatra_ada(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 1299,
        'hotel_slug' => 'kleopatra-ada-hotel-1299',
        'hotel_name' => 'KLEOPATRA ADA HOTEL',
    ], [
        'title' => 'Туры в KLEOPATRA ADA HOTEL — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в KLEOPATRA ADA HOTEL: сравните даты, длительность и состав конкретных предложений, а актуальные условия проверьте в поиске AnyTour.',
        'intro' => 'Страница помогает перейти к актуальным пакетным турам в KLEOPATRA ADA HOTEL без закрепления меняющихся коммерческих условий. Сравнивайте параметры конкретных найденных предложений.',
        'sections' => [
            ['id'=>'search','title'=>'Настройка поиска KLEOPATRA ADA HOTEL','paragraphs'=>['Выберите город вылета, диапазон дат и состав туристов, чтобы поиск учитывал ваши исходные условия.','При гибком графике сравните несколько соседних дат и вариантов продолжительности.']],
            ['id'=>'room','title'=>'Номер и питание','paragraphs'=>['Категория номера и тип питания относятся к конкретному предложению и могут отличаться.','Проверяйте эти параметры непосредственно в выбранном туре.']],
            ['id'=>'transport','title'=>'Перелёт и услуги','paragraphs'=>['Рейс, багаж, трансфер и дополнительные услуги зависят от конкретного пакетного предложения.','Не переносите условия одного пакета на другие варианты того же отеля.']],
            ['id'=>'final','title'=>'Финальная проверка','paragraphs'=>['Перед отправкой заявки повторно сверяйте цену, даты и наличие выбранного тура.','Если предложение изменилось, используйте свежую выдачу и сравните доступные альтернативы.']],
        ],
        'content_notes' => ['Hotel ID 1299 and catalog slug kleopatra-ada-hotel-1299 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
