<?php
declare(strict_types=1);

/** MATCH #1971: one synchronized read-only hotel identity pilot across TV ANEX-only, direct ANEX and Andromeda ANEX. */
const M111_OPERATION = 'hotel-match-three-provider-111-pilot-1971-20260911-v1';
const M111_DATE = '2026-09-25';
const M111_NIGHTS = 8;

function m111_fail(string $stage, string $reason, array $extra=[]): void {
    echo 'MATCH111_JSON:'.json_encode(array_merge([
        'status'=>'failed','stage'=>$stage,'reason'=>$reason,'operation_id'=>M111_OPERATION,
        'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'continue_calls'=>0,
        'token_values_recorded'=>false,'raw_provider_bodies_recorded'=>false,
    ],$extra),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    exit(2);
}
function m111_text($v,int $max=180): string {
    if(!is_string($v))return '';$v=trim(preg_replace('/\s+/u',' ',$v)??'');
    return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);
}
function m111_norm(string $v,bool $generic=false): string {
    $v=str_replace(['Ё','ё'],['Е','е'],$v);
    $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);
    $v=preg_replace('/\bex\.?\s*/iu',' ',$v)??$v;
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    $parts=array_values(array_filter(preg_split('/\s+/u',trim($v))?:[],static fn($x)=>$x!==''));
    if($generic)$parts=array_values(array_filter($parts,static fn($x)=>!in_array($x,['hotel','resort','spa'],true)));
    sort($parts,SORT_STRING);return implode(' ',$parts);
}
function m111_aliases(string $name): array {
    $raw=[$name];
    if(preg_match_all('/\((?:EX\.?\s*)?([^)]{2,100})\)/iu',$name,$m))foreach($m[1] as $x)$raw[]=trim($x);
    $raw[]=preg_replace('/\s*\([^)]*\)\s*/u',' ',$name)??$name;
    $out=[];foreach($raw as $x)foreach([false,true] as $g){$k=m111_norm((string)$x,$g);if($k!=='')$out[$k]=true;}
    return array_keys($out);
}
function m111_rows(array $data): array {
    if(array_is_list($data))return array_values(array_filter($data,'is_array'));
    foreach(['hotels','items','results','data'] as $k)if(is_array($data[$k]??null)){ $r=m111_rows($data[$k]); if($r!==[])return $r; }
    return [];
}
function m111_complete(array $data): bool {
    if((int)($data['progress']??0)>=100)return true;
    if(in_array(strtolower((string)($data['status']??'')),['complete','completed','done','ready'],true))return true;
    foreach($data as $v)if(is_array($v)&&m111_complete($v))return true;
    return false;
}
function m111_unique_hotels(array $rows,string $idKey,string $nameKey,array $extraKeys=[]): array {
    $out=[];
    foreach($rows as $r){if(!is_array($r))continue;$id=(string)($r[$idKey]??'');$name=m111_text($r[$nameKey]??'');if($id===''||$name==='')continue;
        $key=$id; if(isset($out[$key]))continue;
        $x=['id'=>$id,'name'=>$name,'aliases'=>m111_aliases($name)];
        foreach($extraKeys as $outKey=>$sourceKey){$v=$r[$sourceKey]??null;if(is_scalar($v)||$v===null)$x[$outKey]=$v;}
        $out[$key]=$x;if(count($out)>=500)break;
    }
    return array_values($out);
}
function m111_compare(array $providers): array {
    $sets=[];
    foreach($providers as $name=>$hotels){$set=[];foreach($hotels as $h)foreach($h['aliases']??[] as $k)$set[$k]=true;$sets[$name]=$set;}
    $names=array_keys($sets);$pair=[];
    for($i=0;$i<count($names);$i++)for($j=$i+1;$j<count($names);$j++){
        $keys=array_values(array_intersect(array_keys($sets[$names[$i]]),array_keys($sets[$names[$j]])));sort($keys,SORT_STRING);
        $pair[$names[$i].'_x_'.$names[$j]]=['count'=>count($keys),'sample'=>array_slice($keys,0,20)];
    }
    $triple=$sets[$names[0]]??[];foreach(array_slice($names,1) as $n)$triple=array_intersect_key($triple,$sets[$n]);$keys=array_keys($triple);sort($keys,SORT_STRING);
    return ['pairwise'=>$pair,'triple_name_keys'=>['count'=>count($keys),'sample'=>array_slice($keys,0,30)],'interpretation'=>'name-key overlap is diagnostic evidence only; it does not create hotel mappings'];
}

