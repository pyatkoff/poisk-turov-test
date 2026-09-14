<?php
declare(strict_types=1);
/* One-hotel saved-data inspection. No provider calls or server file/database writes. */
const IL_OP='hotel-match-ilmercato-saved-links-1971-20260915-v1';
function il_url($value): ?string {
    if(!is_string($value)||strlen($value)>8192)return null;
    $p=parse_url($value);if(!is_array($p)||!in_array(strtolower($p['scheme']??''),['http','https'],true)||empty($p['host'])||isset($p['user'])||isset($p['pass']))return null;
    parse_str($p['query']??'',$query);
    foreach(array_keys($query) as $key)if(preg_match('/token|pass|secret|session|auth|login|credential|bearer/i',(string)$key))return null;
    return $value;
}
function il_is_target(array $row): bool {
    foreach(['hotelKey','hotelId','hotelid','hotel_id','id','external_hotel_id'] as $key){
        $id=$row[$key]??null;if(is_scalar($id)&&in_array((string)$id,['2904','238653'],true))return true;
    }
    foreach(['name','hotel','hotelName','hotelname','hotel_name','title'] as $key)if(is_string($row[$key]??null)&&preg_match('/\bil\s*mercato\b/i',$row[$key]))return true;
    return false;
}
function il_safe_row(array $row): array {
    $out=[];
    foreach(['hotelKey','hotelId','hotelid','hotel_id','id','external_hotel_id','supplier_namespace','name','hotel','hotelName','hotelname','hotel_name','title','country','countryId','countryid','country_id','stateKey','operatorKey','operatorId','operatorid','operator','operatorName','operatorname','isOperatorHotelKey','tourId','tourid'] as $key){
        if(isset($row[$key])&&is_scalar($row[$key]))$out[$key]=is_string($row[$key])?substr($row[$key],0,512):$row[$key];
    }
    foreach(['hotelDescriptionLink','hotelUrl','hotel_url','operatorLink','operatorlink','site'] as $key){
        if(!is_string($row[$key]??null)||$row[$key]==='')continue;
        $url=il_url($row[$key]);if($url!==null)$out[$key]=$url;else$out[$key.'_withheld_sha256']=hash('sha256',$row[$key]);
    }
    if(is_array($row['original']??null))$out['original']=array_intersect_key($row['original'],['hotel'=>1,'hotelKey'=>1]);
    return $out;
}
function il_walk($node,string $pointer,array &$hits,bool $within=false,int $depth=0): void {
    if(!is_array($node)||$depth>32||count($hits)>=150)return;
    $target=il_is_target($node);$active=$within||$target;$safe=il_safe_row($node);
    if($active&&$safe){$has=false;foreach(['hotelDescriptionLink','hotelUrl','hotel_url','operatorLink','operatorlink','site'] as $k)if(isset($safe[$k]))$has=true;
        if($target||$has||($within&&preg_match('/intourist|интурист/i',json_encode($safe,JSON_UNESCAPED_UNICODE))))$hits[]=['json_pointer'=>$pointer,'direct_target'=>$target,'fields'=>$safe];
    }
    foreach($node as $key=>$value){if(!is_array($value))continue;if(preg_match('/credential|password|token|customer|tourist|passport|traveller|contact/i',(string)$key))continue;il_walk($value,$pointer.'/'.str_replace(['/','~'],['~1','~0'],(string)$key),$hits,$active,$depth+1);}
}
function il_main(): array {
    if(PHP_SAPI!=='cli'||(getenv('MATCH_OPERATION_ID')?:'')!==IL_OP||!preg_match('/^[0-9a-f]{40}$/D',getenv('MATCH_SOURCE_SHA')?:''))throw new RuntimeException('execution_guard');
    $home=rtrim((string)getenv('HOME'),'/');$root=realpath($home.'/www/anytoour.ru');
    if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('site_root_missing');
    $out=['operation_id'=>IL_OP,'source_sha'=>getenv('MATCH_SOURCE_SHA'),'state'=>'completed_read_only','target'=>['tourvisor'=>2904,'andromeda'=>238653,'operator'=>342,'native'=>12114],'saved_rows'=>[],'database_observations'=>[],'roots'=>[],'files_scanned'=>0,'bytes_scanned'=>0,'skipped_large'=>0,'scan_complete'=>true,'supplier_calls'=>0,'database_writes'=>0,'server_file_writes'=>0,'no_replay'=>true];
    $dirs=['tv_cache'=>$root.'/var/cache/v2','site_cache'=>$root.'/var/cache','preview_cache'=>$root.'/_preview/search3-anex-candidate/var/cache','andromeda_private'=>$home.'/.anytoour-andromeda','anex_private'=>$home.'/.anytoour-anex'];
    $seen=[];$files=[];
    foreach($dirs as $label=>$dir){$real=realpath($dir);$out['roots'][$label]=['exists'=>$real!==false&&is_dir($real),'json_files'=>0];if(!$real||!is_dir($real)||is_link($dir))continue;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS));$it->setMaxDepth(6);
        foreach($it as $file){if(count($files)>=10000){$out['scan_complete']=false;break;}if(!$file->isFile()||$file->isLink()||strtolower($file->getExtension())!=='json')continue;$path=$file->getPathname();if(isset($seen[$path]))continue;$seen[$path]=true;$out['roots'][$label]['json_files']++;$files[]=['path'=>$path,'label'=>$label,'relative'=>substr($path,strlen($real)+1),'size'=>$file->getSize(),'mtime'=>$file->getMTime()];}
    }
    usort($files,static fn($a,$b)=>$b['mtime']<=>$a['mtime']);$start=microtime(true);
    foreach($files as $file){if(microtime(true)-$start>45||$out['bytes_scanned']>=256*1024*1024){$out['scan_complete']=false;break;}if($file['size']>24*1024*1024){$out['skipped_large']++;$out['scan_complete']=false;continue;}
        $raw=@file_get_contents($file['path']);if($raw===false){$out['scan_complete']=false;continue;}$out['files_scanned']++;$out['bytes_scanned']+=strlen($raw);
        if(!str_contains($raw,'2904')&&!str_contains($raw,'238653')&&stripos($raw,'mercato')===false)continue;
        $data=json_decode($raw,true,64);if(!is_array($data))continue;$hits=[];il_walk($data,'',$hits);
        if($hits)$out['saved_rows'][]=['root'=>$file['label'],'file'=>$file['relative'],'mtime_utc'=>gmdate('c',$file['mtime']),'body_sha256'=>hash('sha256',$raw),'hits'=>$hits];
        if(count($out['saved_rows'])>=100){$out['scan_complete']=false;break;}
    }
    $db=null;
    try {
        $bootstrap=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        if(!is_file($bootstrap)||is_link($bootstrap))throw new RuntimeException('db_bootstrap_missing');require_once $bootstrap;
        $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
        $q=$db->prepare("SELECT supplier_namespace,external_hotel_id,hotel_name,hotel_url,operator_refs_json,operator_names_json,observed_at_utc FROM andromeda_search_hotel_observations WHERE external_hotel_id=? ORDER BY observed_at_utc DESC LIMIT 20");$q->execute(['238653']);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$safe=il_safe_row($r);$safe['observed_at_utc']=$r['observed_at_utc'];foreach(['operator_refs_json','operator_names_json'] as $k)$safe[$k]=json_decode((string)$r[$k],true);$out['database_observations'][]=$safe;}
        $db->rollBack();$out['database_read_status']='read_only_transaction_completed';
    }catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$out['database_read_status']='unavailable';$out['database_error_class']=get_class($e);}
    return $out;
}
if(($argv[1]??'')==='--self-test'){
    assert_options(ASSERT_ACTIVE,1);$n=0;$check=static function($ok)use(&$n){if(!$ok)throw new RuntimeException('offline_test_failed');$n++;};
    $check(il_url('https://example.test/hotel/12114')==='https://example.test/hotel/12114');$check(il_url('https://u:p@example.test/hotel')===null);$check(il_url('https://example.test/?token=secret')===null);$check(il_url('file:///etc/passwd')===null);
    $h=[];il_walk(['hotels'=>[['id'=>2904,'name'=>'IL MERCATO','hotelDescriptionLink'=>'https://example.test/h/2904','tours'=>[['operatorName'=>'Intourist','operatorLink'=>'https://example.test/o/12114']]],['id'=>99,'name'=>'OTHER','hotelDescriptionLink'=>'https://example.test/other']]],'',$h);$check(count($h)===2);$check($h[1]['fields']['operatorLink']==='https://example.test/o/12114');
    $h=[];il_walk(['PRICES'=>[['hotelKey'=>238653,'operatorKey'=>342,'original'=>['hotelKey'=>12114],'hotelUrl'=>'https://example.test/h/12114']]],'',$h);$check($h[0]['fields']['original']['hotelKey']===12114);$check(count($h)===1);echo "$n saved-link tests PASS\n";exit;
}
try{$r=il_main();echo json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";}catch(Throwable $e){echo json_encode(['operation_id'=>IL_OP,'state'=>'failed_read_only','error_class'=>get_class($e),'supplier_calls'=>0,'database_writes'=>0,'server_file_writes'=>0,'no_replay'=>true])."\n";exit(2);}
