<?php
require_once dirname(dirname(__DIR__)) . '/country-page-v1.php';
require_once dirname(dirname(__DIR__)) . '/seo-content-pilot-uae-v1.php';

$editorial = v2_seo_content_pilot_uae();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'oae',
    'name' => 'ОАЭ',
    'h1' => $data['h1'] ?? 'Туры в ОАЭ',
    'title' => $data['title'] ?? 'Туры в ОАЭ — AnyTour',
    'description' => $data['description'] ?? 'Туры в ОАЭ: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните эмираты и отели ОАЭ перед выбором конкретного тура.',
    'countryId' => 9,
    'resorts' => ['Дубай', 'Шарджа', 'Рас-эль-Хайма', 'Абу-Даби'],
    'facts' => [
        ['title' => 'Выберите эмират', 'text' => 'Дубай, Шарджа, Рас-эль-Хайма и Абу-Даби заметно отличаются по атмосфере, расположению отелей и формату отдыха.'],
        ['title' => 'Учитывайте расположение', 'text' => 'Для ОАЭ важно смотреть не только категорию отеля, но и район, расстояние до пляжа и выбранный формат питания.'],
        ['title' => 'Проверяйте вариант целиком', 'text' => 'Перед заявкой откройте конкретный тур: цена, рейс, багаж и размещение должны относиться именно к выбранному варианту.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
]);
