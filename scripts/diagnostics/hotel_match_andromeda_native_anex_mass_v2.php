<?php
declare(strict_types=1);

const HMAN_OP='hotel-match-andromeda-native-anex-mass-1971-20260918-v2';
const HMAN_MAX_CONTEXTS=20;
const HMAN_MAX_CALLS=250;
const HMAN_MAX_PAGES_PER_CONTEXT=40;
const HMAN_ROW_LIMIT=250000;
const HMAN_EXCLUDED_COUNTRIES=[46=>true,47=>true];

function hman_rows(PDO $db,string $sql,array $p=[]):array{
    $s=$db->prepare($sql);$s->execute(array_values($p));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
    if(count($r)>HMAN_ROW_LIMIT)throw new RuntimeException('row_budget');
    return $r;
}
function hman_scalar(mixed $v,int $max=255):string{return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';}
function hman_norm(string $v):string{
    $v=mb_strtolower(trim($v),'UTF-8');
    $v=strtr($v,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ','’'=>"'"]);
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function hman_tokens(string $v):array{
    $drop=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'ex'=>1];
    $o=[];foreach(preg_split('/\s+/u',hman_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[]as$t)if(!isset($drop[$t]))$o[$t]=true;
    return array_keys($o);
}
function hman_nums(string $v):array{$o=[];foreach(hman_tokens($v)as$t)if(preg_match('/^[0-9]+$/D',$t))$o[$t]=true;$x=array_keys($o);sort($x,SORT_STRING);return$x;}
function hman_quals(string $v):array{
    $q=['annex'=>1,'beach'=>1,'garden'=>1,'gardens'=>1,'north'=>1,'south'=>1,'posh'=>1,'adult'=>1,'adults'=>1,'pool'=>1,'sea'=>1,'view'=>1,'prestige'=>1,'aquamarine'=>1,'family'=>1,'deluxe'=>1,'suite'=>1,'club'=>1,'royal'=>1,'premium'=>1,'villas'=>1,'villa'=>1];
    $o=[];foreach(hman_tokens($v)as$t)if(isset($q[$t]))$o[$t]=true;$x=array_keys($o);sort($x,SORT_STRING);return$x;
}
function hman_score(string $a,string $b):int{
    $aa=array_fill_keys(hman_tokens($a),true);$bb=array_fill_keys(hman_tokens($b),true);
    if(!$aa||!$bb)return 0;$c=count(array_intersect_key($aa,$bb));if(!$c)return 0;
    return(int)round(100*(2*$c/(count($aa)+count($bb))));
}
function hman_product(string $name):bool{
    $raw=mb_strtolower(trim($name),'UTF-8');$n=hman_norm($name);
    if(preg_match('/^(roulette|рулетка|рулет)(\s|$)/u',$n))return true;
    if(!preg_match('/^(fortuna|фортуна)(\s|$)/u',$n))return false;
    if(preg_match('/^(fortuna|фортуна)\s+[1-5]\s*[*★]/u',$raw))return true;
    $tokens=preg_split('/\s+/u',$n,-1,PREG_SPLIT_NO_EMPTY)?:[];
    $physical=(bool)preg_match('/\b(hotel|hotels|otel|отель|resort)\b/u',$n);
    return!($physical&&count($tokens)>=3);
}
function hman_aliases(string $v,array $groups):array{
    $n=hman_norm($v);$o=[$n=>true];
    foreach($groups as$g){$ng=array_map('hman_norm',$g);if(in_array($n,$ng,true))foreach($ng as$x)$o[$x]=true;}
    return array_keys($o);
}
function hman_country_aliases(string $v):array{return hman_aliases($v,[
 ['Турция','Turkey','Turkiye','Türkiye'],['Египет','Egypt'],['ОАЭ','UAE','United Arab Emirates','Объединенные Арабские Эмираты'],
 ['Мальдивы','Maldives'],['Вьетнам','Vietnam'],['Таиланд','Thailand'],['Куба','Cuba'],['Шри-Ланка','Sri Lanka'],
 ['Катар','Qatar'],['Китай','China'],['Маврикий','Mauritius'],['Индонезия','Indonesia'],['Тунис','Tunisia'],
 ['Индия','India'],['Танзания','Tanzania'],['Узбекистан','Uzbekistan'],['Марокко','Morocco'],['Сейшелы','Seychelles']
]);}
function hman_departure_aliases(string $v):array{return hman_aliases($v,[
 ['Москва','Moscow'],['Санкт-Петербург','Санкт Петербург','С.Петербург','Saint Petersburg','St Petersburg'],
 ['Екатеринбург','Yekaterinburg','Ekaterinburg'],['Казань','Kazan'],['Новосибирск','Novosibirsk'],['Самара','Samara'],
 ['Уфа','Ufa'],['Челябинск','Chelyabinsk'],['Нижний Новгород','Nizhny Novgorod'],['Минеральные Воды','Mineralnye Vody'],
 ['Пермь','Perm'],['Тюмень','Tyumen'],['Омск','Omsk'],['Красноярск','Krasnoyarsk'],['Иркутск','Irkutsk']
]);}
function hman_unique_id(array $rows,array $aliases):?int{
    $want=array_fill_keys($aliases,true);$hits=[];
    foreach($rows as$r){if(!is_array($r))continue;$id=(int)($r['id']??0);$n=hman_norm(hman_scalar($r['name']??'',180));if($id>0&&isset($want[$n]))$hits[$id]=true;}
    return count($hits)===1?(int)array_key_first($hits):null;
}
function hman_family(string $name):?string{
    $n=hman_norm($name);$compact=preg_replace('/[^\p{L}\p{N}]+/u','',$n)??'';
    if(in_array($compact,['anex','anextour','анекс','анекстур'],true))return'anex';
    if(str_contains($compact,'библиоглобус')||str_contains($compact,'biblioglobus'))return'biblio';
    if(str_contains($compact,'funsun')||str_contains($compact,'funandsun'))return'funsun';
    if(str_contains($compact,'интурист')||str_contains($compact,'intourist'))return'intourist';
    return null;
}
function hman_operator_id(array $rows,string $family):?int{
    $hits=[];foreach($rows as$r){if(!is_array($r))continue;$id=(int)($r['id']??0);$name=hman_scalar($r['name']??'',180);if($id>0&&hman_family($name)===$family)$hits[$id]=true;}
    return count($hits)===1?(int)array_key_first($hits):null;
}
function hman_durable(string $path,array $v):string{
    $raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_exists');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');}finally{fclose($f);}
    return hash('sha256',$raw);
}
function hman_pace(float &$last):void{$wait=1.05-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));$last=microtime(true);}
function hman_catalog(array $session,string $action,array $params,int &$calls,float &$last):array{
    if(++$calls>HMAN_MAX_CALLS)throw new RuntimeException('andromeda_call_budget');hman_pace($last);
    $tr=new AnyTourAndromedaTransport(false,false);$cl=new AnyTourAndromedaClient($tr,true);$cl->restorePrivateSession($session);
    return $cl->catalog($action,$params);
}
function hman_price(array $session,array $params,int &$calls,float &$last):array{
    if(++$calls>HMAN_MAX_CALLS)throw new RuntimeException('andromeda_call_budget');hman_pace($last);
    $tr=new AnyTourAndromedaTransport(true,false);$cl=new AnyTourAndromedaClient($tr,true);$cl->restorePrivateSession($session);
    return $cl->price($params);
}
function hman_name_veto(string $provider,array $localNames):array{
    $provider=(string)$provider;$pn=hman_nums($provider);$pq=hman_quals($provider);$best=0;$ok=false;$reason=null;
    foreach($localNames as$l){$l=is_scalar($l)?(string)$l:'';if($l===''||preg_match('/\\p{L}/u',$l)!==1){$reason='low_information_name';continue;}$ln=hman_nums($l);$lq=hman_quals($l);if($pn!==$ln){$reason='number_conflict';continue;}if(($pq||$lq)&&$pq!==$lq){$reason='qualifier_conflict';continue;}$ok=true;$best=max($best,hman_score($provider,$l));}
    return['ok'=>$ok,'best_score'=>$best,'reason'=>$ok?null:($reason??'name_guard')];
}

