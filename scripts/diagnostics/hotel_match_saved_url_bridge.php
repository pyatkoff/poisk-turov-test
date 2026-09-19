<?php
declare(strict_types=1);
/* MATCH saved URL evidence only. No HTTP requests and no application writes. */
const MSU_OP='hotel-match-saved-url-bridge-1971-20260915-v1';
const MSU_QUEUE_SHA='f17ca8e8d9d3945f6aea3d232fca96708fc6f119c504461f95a4cc51af8ed3ed';
const MSU_IDS='2000067227,2000067202,443416,2000062084,2000049726,252644,3126,2000027371,2000086129,2000068282,475947,201859,2000061716,2000049855,2000040860,2000022017,150285,2000057636,2000050217,379202,2000039701,2000084135,1045,3060,2000112070,182522,2000110036,2000121380,2000117376,2000103241,67775,2000038836,2000121504,75419,2000073592,240679,2000055468,2000121515,2000103831,2000023232,2000025414,44577,9047,118783,146703,218356,2000073810,2000121028,190031,9501,2000079658,2000121381,2000090661,2000098416,2000034238,9180,2000043232,372134,2000071512,156280,2000068203,325764,2000034136,1441,444805,2000048736,2000034214,2000052591,2000040737,2000035221,2000034121,2000091587,2000064940,2000073045,2000072249,2000109038,2000108645,118706,2000062523,2000081107,175606,11557,650,2000037261,2000061188,2000105597,2000034247,285,3888,2000044474';
function msu_url($url):array {
    if(!is_string($url)||$url===''||strlen($url)>2048)return ['reason'=>'empty_or_oversize'];
    $p=parse_url($url);if(!$p||!in_array(strtolower($p['scheme']??''),['https','http'],true)||isset($p['user'])||isset($p['pass'])||isset($p['port']))return ['reason'=>'unsafe_url'];
    $host=strtolower($p['host']??'');$op=null;
    foreach(['5'=>'anextour.ru','315'=>'fstravel.com','342'=>'intourist.ru','115'=>'bgoperator.ru'] as $key=>$domain)if($host===$domain||str_ends_with($host,'.'.$domain))$op=(string)$key;
    if($op===null)return ['reason'=>'unbound_host'];
    $path=$p['path']??'/';$decoded=rawurldecode(rawurldecode($path));
    if(preg_match('/[\x00-\x20\\\\]/',$decoded)||preg_match('~(?:^|/)(?:login|auth|account|booking|search|search_tour)(?:/|$)~i',$decoded))return ['reason'=>'not_public_hotel_path'];
    parse_str($p['query']??'',$query);$safe=[];
    foreach($query as $key=>$value){if(!is_string($value)||!in_array($key,['code','id','hotelCode','hotelcode','tid','flt','action'],true)||!preg_match('/^(?:[0-9]+|shw)$/D',$value))return ['reason'=>'unapproved_query'];$safe[$key]=$value;}
    $specific=preg_match('~/hotels?/[^/]+~i',$path)===1||($op==='115'&&$path==='/price.shtml'&&isset($safe['code'])&&($safe['action']??'')==='shw');
    if(!$specific)return ['reason'=>'generic_or_unrecognized_page'];
    // Preserve scheme, path case, trailing slash and all allowed identity parameters.
    // Only host case and fragment are non-identifying here. No redirects are fetched.
    ksort($safe);$canonical=strtolower($p['scheme']).'://'.$host.$path.($safe?'?'.http_build_query($safe,'','&',PHP_QUERY_RFC3986):'');
    return ['reason'=>'hotel_specific','url'=>$url,'canonical'=>$canonical,'operator_key'=>$op,'host'=>$host];
}
function msu_ops(string $raw):array {
    $r=json_decode($raw,true);if(!is_array($r))return [];$keys=[];
    foreach($r as $v)if((is_int($v)||is_string($v))&&in_array((string)$v,['5','315','342','115'],true))$keys[(string)$v]=true;else return [];
    $out=array_map('strval',array_keys($keys));sort($out);return $out;
}
function msu_words(string $s):array {
    $s=strtolower($s);$s=preg_replace('/\s*\(\s*ex\.?\s+[^)]*\)\s*$/i','',$s);preg_match_all('/[a-z0-9]+/',$s,$m);
    return array_values(array_unique(array_diff($m[0],['hotel','hotels','resort','resorts','spa','the','and'])));
}
function msu_name_guard(string $a,string $b):bool {
    // Non-Latin names are deferred rather than transliterated into false identity.
    if(preg_match('/[^\x00-\x7F]/',$a.$b))return false;$x=msu_words($a);$y=msu_words($b);if(!$x||!$y)return false;
    $qual=['annex','beach','garden','gardens','north','south','hill','hills','pool'];$qx=array_values(array_intersect($x,$qual));$qy=array_values(array_intersect($y,$qual));sort($qx);sort($qy);
    return $qx===$qy&&($x===$y||count(array_intersect($x,$y))===min(count($x),count($y)));
}
function msu_query(PDO $db,string $sql,array $args=[]):array {$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
function msu_source(string $raw):array {
    $e=json_decode($raw,true);if(!is_array($e))return [];
    for($d=0;$d<8&&isset($e['prior_evidence'])&&is_array($e['prior_evidence']);$d++)$e=$e['prior_evidence'];
    $src=is_array($e['source']??null)?$e['source']:[];$geo=is_array($e['geography']??null)?$e['geography']:[];
    return ['source'=>array_intersect_key($src,array_fill_keys(['id','name','lName','stateKey','townKey','latitude','longitude','lat','lon','lng'],true)),
        'geography'=>array_intersect_key($geo,array_fill_keys(['source_town_name','latitude','longitude','lat','lon','lng'],true))];
}
function msu_run():array {
    if(PHP_SAPI!=='cli'||getenv('MATCH_OPERATION_ID')!==MSU_OP||!preg_match('/^[0-9a-f]{40}$/D',getenv('MATCH_SOURCE_SHA')?:'')||getenv('MATCH_RESERVATION_CONFIRMED')!=='1')throw new RuntimeException('operation_guard');
    $root=realpath(getenv('HOME').'/www/anytoour.ru');if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');
    $ids=explode(',',MSU_IDS);if(count($ids)!==90||count(array_unique($ids))!==90)throw new RuntimeException('target_scope');
    $result=['operation_id'=>MSU_OP,'source_sha'=>getenv('MATCH_SOURCE_SHA'),'queue_sha256'=>MSU_QUEUE_SHA,'target_ids'=>$ids,'state'=>'incomplete','supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'server_file_writes'=>0,'no_replay'=>true];$db=null;
    try {
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
        $rows=msu_query($db,"SELECT o.observation_sha256,o.search_evidence_sha256,o.external_hotel_id,o.hotel_name,o.operator_refs_json,o.country_id,o.region_name,o.hotel_url,o.observed_at_utc,i.local_hotel_id,i.decision_status,i.evidence_sha256,c.name AS local_name,c.country_id AS local_country_id,c.latitude,c.longitude,c.is_active FROM andromeda_search_hotel_observations o LEFT JOIN andromeda_hotel_identities i ON i.supplier_namespace=o.supplier_namespace AND i.external_hotel_id=o.external_hotel_id LEFT JOIN catalog_hotels c ON c.id=i.local_hotel_id WHERE o.supplier_namespace='andromeda_catalog' AND o.country_id IN (1,4) ORDER BY o.external_hotel_id,o.observed_at_utc,o.observation_sha256 LIMIT 50001");
        if(count($rows)>50000)throw new RuntimeException('observation_cap_incomplete');
        $current=msu_query($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN (".implode(',',array_fill(0,90,'?')).")",$ids);
        $targets=[];foreach($current as $r){$source=msu_source((string)$r['evidence_json']);$r['evidence_hash_valid']=hash_equals((string)$r['evidence_sha256'],hash('sha256',(string)$r['evidence_json']));unset($r['evidence_json']);$targets[(string)$r['external_hotel_id']]=$r+$source;}
        $db->rollBack();$groups=[];$unsafe=[];$wanted=array_fill_keys($ids,true);$rawTargetCount=0;
        foreach($rows as $r){$id=(string)$r['external_hotel_id'];if(isset($wanted[$id]))$rawTargetCount++;$u=msu_url($r['hotel_url']);$ops=msu_ops((string)$r['operator_refs_json']);unset($r['hotel_url'],$r['operator_refs_json']);
            if($u['reason']!=='hotel_specific'||count($ops)!==1||$ops[0]!==$u['operator_key']){$reason=$u['reason']!=='hotel_specific'?$u['reason']:'operator_url_namespace_ambiguous';$unsafe[$reason]=($unsafe[$reason]??0)+1;continue;}
            $r['url']=$u['url'];$r['canonical_url']=$u['canonical'];$r['operator_key']=$ops[0];$key=implode('|',[$id,$ops[0],$u['canonical'],$r['hotel_name'],$r['country_id']]);
            if(!isset($groups[$key])){$groups[$key]=array_diff_key($r,['observation_sha256'=>1,'search_evidence_sha256'=>1,'observed_at_utc'=>1]);$groups[$key]['observations']=[];}
            $groups[$key]['observations'][]=['observation_sha256'=>$r['observation_sha256'],'search_evidence_sha256'=>$r['search_evidence_sha256'],'observed_at_utc'=>$r['observed_at_utc']];
        }
        $peers=[];foreach($groups as $g)if($g['decision_status']==='accepted'&&$g['local_hotel_id']!==null&&(int)$g['is_active']===1&&(int)$g['local_country_id']===(int)$g['country_id']){$key=$g['operator_key'].'|'.$g['country_id'].'|'.$g['canonical_url'];$peers[$key][]=$g;}
        $matches=[];$targetGroups=[];$relevantPeers=[];
        foreach($groups as $g){$id=(string)$g['external_hotel_id'];if(!isset($wanted[$id]))continue;$targetGroups[]=$g;$key=$g['operator_key'].'|'.$g['country_id'].'|'.$g['canonical_url'];$found=[];
            foreach($peers[$key]??[] as $p)if((string)$p['external_hotel_id']!==$id){$found[]=$p;$relevantPeers[hash('sha256',json_encode($p,JSON_THROW_ON_ERROR))]=$p;}
            if(!$found)continue;$locals=array_values(array_unique(array_map(fn($p)=>(int)$p['local_hotel_id'],$found)));$state=$targets[$id]??[];
            $reason=count($locals)!==1?'multiple_local_url_collision':(($state['decision_status']??'')!=='pending'||($state['local_hotel_id']??null)!==null?'current_identity_protected':'needs_current_acceptance_review');
            if($reason==='needs_current_acceptance_review')foreach($found as $p)if(!msu_name_guard((string)$g['hotel_name'],(string)$p['local_name'])){$reason='name_or_qualifier_review';break;}
            $matches[]=['andromeda_hotel_id'=>$id,'operator_key'=>$g['operator_key'],'canonical_url'=>$g['canonical_url'],'country_id'=>(int)$g['country_id'],'hotel_name'=>$g['hotel_name'],'peer_andromeda_ids'=>array_values(array_unique(array_map(fn($p)=>(string)$p['external_hotel_id'],$found))),'candidate_local_ids'=>$locals,'reason'=>$reason,'accepted'=>false];
        }
        $result+=['snapshot_utc'=>gmdate('c'),'observations_examined'=>count($rows),'target_observations'=>$rawTargetCount,'safe_group_count'=>count($groups),'rejected_observation_reasons'=>$unsafe,'target_current_rows'=>array_values($targets),'target_url_groups'=>$targetGroups,'accepted_url_peers'=>array_values($relevantPeers),'url_candidates'=>$matches,'missing_current_ids'=>array_values(array_diff($ids,array_map('strval',array_keys($targets)))),'no_safe_url_target_ids'=>array_values(array_diff($ids,array_map(fn($g)=>(string)$g['external_hotel_id'],$targetGroups))),'acceptance_status'=>'read_only_evidence_no_mappings'];
        $result['state']='completed_read_only';
    }catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$result['state']='failed_read_only_no_retry';$result['error_class']=get_class($e);$result['reason']=preg_match('/^[A-Za-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'sanitized_error';}
    return $result;
}
function msu_test():void {
    $n=0;$t=static function(bool $ok)use(&$n){if(!$ok)throw new RuntimeException('test_'.$n);$n++;};
    $t(msu_url('https://intourist.ru/info/egypt/hotel/il-mercato-hotel')['operator_key']==='342');
    $t(msu_url('https://b2b.fstravel.com/hotel/18672')['operator_key']==='315');
    $t(msu_url('https://www.bgoperator.ru/price.shtml?action=shw&code=102610157233')['operator_key']==='115');
    foreach(['https://anextour.ru/','https://intourist.ru/search_tour?HOTELS=12114,12232','https://x:y@intourist.ru/hotel/a','file:///etc/passwd','https://intourist.ru.evil.test/hotel/a','https://intourist.ru/hotel/a?token=x','https://intourist.ru/hotel/a?%2574oken=x','https://intourist.ru/hotel/a?code[]=1','https://intourist.ru/hotel/a%0a'] as $u)$t(msu_url($u)['reason']!=='hotel_specific');
    $t(msu_url('https://intourist.ru/hotel/a#photos')['canonical']==='https://intourist.ru/hotel/a');$t(msu_ops('[342]')===['342']);$t(msu_ops('["342","342"]')===['342']);$t(msu_ops('["342",999]')===[]);
    $t(msu_name_guard('Raimond Hotel','RAIMOND HOTEL KUMKAPI'));$t(!msu_name_guard('Perdikia Beach','Perdikia Hill'));$t(!msu_name_guard('Greenport','Palm Garden'));$t(!msu_name_guard('Отель','Other'));
    $t(count(explode(',',MSU_IDS))===90);$t(hash('sha256',MSU_IDS)==='66a0b477270e3c534bc8fda276f767e9d6f37de27c9942075820a538e7870544');
    echo "$n saved-URL tests PASS; no database or supplier calls\n";
}
if(($argv[1]??'')==='--self-test'){msu_test();exit;}
$r=msu_run();echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";exit($r['state']==='completed_read_only'?0:2);
