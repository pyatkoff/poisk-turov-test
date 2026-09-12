<?php
declare(strict_types=1);

/** MATCH #1971: ANEX + Andromeda supplier-first evidence retaining order and price-like normalized fields. */
const M15_ANDROMEDA_OPERATOR = '5';
const M15_PAGE_CAP = 100;
const M15_HOTEL_CAP = 5000;

function m15_env(string $k,string $default=''): string { $v=getenv($k); return $v===false?$default:trim((string)$v); }
function m15_text($v,int $max=180): string { if(!is_string($v))return ''; $v=trim((string)(preg_replace('/\s+/u',' ',$v)??'')); return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max); }
function m15_norm(string $v,bool $generic=false): string {
    $v=str_replace(['Ё','ё'],['Е','е'],$v);$v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);
    $v=(string)(preg_replace('/\bex\.?\s*/iu',' ',$v)??$v);$v=(string)(preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v);
    $parts=array_values(array_filter(preg_split('/\s+/u',trim($v))?:[],static fn($x)=>$x!==''));
    if($generic)$parts=array_values(array_filter($parts,static fn($x)=>!in_array($x,['hotel','resort','spa'],true)));
    return implode(' ',$parts);
}
function m15_aliases(string $name): array {
    $raw=[$name,(string)(preg_replace('/\s*\([^)]*\)\s*/u',' ',$name)??$name)];
    if(preg_match_all('/\((?:EX\.?\s*)?([^)]{2,100})\)/iu',$name,$m))foreach($m[1] as $x)$raw[]=trim((string)$x);
    $out=[];foreach($raw as $x)foreach([false,true] as $g){$k=m15_norm((string)$x,$g);if($k!=='')$out[$k]=true;}return array_keys($out);
}
function m15_flatten_selected($v,string $prefix='',int $depth=0): array {
    if($depth>4||!is_array($v))return [];$out=[];
    foreach($v as $k=>$x){$key=(string)$k;$path=$prefix===''?$key:$prefix.'.'.$key;$lk=strtolower($key);
        if(is_array($x)){$out+=m15_flatten_selected($x,$path,$depth+1);continue;}
        if(!is_scalar($x)||$x==='')continue;
        if(preg_match('/price|cost|amount|currency|room|meal|operator|board/i',$lk))$out[$path]=is_string($x)?m15_text($x,120):$x;
    }
    return $out;
}
function m15_offer_evidence(array $o): array {
    $f=m15_flatten_selected($o);$prices=[];$curr=[];$rooms=[];$meals=[];$ops=[];
    foreach($f as $path=>$v){$lp=strtolower($path);
        if(preg_match('/price|cost|amount/',$lp)&&is_numeric($v))$prices[]=(float)$v;
        if(str_contains($lp,'currency'))$curr[]=(string)$v;
        if(str_contains($lp,'room'))$rooms[]=(string)$v;
        if(str_contains($lp,'meal')||str_contains($lp,'board'))$meals[]=(string)$v;
        if(str_contains($lp,'operator'))$ops[]=(string)$v;
    }
    $uniq=static fn(array $a)=>array_values(array_slice(array_unique(array_filter($a,static fn($x)=>(string)$x!=='')),0,8));
    $prices=array_values(array_unique($prices));sort($prices,SORT_NUMERIC);
    return ['price_candidates'=>array_slice($prices,0,8),'currency_candidates'=>$uniq($curr),'room_candidates'=>$uniq($rooms),'meal_candidates'=>$uniq($meals),'operator_candidates'=>$uniq($ops),'selected_fields'=>array_slice($f,0,24,true)];
}
function m15_name_score(string $a,string $b): float {
    $a=m15_norm($a,true);$b=m15_norm($b,true);if($a===''||$b==='')return 0.0;if($a===$b)return 1.0;
    $ta=array_values(array_unique(preg_split('/\s+/u',$a)?:[]));$tb=array_values(array_unique(preg_split('/\s+/u',$b)?:[]));
    $inter=count(array_intersect($ta,$tb));$union=count(array_unique(array_merge($ta,$tb)));$jac=$union?($inter/$union):0.0;
    similar_text($a,$b,$pct);return max($jac,$pct/100.0);
}
function m15_positional_pairs(array $anex,array $andr,int $window=5): array {
    $bestA=[];$bestD=[];
    foreach($anex as $ai=>$a){foreach($andr as $di=>$d){$delta=abs(($a['position']??999999)-($d['position']??-999999));if($delta>$window)continue;$score=m15_name_score((string)$a['name'],(string)$d['name']);if($score<0.55)continue;$cand=['ai'=>$ai,'di'=>$di,'score'=>$score,'delta'=>$delta];if(!isset($bestA[$ai])||$score>$bestA[$ai]['score']||($score===$bestA[$ai]['score']&&$delta<$bestA[$ai]['delta']))$bestA[$ai]=$cand;if(!isset($bestD[$di])||$score>$bestD[$di]['score']||($score===$bestD[$di]['score']&&$delta<$bestD[$di]['delta']))$bestD[$di]=$cand;}}
    $rows=[];foreach($bestA as $ai=>$c){$di=$c['di'];if(($bestD[$di]['ai']??null)!==$ai)continue;$a=$anex[$ai];$d=$andr[$di];$rows[]=['anex'=>['id'=>$a['id'],'name'=>$a['name'],'position'=>$a['position'],'price_candidates'=>$a['price_candidates'],'currency_candidates'=>$a['currency_candidates']],'andromeda'=>['id'=>$d['id'],'namespace'=>$d['namespace'],'name'=>$d['name'],'position'=>$d['position'],'price_candidates'=>$d['price_candidates'],'currency_candidates'=>$d['currency_candidates']],'position_delta'=>$d['position']-$a['position'],'name_score'=>round($c['score'],4)];}
    return $rows;
}
function m15_exact_pairs(array $anex,array $andr): array {
    $ia=[];$id=[];foreach($anex as $i=>$h)foreach($h['aliases'] as $k)$ia[$k][]=$i;foreach($andr as $i=>$h)foreach($h['aliases'] as $k)$id[$k][]=$i;
    $seen=[];$rows=[];foreach(array_intersect(array_keys($ia),array_keys($id)) as $k){$aa=array_values(array_unique($ia[$k]));$dd=array_values(array_unique($id[$k]));if(count($aa)!==1||count($dd)!==1)continue;$key=$aa[0].'|'.$dd[0];if(isset($seen[$key]))continue;$seen[$key]=true;$a=$anex[$aa[0]];$d=$andr[$dd[0]];$rows[]=['alias_key'=>$k,'anex'=>['id'=>$a['id'],'name'=>$a['name'],'position'=>$a['position'],'price_candidates'=>$a['price_candidates'],'currency_candidates'=>$a['currency_candidates']],'andromeda'=>['id'=>$d['id'],'namespace'=>$d['namespace'],'name'=>$d['name'],'position'=>$d['position'],'price_candidates'=>$d['price_candidates'],'currency_candidates'=>$d['currency_candidates']]];}return $rows;
}
function m15_fail(string $stage,string $reason,array $extra=[]): void { echo 'M15_JSON:'.json_encode(array_merge(['status'=>'failed','stage'=>$stage,'reason'=>$reason,'operation_id'=>m15_env('M15_OPERATION_ID'),'tourvisor_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true],$extra),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(2); }

try{
    ini_set('display_errors','0');ini_set('log_errors','0');
    $op=m15_env('M15_OPERATION_ID');$countryId=(int)m15_env('M15_COUNTRY_ID');$countryName=m15_env('M15_COUNTRY_NAME');$date=m15_env('M15_DATE');$nights=(int)m15_env('M15_NIGHTS');
    if($op===''||$countryId<=0||$countryName===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$nights<1||$nights>30)m15_fail('input','invalid_criteria');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')m15_fail('bootstrap','server_root_invalid');
    $preview=$root.'/_preview/search3-anex-candidate';$andrApi=$preview.'/api-andromeda-search3-preview.php';if(!is_file($andrApi))m15_fail('bootstrap','andromeda_runtime_missing');require_once $andrApi;
    $anexPrivate=$preview.'/.anex-private.php';if(is_file($anexPrivate))require_once $anexPrivate;$andrPrivate=$preview.'/.andromeda-private.php';if(!is_file($andrPrivate))m15_fail('bootstrap','andromeda_private_missing');$andromedaConfig=require $andrPrivate;
    require_once $root.'/config.php';$dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbHelper;
    $app=is_file($preview.'/app/integrations/anex-search.php')?$preview.'/app/integrations':$root.'/app/integrations';foreach(['anex-search','anex-search-mapping-registry'] as $f)require_once $app.'/'.$f.'.php';$pdo=v2_data_db();
    $criteria=['departureId'=>1,'countryId'=>$countryId,'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>$nights,'nightsTo'=>$nights,'adults'=>2,'childs'=>[],'currency'=>'RUB','meal'=>'','hotelIds'=>[],'regionIds'=>[],'subregionIds'=>[],'arrivalId'=>'','operatorIds'=>[],'hotelServices'=>[],'hotelTypes'=>[],'onlyDirect'=>false,'onlyCharter'=>false,'hotelCategory'=>'','hotelRating'=>'','priceFrom'=>'','priceTo'=>''];
    $anexToken=trim((string)getenv('ANEX_API_TOKEN'));if($anexToken===''&&defined('ANEX_API_TOKEN'))$anexToken=trim((string)ANEX_API_TOKEN);if($anexToken==='')m15_fail('anex','credential_missing');$anexClient=new AnyTourAnexClient($anexToken);$cache=[];$core=anytour_anex_search3_core($criteria);
    $lookup=$pdo->prepare('SELECT d.name AS departure_name,c.name AS country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');$lookup->execute([1,$countryId]);$names=$lookup->fetch(PDO::FETCH_ASSOC);if(!$names)m15_fail('anex','local_dictionary_names_missing');
    $core['supplier_namespace']='anex_online';$core['departure_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_TOWNFROMS',[],$cache),[$names['departure_name']]);$core['destination_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_STATES',['TOWNFROMINC'=>$core['departure_id']],$cache),[$names['country_name']]);
    $dated=['TOWNFROMINC'=>$core['departure_id'],'STATEINC'=>$core['destination_id'],'CHECKIN_BEG'=>str_replace('-','',$core['checkin_begin']),'CHECKIN_END'=>str_replace('-','',$core['checkin_end']),'ADULT'=>2,'CHILD'=>0];$core['currency_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
    $resolver=AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver();$anexResult=anytour_anex_search3_prices($anexClient,$resolver,$core);$anex=[];$pos=0;
    foreach($anexResult['offers'] as $o){$pos++;$h=$o['hotel']??[];$id=(string)($h['external_id']??'');$name=m15_text($h['name']??'');if($id===''||$name==='')continue;$ev=m15_offer_evidence($o);if(!isset($anex[$id]))$anex[$id]=array_merge(['id'=>$id,'name'=>$name,'local_id'=>$h['local_id']??null,'position'=>$pos,'offer_count'=>0,'aliases'=>m15_aliases($name)],$ev);$anex[$id]['offer_count']++;if(count($anex)>M15_HOTEL_CAP)m15_fail('anex','hotel_cap_exceeded');}

    $request=['generation'=>1,'page'=>1,'params'=>$criteria,'andromeda_operator_ids'=>[M15_ANDROMEDA_OPERATOR]];$saved=anytour_andromeda_search3_catalog($andromedaConfig,$request);$saved['excluded_operator_ids']=$andromedaConfig['excluded_operator_ids']??[];$baseParams=anytour_andromeda_search3_params($request,$pdo,$saved);$budgetDir=dirname((string)$andromedaConfig['catalog_path']);$transport=new AnyTourAndromedaTransport(true);
    $makeClient=static function()use($transport,$budgetDir){return new AnyTourAndromedaClient(static function($url,$options)use($transport,$budgetDir){anytour_andromeda_search3_budget($budgetDir);return $transport($url,$options);},true);};$client=$makeClient();$client->login((string)$andromedaConfig['username'],(string)$andromedaConfig['password']);$privateSession=$client->privateSession();if(!$privateSession)m15_fail('andromeda','private_session_missing');
    $andr=[];$page=1;$pagesExpected=1;$offerPos=0;$pageStats=[];$rejected=0;$offerCount=0;do{if($page>M15_PAGE_CAP)m15_fail('andromeda','page_cap_exceeded');$params=$baseParams;$params['PAGE']=$page;if($page===1){$pageClient=$client;}else{$pageClient=$makeClient();$pageClient->restorePrivateSession($privateSession);} $raw=$pageClient->price($params);$projection=AnyTourAndromedaNormalizer::page($raw,$params,'match-price-position-v15',1);$pagesExpected=(int)$projection['pages_count'];$rejected+=count($projection['rejected']??[]);$offerCount+=count($projection['offers']);foreach($projection['offers'] as $o){$offerPos++;$id=(string)($o['external_hotel_id']??'');$name=m15_text($o['hotel']??'');$ns=m15_text($o['supplier_namespace']??'',80);if($id===''||$name==='')continue;$key=$ns.':'.$id;$ev=m15_offer_evidence($o);if(!isset($andr[$key]))$andr[$key]=array_merge(['id'=>$id,'namespace'=>$ns,'name'=>$name,'operator'=>m15_text($o['operator']??'',80),'local_id'=>$o['local_id']??$o['hotel_local_id']??$o['local_hotel_id']??null,'position'=>$offerPos,'offer_count'=>0,'aliases'=>m15_aliases($name)],$ev);$andr[$key]['offer_count']++;if(count($andr)>M15_HOTEL_CAP)m15_fail('andromeda','hotel_cap_exceeded');}$pageStats[]=['page'=>$page,'pages_count'=>$pagesExpected,'offers'=>count($projection['offers']),'unique_hotels_union'=>count($andr)];$page++;}while($page<=$pagesExpected);
    $aa=array_values($anex);$dd=array_values($andr);$exact=m15_exact_pairs($aa,$dd);$pospairs=m15_positional_pairs($aa,$dd,5);
    $result=['status'=>'completed','operation_id'=>$op,'criteria'=>['departure'=>'Moscow','country_id'=>$countryId,'country'=>$countryName,'date'=>$date,'nights'=>$nights,'adults'=>2,'currency'=>'RUB'],'providers'=>['anex'=>['hotel_count'=>count($aa),'offer_count'=>count($anexResult['offers']),'hotels'=>$aa],'andromeda'=>['hotel_count'=>count($dd),'offer_count'=>$offerCount,'pages'=>$pageStats,'hotels'=>$dd]],'comparison'=>['exact_alias_pairs'=>['count'=>count($exact),'rows'=>$exact],'mutual_positional_pairs'=>['window'=>5,'count'=>count($pospairs),'rows'=>$pospairs]],'tourvisor_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'raw_provider_bodies_recorded'=>false,'no_replay'=>true];
    echo 'M15_JSON:'.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){m15_fail('runtime','provider_exception',['exception_class'=>get_class($e),'safe_message'=>m15_text($e->getMessage(),160)]);}
