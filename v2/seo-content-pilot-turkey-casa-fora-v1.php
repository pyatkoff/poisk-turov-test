<?php
require_once __DIR__ . '/seo-hotel-tours-content-factory-v1.php';

function v2_seo_content_pilot_turkey_casa_fora(): array
{
    return v2_seo_hotel_tours_content_record([
        'country_id' => 4,
        'country_slug' => 'turkey',
        'country_name' => 'Турция',
        'hotel_id' => 90704,
        'hotel_slug' => 'casa-fora-beach-resort-by-miramor-90704',
        'hotel_name' => 'CASA FORA BEACH RESORT BY MIRAMOR',
    ], [
        'title' => 'Туры в CASA FORA BEACH RESORT BY MIRAMOR — подбор тура | AnyTour',
        'description' => 'Подберите пакетный тур в CASA FORA BEACH RESORT BY MIRAMOR: сравните даты, длительность и условия пакетов, а актуальную цену и доступность проверьте в поиске AnyTour.',
        'intro' => 'Страница предназначена для подбора пакетных туров в CASA FORA BEACH RESORT BY MIRAMOR. Она сохраняет только стабильную идентичность отеля, а меняющиеся условия предлагается проверять в актуальном поиске.',
        'sections' => [
            ['id'=>'compare','title'=>'Сравнивайте пакеты на одинаковых условиях','paragraphs'=>['Начните с одинаковых дат, продолжительности и состава туристов, чтобы сравнение предложений было содержательным.','При изменении исходных параметров итоговая стоимость и набор доступных вариантов могут измениться.']],
            ['id'=>'specifics','title'=>'Уточняйте условия выбранного тура','paragraphs'=>['Тип размещения и питание относятся к конкретному предложению и не закрепляются за этой страницей.','Перед заявкой проверьте все выбранные параметры в карточке тура.']],
            ['id'=>'components','title'=>'Проверьте компоненты поездки','paragraphs'=>['Условия перелёта, багажа, трансфера и других услуг следует смотреть в составе конкретного пакета.','Финальные условия должны совпадать с тем вариантом, который передаётся в заявку.']],
            ['id'=>'search','title'=>'Получите актуальные варианты','paragraphs'=>['Поиск откроется с выбранным отелем; задайте город вылета, даты и состав туристов.','Актуальные цены и наличие берутся из поиска и не дублируются постоянным SEO-текстом.']],
        ],
        'content_notes' => ['Hotel ID 90704 and catalog slug casa-fora-beach-resort-by-miramor-90704 were verified by fresh production hotel snapshot evidence on 2026-09-02.'],
    ]);
}
