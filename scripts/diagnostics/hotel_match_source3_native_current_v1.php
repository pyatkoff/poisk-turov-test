<?php
declare(strict_types=1);

/** Fixed three-source Andromeda native-evidence acquisition. No Tourvisor or writer entrypoint. */
require_once __DIR__.'/hotel_match_primary_proof_audit_v1.php';

const S3N_OP='int-andromeda-match-source3-native-current-20261001-v1';
const S3N_BATCH='source3-native-20261001';
const S3N_MANIFEST_SHA='8af3a42bc63fb7b7eacb01df661cbf7ba6bcc83bdf9681adfc1e59c159b2cf85';
const S3N_HTTP_CAP=3;
const S3N_MONTHLY_LIMIT=5000000;
const S3N_OPERATORS=[5=>'operator_5',342=>'operator_342'];

function s3n_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function s3n_json(mixed $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function s3n_pos(mixed $value):?string{$v=trim((string)$value);return preg_match('/^[1-9][0-9]{0,31}$/D',$v)===1?$v:null;}
function s3n_read(string $path,int $cap=1048576):array{
    s3n_need(is_file($path)&&!is_link($path)&&filesize($path)>0&&filesize($path)<=$cap,'input_file');
    $value=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
    s3n_need(is_array($value),'input_json');return $value;
}
function s3n_save(string $path,array $value):string{
    $raw=s3n_json($value)."\n";$f=@fopen($path,'x+b');s3n_need($f!==false,'exclusive_create');
    try{s3n_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))s3n_need(fsync($f),'durable_sync');}
    finally{fclose($f);}chmod($path,0600);return hash('sha256',$raw);
}

function s3n_manifest(array $manifest):array{
    s3n_need(($manifest['schema']??null)==='match-source3-native-current/1'
        &&($manifest['batch']??null)===S3N_BATCH,'manifest_header');
    $request=$manifest['request']??null;$rows=$manifest['rows']??null;
    s3n_need(is_array($request)&&is_array($rows)&&array_is_list($rows)&&count($rows)===3,'manifest_shape');
    $expectedRequest=['townfrominc'=>1,'stateinc'=>5,'checkin_beg'=>'20261008','checkin_end'=>'20261029',
        'nights'=>7,'adults'=>2,'children'=>0,'currencyinc'=>643,'packettype'=>0,'group_by'=>32,'page'=>1];
    s3n_need($request===$expectedRequest,'manifest_request');
    $expected=['163887'=>[5,'operator_5',1124,'8319'],'2000057636'=>[342,'operator_342',21679,'24891'],
        '2000073063'=>[5,'operator_5',60766,null]];
    $out=[];$targets=[];
    foreach($rows as $row){
        s3n_need(is_array($row)&&is_string($row['catalog_id']??null)&&isset($expected[$row['catalog_id']]),'manifest_catalog');
        $cat=$row['catalog_id'];s3n_need(!isset($out[$cat]),'manifest_duplicate');
        [$operator,$namespace,$target,$targetNative]=$expected[$cat];
        s3n_need(($row['operator_id']??null)===$operator&&($row['supplier_namespace']??null)===$namespace
            &&($row['target_tv_hotel_id']??null)===$target&&($row['target_native_id_for_comparison']??null)===$targetNative
            &&($row['expected_country_id']??null)==='4'&&w76_sha($row['catalog_sha256']??null)
            &&w76_sha($row['evidence_sha256']??null),'manifest_row');
        $out[$cat]=$row;$targets[$target]=true;
    }
    ksort($out,SORT_NATURAL);s3n_need(array_keys($out)===array_keys($expected),'manifest_membership');
    return ['request'=>$request,'rows'=>$out,'target_ids'=>array_map('intval',array_keys($targets))];
}

/** Exact raw identity only: top-level catalog ID plus original operator-native hotelKey. */
function s3n_fact(array $row,string $catalogId,int $operatorId):array{
    if((int)($row['operatorKey']??0)!==$operatorId)return ['state'=>'other_operator','native_id'=>null];
    if((string)($row['hotelKey']??'')!==$catalogId)return ['state'=>'other_catalog','native_id'=>null];
    if(in_array($row['isOperatorHotelKey']??false,[true,1,'1','true'],true))return ['state'=>'operator_key_row','native_id'=>null];
    $original=is_array($row['original']??null)?$row['original']:[];
    $native=s3n_pos($original['hotelKey']??null);$originalOperator=(int)($original['operatorKey']??0);
    if($native===null||$originalOperator!==$operatorId)return ['state'=>'catalog_only','native_id'=>null];
    return ['state'=>'exact_catalog_to_native','native_id'=>$native];
}

