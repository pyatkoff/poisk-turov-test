<?php
require_once dirname(dirname(__DIR__)) . '/country-page-v1.php';
require_once dirname(dirname(__DIR__)) . '/seo-content-pilot-vietnam-v1.php';

$editorial = v2_seo_content_pilot_vietnam();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'vetnam',
    'name' => 'Вьетнам',
    'h1' => $data['h1'] ?? 'Туры во Вьетнам',
    'title' => $data['title'] ?? 'Туры во Вьетнам — AnyTour',
    'description' => $data['description'] ?? 'Туры во Вьетнам: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните курорты и отели Вьетнама перед выбором конкретного тура.',
    'resorts' => ['Нячанг', 'Фукуок', 'Фантьет', 'Муйне'],
    'facts' => [
        ['title' => 'Курорт под даты', 'text' => 'Климат и формат отдыха различаются по регионам, поэтому направление лучше выбирать вместе с конкретным периодом поездки.'],
        ['title' => 'Расположение отеля', 'text' => 'Смотрите район, расстояние до пляжа, питание и инфраструктуру рядом — одинаковая категория может давать очень разный опыт.'],
        ['title' => 'Проверка перед заявкой', 'text' => 'Перед передачей менеджеру откройте конкретный тур и проверьте актуальные даты, перелёт, размещение и итоговую стоимость.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
]);
