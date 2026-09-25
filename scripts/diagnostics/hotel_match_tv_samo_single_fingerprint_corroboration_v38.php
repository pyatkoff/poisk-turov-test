<?php
declare(strict_types=1);

const V38_OP='hotel-match-tv-samo-single-fingerprint-corroboration-1971-20260925-v38';
const V38_V37_OP='hotel-match-tv-samo-common4-fingerprint-join-1971-20260925-v37';
const V38_V37_SHA='b7ef6082b8d27ad822ddaf69dd86e9249523f859cd61ee1b547c106118ffc55a';

function v38_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v38_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v38_load(string $p):array{$raw=(string)file_get_contents($p);$v=json_decode($raw,true,512,JSON_THROW_ON_ERROR);v38_need(is_array($v),'json_shape');return $v;}
function v38_save(string $p,array $v):string{$raw=v38_json($v)."\n";$f=@fopen($p,'x+b');v38_need($f!==false,'exclusive_create');try{v38_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v38_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v38_id(mixed $v):?string{$s=trim((string)$v);return preg_match('/^[1-9][0-9]{0,21}$/D',$s)===1?$s:null;}
function v38_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function v38_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v38_norm(mixed $v):string{$s=mb_strtolower(trim((string)$v),'UTF-8');$s=str_replace('ё','е',$s);$s=strtr($s,['é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);$s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;return trim(preg_replace('/\s+/u',' ',$s)??$s);}
function v38_name_key(mixed $v):string{$generic=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'by'=>1,'резорт'=>1,'ресорт'=>1,'спа'=>1];$p=[];foreach(preg_split('/\s+/u',v38_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[] as $x)if(!isset($generic[$x]))$p[$x]=true;$k=array_keys($p);sort($k,SORT_STRING);return implode(' ',$k);}
function v38_place_key(mixed $v):string{$n=v38_norm($v);$n=preg_replace('/\b(?:центр|center|centre|город|city|остров|island)\b/u',' ',$n)??$n;return trim(preg_replace('/\s+/u',' ',$n)??$n);}
function v38_country(mixed $v):?string{$n=v38_norm($v);if($n==='')return null;$groups=['egypt'=>['egypt','египет'],'thailand'=>['thailand','таиланд','тайланд'],'turkey'=>['turkey','turkiye','türkiye','турция'],'maldives'=>['maldives','мальдивы'],'uae'=>['united arab emirates','uae','оаэ','эмираты'],'cuba'=>['cuba','куба'],'sri_lanka'=>['sri lanka','шри ланка'],'vietnam'=>['vietnam','viet nam','вьетнам'],'qatar'=>['qatar','катар'],'china'=>['china','китай'],'mauritius'=>['mauritius','маврикий'],'seychelles'=>['seychelles','сейшелы'],'morocco'=>['morocco','марокко'],'tunisia'=>['tunisia','тунис'],'tanzania'=>['tanzania','танзания'],'uzbekistan'=>['uzbekistan','узбекистан'],'philippines'=>['philippines','филиппины'],'india'=>['india','индия'],'indonesia'=>['indonesia','индонезия'],'greece'=>['greece','греция'],'cyprus'=>['cyprus','кипр'],'georgia'=>['georgia','грузия'],'armenia'=>['armenia','армения'],'spain'=>['spain','испания'],'italy'=>['italy','италия'],'france'=>['france','франция'],'austria'=>['austria','австрия'],'germany'=>['germany','германия']];foreach($groups as $k=>$vals)foreach($vals as $x)if($n===v38_norm($x))return $k;return $n;}
function v38_point(array $x):?array{foreach([['latitude','longitude'],['lat','lng'],['lat','lon']] as [$a,$b]){if(!array_key_exists($a,$x)||!array_key_exists($b,$x))continue;if(!is_numeric($x[$a])||!is_numeric($x[$b]))continue;$lat=(float)$x[$a];$lon=(float)$x[$b];if(abs($lat)<=90&&abs($lon)<=180&&($lat!=0.0||$lon!=0.0))return[$lat,$lon];}return null;}
function v38_dist(array $a,array $b):float{[$lat1,$lon1]=$a;[$lat2,$lon2]=$b;$p1=deg2rad($lat1);$p2=deg2rad($lat2);$dp=$p2-$p1;$dl=deg2rad($lon2-$lon1);$x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return 6371000*2*asin(min(1,sqrt($x)));}
function v38_candidates(string $path):array{
    $raw=(string)file_get_contents($path);v38_need(hash('sha256',$raw)===V38_V37_SHA,'v37_hash');$r=json_decode($raw,true,256,JSON_THROW_ON_ERROR);
    v38_need(is_array($r)&&($r['operation']??'')===V38_V37_OP&&($r['state']??'')==='completed_read_only_tv_samo_common4_fingerprint_join','v37_state');
    v38_need((int)($r['single_direct_review_count']??-1)===9&&(int)($r['support_only_review_count']??-1)===1,'v37_counts');
    $out=[];foreach($r['rows']??[] as $row){if(!is_array($row))continue;$s=(string)($row['status']??'');if(!in_array($s,['single_direct','support_only'],true))continue;$cid=v38_id($row['andromeda_catalog_id']??null);$local=(int)($row['candidate_local_hotel_id']??0);v38_need($cid!==null&&$local>0,'candidate_identity');$k=$cid.'|'.$local;v38_need(!isset($out[$k]),'candidate_duplicate');$out[$k]=['andromeda_catalog_id'=>$cid,'local_hotel_id'=>$local,'v37_status'=>$s,'frontier_bucket'=>(string)($row['frontier_bucket']??''),'direct_operator_count'=>(int)($row['direct_operator_count']??0),'support_operator_count'=>(int)($row['support_operator_count']??0)];}
    v38_need(count($out)===10,'candidate_count');return array_values($out);
}
function v38_private_config(string $root):array{foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $p){if(!is_file($p)||is_link($p))continue;$v=require$p;if(is_array($v)&&is_string($v['catalog_path']??null)&&$v['catalog_path']!=='')return$v;}throw new RuntimeException('andromeda_private_config_missing');}
function v38_dict_name(array $payload,string $key,mixed $id):array{$want=v38_id($id);if($want===null)return[];$out=[];foreach(($payload[$key]??[]) as $x){if(!is_array($x))continue;$xid=v38_id($x['id']??$x['key']??$x['value']??null);if($xid!==$want)continue;foreach(['name','lName','russianName','title'] as $f)if(trim((string)($x[$f]??''))!=='')$out[]=(string)$x[$f];}return$out;}
function v38_merge_fact(array &$f,string $kind,mixed $v):void{$s=trim((string)$v);if($s==='')return;if($kind==='name'){$k=v38_name_key($s);if($k!=='')$f['names'][$k]=$s;}elseif($kind==='country'){$k=v38_country($s);if($k!==null)$f['countries'][$k]=$s;}elseif($kind==='place'){$k=v38_place_key($s);if($k!=='')$f['places'][$k]=$s;}}
function v38_extract_object(array $o,array $payload,?int $stateInc):array{
    $f=['names'=>[],'countries'=>[],'places'=>[],'points'=>[]];
    foreach(['name','lName','hotelName','hotelLName','russianName','title'] as $k)if(isset($o[$k]))v38_merge_fact($f,'name',$o[$k]);
    foreach(['country','countryName','countryLName','stateName','stateLName'] as $k)if(isset($o[$k])&&!is_numeric($o[$k]))v38_merge_fact($f,'country',$o[$k]);
    foreach(['townName','townLName','region','regionName','regionLName','resort','resortName','subregion','subregionName','place'] as $k)if(isset($o[$k])&&!is_numeric($o[$k]))v38_merge_fact($f,'place',$o[$k]);
    $state=$o['stateKey']??$o['state']??$stateInc;foreach(v38_dict_name($payload,'STATES',$state) as $x)v38_merge_fact($f,'country',$x);
    $town=$o['townKey']??$o['town']??null;foreach(v38_dict_name($payload,'TOWNS',$town) as $x)v38_merge_fact($f,'place',$x);
    $region=$o['regionKey']??null;foreach(v38_dict_name($payload,'REGIONS',$region) as $x)v38_merge_fact($f,'place',$x);
    if(($pt=v38_point($o))!==null)$f['points'][sprintf('%.6f|%.6f',$pt[0],$pt[1])]=$pt;
    return$f;
}
function v38_walk_catalog(mixed $node,array $wanted,array $payload,?int $stateInc,array &$facts,int &$nodes,int $depth=0):void{
    if($depth>18||!is_array($node))return;if(++$nodes>2000000)throw new RuntimeException('catalog_node_cap');
    if(!array_is_list($node)){
        $ids=[];foreach(['id','hotelKey','hotel_id','hotelId','key'] as $k){$id=v38_id($node[$k]??null);if($id!==null)$ids[$id]=true;}
        foreach(array_keys($ids) as $id)if(isset($wanted[$id])){$x=v38_extract_object($node,$payload,$stateInc);foreach(['names','countries','places','points'] as $k)foreach($x[$k] as $kk=>$vv)$facts[$id][$k][$kk]=$vv;}
    }
    foreach($node as $v)if(is_array($v))v38_walk_catalog($v,$wanted,$payload,$stateInc,$facts,$nodes,$depth+1);
}
function v38_source_facts(string $catalogPath,array $candidates):array{
    $wanted=[];foreach($candidates as $c)$wanted[$c['andromeda_catalog_id']]=true;$facts=[];$files=[];
    if(is_file($catalogPath)&&!is_link($catalogPath))$files[]=$catalogPath;foreach(glob(dirname($catalogPath).'/countries/*.json')?:[] as $p)if(is_file($p)&&!is_link($p))$files[]=$p;$files=array_values(array_unique($files));sort($files,SORT_STRING);v38_need(count($files)<=500,'catalog_file_cap');
    $parsed=0;$nodes=0;foreach($files as $p){$size=filesize($p);if($size===false||$size<2||$size>33554432)continue;try{$d=json_decode((string)file_get_contents($p),true,64,JSON_THROW_ON_ERROR);}catch(Throwable){continue;}if(!is_array($d))continue;$parsed++;$all=$d['all']??[];$payload=is_array($all)&&is_array($all['payload']??null)?$all['payload']:[];$stateInc=is_array($all)&&is_array($all['params']??null)&&is_numeric($all['params']['STATEINC']??null)?(int)$all['params']['STATEINC']:null;v38_walk_catalog($d,$wanted,$payload,$stateInc,$facts,$nodes);}
    foreach(array_keys($wanted) as $id)if(!isset($facts[$id]))$facts[$id]=['names'=>[],'countries'=>[],'places'=>[],'points'=>[]];
    return['facts'=>$facts,'files_examined'=>count($files),'files_parsed'=>$parsed,'nodes_examined'=>$nodes];
}
function v38_local_fact(array $h,array $aliases):array{$names=[];foreach([(string)($h['name']??''),(string)($h['normalized_name']??'')] as $n){$k=v38_name_key($n);if($k!=='')$names[$k]=$n;}foreach($aliases as $n){$k=v38_name_key($n);if($k!=='')$names[$k]=$n;}$places=[];foreach([(string)($h['region_name']??''),(string)($h['subregion_name']??'')] as $p){$k=v38_place_key($p);if($k!=='')$places[$k]=$p;}$country=v38_country($h['country_name']??'');$pt=null;if(is_numeric($h['latitude']??null)&&is_numeric($h['longitude']??null)){$pt=[(float)$h['latitude'],(float)$h['longitude']];if($pt[0]==0.0&&$pt[1]==0.0)$pt=null;}return['names'=>$names,'places'=>$places,'country'=>$country,'point'=>$pt];}
function v38_evidence_ok(array $rows):bool{foreach($rows as $r){if(($r['decision_status']??'')!=='accepted')continue;$raw=(string)($r['evidence_json']??'');$eh=(string)($r['evidence_sha256']??'');$ch=(string)($r['catalog_sha256']??'');if(!v38_sha($eh)||!v38_sha($ch)||hash('sha256',$raw)!==$eh)return false;}return true;}
function v38_geo(array $src,array $local):array{$place=(bool)array_intersect_key($src['places']??[],$local['places']??[]);$best=null;foreach($src['points']??[] as $a)if(is_array($a)&&$local['point']!==null){$d=v38_dist($a,$local['point']);if($best===null||$d<$best)$best=$d;}$ok=$place||($best!==null&&$best<=5000);return['ok'=>$ok,'place_match'=>$place,'distance_m'=>$best===null?null:round($best,1)];}
function v38_execute(PDO $db,string $root,string $v37Path,string $sourceSha):array{
    $candidates=v38_candidates($v37Path);$cfg=v38_private_config($root);$srcPack=v38_source_facts((string)$cfg['catalog_path'],$candidates);$src=$srcPack['facts'];
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];foreach(v38_query($db,"SELECT h.id,h.name,h.normalized_name,h.country_name,h.region_name,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.is_active=1 ORDER BY h.id") as $r)$hotels[(int)$r['id']]=$r;
        $aliases=[];foreach(v38_query($db,"SELECT a.hotel_id,a.alias,a.normalized_alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 ORDER BY a.hotel_id,a.id") as $r){$id=(int)$r['hotel_id'];foreach([$r['alias'],$r['normalized_alias']] as $x)if(trim((string)$x)!=='')$aliases[$id][]=(string)$x;}
        $identity=v38_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id,local_hotel_id");
        $bySource=[];$byTarget=[];$targetOps=[];foreach($identity as $r){$ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];$bySource[$ns.'|'.$ext][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null){$l=(int)$r['local_hotel_id'];$byTarget[$ns.'|'.$l][]=$r;if(in_array($ns,['operator_5','operator_115','operator_315','operator_342'],true))$targetOps[$l][$ns]=true;}}
        $manual=[];foreach(v38_query($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IS NOT NULL") as $r)$manual[(int)$r['catalog_hotel_id']]=true;
        $db->rollBack();

        $localFacts=[];foreach($hotels as $id=>$h)$localFacts[$id]=v38_local_fact($h,$aliases[$id]??[]);
        $rows=[];$counts=[];$buckets=[];
        foreach($candidates as $c){$cid=$c['andromeda_catalog_id'];$target=$c['local_hotel_id'];$s=$src[$cid]??['names'=>[],'countries'=>[],'places'=>[],'points'=>[]];$lf=$localFacts[$target]??null;$status='review_missing_target';$reason=null;$nameMatch=false;$countryMatch=false;$geo=['ok'=>false,'place_match'=>false,'distance_m'=>null];$matches=[];
            $sourceRows=$bySource['andromeda_catalog|'.$cid]??[];$targetRows=$byTarget['andromeda_catalog|'.$target]??[];$sourceAccepted=array_values(array_filter($sourceRows,fn($r)=>($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null));$targetOther=array_values(array_filter($targetRows,fn($r)=>(string)($r['external_hotel_id']??'')!==$cid));
            $evidenceOk=v38_evidence_ok(array_merge($sourceRows,$targetRows));
            if($sourceAccepted){$targets=array_values(array_unique(array_map(fn($r)=>(int)$r['local_hotel_id'],$sourceAccepted)));sort($targets,SORT_NUMERIC);if(count($targets)===1&&$targets[0]===$target)$status='resolved_same';else$status='hold_source_occupied';}
            elseif($targetOther)$status='hold_target_catalog_occupied';
            elseif(isset($manual[$target]))$status='hold_manual_target';
            elseif(!$evidenceOk)$status='hold_invalid_accepted_evidence';
            elseif($lf===null)$status='hold_target_inactive_or_missing';
            else{
                $nameMatch=(bool)array_intersect_key($s['names']??[],$lf['names']);
                $countryMatch=$lf['country']!==null&&isset(($s['countries']??[])[$lf['country']]);
                $geo=v38_geo($s,$lf);
                foreach($localFacts as $id=>$x){if($x['country']===null||!isset(($s['countries']??[])[$x['country']]))continue;if(!array_intersect_key($s['names']??[],$x['names']))continue;$g=v38_geo($s,$x);if($g['ok'])$matches[]=(int)$id;}
                sort($matches,SORT_NUMERIC);$matches=array_values(array_unique($matches));$mutual=count($matches)===1&&$matches[0]===$target;
                if($c['v37_status']==='support_only')$status='support_only_review';
                elseif(($s['names']??[])===[]||($s['countries']??[])===[]||(($s['places']??[])===[]&&($s['points']??[])===[]))$status='review_source_facts_incomplete';
                elseif(!$nameMatch)$status='review_name_mismatch';
                elseif(!$countryMatch)$status='review_country_mismatch';
                elseif(!$geo['ok'])$status='review_geo_mismatch';
                elseif(!$mutual)$status='review_not_mutual_unique';
                else$status='corroborated_single_direct';
            }
            $counts[$status]=($counts[$status]??0)+1;$b=$c['frontier_bucket'];$buckets[$b][$status]=($buckets[$b][$status]??0)+1;
            $rows[]=$c+['status'=>$status,'source_name_keys'=>array_keys($s['names']??[]),'source_country_keys'=>array_keys($s['countries']??[]),'source_place_keys'=>array_keys($s['places']??[]),'source_point_count'=>count($s['points']??[]),'target_name'=>(string)($hotels[$target]['name']??''),'target_country'=>(string)($hotels[$target]['country_name']??''),'target_region'=>(string)($hotels[$target]['region_name']??''),'name_match'=>$nameMatch,'country_match'=>$countryMatch,'geo_match'=>$geo,'mutual_match_local_ids'=>$matches,'accepted_evidence_hashes_valid'=>$evidenceOk,'current_target_operator_namespaces'=>array_keys($targetOps[$target]??[]),'safe_to_write_now'=>false];
        }
        ksort($counts);ksort($buckets);foreach($buckets as &$x)ksort($x);unset($x);usort($rows,fn($a,$b)=>[$a['status'],$a['local_hotel_id'],$a['andromeda_catalog_id']]<=>[$b['status'],$b['local_hotel_id'],$b['andromeda_catalog_id']]);
        return['operation'=>V38_OP,'state'=>'completed_read_only_single_fingerprint_corroboration','generated_at_utc'=>gmdate('c'),'source_sha'=>$sourceSha,'v37_operation'=>V38_V37_OP,'v37_result_sha256'=>V38_V37_SHA,'input_candidate_count'=>count($candidates),'source_catalog_scan'=>['files_examined'=>$srcPack['files_examined'],'files_parsed'=>$srcPack['files_parsed'],'nodes_examined'=>$srcPack['nodes_examined']],'status_counts'=>$counts,'bucket_status_counts'=>$buckets,'corroborated_count'=>(int)($counts['corroborated_single_direct']??0),'rows'=>$rows,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v38_self_test():void{$s=['names'=>['abc'=>'ABC'],'countries'=>['turkey'=>'Turkey'],'places'=>['side'=>'Side'],'points'=>[]];$l=['names'=>['abc'=>'ABC Hotel'],'country'=>'turkey','places'=>['side'=>'Side'],'point'=>null];v38_need((bool)array_intersect_key($s['names'],$l['names']),'name');v38_need(isset($s['countries'][$l['country']]),'country');v38_need(v38_geo($s,$l)['ok']===true,'geo');v38_need(v38_name_key('The ABC Hotel & Spa')==='abc','name_key');}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){if(in_array('--self-test',$argv??[],true)){v38_self_test();echo"MATCH_TV_SAMO_SINGLE_FINGERPRINT_CORROBORATION_V38_SELFTEST_OK\n";exit;}v38_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$v37=(string)getenv('MATCH_V37_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');v38_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V38_OP&&is_file($v37)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');$reservation=v38_load($dir.'/reservation.json');v38_need(($reservation['operation']??'')===V38_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');try{$r=v38_execute(v2_data_db(),$root,$v37,$sha);$h=v38_save($dir.'/result.json',$r);v38_save($dir.'/receipt.json',['operation'=>V38_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v38_json(['state'=>$r['state'],'input_candidate_count'=>$r['input_candidate_count'],'corroborated_count'=>$r['corroborated_count'],'status_counts'=>$r['status_counts'],'bucket_status_counts'=>$r['bucket_status_counts'],'source_catalog_scan'=>$r['source_catalog_scan']])."\n";}catch(Throwable$e){$f=['operation'=>V38_OP,'state'=>'failed_read_only_single_fingerprint_corroboration','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=v38_save($dir.'/result.json',$f);v38_save($dir.'/receipt.json',['operation'=>V38_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}}
