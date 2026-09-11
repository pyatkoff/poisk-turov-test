<?php
declare(strict_types=1);

/** MATCH #1971: prefilter Tourvisor work using direct ANEX + Andromeda only. */
const M14_OPERATION='hotel-match-anex-andromeda-prefilter-1971-20260911-v14';
const M14_ANDROMEDA_OPERATOR='5';
const M14_PAGE_CAP=100;
const M14_HOTEL_CAP=5000;

function m14_text($v,int $max=180): string { if(!is_string($v))return ''; $v=trim((string)(preg_replace('/\s+/u',' ',$v)??'')); return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max); }
function m14_norm(string $v,bool $generic=false): string { $v=str_replace(['Ё','ё'],['Е','е'],$v); $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v); $v=(string)(preg_replace('/\bex\.?\s*/iu',' ',$v)??$v); $v=(string)(preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v); $parts=array_values(array_filter(preg_split('/\s+/u',trim($v))?:[],static fn($x)=>$x!=='')); if($generic)$parts=array_values(array_filter($parts,static fn($x)=>!in_array($x,['hotel','resort','spa'],true))); sort($parts,SORT_STRING); return implode(' ',$parts); }
function m14_aliases(string $name): array { $raw=[$name,(string)(preg_replace('/\s*\([^)]*\)\s*/u',' ',$name)??$name)]; if(preg_match_all('/\((?:EX\.?\s*)?([^)]{2,100})\)/iu',$name,$m))foreach($m[1] as $x)$raw[]=trim((string)$x); $out=[]; foreach($raw as $x)foreach([false,true] as $g){$k=m14_norm((string)$x,$g);if($k!=='')$out[$k]=true;} return array_keys($out); }
function m14_pairs(array $anex,array $andr): int { $ai=[];$di=[]; foreach($anex as $k=>$h)foreach($h['aliases'] as $a)$ai[$a][]=$k; foreach($andr as $k=>$h)foreach($h['aliases'] as $a)$di[$a][]=$k; $seen=[]; foreach(array_intersect(array_keys($ai),array_keys($di)) as $a){$x=array_values(array_unique($ai[$a]));$y=array_values(array_unique($di[$a]));if(count($x)===1&&count($y)===1)$seen[$x[0].'|'.$y[0]]=true;} return count($seen); }
function m14_fail(string $stage,string $reason,array $extra=[]): void { echo 'MATCH14_JSON:'.json_encode(array_merge(['status'=>'failed','stage'=>$stage,'reason'=>$reason,'operation_id'=>M14_OPERATION,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0],$extra),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL; exit(2); }

try {
    ini_set('display_errors','0'); ini_set('log_errors','0');
    $root=realpath(getcwd()); if(!$root||basename($root)!=='anytoour.ru')m14_fail('bootstrap','server_root_invalid');
    $preview=$root.'/_preview/search3-anex-candidate'; $andrApi=$preview.'/api-andromeda-search3-preview.php'; if(!is_file($andrApi))m14_fail('bootstrap','andromeda_runtime_missing'); require_once $andrApi;
    $anexPrivate=$preview.'/.anex-private.php'; if(is_file($anexPrivate))require_once $anexPrivate;
    $andrPrivate=$preview.'/.andromeda-private.php'; if(!is_file($andrPrivate))m14_fail('bootstrap','andromeda_private_missing'); $andromedaConfig=require $andrPrivate; if(!is_array($andromedaConfig))m14_fail('bootstrap','andromeda_config_invalid');
    require_once $root.'/config.php'; $dbHelper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php'; require_once $dbHelper;
    $app=is_file($preview.'/app/integrations/anex-search.php')?$preview.'/app/integrations':$root.'/app/integrations'; foreach(['anex-search','anex-search-mapping-registry'] as $f)require_once $app.'/'.$f.'.php';
    $pdo=v2_data_db(); $anexToken=trim((string)getenv('ANEX_API_TOKEN')); if($anexToken===''&&defined('ANEX_API_TOKEN'))$anexToken=trim((string)ANEX_API_TOKEN); if($anexToken==='')m14_fail('anex','credential_missing');
    $anexClient=new AnyTourAnexClient($anexToken); $resolver=AnyTourAnexSearchMappingRegistry::fromPdo($pdo)->previewResolver(); $cache=[];
    $budgetDir=dirname((string)$andromedaConfig['catalog_path']); $transport=new AnyTourAndromedaTransport(true); $makeClient=static function()use($transport,$budgetDir){return new AnyTourAndromedaClient(static function($url,$options)use($transport,$budgetDir){anytour_andromeda_search3_budget($budgetDir);return $transport($url,$options);},true);};
    $login=$makeClient(); $login->login((string)$andromedaConfig['username'],(string)$andromedaConfig['password']); $session=$login->privateSession(); if(!$session)m14_fail('andromeda','private_session_missing');
    $countries=[1=>'Egypt',4=>'Turkey',2=>'Thailand',9=>'UAE',16=>'Vietnam',12=>'Sri Lanka',8=>'Maldives',10=>'Cuba'];
    $dates=['2026-10-16','2026-11-20','2026-12-18']; $rows=[]; $supplierCalls=['anex_slices'=>0,'andromeda_login'=>1,'andromeda_price_pages'=>0];
    $lookup=$pdo->prepare('SELECT d.name AS departure_name,c.name AS country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');
    foreach($countries as $countryId=>$countryLabel){
        foreach($dates as $date){
            $criteria=['departureId'=>1,'countryId'=>$countryId,'dateFrom'=>$date,'dateTo'=>$date,'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>[],'currency'=>'RUB','meal'=>'','hotelIds'=>[],'regionIds'=>[],'subregionIds'=>[],'arrivalId'=>'','operatorIds'=>[],'hotelServices'=>[],'hotelTypes'=>[],'onlyDirect'=>false,'onlyCharter'=>false,'hotelCategory'=>'','hotelRating'=>'','priceFrom'=>'','priceTo'=>''];
            $row=['country_id'=>$countryId,'country'=>$countryLabel,'date'=>$date,'nights'=>7,'anex_hotels'=>0,'andromeda_hotels'=>0,'andromeda_pages'=>0,'alias_pairs'=>0,'tv_candidate'=>false,'state'=>'checked'];
            try {
                $core=anytour_anex_search3_core($criteria); $lookup->execute([1,$countryId]); $names=$lookup->fetch(PDO::FETCH_ASSOC); if(!$names)throw new RuntimeException('local_dictionary_names_missing');
                $core['supplier_namespace']='anex_online'; $core['departure_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_TOWNFROMS',[],$cache),[$names['departure_name']]);
                $core['destination_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_STATES',['TOWNFROMINC'=>$core['departure_id']],$cache),[$names['country_name']]);
                $dated=['TOWNFROMINC'=>$core['departure_id'],'STATEINC'=>$core['destination_id'],'CHECKIN_BEG'=>str_replace('-','',$core['checkin_begin']),'CHECKIN_END'=>str_replace('-','',$core['checkin_end']),'ADULT'=>2,'CHILD'=>0];
                $core['currency_id']=anytour_anex_search3_dictionary_id(anytour_anex_search3_dictionary($anexClient,'SearchTour_CURRENCIES',$dated,$cache),['RUB','RUR','Рубль','Рубли','Руб']);
                $ar=anytour_anex_search3_prices($anexClient,$resolver,$core); $supplierCalls['anex_slices']++; $anex=[]; foreach($ar['offers'] as $o){$h=$o['hotel']??[];$id=(string)($h['external_id']??'');$name=m14_text($h['name']??'');if($id===''||$name===''||isset($anex[$id]))continue;$anex[$id]=['aliases'=>m14_aliases($name)];if(count($anex)>M14_HOTEL_CAP)throw new RuntimeException('anex_hotel_cap');} $row['anex_hotels']=count($anex);
                $request=['generation'=>1,'page'=>1,'params'=>$criteria,'andromeda_operator_ids'=>[M14_ANDROMEDA_OPERATOR]]; $saved=anytour_andromeda_search3_catalog($andromedaConfig,$request); $saved['excluded_operator_ids']=$andromedaConfig['excluded_operator_ids']??[]; $base=anytour_andromeda_search3_params($request,$pdo,$saved);
                $andr=[];$page=1;$pages=1; do { if($page>M14_PAGE_CAP)throw new RuntimeException('andromeda_page_cap'); $client=$makeClient();$client->restorePrivateSession($session);$params=$base;$params['PAGE']=$page;$raw=$client->price($params);$supplierCalls['andromeda_price_pages']++;$projection=AnyTourAndromedaNormalizer::page($raw,$params,'match14',1);$pages=(int)$projection['pages_count'];foreach($projection['offers'] as $o){$id=(string)($o['external_hotel_id']??'');$name=m14_text($o['hotel']??'');$ns=m14_text($o['supplier_namespace']??'',80);if($id===''||$name==='')continue;$k=$ns.':'.$id;if(!isset($andr[$k]))$andr[$k]=['aliases'=>m14_aliases($name)];if(count($andr)>M14_HOTEL_CAP)throw new RuntimeException('andromeda_hotel_cap');}$page++;} while($page<=$pages);
                $row['andromeda_hotels']=count($andr);$row['andromeda_pages']=$pages;$row['alias_pairs']=m14_pairs($anex,$andr);$row['tv_candidate']=count($anex)>0;$row['priority_score']=(count($anex)>0?100000:0)+min(9999,$row['alias_pairs']*100)+min(999,count($anex))+min(999,count($andr));
            } catch(Throwable $e) { $row['state']='supplier_error';$row['safe_error']=m14_text($e->getMessage(),100);$row['tv_candidate']=false;$row['priority_score']=0; }
            $rows[]=$row; usleep(3000000);
        }
    }
    usort($rows,static fn($a,$b)=>($b['priority_score']<=>$a['priority_score'])?:strcmp($a['country'].$a['date'],$b['country'].$b['date']));
    $candidates=array_values(array_filter($rows,static fn($r)=>(bool)$r['tv_candidate']));
    echo 'MATCH14_JSON:'.json_encode(['status'=>'completed','operation_id'=>M14_OPERATION,'rule'=>'Tourvisor candidate only when direct ANEX has inventory; Andromeda overlap ranks priority; supplier errors/empty ANEX are defer-not-negative','rows'=>$rows,'tv_candidate_count'=>count($candidates),'tv_candidate_keys'=>array_map(static fn($r)=>[$r['country_id'],$r['date'],$r['nights'],$r['anex_hotels'],$r['andromeda_hotels'],$r['alias_pairs']],$candidates),'provider_calls'=>$supplierCalls,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,'raw_provider_bodies_recorded'=>false,'token_values_recorded'=>false],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) { m14_fail('runtime','provider_exception',['exception_class'=>get_class($e),'safe_message'=>m14_text($e->getMessage(),120)]); }
