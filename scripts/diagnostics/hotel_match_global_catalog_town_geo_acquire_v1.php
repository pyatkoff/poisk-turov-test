<?php
declare(strict_types=1);

const HMGG_OPERATION='hotel-match-global-catalog-town-geo-acquire-1971-20260918-v1';
const HMGG_SOURCE_SHA='7516046dd2a0d052ea806ca07b337400d7d3769e2ff3fd7832e3479eecde1324';
const HMGG_CURRENT_SHA='43ff52996b418c519f984910e77f83929cbef9fda8525a66a4e0dca0d042f65f';
const HMGG_CANDIDATES=102;
const HMGG_MAX_CATALOG_CALLS=20;

function hmgg_scalar(mixed $v,int $max=255): string {
    return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';
}
function hmgg_norm(string $v): string {
    $n=mb_strtolower(trim($v),'UTF-8');
    $n=strtr($n,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ','’'=>"'"]);
    $n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;
    return trim(preg_replace('/\s+/u',' ',$n)??$n);
}
function hmgg_geo_match(array $labels,string $region,string $subregion): bool {
    $locals=[];foreach([$region,$subregion] as $v){$n=hmgg_norm($v);if($n!=='')$locals[$n]=true;}
    if(!$locals)return false;
    foreach($labels as $label){
        $s=hmgg_norm((string)$label);if($s==='')continue;
        foreach(array_keys($locals) as $l){
            if($s===$l)return true;
            if(mb_strlen($s,'UTF-8')>=4&&mb_strlen($l,'UTF-8')>=4&&(str_contains($s,$l)||str_contains($l,$s)))return true;
        }
    }
    return false;
}
function hmgg_durable(string $path,array $v): string {
    $raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_exists');
    try{
        if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');
        rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');
    }finally{fclose($f);}
    return hash('sha256',$raw);
}
function hmgg_catalog_call(array $session,int $dep,int $state,int &$calls,float &$last): array {
    if(++$calls>HMGG_MAX_CATALOG_CALLS)throw new RuntimeException('catalog_call_budget');
    $wait=1.05-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));$last=microtime(true);
    $tr=new AnyTourAndromedaTransport(false,false);
    $cl=new AnyTourAndromedaClient($tr,true);
    $cl->restorePrivateSession($session);
    return $cl->catalog('all',['TOWNFROMINC'=>$dep,'STATEINC'=>$state]);
}
function hmgg_row_labels(array $row,array $byId): array {
    $labels=[];
    foreach(['name','lName','nameAlt','title','label','region','area','parent'] as $k){
        $v=hmgg_scalar($row[$k]??'',180);if($v!=='')$labels[hmgg_norm($v)]=$v;
    }
    $current=$row;
    for($depth=0;$depth<3;$depth++){
        $next=null;
        foreach(['parentKey','parentId','regionKey','areaKey','groupKey'] as $k){
            $id=hmgg_scalar($current[$k]??'',64);
            if($id!==''&&isset($byId[$id])){$next=$byId[$id];break;}
        }
        if(!is_array($next))break;
        foreach(['name','lName','nameAlt','title','label','region','area','parent'] as $k){
            $v=hmgg_scalar($next[$k]??'',180);if($v!=='')$labels[hmgg_norm($v)]=$v;
        }
        $current=$next;
    }
    return array_values($labels);
}
function hmgg_sanitize_town(array $row,array $labels): array {
    $out=['id'=>hmgg_scalar($row['id']??'',64),'name'=>hmgg_scalar($row['name']??'',180),'labels'=>$labels];
    foreach(['parentKey','parentId','regionKey','areaKey','groupKey','lName','nameAlt','region','area','parent'] as $k){
        if(array_key_exists($k,$row)&&is_scalar($row[$k])){
            $v=hmgg_scalar($row[$k],180);if($v!=='')$out[$k]=$v;
        }
    }
    return $out;
}

