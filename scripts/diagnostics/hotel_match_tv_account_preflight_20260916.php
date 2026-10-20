<?php
declare(strict_types=1);
/** MATCH only: current account telemetry + hotel metadata, never supplier or DB writes. */
const MTP_OP='hotel-match-tv-account-preflight-1971-20260916-v1';
function mtp_check(bool $ok,string $why):void { if(!$ok)throw new RuntimeException($why); }
function mtp_json(array $v):string { return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n"; }
function mtp_write(string $path,array $v):string {
    $raw=mtp_json($v);$f=@fopen($path,'x+b');mtp_check(is_resource($f),'exclusive_output');
    try{mtp_check(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write_failed');if(function_exists('fsync'))mtp_check(fsync($f),'sync_failed');rewind($f);mtp_check(stream_get_contents($f)===$raw,'readback_failed');}finally{fclose($f);}
    return hash('sha256',$raw);
}
function mtp_fields(array $r,array $allow):array { return array_intersect_key($r,array_fill_keys($allow,true)); }
function mtp_select(PDO $db,string $sql,array $params=[]):array {
    mtp_check(preg_match('/^SELECT\b/i',$sql)===1,'read_only_sql');$q=$db->prepare($sql);$q->execute($params);$r=$q->fetchAll(PDO::FETCH_ASSOC);mtp_check(count($r)<=150000,'row_limit');return$r;
}
function mtp_counter(array $r):?int {
    foreach(['tourvisor_http_attempts','tourvisor_calls','tv_calls']as$k)if(array_key_exists($k,$r)){
        $v=$r[$k];if(is_int($v)&&$v>=0)return$v;
        if(is_string($v)&&preg_match('/^[0-9]{1,7}$/D',$v))return(int)$v;
        return null;
    }return null;
}
function mtp_metadata(array $r):array {
    return mtp_fields($r,['operation_id','source_sha','state','status','no_replay','credential_source','credential_identifier','client_token_slot','account','call_count_unit','tourvisor_calls','tourvisor_http_attempts','tv_calls','created_at','created_at_utc','completed_at','completed_at_utc','read_at_utc','date','result_sha256','readback_verified']);
}
function mtp_scan(string $base,int $now):array {
    $entries=glob($base.'/*',GLOB_ONLYDIR);mtp_check(is_array($entries)&&count($entries)<=5000,'operation_scan_limit');
    $out=[];$errors=[];$bytes=0;$seen=0;
    foreach($entries as$dir){
        if(is_link($dir)||basename($dir)===MTP_OP)continue;
        if(!preg_match('/^[A-Za-z0-9_.-]{1,160}$/D',basename($dir))){$errors[]='nonstandard_operation_name';continue;}
        $paths=[];$modified=0;foreach(['reservation.json','runner-reservation.json','receipt.json','result.json']as$n){$p=$dir.'/'.$n;if(is_file($p)&&!is_link($p)){$paths[$n]=$p;$modified=max($modified,(int)filemtime($p));}}
        if($modified<$now-172800)continue;$seen++;$docs=[];$rawHashes=[];
        foreach($paths as$n=>$p){
            $size=(int)filesize($p);if($size>64000000||$bytes+$size>536870912){$errors[]=basename($dir).':metadata_budget';continue;}
            $raw=@file_get_contents($p);if(!is_string($raw)){$errors[]=basename($dir).':unreadable';continue;}$bytes+=strlen($raw);
            try{$d=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(Throwable$e){$errors[]=basename($dir).':invalid_json';continue;}
            if(!is_array($d)){$errors[]=basename($dir).':not_object';continue;}$docs[$n]=$d;$rawHashes[$n]=hash('sha256',$raw);
        }
        $result=$docs['result.json']??[];$receipt=$docs['receipt.json']??[];
        $count=mtp_counter($result);$related=str_contains(strtolower(basename($dir)),'tv')||str_contains(strtolower(basename($dir)),'tourvisor');
        foreach($docs as$d)foreach(['credential_source','credential_identifier']as$k)if(str_contains((string)($d[$k]??''),'TOURVISOR'))$related=true;
        if(!$related&&($count===null||$count===0))continue;
        $verified=isset($rawHashes['result.json'])&&($receipt['result_sha256']??null)===$rawHashes['result.json']&&($receipt['readback_verified']??false)===true;
        $out[]=['operation_id'=>basename($dir),'observed_file_mtime_utc'=>gmdate('c',$modified),'within_last_24h'=>$modified>=$now-86400,'current_utc_day'=>gmdate('Y-m-d',$modified)===gmdate('Y-m-d',$now),'counter'=>$count,'counter_is_http_attempts'=>isset($result['tourvisor_http_attempts']),'verified_terminal_receipt'=>$verified,'metadata'=>array_map('mtp_metadata',$docs),'sha256'=>$rawHashes];
        unset($docs,$result,$raw);
    }
    return ['scope'=>'existing MATCH operation metadata modified in last 48h; file times are not provider billing timestamps','operations_inspected'=>$seen,'bytes_inspected'=>$bytes,'errors'=>$errors,'operations'=>$out,'provider_daily_remaining'=>null,'provider_counter_observed'=>false,'account_wide_coverage_proven'=>false];
}
function mtp_main():void {
    mtp_check(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===MTP_OP,'operation_guard');$sha=(string)getenv('MATCH_SOURCE_SHA');mtp_check(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_guard');
    $base=(string)getenv('HOME').'/.anytoour-match/operations';$dir=$base.'/'.MTP_OP;$rp=$dir.'/reservation.json';
    mtp_check(is_file($rp)&&!is_link($rp)&&realpath($rp)===$rp,'reservation_path');$r=json_decode((string)file_get_contents($rp),true,32,JSON_THROW_ON_ERROR);
    mtp_check(($r['operation_id']??'')===MTP_OP&&($r['source_sha']??'')===$sha&&($r['state']??'')==='reserved_before_private_access','reservation_binding');
    $db=null;$out=['operation_id'=>MTP_OP,'source_sha'=>$sha,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'safe_to_write_now'=>false,'read_at_utc'=>gmdate('c')];
    ob_start();try{
        $root=realpath(getcwd());mtp_check(is_string($root)&&basename($root)==='anytoour.ru','project_root');
        $out['retained_account_telemetry']=mtp_scan($base,time());
        $cfg=is_file($root.'/v2/config.php')?$root.'/v2/config.php':$root.'/config.php';mtp_check(is_file($cfg)&&!is_link($cfg),'config_missing');require_once$cfg;
        $out['anex_account_configured']=defined('TOURVISOR_ANEX_JWT')&&trim((string)constant('TOURVISOR_ANEX_JWT'))!=='';
        $client=is_file($root.'/data/tourvisor-client-v1.php')?$root.'/data/tourvisor-client-v1.php':$root.'/v2/data/tourvisor-client-v1.php';
        $out['deployed_client_sha256']=is_file($client)?hash_file('sha256',$client):null;
        $out['deployed_client_git_blob']=null;if(is_file($client)){$text=(string)file_get_contents($client);$out['deployed_client_git_blob']=sha1('blob '.strlen($text)."\0".$text);}
        $bp=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';mtp_check(is_file($bp)&&!is_link($bp),'db_bootstrap');require_once$bp;
        $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $core=[1,2,4,8,9,10,12,16];$countries=mtp_select($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 AND id IN (1,2,4,8,9,10,12,16) ORDER BY id');mtp_check(count($countries)===8,'core8_country_count');$out['countries']=$countries;
        $out['mappings']=mtp_select($db,"SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE enabled=1 ORDER BY anex_hotel_id,catalog_hotel_id");
        $out['decisions']=mtp_select($db,'SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions ORDER BY anex_hotel_id');
        $out['exclusions']=mtp_select($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id');
        $identity=['anex_hotel_id','hotel_name','api_name','xml_name','country_id','country_name','town_id','town_name','region_id','region_name','category','star','stars','latitude','longitude','lat','lon','api_latitude','api_longitude','search_count','first_seen_utc','last_seen_utc','catalog_hotel_id','local_hotel_id','status'];
        $out['observations']=array_map(static fn($v)=>mtp_fields($v,$identity),mtp_select($db,'SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id'));
        $out['staging']=array_map(static fn($v)=>mtp_fields($v,$identity),mtp_select($db,'SELECT * FROM anex_hotels ORDER BY anex_hotel_id'));
        $out['local_hotels']=mtp_select($db,'SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN (1,2,4,8,9,10,12,16) ORDER BY country_id,id');
        $out['aliases']=mtp_select($db,'SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN (1,2,4,8,9,10,12,16) ORDER BY a.hotel_id,a.id');
        $db->exec('ROLLBACK');$out['state']='completed_read_only';$out['transaction']='REPEATABLE READ READ ONLY';
    }catch(Throwable$e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$out['state']='failed_read_only';$out['error_code']=preg_match('/^[a-z_]{3,80}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';}
    while(ob_get_level())ob_end_clean();$digest=mtp_write($dir.'/result.json',$out);$ok=$out['state']==='completed_read_only';
    mtp_write($dir.'/receipt.json',['operation_id'=>MTP_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$digest,'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0]);
    echo mtp_json(['state'=>$out['state'],'result_sha256'=>$digest,'observations'=>count($out['observations']??[]),'local_hotels'=>count($out['local_hotels']??[]),'provider_daily_remaining'=>null]);if(!$ok)exit(2);
}
if(in_array('--self-test',$argv??[],true)){
    mtp_check(mtp_counter(['tourvisor_calls'=>7])===7,'counter_int');mtp_check(mtp_counter(['tourvisor_calls'=>true])===null,'counter_boolean');mtp_check(mtp_counter([])===null,'counter_unknown');mtp_check(mtp_counter(['tourvisor_calls'=>0])===0,'counter_zero');mtp_check(mtp_counter(['tourvisor_http_attempts'=>8,'tourvisor_calls'=>2])===8,'counter_units');
    mtp_check(mtp_metadata(['operation_id'=>'x','token'=>'secret'])===['operation_id'=>'x'],'no_secret_metadata');mtp_check(mtp_fields(['hotel_name'=>'A','email'=>'private'],['hotel_name'])===['hotel_name'=>'A'],'no_private_fields');
    $d=sys_get_temp_dir().'/mtp-test-'.bin2hex(random_bytes(6));mkdir($d);$p=$d.'/result.json';mtp_write($p,['ok'=>true]);$failed=false;try{mtp_write($p,['ok'=>false]);}catch(Throwable$e){$failed=true;}mtp_check($failed,'no_replay');unlink($p);rmdir($d);echo "8 preflight checks PASS\n";exit;
}
mtp_main();
