<?php
declare(strict_types=1);
/** MATCH acquisition planning only. No provider client, mutation SQL or accept authority. */
const HMNF_OP = 'hotel-match-unseen-native-frontier-1971-20260916-v1';
function hmnf_json(array $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}
function hmnf_write(string $path, array $value): string {
    $raw=hmnf_json($value); $f=@fopen($path,'x+b');
    if(!$f)throw new RuntimeException('exclusive_output');
    try {
        if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('output_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('output_sync');
        rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('output_readback');
    } finally { fclose($f); }
    return hash('sha256',$raw);
}
function hmnf_id($v): ?string {
    return (is_int($v)||is_string($v))&&preg_match('/^[1-9][0-9]{0,19}$/D',(string)$v)?(string)$v:null;
}
function hmnf_decode(string $raw): array {
    $v=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    if(!is_array($v))throw new RuntimeException('evidence_shape');return $v;
}
function hmnf_source(array $e): array { return is_array($e['source']??null)?$e['source']:$e; }
function hmnf_state(array $e): ?string {
    $s=hmnf_source($e);$ids=[];
    foreach([$s['stateKey']??null,$s['state_key']??null,$e['stateKey']??null,$e['state_key']??null] as $v)
        if(($id=hmnf_id($v))!==null)$ids[$id]=true;
    return count($ids)===1?(string)array_key_first($ids):null;
}
function hmnf_lower(string $s): string {
    if(function_exists('mb_strtolower'))return mb_strtolower($s,'UTF-8');
    $a=preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯÜİ',-1,PREG_SPLIT_NO_EMPTY);
    $b=preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюяüi',-1,PREG_SPLIT_NO_EMPTY);
    return strtolower(strtr($s,array_combine($a,$b)));
}
function hmnf_country(string $name): ?string {
    $name=hmnf_lower(trim($name));$name=strtr($name,['ё'=>'е','-'=>' ']);
    $name=preg_replace('/\s+/u',' ',$name);
    $labels=['egypt'=>['египет','egypt'],'turkey'=>['турция','turkey','türkiye','turkiye'],
        'thailand'=>['таиланд','thailand'],'uae'=>['оаэ','uae','united arab emirates','объединенные арабские эмираты'],
        'vietnam'=>['вьетнам','vietnam'],'sri_lanka'=>['шри ланка','sri lanka'],
        'maldives'=>['мальдивы','maldives'],'cuba'=>['куба','cuba']];
    foreach($labels as $key=>$names)if(in_array($name,$names,true))return $key;return null;
}
function hmnf_protected(array $e): bool {
    foreach($e as $k=>$v){
        $k=hmnf_lower((string)$k);
        if(in_array($k,['manual','manual_decision','excluded','exclusion','rejected','protected','pair_exclusion','conflict'],true)){
            if(is_array($v)&&$v)return true;
            if($v===true||$v===1||$v==='1')return true;
            if(is_string($v)&&!in_array(hmnf_lower(trim($v)),['','0','false','none','no'],true))return true;
        }
        if(is_array($v)&&hmnf_protected($v))return true;
    }return false;
}
function hmnf_existing_relation_ids(array $e): array {
    // Exclusion, not identity inference: any retained typed relation reserves its Andromeda side.
    $ids=[];
    foreach($e as $k=>$v){
        if($k==='andromeda_hotel_id'&&($id=hmnf_id($v))!==null)$ids[$id]=true;
        if(is_array($v))$ids+=hmnf_existing_relation_ids($v);
    }return $ids;
}
function hmnf_frequency(array $e): array {
    $facts=[];$nodes=['root'=>$e];
    foreach(['observation','observations','live_priority','priority'] as $k)
        if(is_array($e[$k]??null))$nodes[$k]=$e[$k];
    foreach($nodes as $path=>$node)foreach(['live_frequency','search_count','seen_count','observation_count'] as $k){
        $v=$node[$k]??null;
        if((is_int($v)&&$v>=0)||(is_string($v)&&preg_match('/^[0-9]{1,10}$/D',$v)))
            $facts[]=['path'=>$path.'.'.$k,'value'=>(int)$v];
    }
    return ['value'=>$facts?max(array_column($facts,'value')):null,'facts'=>$facts,'semantics'=>'retained_explicit_counter_max_not_current_search_census'];
}
function hmnf_names(array $e): array {
    $s=hmnf_source($e);$out=[];
    foreach(['name','lName','hotelName','hotel_name','original_name'] as $k){
        $v=$s[$k]??null;
        if(is_string($v)&&trim($v)!==''&&strlen($v)<=1024&&!preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$v))$out[trim($v)]=true;
    }return array_keys($out);
}
function hmnf_authority(array $accepted, array $core): array {
    $counts=[];
    foreach($accepted as $r){$st=hmnf_state(hmnf_decode($r['evidence_json']));$cid=(int)$r['country_id'];
        if($st!==null)$counts[$st][$cid]=($counts[$st][$cid]??0)+1;
    }
    $authority=[];$support=[];
    foreach($counts as $state=>$votes){arsort($votes,SORT_NUMERIC);$keys=array_keys($votes);$cid=(int)$keys[0];
        $n=$votes[$cid];$total=array_sum($votes);$second=$votes[$keys[1]??-1]??0;
        $support[$state]=['votes'=>$votes,'total'=>$total,'best'=>$n,'runner_up'=>$second];
        if(isset($core[$cid])&&$n>=5&&$n/$total>=0.98&&$n-$second>=5)$authority[(string)$state]=$cid;
    }return [$authority,$support];
}
function hmnf_plan(array $pending,array $authority,array $core,array $existing,array $foreign): array {
    $queue=[];$held=[];$routes=[];$foreign=array_fill_keys(array_map('strval',$foreign),true);
    foreach($pending as $r){
        $id=hmnf_id($r['external_hotel_id']??null);$e=hmnf_decode((string)$r['evidence_json']);
        $state=hmnf_state($e);$names=hmnf_names($e);$route='queue';
        if($id===null)$route='invalid_id';
        elseif(isset($foreign[$id]))$route='foreign_claim';
        elseif(isset($existing[$id]))$route='existing_typed_relation';
        elseif(hmnf_protected($e))$route='protected_evidence';
        elseif($state===null||!isset($authority[$state]))$route='country_unresolved_or_non_core';
        elseif(!$names)$route='missing_source_name';
        elseif(preg_match('/roulette|fortuna|фортуна|рулетк|excursion|экскурсион/ui',implode(' ',$names)))$route='non_hotel_product';
        elseif(!preg_match('/^[a-f0-9]{64}$/D',(string)($r['evidence_sha256']??'')))$route='missing_evidence_digest';
        if($route==='queue'){
            $source=hmnf_source($e);
            foreach(['countryName','country_name','stateName','state_name'] as $key){
                $value=$source[$key]??null;
                if(is_string($value)&&trim($value)!==''&&hmnf_country($value)!==$core[$authority[$state]]['label'])
                    $route='source_country_conflict_or_unknown';
            }
        }
        $routes[$route]=($routes[$route]??0)+1;
        if($route!=='queue'){$held[]=['andromeda_hotel_id'=>$id,'route'=>$route];continue;}
        $freq=hmnf_frequency($e);
        $queue[]=['supplier_namespace'=>'andromeda_catalog','andromeda_hotel_id'=>$id,'state_key'=>(int)$state,
            'country_id'=>$authority[$state],'country_name'=>$core[$authority[$state]]['name'],'names'=>$names,
            'evidence_sha256'=>$r['evidence_sha256'],'frequency'=>$freq['value'],'frequency_provenance'=>$freq,
            'safe_to_write_now'=>false];
    }
    usort($queue,static fn($a,$b)=>(($b['frequency']??-1)<=>($a['frequency']??-1))?:strcmp($a['andromeda_hotel_id'],$b['andromeda_hotel_id']));
    ksort($routes);return ['queue'=>$queue,'holds'=>$held,'route_counts'=>$routes];
}
function hmnf_chunks(array $ids,int $limit): array {
    if($limit<1||$limit>30)throw new RuntimeException('chunk_limit');
    $out=[];$part=[];$seen=[];
    foreach($ids as $raw){$id=hmnf_id($raw);if($id===null||isset($seen[$id]))throw new RuntimeException('chunk_identity');$seen[$id]=true;
        if($part&&(count($part)>=$limit||strlen(implode(',',array_merge($part,[$id])))>300)){$out[]=$part;$part=[];}
        $part[]=$id;
    }if($part)$out[]=$part;return $out;
}
function hmnf_query(PDO $db,string $sql): array { return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
function hmnf_main(): void {
    if(PHP_SAPI!=='cli'||getenv('OPERATION_ID')!==HMNF_OP||!defined('HMNF_EXCLUDED'))throw new RuntimeException('operation_guard');
    $sha=(string)getenv('MATCH_SOURCE_SHA');if(!preg_match('/^[a-f0-9]{40}$/D',$sha))throw new RuntimeException('source_guard');
    $root=realpath(getcwd());$base=(string)getenv('HOME').'/.anytoour-match/operations';
    if(!$root||basename($root)!=='anytoour.ru'||!is_dir($base))throw new RuntimeException('root_guard');
    $dir=$base.'/'.HMNF_OP;if(!@mkdir($dir,0700))throw new RuntimeException('operation_already_reserved');
    hmnf_write($dir.'/reservation.json',['operation_id'=>HMNF_OP,'source_sha'=>$sha,'state'=>'reserved_before_db_access',
        'claim_comment_id'=>5697525470,'excluded_count'=>count(HMNF_EXCLUDED),'no_replay'=>true,'database_writes'=>0,'supplier_calls'=>0]);
    $db=null;$phase='current';$result=['schema'=>'unseen-native-acquisition-frontier/1','operation_id'=>HMNF_OP,'source_sha'=>$sha,
        'state'=>'failed_no_replay','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,
        'safe_to_write_now'=>false,'no_replay'=>true];
    ob_start();
    try {
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $core=[];$labels=[];
        foreach(hmnf_query($db,'SELECT id,name,slug FROM catalog_countries WHERE is_active=1 ORDER BY id') as $r){
            $label=hmnf_country($r['name']);if($label===null)continue;
            if(isset($labels[$label]))throw new RuntimeException('duplicate_core_country');
            $labels[$label]=(int)$r['id'];$core[(int)$r['id']]=['name'=>$r['name'],'label'=>$label];
        }if(count($core)!==8)throw new RuntimeException('core8_incomplete');
        $accepted=hmnf_query($db,"SELECT i.evidence_json,h.country_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL AND h.is_active=1");
        [$authority,$support]=hmnf_authority($accepted,$core);unset($accepted);
        $pending=hmnf_query($db,"SELECT external_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");
        $operators=hmnf_query($db,"SELECT supplier_namespace,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace IN ('operator_5','operator_315','operator_342','operator_115') ORDER BY supplier_namespace,external_hotel_id");
        $existing=[];foreach($operators as $row)$existing+=hmnf_existing_relation_ids(hmnf_decode($row['evidence_json']));
        $readAt=gmdate('c');$db->exec('ROLLBACK');$phase='plan';
        $plan=hmnf_plan($pending,$authority,$core,$existing,array_merge(HMNF_EXCLUDED,['366230','402863']));
        $groups=[];foreach($plan['queue'] as $r)$groups[(string)$r['state_key']][]=$r['andromeda_hotel_id'];
        $batches=[];foreach($groups as $state=>$ids)foreach(['5','315','342','115'] as $op)
            foreach(hmnf_chunks($ids,$op==='115'?10:30) as $chunk)$batches[]=['state_key'=>(int)$state,'operator_key'=>$op,'hotel_ids'=>$chunk];
        $known=array_filter($plan['queue'],static fn($r)=>$r['frequency']!==null);
        $result+=['core8_countries'=>$core,'authoritative_states'=>$authority,'state_support'=>$support,
            'current_pending_total'=>count($pending),'existing_operator_rows'=>count($operators),'existing_relation_andromeda_ids'=>count($existing),
            'retained_foreign_claim_ids'=>count(HMNF_EXCLUDED),'queue_count'=>count($plan['queue']),
            'known_frequency_rows'=>count($known),'unknown_frequency_rows'=>count($plan['queue'])-count($known),
            'known_frequency_sum'=>array_sum(array_column($known,'frequency')),
            'queue'=>$plan['queue'],'holds'=>$plan['holds'],'route_counts'=>$plan['route_counts'],
            'batches'=>$batches,'first_page_requests_all_operators'=>count($batches),'read_at_utc'=>$readAt];
        $result['state']='completed_read_only';
    } catch(Throwable $e) {
        if($db instanceof PDO&&$db->inTransaction())$db->rollBack();
        $result['error_phase']=$phase;$result['error_code']=preg_match('/^[a-z_]{3,80}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';
    }
    while(ob_get_level())ob_end_clean();
    $digest=hmnf_write($dir.'/result.json',$result);
    hmnf_write($dir.'/receipt.json',['operation_id'=>HMNF_OP,'source_sha'=>$sha,'state'=>$result['state'],'result_sha256'=>$digest,
        'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0]);
    echo hmnf_json(['state'=>$result['state'],'queue_count'=>$result['queue_count']??null,'result_sha256'=>$digest]);
    if($result['state']!=='completed_read_only')exit(2);
}
if(getenv('HMNF_LIBRARY_ONLY')==='1')return;
hmnf_main();