if(in_array('--self-test',$argv??[],true)){
    if(!hmgg_geo_match(['Санья'],'Хайнань','Санья')||hmgg_geo_match(['Дубай'],'Хайнань','Санья'))throw new RuntimeException('geo');
    $by=['1'=>['id'=>1,'name'=>'Санья','parentKey'=>2],'2'=>['id'=>2,'name'=>'Хайнань']];
    $labels=hmgg_row_labels($by['1'],$by);if(!in_array('Санья',$labels,true)||!in_array('Хайнань',$labels,true))throw new RuntimeException('hierarchy');
    echo "MATCH_GLOBAL_CATALOG_TOWN_GEO_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$sourcePath=(string)($argv[1]??'');$currentPath=(string)($argv[2]??'');
$opDir=(string)getenv('MATCH_OPERATION_DIR');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if($opDir===''||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha)||!is_dir($opDir)||!is_file($opDir.'/reservation.json')||!is_file($sourcePath)||!is_file($currentPath))throw new RuntimeException('runtime_guard');
$sraw=file_get_contents($sourcePath);$craw=file_get_contents($currentPath);
if(!is_string($sraw)||!hash_equals(HMGG_SOURCE_SHA,hash('sha256',$sraw))||!is_string($craw)||!hash_equals(HMGG_CURRENT_SHA,hash('sha256',$craw)))throw new RuntimeException('input_hash');
$source=json_decode($sraw,true,64,JSON_THROW_ON_ERROR);$current=json_decode($craw,true,64,JSON_THROW_ON_ERROR);
if(($source['state']??null)!=='completed_read_only'||(int)($source['strict_catalog_candidate_count']??-1)!==HMGG_CANDIDATES
    ||($current['status']??null)!=='read_only_complete'||(int)($current['candidate_count']??-1)!==HMGG_CANDIDATES
    ||(int)($current['current_clean_needs_geo_count']??-1)!==HMGG_CANDIDATES||!is_array($current['rows']??null))
    throw new RuntimeException('input_contract');

$res=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
if(($res['operation']??null)!==HMGG_OPERATION||($res['state']??null)!=='reserved_before_provider_access')throw new RuntimeException('reservation_guard');

$sourceBy=[];foreach($source['strict_catalog_candidates'] as $r){$sourceBy[(int)$r['hotel_id']]=$r;}
$currentBy=[];foreach($current['rows'] as $r){if(($r['route']??null)==='current_clean_needs_geo')$currentBy[(int)$r['hotel_id']]=$r;}
if(count($sourceBy)!==HMGG_CANDIDATES||count($currentBy)!==HMGG_CANDIDATES)throw new RuntimeException('candidate_set');

$pairs=[];
foreach($sourceBy as $hid=>$r){
    if(!isset($currentBy[$hid]))throw new RuntimeException('candidate_drift');
    $dep=(int)($r['provider_departure_id']??0);$state=(int)($r['provider_state_id']??0);$town=hmgg_scalar($r['town_key']??'',64);
    if($dep<1||$state<1||!preg_match('/^[1-9][0-9]{0,31}$/D',$town))throw new RuntimeException('provider_context');
    $pairs[$dep.'|'.$state]=['provider_departure_id'=>$dep,'provider_state_id'=>$state];
}
if(count($pairs)!==13)throw new RuntimeException('context_count');

$stdin=file('php://stdin',FILE_IGNORE_NEW_LINES);$user=trim((string)($stdin[0]??''));$pass=trim((string)($stdin[1]??''));unset($stdin);
if($user===''||$pass==='')throw new RuntimeException('credentials_missing');