/** Pure per-row CURRENT preflight. One HOLD never blocks another row. */
function s3n_preflight(array $scope,array $sources,array $targetOwners,array $hotels,array $manual,array $excluded):array{
    $out=[];
    foreach($scope['rows'] as $cat=>$request){
        $holds=[];$matches=$sources[$cat]??[];$source=count($matches)===1?$matches[0]:null;
        if($source===null)$holds[]='current_source_not_unique';
        else{
            if(($source['supplier_namespace']??null)!=='andromeda_catalog'||(string)($source['external_hotel_id']??'')!==$cat
                    ||($source['decision_status']??null)!=='pending'||($source['local_hotel_id']??null)!==null)$holds[]='current_source_not_pending_null';
            if(($source['catalog_sha256']??null)!==$request['catalog_sha256']||($source['evidence_sha256']??null)!==$request['evidence_sha256'])$holds[]='current_source_revision_differs';
            $history=json_decode((string)($source['evidence_json']??''),true);
            if(!is_array($history)||!is_array($history['source']??null)||(string)($history['source']['id']??'')!==$cat)$holds[]='current_source_history_review';
        }
        $target=(int)$request['target_tv_hotel_id'];$hotel=$hotels[$target]??null;
        if(!is_array($hotel)||(int)($hotel['is_active']??0)!==1)$holds[]='target_missing_or_inactive';
        elseif((string)($hotel['country_id']??'')!==$request['expected_country_id'])$holds[]='target_country_changed';
        if(isset($manual[$target])||isset($excluded[$target]))$holds[]='target_manual_or_exclusion';
        foreach($targetOwners[$target]??[] as $owner)if((string)($owner['external_hotel_id']??'')!==$cat)$holds[]='target_occupied';
        $holds=array_values(array_unique($holds));sort($holds,SORT_STRING);
        $out[]=['catalog_id'=>(string)$cat,'operator_id'=>$request['operator_id'],'supplier_namespace'=>$request['supplier_namespace'],
            'target_tv_hotel_id'=>$target,'target_native_id_for_comparison'=>$request['target_native_id_for_comparison'],
            'state'=>$holds===[]?'eligible_for_source_evidence':'hold','holds'=>$holds,'safe_to_write_now'=>false];
    }
    return $out;
}

