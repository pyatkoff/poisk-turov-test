<?php
declare(strict_types=1);

putenv('MATCH_STATE_ACCEPTED_COUNTRY_TEST_LIBRARY=1');
require_once __DIR__ . '/hotel_match_state_accepted_country_consensus.php';

const MANB_OP = 'hotel-match-accepted-name-bridge-review-1971-20260915-v1';

function manb_json(array $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function manb_query(PDO $db,string $sql,array $args=[]): array { $q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC); }
function manb_write(string $file,array $v): string { $raw=manb_json($v);$f=@fopen($file,'x+b');if(!$f)throw new RuntimeException('exclusive_file');try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('readback');}finally{fclose($f);}return hash('sha256',$raw); }
function manb_weak_key(string $raw,array $hotel): ?string {
    $norm=mcr_norm($raw); if($norm==='')return'empty';
    $tokens=mcr_tokens($raw); if(count($tokens)<2)return'single_token';
    if(strlen($norm)<6)return'too_short';
    $geo=[];foreach(['country_name','region_name','subregion_name'] as $k){$g=mcr_norm((string)($hotel[$k]??''));if($g!=='')$geo[$g]=true;}
    if(isset($geo[$norm]))return'geography_only';
    $hasAlpha=false;foreach($tokens as $t)if(preg_match('/\p{L}/u',$t)){$hasAlpha=true;break;}if(!$hasAlpha)return'numeric_only';
    return null;
}
function manb_build_anchors(array $accepted,array $hotels): array {
    $raw=[];$weak=[];
    foreach($accepted as $r){$local=(int)($r['local_hotel_id']??0);$cid=(int)($r['local_country_id']??0);if($local<=0||$cid<=0||!isset($hotels[$local]))continue;$e=mcr_evidence((string)($r['evidence_json']??''));foreach(mcr_names($e) as $name){$norm=mcr_norm((string)$name);if($norm==='')continue;$why=manb_weak_key((string)$name,$hotels[$local]);if($why!==null){$weak[$cid][$norm][$why]=true;continue;}$raw[$cid][$norm][$local]['accepted_ids'][(string)$r['external_hotel_id']]=true;$raw[$cid][$norm][$local]['raw_names'][(string)$name]=true;}}
    $usable=[];$ambiguous=[];
    foreach($raw as $cid=>$keys)foreach($keys as $norm=>$targets){
        if(count($targets)!==1){$ambiguous[(int)$cid][$norm]=array_map('intval',array_keys($targets));continue;}
        $local=(int)array_key_first($targets);$x=$targets[$local];$usable[(int)$cid][$norm]=[
            'local_hotel_id'=>$local,
            'local_hotel_name'=>(string)($hotels[$local]['name']??''),
            'anchor_count'=>count($x['accepted_ids']??[]),
            'accepted_external_ids'=>array_values(array_keys($x['accepted_ids']??[])),
            'accepted_raw_names'=>array_values(array_keys($x['raw_names']??[])),
        ];
    }
    return ['usable'=>$usable,'ambiguous'=>$ambiguous,'weak'=>$weak];
}
function manb_select(array $names,int $cid,array $anchors): array {
    $hits=[];$hitNames=[];$single=[];
    foreach($names as $raw){$norm=mcr_norm((string)$raw);if($norm==='')continue;if(isset($anchors['usable'][$cid][$norm])){$a=$anchors['usable'][$cid][$norm];$local=(int)$a['local_hotel_id'];$hits[$local]=$a;$hitNames[$local][(string)$raw]=true;}elseif(count(mcr_tokens((string)$raw))===1){$single[$norm]=true;}}
    if(!$hits){return['route'=>'needs_extra_evidence','reason'=>$single?'accepted_name_single_token_not_authoritative':'no_unique_accepted_name_anchor','single_token_keys'=>array_values(array_keys($single))];}
    if(count($hits)!==1)return['route'=>'hard_conflict','reason'=>'accepted_name_anchors_disagree','targets'=>array_map('intval',array_keys($hits))];
    $local=(int)array_key_first($hits);$a=$hits[$local];
    return ['route'=>'auto_accept_candidate','reason'=>'exact_provider_name_to_unique_current_accepted_local','target'=>$local,'target_name'=>$a['local_hotel_name'],'anchor_count'=>(int)$a['anchor_count'],'accepted_external_ids'=>$a['accepted_external_ids'],'accepted_raw_names'=>$a['accepted_raw_names'],'matched_pending_names'=>array_values(array_keys($hitNames[$local]??[]))];
}

