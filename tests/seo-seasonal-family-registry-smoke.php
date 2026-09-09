<?php
require_once __DIR__ . '/../v2/seo-seasonal-family-registry-v1.php';
function seasonal_registry_fail(string $m):void{fwrite(STDERR,"SEO_SEASONAL_FAMILY_REGISTRY_FAIL:$m\n");exit(1);}

$families=v2_seo_seasonal_family_registry();
if(array_keys($families)!==['turkey']) seasonal_registry_fail('unexpected_family_keys');
$turkey=$families['turkey']??[];
if(($turkey['state']??'')!=='verified_review_only_destination_family') seasonal_registry_fail('state');
if(($turkey['country_id']??0)!==4) seasonal_registry_fail('country_id');
if(($turkey['resort_count']??0)!==5) seasonal_registry_fail('resort_count');
if(($turkey['publication_allowed']??true)!==false||($turkey['copy_allowed']??true)!==false||($turkey['publication_candidates']??null)!==[]) seasonal_registry_fail('publication_boundary');
$regions=[];
foreach($turkey['resorts']??[] as $record){
    $region=(int)($record['data']['search_state']['region']??0);
    if($region<=0||isset($regions[$region])) seasonal_registry_fail('region_identity');
    if((int)($record['data']['search_state']['country']??0)!==4) seasonal_registry_fail('resort_country');
    $regions[$region]=true;
}
ksort($regions,SORT_NUMERIC);
if(array_keys($regions)!==[19,20,21,22,23]) seasonal_registry_fail('verified_region_set');
try{v2_seo_seasonal_family_registry_get('maldives');seasonal_registry_fail('unknown_family_passed');}catch(InvalidArgumentException $e){}
echo "SEO_SEASONAL_FAMILY_REGISTRY_OK families=1 turkey_resorts=5 publication=0\n";
