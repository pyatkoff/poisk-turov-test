<?php
declare(strict_types=1);

/** MATCH #1971: isolated read-only probe of owner-configured Tourvisor ANEX-only credential. */

function mtv5_emit(array $v, int $code = 0): void {
    echo 'MATCH_JSON:' . json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($code);
}
function mtv5_fail(string $stage, string $reason, array $extra=[]): void {
    mtv5_emit(array_merge([
        'status'=>'failed','stage'=>$stage,'reason'=>$reason,'token_value_recorded'=>false,
        'database_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'continue_calls'=>0,
        'tour_detail_calls'=>0,'booking_calls'=>0,'search_started'=>false,
    ], $extra), 2);
}
function mtv5_rows(array $d): array {
    if (array_is_list($d)) return $d;
    foreach (['data','items','results','operators','countries','departures','hotels'] as $k) {
        if (isset($d[$k]) && is_array($d[$k])) {
            $r=mtv5_rows($d[$k]); if ($r!==[]) return $r;
        }
    }
    return [];
}
function mtv5_entities(array $d, int $max=300): array {
    $out=[];
    foreach (mtv5_rows($d) as $row) {
        if (!is_array($row)) continue;
        $id=$row['id']??$row['operatorId']??$row['countryId']??$row['departureId']??null;
        $name=$row['name']??$row['russianName']??$row['fullName']??$row['operatorName']??null;
        if ($id===null && !is_string($name)) continue;
        $out[]=['id'=>$id===null?null:(string)$id,'name'=>is_string($name)?substr($name,0,140):null];
        if (count($out)>=$max) break;
    }
    return $out;
}
function mtv5_dates($node, array &$out): void {
    if (count($out)>=1000) return;
    if (is_string($node)) {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/D',$node)) $out[$node]=true;
        elseif (preg_match_all('/\b(20\d{2}-\d{2}-\d{2})\b/',$node,$m)) foreach($m[1] as $d)$out[$d]=true;
        return;
    }
    if (!is_array($node)) return;
    foreach($node as $v) mtv5_dates($v,$out);
}
function mtv5_complete(array $node): bool {
    foreach($node as $k=>$v){
        if(is_array($v)&&mtv5_complete($v)) return true;
        if(is_string($k)&&strtolower($k)==='progress'&&is_numeric($v)&&(float)$v>=100) return true;
        if(is_string($k)&&strtolower($k)==='status'&&is_string($v)&&in_array(strtolower($v),['complete','completed','ready','done'],true)) return true;
    }
    return false;
}
function mtv5_operator_hints($node, array &$out): void {
    if (!is_array($node) || count($out)>=100) return;
    foreach($node as $k=>$v){
        if(is_string($k)&&strpos(strtolower($k),'operator')!==false&&is_scalar($v)) $out[$k.'='.substr((string)$v,0,120)]=true;
        if(is_array($v)) mtv5_operator_hints($v,$out);
    }
}