$providerAccess=true;$catalogCalls=0;$loginCalls=1;$last=0.0;
try{
    $tr=new AnyTourAndromedaTransport(false,false);$login=new AnyTourAndromedaClient($tr,true);$login->login($user,$pass);$session=$login->privateSession();unset($user,$pass,$login,$tr);
    if(!$session)throw new RuntimeException('login_session');

    $townByPair=[];$pairMeta=[];
    foreach($pairs as $key=>$pair){
        $reply=hmgg_catalog_call($session,$pair['provider_departure_id'],$pair['provider_state_id'],$catalogCalls,$last);
        $rows=$reply['TOWNTO']??[];$byId=[];
        foreach($rows as $row){
            if(!is_array($row))continue;$id=hmgg_scalar($row['id']??'',64);if($id!=='')$byId[$id]=$row;
        }
        $townByPair[$key]=$byId;
        $pairMeta[$key]=$pair+['town_dictionary_rows'=>count($byId)];
    }

    $rows=[];$counts=['geo_clean'=>0,'needs_geo'=>0,'geo_conflict'=>0];
    foreach($sourceBy as $hid=>$src){
        $cur=$currentBy[$hid];$key=(int)$src['provider_departure_id'].'|'.(int)$src['provider_state_id'];$townId=hmgg_scalar($src['town_key'],64);
        $town=$townByPair[$key][$townId]??null;$labels=[];$route='needs_geo';$reasons=[];
        if(is_array($town)){
            $labels=hmgg_row_labels($town,$townByPair[$key]);
            if(hmgg_geo_match($labels,(string)$cur['region_name'],(string)$cur['subregion_name'])){$route='geo_clean';$reasons[]='supplier_town_hierarchy_match';}
            elseif($labels){$route='geo_conflict';$reasons[]='supplier_town_hierarchy_mismatch';}
            else{$reasons[]='supplier_town_labels_missing';}
        }else{$reasons[]='supplier_town_key_missing';}
        $counts[$route]++;
        $rows[]=[
            'hotel_id'=>$hid,'external_hotel_id'=>$src['external_hotel_id'],'hotel_name'=>$cur['hotel_name'],'supplier_name'=>$src['supplier_name'],
            'country_id'=>$cur['country_id'],'country_name'=>$cur['country_name'],'region_name'=>$cur['region_name'],'subregion_name'=>$cur['subregion_name'],
            'provider_departure_id'=>(int)$src['provider_departure_id'],'provider_state_id'=>(int)$src['provider_state_id'],'supplier_town_key'=>$townId,
            'supplier_town'=>is_array($town)?hmgg_sanitize_town($town,$labels):null,'geo_labels'=>$labels,'route'=>$route,'reasons'=>$reasons,
            'match_key'=>$cur['match_key'],'match_reasons'=>$cur['match_reasons'],'user_observation_rows'=>$cur['user_observation_rows'],
        ];
    }
    usort($rows,static fn($a,$b)=>strcmp($a['route'],$b['route'])?:($b['user_observation_rows']<=>$a['user_observation_rows'])?:($a['hotel_id']<=>$b['hotel_id']));
    $result=['operation'=>HMGG_OPERATION,'state'=>'completed_read_only','source_sha'=>$sourceSha,'provider_access'=>true,'login_calls'=>$loginCalls,
        'catalog_calls'=>$catalogCalls,'provider_calls'=>$loginCalls+$catalogCalls,'price_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,
        'candidate_count'=>count($rows),'context_pair_count'=>count($pairs),'route_counts'=>$counts,'pair_meta'=>$pairMeta,'rows'=>$rows];
}catch(Throwable $e){
    $result=['operation'=>HMGG_OPERATION,'state'=>$providerAccess?'terminal_failed_no_replay':'failed_before_provider_access','source_sha'=>$sourceSha,
        'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',mb_substr($e->getMessage(),0,100)),'provider_access'=>$providerAccess,'login_calls'=>$loginCalls,
        'catalog_calls'=>$catalogCalls,'provider_calls'=>$loginCalls+$catalogCalls,'price_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>$providerAccess];
}
$sha=hmgg_durable($opDir.'/result.json',$result);
hmgg_durable($opDir.'/receipt.json',['operation'=>HMGG_OPERATION,'state'=>$result['state'],'result_sha256'=>$sha,'provider_access'=>$result['provider_access'],
    'provider_calls'=>$result['provider_calls'],'price_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>$result['no_replay']]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit(($result['state']??'')==='completed_read_only'?0:2);
