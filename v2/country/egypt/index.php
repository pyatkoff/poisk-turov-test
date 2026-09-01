<?php
require_once dirname(dirname(__DIR__)) . '/country-page-v1.php';
require_once dirname(dirname(__DIR__)) . '/seo-content-pilot-egypt-v1.php';

$editorial = v2_seo_content_pilot_egypt();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'egypt',
    'name' => 'Египет',
    'h1' => $data['h1'] ?? 'Туры в Египет',
    'title' => $data['title'] ?? 'Туры в Египет — AnyTour',
    'description' => $data['description'] ?? 'Туры в Египет: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните курорты и отели Египта перед выбором конкретного тура.',
    'countryId' => 1,
    'resorts' => ['Хургада', 'Шарм-эль-Шейх', 'Марса-Алам'],
    'facts' => [
        ['title' => 'Выберите курорт', 'text' => 'Хургада, Шарм-эль-Шейх и Марса-Алам отличаются логистикой и форматом отдыха — сравнивайте варианты под свои даты.'],
        ['title' => 'Смотрите конкретный отель', 'text' => 'Рейтинг, тип питания, номер, пляж и расположение часто важнее одной только категории в звёздах.'],
        ['title' => 'Проверьте перелёт', 'text' => 'Перед заявкой откройте конкретный тур и проверьте доступные сведения о рейсе, багаже и итоговой стоимости.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
]);
