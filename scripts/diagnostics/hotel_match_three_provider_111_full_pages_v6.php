<?php
declare(strict_types=1);

/** MATCH #1971: synchronized three-provider identity evidence without page-1 truncation. */
const M6_OPERATION = 'hotel-match-three-provider-111-full-pages-1971-20260911-v6';
const M6_DATE = '2026-11-06';
const M6_NIGHTS = 7;
const M6_TV_OPERATOR = 13;
const M6_ANDROMEDA_OPERATOR = '5';
const M6_TV_CONTINUE_CAP = 8;
const M6_ANDROMEDA_PAGE_CAP = 100;
const M6_HOTEL_CAP = 5000;

function m6_fail(string $stage,string $reason,array $extra=[]): void {
    echo 'MATCH111FULL_JSON:'.json_encode(array_merge([
        'status'=>'failed','stage'=>$stage,'reason'=>$reason,'operation_id'=>M6_OPERATION,
        'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,
        'token_values_recorded'=>false,'raw_provider_bodies_recorded'=>false,
    ],$extra),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    exit(2);
}
function m6_text($v,int $max=180): string {
    if(!is_string($v))return '';
    $v=trim((string)(preg_replace('/\s+/u',' ',$v)??''));
    return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);
}
function m6_norm(string $v,bool $generic=false): string {
    $v=str_replace(['Ё','ё'],['Е','е'],$v);
    $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);
    $v=(string)(preg_replace('/\bex\.?\s*/iu',' ',$v)??$v);
    $v=(string)(preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v);
    $parts=array_values(array_filter(preg_split('/\s+/u',trim($v))?:[],static fn($x)=>$x!==''));
    if($generic)$parts=array_values(array_filter($parts,static fn($x)=>!in_array($x,['hotel','resort','spa'],true)));
    sort($parts,SORT_STRING);
    return implode(' ',$parts);
}
function m6_aliases(string $name): array {
    $raw=[$name,(string)(preg_replace('/\s*\([^)]*\)\s*/u',' ',$name)??$name)];
    if(preg_match_all('/\((?:EX\.?\s*)?([^)]{2,100})\)/iu',$name,$m))foreach($m[1] as $x)$raw[]=trim((string)$x);
    $out=[];
    foreach($raw as $x)foreach([false,true] as $g){$k=m6_norm((string)$x,$g);if($k!=='')$out[$k]=true;}
    return array_keys($out);
}
function m6_rows(array $data): array {
    if(array_is_list($data))return array_values(array_filter($data,'is_array'));
    foreach(['hotels','items','results','data'] as $k){
        if(is_array($data[$k]??null)){$rows=m6_rows($data[$k]);if($rows!==[])return $rows;}
    }
    return [];
}
function m6_complete(array $data): bool {
    if((int)($data['progress']??0)>=100)return true;
    if(in_array(strtolower(trim((string)($data['status']??''))),['complete','completed','done','ready'],true))return true;
    foreach($data as $v)if(is_array($v)&&m6_complete($v))return true;
    return false;
}
function m6_tv_hotels(array $payload): array {
    $out=[];
    foreach(m6_rows($payload) as $row){
        $id=filter_var($row['id']??null,FILTER_VALIDATE_INT);$name=m6_text($row['name']??'');
        if($id===false||(int)$id<=0||$name==='')continue;
        $out[(string)(int)$id]=['id'=>(string)(int)$id,'name'=>$name,'aliases'=>m6_aliases($name)];
        if(count($out)>M6_HOTEL_CAP)m6_fail('tourvisor','hotel_cap_exceeded');
    }
    return $out;
}
function m6_fetch_tv_results(int $searchId): array {
    $last=null;
    foreach([10000,5000,2000,1000,500,300,100] as $limit){
        try{return ['limit'=>$limit,'payload'=>v2_data_tv_get('/tours/search/'.$searchId,['limit'=>$limit])];}
        catch(Throwable $e){$last=$e;}
    }
    throw new RuntimeException('tourvisor_results_unavailable',0,$last);
}
function m6_poll_tv(int $searchId,int $max=24): int {
    for($i=1;$i<=$max;$i++){
        if($i>1)usleep(750000);
        if(m6_complete(v2_data_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false])))return $i;
    }
    throw new RuntimeException('tourvisor_search_not_complete');
}
function m6_common_triples(array $tv,array $anex,array $andr): array {
    $providers=['tourvisor'=>$tv,'anex'=>$anex,'andromeda'=>$andr];$indexes=[];
    foreach($providers as $p=>$hotels){
        foreach($hotels as $key=>$h)foreach($h['aliases']??[] as $alias)$indexes[$p][$alias][]=$key;
    }
    $keys=array_intersect(array_keys($indexes['tourvisor']??[]),array_keys($indexes['anex']??[]),array_keys($indexes['andromeda']??[]));
    sort($keys,SORT_STRING);$seen=[];$rows=[];
    foreach($keys as $alias){
        $ids=[];$unique=true;
        foreach(array_keys($providers) as $p){$x=array_values(array_unique($indexes[$p][$alias]??[]));if(count($x)!==1){$unique=false;break;}$ids[$p]=$x[0];}
        if(!$unique)continue;
        $dedupe=implode('|',[$ids['tourvisor'],$ids['anex'],$ids['andromeda']]);if(isset($seen[$dedupe]))continue;$seen[$dedupe]=true;
        $rows[]=['alias_key'=>$alias,
            'tourvisor'=>['id'=>$providers['tourvisor'][$ids['tourvisor']]['id'],'name'=>$providers['tourvisor'][$ids['tourvisor']]['name']],
            'anex'=>['id'=>$providers['anex'][$ids['anex']]['id'],'name'=>$providers['anex'][$ids['anex']]['name']],
            'andromeda'=>['id'=>$providers['andromeda'][$ids['andromeda']]['id'],'namespace'=>$providers['andromeda'][$ids['andromeda']]['namespace'],'name'=>$providers['andromeda'][$ids['andromeda']]['name']]];
        if(count($rows)>=1000)break;
    }
    return $rows;
}

