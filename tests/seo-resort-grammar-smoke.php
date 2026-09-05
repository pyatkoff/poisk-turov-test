<?php
declare(strict_types=1);

require_once __DIR__ . '/../v2/seo-resort-page-v1.php';

function resort_grammar_fail(string $message): void
{
    fwrite(STDERR, "SEO_RESORT_GRAMMAR_FAIL:$message\n");
    exit(1);
}

$cases = [
    [['name' => 'Анталья', 'h1' => 'Туры в Анталью'], 'Анталью'],
    [['name' => 'Аланья', 'h1' => 'Туры в Аланью'], 'Аланью'],
    [['name' => 'Кемер', 'h1' => 'Туры в Кемер'], 'Кемер'],
    [['name' => 'Тангалле', 'h1' => 'Туры в Тангалле'], 'Тангалле'],
    [['name' => 'Анталья', 'name_accusative' => 'Анталью', 'h1' => 'Другое название'], 'Анталью'],
    [['name' => 'Кемер', 'h1' => 'Курорт Кемер'], 'Кемер'],
];

foreach ($cases as [$page, $expected]) {
    $actual = v2_seo_resort_destination_name($page);
    if ($actual !== $expected) resort_grammar_fail($actual.'!='.$expected);
}

echo "SEO_RESORT_GRAMMAR_OK cases=".count($cases)."\n";