function s3n_current(PDO $db,array $scope):array{
    $ids=array_keys($scope['rows']);$targets=$scope['target_ids'];$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $all=w76_q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256 FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 50001");
        s3n_need(count($all)<=50000,'identity_cap');$context=pm1_context($all);
        $ph=implode(',',array_fill(0,count($ids),'?'));$sources=[];
        foreach(w76_q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($ph) ORDER BY external_hotel_id",$ids) as $row)$sources[(string)$row['external_hotel_id']][]=$row;
        $ph=implode(',',array_fill(0,count($targets),'?'));$hotels=[];$manual=[];$excluded=[];
        foreach(w76_q($db,"SELECT id,country_id,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$targets) as $row)$hotels[(int)$row['id']]=$row;
        foreach(w76_q($db,"SELECT catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph)",$targets) as $row)$manual[(int)$row['catalog_hotel_id']]=true;
        foreach(w76_q($db,"SELECT catalog_hotel_id FROM anex_review_pair_exclusions WHERE catalog_hotel_id IN ($ph)",$targets) as $row)$excluded[(int)$row['catalog_hotel_id']]=true;
        $out=s3n_preflight($scope,$sources,$context['targets'],$hotels,$manual,$excluded);$db->rollBack();return $out;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function s3n_private_config(string $root):array{
    foreach([$root.'/_preview/search3-anex-candidate/.andromeda-private.php',$root.'/v2/.andromeda-private.php'] as $path){
        if(!is_file($path)||is_link($path))continue;$cfg=require $path;
        if(is_array($cfg)&&($cfg['enabled']??false)===true&&is_string($cfg['catalog_path']??null)&&$cfg['catalog_path']!==''
                &&is_string($cfg['username']??null)&&$cfg['username']!==''&&is_string($cfg['password']??null)&&$cfg['password']!=='')return $cfg;
    }
    throw new RuntimeException('andromeda_private_config_missing');
}
function s3n_budget(string $private,int $call,string $action):void{
    $path=$private.'/monthly-requests.json';$lock=fopen($path.'.lock','c');s3n_need($lock!==false&&flock($lock,LOCK_EX),'budget_lock');
    try{
        $month=gmdate('Y-m');$state=is_file($path)?s3n_read($path):[];
        if(($state['month']??'')!==$month)$state=['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>S3N_MONTHLY_LIMIT,'scope'=>'this_integration'];
        $used=(int)($state['reserved_requests']??0);$limit=(int)($state['monthly_limit']??S3N_MONTHLY_LIMIT);
        s3n_need($limit===S3N_MONTHLY_LIMIT&&$used<$limit,'monthly_quota');$state['reserved_requests']=$used+1;
        $state['last_match_operation']=S3N_OP;$state['last_match_call']=$call;$state['last_match_action']=$action;
        $tmp=$path.'.'.bin2hex(random_bytes(4));file_put_contents($tmp,s3n_json($state));chmod($tmp,0600);rename($tmp,$path);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function s3n_acquire(string $root,string $dir,array $scope,array $preflight):array{
    $eligible=[];foreach($preflight as $row)if($row['state']==='eligible_for_source_evidence')$eligible[$row['operator_id']][]=$row;
    ksort($eligible,SORT_NUMERIC);$evidence=[];$calls=0;$last=0.0;
    foreach($preflight as $row)$evidence[$row['catalog_id']]=['catalog_id'=>$row['catalog_id'],'operator_id'=>$row['operator_id'],
        'supplier_namespace'=>$row['supplier_namespace'],'target_tv_hotel_id'=>$row['target_tv_hotel_id'],
        'target_native_id_for_comparison'=>$row['target_native_id_for_comparison'],'state'=>$row['state']==='hold'?'preflight_hold':'not_returned_in_context',
        'holds'=>$row['holds'],'price_rows'=>0,'native_ids'=>[],'references'=>[],'matches_target_native'=>false,'safe_to_write_now'=>false];
    if($eligible===[])return ['provider_http_calls'=>0,'rows'=>array_values($evidence),'responses'=>[]];
    $integrations=dirname(__DIR__,2).'/app/integrations';require_once $integrations.'/andromeda-client.php';require_once $integrations.'/andromeda-transport.php';
    $cfg=s3n_private_config($root);$private=dirname((string)$cfg['catalog_path']);
    $call=function(string $url,array $opts)use($private,$dir,&$calls,&$last){
        $query=[];parse_str((string)parse_url($url,PHP_URL_QUERY),$query);$action=(string)($query['action']??'unknown');$next=$calls+1;
        s3n_need($next<=S3N_HTTP_CAP,'operation_http_cap');s3n_budget($private,$next,$action);$calls=$next;
        s3n_save($dir.'/http-'.str_pad((string)$calls,2,'0',STR_PAD_LEFT).'-reserved.json',['operation'=>S3N_OP,'call'=>$calls,'action'=>$action,'state'=>'reserved_before_http']);
        $wait=1.05-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));$transport=new AnyTourAndromedaTransport(true);$last=microtime(true);return $transport($url,$opts);
    };
    s3n_save($dir.'/login-reserved.json',['operation'=>S3N_OP,'state'=>'reserved_before_login']);
    $client=new AnyTourAndromedaClient($call,true);$client->login((string)$cfg['username'],(string)$cfg['password']);$session=$client->privateSession();s3n_need($session!==[],'login');
    s3n_save($dir.'/login-result.json',['operation'=>S3N_OP,'state'=>'login_succeeded']);$responses=[];
    $privateDir=$dir.'/evidence-private';s3n_need(mkdir($privateDir,0700),'evidence_dir');
    foreach($eligible as $operator=>$rows){
        $catalogIds=array_column($rows,'catalog_id');sort($catalogIds,SORT_NATURAL);$request=$scope['request'];
        $params=['TOWNFROMINC'=>$request['townfrominc'],'STATEINC'=>$request['stateinc'],'CHECKIN_BEG'=>$request['checkin_beg'],'CHECKIN_END'=>$request['checkin_end'],
            'NIGHTS_FROM'=>$request['nights'],'NIGHTS_TILL'=>$request['nights'],'ADULT'=>$request['adults'],'CHILD'=>$request['children'],
            'CURRENCYINC'=>$request['currencyinc'],'PACKETTYPE'=>$request['packettype'],'PAGE'=>$request['page'],'OPERATORS'=>(string)$operator,
            'HOTELS'=>implode(',',$catalogIds),'GROUP_BY'=>$request['group_by']];
        s3n_save($dir.'/operator-'.$operator.'-reserved.json',['operation'=>S3N_OP,'operator_id'=>$operator,'catalog_ids'=>$catalogIds,'state'=>'reserved_before_price']);
        $price=new AnyTourAndromedaClient($call,true);$price->restorePrivateSession($session);$reply=$price->price($params);
        $file='operator-'.$operator.'-page-1.json';$digest=s3n_save($privateDir.'/'.$file,$reply);$responses[]=['operator_id'=>$operator,'catalog_ids'=>$catalogIds,'sha256'=>$digest];
        foreach(($reply['PRICES']??[]) as $index=>$raw){
            if(!is_array($raw))continue;$catalog=s3n_pos($raw['hotelKey']??null);if($catalog===null||!isset($evidence[$catalog])||$evidence[$catalog]['operator_id']!==$operator)continue;
            $fact=s3n_fact($raw,$catalog,$operator);$evidence[$catalog]['price_rows']++;
            if($fact['state']==='exact_catalog_to_native'){$native=(string)$fact['native_id'];$evidence[$catalog]['native_ids'][$native]=true;
                $evidence[$catalog]['references'][]=['private_file'=>$file,'sha256'=>$digest,'json_pointer'=>'/PRICES/'.$index];}
        }
    }
    foreach($evidence as &$row){
        $ids=array_keys($row['native_ids']);sort($ids,SORT_NATURAL);$row['native_ids']=$ids;
        if($row['state']==='preflight_hold')continue;
        $row['state']=count($ids)===1?'captured_single_native':(count($ids)>1?'captured_ambiguous_native':($row['price_rows']>0?'catalog_only':'not_returned_in_context'));
        $target=$row['target_native_id_for_comparison'];$row['matches_target_native']=$target!==null&&$ids===[$target];
    }unset($row);
    return ['provider_http_calls'=>$calls,'rows'=>array_values($evidence),'responses'=>$responses];
}

function s3n_main(array $args):void{
    s3n_need(count($args)===2&&$args[1]==='--acquire-source-evidence','source3_disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    s3n_need(realpath($root)===$root&&basename($root)==='anytoour.ru'&&realpath($dir)===$dir&&basename($dir)===S3N_OP
        &&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'source3_execution_scope');
    $reservation=s3n_read($dir.'/reservation.json');
    s3n_need(($reservation['operation']??null)===S3N_OP&&($reservation['source_sha']??null)===$head&&($reservation['batch']??null)===S3N_BATCH
        &&($reservation['maximum_writes']??null)===0&&($reservation['provider_http_calls']??null)===S3N_HTTP_CAP
        &&($reservation['state']??null)==='reserved_before_db_and_provider','source3_reservation');
    $manifestPath=__DIR__.'/fixtures/hotel_match_source3_native_current_v1.json';
    s3n_need(is_file($manifestPath)&&!is_link($manifestPath)&&hash_file('sha256',$manifestPath)===S3N_MANIFEST_SHA,'source3_manifest_digest');
    $scope=s3n_manifest(s3n_read($manifestPath));foreach(['result.json','receipt.json'] as $file)s3n_need(!file_exists($dir.'/'.$file),'source3_no_replay');
    $state='failed_before_provider';$reason=null;$preflight=[];$databaseReads=0;$acquired=['provider_http_calls'=>0,'rows'=>[],'responses'=>[]];
    try{
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$preflight=s3n_current(v2_data_db(),$scope);$databaseReads=1;
        s3n_save($dir.'/current-preflight.json',['operation'=>S3N_OP,'source_sha'=>$head,'batch'=>S3N_BATCH,'rows'=>$preflight,'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false]);
        $acquired=s3n_acquire($root,$dir,$scope,$preflight);$state='completed_source3_native_current';
    }catch(Throwable $e){
        $reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,140,'UTF-8'));
        $calls=glob($dir.'/http-*-reserved.json')?:[];$acquired['provider_http_calls']=count($calls);$state=$calls===[]?'failed_before_provider':'terminal_failed_no_replay';
    }
    $result=['schema'=>'match-source3-native-current-result/1','state'=>$state,'reason'=>$reason,'operation'=>S3N_OP,'source_sha'=>$head,'batch'=>S3N_BATCH,
        'captured_at_utc'=>gmdate('c'),'requested_sources'=>3,'preflight_rows'=>$preflight,'evidence_rows'=>$acquired['rows'],'responses'=>$acquired['responses'],
        'provider_http_calls'=>$acquired['provider_http_calls'],'tourvisor_http_calls'=>0,'database_reads'=>$databaseReads,'database_writes'=>0,'mapping_writes'=>0,
        'safe_to_write_now'=>false,'acceptance_evaluated'=>false,'no_replay'=>$acquired['provider_http_calls']>0];
    $digest=s3n_save($dir.'/result.json',$result);s3n_save($dir.'/receipt.json',['state'=>$state,'operation'=>S3N_OP,'source_sha'=>$head,'batch'=>S3N_BATCH,
        'result_sha256'=>$digest,'provider_http_calls'=>$acquired['provider_http_calls'],'tourvisor_http_calls'=>0,'database_reads'=>$databaseReads,'database_writes'=>0,
        'mapping_writes'=>0,'safe_to_write_now'=>false,'no_replay'=>$acquired['provider_http_calls']>0]);
    echo s3n_json(['state'=>$state,'reason'=>$reason,'requested_sources'=>3,'provider_http_calls'=>$acquired['provider_http_calls'],
        'evidence_states'=>array_count_values(array_column($acquired['rows'],'state')),'safe_to_write_now'=>false])."\n";
    exit($state==='completed_source3_native_current'?0:2);
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)s3n_main($argv??[]);
