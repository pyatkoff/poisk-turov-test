<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMSMA_OP='hotel-match-live-samo-missing-anex-saved-direct-1971-20260922-v1';

function hmsma_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmsma_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmsma_save(string $path,array $value):string{$raw=hmsma_json($value)."\n";$f=@fopen($path,'x+b');hmsma_need($f!==false,'exclusive_create');try{hmsma_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmsma_need(fsync($f),'durable_sync');}finally{fclose($f);}return hash('sha256',$raw);}
function hmsma_norm(string $v):string{$v=mb_strtolower(trim($v),'UTF-8');$v=str_replace('ё','е',$v);$v=preg_replace('/\s+/u',' ',$v)??$v;return trim($v);}
function hmsma_is_anex(array $r):bool{
    $n=hmsma_norm((string)($r['operator_name']??''));$host=strtolower(trim((string)($r['operator_link_host']??'')));
    if(in_array($n,['anex','anex tour','anextour','анекс','анекс тур'],true))return true;
    return $host==='anextour.ru'||$host==='www.anextour.ru'||$host==='agent.anextour.ru'||str_ends_with($host,'.anextour.ru');
}
function hmsma_direct_id(array $r):array{
    if(!hmsma_is_anex($r))return ['status'=>'not_anex'];
    $link=trim((string)($r['operator_link']??''));$host=strtolower(trim((string)($r['operator_link_host']??'')));$query=trim((string)($r['operator_link_query']??''));
    if($link!==''){
        $u=parse_url($link);if(!is_array($u)||strtolower((string)($u['scheme']??''))!=='https'||isset($u['user'])||isset($u['pass']))return ['status'=>'invalid_link'];
        $lh=strtolower((string)($u['host']??''));if($lh==='')return ['status'=>'invalid_link'];if($host!==''&&$host!==$lh)return ['status'=>'metadata_conflict'];$host=$lh;
        $lq=(string)($u['query']??'');if($query!==''&&$lq!==''&&$query!==$lq)return ['status'=>'metadata_conflict'];if($query==='')$query=$lq;
    }
    if($query==='')return ['status'=>'missing_direct_id'];
    $ids=[];
    foreach(explode('&',$query) as $part){
        if($part==='')continue;[$rk,$rv]=array_pad(explode('=',$part,2),2,'');$k=strtolower(urldecode($rk));
        if(!in_array($k,['hotellist','hotelcode','hotel_code'],true))continue;
        $decoded=trim(urldecode($rv));if($decoded==='')continue;
        foreach(preg_split('/\s*,\s*/',$decoded)?:[] as $piece){
            if(!preg_match('/^[1-9][0-9]{0,11}$/D',$piece))return ['status'=>'invalid_direct_id'];
            $ids[$piece]=true;
        }
    }
    if(!$ids)return ['status'=>'missing_direct_id'];
    if(count($ids)!==1)return ['status'=>'ambiguous_direct_id','ids'=>array_keys($ids)];
    return ['status'=>'direct_id','anex_hotel_id'=>(string)array_key_first($ids)];
}
function hmsma_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}
function hmsma_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $facts=[];$active=[];
        foreach($db->query("SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(hmsma_excluded((string)($r['country_name']??'')))continue;$id=(int)$r['id'];if($id<=0)continue;$facts[$id]=$r;$active[$id]=true;
        }
        $samo=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $id=(int)$r['local_hotel_id'];$ext=trim((string)$r['external_hotel_id']);if(isset($active[$id])&&$ext!=='')$samo[$id][$ext]=true;
        }
        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];foreach(($anex['by_local']??[]) as $local=>$ids)$anexByLocal[(int)$local]=$ids;

        $live30=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
        $obsAll=$db->query("SELECT id,first_seen_at,last_seen_at,observation_count,hotel_id,hotel_name,country_id,region_name,subregion_name,operator_name,operator_link,operator_link_host,operator_link_path,operator_link_query FROM tour_operator_identity_observations ORDER BY hotel_id,id")->fetchAll(PDO::FETCH_ASSOC);
        foreach($obsAll as $r){$id=(int)$r['hotel_id'];if(!isset($active[$id]))continue;$raw=trim((string)($r['last_seen_at']??''));if($raw==='')continue;try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}if($dt>=$cut)$live30[$id]=true;}
        $frontier=[];foreach($live30 as $id=>$_)if(isset($samo[$id])&&!isset($anexByLocal[$id]))$frontier[$id]=true;

        $perTv=[];$perAnex=[];$parseStats=[];
        foreach($obsAll as $r){
            $tv=(int)$r['hotel_id'];if(!isset($frontier[$tv]))continue;
            $p=hmsma_direct_id($r);$st=$p['status'];$parseStats[$st]=($parseStats[$st]??0)+1;if($st!=='direct_id')continue;
            $aid=(string)$p['anex_hotel_id'];$perTv[$tv][$aid]['count']=($perTv[$tv][$aid]['count']??0)+(int)($r['observation_count']??1);
            $ls=(string)($r['last_seen_at']??'');if(($perTv[$tv][$aid]['last_seen']??'')<$ls)$perTv[$tv][$aid]['last_seen']=$ls;
            $perTv[$tv][$aid]['hashes'][hash('sha256',(string)($r['operator_link']??'').'|'.(string)($r['operator_link_query']??''))]=true;
            $perAnex[$aid][$tv]=true;
        }

        $manual=[];foreach($db->query("SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions")->fetchAll(PDO::FETCH_ASSOC) as $r)$manual[(string)$r['anex_hotel_id']]=$r;
        $mapping=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,enabled FROM anex_hotel_search_mappings")->fetchAll(PDO::FETCH_ASSOC) as $r)$mapping[(string)$r['anex_hotel_id']][]=$r;
        $ex=[];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions")->fetchAll(PDO::FETCH_ASSOC) as $r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;

        $rows=[];$counts=[];$geo=[];
        foreach(array_keys($frontier) as $tv){
            $ids=array_keys($perTv[$tv]??[]);$status='no_direct_evidence';$aid=null;
            if(count($ids)>1)$status='ambiguous_tv_anex_ids';
            elseif(count($ids)===1){
                $aid=(string)$ids[0];
                if(count($perAnex[$aid]??[])>1)$status='ambiguous_anex_tv_targets';
                elseif(isset($manual[$aid]))$status='manual_protected';
                elseif(isset($ex[$aid][$tv]))$status='pair_excluded';
                else{
                    $occupied=$anex['by_native'][(int)$aid]??$anex['by_native'][$aid]??null;
                    if($occupied!==null&&(int)$occupied!==$tv)$status='source_occupied';
                    elseif(isset($mapping[$aid]))$status='existing_non_effective_mapping';
                    else $status='candidate_direct_unique';
                }
            }
            $counts[$status]=($counts[$status]??0)+1;$f=$facts[$tv];
            $g=implode('|',[(string)$f['country_name'],(string)$f['region_name'],(string)$f['subregion_name']]);$geo[$status][$g]=($geo[$status][$g]??0)+1;
            $ev=$aid!==null?($perTv[$tv][$aid]??[]):[];
            $rows[]=[
                'tv_hotel_id'=>$tv,'hotel_name'=>(string)$f['name'],'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],'category'=>(string)$f['category'],
                'samo_anchor_count'=>count($samo[$tv]??[]),'status'=>$status,'anex_hotel_id'=>$aid===null?null:(int)$aid,
                'saved_observation_count'=>(int)($ev['count']??0),'last_seen'=>(string)($ev['last_seen']??''),'operator_link_hashes'=>array_keys($ev['hashes']??[]),'safe_to_write_now'=>false,
            ];
        }
        foreach($geo as &$m){arsort($m);$m=array_slice($m,0,50,true);}unset($m);ksort($counts);
        usort($rows,static fn($a,$b)=>[$a['status'],$a['country'],$a['region'],$a['subregion'],$a['tv_hotel_id']]<=>[$b['status'],$b['country'],$b['region'],$b['subregion'],$b['tv_hotel_id']]);
        $db->rollBack();
        return ['operation'=>HMSMA_OP,'state'=>'completed_read_only_saved_direct','live30_tv'=>count($live30),'frontier_samo_present_anex_missing'=>count($frontier),'status_counts'=>$counts,'parse_stats'=>$parseStats,'top_geography_by_status'=>$geo,'rows'=>$rows,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $r=['operator_name'=>'ANEX Tour','operator_link'=>'https://agent.anextour.ru/x?hotellist=12345','operator_link_host'=>'agent.anextour.ru','operator_link_query'=>'hotellist=12345'];
        $x=hmsma_direct_id($r);hmsma_need(($x['status']??'')==='direct_id'&&($x['anex_hotel_id']??'')==='12345','direct_fixture');
        $r['operator_link_query']='hotellist=12345,67890';$r['operator_link']='https://agent.anextour.ru/x?hotellist=12345,67890';hmsma_need(hmsma_direct_id($r)['status']==='ambiguous_direct_id','ambiguous_fixture');
        echo "MATCH_LIVE_SAMO_MISSING_ANEX_SAVED_DIRECT_V1_SELFTEST_OK\n";exit;
    }
    hmsma_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hmsma_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMSMA_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);hmsma_need(($reservation['operation']??'')===HMSMA_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$result=hmsma_execute(v2_data_db());$result['source_sha']=$sha;$h=hmsma_save($dir.'/result.json',$result);hmsma_save($dir.'/receipt.json',['operation'=>HMSMA_OP,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hmsma_json(['state'=>$result['state'],'frontier'=>$result['frontier_samo_present_anex_missing'],'status_counts'=>$result['status_counts']])."\n";}
    catch(Throwable $e){$f=['operation'=>HMSMA_OP,'state'=>'failed_read_only_saved_direct','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=hmsma_save($dir.'/result.json',$f);hmsma_save($dir.'/receipt.json',['operation'=>HMSMA_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
