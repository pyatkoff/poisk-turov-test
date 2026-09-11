<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/three-provider-meal.php';

$checks = 0;

$assert = static function (bool $condition, string $message) use (&$checks): void {
    ++$checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$plainAi = AnyTourThreeProviderMeal::fromLabel('AI');
$assert($plainAi['family'] === 'ai', 'AI family');
$assert($plainAi['canonical_key'] === 'ai', 'AI key');
$assert($plainAi['qualifiers'] === [], 'AI qualifiers');
$assert($plainAi['classification_status'] === 'verified', 'AI verified');
$assert($plainAi['cross_provider_equivalence_verified'] === false, 'AI cross-provider unverified');
$assert($plainAi['package_equivalence_verified'] === false, 'AI package unverified');

$noAlcohol = AnyTourThreeProviderMeal::fromLabel('AI WITHOUT ALCOHOL');
$assert($noAlcohol['family'] === 'ai', 'AI without alcohol family');
$assert($noAlcohol['qualifiers'] === ['without_alcohol'], 'AI without alcohol qualifier');
$assert($noAlcohol['canonical_key'] === 'ai:without_alcohol', 'AI without alcohol key');
$assert($noAlcohol['canonical_key'] !== $plainAi['canonical_key'], 'AI without alcohol distinct');

$plus = AnyTourThreeProviderMeal::fromLabel('ALL INCLUSIVE+');
$assert($plus['family'] === 'ai', 'AI plus family');
$assert($plus['qualifiers'] === ['plus'], 'AI plus qualifier');
$assert($plus['canonical_key'] === 'ai:plus', 'AI plus key');
$assert($plus['canonical_key'] !== $plainAi['canonical_key'], 'AI plus distinct');

$uai = AnyTourThreeProviderMeal::fromLabel('Ultra All Inclusive');
$assert($uai['family'] === 'uai', 'UAI family');
$assert($uai['canonical_key'] === 'uai', 'UAI key');
$assert($uai['canonical_key'] !== $plainAi['canonical_key'], 'UAI distinct');

$aliases = [
    'RO' => 'ro',
    'Room Only' => 'ro',
    'без питания' => 'ro',
    'BB' => 'bb',
    'Bed & Breakfast' => 'bb',
    'завтрак' => 'bb',
    'HB' => 'hb',
    'Half Board' => 'hb',
    'полупансион' => 'hb',
    'FB' => 'fb',
    'Full Board' => 'fb',
    'полный пансион' => 'fb',
    'Всё включено' => 'ai',
    'Ультра всё включено' => 'uai',
];

foreach ($aliases as $label => $family) {
    $meal = AnyTourThreeProviderMeal::fromLabel($label);
    $assert($meal['family'] === $family, $label . ' family');
    $assert($meal['canonical_key'] === $family, $label . ' key');
}

$unknown = AnyTourThreeProviderMeal::fromLabel('chef choice');
$assert($unknown['family'] === null, 'unknown family');
$assert($unknown['canonical_key'] === null, 'unknown key');
$assert($unknown['classification_status'] === 'unknown', 'unknown status');
$assert($unknown['cross_provider_equivalence_verified'] === false, 'unknown equivalence');

foreach ([null, 7, '', '   ', str_repeat('a', 121), '12345'] as $invalid) {
    $thrown = false;
    try {
        AnyTourThreeProviderMeal::fromLabel($invalid);
    } catch (InvalidArgumentException) {
        $thrown = true;
    }
    $assert($thrown, 'invalid value rejected');
}

echo "three-provider-meal: {$checks} checks passed\n";
