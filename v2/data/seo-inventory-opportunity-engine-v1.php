<?php
declare(strict_types=1);

/**
 * Review-only SEO inventory opportunity engine.
 *
 * This layer ranks identities that already exist in first-party tour observations.
 * It does not generate Cartesian combinations, infer demand, or grant publication.
 */

function v2_seo_inventory_month_slug(int $month): ?string
{
    static $months = [
        1=>'january',2=>'february',3=>'march',4=>'april',5=>'may',6=>'june',
        7=>'july',8=>'august',9=>'september',10=>'october',11=>'november',12=>'december',
    ];
    return $months[$month] ?? null;
}

function v2_seo_inventory_candidate_path(array $row): ?string
{
    $type=(string)($row['candidate_type']??'');
    $countrySlug=trim((string)($row['country_slug']??''));
    $regionSlug=trim((string)($row['region_slug']??''));
    if($countrySlug==='') return null;

    if($type==='country') return '/country/'.$countrySlug.'/';
    if($type==='resort') return $regionSlug!==''?'/country/'.$countrySlug.'/'.$regionSlug.'/':null;

    $month=v2_seo_inventory_month_slug((int)($row['departure_month']??0));
    if($month===null) return null;
    if($type==='country_month') return '/country/'.$countrySlug.'/'.$month.'/';
    if($type==='resort_month') return $regionSlug!==''?'/country/'.$countrySlug.'/'.$regionSlug.'/'.$month.'/':null;
    return null;
}

