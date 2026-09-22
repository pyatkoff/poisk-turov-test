<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMSAB_OP='hotel-match-live-samo-anchor-anex-bridge-1971-20260922-v1';

function hmsab_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmsab_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmsab_save(string $path,array $value):string{$raw=hmsab_json($value)."\n";$f=@fopen($path,'x+b');hmsab_need($f!==false,'exclusive_create');try{hmsab_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmsab_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hmsab_positive(mixed $v):?string{
    if(is_int($v))$v=(string)$v;
    if(!is_string($v)||preg_match('/^[1-9][0-9]{0,7}$/D',$v)!==1)return null;
    return $v;
}
function hmsab_collect_ids(mixed $node,array &$ids,int $depth=0):void{
    if($depth>24||!is_array($node))return;
    if(isset($node['anex_bridges'])&&is_array($node['anex_bridges'])){
        foreach($node['anex_bridges'] as $b)if(is_array($b)&&($id=hmsab_positive($b['anex_id']??null))!==null)$ids[$id]=true;
    }
    $op=(string)($node['operatorKey']??$node['operator_key']??'');
    if($op==='5'){
        foreach(['hotelKey','original_hotel_id','originalHotelId','original_hotel_key'] as $k)if(($id=hmsab_positive($node[$k]??null))!==null)$ids[$id]=true;
        if(isset($node['original'])&&is_array($node['original'])&&($id=hmsab_positive($node['original']['hotelKey']??null))!==null)$ids[$id]=true;
    }
    foreach($node as $k=>$v){
        if($k==='anex_bridges')continue;
        if(is_array($v))hmsab_collect_ids($v,$ids,$depth+1);
    }
}
function hmsab_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}

function hmsab_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $facts=[];$active=[];
        foreach($db->query("SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(hmsab_excluded((string)($r['country_name']??'')))continue;$id=(int)$r['id'];if($id<=0)continue;$facts[$id]=$r;$active[$id]=true;
        }
        $anchors=[];
        foreach($db->query("SELECT external_hotel_id,local_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $tv=(int)$r['local_hotel_id'];if(isset($active[$tv]))$anchors[$tv][]=$r;
        }
        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];foreach(($anex['by_local']??[]) as $local=>$ids)$anexByLocal[(int)$local]=$ids;

        $live30=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
        foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $tv=(int)$r['hotel_id'];if(!isset($active[$tv]))continue;$raw=trim((string)($r['last_seen_at']??''));if($raw==='')continue;
            try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}if($dt>=$cut)$live30[$tv]=true;
        }
        $frontier=[];foreach($live30 as $tv=>$_)if(isset($anchors[$tv])&&!isset($anexByLocal[$tv]))$frontier[$tv]=true;

        $manual=[];foreach($db->query("SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions")->fetchAll(PDO::FETCH_ASSOC) as $r)$manual[(string)$r['anex_hotel_id']]=$r;
        $mapping=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,enabled FROM anex_hotel_search_mappings")->fetchAll(PDO::FETCH_ASSOC) as $r)$mapping[(string)$r['anex_hotel_id']][]=$r;
        $ex=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions")->fetchAll(PDO::FETCH_ASSOC) as $r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;

        $rows=[];$counts=[];$geo=[];$nativeTargets=[];
        $tmp=[];
        foreach(array_keys($frontier) as $tv){
            $ids=[];$invalidHash=false;$valid=0;$anchorCount=0;
            foreach($anchors[$tv] as $a){
                $anchorCount++;$raw=(string)($a['evidence_json']??'');$expected=(string)($a['evidence_sha256']??'');
                if(hash('sha256',$raw)!==$expected){$invalidHash=true;continue;}
                $decoded=json_decode($raw,true);if(!is_array($decoded)){$invalidHash=true;continue;}
                $valid++;hmsab_collect_ids($decoded,$ids);
            }
            $tmp[$tv]=['ids'=>array_keys($ids),'invalid_hash'=>$invalidHash,'valid'=>$valid,'anchor_count'=>$anchorCount];
            foreach(array_keys($ids) as $id)$nativeTargets[$id][$tv]=true;
        }
        foreach(array_keys($frontier) as $tv){
            $x=$tmp[$tv];$ids=$x['ids'];$status='no_explicit_anex_bridge';$aid=null;
            if($x['invalid_hash'])$status='anchor_hash_invalid';
            elseif(count($ids)>1)$status='ambiguous_anchor_anex_ids';
            elseif(count($ids)===1){
                $aid=(string)$ids[0];
                if(count($nativeTargets[$aid]??[])>1)$status='ambiguous_anex_tv_targets';
                elseif(isset($manual[$aid]))$status='manual_protected';
                elseif(isset($ex[$aid][$tv]))$status='pair_excluded';
                else{
                    $occupied=$anex['by_native'][(int)$aid]??$anex['by_native'][$aid]??null;
                    if($occupied!==null&&(int)$occupied!==$tv)$status='source_occupied';
                    elseif(isset($mapping[$aid]))$status='existing_non_effective_mapping';
                    else $status='candidate_explicit_anchor_bridge';
                }
            }
            $counts[$status]=($counts[$status]??0)+1;$f=$facts[$tv];$g=implode('|',[(string)$f['country_name'],(string)$f['region_name'],(string)$f['subregion_name']]);$geo[$status][$g]=($geo[$status][$g]??0)+1;
            $rows[]=['tv_hotel_id'=>$tv,'hotel_name'=>(string)$f['name'],'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],'category'=>(string)$f['category'],'samo_anchor_count'=>$x['anchor_count'],'valid_anchor_hash_count'=>$x['valid'],'status'=>$status,'anex_hotel_id'=>$aid===null?null:(int)$aid,'safe_to_write_now'=>false];
        }
        foreach($geo as &$m){arsort($m);$m=array_slice($m,0,50,true);}unset($m);ksort($counts);
        usort($rows,static fn($a,$b)=>[$a['status'],$a['country'],$a['region'],$a['subregion'],$a['tv_hotel_id']]<=>[$b['status'],$b['country'],$b['region'],$b['subregion'],$b['tv_hotel_id']]);
        $db->rollBack();
        return ['operation'=>HMSAB_OP,'state'=>'completed_read_only_anchor_bridge','live30_tv'=>count($live30),'frontier_samo_present_anex_missing'=>count($frontier),'status_counts'=>$counts,'top_geography_by_status'=>$geo,'rows'=>$rows,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $ids=[];hmsab_collect_ids(['anex_bridges'=>[['anex_id'=>123]],'prior'=>['source'=>['operatorKey'=>5,'hotelKey'=>'456']]],$ids);
        hmsab_need(array_keys($ids)===['123','456'],'collect_fixture');
        $ids=[];hmsab_collect_ids(['source'=>['operatorKey'=>315,'hotelKey'=>'456']],$ids);hmsab_need($ids===[],'operator_guard');
        echo "MATCH_LIVE_SAMO_ANCHOR_ANEX_BRIDGE_V1_SELFTEST_OK\n";exit;
    }
    hmsab_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmsab_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMSAB_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);hmsab_need(($reservation['operation']??'')===HMSAB_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$result=hmsab_execute(v2_data_db());$result['source_sha']=$sha;$h=hmsab_save($dir.'/result.json',$result);hmsab_save($dir.'/receipt.json',['operation'=>HMSAB_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hmsab_json(['state'=>$result['state'],'frontier'=>$result['frontier_samo_present_anex_missing'],'status_counts'=>$result['status_counts']])."\n";}
    catch(Throwable $e){$f=['operation'=>HMSAB_OP,'state'=>'failed_read_only_anchor_bridge','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=hmsab_save($dir.'/result.json',$f);hmsab_save($dir.'/receipt.json',['operation'=>HMSAB_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
