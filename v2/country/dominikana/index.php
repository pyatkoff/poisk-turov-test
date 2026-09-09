<?php
require_once dirname(dirname(__DIR__)) . '/country-page-v1.php';
require_once dirname(dirname(__DIR__)) . '/seo-content-pilot-dominican-v1.php';

$editorial = v2_seo_content_pilot_dominican();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'dominikana',
    'name' => 'Доминикану',
    'h1' => $data['h1'] ?? 'Туры в Доминикану',
    'title' => $data['title'] ?? 'Туры в Доминикану — AnyTour',
    'description' => $data['description'] ?? 'Туры в Доминикану: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните курортные зоны и отели Доминиканы перед выбором конкретного тура.',
    'resorts' => ['Пунта-Кана', 'Ла-Романа', 'Пуэрто-Плата', 'Бока-Чика'],
    'facts' => [
        ['title' => 'Курортная зона', 'text' => 'Пляжи, атмосфера и набор отелей различаются по районам — сначала определите подходящий сценарий отдыха.'],
        ['title' => 'Формат отеля', 'text' => 'Смотрите территорию, питание, пляж, категорию номера и расположение, а не только формальную звёздность.'],
        ['title' => 'Проверка перед заявкой', 'text' => 'Перед передачей менеджеру откройте конкретный тур и проверьте актуальные даты, перелёт, размещение и итоговую стоимость.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
]);
