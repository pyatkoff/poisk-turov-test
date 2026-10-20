<?php
declare(strict_types=1);

const OP = 'hotel-match-biblio-probe1-1971-20260922-v1';
const TV = 1273;
const F4 = '102626027108';
const EXPECTED_SAVED_CANONICAL = '2000080937';
const EXCLUDED_PREVIOUS = [1096,1098,1102,1105,1108,1111,1113,1114,1118,1134,1137,1139,1140,1141,1143,1146,1161,1162,1177,1245,1246,1249,1250,1252,1257,1262,1279,1282,1284,1292];

function need(bool $ok, string $code): void { if (!$ok) throw new RuntimeException($code); }
function loadj(string $path): array { $v=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR); need(is_array($v),'json'); return $v; }
function savej(string $path,array $value): string {
    $body=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    $f=fopen($path,'xb');need(is_resource($f),'exclusive_output');need(fwrite($f,$body)===strlen($body)&&fflush($f),'write');
    if(function_exists('fsync'))need(fsync($f),'fsync');fclose($f);return hash('sha256',$body);
}
function query(PDO $db,string $sql,array $params=[]): array { $st=$db->prepare($sql);$st->execute(array_values($params));return $st->fetchAll(PDO::FETCH_ASSOC)?:[]; }
function norm(string $s): string { $s=str_replace(['Ё','ё'],'е',trim($s));$s=function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);return preg_replace('/[^\p{L}\p{N}]+/u','',$s)??''; }
function one(array $rows,array $aliases): int {
    $want=array_map(fn($x)=>norm((string)$x),$aliases);$found=[];
    foreach($rows as $row)if(isset($row['id'],$row['name'])&&in_array(norm((string)$row['name']),$want,true))$found[(string)$row['id']]=true;
    need(count($found)===1,'dictionary_identity');return (int)array_key_first($found);
}
function budget(string $root): void {
    $path=$root.'/monthly-requests.json';$lock=fopen($path.'.lock','c');need(is_resource($lock)&&flock($lock,LOCK_EX),'budget_lock');
    try{$month=gmdate('Y-m');$s=is_file($path)?loadj($path):[];if(($s['month']??'')!==$month)$s=['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>5000000,'scope'=>'this_integration'];need((int)($s['reserved_requests']??0)<5000000,'quota');$s['reserved_requests']=(int)$s['reserved_requests']+1;$tmp=$path.'.'.bin2hex(random_bytes(4));file_put_contents($tmp,json_encode($s,JSON_THROW_ON_ERROR));chmod($tmp,0600);rename($tmp,$path);}finally{flock($lock,LOCK_UN);fclose($lock);}
}
function boundedError(Throwable $e): array {
    $m=(string)$e->getMessage();
    $map=[
        'ANDROMEDA_SUPPLIER_ERROR'=>'supplier_error',
        'ANDROMEDA_NETWORK_TRANSPORT_FAILURE'=>'network',
        'ANDROMEDA_INVALID_RESPONSE'=>'invalid_response',
        'ANDROMEDA_INVALID_PRICE_RESPONSE'=>'invalid_response',
        'ANDROMEDA_RESPONSE_TOO_LARGE'=>'response_limit',
        'ANDROMEDA_PRICE_ROW_BUDGET'=>'response_limit',
        'ANDROMEDA_REQUEST_BUDGET'=>'local_budget',
        'ANDROMEDA_TRANSPORT_ERROR'=>'transport_setup',
        'ANDROMEDA_CURL_REQUIRED'=>'transport_setup',
        'ANDROMEDA_LOGIN_REQUIRED'=>'session',
        'ANDROMEDA_PRICE_REPLAY_REFUSED'=>'local_replay_guard',
        'ANDROMEDA_ENDPOINT_REJECTED'=>'local_request_guard',
        'ANDROMEDA_ACTION_NOT_ALLOWED'=>'local_request_guard',
        'ANDROMEDA_INVALID_PARAMS'=>'local_request_guard',
    ];
    return ['error_category'=>$map[$m]??'unexpected_local','error_code'=>isset($map[$m])?$m:'UNCLASSIFIED_FIXED_EXCEPTION','error_class'=>get_class($e)];
}
function selfTest(): void {
    need(TV===1273&&F4==='102626027108'&&EXPECTED_SAVED_CANONICAL==='2000080937','probe');
    need(!in_array(TV,EXCLUDED_PREVIOUS,true),'previous_overlap');
    $e=boundedError(new RuntimeException('ANDROMEDA_REQUEST_BUDGET'));need($e['error_category']==='local_budget'&&$e['error_code']==='ANDROMEDA_REQUEST_BUDGET','error_map');
    $u=boundedError(new RuntimeException('secret supplier text'));need($u['error_category']==='unexpected_local'&&$u['error_code']==='UNCLASSIFIED_FIXED_EXCEPTION','error_redaction');
    echo "OK\n";
}
if(($argv[1]??'')==='--self-test'){selfTest();exit(0);}need(($argv[1]??'')==='--execute','mode');

$root=realpath((string)getenv('ANYTOUR_ROOT'));$dir=realpath((string)getenv('MATCH_OPERATION_DIR'));$sha=(string)getenv('MATCH_SOURCE_SHA');
need($root!==false&&basename($root)==='anytoour.ru'&&$dir!==false&&basename($dir)===OP&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'scope');
$res=loadj($dir.'/payload/reservation.json');$source=loadj($dir.'/payload/source.json');
need(($res['operation']??'')===OP&&($res['source_sha']??'')===$sha&&($res['script_sha256']??'')===hash_file('sha256',__FILE__),'reservation');
need(($res['source_sha256']??'')===hash_file('sha256',$dir.'/payload/source.json'),'source_hash');
need((int)($source['tv']??0)===TV&&(string)($source['f4']??'')===F4,'source_membership');
savej($dir.'/started.json',['operation'=>OP,'state'=>'started_no_replay','source_sha'=>$sha,'tv'=>TV,'f4'=>F4,'excluded_previous'=>EXCLUDED_PREVIOUS]);
$base=['operation'=>OP,'source_sha'=>$sha,'tv'=>TV,'f4'=>F4,'tourvisor_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true];
$calls=0;$lastStarted=0.0;
try{
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $app=$dir.'/payload';require_once $app.'/andromeda-client.php';require_once $app.'/andromeda-transport.php';
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $hotels=query($db,'SELECT id,country_id,name,is_active FROM catalog_hotels WHERE id=?',[TV]);
    $anchors=query($db,"SELECT external_hotel_id,local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id=? ORDER BY external_hotel_id",[TV]);
    $providerRows=query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE (local_hotel_id=? OR (supplier_namespace='operator_115' AND external_hotel_id=?)) ORDER BY local_hotel_id,supplier_namespace,external_hotel_id",[TV,F4]);
    $manual=query($db,'SELECT catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE catalog_hotel_id=? ORDER BY catalog_hotel_id',[TV]);
    $db->rollBack();
    $hold=null;$h=$hotels[0]??null;$canonical=null;
    if(count($hotels)!==1||(int)($h['is_active']??0)!==1)$hold='target_missing_or_inactive';
    elseif($manual)$hold='manual_target_protected';
    elseif(count($anchors)!==1)$hold='canonical_anchor_not_unique';
    else{$a=$anchors[0];$ev=json_decode((string)$a['evidence_json'],true,32,JSON_THROW_ON_ERROR);$s=$ev['source']??[];if((int)($s['stateKey']??0)!==5||(string)($s['id']??'')!==(string)$a['external_hotel_id'])$hold='canonical_anchor_state_mismatch';else{$canonical=(string)$a['external_hotel_id'];if($canonical!==EXPECTED_SAVED_CANONICAL)$hold='canonical_anchor_changed_from_saved_evidence';}}
    foreach($providerRows as $p){if((string)$p['supplier_namespace']==='operator_115'&&(string)$p['decision_status']==='accepted'){$hold='current_operator115_identity_present';break;}}
    $current=['target'=>$h,'canonical'=>$canonical,'provider_rows'=>$providerRows,'manual_rows'=>$manual];
    if($hold!==null){$row=$base+['state'=>'current_hold','hold_reason'=>$hold,'current'=>$current,'supplier_transport_invocations'=>0];$rh=savej($dir.'/result.json',$row);savej($dir.'/receipt.json',$base+['state'=>'completed_read_only_hold','result_sha256'=>$rh,'supplier_transport_invocations'=>0,'hold_reason'=>$hold]);echo json_encode(['state'=>'hold','hold'=>$hold,'calls'=>0,'result_sha256'=>$rh])."\n";exit(0);}

    $cfg=require $root.'/_preview/search3-anex-candidate/.andromeda-private.php';need(($cfg['enabled']??false)===true&&is_string($cfg['catalog_path']??null),'config');
    $country=(int)$h['country_id'];$catalogPath=$country===1?$cfg['catalog_path']:dirname($cfg['catalog_path']).'/countries/'.$country.'.json';$saved=loadj($catalogPath);need((int)($saved['all']['params']['STATEINC']??0)===5,'catalog_state');
    $departure=one($saved['townfrom']['payload']['TOWNFROM']??[],['Москва','Moscow']);$biblio=one($saved['all']['payload']['OPERATORS']??[],['Библио-Глобус','Библио Глобус','Biblio Globus']);need($biblio===115,'operator_changed');
    $hotelDict=[];foreach($saved['all']['payload']['HOTELS']??[] as $x)if(isset($x['id']))$hotelDict[(string)$x['id']]=true;
    if(!isset($hotelDict[$canonical])){$hold='canonical_missing_from_saved_hotel_dictionary';$row=$base+['state'=>'current_hold','hold_reason'=>$hold,'current'=>$current,'provider_operator_id'=>$biblio,'supplier_transport_invocations'=>0];$rh=savej($dir.'/result.json',$row);savej($dir.'/receipt.json',$base+['state'=>'completed_read_only_hold','result_sha256'=>$rh,'supplier_transport_invocations'=>0,'hold_reason'=>$hold]);echo json_encode(['state'=>'hold','hold'=>$hold,'calls'=>0,'result_sha256'=>$rh])."\n";exit(0);}

    $makeWrap=function(AnyTourAndromedaTransport $transport)use($cfg,&$calls,&$lastStarted){return function($url,$opts)use($transport,$cfg,&$calls,&$lastStarted){$wait=1.05-(microtime(true)-$lastStarted);if($lastStarted>0&&$wait>0)usleep((int)ceil($wait*1000000));budget(dirname($cfg['catalog_path']));$calls++;$lastStarted=microtime(true);return $transport($url,$opts);};};
    savej($dir.'/login-reserved.json',['operation'=>OP,'state'=>'reserved_before_login','call_index'=>1]);
    $loginTransport=new AnyTourAndromedaTransport(true);$login=new AnyTourAndromedaClient($makeWrap($loginTransport),true);$login->login((string)$cfg['username'],(string)$cfg['password']);$session=$login->privateSession();need((bool)$session,'login');savej($dir.'/login-result.json',['operation'=>OP,'state'=>'login_succeeded','call_index'=>1]);

    savej($dir.'/price-reserved.json',['operation'=>OP,'state'=>'reserved_before_price','call_index'=>2,'tv'=>TV,'f4'=>F4,'canonical'=>$canonical]);
    try{
        $priceTransport=new AnyTourAndromedaTransport(true);$client=new AnyTourAndromedaClient($makeWrap($priceTransport),true);$client->restorePrivateSession($session);
        $params=['TOWNFROMINC'=>$departure,'STATEINC'=>5,'CHECKIN_BEG'=>'20261007','CHECKIN_END'=>'20261007','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>1,'OPERATORS'=>(string)$biblio,'HOTELS'=>$canonical];
        $reply=$client->price($params);$identities=[];$seen=[];
        foreach($reply['PRICES'] as $z){if((string)($z['operatorKey']??'')!==(string)$biblio)continue;$id=(string)($z['hotelKey']??'');$io=(string)($z['isOperatorHotelKey']??'');if($id===''||!in_array($io,['0','1'],true))continue;$key=$io.'|'.$id;if(isset($seen[$key]))continue;$seen[$key]=true;$identities[]=['hotelKey'=>$id,'isOperatorHotelKey'=>$io,'hotel'=>(string)($z['hotel']??''),'operator'=>(string)($z['operator']??'')];}
        $native=array_values(array_unique(array_map(fn($z)=>(string)$z['hotelKey'],array_filter($identities,fn($z)=>(string)$z['isOperatorHotelKey']==='1'))));
        if(count($native)===1)$state=$native[0]===F4?'confirmed_f4_equal':'unique_operator_native_different';elseif(count($native)>1)$state='multiple_operator_native';elseif($identities)$state='catalog_only';else$state='not_returned';
        $row=$base+['state'=>$state,'provider_operator_id'=>$biblio,'supplier_namespace'=>'operator_'.$biblio,'departure_id'=>$departure,'country_id'=>$country,'canonical'=>$canonical,'supplier_transport_invocations'=>$calls,'operator_native_ids'=>$native,'identities'=>$identities,'price_page'=>(int)$reply['PAGE'],'pages_count'=>(int)$reply['PAGES_COUNT'],'response_sha256'=>hash('sha256',json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))];
    }catch(Throwable $e){$row=$base+['state'=>'supplier_error','provider_operator_id'=>$biblio,'supplier_namespace'=>'operator_'.$biblio,'departure_id'=>$departure,'country_id'=>$country,'canonical'=>$canonical,'supplier_transport_invocations'=>$calls]+boundedError($e)+['operator_native_ids'=>[],'identities'=>[]];}
    $prh=savej($dir.'/price-result.json',$row);$row['price_result_sha256']=$prh;$rh=savej($dir.'/result.json',$row);savej($dir.'/receipt.json',$base+['state'=>'completed_read_only','result_sha256'=>$rh,'supplier_transport_invocations'=>$calls,'result_state'=>$row['state']]);echo json_encode(['state'=>$row['state'],'calls'=>$calls,'result_sha256'=>$rh],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){$err=boundedError($e);savej($dir.'/failure.json',$base+['state'=>'failed_no_replay','supplier_transport_invocations'=>$calls]+$err);exit(1);}
