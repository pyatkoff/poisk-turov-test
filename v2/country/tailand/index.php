<?php
require_once dirname(dirname(__DIR__)) . '/country-page-v1.php';
require_once dirname(dirname(__DIR__)) . '/seo-content-pilot-thailand-v1.php';

$editorial = v2_seo_content_pilot_thailand();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'tailand',
    'name' => 'Таиланд',
    'h1' => $data['h1'] ?? 'Туры в Таиланд',
    'title' => $data['title'] ?? 'Туры в Таиланд — AnyTour',
    'description' => $data['description'] ?? 'Туры в Таиланд: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните курорты и отели Таиланда перед выбором конкретного тура.',
    'countryId' => 2,
    'resorts' => ['Пхукет', 'Паттайя', 'Као-Лак'],
    'facts' => [
        ['title' => 'Курорт под сценарий', 'text' => 'Пхукет, Паттайя и Као-Лак отличаются пляжами, логистикой и форматом отдыха — сравнивайте направление вместе с конкретным отелем.'],
        ['title' => 'Район и пляж', 'text' => 'Для Таиланда расположение отеля и характер ближайшего пляжа часто важнее формальной категории размещения.'],
        ['title' => 'Перелёт и состав тура', 'text' => 'Перед заявкой откройте конкретный вариант и проверьте доступные сведения о рейсе, багаже, размещении и итоговой стоимости.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
]);
