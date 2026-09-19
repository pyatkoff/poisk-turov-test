<?php
declare(strict_types=1);
// MATCH #1971: retained candidates only. This program cannot accept a mapping.
const HM12_OPERATION='hotel-match-saved12-current-1971-20260919-v1';
const HM12_TARGETS=['190031'=>1441,'2000106034'=>65341,'2000050280'=>70458,'2000091832'=>88168,'2000072026'=>9446,'2000086139'=>79837,'2000071081'=>96954,'2000074084'=>46680,'304393'=>63524,'2000037910'=>59126,'2000050680'=>60804,'2000021340'=>60804];
const HM12_ANEX=[8365,10241,23082,24299,26359,31365,32549,32970,34797,35726,35844,36169,42297];
function hm12_require(bool $ok,string $reason):void{if(!$ok)throw new RuntimeException($reason);}
function hm12_json(array $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";}
function hm12_save(string $path,array $value):string{
    $raw=hm12_json($value);$f=fopen($path,'xb');hm12_require(is_resource($f),'exclusive_output');
    try{hm12_require(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))hm12_require(fsync($f),'output_sync');}finally{fclose($f);}
    $hash=hash('sha256',$raw);hm12_require(hash_file('sha256',$path)===$hash,'output_readback');return $hash;
}
function hm12_rows(PDO $db,string $sql,array $params=[],int $cap=50000):array{
    hm12_require(preg_match('/^SELECT\s/i',$sql)===1,'select_only');$s=$db->prepare($sql);$s->execute($params);$rows=$s->fetchAll(PDO::FETCH_ASSOC);hm12_require(count($rows)<=$cap,'read_cap');return $rows;
}
function hm12_status(?array $row,string $source,int $target,?int $effective):string{
    if($row===null)return 'source_absent';
    hm12_require(($row['supplier_namespace']??null)==='andromeda_catalog'&&(string)($row['external_hotel_id']??'')===$source,'exact_namespace');
    $status=$row['decision_status']??'';$local=$row['local_hotel_id']??null;
    if($status==='accepted'){
        if($local===null||$effective===null||(int)$local!==$effective)return 'accepted_resolver_mismatch';
        return $effective===$target?'already_effectively_mapped':'accepted_other_target_protected';
    }
    if($status==='pending'&&$local===null)return 'pending_unassigned_needs_evidence';
    return 'existing_state_protected';
}
function hm12_evidence(mixed $value,int $depth=0):mixed{
    if($depth>8)return '[depth-limited]';
    if(!is_array($value))return is_string($value)&&(strlen($value)>600||preg_match('~https?://|@|Bearer\s~i',$value))?'[redacted]':$value;
    $out=[];$n=0;foreach($value as $key=>$item){if(++$n>150){$out['_truncated']=true;break;}
        $out[$key]=is_string($key)&&preg_match('/auth|token|secret|password|passenger|tourist|email|phone|decided_by|cookie|session|credential|url|link/i',$key)?'[redacted]':hm12_evidence($item,$depth+1);
    }return $out;
}
if(in_array('--self-test',$argv??[],true)){
    $n=0;$check=function(bool $ok)use(&$n):void{hm12_require($ok,'selftest_'.(++$n));};
    $check(count(HM12_TARGETS)===12&&count(array_unique(HM12_TARGETS))===11);
    $check(count(HM12_ANEX)===13&&HM12_TARGETS['190031']===1441);
    $check(HM12_TARGETS['2000050680']===HM12_TARGETS['2000021340']);
    $row=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'190031','decision_status'=>'accepted','local_hotel_id'=>1441];
    $check(hm12_status(null,'190031',1441,null)==='source_absent');
    $check(hm12_status($row,'190031',1441,1441)==='already_effectively_mapped');
    $x=$row;$x['local_hotel_id']=999;$check(hm12_status($x,'190031',1441,999)==='accepted_other_target_protected');
    $check(hm12_status($row,'190031',1441,null)==='accepted_resolver_mismatch');
    $check(hm12_status($row,'190031',1441,999)==='accepted_resolver_mismatch');
    $x=$row;$x['decision_status']='pending';$x['local_hotel_id']=null;$check(hm12_status($x,'190031',1441,null)==='pending_unassigned_needs_evidence');
    foreach(['conflict','rejected','manual','unknown','']as$status){$x['decision_status']=$status;$check(hm12_status($x,'190031',1441,null)==='existing_state_protected');}
    $x=$row;$x['decision_status']='pending';$check(hm12_status($x,'190031',1441,null)==='existing_state_protected');
    foreach(['operator_315','operator_5','']as$ns){$x=$row;$x['supplier_namespace']=$ns;$failed=false;try{hm12_status($x,'190031',1441,null);}catch(RuntimeException $e){$failed=true;}$check($failed);}
    $failed=false;try{hm12_status($row,'8365',1441,null);}catch(RuntimeException $e){$failed=true;}$check($failed);
    $e=hm12_evidence(['hotel'=>'Ramada Plaza','token'=>'x','note'=>'https://example.test/?token=x','nested'=>['email'=>'a@b.test','stars'=>5]]);
    $check($e['hotel']==='Ramada Plaza'&&$e['token']==='[redacted]'&&$e['note']==='[redacted]'&&$e['nested']['email']==='[redacted]'&&$e['nested']['stars']===5);
    echo 'HM12_SELFTEST_OK checks='.$n."\n";exit(0);
}
hm12_require(PHP_SAPI==='cli','cli_only');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');$db=null;
hm12_require(is_dir($dir)&&!is_link($dir)&&basename($dir)===HM12_OPERATION&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'operation_path');
$base=['operation'=>HM12_OPERATION,'source_sha'=>$sha,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'accepted_delta'=>0,'triple_delta'=>0,'no_replay'=>true];
try{
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hm12_require(($reservation['operation']??null)===HM12_OPERATION&&($reservation['source_sha']??null)===$sha,'reservation');
    hm12_require(($reservation['reader_sha256']??'')===hash_file('sha256',__FILE__),'reader_hash');
    foreach(['andromeda-hotel-resolver.php','anex-search-mapping-registry.php']as$name){hm12_require(isset($reservation['dependencies'][$name])&&hash_file('sha256',$dir.'/'.$name)===$reservation['dependencies'][$name],'dependency_hash');require_once $dir.'/'.$name;}
    $root=realpath((string)getenv('ANYTOOUR_ROOT'));hm12_require(is_string($root)&&basename($root)==='anytoour.ru','root');
    $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');hm12_require(is_file($bootstrap),'db_bootstrap');require_once $bootstrap;
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    $all=hm12_rows($db,'SELECT i.supplier_namespace,i.external_hotel_id,i.local_hotel_id,i.decision_status,h.id AS existing_catalog_hotel_id FROM andromeda_hotel_identities i LEFT JOIN catalog_hotels h ON h.id=i.local_hotel_id ORDER BY i.supplier_namespace,i.external_hotel_id LIMIT 50001');
    $sources=array_map('strval',array_keys(HM12_TARGETS));$targets=array_values(array_unique(HM12_TARGETS));$sp=implode(',',array_fill(0,count($sources),'?'));$tp=implode(',',array_fill(0,count($targets),'?'));$ap=implode(',',array_fill(0,count(HM12_ANEX),'?'));
    $raw=hm12_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE (supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($sp)) OR local_hotel_id IN ($tp) OR (supplier_namespace='operator_5' AND external_hotel_id IN ($ap)) ORDER BY supplier_namespace,external_hotel_id LIMIT 3001",array_merge($sources,$targets,HM12_ANEX),3000);
    $hotels=hm12_rows($db,'SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE country_id=4 ORDER BY id LIMIT 30001',[],30000);
    $aliases=hm12_rows($db,'SELECT a.hotel_id,a.alias,a.source FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=4 ORDER BY a.hotel_id,a.alias,a.source LIMIT 100001',[],100000);
    $guards=[];foreach(['anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions']as$table){$guards[$table]=hm12_rows($db,"SELECT * FROM $table WHERE anex_hotel_id IN ($ap) OR catalog_hotel_id IN ($tp) ORDER BY anex_hotel_id,catalog_hotel_id LIMIT 10001",array_merge(HM12_ANEX,$targets),10000);}
    $index=[];$occupants=[];$projection=[];$statusCounts=[];
    foreach($all as$r){$key=$r['supplier_namespace'].':'.$r['external_hotel_id'];hm12_require(!isset($index[$key]),'duplicate_identity');$index[$key]=$r;$statusCounts[$r['decision_status']]=($statusCounts[$r['decision_status']]??0)+1;
        if($r['local_hotel_id']!==null)$occupants[(int)$r['local_hotel_id']][]=$r;
        if(in_array($r['decision_status'],['accepted','rejected'],true))$projection[]=['supplier_namespace'=>$r['supplier_namespace'],'external_hotel_id'=>$r['external_hotel_id'],'decision_status'=>$r['decision_status'],'catalog_hotel_id'=>$r['local_hotel_id'],'existing_catalog_hotel_id'=>$r['existing_catalog_hotel_id']];
    }
    $version=hash('sha256',hm12_json($projection));$resolver=AnyTourAndromedaHotelResolver::fromRows($projection,$version);$offers=[];
    foreach($sources as$s)$offers[]=['provider'=>'andromeda','selection_enabled'=>false,'local_hotel_id'=>null,'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$s];
    $page=$resolver->apply(['provider'=>'andromeda','selection_enabled'=>false,'offers'=>$offers]);$effective=[];foreach($page['offers']as$o)$effective[$o['external_hotel_id']]=$o['local_hotel_id'];
    $anex=AnyTourAnexSearchMappingRegistry::fromPdo($db);$anexEffective=[];foreach(HM12_ANEX as$id)$anexEffective[(string)$id]=$anex->resolve('anex_online',(string)$id,'preview');
    $details=[];foreach($raw as$r){$key=$r['supplier_namespace'].':'.$r['external_hotel_id'];$r['row_sha256']=hash('sha256',hm12_json($r));$r['evidence_bytes_sha256']=hash('sha256',(string)$r['evidence_json']);$e=json_decode((string)$r['evidence_json'],true);$r['evidence']=is_array($e)?hm12_evidence($e):['unparseable'=>true];unset($r['evidence_json']);$details[$key]=$r;}
    $relevant=$targets;foreach($sources as$s){$row=$index['andromeda_catalog:'.$s]??null;if($row!==null&&$row['local_hotel_id']!==null)$relevant[]=(int)$row['local_hotel_id'];}$relevant=array_values(array_unique($relevant));$rp=implode(',',array_fill(0,count($relevant),'?'));
    $facts=hm12_rows($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ($rp) ORDER BY id",$relevant,100);
    $readAt=gmdate('c');$db->rollBack();$capture=hm12_save($dir.'/capture.json',$base+['rows'=>$raw,'guards'=>$guards,'identity_projection_sha256'=>hash('sha256',hm12_json($all))]);
    $rows=[];$counts=[];foreach(HM12_TARGETS as$s=>$target){$s=(string)$s;hm12_require(array_key_exists($s,$effective),'resolver_missing');$key='andromeda_catalog:'.$s;$state=hm12_status($index[$key]??null,$s,$target,$effective[$s]);$counts[$state]=($counts[$state]??0)+1;$rows[]=['external_hotel_id'=>$s,'expected_tv_id'=>$target,'state'=>$state,'current_row'=>$details[$key]??null,'effective_local_id'=>$effective[$s],'target_occupants'=>$occupants[$target]??[],'safe_to_write_now'=>false];}
    $anchors=[];foreach(HM12_ANEX as$id){$key='operator_5:'.$id;$anchors[$key]=$details[$key]??null;}
    $out=$base+['state'=>'completed_read_only','read_at_utc'=>$readAt,'examined'=>count($rows),'unique_target_hotels'=>count($targets),'status_counts'=>$counts,'identity_count'=>count($all),'identity_status_counts'=>$statusCounts,'rows'=>$rows,'target_facts'=>$facts,'country_hotels'=>$hotels,'country_aliases'=>$aliases,'anex_guards'=>hm12_evidence($guards),'anex_effective'=>$anexEffective,'operator_5_rows'=>$anchors,'capture_sha256'=>$capture,'resolver_version'=>$version,'dependencies'=>$reservation['dependencies']];
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$reason=$e instanceof RuntimeException&&preg_match('/^[a-z0-9_]{1,80}$/D',$e->getMessage())?$e->getMessage():'read_failed';$out=$base+['state'=>'failed_read_only','reason'=>$reason];}
$hash=hm12_save($dir.'/result.json',$out);hm12_save($dir.'/receipt.json',$base+['state'=>$out['state'],'result_sha256'=>$hash,'readback_verified'=>true]);
echo hm12_json(['state'=>$out['state'],'result_sha256'=>$hash,'status_counts'=>$out['status_counts']??[],'reason'=>$out['reason']??null]);exit($out['state']==='completed_read_only'?0:2);