if(in_array('--self-test',$argv??[],true)){
    if(hman_operator_id([['id'=>5,'name'=>'ANEX'],['id'=>6,'name'=>'Other']],'anex')!==5)throw new RuntimeException('operator');
    if(!in_array('с петербург',hman_departure_aliases('С.Петербург'),true))throw new RuntimeException('departure');
    if(!hman_name_veto('ROYAL BEACH HOTEL',['Royal Beach'])['ok'])throw new RuntimeException('name');
    if(hman_name_veto('ROYAL NORTH HOTEL',['Royal South Hotel'])['ok'])throw new RuntimeException('qualifier');
    if(hman_name_veto('Hotel Alpha',['123'])['ok'])throw new RuntimeException('numeric_guard');echo"MATCH_ANDROMEDA_NATIVE_ANEX_MASS_V2_SELFTEST_OK\n";exit;
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=(string)getenv('MATCH_OPERATION_DIR');$reg=realpath((string)getenv('MATCH_MAPPING_REGISTRY_PATH'));$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if(!$root||$opDir===''||!is_dir($opDir)||!is_file($opDir.'/reservation.json')||!is_string($reg)||!is_file($reg)||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha))throw new RuntimeException('runtime_guard');
$res=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
if(($res['operation']??'')!==HMAN_OP||($res['state']??'')!=='reserved_before_provider_access')throw new RuntimeException('reservation_guard');
require_once $reg;
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbf;
require_once $opDir.'/payload/andromeda-client.php';require_once $opDir.'/payload/andromeda-network-transport-failure.php';require_once $opDir.'/payload/andromeda-transport.php';
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$providerAccess=false;$calls=0;$last=0.0;$loginCalls=0;
try{
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);
    $nativeToLocal=[];$localToNative=[];
    foreach(hman_rows($db,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions')as$r){
        $aid=(int)$r['anex_hotel_id'];if($aid<1)continue;$local=$registry->resolve('anex_online',(string)$aid,'preview');
        if(is_int($local)&&$local>0){$nativeToLocal[$aid][$local]=true;$localToNative[$local][$aid]=true;}
    }
    $andLocal=[];$andOcc=[];$identityRows=hman_rows($db,"SELECT external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'");
    foreach($identityRows as$r){$ext=hman_scalar($r['external_hotel_id']??'',128);if($ext==='')continue;$andOcc[$ext][]=['local'=>$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'],'status'=>hman_scalar($r['decision_status']??'',60)];if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$andLocal[(int)$r['local_hotel_id']]=true;}
    $seen=[];foreach(hman_rows($db,"SELECT hotel_id,COUNT(*) obs,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id")as$r)$seen[(int)$r['hotel_id']]=['obs'=>(int)$r['obs'],'last'=>(string)$r['last_seen']];
    $target=[];$names=[];
    foreach(hman_rows($db,'SELECT id,country_id,country_name,name,region_name,subregion_name,is_active FROM catalog_hotels WHERE is_active=1')as$r){
        $lid=(int)$r['id'];$names[$lid][(string)$r['name']]=true;$cid=(int)$r['country_id'];
        if(isset($seen[$lid],$localToNative[$lid])&&!isset($andLocal[$lid],HMAN_EXCLUDED_COUNTRIES[$cid])&&!hman_product((string)$r['name']))$target[$lid]=$r+$seen[$lid];
    }
    foreach(hman_rows($db,'SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1')as$r){$lid=(int)$r['hotel_id'];$v=trim((string)$r['alias']);if($v!==''&&isset($names[$lid]))$names[$lid][$v]=true;}
    $deps=[];foreach(hman_rows($db,'SELECT id,name FROM catalog_departures WHERE is_active=1')as$r)$deps[(int)$r['id']]=(string)$r['name'];
    $countries=[];foreach(hman_rows($db,'SELECT id,name FROM catalog_countries WHERE is_active=1')as$r)$countries[(int)$r['id']]=(string)$r['name'];
    $ctx=[];
    if($target){$ids=array_keys($target);foreach(array_chunk($ids,700)as$chunk){$ph=implode(',',array_fill(0,count($chunk),'?'));
        foreach(hman_rows($db,"SELECT hotel_id,departure_id,country_id,departure_date,nights,COUNT(*) obs FROM tour_price_observations WHERE source='user_search' AND operator_id=13 AND departure_date>=CURRENT_DATE AND adults=2 AND children_count=0 AND hotel_id IN ($ph) GROUP BY hotel_id,departure_id,country_id,departure_date,nights",array_values($chunk))as$r){
            $lid=(int)$r['hotel_id'];$dep=(int)$r['departure_id'];$cid=(int)$r['country_id'];if(!isset($target[$lid],$deps[$dep],$countries[$cid])||$cid!==(int)$target[$lid]['country_id'])continue;
            $key=$dep.'|'.$cid.'|'.(string)$r['departure_date'].'|'.(int)$r['nights'];$ctx[$key]['departure_id']=$dep;$ctx[$key]['departure_name']=$deps[$dep];$ctx[$key]['country_id']=$cid;$ctx[$key]['country_name']=$countries[$cid];$ctx[$key]['date']=(string)$r['departure_date'];$ctx[$key]['nights']=(int)$r['nights'];$ctx[$key]['targets'][$lid]=true;$ctx[$key]['obs']=($ctx[$key]['obs']??0)+(int)$r['obs'];
        }
    }}
    $contexts=array_values($ctx);usort($contexts,fn($a,$b)=>count($b['targets'])<=>count($a['targets'])?:$b['obs']<=>$a['obs']?:strcmp($a['date'],$b['date']));
    $selected=[];$pairCap=[];$countryCap=[];
    foreach($contexts as$c){$pair=$c['departure_id'].'|'.$c['country_id'];$cid=$c['country_id'];if(($pairCap[$pair]??0)>=4||($countryCap[$cid]??0)>=8)continue;$selected[]=$c;$pairCap[$pair]=($pairCap[$pair]??0)+1;$countryCap[$cid]=($countryCap[$cid]??0)+1;if(count($selected)>=HMAN_MAX_CONTEXTS)break;}
    $db->rollBack();
    if(count($selected)<2)throw new RuntimeException('no_contexts');$skippedNoReplay=array_shift($selected);

    $stdin=file('php://stdin',FILE_IGNORE_NEW_LINES);$user=trim((string)($stdin[0]??''));$pass=trim((string)($stdin[1]??''));unset($stdin);
    if($user===''||$pass==='')throw new RuntimeException('credentials_missing');
    $providerAccess=true;$loginCalls=1;$calls=1;hman_pace($last);$tr=new AnyTourAndromedaTransport(false,false);$login=new AnyTourAndromedaClient($tr,true);$login->login($user,$pass);$session=$login->privateSession();unset($user,$pass,$login,$tr);
    if(!$session)throw new RuntimeException('login_session');
    $townFrom=hman_catalog($session,'townfrom',[],$calls,$last)['TOWNFROM'];
    $depBind=[];$stateBind=[];$opBind=[];$unbound=[];
    foreach($selected as$c){$dep=(int)$c['departure_id'];$cid=(int)$c['country_id'];$key=$dep.'|'.$cid;
        if(!isset($depBind[$dep]))$depBind[$dep]=hman_unique_id($townFrom,hman_departure_aliases($c['departure_name']));
        if(!$depBind[$dep]){$unbound[$key]='departure';continue;}
        if(!isset($stateBind[$key])){$states=hman_catalog($session,'state',['TOWNFROMINC'=>$depBind[$dep]],$calls,$last)['STATE'];$stateBind[$key]=hman_unique_id($states,hman_country_aliases($c['country_name']));}
        if(!$stateBind[$key]){$unbound[$key]='country';continue;}
        if(!isset($opBind[$key])){$all=hman_catalog($session,'all',['TOWNFROMINC'=>$depBind[$dep],'STATEINC'=>$stateBind[$key]],$calls,$last);$opBind[$key]=hman_operator_id($all['OPERATORS'],'anex');}
        if(!$opBind[$key])$unbound[$key]='anex_operator';
    }

    $raw=[];$contextMeta=[];$providerRows=0;
    foreach($selected as$c){$dep=(int)$c['departure_id'];$cid=(int)$c['country_id'];$pair=$dep.'|'.$cid;$contextKey=$pair.'|'.$c['date'].'|'.$c['nights'];
        if(isset($unbound[$pair])){$contextMeta[$contextKey]=['status'=>'unbound','reason'=>$unbound[$pair]];continue;}
        $base=['TOWNFROMINC'=>$depBind[$dep],'STATEINC'=>$stateBind[$pair],'CHECKIN_BEG'=>str_replace('-','',$c['date']),'CHECKIN_END'=>str_replace('-','',$c['date']),'NIGHTS_FROM'=>(int)$c['nights'],'NIGHTS_TILL'=>(int)$c['nights'],'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'OPERATORS'=>(string)$opBind[$pair],'PACKETTYPE'=>0,'GROUP_BY'=>32];
        $ctxRows=[];$pages=null;$discard=false;
        for($page=1;$page<=HMAN_MAX_PAGES_PER_CONTEXT;$page++){$reply=hman_price($session,$base+['PAGE'=>$page],$calls,$last);$pages=(int)$reply['PAGES_COUNT'];if($pages>HMAN_MAX_PAGES_PER_CONTEXT){$discard=true;break;}
            foreach($reply['PRICES']as$r){if(!is_array($r))continue;$providerRows++;
                if((string)($r['operatorKey']??'')!==(string)$opBind[$pair]||!in_array($r['isOperatorHotelKey']??null,[0,'0'],true)||!is_array($r['original']??null))continue;
                $native=hman_scalar($r['original']['hotelKey']??'',64);$ext=hman_scalar($r['hotelKey']??'',128);$name=hman_scalar($r['hotel']??'',300);
                if(!preg_match('/^[1-9][0-9]{0,31}$/D',$native)||$ext===''||$name===''||hman_product($name))continue;$n=(int)$native;
                if(!isset($nativeToLocal[$n])||count($nativeToLocal[$n])!==1)continue;$lid=(int)array_key_first($nativeToLocal[$n]);if(!isset($c['targets'][$lid],$target[$lid]))continue;
                $guard=hman_name_veto($name,array_keys($names[$lid]??[(string)$target[$lid]['name']=>true]));if(!$guard['ok'])continue;
                $ctxRows[]=['external_hotel_id'=>$ext,'native_anex_hotel_id'=>$n,'local_hotel_id'=>$lid,'provider_hotel_name'=>$name,'provider_town_key'=>hman_scalar($r['townKey']??'',64)?:null,'operator_key'=>$opBind[$pair],'context_key'=>$contextKey,'page'=>$page,'name_score'=>$guard['best_score'],'user_observations'=>(int)$target[$lid]['obs']];
            }
            if($page>=$pages)break;
        }
        if($discard||$pages===null){$contextMeta[$contextKey]=['status'=>'discarded_page_budget','pages_count'=>$pages,'rows_seen'=>count($ctxRows)];continue;}
        $contextMeta[$contextKey]=['status'=>'drained','pages_count'=>$pages,'candidate_rows'=>count($ctxRows),'target_count'=>count($c['targets'])];foreach($ctxRows as$r)$raw[]=$r;
    }

    $candidate=[];$holds=[];$extToLocal=[];$localToExt=[];
    foreach($raw as$r){$ext=$r['external_hotel_id'];$lid=$r['local_hotel_id'];$blocked=false;$reasons=[];
        foreach($andOcc[$ext]??[]as$o){$st=$o['status'];$ol=$o['local'];if($st==='accepted'&&$ol===$lid)continue;if($st==='accepted'&&$ol!==null&&$ol!==$lid){$blocked=true;$reasons[]='accepted_elsewhere';}elseif(in_array($st,['conflict','rejected','excluded'],true)){$blocked=true;$reasons[]='current_'.$st;}elseif($ol!==null&&$ol!==$lid){$blocked=true;$reasons[]='occupied_other_local';}}
        if($blocked){$holds[]=$r+['reason'=>'current_identity_hold','hold_detail'=>array_values(array_unique($reasons))];continue;}
        $key=$ext.'|'.$lid;if(!isset($candidate[$key]))$candidate[$key]=$r+['independent_contexts'=>[]];$candidate[$key]['independent_contexts'][$r['context_key']]=true;$extToLocal[$ext][$lid]=true;$localToExt[$lid][$ext]=true;
    }
    $safe=[];foreach($candidate as$r){$ext=$r['external_hotel_id'];$lid=$r['local_hotel_id'];if(count($extToLocal[$ext]??[])!==1){$holds[]=$r+['reason'=>'external_multiple_locals'];continue;}if(count($localToExt[$lid]??[])!==1){$holds[]=$r+['reason'=>'local_multiple_andromeda_ids'];continue;}$r['independent_context_count']=count($r['independent_contexts']);$r['independent_contexts']=array_keys($r['independent_contexts']);$safe[]=$r;}
    usort($safe,fn($a,$b)=>$b['user_observations']<=>$a['user_observations']?:$b['independent_context_count']<=>$a['independent_context_count']?:strcmp($a['external_hotel_id'],$b['external_hotel_id']));
    $ctxCounts=[];foreach($contextMeta as$m)$ctxCounts[$m['status']]=($ctxCounts[$m['status']]??0)+1;ksort($ctxCounts);
    $result=['operation'=>HMAN_OP,'state'=>'completed_read_only','source_sha'=>$sourceSha,'provider_access'=>true,'login_calls'=>$loginCalls,'andromeda_calls'=>$calls,'price_rows_seen'=>$providerRows,'current_anex_accepted_unique_local'=>count($localToNative),'target_user_seen_anex_accepted_andromeda_missing'=>count($target),'selected_context_count'=>count($selected),'skipped_no_replay_context'=>['departure_id'=>$skippedNoReplay['departure_id'],'country_id'=>$skippedNoReplay['country_id'],'date'=>$skippedNoReplay['date'],'nights'=>$skippedNoReplay['nights']],'context_status_counts'=>$ctxCounts,'context_meta'=>$contextMeta,'raw_direct_bridge_rows'=>count($raw),'safe_candidate_count'=>count($safe),'safe_candidates'=>$safe,'hold_count'=>count($holds),'holds'=>array_slice($holds,0,300),'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'direct_anex_calls'=>0,'no_replay'=>true];
}catch(Throwable$e){if(isset($db)&&$db->inTransaction())$db->rollBack();$result=['operation'=>HMAN_OP,'state'=>$providerAccess?'terminal_failed_no_replay':'failed_before_provider_access','source_sha'=>$sourceSha,'reason'=>preg_replace('/[^A-Za-z0-9_.:-]/','_',mb_substr($e->getMessage(),0,100)),'provider_access'=>$providerAccess,'login_calls'=>$loginCalls,'andromeda_calls'=>$calls,'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'direct_anex_calls'=>0,'no_replay'=>$providerAccess];}
$sha=hman_durable($opDir.'/result.json',$result);hman_durable($opDir.'/receipt.json',['operation'=>HMAN_OP,'state'=>$result['state'],'result_sha256'=>$sha,'provider_access'=>$result['provider_access']??false,'andromeda_calls'=>$result['andromeda_calls']??0,'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'direct_anex_calls'=>0,'no_replay'=>$result['no_replay']??false]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit(($result['state']??'')==='completed_read_only'?0:2);
