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

$hbPlus=AnyTourThreeProviderMealFamily::normalize('HB+');
meal_check($hbPlus['family']==='hb'&&$hbPlus['qualifiers']['plus']===true&&$hbPlus['qualifiers']['without_alcohol']===false);
$aiPlus=AnyTourThreeProviderMealFamily::normalize('All Inclusive Plus');
meal_check($aiPlus['family']==='ai'&&$aiPlus['qualifiers']['plus']===true);
$noAlcohol=AnyTourThreeProviderMealFamily::normalize('AI-WITHOUT ALCOHOL');
meal_check($noAlcohol['family']==='ai'&&$noAlcohol['qualifiers']['without_alcohol']===true&&$noAlcohol['qualifiers']['plus']===false);
$uaiNoAlcohol=AnyTourThreeProviderMealFamily::normalize('Ультра все включено без алкоголя');
meal_check($uaiNoAlcohol['family']==='uai'&&$uaiNoAlcohol['qualifiers']['without_alcohol']===true);

$unknown=AnyTourThreeProviderMealFamily::normalize('Premium Concept');
meal_check($unknown['family']===null&&$unknown['family_verified']===false);
meal_check($unknown['raw']==='Premium Concept'&&$unknown['normalized_label']==='premium concept');
meal_check($unknown['qualifiers']===['plus'=>false,'without_alcohol'=>false]);

foreach([null,7,[], '', '   ', str_repeat('A',121)] as $bad){try{AnyTourThreeProviderMealFamily::normalize($bad);meal_check(false);}catch(InvalidArgumentException $e){meal_check(true);}}

// Numeric supplier IDs must never enter the cross-provider family mapper.
try{AnyTourThreeProviderMealFamily::normalize('7');$numeric=AnyTourThreeProviderMealFamily::normalize('7');meal_check($numeric['family']===null&&$numeric['family_verified']===false);}catch(Throwable $e){meal_check(false);}

$source='AI-WITHOUT ALCOHOL';$copy=$source;AnyTourThreeProviderMealFamily::normalize($source);meal_check($source===$copy);

echo 'Three-provider meal family: '.$checks." checks passed; supplier/DB=0.\n";
