<?php
require_once dirname(dirname(__DIR__)).'/country-page-v1.php';
require_once dirname(dirname(__DIR__)).'/seo-content-pilot-russia-v1.php';

$editorial = v2_seo_content_pilot_russia();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'russia',
    'name' => 'Россию',
    'h1' => $data['h1'] ?? 'Туры по России',
    'title' => $data['title'] ?? 'Туры по России — AnyTour',
    'description' => $data['description'] ?? 'Туры по России: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните направления и отели по России перед выбором конкретного тура.',
    'countryId' => 47,
    'resorts' => ['Сочи', 'Адлер', 'Красная Поляна'],
    'facts' => [
        ['title' => 'Выберите формат отдыха', 'text' => 'Пляжный отдых, городская поездка или горный курорт требуют разных районов и типов размещения.'],
        ['title' => 'Сравнивайте даты и отели', 'text' => 'Даже внутри одного направления стоимость и доступность заметно меняются по датам и конкретным объектам размещения.'],
        ['title' => 'Проверяйте состав тура', 'text' => 'Перед заявкой откройте конкретный вариант и убедитесь, какие услуги и транспорт входят именно в него.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
]);
