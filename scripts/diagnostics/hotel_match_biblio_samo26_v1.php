<?php
declare(strict_types=1);

const OP = 'hotel-match-biblio-samo26-1971-20260922-v1';
const IDS = [1108,1111,1113,1114,1118,1134,1137,1139,1140,1141,1143,1146,1161,1162,1177,1245,1246,1249,1250,1252,1257,1262,1279,1282,1284,1292];
const EXCLUDED_CONSUMED = [1096,1098,1102,1105];

function need(bool $ok, string $code): void { if (!$ok) throw new RuntimeException($code); }
function loadj(string $path): array { $v=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR); need(is_array($v),'json'); return $v; }
function savej(string $path, array $value): string {
    $body=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    $f=fopen($path,'xb'); need(is_resource($f),'exclusive_output'); need(fwrite($f,$body)===strlen($body)&&fflush($f),'write');
    if(function_exists('fsync')) need(fsync($f),'fsync'); fclose($f); return hash('sha256',$body);
}
function query(PDO $db,string $sql,array $params=[]): array { $st=$db->prepare($sql);$st->execute(array_values($params));return $st->fetchAll(PDO::FETCH_ASSOC)?:[]; }
function norm(string $s): string { $s=str_replace(['Ё','ё'],'е',trim($s));$s=function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);return preg_replace('/[^\p{L}\p{N}]+/u','',$s)??''; }
function one(array $rows,array $aliases): int {
    $want=array_map(fn($x)=>norm((string)$x),$aliases);$found=[];
    foreach($rows as $row) if(isset($row['id'],$row['name'])&&in_array(norm((string)$row['name']),$want,true)) $found[(string)$row['id']]=true;
    need(count($found)===1,'dictionary_identity'); return (int)array_key_first($found);
}
function budget(string $root): void {
    $path=$root.'/monthly-requests.json';$lock=fopen($path.'.lock','c');need(is_resource($lock)&&flock($lock,LOCK_EX),'budget_lock');
    try{$month=gmdate('Y-m');$s=is_file($path)?loadj($path):[];if(($s['month']??'')!==$month)$s=['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>5000000,'scope'=>'this_integration'];need((int)($s['reserved_requests']??0)<5000000,'quota');$s['reserved_requests']=(int)$s['reserved_requests']+1;$tmp=$path.'.'.bin2hex(random_bytes(4));file_put_contents($tmp,json_encode($s,JSON_THROW_ON_ERROR));chmod($tmp,0600);rename($tmp,$path);}finally{flock($lock,LOCK_UN);fclose($lock);}
}
function selfTest(): void {
    need(count(IDS)===26&&count(array_unique(IDS))===26,'ids');
    need(!array_intersect(IDS,EXCLUDED_CONSUMED),'consumed_overlap');
    echo "OK\n";
}
if(($argv[1]??'')==='--self-test'){selfTest();exit(0);} need(($argv[1]??'')==='--execute','mode');

$root=realpath((string)getenv('ANYTOUR_ROOT'));$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$sha=(string)getenv('MATCH_SOURCE_SHA');
need($root!==false&&basename($root)==='anytoour.ru'&&$dir!==false&&basename($dir)===OP&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'scope');
$res=loadj($dir.'/payload/reservation.json');$cohort=loadj($dir.'/payload/cohort.json');
need(($res['operation']??'')===OP&&($res['source_sha']??'')===$sha&&($res['script_sha256']??'')===hash_file('sha256',__FILE__),'reservation');
need(($res['cohort_sha256']??'')===hash_file('sha256',$dir.'/payload/cohort.json'),'cohort_hash');
need(array_map(fn($x)=>(int)$x['tv'],$cohort)===IDS,'cohort_membership');
foreach($cohort as $row){need(preg_match('/^[1-9][0-9]*$/D',(string)($row['f4']??''))===1,'f4');need(!in_array((int)$row['tv'],EXCLUDED_CONSUMED,true),'consumed');}
savej($dir.'/started.json',['operation'=>OP,'state'=>'started_no_replay','source_sha'=>$sha,'tv_ids'=>IDS,'excluded_consumed'=>EXCLUDED_CONSUMED]);
$base=['operation'=>OP,'source_sha'=>$sha,'tv_ids'=>IDS,'excluded_consumed'=>EXCLUDED_CONSUMED,'tourvisor_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];
$calls=0;$currentTv=null;
try{
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $app=$dir.'/payload';require_once $app.'/andromeda-client.php';require_once $app.'/andromeda-transport.php';
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $ph=implode(',',array_fill(0,count(IDS),'?'));
    $hotels=query($db,"SELECT id,country_id,name,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",IDS);
    $anchors=query($db,"SELECT external_hotel_id,local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($ph) ORDER BY local_hotel_id,external_hotel_id",IDS);
    $providerRows=query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE local_hotel_id IN ($ph) ORDER BY local_hotel_id,supplier_namespace,external_hotel_id",IDS);
    $manual=query($db,"SELECT catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph) ORDER BY catalog_hotel_id",IDS);
    $db->rollBack();
    $hotelBy=[];foreach($hotels as $x)$hotelBy[(int)$x['id']]=$x;
    $anchorBy=[];foreach($anchors as $x)$anchorBy[(int)$x['local_hotel_id']][]=$x;
    $identityBy=[];foreach($providerRows as $x)$identityBy[(int)$x['local_hotel_id']][]=$x;
    $manualBy=[];foreach($manual as $x)$manualBy[(int)$x['catalog_hotel_id']][]=$x;
    $sourceBy=[];foreach($cohort as $x)$sourceBy[(int)$x['tv']]=$x;
    $rows=[];$eligible=[];$country=null;
    foreach(IDS as $tv){
        $src=$sourceBy[$tv];$h=$hotelBy[$tv]??null;$aa=$anchorBy[$tv]??[];$why=null;$canonical=null;
        if(!$h||(int)$h['is_active']!==1)$why='target_missing_or_inactive';
        elseif(isset($manualBy[$tv]))$why='manual_target_protected';
        elseif(count($aa)!==1)$why='canonical_anchor_not_unique';
        else{$a=$aa[0];$ev=json_decode((string)$a['evidence_json'],true,32,JSON_THROW_ON_ERROR);$s=$ev['source']??[];if((int)($s['stateKey']??0)!==5||(string)($s['id']??'')!==(string)$a['external_hotel_id'])$why='canonical_anchor_state_mismatch';else$canonical=(string)$a['external_hotel_id'];}
        $row=['tv'=>$tv,'f4'=>(string)$src['f4'],'operator_link_sha256'=>(string)$src['operator_link_sha256'],'tour_id'=>$src['tour_id']??null,'search_id'=>$src['search_id']??null,'batch'=>$src['batch']??null,'name'=>$h['name']??null,'country_id'=>$h===null?null:(int)$h['country_id'],'canonical'=>$canonical,'current_provider_rows'=>$identityBy[$tv]??[]];
        if($why!==null){$row['state']='current_hold';$row['hold_reason']=$why;$rows[$tv]=$row;continue;}
        $country??=(int)$h['country_id'];need($country===(int)$h['country_id'],'country_drift');$rows[$tv]=$row;$eligible[]=$tv;
    }
    if(!$eligible){$out=$base+['state'=>'completed_read_only','andromeda_calls'=>0,'provider_operator_id'=>null,'country_id'=>$country,'counts'=>['current_hold'=>26],'rows'=>array_values($rows)];$rh=savej($dir.'/result.json',$out);savej($dir.'/receipt.json',$base+['state'=>'completed_read_only','andromeda_calls'=>0,'result_sha256'=>$rh,'counts'=>['current_hold'=>26]]);echo json_encode(['calls'=>0,'counts'=>['current_hold'=>26],'result_sha256'=>$rh])."\n";exit(0);}
    $cfg=require $root.'/_preview/search3-anex-candidate/.andromeda-private.php';need(($cfg['enabled']??false)===true&&is_string($cfg['catalog_path']??null),'config');
    $catalogPath=$country===1?$cfg['catalog_path']:dirname($cfg['catalog_path']).'/countries/'.$country.'.json';$saved=loadj($catalogPath);need((int)($saved['all']['params']['STATEINC']??0)===5,'catalog_state');
    $departure=one($saved['townfrom']['payload']['TOWNFROM']??[],['Москва','Moscow']);$biblio=one($saved['all']['payload']['OPERATORS']??[],['Библио-Глобус','Библио Глобус','Biblio Globus']);$namespace='operator_'.$biblio;
    $hotelDict=[];foreach($saved['all']['payload']['HOTELS']??[] as $x)if(isset($x['id']))$hotelDict[(string)$x['id']]=true;
    $attempt=[];foreach($eligible as $tv){if(!isset($hotelDict[(string)$rows[$tv]['canonical']])){$rows[$tv]['state']='current_hold';$rows[$tv]['hold_reason']='canonical_missing_from_saved_hotel_dictionary';continue;}$existing=array_values(array_filter($rows[$tv]['current_provider_rows'],fn($r)=>(string)$r['supplier_namespace']===$namespace));$rows[$tv]['current_biblio_rows']=$existing;$attempt[]=$tv;}
    if(!$attempt){$counts=[];foreach($rows as $x)$counts[$x['state']]=($counts[$x['state']]??0)+1;ksort($counts);$out=$base+['state'=>'completed_read_only','andromeda_calls'=>0,'provider_operator_id'=>$biblio,'supplier_namespace'=>$namespace,'departure_id'=>$departure,'country_id'=>$country,'counts'=>$counts,'rows'=>array_values($rows)];$rh=savej($dir.'/result.json',$out);savej($dir.'/receipt.json',$base+['state'=>'completed_read_only','andromeda_calls'=>0,'result_sha256'=>$rh,'counts'=>$counts]);echo json_encode(['calls'=>0,'counts'=>$counts,'result_sha256'=>$rh])."\n";exit(0);}
    $transport=new AnyTourAndromedaTransport(true);$wrap=function($url,$opts)use($transport,$cfg,&$calls){budget(dirname($cfg['catalog_path']));$calls++;return $transport($url,$opts);};
    savej($dir.'/login-reserved.json',['operation'=>OP,'state'=>'reserved_before_login','call_index'=>1]);$login=new AnyTourAndromedaClient($wrap,true);$login->login((string)$cfg['username'],(string)$cfg['password']);$session=$login->privateSession();need((bool)$session,'login');savej($dir.'/login-result.json',['operation'=>OP,'state'=>'login_succeeded','call_index'=>1]);
    foreach($attempt as $i=>$tv){$currentTv=$tv;$idx=$i+2;$x=$rows[$tv];savej($dir.'/call-'.$tv.'-reserved.json',['operation'=>OP,'state'=>'reserved_before_price','call_index'=>$idx,'tv'=>$tv,'f4'=>$x['f4'],'canonical'=>$x['canonical']]);
        try{$client=new AnyTourAndromedaClient($wrap,true);$client->restorePrivateSession($session);$params=['TOWNFROMINC'=>$departure,'STATEINC'=>5,'CHECKIN_BEG'=>'20261007','CHECKIN_END'=>'20261007','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>1,'OPERATORS'=>(string)$biblio,'HOTELS'=>(string)$x['canonical']];$reply=$client->price($params);
            $identities=[];$seen=[];foreach($reply['PRICES'] as $z){if((string)($z['operatorKey']??'')!==(string)$biblio)continue;$id=(string)($z['hotelKey']??'');$io=(string)($z['isOperatorHotelKey']??'');if($id===''||!in_array($io,['0','1'],true))continue;$key=$io.'|'.$id;if(isset($seen[$key]))continue;$seen[$key]=true;$identities[]=['hotelKey'=>$id,'isOperatorHotelKey'=>$io,'hotel'=>(string)($z['hotel']??''),'operator'=>(string)($z['operator']??'')];}
            $native=array_values(array_unique(array_map(fn($z)=>(string)$z['hotelKey'],array_filter($identities,fn($z)=>(string)$z['isOperatorHotelKey']==='1'))));
            if(count($native)===1)$state=$native[0]===(string)$x['f4']?'confirmed_f4_equal':'unique_operator_native_different';elseif(count($native)>1)$state='multiple_operator_native';elseif($identities)$state='catalog_only';else$state='not_returned';
            $row=$x+['state'=>$state,'provider_operator_id'=>$biblio,'supplier_namespace'=>$namespace,'price_page'=>(int)$reply['PAGE'],'pages_count'=>(int)$reply['PAGES_COUNT'],'operator_native_ids'=>$native,'identities'=>$identities,'response_sha256'=>hash('sha256',json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
        }catch(Throwable $e){$row=$x+['state'=>'supplier_error','provider_operator_id'=>$biblio,'supplier_namespace'=>$namespace,'error_class'=>get_class($e),'operator_native_ids'=>[],'identities'=>[]];}
        savej($dir.'/call-'.$tv.'-result.json',$row);$rows[$tv]=$row;
    }
    $counts=[];foreach($rows as $x){$state=(string)($x['state']??'unknown');$counts[$state]=($counts[$state]??0)+1;}ksort($counts);
    $out=$base+['state'=>'completed_read_only','andromeda_calls'=>$calls,'supplier_price_attempts'=>count($attempt),'provider_operator_id'=>$biblio,'supplier_namespace'=>$namespace,'departure_id'=>$departure,'country_id'=>$country,'counts'=>$counts,'rows'=>array_values($rows)];
    $rh=savej($dir.'/result.json',$out);savej($dir.'/receipt.json',$base+['state'=>'completed_read_only','andromeda_calls'=>$calls,'supplier_price_attempts'=>count($attempt),'result_sha256'=>$rh,'counts'=>$counts]);echo json_encode(['calls'=>$calls,'attempts'=>count($attempt),'counts'=>$counts,'result_sha256'=>$rh],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){savej($dir.'/failure.json',$base+['state'=>'failed_no_replay','andromeda_calls'=>$calls,'failure_at_tv'=>$currentTv,'error_class'=>get_class($e)]);exit(1);}
