<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/data/search3-local-results-read-v1.php';

function category_need(bool $ok,string $label):void
{
    if(!$ok)throw new RuntimeException('CATEGORY_SCOPE_CHECK_FAILED:'.$label);
}

$params=[
    'departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-09-19','dateTo'=>'2026-09-22',
    'nightsFrom'=>7,'nightsTo'=>10,'adults'=>2,'childs'=>[],
    'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],'hotelIds'=>[],
    'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],
    'operatorIds'=>[],'priceFrom'=>'','priceTo'=>'','currency'=>'RUB',
    'onlyCharter'=>false,'onlyDirect'=>false,
];
$broad=AnyTourSearchScopeV1::fromParams($params);
$currentParams=$params;$currentParams['hotelCategory']='4';
$current=AnyTourSearchScopeV1::fromParams($currentParams);
category_need(
    !AnyTourSearchScopeV1::savedCanContributeToCurrent($broad['params'],$current['params']),
    'strict saved-scope contract stays unchanged'
);
category_need(
    AnyTourSearchScopeV1::savedCanContributeWithCanonicalCategoryProof($broad['params'],$current['params']),
    'broad saved scope is nominated for canonical category proof'
);

$fiveParams=$params;$fiveParams['hotelCategory']='5';
$five=AnyTourSearchScopeV1::fromParams($fiveParams);
category_need(
    !AnyTourSearchScopeV1::savedCanContributeWithCanonicalCategoryProof($five['params'],$current['params']),
    'category-bearing saved scope remains strict'
);

category_need(search3_local_profile_matches_scope(['category'=>4],$current['params']),'4-star accepted');
category_need(search3_local_profile_matches_scope(['category'=>5],$current['params']),'5-star accepted');
category_need(!search3_local_profile_matches_scope(['category'=>3],$current['params']),'3-star rejected');
category_need(!search3_local_profile_matches_scope(['category'=>null],$current['params']),'unknown category rejected');
category_need(!search3_local_profile_matches_scope(['category'=>'4'],$current['params']),'string category rejected');
category_need(!search3_local_profile_matches_scope(['category'=>6],$current['params']),'out-of-range category rejected');
category_need(search3_local_profile_matches_scope(['category'=>null],$broad['params']),'all-stars request keeps unknown category');

$malformed=$current['params'];$malformed['hotelCategory']='4+';
category_need(!search3_local_profile_matches_scope(['category'=>5],$malformed),'malformed request fails closed');

echo "SEARCH3_LOCAL_CATEGORY_SCOPE_OK nomination=1 accepted=2 rejected=5 strict=2\n";
