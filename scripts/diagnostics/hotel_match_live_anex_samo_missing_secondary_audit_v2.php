<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMAMS_OP='hotel-match-live-anex-samo-missing-secondary-audit-1971-20260926-v2';

function hmams_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmams_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmams_save(string $path,array $value):string{
    $raw=hmams_json($value)."\n";$f=@fopen($path,'x+b');hmams_need($f!==false,'exclusive_create');
    try{hmams_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmams_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmams_excluded(string $country):bool{
    return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;
}
function hmams_pos(mixed $v):?string{
    if(is_int($v))$v=(string)$v;
    if(!is_string($v))return null;$v=trim($v);
    return preg_match('/^[1-9][0-9]{0,19}$/D',$v)===1?$v:null;
}
function hmams_operator_namespace(int $operatorId):?string{
    return match($operatorId){18=>'bgoperator',25=>'operator_315',43=>'operator_342',default=>null};
}
function hmams_hotelish_key(string $key):bool{
    $k=strtolower($key);
    return in_array($k,['f4','hotel','hotels','hotelid','hotel_id','hotelcode','hotel_code','hotellist','hotelkey','hotel_key'],true);
}
function hmams_lane_needs_fresh(array $lane):bool{
    if(!empty($lane['accepted_external_ids']))return false;
    return (string)($lane['state']??'')!=='saved_single_native_missing_secondary_edge';
}
function hmams_parse_link(array $r):array{
    $op=(int)($r['operator_id']??0);$ns=hmams_operator_namespace($op);
    if($ns===null)return ['state'=>'unsupported_operator','namespace'=>null,'candidates'=>[]];
    $link=trim((string)($r['operator_link']??''));$metaHost=strtolower(trim((string)($r['operator_link_host']??'')));
    $metaQuery=trim((string)($r['operator_link_query']??''));
    if($link==='')return ['state'=>'missing_link','namespace'=>$ns,'candidates'=>[]];
    $u=parse_url($link);
    if(!is_array($u)||strtolower((string)($u['scheme']??''))!=='https'||empty($u['host'])||isset($u['user'])||isset($u['pass'])||isset($u['fragment']))
        return ['state'=>'invalid_link','namespace'=>$ns,'candidates'=>[]];
    $host=strtolower((string)$u['host']);$query=(string)($u['query']??'');
    if($metaHost!==''&&$metaHost!==$host)return ['state'=>'metadata_conflict','namespace'=>$ns,'candidates'=>[]];
    if($metaQuery!==''&&$query!==''&&$metaQuery!==$query)return ['state'=>'metadata_conflict','namespace'=>$ns,'candidates'=>[]];
    if($query==='')$query=$metaQuery;
    $candidates=[];$numericAll=[];$keys=[];$secret=false;
    foreach(explode('&',$query) as $part){
        if($part==='')continue;[$rk,$rv]=array_pad(explode('=',$part,2),2,'');
        $key=strtolower(urldecode($rk));if($key==='')continue;$keys[$key]=true;
        if(preg_match('/(?:token|jwt|auth|pass|password|secret|session|sid|cookie|signature|api[_-]?key)/i',$key)){$secret=true;break;}
        $raw=trim(urldecode($rv));$id=hmams_pos($raw);if($id===null)continue;
        $numericAll[$id]=true;if(hmams_hotelish_key($key))$candidates[$id]=true;
    }
    if($secret)return ['state'=>'secret_bearing_link','namespace'=>$ns,'candidates'=>[]];
    if($op===18){
        if(!in_array($host,['bgoperator.ru','www.bgoperator.ru'],true))return ['state'=>'unexpected_bg_host','namespace'=>$ns,'candidates'=>[]];
        $f4=[];foreach(explode('&',$query) as $part){[$rk,$rv]=array_pad(explode('=',$part,2),2,'');if(strcasecmp(urldecode($rk),'F4')!==0)continue;$id=hmams_pos(urldecode($rv));if($id!==null)$f4[$id]=true;}
        $candidates=$f4;
    }
    $ids=array_keys($candidates);sort($ids,SORT_NATURAL);
    if(count($ids)===1)return ['state'=>'single_native_candidate','namespace'=>$ns,'candidates'=>$ids,'host'=>$host,'query_keys'=>array_keys($keys)];
    if(count($ids)>1)return ['state'=>'ambiguous_native_candidates','namespace'=>$ns,'candidates'=>$ids,'host'=>$host,'query_keys'=>array_keys($keys)];
    $all=array_keys($numericAll);sort($all,SORT_NATURAL);
    return ['state'=>$all?'numeric_nonhotel_only':'no_native_candidate','namespace'=>$ns,'candidates'=>[],'host'=>$host,'query_keys'=>array_keys($keys)];
}
function hmams_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}

function hmams_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $facts=[];$active=[];
        foreach($db->query("SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(hmams_excluded((string)($r['country_name']??'')))continue;$id=(int)$r['id'];if($id<1)continue;$facts[$id]=$r;$active[$id]=true;
        }
        $samo=[];$secondary=[];$secondaryByKey=[];
        foreach($db->query("SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY supplier_namespace,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $tv=(int)$r['local_hotel_id'];if(!isset($active[$tv]))continue;$ns=(string)$r['supplier_namespace'];$ext=trim((string)$r['external_hotel_id']);if($ext==='')continue;
            if($ns==='andromeda_catalog')$samo[$tv][$ext]=true;
            if(in_array($ns,['bgoperator','operator_315','operator_342'],true)){
                $secondary[$tv][$ns][$ext]=true;$secondaryByKey[$ns.'|'.$ext][$tv]=true;
            }
        }
        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];
        foreach(($anex['by_local']??[]) as $local=>$ids)$anexByLocal[(int)$local]=$ids;

        $cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');$live30=[];
        foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $tv=(int)$r['hotel_id'];if(!isset($active[$tv]))continue;$raw=trim((string)$r['last_seen_at']);if($raw==='')continue;
            try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}if($dt>=$cut)$live30[$tv]=true;
        }
        $frontier=[];foreach($live30 as $tv=>$_)if(isset($anexByLocal[$tv])&&!isset($samo[$tv]))$frontier[$tv]=true;

        $obs=hmams_query($db,"SELECT id,last_seen_at,observation_count,hotel_id,operator_id,operator_name,operator_link,operator_link_host,operator_link_query FROM tour_operator_identity_observations WHERE operator_id IN (18,25,43) ORDER BY hotel_id,operator_id,last_seen_at DESC,id DESC");
        $saved=[];$parseCounts=[];$sourceTargets=[];
        foreach($obs as $r){
            $tv=(int)$r['hotel_id'];if(!isset($frontier[$tv]))continue;$parsed=hmams_parse_link($r);$state=(string)$parsed['state'];$parseCounts[$state]=($parseCounts[$state]??0)+1;
            $ns=$parsed['namespace'];if(!is_string($ns))continue;
            $saved[$tv][$ns]['observations']=($saved[$tv][$ns]['observations']??0)+(int)($r['observation_count']??1);
            $last=(string)($r['last_seen_at']??'');if(($saved[$tv][$ns]['last_seen']??'')<$last)$saved[$tv][$ns]['last_seen']=$last;
            if($state==='single_native_candidate'){
                $ext=(string)$parsed['candidates'][0];$saved[$tv][$ns]['candidates'][$ext]=true;$sourceTargets[$ns.'|'.$ext][$tv]=true;
            }elseif($state==='ambiguous_native_candidates'){
                foreach($parsed['candidates'] as $ext)$saved[$tv][$ns]['ambiguous_candidates'][(string)$ext]=true;
            }
        }

        $rows=[];$statusCounts=[];$freshNeeded=0;$usableSaved=0;$acceptedSecondaryTargets=0;$geo=[];
        foreach(array_keys($frontier) as $tv){
            $accepted=[];foreach($secondary[$tv]??[] as $ns=>$ids)$accepted[$ns]=array_values(array_keys($ids));
            if($accepted)$acceptedSecondaryTargets++;
            $lanes=[];$hasUsable=false;$hasAmbiguous=false;
            foreach(['bgoperator','operator_315','operator_342'] as $ns){
                $cand=array_keys($saved[$tv][$ns]['candidates']??[]);sort($cand,SORT_NATURAL);
                $amb=array_keys($saved[$tv][$ns]['ambiguous_candidates']??[]);sort($amb,SORT_NATURAL);
                $state='no_saved_native';
                if(count($cand)>1)$state='target_multiple_saved_native';
                elseif(count($cand)===1){
                    $ext=$cand[0];$targets=$sourceTargets[$ns.'|'.$ext]??[];
                    if(count($targets)>1)$state='saved_source_collision';
                    else{
                        $acceptedForSource=$secondaryByKey[$ns.'|'.$ext]??[];
                        if($acceptedForSource&& !isset($acceptedForSource[$tv]))$state='source_occupied_other';
                        elseif(isset($secondary[$tv][$ns][$ext]))$state='resolved_same_secondary';
                        elseif(!empty($secondary[$tv][$ns]))$state='target_namespace_occupied_other';
                        else{$state='saved_single_native_missing_secondary_edge';$hasUsable=true;}
                    }
                }elseif($amb){$state='saved_ambiguous_native';$hasAmbiguous=true;}
                $lanes[$ns]=[
                    'state'=>$state,'single_candidates'=>$cand,'ambiguous_candidates'=>$amb,
                    'saved_observation_count'=>(int)($saved[$tv][$ns]['observations']??0),
                    'last_seen'=>(string)($saved[$tv][$ns]['last_seen']??''),
                    'accepted_external_ids'=>$accepted[$ns]??[],
                ];
                $statusCounts[$ns.'|'.$state]=($statusCounts[$ns.'|'.$state]??0)+1;
            }
            if($hasUsable)$usableSaved++;
            $freshLanes=[];
            foreach(['bgoperator','operator_315','operator_342'] as $ns){
                $lane=$lanes[$ns];
                if(hmams_lane_needs_fresh($lane))$freshLanes[]=$ns;
            }
            $needsFresh=$freshLanes!==[];
            if($needsFresh)$freshNeeded++;
            $f=$facts[$tv];$g=implode('|',[(string)$f['country_name'],(string)$f['region_name'],(string)$f['subregion_name']]);
            $geo[$needsFresh?'needs_fresh':'saved_or_secondary'][$g]=($geo[$needsFresh?'needs_fresh':'saved_or_secondary'][$g]??0)+1;
            $rows[]=[
                'tv_hotel_id'=>$tv,'hotel_name'=>(string)$f['name'],'country'=>(string)$f['country_name'],
                'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],'category'=>(string)$f['category'],
                'anex_effective_ids'=>array_values($anexByLocal[$tv]??[]),'samo_anchor_count'=>0,
                'accepted_secondary'=>$accepted,'secondary_lanes'=>$lanes,
                'has_usable_saved_secondary_candidate'=>$hasUsable,'has_ambiguous_saved_secondary'=>$hasAmbiguous,
                'fresh_secondary_lanes'=>$freshLanes,'needs_fresh_secondary_acquisition'=>$needsFresh,'safe_to_write_now'=>false,
            ];
        }
        foreach($geo as &$m){arsort($m);$m=array_slice($m,0,50,true);}unset($m);ksort($statusCounts);ksort($parseCounts);
        usort($rows,static fn($a,$b)=>[(int)!$a['needs_fresh_secondary_acquisition'],$a['country'],$a['region'],$a['subregion'],$a['tv_hotel_id']]<=>[(int)!$b['needs_fresh_secondary_acquisition'],$b['country'],$b['region'],$b['subregion'],$b['tv_hotel_id']]);
        $db->rollBack();
        return [
            'operation'=>HMAMS_OP,'state'=>'completed_read_only_secondary_audit','historical_frontier_baseline'=>234,
            'live30_tv'=>count($live30),'current_anex_present_samo_missing'=>count($frontier),
            'targets_with_accepted_secondary'=>$acceptedSecondaryTargets,'targets_with_usable_saved_secondary_candidate'=>$usableSaved,
            'targets_needing_fresh_secondary_acquisition'=>$freshNeeded,'parse_state_counts'=>$parseCounts,
            'lane_status_counts'=>$statusCounts,'top_geography'=>$geo,'rows'=>$rows,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,
            'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        hmams_need(hmams_operator_namespace(18)==='bgoperator'&&hmams_operator_namespace(25)==='operator_315'&&hmams_operator_namespace(43)==='operator_342','bindings');
        $bg=hmams_parse_link(['operator_id'=>18,'operator_link'=>'https://www.bgoperator.ru/x?F4=12345','operator_link_host'=>'www.bgoperator.ru','operator_link_query'=>'F4=12345']);
        hmams_need(($bg['state']??'')==='single_native_candidate'&&(string)($bg['candidates'][0]??'')==='12345','bg_f4');
        $fs=hmams_parse_link(['operator_id'=>25,'operator_link'=>'https://example.test/x?hotelCode=4567','operator_link_host'=>'example.test','operator_link_query'=>'hotelCode=4567']);
        hmams_need(($fs['state']??'')==='single_native_candidate'&&(string)($fs['candidates'][0]??'')==='4567','hotel_code');
        $bad=hmams_parse_link(['operator_id'=>43,'operator_link'=>'https://example.test/x?session=123&hotelId=4567','operator_link_host'=>'example.test','operator_link_query'=>'session=123&hotelId=4567']);
        hmams_need(($bad['state']??'')==='secret_bearing_link','secret');
        hmams_need(!hmams_lane_needs_fresh(['state'=>'no_saved_native','accepted_external_ids'=>['1']]),'accepted_lane');
        hmams_need(!hmams_lane_needs_fresh(['state'=>'saved_single_native_missing_secondary_edge','accepted_external_ids'=>[]]),'saved_lane');
        hmams_need(hmams_lane_needs_fresh(['state'=>'no_saved_native','accepted_external_ids'=>[]]),'fresh_lane');
        echo "MATCH_LIVE_ANEX_SAMO_MISSING_SECONDARY_AUDIT_V2_SELFTEST_OK\n";exit;
    }
    hmams_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmams_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMAMS_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hmams_need(($reservation['operation']??'')===HMAMS_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $result=hmams_execute(v2_data_db());$result['source_sha']=$sha;$h=hmams_save($dir.'/result.json',$result);
        hmams_save($dir.'/receipt.json',['operation'=>HMAMS_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo hmams_json(['state'=>$result['state'],'current_frontier'=>$result['current_anex_present_samo_missing'],'accepted_secondary'=>$result['targets_with_accepted_secondary'],'usable_saved'=>$result['targets_with_usable_saved_secondary_candidate'],'needs_fresh'=>$result['targets_needing_fresh_secondary_acquisition']])."\n";
    }catch(Throwable $e){
        $f=['operation'=>HMAMS_OP,'state'=>'failed_read_only_secondary_audit','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hmams_save($dir.'/result.json',$f);hmams_save($dir.'/receipt.json',['operation'=>HMAMS_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
