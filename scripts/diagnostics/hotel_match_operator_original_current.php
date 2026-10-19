<?php
declare(strict_types=1);
/* MATCH-only CURRENT intake. No supplier transport, DDL or mapping mutation. */
const MOC_OP='hotel-match-operator-original-current-1971-20260915-v1';
const MOC_CORE=[1,2,4,8,9,10,12,16];
function moc_write(string $dir,string $name,array $doc): string {
    $raw=json_encode($doc,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    $f=fopen($dir.'/'.$name,'x+b'); if(!$f)throw new RuntimeException('exclusive_output');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('output_failed');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('output_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('output_readback');}finally{fclose($f);}return hash('sha256',$raw);
}
function moc_safe(array $row): array {
    $out=[];foreach(['name','lName','hotel','hotelKey','operatorKey','isOperatorHotelKey','state','stateKey','stateLName','town','townKey','townLName','latitude','longitude','country_id','country_name','region_name','star','category'] as $k)
        if(isset($row[$k])&&is_scalar($row[$k])&&strlen((string)$row[$k])<=512)$out[$k]=$row[$k];return $out;
}
function moc_pairs($value,array &$pairs,string $source,int $depth=0): void {
    if(!is_array($value)||$depth>20)return;
    if(isset($value['hotelKey'],$value['operatorKey'],$value['original']['hotelKey'])&&array_key_exists('isOperatorHotelKey',$value)){
        $d=(string)$value['hotelKey'];$o=(string)$value['operatorKey'];$n=(string)$value['original']['hotelKey'];
        if(preg_match('/^[1-9][0-9]{0,19}$/D',$d)&&preg_match('/^[1-9][0-9]{0,8}$/D',$o)&&preg_match('/^[1-9][0-9]{0,19}$/D',$n)&&(string)$value['isOperatorHotelKey']==='0'){
            $key="$o|$n|$d";$pairs[$key]=['operator_key'=>$o,'native_hotel_id'=>$n,'andromeda_hotel_id'=>$d,'source'=>$source,'hotel_name'=>(string)($value['hotel']??''),'original_name'=>(string)($value['original']['hotel']??'')];
        }
    }
    foreach($value as $child)if(is_array($child))moc_pairs($child,$pairs,$source,$depth+1);
}
if(in_array('--self-test',$argv??[],true)){
    $p=[];moc_pairs(['hotelKey'=>177152,'operatorKey'=>5,'isOperatorHotelKey'=>0,'original'=>['hotelKey'=>4158]],$p,'test');
    if(count($p)!==1||!isset($p['5|4158|177152']))throw new RuntimeException('pair_test');
    moc_pairs(['hotelKey'=>177152,'operatorKey'=>8,'isOperatorHotelKey'=>0,'original'=>['hotelKey'=>4158]],$p,'test');if(count($p)!==2)throw new RuntimeException('namespace_test');
    moc_pairs(['hotelKey'=>177152,'operatorKey'=>9,'isOperatorHotelKey'=>1,'original'=>['hotelKey'=>4158]],$p,'test');if(count($p)!==2)throw new RuntimeException('quarantine_test');
    if(moc_safe(['name'=>'X','password'=>'hidden'])!==['name'=>'X'])throw new RuntimeException('allowlist_test');
    echo "4 CURRENT intake self-tests PASS\n";exit;
}
$op=getenv('MATCH_OPERATION_ID')?:'';$sha=getenv('MATCH_SOURCE_SHA')?:'';$dir=getenv('HOME').'/.anytoour-match/operations/'.MOC_OP;
if($op!==MOC_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha)||!is_file($dir.'/reservation.json'))throw new RuntimeException('reservation_required');
$res=json_decode(file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
if(($res['operation_id']??null)!==$op||($res['source_sha']??null)!==$sha||($res['state']??null)!=='reserved_before_db_access')throw new RuntimeException('reservation_mismatch');
$phase='bootstrap';$db=null;
try{
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $phase='schema';$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    $tables=$db->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME LIKE '%hotel%identit%' OR TABLE_NAME LIKE '%operator%' OR TABLE_NAME IN ('andromeda_search_hotel_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions')) ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
    $schemas=[];foreach($tables as $t){$name=$t['TABLE_NAME'];if(!preg_match('/^[a-z][a-z0-9_]*$/D',$name))throw new RuntimeException('table_name');$q=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$q->execute([$name]);$schemas[$name]=['engine'=>$t['ENGINE'],'columns'=>$q->fetchAll(PDO::FETCH_ASSOC)];}
    $phase='identities';$ids=$db->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
    $obs=$db->query("SELECT supplier_namespace,external_hotel_id,country_id,hotel_name,observed_at_utc FROM andromeda_search_hotel_observations WHERE country_id IN (1,2,4,8,9,10,12,16) ORDER BY observed_at_utc DESC")->fetchAll(PDO::FETCH_ASSOC);
    $observations=[];foreach($obs as $r){$k=$r['supplier_namespace'].'|'.$r['external_hotel_id'];if(!isset($observations[$k]))$observations[$k]=['frequency'=>0,'latest'=>$r];++$observations[$k]['frequency'];}
    $locals=[];foreach($db->query('SELECT id,country_id,name,region_name,subregion_name,latitude,longitude,is_active FROM catalog_hotels WHERE country_id IN (1,2,4,8,9,10,12,16) AND is_active=1')->fetchAll(PDO::FETCH_ASSOC) as $h)$locals[(string)$h['id']]=$h;
    $targets=[];$saved=[];$namespaces=[];$operatorRows=[];
    foreach($ids as $r){$ns=(string)$r['supplier_namespace'];$namespaces[$ns][$r['decision_status']]=($namespaces[$ns][$r['decision_status']]??0)+1;$ext=(string)$r['external_hotel_id'];$ev=json_decode((string)($r['evidence_json']??''),true)?:[];moc_pairs($ev,$saved,"registry:$ns:$ext");
        if($ns!=='andromeda_catalog'){$operatorRows[]=['supplier_namespace'=>$ns,'external_hotel_id'=>$ext,'local_hotel_id'=>$r['local_hotel_id'],'decision_status'=>$r['decision_status']];continue;}
        $o=$observations[$ns.'|'.$ext]??null;$h=$locals[(string)($r['local_hotel_id']??'')]??null;$country=(int)($o['latest']['country_id']??$h['country_id']??0);if(!in_array($country,MOC_CORE,true))continue;
        $source=is_array($ev['source']??null)?moc_safe($ev['source']):[];
        $targets[]=['andromeda_hotel_id'=>$ext,'country_id'=>$country,'local_hotel_id'=>$r['local_hotel_id'],'decision_status'=>$r['decision_status'],'frequency'=>$o['frequency']??0,'latest'=>$o['latest']??null,'source'=>$source,'local'=>$h,'identity_evidence_sha256'=>$r['evidence_sha256']??null];
    }
    usort($targets,static fn($a,$b)=>(($a['local_hotel_id']===null?0:1)<=>($b['local_hotel_id']===null?0:1))?:($b['frequency']<=>$a['frequency'])?:strcmp($a['andromeda_hotel_id'],$b['andromeda_hotel_id']));
    $db->exec('ROLLBACK');$phase='receipt';
    $result=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','schemas'=>$schemas,'namespace_counts'=>$namespaces,'registry_rows'=>count($ids),'observation_rows'=>count($obs),'targets'=>$targets,'target_count'=>count($targets),'existing_operator_rows'=>$operatorRows,'saved_typed_pairs'=>array_values($saved),'database_writes'=>0,'supplier_calls'=>0,'no_replay'=>true];
    $digest=moc_write($dir,'result.json',$result);moc_write($dir,'receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$digest,'readback_verified'=>true,'database_writes'=>0,'supplier_calls'=>0,'no_replay'=>true]);echo json_encode(['targets'=>count($targets),'saved_typed_pairs'=>count($saved),'namespace_counts'=>$namespaces])."\n";
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$digest=moc_write($dir,'result.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','phase'=>$phase,'error_class'=>get_class($e),'database_writes'=>0,'supplier_calls'=>0,'no_replay'=>true]);moc_write($dir,'receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','result_sha256'=>$digest,'readback_verified'=>true,'no_replay'=>true]);fwrite(STDERR,'CURRENT_READ_FAILED:'.$phase."\n");exit(2);}
