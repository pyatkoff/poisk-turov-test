<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMSASC_OP='hotel-match-live-samo-anex-staged-candidates-1971-20260922-v1';

function hmsasc_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmsasc_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmsasc_save(string $path,array $value):string{
    $raw=hmsasc_json($value)."\n";$f=@fopen($path,'x+b');hmsasc_need($f!==false,'exclusive_create');
    try{hmsasc_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmsasc_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmsasc_excluded(string $country):bool{
    return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;
}
function hmsasc_classify(array $x):string{
    if(($x['automated_status']??'')!=='strong_candidate')return 'not_staged_strong';
    if((int)($x['suggested_target']??0)!==(int)($x['tv']??-1))return 'staged_target_mismatch';
    if((int)($x['rank1_target']??0)!==(int)($x['tv']??-1))return 'rank1_target_mismatch';
    if(($x['country_match']??null)===false)return 'country_conflict';
    $distance=$x['distance_m']??null;
    if($distance!==null&&(float)$distance>5000)return 'coordinate_conflict';
    if(($x['manual']??false))return 'manual_protected';
    if(($x['pair_excluded']??false))return 'pair_excluded';
    if(($x['effective_other']??false))return 'source_occupied';
    if(($x['existing_mapping']??false))return 'existing_non_effective_mapping';
    if((int)($x['source_target_count']??0)!==1)return 'ambiguous_staged_source';
    if((int)($x['target_source_count']??0)!==1)return 'ambiguous_staged_target';
    return 'candidate_staged_strong_unique';
}
function hmsasc_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $tables=[];
        foreach($db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('anex_hotel_auto_matches','anex_hotel_candidates','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities','tour_operator_identity_observations','catalog_hotels')")->fetchAll(PDO::FETCH_COLUMN) as $t)$tables[(string)$t]=true;
        foreach(['anex_hotel_auto_matches','anex_hotel_candidates','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities','tour_operator_identity_observations','catalog_hotels'] as $t)hmsasc_need(isset($tables[$t]),'missing_'.$t);

        $facts=[];$active=[];
        foreach($db->query("SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(hmsasc_excluded((string)($r['country_name']??'')))continue;
            $id=(int)$r['id'];if($id<=0)continue;$facts[$id]=$r;$active[$id]=true;
        }
        $samo=[];
        foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $id=(int)$r['local_hotel_id'];$ext=trim((string)$r['external_hotel_id']);
            if(isset($active[$id])&&$ext!=='')$samo[$id][$ext]=true;
        }
        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];
        foreach(($anex['by_local']??[]) as $local=>$ids)$anexByLocal[(int)$local]=$ids;

        $live30=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
        foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $tv=(int)$r['hotel_id'];if(!isset($active[$tv]))continue;
            $raw=trim((string)($r['last_seen_at']??''));if($raw==='')continue;
            try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}
            if($dt>=$cut)$live30[$tv]=true;
        }
        $frontier=[];
        foreach($live30 as $tv=>$_)if(isset($samo[$tv])&&!isset($anexByLocal[$tv]))$frontier[$tv]=true;
        hmsasc_need(count($frontier)===942,'frontier_changed');

        $manual=[];foreach($db->query("SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions")->fetchAll(PDO::FETCH_ASSOC) as $r)$manual[(string)$r['anex_hotel_id']]=$r;
        $mappings=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,enabled FROM anex_hotel_search_mappings")->fetchAll(PDO::FETCH_ASSOC) as $r)$mappings[(string)$r['anex_hotel_id']][]=$r;
        $ex=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions")->fetchAll(PDO::FETCH_ASSOC) as $r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;

        $auto=[];
        $q=$db->query("SELECT anex_hotel_id,row_digest,source_fingerprint,automated_status,automated_reason,api_xml_relation,suggested_catalog_hotel_id,candidate_count,stored_candidate_count,candidate_limit,checked_at FROM anex_hotel_auto_matches ORDER BY anex_hotel_id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$auto[(string)$r['anex_hotel_id']]=$r;

        $rank1=[];
        $q=$db->query("SELECT anex_hotel_id,catalog_hotel_id,score,name_similarity,distance_m,country_match,address_exact FROM anex_hotel_candidates WHERE candidate_rank=1 ORDER BY anex_hotel_id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$rank1[(string)$r['anex_hotel_id']]=$r;

        $candidateRows=[];
        foreach($auto as $aid=>$a){
            $tv=(int)($a['suggested_catalog_hotel_id']??0);if(!isset($frontier[$tv]))continue;
            $c=$rank1[$aid]??null;
            $candidateRows[$aid]=[
                'aid'=>$aid,'tv'=>$tv,'auto'=>$a,'rank1'=>$c,
                'rank1_target'=>$c===null?0:(int)$c['catalog_hotel_id'],
            ];
        }
        $sourceTargets=[];$targetSources=[];
        foreach($candidateRows as $aid=>$r){
            $tv=(int)$r['tv'];$sourceTargets[$aid][$tv]=true;$targetSources[$tv][$aid]=true;
        }

        $rows=[];$counts=[];$geo=[];$strongRaw=0;
        foreach(array_keys($frontier) as $tv){
            $matching=[];
            foreach($targetSources[$tv]??[] as $aid=>$_)$matching[(string)$aid]=$candidateRows[(string)$aid];
            if(!$matching){
                $status='no_staged_candidate';$counts[$status]=($counts[$status]??0)+1;
                $f=$facts[$tv];$g=implode('|',[(string)$f['country_name'],(string)$f['region_name'],(string)$f['subregion_name']]);$geo[$status][$g]=($geo[$status][$g]??0)+1;
                $rows[]=['tv_hotel_id'=>$tv,'hotel_name'=>(string)$f['name'],'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],'category'=>(string)$f['category'],'samo_anchor_count'=>count($samo[$tv]),'status'=>$status,'safe_to_write_now'=>false];
                continue;
            }
            foreach($matching as $aid=>$r){
                $a=$r['auto'];$c=$r['rank1'];
                if(($a['automated_status']??'')==='strong_candidate')$strongRaw++;
                $effective=$anex['by_native'][(int)$aid]??$anex['by_native'][$aid]??null;
                $x=[
                    'automated_status'=>(string)($a['automated_status']??''),
                    'suggested_target'=>$r['tv'],'rank1_target'=>$r['rank1_target'],'tv'=>$tv,
                    'country_match'=>$c===null?null:((string)($c['country_match']??'')==='1'?true:((string)($c['country_match']??'')==='0'?false:null)),
                    'distance_m'=>$c!==null&&$c['distance_m']!==null?(float)$c['distance_m']:null,
                    'manual'=>isset($manual[$aid]),'pair_excluded'=>isset($ex[$aid][$tv]),
                    'effective_other'=>$effective!==null&&(int)$effective!==$tv,
                    'existing_mapping'=>isset($mappings[$aid])&&$effective===null,
                    'source_target_count'=>count($sourceTargets[$aid]??[]),'target_source_count'=>count($targetSources[$tv]??[]),
                ];
                $status=hmsasc_classify($x);$counts[$status]=($counts[$status]??0)+1;
                $f=$facts[$tv];$g=implode('|',[(string)$f['country_name'],(string)$f['region_name'],(string)$f['subregion_name']]);$geo[$status][$g]=($geo[$status][$g]??0)+1;
                $rows[]=[
                    'tv_hotel_id'=>$tv,'hotel_name'=>(string)$f['name'],'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],'category'=>(string)$f['category'],'samo_anchor_count'=>count($samo[$tv]),
                    'anex_hotel_id'=>(int)$aid,'status'=>$status,'automated_status'=>(string)$a['automated_status'],'automated_reason'=>(string)$a['automated_reason'],'api_xml_relation'=>(string)$a['api_xml_relation'],
                    'candidate_count'=>(int)$a['candidate_count'],'stored_candidate_count'=>(int)$a['stored_candidate_count'],'rank1_name_similarity'=>$c===null?null:(float)$c['name_similarity'],'rank1_distance_m'=>$c===null||$c['distance_m']===null?null:(float)$c['distance_m'],'rank1_country_match'=>$x['country_match'],
                    'row_digest'=>(string)$a['row_digest'],'source_fingerprint'=>(string)$a['source_fingerprint'],'safe_to_write_now'=>false,
                ];
            }
        }
        foreach($geo as &$m){arsort($m);$m=array_slice($m,0,50,true);}unset($m);ksort($counts);
        usort($rows,static fn($a,$b)=>[$a['status'],$a['country'],$a['region'],$a['subregion'],$a['tv_hotel_id'],$a['anex_hotel_id']??0]<=>[$b['status'],$b['country'],$b['region'],$b['subregion'],$b['tv_hotel_id'],$b['anex_hotel_id']??0]);
        $db->rollBack();
        return [
            'operation'=>HMSASC_OP,'state'=>'completed_read_only_staged_candidates','live30_tv'=>count($live30),
            'frontier_samo_present_anex_missing'=>count($frontier),'staged_auto_rows'=>count($auto),'frontier_staged_source_rows'=>count($candidateRows),'frontier_staged_strong_raw'=>$strongRaw,
            'status_counts'=>$counts,'top_geography_by_status'=>$geo,'rows'=>$rows,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $base=['automated_status'=>'strong_candidate','suggested_target'=>10,'rank1_target'=>10,'tv'=>10,'country_match'=>true,'distance_m'=>120.0,'manual'=>false,'pair_excluded'=>false,'effective_other'=>false,'existing_mapping'=>false,'source_target_count'=>1,'target_source_count'=>1];
        hmsasc_need(hmsasc_classify($base)==='candidate_staged_strong_unique','candidate_fixture');
        $x=$base;$x['country_match']=false;hmsasc_need(hmsasc_classify($x)==='country_conflict','country_fixture');
        $x=$base;$x['manual']=true;hmsasc_need(hmsasc_classify($x)==='manual_protected','manual_fixture');
        $x=$base;$x['target_source_count']=2;hmsasc_need(hmsasc_classify($x)==='ambiguous_staged_target','ambiguity_fixture');
        echo "MATCH_LIVE_SAMO_ANEX_STAGED_CANDIDATES_V1_SELFTEST_OK\n";exit;
    }
    hmsasc_need($mode==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmsasc_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMSASC_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hmsasc_need(($reservation['operation']??'')===HMSASC_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $result=hmsasc_execute(v2_data_db());$result['source_sha']=$sha;$h=hmsasc_save($dir.'/result.json',$result);
        hmsasc_save($dir.'/receipt.json',['operation'=>HMSASC_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo hmsasc_json(['state'=>$result['state'],'frontier'=>$result['frontier_samo_present_anex_missing'],'status_counts'=>$result['status_counts']])."\n";
    }catch(Throwable $e){
        $f=['operation'=>HMSASC_OP,'state'=>'failed_read_only_staged_candidates','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hmsasc_save($dir.'/result.json',$f);hmsasc_save($dir.'/receipt.json',['operation'=>HMSASC_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