if(getenv('MATCH_ACCEPTED_NAME_BRIDGE_TEST_LIBRARY')==='1')return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$op=(string)getenv('MATCH_OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');if($op!==MANB_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('operation_or_source_guard');$home=(string)getenv('HOME');if($home==='')throw new RuntimeException('home');$dir=$home.'/.anytoour-match/operations/'.MANB_OP;$res=mcr_evidence((string)@file_get_contents($dir.'/reservation.json'));if(($res['operation_id']??'')!==MANB_OP||($res['source_sha']??'')!==$sha||($res['state']??'')!=='reserved_before_db_access')throw new RuntimeException('reservation');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
    $core=[];foreach(manb_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c)if(mcr_is_core8_name((string)$c['name']))$core[(int)$c['id']]=(string)$c['name'];if(count($core)<6)throw new RuntimeException('core8');$marks=implode(',',array_fill(0,count($core),'?'));
    $hotels=[];foreach(manb_query($db,"SELECT id,country_id,country_name,region_name,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ($marks) ORDER BY country_id,id",array_keys($core)) as $h)$hotels[(int)$h['id']]=$h;
    $all=manb_query($db,"SELECT external_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id");
    $accepted=manb_query($db,"SELECT i.external_hotel_id,i.local_hotel_id,i.evidence_json,h.country_id AS local_country_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id AND h.is_active=1 WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL ORDER BY i.external_hotel_id");
    $cons=msac_accepted_country_consensus($accepted,$all,$core);$inferred=$cons['inferred'];$anchors=manb_build_anchors($accepted,$hotels);
    $pending=manb_query($db,"SELECT supplier_namespace,external_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");
    $routes=[];$candidates=[];$reason=[];$covered=0;$hitFreq=0;
    foreach($pending as $r){$e=mcr_evidence((string)$r['evidence_json']);$sk=mcr_state_key($e);if($sk===null||!isset($inferred[$sk]))continue;$cid=(int)$inferred[$sk]['country_id'];$sel=manb_select(mcr_names($e),$cid,$anchors);$item=array_merge(['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)$r['external_hotel_id'],'evidence_sha256'=>(string)$r['evidence_sha256'],'state_key'=>$sk,'inferred_country_id'=>$cid,'frequency'=>mcr_frequency($e)],$sel);$routes[$item['route']][]=$item;$reason[$item['reason']]=($reason[$item['reason']]??0)+1;if($item['route']==='auto_accept_candidate'){$covered++;$hitFreq+=(int)$item['frequency'];$candidates[]=$item;}}
    foreach($routes as &$ls)usort($ls,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));unset($ls);usort($candidates,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));ksort($routes);ksort($reason);
    $usableKeys=0;$ambiguousKeys=0;$weakKeys=0;foreach($anchors['usable'] as $x)$usableKeys+=count($x);foreach($anchors['ambiguous'] as $x)$ambiguousKeys+=count($x);foreach($anchors['weak'] as $x)$weakKeys+=count($x);
    $result=['schema'=>'hotel-match-accepted-name-bridge-review/1','operation_id'=>MANB_OP,'source_sha'=>$sha,'state'=>'completed_read_only','server_current'=>true,'transaction'=>'REPEATABLE READ READ ONLY','accepted_andromeda_rows'=>count($accepted),'pending_andromeda_rows'=>count($pending),'inferred_state_count'=>count($inferred),'usable_unique_accepted_name_keys'=>$usableKeys,'ambiguous_accepted_name_keys'=>$ambiguousKeys,'weak_accepted_name_keys'=>$weakKeys,'candidate_count'=>count($candidates),'candidate_live_frequency_sum'=>$hitFreq,'candidates'=>$candidates,'route_counts'=>array_map('count',$routes),'reason_counts'=>$reason,'routes'=>$routes,'supplier_calls'=>0,'tourvisor_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'mapping_writes'=>0,'operator_5_writes'=>0,'no_replay'=>true,'created_at'=>gmdate('c')];$hash=manb_write($dir.'/result.json',$result);$raw=(string)file_get_contents($dir.'/result.json');$x=mcr_evidence($raw);if(hash('sha256',$raw)!==$hash||($x['operation_id']??'')!==MANB_OP||($x['state']??'')!=='completed_read_only')throw new RuntimeException('result_readback');manb_write($dir.'/receipt.json',['operation_id'=>MANB_OP,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$hash,'readback_verified'=>true,'no_replay'=>true,'created_at'=>gmdate('c')]);$db->rollBack();echo 'MATCH_ACCEPTED_NAME_BRIDGE_REVIEW_OK '.count($candidates)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