try {
    ini_set('display_errors','0'); ini_set('log_errors','0');
    $root=realpath(getcwd()); if(!$root||basename($root)!=='anytoour.ru') mtv5_fail('config','server_root_invalid');
    $cfg=$root.'/config.php'; if(!is_file($cfg)||is_link($cfg)) mtv5_fail('config','config_missing');
    ob_start(); require_once $cfg; ob_end_clean();
    if(!defined('TOURVISOR_ANEX_JWT')) mtv5_fail('config','anex_jwt_constant_missing');
    $token=trim((string)constant('TOURVISOR_ANEX_JWT')); if(stripos($token,'Bearer ')===0)$token=trim(substr($token,7));
    if($token===''||strlen($token)<20) mtv5_fail('config','anex_jwt_empty');
    putenv('TOURVISOR_JWT='.$token);
    $client=is_file($root.'/data/tourvisor-client-v1.php')?$root.'/data/tourvisor-client-v1.php':$root.'/v2/data/tourvisor-client-v1.php';
    if(!is_file($client)||is_link($client)) mtv5_fail('config','tourvisor_client_missing');
    require_once $client;
    $base=['token_configured'=>true,'token_value_recorded'=>false,'database_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'continue_calls'=>0,'tour_detail_calls'=>0,'booking_calls'=>0,'search_started'=>false];

    try{$deps=v2_data_tv_get('/departures');}catch(Throwable $e){mtv5_fail('departures','tourvisor_error',$base+['message'=>substr($e->getMessage(),0,160)]);}
    $depRows=mtv5_entities($deps); $base['departures_count']=count($depRows);
    $departureId=0; foreach($depRows as $r){if(($r['id']??'')==='1'){$departureId=1;break;}} if($departureId<=0)$departureId=(int)($depRows[0]['id']??0);
    if($departureId<=0) mtv5_fail('departures','departure_id_missing',$base); $base['departure_id']=(string)$departureId;

    try{$countriesRaw=v2_data_tv_get('/countries',['departureId'=>$departureId]);}catch(Throwable $e){mtv5_fail('countries','tourvisor_error',$base+['message'=>substr($e->getMessage(),0,160)]);}
    $countries=mtv5_entities($countriesRaw); $base['countries_count']=count($countries);
    $countryId=0; foreach($countries as $r){$n=strtolower((string)($r['name']??''));if(($r['id']??'')==='4'||strpos($n,'turk')!==false||strpos($n,'турц')!==false){$countryId=(int)$r['id'];break;}}
    if($countryId<=0) mtv5_fail('countries','turkey_not_available',$base); $base['country_id']=(string)$countryId;

    try{$opsRaw=v2_data_tv_get('/operators',['departureId'=>$departureId,'countryId'=>$countryId]);}catch(Throwable $e){mtv5_fail('operators','tourvisor_error',$base+['message'=>substr($e->getMessage(),0,160)]);}
    $ops=mtv5_entities($opsRaw); $base['operator_count']=count($ops); $base['operators']=array_slice($ops,0,30);
    $anex=[]; foreach($ops as $op){if(strpos(strtolower((string)($op['name']??'')),'anex')!==false)$anex[]=$op;}
    $base['anex_operator_match_count']=count($anex); $base['account_operator_scope_exact_anex']=count($ops)===1&&count($anex)===1;
    if(count($anex)!==1||!ctype_digit((string)($anex[0]['id']??''))) mtv5_fail('operators','anex_operator_not_unique',$base);
    $operatorId=(int)$anex[0]['id']; $base['anex_operator_id']=(string)$operatorId;

    try{$datesRaw=v2_data_tv_get('/tours/dates',['departureId'=>$departureId,'countryId'=>$countryId,'onlyCharter'=>false]);}catch(Throwable $e){mtv5_fail('dates','tourvisor_error',$base+['message'=>substr($e->getMessage(),0,160)]);}
    $dateSet=[]; mtv5_dates($datesRaw,$dateSet); $dates=array_keys($dateSet); sort($dates,SORT_STRING);
    $today='2026-09-11'; $date=null; foreach($dates as $d){if($d>$today){$date=$d;break;}}
    $base['dates_count']=count($dates); $base['first_dates']=array_slice($dates,0,12);
    if($date===null) mtv5_fail('dates','future_date_missing',$base); $base['search_date']=$date;

    try{$start=v2_data_tv_get('/tours/search',['departureId'=>$departureId,'countryId'=>$countryId,'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,'operatorIds'=>[$operatorId]]);}catch(Throwable $e){mtv5_fail('search_start','tourvisor_error',$base+['message'=>substr($e->getMessage(),0,160)]);}
    $sid=(int)($start['searchId']??$start['id']??0); if($sid<=0)mtv5_fail('search_start','search_id_missing',$base); $base['search_started']=true;
    $done=false;$polls=0;for($i=0;$i<30;$i++){if($i>0)sleep(2);try{$st=v2_data_tv_get('/tours/search/'.$sid.'/status',['operatorStatus'=>false]);}catch(Throwable $e){mtv5_fail('status','tourvisor_error',$base+['status_polls'=>$polls,'message'=>substr($e->getMessage(),0,160)]);} $polls++;if(mtv5_complete($st)){$done=true;break;}}
    if(!$done)mtv5_fail('status','search_not_complete',$base+['status_polls'=>$polls]);
    try{$res=v2_data_tv_get('/tours/search/'.$sid,['limit'=>100]);}catch(Throwable $e){mtv5_fail('results','tourvisor_error',$base+['status_polls'=>$polls,'message'=>substr($e->getMessage(),0,160)]);}
    $h=[];mtv5_operator_hints($res,$h);$hints=array_keys($h);$anexHints=array_values(array_filter($hints,fn($s)=>strpos(strtolower($s),'anex')!==false));
    mtv5_emit($base+['status'=>'completed','search_completed'=>true,'status_polls'=>$polls,'result_row_count'=>count(mtv5_rows($res)),'operator_hints'=>$hints,'anex_operator_hints'=>$anexHints,'anex_only_observed'=>count($hints)>0&&count($hints)===count($anexHints),'search_contract'=>['departureId'=>$departureId,'countryId'=>$countryId,'date'=>$date,'nights'=>7,'adults'=>2,'operatorId'=>$operatorId]]);
} catch(Throwable $e) {
    mtv5_fail('probe','probe_exception',['exception_class'=>get_class($e),'message'=>substr((string)$e->getMessage(),0,160)]);
}