try {
    ini_set('display_errors','0');ini_set('log_errors','0');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')m111_fail('bootstrap','server_root_invalid');
    $preview=$root.'/_preview/search3-anex-candidate';
    $anexApi=$preview.'/api-anex-search3-preview.php';$andrApi=$preview.'/api-andromeda-search3-preview.php';
    if(!is_file($anexApi)||!is_file($andrApi))m111_fail('bootstrap','preview_runtime_missing');
    require_once $andrApi; // also loads ANEX API boundary + Andromeda client/transport/normalizer.
    $anexPrivate=$preview.'/.anex-private.php';if(is_file($anexPrivate))require_once $anexPrivate;
    $andrPrivate=$preview.'/.andromeda-private.php';if(!is_file($andrPrivate))m111_fail('bootstrap','andromeda_private_missing');
    $andromedaConfig=require $andrPrivate;if(!is_array($andromedaConfig))m111_fail('bootstrap','andromeda_config_invalid');
    require_once $root.'/config.php';
    $dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbHelper;
    $app=is_file($preview.'/app/integrations/anex-search.php')?$preview.'/app/integrations':$root.'/app/integrations';
    foreach(['anex-search','anex-search-mapping-registry'] as $f)require_once $app.'/'.$f.'.php';
    $tvClient=is_file($root.'/data/tourvisor-client-v1.php')?$root.'/data/tourvisor-client-v1.php':$root.'/v2/data/tourvisor-client-v1.php';require_once $tvClient;
    $pdo=v2_data_db();
    $criteria=['departureId'=>1,'countryId'=>4,'dateFrom'=>M111_DATE,'dateTo'=>M111_DATE,'nightsFrom'=>M111_NIGHTS,'nightsTo'=>M111_NIGHTS,'adults'=>2,'childs'=>[],'currency'=>'RUB','meal'=>'','hotelIds'=>[],'regionIds'=>[],'subregionIds'=>[],'arrivalId'=>'','operatorIds'=>[],'hotelServices'=>[],'hotelTypes'=>[],'onlyDirect'=>false,'onlyCharter'=>false,'hotelCategory'=>'','hotelRating'=>'','priceFrom'=>'','priceTo'=>''];
    $result=['status'=>'completed','operation_id'=>M111_OPERATION,'criteria'=>['departure'=>'Moscow','country'=>'Turkey','date'=>M111_DATE,'nights'=>M111_NIGHTS,'adults'=>2,'children'=>0,'currency'=>'RUB','tourvisor_operator_id'=>13,'andromeda_operator_id'=>5],
        'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'continue_calls'=>0,'token_values_recorded'=>false,'raw_provider_bodies_recorded'=>false,'providers'=>[]];

    // 1) Tourvisor, separate ANEX credential and explicit ANEX operator filter.
    if(!defined('TOURVISOR_ANEX_JWT'))m111_fail('tourvisor','credential_missing');
    $tvToken=trim((string)constant('TOURVISOR_ANEX_JWT'));if(stripos($tvToken,'Bearer ')===0)$tvToken=trim(substr($tvToken,7));if($tvToken==='')m111_fail('tourvisor','credential_empty');
    putenv('TOURVISOR_JWT='.$tvToken);
    $start=v2_data_tv_get('/tours/search',['departureId'=>1,'countryId'=>4,'dateFrom'=>M111_DATE,'dateTo'=>M111_DATE,'nightsFrom'=>M111_NIGHTS,'nightsTo'=>M111_NIGHTS,'adults'=>2,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,'operatorIds'=>[13]]);
    $searchId=(int)($start['searchId']??$start['id']??0);if($searchId<=0)m111_fail('tourvisor','search_id_missing');
    $polls=0;$done=false;for($i=0;$i<20;$i++){if($i)usleep(750000);$s=v2_data_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false]);$polls++;if(m111_complete($s)){$done=true;break;}}
    if(!$done)m111_fail('tourvisor','search_not_complete',['tourvisor_status_polls'=>$polls]);
    $tvRaw=v2_data_tv_get('/tours/search/'.$searchId,['limit'=>300]);$tvRows=m111_rows($tvRaw);$tvHotels=m111_unique_hotels($tvRows,'id','name');
    $result['providers']['tourvisor']=['status'=>'completed','operator_id'=>13,'hotel_count'=>count($tvHotels),'status_polls'=>$polls,'hotels'=>$tvHotels];

    // 2) Direct ANEX SearchTour, same date/night/party; no HOTELLIST narrowing.
    $anexToken=trim((string)getenv('ANEX_API_TOKEN'));if($anexToken===''&&defined('ANEX_API_TOKEN'))$anexToken=trim((string)ANEX_API_TOKEN);if($anexToken==='')m111_fail('anex','credential_missing');
    $anexClient=new AnyTourAnexClient($anexToken);$cache=[];$core=anytour_anex_search3_core($criteria);
    $lookup=$pdo->prepare('SELECT d.name AS departure_name,c.name AS country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');$lookup->execute([1,4]);$names=$lookup->fetch(PDO::FETCH_ASSOC);if(!$names)m111_fail('anex','local_dictionary_names_missing');
    $core['supplier_namespace']='anex_online';
    $core['departure_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_TOWNFROMS',[],$cache),[$names['departure_name']]);
    $core['destination_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_STATES',['TOWNFROMINC'=>$core['departure_id']],$cache),[$names['country_name']]);
    $dated=['TOWNFROMINC'=>$core['departure_id'],'STATEINC'=>$core['destination_id'],'CHECKIN_BEG'=>str_replace('-','',$core['checkin_begin']),'CHECKIN_END'=>str_replace('-','',$core['checkin_end']),'ADULT'=>2,'CHILD'=>0];
    $core['currency_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
    $resolver=AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver();$anexResult=anytour_anex_search3_prices($anexClient,$resolver,$core);
    $a=[];foreach($anexResult['offers'] as $o){$h=$o['hotel']??[];$id=(string)($h['external_id']??'');$name=m111_text($h['name']??'');if($id===''||$name===''||isset($a[$id]))continue;$a[$id]=['id'=>$id,'name'=>$name,'local_id'=>$h['local_id']??null,'aliases'=>m111_aliases($name)];}
    $anexHotels=array_values($a);$result['providers']['anex']=['status'=>'completed','hotel_count'=>count($anexHotels),'external_search_pending'=>(bool)($anexResult['external_search_pending']??false),'rejected_count'=>(int)($anexResult['rejected_count']??0),'hotels'=>$anexHotels];

    // 3) Andromeda ANEX operator only, same date/night/party, page 1 only. Reserve account budget per actual login/price request.
    $request=['generation'=>1,'page'=>1,'params'=>$criteria,'andromeda_operator_ids'=>['5']];$saved=anytour_andromeda_search3_catalog($andromedaConfig,$request);$saved['excluded_operator_ids']=$andromedaConfig['excluded_operator_ids']??[];
    $andrParams=anytour_andromeda_search3_params($request,$pdo,$saved);$transport=new AnyTourAndromedaTransport(true);
    $budgetDir=dirname((string)$andromedaConfig['catalog_path']);
    $andrClient=new AnyTourAndromedaClient(static function($url,$options)use($transport,$budgetDir){anytour_andromeda_search3_budget($budgetDir);return $transport($url,$options);},true);
    $andrClient->login((string)$andromedaConfig['username'],(string)$andromedaConfig['password']);$andrRaw=$andrClient->price($andrParams);
    $andrPage=AnyTourAndromedaNormalizer::page($andrRaw,$andrParams,'match111',1);$d=[];foreach($andrPage['offers'] as $o){$id=(string)($o['external_hotel_id']??'');$name=m111_text($o['hotel']??'');$ns=m111_text($o['supplier_namespace']??'',80);if($id===''||$name==='')continue;$key=$ns.':'.$id;if(isset($d[$key]))continue;$d[$key]=['id'=>$id,'namespace'=>$ns,'name'=>$name,'operator'=>m111_text($o['operator']??'',80),'aliases'=>m111_aliases($name)];}
    $andrHotels=array_values($d);$result['providers']['andromeda']=['status'=>$andrPage['status'],'operator_id'=>5,'page'=>(int)$andrPage['page'],'pages_count'=>(int)$andrPage['pages_count'],'hotel_count'=>count($andrHotels),'rejected_count'=>count($andrPage['rejected']??[]),'hotels'=>$andrHotels];

    $result['comparison']=m111_compare(['tourvisor'=>$tvHotels,'anex'=>$anexHotels,'andromeda'=>$andrHotels]);
    $result['provider_calls']=['tourvisor'=>3+$polls,'anex'=>4,'andromeda'=>2];
    echo 'MATCH111_JSON:'.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) {
    m111_fail('runtime','provider_exception',['exception_class'=>get_class($e),'safe_message'=>m111_text($e->getMessage(),160)]);
}
