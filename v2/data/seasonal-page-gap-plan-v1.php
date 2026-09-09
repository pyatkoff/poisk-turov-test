<?php
/** Bounded repair of empty existing seasonal pages; no publication or supplier I/O. */
declare(strict_types=1);

function v2_seasonal_page_gap_plan(array $records, array $departures, array $freshKeys, array $publishedPaths, string $today, int $months=12): array
{
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$today);
    if(!$date||$date->format('Y-m-d')!==$today||$months<1||$months>18)throw new InvalidArgumentException('invalid gap horizon');
    $first=$date->modify('+1 day');
    $last=$date->modify('first day of this month')->modify('+'.($months-1).' months')->modify('last day of this month');
    $routes=[];foreach($departures as $r)if((int)($r['is_active']??1)===1)$routes[(int)($r['departure_id']??0).':'.(int)($r['country_id']??0)]=true;
    $fresh=array_fill_keys($freshKeys,true);$published=array_fill_keys($publishedPaths,true);$seen=[];$targets=[];
    foreach($records as $record){
        if(($record['type']??'')!=='seasonal'||($record['status']??'')!=='approved'||($record['publication_allowed']??false)!==true||($record['route_launch_allowed']??false)!==true)continue;
        $path=(string)($record['path']??'');
        if(!isset($published[$path])||!preg_match('~^/country/[a-z0-9-]+/(?:[a-z0-9-]+/)?[a-z]+/$~D',$path))continue;
        $data=$record['data']??[];$identity=$data['seasonal_identity']??[];
        $country=(int)($identity['country_id']??0);$region=(int)($identity['region_id']??0);$departure=(int)($identity['departure_id']??1);
        $year=(int)($identity['year']??0);$month=(int)($identity['month']??0);
        if($country<=0||$departure<=0||$region<0||$year<2020||$year>2100||$month<1||$month>12)continue;
        if((int)($data['search_state']['country']??0)!==$country||(int)($data['search_state']['region']??0)!==$region)continue;
        $period=sprintf('%04d-%02d',$year,$month);
        $key=($region>0?'resort_month:':'month:').$departure.':'.$country.($region>0?':'.$region:'').':'.$period;
        if(($identity['page_key']??'')!==$key||isset($fresh[$key])||isset($seen[$key])||!isset($routes[$departure.':'.$country]))continue;
        $start=new DateTimeImmutable($period.'-01');$end=$start->modify('last day of this month');
        if($end<$first||$start>$last)continue;
        $seen[$key]=true;
        for($offset=0,$week=0;$offset<(int)$end->format('j');$offset+=7,$week++){
            $from=$start->modify('+'.$offset.' days');$to=min($from->modify('+6 days'),$end,$last);
            if($to<$first)continue;$from=max($from,$first);
            $targets[]=['criterion'=>$region>0?'resort':'month','target_key'=>'page_gap:'.$key.':'.$from->format('Y-m-d').':'.$to->format('Y-m-d'),
                'page_key'=>$key,'page_path'=>$path,'departure_id'=>$departure,'country_id'=>$country,'region_id'=>$region>0?$region:null,
                'hotel_ids'=>[],'month'=>$period,'date_from'=>$from->format('Y-m-d'),'date_to'=>$to->format('Y-m-d'),'gap_week'=>$week];
        }
    }
    // Try one small window per empty page before spending the reserve on its
    // whole month. Existing attempt timestamps provide fair resumption later.
    usort($targets,static fn(array $a,array $b):int=>($a['gap_week']<=>$b['gap_week'])?:strcmp($a['month'],$b['month'])?:($a['country_id']<=>$b['country_id'])?:(($a['region_id']??0)<=>($b['region_id']??0))?:strcmp($a['target_key'],$b['target_key']));
    return $targets;
}

/** The gap reserve is part of the existing pass budget, not additional searches. */
function v2_seasonal_gap_pass_targets(array $hotelTargets,array $gapTargets,int $budget,int $reserve=8,string $onlyMonth='',int $onlyCountry=0):array
{
    if($budget<1||$budget>100||$reserve<0||$reserve>8)throw new InvalidArgumentException('invalid gap budget');
    $accept=static fn(array $t):bool=>($onlyMonth===''||$t['month']===$onlyMonth)&&($onlyCountry===0||(int)$t['country_id']===$onlyCountry);
    $hotelTargets=array_values(array_filter($hotelTargets,$accept));$gapTargets=array_values(array_filter($gapTargets,$accept));
    $gapCount=min(count($gapTargets),$reserve,max(0,$budget-($hotelTargets?1:0)));
    return array_merge(array_slice($gapTargets,0,$gapCount),array_slice($hotelTargets,0,$budget-$gapCount));
}

/** Preserve existing hotel-ID search parameters; gap targets use verified scope. */
function v2_top500_target_search_params(array $target):array
{
    $params=['departureId'=>(int)$target['departure_id'],'countryId'=>(int)$target['country_id'],'dateFrom'=>$target['date_from'],'dateTo'=>$target['date_to'],'nightsFrom'=>5,'nightsTo'=>14,'adults'=>2,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false];
    $criterion=(string)($target['criterion']??'');
    if($criterion==='hotel_batch'){
        $ids=array_values(array_unique(array_filter(array_map('intval',$target['hotel_ids']??[]),static fn(int $id):bool=>$id>0)));
        if(!$ids||count($ids)>30)throw new InvalidArgumentException('invalid priority hotel batch');
        $params['hotelIds']=$ids;
    }elseif($criterion==='resort'){
        $region=(int)($target['region_id']??0);if($region<=0)throw new InvalidArgumentException('missing resort identity');
        $params['regionIds']=[$region];
    }elseif($criterion!=='month')throw new InvalidArgumentException('unsupported collection target');
    return $params;
}

function v2_top500_target_accepts_hotel(array $target,array $hotel):bool
{
    $idValue=static function(mixed $v):int{if(is_array($v))$v=$v['id']??0;$n=filter_var($v,FILTER_VALIDATE_INT);return $n!==false&&$n>0?(int)$n:0;};
    $id=$idValue($hotel['id']??0);$country=$idValue($hotel['country']??0);
    if($id<=0||($country>0&&$country!==(int)$target['country_id']))return false;
    if(($target['criterion']??'')==='hotel_batch')return in_array($id,$target['hotel_ids'],true);
    if($country!==(int)$target['country_id'])return false;
    return ($target['criterion']??'')==='month'||(($target['criterion']??'')==='resort'&&$idValue($hotel['region']??0)===(int)$target['region_id']);
}