try {
    ini_set('display_errors','0');ini_set('log_errors','0');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')m6_fail('bootstrap','server_root_invalid');
    $preview=$root.'/_preview/search3-anex-candidate';$andrApi=$preview.'/api-andromeda-search3-preview.php';
    if(!is_file($andrApi))m6_fail('bootstrap','andromeda_runtime_missing');require_once $andrApi;
    $anexPrivate=$preview.'/.anex-private.php';if(is_file($anexPrivate))require_once $anexPrivate;
    $andrPrivate=$preview.'/.andromeda-private.php';if(!is_file($andrPrivate))m6_fail('bootstrap','andromeda_private_missing');
    $andromedaConfig=require $andrPrivate;if(!is_array($andromedaConfig))m6_fail('bootstrap','andromeda_config_invalid');
    require_once $root.'/config.php';
    $dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbHelper;
    $app=is_file($preview.'/app/integrations/anex-search.php')?$preview.'/app/integrations':$root.'/app/integrations';
    foreach(['anex-search','anex-search-mapping-registry'] as $f)require_once $app.'/'.$f.'.php';
    $tvClient=is_file($root.'/data/tourvisor-client-v1.php')?$root.'/data/tourvisor-client-v1.php':$root.'/v2/data/tourvisor-client-v1.php';require_once $tvClient;
    $pdo=v2_data_db();
    $criteria=['departureId'=>1,'countryId'=>4,'dateFrom'=>M6_DATE,'dateTo'=>M6_DATE,'nightsFrom'=>M6_NIGHTS,'nightsTo'=>M6_NIGHTS,'adults'=>2,'childs'=>[],'currency'=>'RUB','meal'=>'','hotelIds'=>[],'regionIds'=>[],'subregionIds'=>[],'arrivalId'=>'','operatorIds'=>[],'hotelServices'=>[],'hotelTypes'=>[],'onlyDirect'=>false,'onlyCharter'=>false,'hotelCategory'=>'','hotelRating'=>'','priceFrom'=>'','priceTo'=>''];
    $result=['status'=>'completed','operation_id'=>M6_OPERATION,'criteria'=>['departure'=>'Moscow','country'=>'Turkey','date'=>M6_DATE,'nights'=>M6_NIGHTS,'adults'=>2,'children'=>0,'currency'=>'RUB','tourvisor_operator_id'=>M6_TV_OPERATOR,'andromeda_operator_id'=>(int)M6_ANDROMEDA_OPERATOR],
        'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'token_values_recorded'=>false,'raw_provider_bodies_recorded'=>false,'providers'=>[]];

    // Tourvisor: high-limit cached result read + bounded explicit continue rounds until the hotel set stops growing.
    if(!defined('TOURVISOR_ANEX_JWT'))m6_fail('tourvisor','credential_missing');
    $tvToken=trim((string)constant('TOURVISOR_ANEX_JWT'));if(stripos($tvToken,'Bearer ')===0)$tvToken=trim(substr($tvToken,7));if($tvToken==='')m6_fail('tourvisor','credential_empty');putenv('TOURVISOR_JWT='.$tvToken);
    $start=v2_data_tv_get('/tours/search',['departureId'=>1,'countryId'=>4,'dateFrom'=>M6_DATE,'dateTo'=>M6_DATE,'nightsFrom'=>M6_NIGHTS,'nightsTo'=>M6_NIGHTS,'adults'=>2,'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,'operatorIds'=>[M6_TV_OPERATOR]]);
    $searchId=(int)($start['searchId']??$start['id']??0);if($searchId<=0)m6_fail('tourvisor','search_id_missing');
    $statusPolls=m6_poll_tv($searchId);$fetched=m6_fetch_tv_results($searchId);$tv=m6_tv_hotels($fetched['payload']);$tvRounds=[['round'=>0,'limit'=>$fetched['limit'],'unique_hotels'=>count($tv)]];$continueCalls=0;$tvStop='continue_cap';
    for($round=1;$round<=M6_TV_CONTINUE_CAP;$round++){
        $before=count($tv);
        try{v2_data_tv_get('/tours/search/'.$searchId.'/continue');$continueCalls++;}
        catch(Throwable $e){$tvStop='continue_unavailable_after_complete';break;}
        $statusPolls+=m6_poll_tv($searchId);$next=m6_fetch_tv_results($searchId);$chunk=m6_tv_hotels($next['payload']);foreach($chunk as $id=>$h)$tv[$id]=$h;
        $tvRounds[]=['round'=>$round,'limit'=>$next['limit'],'returned_unique'=>count($chunk),'union_unique'=>count($tv)];
        if(count($tv)===$before){$tvStop='no_growth_after_continue';break;}
        if(count($tv)>M6_HOTEL_CAP)m6_fail('tourvisor','hotel_cap_exceeded');
    }
    $result['providers']['tourvisor']=['status'=>'completed','operator_id'=>M6_TV_OPERATOR,'hotel_count'=>count($tv),'initial_result_limit'=>$fetched['limit'],'status_polls'=>$statusPolls,'continue_calls'=>$continueCalls,'stop_reason'=>$tvStop,'rounds'=>$tvRounds,'hotels'=>array_values($tv)];

    // Direct ANEX anchor on the exact same day/night/party. Existing API returns one bounded SearchTour payload.
    $anexToken=trim((string)getenv('ANEX_API_TOKEN'));if($anexToken===''&&defined('ANEX_API_TOKEN'))$anexToken=trim((string)ANEX_API_TOKEN);if($anexToken==='')m6_fail('anex','credential_missing');
    $anexClient=new AnyTourAnexClient($anexToken);$cache=[];$core=anytour_anex_search3_core($criteria);
    $lookup=$pdo->prepare('SELECT d.name AS departure_name,c.name AS country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');$lookup->execute([1,4]);$names=$lookup->fetch(PDO::FETCH_ASSOC);if(!$names)m6_fail('anex','local_dictionary_names_missing');
    $core['supplier_namespace']='anex_online';$core['departure_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_TOWNFROMS',[],$cache),[$names['departure_name']]);
    $core['destination_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_STATES',['TOWNFROMINC'=>$core['departure_id']],$cache),[$names['country_name']]);
    $dated=['TOWNFROMINC'=>$core['departure_id'],'STATEINC'=>$core['destination_id'],'CHECKIN_BEG'=>str_replace('-','',$core['checkin_begin']),'CHECKIN_END'=>str_replace('-','',$core['checkin_end']),'ADULT'=>2,'CHILD'=>0];
    $core['currency_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
    $resolver=AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver();$anexResult=anytour_anex_search3_prices($anexClient,$resolver,$core);$anex=[];
    foreach($anexResult['offers'] as $o){$h=$o['hotel']??[];$id=(string)($h['external_id']??'');$name=m6_text($h['name']??'');if($id===''||$name===''||isset($anex[$id]))continue;$anex[$id]=['id'=>$id,'name'=>$name,'local_id'=>$h['local_id']??null,'aliases'=>m6_aliases($name)];if(count($anex)>M6_HOTEL_CAP)m6_fail('anex','hotel_cap_exceeded');}
    $result['providers']['anex']=['status'=>'completed','hotel_count'=>count($anex),'external_search_pending'=>(bool)($anexResult['external_search_pending']??false),'rejected_count'=>(int)($anexResult['rejected_count']??0),'hotels'=>array_values($anex)];

    // Andromeda: consume PAGE=1 through the latest PAGES_COUNT using the same private supplier sid.
    $request=['generation'=>1,'page'=>1,'params'=>$criteria,'andromeda_operator_ids'=>[M6_ANDROMEDA_OPERATOR]];$saved=anytour_andromeda_search3_catalog($andromedaConfig,$request);$saved['excluded_operator_ids']=$andromedaConfig['excluded_operator_ids']??[];
    $baseParams=anytour_andromeda_search3_params($request,$pdo,$saved);$budgetDir=dirname((string)$andromedaConfig['catalog_path']);$transport=new AnyTourAndromedaTransport(true);
    $makeClient=static function()use($transport,$budgetDir){return new AnyTourAndromedaClient(static function($url,$options)use($transport,$budgetDir){anytour_andromeda_search3_budget($budgetDir);return $transport($url,$options);},true);};
    $client=$makeClient();$client->login((string)$andromedaConfig['username'],(string)$andromedaConfig['password']);$privateSession=$client->privateSession();if(!$privateSession)m6_fail('andromeda','private_session_missing');
    $andr=[];$pageStats=[];$page=1;$pagesExpected=1;$rejected=0;$offerCount=0;
    do{
        if($page>M6_ANDROMEDA_PAGE_CAP)m6_fail('andromeda','page_cap_exceeded',['last_pages_count'=>$pagesExpected]);
        $params=$baseParams;$params['PAGE']=$page;
        if($page===1){$pageClient=$client;}else{$pageClient=$makeClient();$pageClient->restorePrivateSession($privateSession);}
        $raw=$pageClient->price($params);$projection=AnyTourAndromedaNormalizer::page($raw,$params,'match111full',1);$pagesExpected=(int)$projection['pages_count'];$rejected+=count($projection['rejected']??[]);$offerCount+=count($projection['offers']);
        foreach($projection['offers'] as $o){$id=(string)($o['external_hotel_id']??'');$name=m6_text($o['hotel']??'');$ns=m6_text($o['supplier_namespace']??'',80);if($id===''||$name==='')continue;$key=$ns.':'.$id;if(isset($andr[$key]))continue;$andr[$key]=['id'=>$id,'namespace'=>$ns,'name'=>$name,'operator'=>m6_text($o['operator']??'',80),'aliases'=>m6_aliases($name)];if(count($andr)>M6_HOTEL_CAP)m6_fail('andromeda','hotel_cap_exceeded');}
        $pageStats[]=['page'=>$page,'pages_count'=>$pagesExpected,'offers'=>count($projection['offers']),'unique_hotels_union'=>count($andr)];
        $page++;
    }while($page<=$pagesExpected);
    $result['providers']['andromeda']=['status'=>'completed','operator_id'=>(int)M6_ANDROMEDA_OPERATOR,'pages_consumed'=>count($pageStats),'final_pages_count'=>$pagesExpected,'offer_count'=>$offerCount,'hotel_count'=>count($andr),'rejected_count'=>$rejected,'pages'=>$pageStats,'hotels'=>array_values($andr)];

    $triples=m6_common_triples($tv,$anex,$andr);$result['comparison']=['unique_alias_triples'=>['count'=>count($triples),'rows'=>$triples],'interpretation'=>'diagnostic identity evidence only; no mapping is created from availability or price'];
    $result['provider_calls']=['tourvisor_continue'=>$continueCalls,'andromeda_login'=>1,'andromeda_price_pages'=>count($pageStats),'direct_anex_searchtour'=>'one bounded normalized price response plus dictionaries'];
    echo 'MATCH111FULL_JSON:'.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) {
    m6_fail('runtime','provider_exception',['exception_class'=>get_class($e),'safe_message'=>m6_text($e->getMessage(),160)]);
}
