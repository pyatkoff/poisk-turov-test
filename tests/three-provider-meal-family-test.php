<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-meal-family.php';

$checks=0;
function meal_check(bool $ok): void { global $checks; ++$checks; if (!$ok) throw new RuntimeException('meal_check_'.$checks); }

$families=[
    'RO'=>'ro','Room Only'=>'ro','Без питания'=>'ro',
    'BB'=>'bb','Bed & Breakfast'=>'bb','Завтрак'=>'bb',
    'HB'=>'hb','Half Board'=>'hb','Полупансион'=>'hb',
    'FB'=>'fb','Full Board'=>'fb','Полный пансион'=>'fb',
    'AI'=>'ai','All Inclusive'=>'ai','ВСЕ ВКЛЮЧЕНО'=>'ai',
    'UAI'=>'uai','Ultra All Inclusive'=>'uai','Ультра всё включено'=>'uai',
];
foreach($families as $raw=>$family){$value=AnyTourThreeProviderMealFamily::normalize($raw);meal_check($value['family']===$family&&$value['family_verified']===true);meal_check($value['raw']===$raw);}

$plainAi=AnyTourThreeProviderMealFamily::normalize('AI');
meal_check($plainAi['canonical_key']==='ai'&&$plainAi['classification_status']==='verified');
meal_check($plainAi['cross_provider_equivalence_verified']===false&&$plainAi['package_equivalence_verified']===false);

$hbPlus=AnyTourThreeProviderMealFamily::normalize('HB+');
meal_check($hbPlus['family']==='hb'&&$hbPlus['qualifiers']['plus']===true&&$hbPlus['qualifiers']['without_alcohol']===false);
meal_check($hbPlus['canonical_key']==='hb:plus');
$aiPlus=AnyTourThreeProviderMealFamily::normalize('All Inclusive Plus');
meal_check($aiPlus['family']==='ai'&&$aiPlus['qualifiers']['plus']===true&&$aiPlus['canonical_key']==='ai:plus');
$noAlcohol=AnyTourThreeProviderMealFamily::normalize('AI-WITHOUT ALCOHOL');
meal_check($noAlcohol['family']==='ai'&&$noAlcohol['qualifiers']['without_alcohol']===true&&$noAlcohol['qualifiers']['plus']===false&&$noAlcohol['canonical_key']==='ai:without_alcohol');
$uaiNoAlcohol=AnyTourThreeProviderMealFamily::normalize('Ультра все включено без алкоголя');
meal_check($uaiNoAlcohol['family']==='uai'&&$uaiNoAlcohol['qualifiers']['without_alcohol']===true&&$uaiNoAlcohol['canonical_key']==='uai:without_alcohol');

$unknown=AnyTourThreeProviderMealFamily::normalize('Premium Concept');
meal_check($unknown['family']===null&&$unknown['family_verified']===false&&$unknown['canonical_key']===null&&$unknown['classification_status']==='unknown');
meal_check($unknown['raw']==='Premium Concept'&&$unknown['normalized_label']==='premium concept');
meal_check($unknown['qualifiers']===['plus'=>false,'without_alcohol'=>false]);

foreach([null,7,[], '', '   ', str_repeat('A',121)] as $bad){try{AnyTourThreeProviderMealFamily::normalize($bad);meal_check(false);}catch(InvalidArgumentException $e){meal_check(true);}}

// Numeric supplier IDs must never enter the cross-provider family mapper.
try{$numeric=AnyTourThreeProviderMealFamily::normalize('7');meal_check($numeric['family']===null&&$numeric['family_verified']===false&&$numeric['canonical_key']===null);}catch(Throwable $e){meal_check(false);}

$source='AI-WITHOUT ALCOHOL';$copy=$source;AnyTourThreeProviderMealFamily::normalize($source);meal_check($source===$copy);

echo 'Three-provider meal family: '.$checks." checks passed; supplier/DB=0.\n";
