<?php
require_once dirname(dirname(__DIR__)).'/country-page-v1.php';
require_once dirname(dirname(__DIR__)).'/seo-content-pilot-tunisia-v1.php';

$editorial = v2_seo_content_pilot_tunisia();
$data = is_array($editorial['data'] ?? null) ? $editorial['data'] : [];

cp_render([
    'slug' => 'tunis',
    'name' => 'Тунис',
    'h1' => $data['h1'] ?? 'Туры в Тунис',
    'title' => $data['title'] ?? 'Туры в Тунис — AnyTour',
    'description' => $data['description'] ?? 'Туры в Тунис: подбор актуальных предложений AnyTour.',
    'intro' => $data['intro'] ?? 'Сравните курорты и отели Туниса перед выбором конкретного тура.',
    'resorts' => ['Хаммамет', 'Сусс', 'Монастир', 'Махдия', 'Джерба'],
    'facts' => [
        ['title' => 'Курорт и атмосфера', 'text' => 'Хаммамет, Сусс, Махдия и Джерба отличаются по ритму отдыха, пляжам и набору отелей — сначала определите сценарий поездки.'],
        ['title' => 'Отель и питание', 'text' => 'Сравнивайте не только категорию отеля, но и питание, расположение и конкретный тип номера в выбранном туре.'],
        ['title' => 'Проверка перед заявкой', 'text' => 'Перед передачей менеджеру откройте конкретный тур и проверьте доступные детали перелёта, размещения и итоговой стоимости.'],
    ],
    'editorialSections' => $data['sections'] ?? [],
]);
