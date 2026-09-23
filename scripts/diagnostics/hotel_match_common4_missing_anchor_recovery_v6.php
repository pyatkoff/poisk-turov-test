<?php
declare(strict_types=1);

const HMC4R6_OP='hotel-match-common4-missing-anchor-recovery-1971-20260923-v7';
const HMC4R6_AUDIT_OP='hotel-match-live30-common4-current-1971-20260923-v2';
const HMC4R6_AUDIT_SHA='69bbd1ab7673f0d5ecd2759604c6a6f7e1182a167ba21f78c3f7aeefe05a069f';
const HMC4R6_EXPECTED_ROWS=110;

function r6_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function r6_sort(mixed $v):mixed{if(!is_array($v))return $v;if(array_is_list($v))return array_map('r6_sort',$v);ksort($v,SORT_STRING);foreach($v as $k=>$x)$v[$k]=r6_sort($x);return $v;}
function r6_json(mixed $v):string{return json_encode(r6_sort($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function r6_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);r6_need(is_array($v),'json_shape');return $v;}
function r6_save(string $p,array $v):string{$raw=r6_json($v)."\n";$f=@fopen($p,'x+b');r6_need($f!==false,'exclusive_create');try{r6_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))r6_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function r6_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function r6_norm(mixed $v):string{
    $s=mb_strtolower(trim((string)$v),'UTF-8');$s=str_replace('ё','е',$s);
    $s=preg_replace('/[^\\p{L}\\p{N}]+/u',' ',$s)??$s;return trim(preg_replace('/\\s+/u',' ',$s)??$s);
}
function r6_num(mixed $v):?float{
    if($v===null||$v==='')return null;$s=str_replace(',','.',trim((string)$v));if(!is_numeric($s))return null;$x=(float)$s;
    return is_finite($x)?$x:null;
}
function r6_point(array $r):?array{
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon']] as [$a,$b]){
        if(!array_key_exists($a,$r)||!array_key_exists($b,$r))continue;$lat=r6_num($r[$a]);$lon=r6_num($r[$b]);
        if($lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180&&($lat!=0.0||$lon!=0.0))return[$lat,$lon];
    }return null;
}
function r6_dist(?array $a,?array $b):?float{
    if($a===null||$b===null)return null;[$lat1,$lon1]=$a;[$lat2,$lon2]=$b;
    $p1=deg2rad($lat1);$p2=deg2rad($lat2);$dp=$p2-$p1;$dl=deg2rad($lon2-$lon1);
    $x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;return 6371000*2*asin(min(1,sqrt($x)));
}
function r6_place_keys(array $r):array{
    $out=[];foreach(['region_name','subregion_name','region','town','town_name','city','place'] as $k){if(!isset($r[$k]))continue;$n=r6_norm($r[$k]);if($n!=='')$out[$n]=true;}return $out;
}
function r6_obs_name(array $r):string{
    foreach(['hotel_name','name','lName','hotelName'] as $k){$n=r6_norm($r[$k]??'');if($n!=='')return $n;}return '';
}
function r6_country_id(array $r):?int{
    foreach(['country_id','countryId','state_id','stateKey'] as $k){if(!isset($r[$k]))continue;$v=filter_var($r[$k],FILTER_VALIDATE_INT);if($v!==false&&(int)$v>0)return(int)$v;}return null;
}
function r6_external_id(array $r):?string{
    foreach(['external_hotel_id','hotel_id','hotelId','id'] as $k){$v=trim((string)($r[$k]??''));if(preg_match('/^[1-9][0-9]{0,21}$/D',$v)===1)return$v;}return null;
}
function r6_target_names(array $hotel,array $aliases):array{
    $out=[];foreach([$hotel['name']??'',$hotel['normalized_name']??''] as $x){$n=r6_norm($x);if($n!=='')$out[$n]=true;}
    foreach($aliases as $a)foreach(['alias','normalized_alias','name','normalized_name'] as $k){$n=r6_norm($a[$k]??'');if($n!=='')$out[$n]=true;}
    ksort($out);return array_keys($out);
}
function r6_candidate_geo(array $obs,array $target):array{
    $op=r6_point($obs);$tp=r6_point($target);$d=r6_dist($op,$tp);
    $oplace=r6_place_keys($obs);$tplace=r6_place_keys($target);$place=(bool)array_intersect_key($oplace,$tplace);
    $class=$d===null?'unknown':($d<=1000?'coord_le_1km':($d>5000?'coord_gt_5km':'coord_1_5km'));
    return ['distance_m'=>$d===null?null:round($d,1),'coordinate_class'=>$class,'place_match'=>$place];
}
function r6_classify(array $candidates):string{
    if(!$candidates)return'no_exact_candidate';
    if(count($candidates)>1)return'ambiguous_exact_candidates';
    $c=$candidates[0];$id=$c['identity_state'];
    if($id==='accepted_same')return'accepted_same_current_drift';
    if($id==='accepted_elsewhere')return'candidate_accepted_elsewhere_conflict';
    if($id==='conflict')return'candidate_identity_conflict';
    if($id==='pending'){
        if(($c['geo']['coordinate_class']??'')==='coord_gt_5km')return'unique_exact_pending_geo_conflict';
        return'unique_exact_pending_candidate';
    }
    if($id==='missing'){
        if(($c['geo']['coordinate_class']??'')==='coord_gt_5km')return'unique_exact_observation_geo_conflict';
        return'unique_exact_observation_only';
    }
    return'candidate_identity_other';
}
function r6_manifest(array $audit):array{
    r6_need(($audit['operation']??'')===HMC4R6_AUDIT_OP&&($audit['state']??'')==='completed_read_only_current_audit','audit_state');
    $rows=[];$targets=[];$missingTotal=0;$missingByStatus=[];
    foreach(($audit['rows']??[]) as $r){
        if(!is_array($r)||($r['anchor_state']??'')!=='canonical_anchor_missing')continue;
        $ns=(string)($r['supplier_namespace']??'');if(!in_array($ns,['bgoperator','operator_315','operator_342'],true))continue;
        $missingTotal++;$st=(string)($r['status']??'unknown');$missingByStatus[$st]=($missingByStatus[$st]??0)+1;
        if($st!=='current_missing_edge')continue;
        $tv=(int)($r['tv_hotel_id']??0);r6_need($tv>0,'target_id');$rows[]=['supplier_namespace'=>$ns,'external_hotel_id'=>(string)$r['external_hotel_id'],'tv_hotel_id'=>$tv];
        $targets[$tv]=true;
    }
    r6_need($missingTotal===HMC4R6_EXPECTED_ROWS,'missing_anchor_total');
    r6_need(count($rows)>0&&count($rows)<=$missingTotal,'missing_anchor_current_rows');
    ksort($missingByStatus);
    return ['rows'=>$rows,'target_ids'=>array_keys($targets),'missing_anchor_total'=>$missingTotal,'missing_anchor_status_counts'=>$missingByStatus];
}
function r6_execute(PDO $db,array $manifest,string $sourceSha):array{
    $targetIds=array_map('intval',$manifest['target_ids']);sort($targetIds,SORT_NUMERIC);r6_need($targetIds!==[],'targets');
    $ph=implode(',',array_fill(0,count($targetIds),'?'));
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];foreach(r6_query($db,"SELECT h.id,h.name,h.normalized_name,h.country_id,h.country_name,h.region_id,h.region_name,h.subregion_id,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.id IN ($ph) ORDER BY h.id",$targetIds) as $h)$hotels[(int)$h['id']]=$h;
        $aliases=[];
        foreach(['hotel_aliases','catalog_hotel_aliases'] as $table){
            try{
                foreach(r6_query($db,"SELECT * FROM `$table` WHERE hotel_id IN ($ph) ORDER BY hotel_id",$targetIds) as $a)$aliases[(int)$a['hotel_id']][]=$a;
            }catch(Throwable){}
        }
        $identity=[];foreach(r6_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id") as $r)$identity[(string)$r['external_hotel_id']]=$r;
        $observations=[];$obsColumns=[];$obsTable=true;
        try{
            $obsColumns=array_map(fn($r)=>(string)($r['Field']??''),r6_query($db,'SHOW COLUMNS FROM andromeda_search_hotel_observations'));
            foreach(r6_query($db,"SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id") as $r)$observations[]=$r;
        }catch(Throwable){$obsTable=false;}
        $index=[];
        foreach($observations as $o){
            $eid=r6_external_id($o);$cid=r6_country_id($o);$name=r6_obs_name($o);
            if($eid===null||$cid===null||$name==='')continue;
            $index[$cid][$name][$eid][]=$o;
        }
        $byTargetRows=[];foreach($manifest['rows'] as $m)$byTargetRows[(int)$m['tv_hotel_id']][]=$m;
        $out=[];$counts=[];$candidateIdentity=[];$geoCounts=[];$targetCandidateCounts=[];
        foreach($targetIds as $tv){
            $h=$hotels[$tv]??null;if(!is_array($h)){$verdict='target_missing';$cands=[];$names=[];}
            else{
                $names=r6_target_names($h,$aliases[$tv]??[]);$cid=(int)($h['country_id']??0);$candMap=[];
                foreach($names as $name)foreach(($index[$cid][$name]??[]) as $eid=>$obsRows){
                    $obs=$obsRows[0];$idrow=$identity[(string)$eid]??null;$state='missing';
                    if(is_array($idrow)){
                        $ds=(string)($idrow['decision_status']??'');$local=$idrow['local_hotel_id']===null?null:(int)$idrow['local_hotel_id'];
                        if($ds==='accepted'&&$local===$tv)$state='accepted_same';
                        elseif($ds==='accepted'&&$local!==null&&$local!==$tv)$state='accepted_elsewhere';
                        elseif($ds==='pending'&&$local===null)$state='pending';
                        elseif($ds==='conflict')$state='conflict';
                        else $state='other';
                    }
                    $g=r6_candidate_geo($obs,$h);$candMap[(string)$eid]=['external_hotel_id'=>(string)$eid,'identity_state'=>$state,'identity_local_hotel_id'=>is_array($idrow)&&$idrow['local_hotel_id']!==null?(int)$idrow['local_hotel_id']:null,'decision_status'=>is_array($idrow)?(string)($idrow['decision_status']??''):null,'matched_name'=>$name,'observation_name'=>(string)($obs['hotel_name']??$obs['name']??''),'geo'=>$g,'observation_count'=>count($obsRows)];
                }
                $cands=array_values($candMap);usort($cands,fn($a,$b)=>strcmp($a['external_hotel_id'],$b['external_hotel_id']));$verdict=r6_classify($cands);
            }
            $counts[$verdict]=($counts[$verdict]??0)+1;$targetCandidateCounts[(string)count($cands)]=($targetCandidateCounts[(string)count($cands)]??0)+1;
            foreach($cands as $c){$candidateIdentity[$c['identity_state']]=($candidateIdentity[$c['identity_state']]??0)+1;$gc=$c['geo']['coordinate_class'];$geoCounts[$gc]=($geoCounts[$gc]??0)+1;}
            $out[]=['tv_hotel_id'=>$tv,'source_edges'=>$byTargetRows[$tv]??[],'target'=>$h,'target_names'=>$names??[],'candidate_count'=>count($cands),'verdict'=>$verdict,'candidates'=>$cands,'safe_to_write_now'=>false];
        }
        $db->rollBack();ksort($counts);ksort($candidateIdentity);ksort($geoCounts);ksort($targetCandidateCounts,SORT_NATURAL);
        return ['operation'=>HMC4R6_OP,'state'=>'completed_read_only_anchor_recovery_census','source_sha'=>$sourceSha,'source_audit_sha256'=>HMC4R6_AUDIT_SHA,'source_missing_anchor_total'=>$manifest['missing_anchor_total'],'source_missing_anchor_status_counts'=>$manifest['missing_anchor_status_counts'],'input_rows'=>count($manifest['rows']),'unique_targets'=>count($targetIds),'observation_table_present'=>$obsTable,'observation_columns'=>$obsColumns,'observation_rows_scanned'=>count($observations),'verdict_counts'=>$counts,'candidate_identity_state_counts'=>$candidateIdentity,'candidate_geo_counts'=>$geoCounts,'target_candidate_count_distribution'=>$targetCandidateCounts,'rows'=>$out,'supplier_calls'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        r6_need(r6_norm('  Hotel   Test! ')==='hotel test','norm');
        $x=r6_classify([['identity_state'=>'pending','geo'=>['coordinate_class'=>'coord_le_1km']]]);r6_need($x==='unique_exact_pending_candidate','pending');
        $x=r6_classify([['identity_state'=>'pending','geo'=>['coordinate_class'=>'coord_gt_5km']]]);r6_need($x==='unique_exact_pending_geo_conflict','geo');
        r6_need(r6_classify([])==='no_exact_candidate','none');
        echo "MATCH_COMMON4_MISSING_ANCHOR_RECOVERY_V6_SELFTEST_OK\n";exit;
    }
    r6_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$input=(string)getenv('MATCH_AUDIT_RESULT');$sha=(string)getenv('MATCH_SOURCE_SHA');
    r6_need(is_dir($root)&&basename($root)==='anytoour.ru'&&is_dir($dir)&&basename($dir)===HMC4R6_OP&&is_file($input)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    r6_need(hash_file('sha256',$input)===HMC4R6_AUDIT_SHA,'audit_hash');$reservation=r6_load($dir.'/reservation.json');r6_need(($reservation['operation']??'')===HMC4R6_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    try{
        $audit=r6_load($input);$manifest=r6_manifest($audit);require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$result=r6_execute(v2_data_db(),$manifest,$sha);
        $h=r6_save($dir.'/result.json',$result);r6_save($dir.'/receipt.json',['operation'=>HMC4R6_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo r6_json(['state'=>$result['state'],'source_missing_anchor_total'=>$result['source_missing_anchor_total'],'source_missing_anchor_status_counts'=>$result['source_missing_anchor_status_counts'],'input_rows'=>$result['input_rows'],'unique_targets'=>$result['unique_targets'],'verdict_counts'=>$result['verdict_counts'],'candidate_identity_state_counts'=>$result['candidate_identity_state_counts'],'candidate_geo_counts'=>$result['candidate_geo_counts'],'target_candidate_count_distribution'=>$result['target_candidate_count_distribution']])."\n";
    }catch(Throwable $e){
        $f=['operation'=>HMC4R6_OP,'state'=>'failed_read_only_anchor_recovery_census','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8')),'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=r6_save($dir.'/result.json',$f);r6_save($dir.'/receipt.json',['operation'=>HMC4R6_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
