<?php
require_once dirname(dirname(__DIR__)) . '/country-page-v1.php';
require_once dirname(dirname(__DIR__)) . '/seo-content-pilot-sri-lanka-v1.php';

$editorial = v2_seo_content_pilot_sri_lanka();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'sri-lanka',
    'name' => 'Шри-Ланка',
    'h1' => $data['h1'] ?? 'Туры на Шри-Ланку',
    'title' => $data['title'] ?? 'Туры на Шри-Ланку — AnyTour',
    'description' => $data['description'] ?? 'Туры на Шри-Ланку: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните районы и отели Шри-Ланки перед выбором конкретного тура.',
    'countryId' => 12,
    'resorts' => ['Бентота', 'Хиккадува', 'Унаватуна', 'Негомбо', 'Велигама'],
    'facts' => [
        ['title' => 'Побережье и даты', 'text' => 'Условия на разных частях побережья отличаются, поэтому район отдыха лучше выбирать вместе с конкретными датами поездки.'],
        ['title' => 'Формат путешествия', 'text' => 'Определите, нужен ли прежде всего пляжный отель или удобная база для поездок по острову.'],
        ['title' => 'Проверка тура', 'text' => 'Перед заявкой сравните даты, отель, питание, перелёт и итоговую стоимость конкретного предложения.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
]);
