<?php
require_once dirname(dirname(__DIR__)) . '/country-page-v1.php';
require_once dirname(dirname(__DIR__)) . '/seo-content-pilot-turkey-v1.php';

$editorial = v2_seo_content_pilot_turkey();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'turkey',
    'name' => 'Турцию',
    'h1' => $data['h1'] ?? 'Туры в Турцию',
    'title' => $data['title'] ?? 'Туры в Турцию — AnyTour',
    'description' => $data['description'] ?? 'Туры в Турцию: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните курорты и отели Турции перед выбором конкретного тура.',
    'countryId' => 4,
    'resorts' => ['Анталья', 'Аланья', 'Кемер', 'Сиде', 'Белек'],
    'facts' => [
        ['title' => 'Курорт под задачу', 'text' => 'Для спокойного, семейного или более активного отдыха подходят разные районы — удобнее сравнивать их вместе с отелями и датами.'],
        ['title' => 'Питание и отель', 'text' => 'В Турции особенно важно смотреть не только звёзды, но и формат питания, рейтинг отеля, расстояние до моря и конкретный тип номера.'],
        ['title' => 'Перелёт', 'text' => 'Перед заявкой откройте конкретный вариант тура: поиск покажет доступные данные по рейсу и багажу, когда они есть у туроператора.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
]);