/** @return array{ok:bool,candidate?:array,errors?:array,raw_sha256:string} */
function v2_seo_inventory_normalize_row(array $row): array
{
    $rawSha=hash('sha256',json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    $errors=[];
    $type=(string)($row['candidate_type']??'');
    $allowed=['country','resort','country_month','resort_month'];
    if(!in_array($type,$allowed,true)) $errors[]='unsupported_candidate_type';

    $countryId=(int)($row['country_id']??0);
    $countryName=trim((string)($row['country_name']??''));
    $countrySlug=trim((string)($row['country_slug']??''));
    if($countryId<=0) $errors[]='country_id_invalid';
    if($countryName==='') $errors[]='country_name_missing';

    $isResort=in_array($type,['resort','resort_month'],true);
    $regionId=$row['region_id']??null;
    $regionId=$regionId===null?null:(int)$regionId;
    $regionName=trim((string)($row['region_name']??''));
    $regionSlug=trim((string)($row['region_slug']??''));
    if($isResort&&($regionId===null||$regionId<=0)) $errors[]='region_id_invalid';
    if($isResort&&$regionName==='') $errors[]='region_name_missing';

    $isMonthly=in_array($type,['country_month','resort_month'],true);
    $year=$row['departure_year']??null;
    $year=$year===null?null:(int)$year;
    $month=$row['departure_month']??null;
    $month=$month===null?null:(int)$month;
    if($isMonthly&&($year===null||$year<2020||$year>2100)) $errors[]='departure_year_invalid';
    if($isMonthly&&($month===null||v2_seo_inventory_month_slug($month)===null)) $errors[]='departure_month_invalid';

    $metricKeys=[
        'observations_total','observations_1d','observations_3d','observations_7d','observations_30d',
        'hotels_total','hotels_30d','departures_total','departures_30d','history_depth_days',
    ];
    $metrics=[];
    foreach($metricKeys as $key){
        if(!array_key_exists($key,$row)||!is_numeric($row[$key])){
            $errors[]='missing_metric_'.$key;
            $metrics[$key]=null;
            continue;
        }
        $value=(int)$row[$key];
        if($value<0) $errors[]='negative_metric_'.$key;
        $metrics[$key]=$value;
    }
    if(($metrics['observations_total']??0)<=0) $errors[]='observations_total_empty';
    if(($metrics['observations_30d']??0)<=0) $errors[]='observations_30d_empty';
    if(
        $metrics['observations_1d']!==null&&$metrics['observations_3d']!==null&&$metrics['observations_7d']!==null&&
        $metrics['observations_30d']!==null&&$metrics['observations_total']!==null&&
        !($metrics['observations_1d']<=$metrics['observations_3d']&&$metrics['observations_3d']<=$metrics['observations_7d']&&
          $metrics['observations_7d']<=$metrics['observations_30d']&&$metrics['observations_30d']<=$metrics['observations_total'])
    ) $errors[]='observation_windows_inconsistent';
    if($metrics['hotels_30d']!==null&&$metrics['hotels_total']!==null&&$metrics['hotels_30d']>$metrics['hotels_total']) $errors[]='hotel_windows_inconsistent';
    if($metrics['departures_30d']!==null&&$metrics['departures_total']!==null&&$metrics['departures_30d']>$metrics['departures_total']) $errors[]='departure_windows_inconsistent';

    $minPrice=null;$maxPrice=null;
    foreach(['min_price_30d','max_price_30d'] as $priceKey){
        if(!array_key_exists($priceKey,$row)||$row[$priceKey]===null||$row[$priceKey]===''){
            $errors[]='missing_'.$priceKey;
            continue;
        }
        if(!is_numeric($row[$priceKey])||(float)$row[$priceKey]<=0){
            $errors[]='invalid_'.$priceKey;
            continue;
        }
        if($priceKey==='min_price_30d') $minPrice=round((float)$row[$priceKey],2);
        else $maxPrice=round((float)$row[$priceKey],2);
    }
    if($minPrice!==null&&$maxPrice!==null&&$minPrice>$maxPrice) $errors[]='price_range_inconsistent';

    $oldest=trim((string)($row['oldest_observed_at']??''));
    $newest=trim((string)($row['newest_observed_at']??''));
    $oldestEpoch=$oldest!==''?strtotime($oldest):false;
    $newestEpoch=$newest!==''?strtotime($newest):false;
    if($oldestEpoch===false) $errors[]='oldest_observed_at_invalid';
    if($newestEpoch===false) $errors[]='newest_observed_at_invalid';
    if($oldestEpoch!==false&&$newestEpoch!==false&&$oldestEpoch>$newestEpoch) $errors[]='observation_time_order_invalid';

    if($errors!==[]) return ['ok'=>false,'errors'=>array_values(array_unique($errors)),'raw_sha256'=>$rawSha];

    $identity=$type.':country='.$countryId;
    if($isResort) $identity.=':region='.$regionId;
    if($isMonthly) $identity.=sprintf(':period=%04d-%02d',$year,$month);

    $candidate=[
        'state'=>'review_only_inventory_candidate',
        'candidate_type'=>$type,
        'identity_key'=>$identity,
        'country_id'=>$countryId,
        'country_name'=>$countryName,
        'country_slug'=>$countrySlug!==''?$countrySlug:null,
        'region_id'=>$isResort?$regionId:null,
        'region_name'=>$isResort?$regionName:null,
        'region_slug'=>$isResort&&$regionSlug!==''?$regionSlug:null,
        'departure_year'=>$isMonthly?$year:null,
        'departure_month'=>$isMonthly?$month:null,
        'period_key'=>$isMonthly?sprintf('%04d-%02d',$year,$month):null,
        'review_path'=>null,
        'path_period_semantics'=>$isMonthly?'yearless_route_requires_period_review':'not_applicable',
        'inventory'=>[
            'observations_total'=>$metrics['observations_total'],
            'observations_1d'=>$metrics['observations_1d'],
            'observations_3d'=>$metrics['observations_3d'],
            'observations_7d'=>$metrics['observations_7d'],
            'observations_30d'=>$metrics['observations_30d'],
            'distinct_hotels_total'=>$metrics['hotels_total'],
            'distinct_hotels_30d'=>$metrics['hotels_30d'],
            'distinct_departures_total'=>$metrics['departures_total'],
            'distinct_departures_30d'=>$metrics['departures_30d'],
            'min_price_30d_rub'=>$minPrice,
            'max_price_30d_rub'=>$maxPrice,
            'median_price_state'=>'not_computed',
            'oldest_observed_at'=>$oldest,
            'newest_observed_at'=>$newest,
            'history_depth_days'=>$metrics['history_depth_days'],
            'fresh_observation_within_3d'=>$metrics['observations_3d']>0,
        ],
        'demand'=>[
            'status'=>'unknown',
            'reason'=>'not_joined_no_zero_imputation',
            'impressions'=>null,
            'clicks'=>null,
            'avg_position'=>null,
        ],
        'review_state'=>'review_required',
        'opportunity_score'=>null,
        'publication_allowed'=>false,
        'indexation_allowed'=>false,
        'sitemap_allowed'=>false,
        'route_launch_allowed'=>false,
    ];
    $candidate['review_path']=v2_seo_inventory_candidate_path($candidate);
    return ['ok'=>true,'candidate'=>$candidate,'raw_sha256'=>$rawSha];
}

function v2_seo_inventory_candidate_compare(array $a,array $b): int
{
    $ai=$a['inventory'];$bi=$b['inventory'];
    foreach(['observations_30d','distinct_hotels_30d','distinct_departures_30d','observations_3d'] as $key){
        $cmp=((int)$bi[$key])<=>((int)$ai[$key]);
        if($cmp!==0) return $cmp;
    }
    $cmp=strcmp((string)$bi['newest_observed_at'],(string)$ai['newest_observed_at']);
    if($cmp!==0) return $cmp;
    return strcmp((string)$a['identity_key'],(string)$b['identity_key']);
}

/**
 * @param array<int,array> $rows rows collected only from observed DB groups
 * @param string[] $controlledPaths exact current controlled path registry
 */
function v2_seo_inventory_opportunity_report(array $rows,int $limitPerType=50,array $controlledPaths=[]): array
{
    $limitPerType=max(1,min(500,$limitPerType));
    $blocked=[];$groups=[];
    foreach($rows as $row){
        if(!is_array($row)){
            $blocked[]=['raw_sha256'=>hash('sha256',serialize($row)),'errors'=>['invalid_item']];
            continue;
        }
        $normalized=v2_seo_inventory_normalize_row($row);
        if(($normalized['ok']??false)!==true){
            $blocked[]=['raw_sha256'=>$normalized['raw_sha256'],'errors'=>$normalized['errors']??['invalid_row']];
            continue;
        }
        $candidate=$normalized['candidate'];
        $groups[(string)$candidate['identity_key']][]=$candidate;
    }

    $unique=[];
    foreach($groups as $identity=>$items){
        if(count($items)!==1){
            $blocked[]=['raw_sha256'=>hash('sha256',$identity),'identity_key'=>$identity,'errors'=>['duplicate_observed_identity']];
            continue;
        }
        $unique[]=$items[0];
    }

    $controlled=array_fill_keys(array_values($controlledPaths),true);
    $typeOrder=['country','resort','country_month','resort_month'];
    $byType=[];$reported=[];$observedCounts=[];
    foreach($typeOrder as $type){
        $items=array_values(array_filter($unique,static fn(array $candidate):bool=>$candidate['candidate_type']===$type));
        usort($items,'v2_seo_inventory_candidate_compare');
        $observedCounts[$type]=count($items);
        $items=array_slice($items,0,$limitPerType);
        foreach($items as $index=>&$candidate){
            $candidate['inventory_rank']=$index+1;
            $path=$candidate['review_path'];
            $candidate['path_exists_in_controlled_registry']=$path!==null&&isset($controlled[$path]);
            $candidate['ranking_basis']='observations_30d_then_hotels_30d_then_departures_30d_then_observations_3d_then_recency';
        }
        unset($candidate);
        $byType[$type]=[
            'observed_identity_count'=>$observedCounts[$type],
            'reported_count'=>count($items),
            'candidates'=>$items,
        ];
        foreach($items as $candidate) $reported[]=$candidate;
    }

    usort($blocked,static function(array $a,array $b):int{
        $ak=(string)($a['identity_key']??$a['raw_sha256']??'');
        $bk=(string)($b['identity_key']??$b['raw_sha256']??'');
        $cmp=strcmp($ak,$bk);
        if($cmp!==0) return $cmp;
        return strcmp(json_encode($a['errors']??[]),json_encode($b['errors']??[]));
    });

    $payload=[
        'state'=>'review_only_inventory_opportunity_report',
        'scope'=>'first_party_observed_country_resort_monthly_combinations',
        'observed_identity_count'=>count($unique),
        'reported_candidate_count'=>count($reported),
        'blocked_count'=>count($blocked),
        'limit_per_type'=>$limitPerType,
        'ranking_semantics'=>'inventory_components_only_no_combined_score',
        'candidate_generation_semantics'=>'observed_database_groups_only_no_cartesian_generation',
        'demand_semantics'=>'unknown_until_real_search_feedback_is_joined_never_zero_imputed',
        'by_type'=>$byType,
        'candidates'=>$reported,
        'blocked'=>$blocked,
        'publication_candidates'=>[],
        'publication_allowed'=>false,
        'automatic_execution_allowed'=>false,
        'hotel_tours_indexation_allowed'=>false,
        'explicit_user_launch_approval_required'=>true,
        'generated_at_utc'=>gmdate('c'),
    ];
    $stable=$payload;unset($stable['generated_at_utc']);
    $payload['evidence_sha256']=hash('sha256',json_encode($stable,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    return $payload;
}
