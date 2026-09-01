<?php
require_once dirname(dirname(__DIR__)) . '/country-page-v1.php';
require_once dirname(dirname(__DIR__)) . '/seo-content-pilot-maldives-v1.php';

$editorial = v2_seo_content_pilot_maldives();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'maldives',
    'name' => 'Мальдивы',
    'h1' => $data['h1'] ?? 'Туры на Мальдивы',
    'title' => $data['title'] ?? 'Туры на Мальдивы — AnyTour',
    'description' => $data['description'] ?? 'Туры на Мальдивы: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните острова, отели и состав турпакета перед выбором поездки на Мальдивы.',
    'countryId' => 8,
    'resorts' => ['Северный Мале атолл', 'Южный Мале атолл', 'Ари атолл', 'Баа атолл', 'Раа атолл'],
    'facts' => [
        ['title' => 'Остров и формат отдыха', 'text' => 'Сравнивайте пляж, риф, размер острова и инфраструктуру конкретного отеля.'],
        ['title' => 'Питание и номер', 'text' => 'Питание и категория размещения заметно влияют и на формат поездки, и на итоговую стоимость.'],
        ['title' => 'Трансфер и пакет', 'text' => 'Перед заявкой проверяйте доступные сведения о перелёте, трансфере, размещении и итоговой стоимости.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
    'hotelTourLinks' => [
        ['label' => 'Туры в Kurumba Maldives', 'href' => '/country/maldives/hotel/kurumba-maldives-2461/'],
    ],
]);
