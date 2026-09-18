<?php
declare(strict_types=1);

const HMAC_OPERATION='hotel-match-user-seen-common4-samo-catalog-accept-1971-20260918-v1';
const HMAC_SOURCE_OPERATION='hotel-match-user-seen-common4-samo-catalog-acquire-1971-20260918-v2';
const HMAC_SOURCE_RESULT_SHA256='7516046dd2a0d052ea806ca07b337400d7d3769e2ff3fd7832e3479eecde1324';
const HMAC_SOURCE_COUNT=102;
const HMAC_MIN_WRITE=100;
const HMAC_ROW_LIMIT=100000;

function hmac_order(mixed $v): mixed {
    if(!is_array($v)) return $v;
    if($v!==[] && array_keys($v)!==range(0,count($v)-1)) ksort($v,SORT_STRING);
    foreach($v as $k=>$x)$v[$k]=hmac_order($x);
    return $v;
}
function hmac_json(mixed $v): string {return json_encode(hmac_order($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmac_hash(mixed $v): string {return hash('sha256',hmac_json($v));}
function hmac_durable(string $path,array $v): string {
    $raw=hmac_json($v)."\n";$f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_exists');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');}finally{fclose($f);}return hash('sha256',$raw);
}
function hmac_rows(PDO $pdo,string $sql,array $params=[]): array {$s=$pdo->prepare($sql);$s->execute(array_values($params));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];if(count($r)>HMAC_ROW_LIMIT)throw new RuntimeException('row_budget');return $r;}
function hmac_scalar(mixed $v,int $max=255): string {return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';}
function hmac_norm(string $v): string {$n=mb_strtolower(trim($v),'UTF-8');$n=strtr($n,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ','’'=>"'",'`'=>"'"]);$n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;return trim(preg_replace('/\s+/u',' ',$n)??$n);}
function hmac_valid_key(string $n): bool {$generic=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'resort'=>1,'spa'=>1,'apart'=>1,'apartment'=>1,'guesthouse'=>1,'guest'=>1,'house'=>1];$parts=preg_split('/\s+/u',$n,-1,PREG_SPLIT_NO_EMPTY)?:[];$sub=0;foreach($parts as $p)if(!isset($generic[$p])&&mb_strlen($p,'UTF-8')>=2)$sub++;return $n!==''&&mb_strlen($n,'UTF-8')>=4&&$sub>=1;}
function hmac_name_variants(string $name): array {
    $out=[];$add=function(string $v,string $why)use(&$out){$n=hmac_norm($v);if(hmac_valid_key($n))$out[$n][$why]=true;};$add($name,'canonical_full');
    if(preg_match('/^(.+?)\s*\((?:EX\.?|FORMERLY|БЫВШ\.?)[^)]*\)/iu',$name,$m))$add($m[1],'canonical_current');
    if(preg_match_all('/\((?:EX\.?|FORMERLY|БЫВШ\.?)\s*([^)]*)\)/iu',$name,$ms))foreach($ms[1] as $inside)foreach(preg_split('/[;|\/]+/u',$inside)?:[] as $part)$add($part,'explicit_former');
    $flat=[];foreach($out as $key=>$reasons){$rs=array_keys($reasons);sort($rs,SORT_STRING);$flat[$key]=$rs;}return $flat;
}
function hmac_external(mixed $v): ?string {$s=hmac_scalar($v,64);return preg_match('/^[1-9][0-9]{0,31}$/D',$s)?$s:null;}
function hmac_scope_digest(array $c): string {
    return hmac_hash(['schema'=>'andromeda-acquisition-state-scope/1','source_result_sha256'=>HMAC_SOURCE_RESULT_SHA256,'country_id'=>(int)$c['country_id'],'provider_state_id'=>(int)$c['provider_state_id']]);
}
function hmac_coverage(PDO $pdo): array {
    $and=[];foreach($pdo->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN)?:[] as $id)$and[(int)$id]=true;
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$anex=[];$ids=$pdo->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)?:[];foreach($ids as $id){$local=$registry->resolve('anex_online',(string)$id,'preview');if(is_int($local)&&$local>0)$anex[$local]=true;}
    $triple=count(array_intersect_key($and,$anex));
    return ['andromeda_unique_local'=>count($and),'andromeda_accepted_rows'=>(int)$pdo->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted'")->fetchColumn(),'anex_unique_local'=>count($anex),'all_three'=>$triple,'anex_tv_only'=>count($anex)-$triple,'andromeda_tv_only'=>count($and)-$triple];
}

if(in_array('--self-test',$argv??[],true)){
    $v=hmac_name_variants('SUN BAY (EX. SUN MARIS PARK)');if(!isset($v['sun bay'],$v['sun maris park']))throw new RuntimeException('variants');
    if(hmac_external('2000095344')!=='2000095344'||hmac_external('0')!==null)throw new RuntimeException('external');
    $d=hmac_scope_digest(['country_id'=>8,'provider_state_id'=>73]);if(!preg_match('/^[a-f0-9]{64}$/D',$d))throw new RuntimeException('scope_digest');
    echo "MATCH_COMMON4_SAMO_CATALOG_ACCEPT_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=(string)getenv('MATCH_OPERATION_DIR');$sourcePath=realpath((string)getenv('MATCH_SOURCE_RESULT_PATH'));$registryPath=realpath((string)getenv('MATCH_MAPPING_REGISTRY_PATH'));$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if(!$root||$opDir===''||!is_dir($opDir)||!is_file($opDir.'/reservation.json')||!is_string($sourcePath)||!is_file($sourcePath)||!is_string($registryPath)||!is_file($registryPath)||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha))throw new RuntimeException('runtime_guard');
$res=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);if(($res['operation']??'')!==HMAC_OPERATION||($res['state']??'')!=='reserved_before_db_access'||($res['source_result_sha256']??'')!==HMAC_SOURCE_RESULT_SHA256)throw new RuntimeException('reservation_guard');
$raw=(string)file_get_contents($sourcePath);if(!hash_equals(HMAC_SOURCE_RESULT_SHA256,hash('sha256',$raw)))throw new RuntimeException('source_result_hash');$source=json_decode($raw,true,128,JSON_THROW_ON_ERROR);
if(($source['operation']??'')!==HMAC_SOURCE_OPERATION||($source['state']??'')!=='completed_read_only'||($source['no_replay']??null)!==true||(int)($source['strict_catalog_candidate_count']??-1)!==HMAC_SOURCE_COUNT||!is_array($source['strict_catalog_candidates']??null)||count($source['strict_catalog_candidates'])!==HMAC_SOURCE_COUNT)throw new RuntimeException('source_contract');

require_once $registryPath;$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbf;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$committed=false;$phase='current_guard';$safe=[];$holds=[];$beforeCoverage=[];$afterCoverage=[];
try{
    $engine=(string)$pdo->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities'")->fetchColumn();if(strtoupper($engine)!=='INNODB')throw new RuntimeException('transactional_schema_required');
    $required=['supplier_namespace','external_hotel_id','local_hotel_id','decision_status','catalog_sha256','evidence_sha256','evidence_json'];$cols=hmac_rows($pdo,"SELECT COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities'");$colmap=[];foreach($cols as $c)$colmap[(string)$c['COLUMN_NAME']]=(string)$c['IS_NULLABLE'];foreach($required as $c)if(!isset($colmap[$c]))throw new RuntimeException('identity_schema_'.$c);

    $pdo->exec('SET SESSION innodb_lock_wait_timeout=10');$pdo->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$pdo->beginTransaction();
    $identityRows=hmac_rows($pdo,"SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE");$beforeIdentity=[];$externalIndex=[];$acceptedLocal=[];
    foreach($identityRows as $r){$k=(string)$r['supplier_namespace'].':'.(string)$r['external_hotel_id'];$beforeIdentity[$k]=hmac_hash($r);if($r['supplier_namespace']==='andromeda_catalog'){$externalIndex[(string)$r['external_hotel_id']][]=$r;if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$acceptedLocal[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];}}
    $beforeCoverage=hmac_coverage($pdo);

    $countries=[];$seenExternal=[];$seenLocal=[];
    foreach($source['strict_catalog_candidates'] as $c){if(!is_array($c))throw new RuntimeException('candidate_shape');$hid=(int)($c['hotel_id']??0);$cid=(int)($c['country_id']??0);$ext=hmac_external($c['external_hotel_id']??null);$sid=(int)($c['provider_state_id']??0);if($hid<1||$cid<1||$ext===null||$sid<1)throw new RuntimeException('candidate_id');if(isset($seenExternal[$ext])||isset($seenLocal[$hid]))throw new RuntimeException('candidate_duplicate');$seenExternal[$ext]=true;$seenLocal[$hid]=true;$countries[$cid]=true;}
    $cids=array_keys($countries);sort($cids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($cids),'?'));
    $allLocal=hmac_rows($pdo,"SELECT id,country_id,country_name,region_name,subregion_name,name,is_active FROM catalog_hotels WHERE country_id IN ($ph) AND is_active=1 ORDER BY country_id,id FOR UPDATE",$cids);
    $localById=[];$competition=[];foreach($allLocal as $h){$id=(int)$h['id'];$localById[$id]=$h;foreach(hmac_name_variants((string)$h['name']) as $k=>$why)$competition[(int)$h['country_id']][$k][$id]=true;}

    foreach($source['strict_catalog_candidates'] as $c){$hid=(int)$c['hotel_id'];$cid=(int)$c['country_id'];$ext=(string)$c['external_hotel_id'];$reason=[];$h=$localById[$hid]??null;
        if(!$h||(int)$h['is_active']!==1)$reason[]='local_missing_or_inactive';
        elseif((int)$h['country_id']!==$cid)$reason[]='country_changed';
        else{
            foreach(['name'=>'hotel_name','country_name'=>'country_name','region_name'=>'region_name','subregion_name'=>'subregion_name'] as $cur=>$sealed)if(hmac_norm((string)($h[$cur]??''))!==hmac_norm((string)($c[$sealed]??'')))$reason[]='local_'.$cur.'_changed';
            $key=hmac_norm((string)($c['match_key']??''));$lv=hmac_name_variants((string)$h['name']);$pv=hmac_name_variants((string)($c['supplier_name']??''));
            if($key===''||!isset($lv[$key])||!isset($pv[$key]))$reason[]='match_key_drift';
            $reasons=array_values(array_map('strval',$c['match_reasons']??[]));sort($reasons,SORT_STRING);$still=array_values(array_intersect($reasons,$lv[$key]??[]));if(!$still)$reason[]='match_reason_drift';
            $hits=array_keys($competition[$cid][$key]??[]);sort($hits,SORT_NUMERIC);if(count($hits)!==1||(int)$hits[0]!==$hid)$reason[]='current_country_name_not_unique';
        }
        if(!empty($c['current_identity_holds']))$reason[]='sealed_current_identity_hold';
        if(isset($externalIndex[$ext]))$reason[]='external_identity_now_exists';
        foreach($acceptedLocal[$hid]??[] as $other)if($other!==$ext)$reason[]='local_occupied_by_andromeda';
        if($reason){$holds[]=['external_hotel_id'=>$ext,'local_hotel_id'=>$hid,'reasons'=>array_values(array_unique($reason))];continue;}
        $proof=['schema'=>'hotel-match-user-seen-common4-samo-catalog-accept/1','operation_id'=>HMAC_OPERATION,'source_operation'=>HMAC_SOURCE_OPERATION,'source_result_sha256'=>HMAC_SOURCE_RESULT_SHA256,'source_candidate'=>$c,'current_local'=>['id'=>$hid,'country_id'=>(int)$h['country_id'],'country_name'=>(string)$h['country_name'],'region_name'=>(string)$h['region_name'],'subregion_name'=>(string)$h['subregion_name'],'name'=>(string)$h['name']],'identity_rule'=>'unique_current_country_name_variant_plus_provider_state_binding','provider_town_semantics_proven'=>false,'catalog_sha256_semantics'=>'acquisition_state_scope_digest_v1'];
        $ejson=hmac_json($proof);$safe[]=['external_hotel_id'=>$ext,'local_hotel_id'=>$hid,'catalog_sha256'=>hmac_scope_digest($c),'evidence_sha256'=>hash('sha256',$ejson),'evidence_json'=>$ejson];
    }
    if(count($safe)<HMAC_MIN_WRITE){$pdo->rollBack();$result=['status'=>'held_below_write_threshold','operation'=>HMAC_OPERATION,'source_result_sha256'=>HMAC_SOURCE_RESULT_SHA256,'source_candidates'=>HMAC_SOURCE_COUNT,'current_safe'=>count($safe),'current_holds'=>count($holds),'holds'=>$holds,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'readback_verified'=>false];}
    else{
        hmac_durable($opDir.'/precommit.json',['operation'=>HMAC_OPERATION,'source_result_sha256'=>HMAC_SOURCE_RESULT_SHA256,'safe_count'=>count($safe),'holds'=>$holds,'rows'=>array_map(fn($r)=>['external_hotel_id'=>$r['external_hotel_id'],'local_hotel_id'=>$r['local_hotel_id'],'catalog_sha256'=>$r['catalog_sha256'],'evidence_sha256'=>$r['evidence_sha256']],$safe)]);
        $ins=$pdo->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES('andromeda_catalog',?,?,'accepted',?,?,?)");
        foreach($safe as $r){$ins->execute([$r['external_hotel_id'],$r['local_hotel_id'],$r['catalog_sha256'],$r['evidence_sha256'],$r['evidence_json']]);if($ins->rowCount()!==1)throw new RuntimeException('insert_count');}
        $pdo->commit();$committed=true;$phase='post_commit_readback';
        $pdo->exec('START TRANSACTION READ ONLY');$read=$pdo->prepare("SELECT local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");$readRows=[];
        foreach($safe as $r){$read->execute([$r['external_hotel_id']]);$v=$read->fetch(PDO::FETCH_ASSOC);if(!$v||(int)$v['local_hotel_id']!==$r['local_hotel_id']||$v['decision_status']!=='accepted'||$v['catalog_sha256']!==$r['catalog_sha256']||$v['evidence_sha256']!==$r['evidence_sha256']||hash('sha256',(string)$v['evidence_json'])!==$r['evidence_sha256'])throw new RuntimeException('post_commit_row_mismatch');$readRows[]=['external_hotel_id'=>$r['external_hotel_id'],'local_hotel_id'=>$r['local_hotel_id'],'evidence_sha256'=>$r['evidence_sha256']];}
        $afterAll=hmac_rows($pdo,"SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id");$afterIndex=[];foreach($afterAll as $r)$afterIndex[(string)$r['supplier_namespace'].':'.(string)$r['external_hotel_id']]=hmac_hash($r);foreach($beforeIdentity as $k=>$hash)if(($afterIndex[$k]??null)!==$hash)throw new RuntimeException('existing_identity_changed');if(count($afterIndex)!==count($beforeIdentity)+count($safe))throw new RuntimeException('identity_count_drift');$afterCoverage=hmac_coverage($pdo);$pdo->rollBack();
        $result=['status'=>'accepted','operation'=>HMAC_OPERATION,'source_result_sha256'=>HMAC_SOURCE_RESULT_SHA256,'source_candidates'=>HMAC_SOURCE_COUNT,'current_safe'=>count($safe),'current_holds'=>count($holds),'holds'=>$holds,'inserted'=>count($safe),'readback_verified'=>true,'existing_identities_unchanged'=>true,'before_identity_count'=>count($beforeIdentity),'after_identity_count'=>count($afterIndex),'before_coverage'=>$beforeCoverage,'after_coverage'=>$afterCoverage,'coverage_delta'=>['andromeda_unique_local'=>$afterCoverage['andromeda_unique_local']-$beforeCoverage['andromeda_unique_local'],'andromeda_accepted_rows'=>$afterCoverage['andromeda_accepted_rows']-$beforeCoverage['andromeda_accepted_rows'],'all_three'=>$afterCoverage['all_three']-$beforeCoverage['all_three']],'rows'=>$readRows,'database_writes'=>count($safe),'mapping_writes'=>count($safe),'supplier_calls'=>0,'tourvisor_calls'=>0];
    }
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$result=['status'=>$committed?'committed_readback_unconfirmed':'failed_rolled_back','operation'=>HMAC_OPERATION,'phase'=>$phase,'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',mb_substr($e->getMessage(),0,120)),'source_result_sha256'=>HMAC_SOURCE_RESULT_SHA256,'database_writes'=>$committed?null:0,'mapping_writes'=>$committed?null:0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];}
$resultSha=hmac_durable($opDir.'/result.json',$result);hmac_durable($opDir.'/receipt.json',['operation'=>HMAC_OPERATION,'status'=>$result['status'],'result_sha256'=>$resultSha,'source_result_sha256'=>HMAC_SOURCE_RESULT_SHA256,'committed'=>$committed,'database_writes'=>$result['database_writes']??null,'mapping_writes'=>$result['mapping_writes']??null,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit(in_array($result['status'],['accepted','held_below_write_threshold'],true)?0:2);
