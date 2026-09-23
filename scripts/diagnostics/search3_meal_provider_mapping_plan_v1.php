<?php
/** Read-only CURRENT evidence plan for global Tourvisor meal-code mappings. */
declare(strict_types=1);

const SMPP_OPERATION='search3-meal-provider-mapping-plan-3353-20260923-v1';
const SMPP_RELATION_TABLES=[
    'anytour_search_meal_provider_mappings_v1',
    'anytour_search_meal_memberships_v1',
    'anytour_hotel_meal_concepts_v2',
    'anytour_hotel_stay_mappings_v2',
];
const SMPP_ROW_LIMIT=5000;

function smpp_json(array $value): string
{
    return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}
function smpp_save(string $path,array $value): void
{
    $h=fopen($path,'x');
    if(!$h) throw new RuntimeException('EXCLUSIVE_OUTPUT_EXISTS');
    try {
        $bytes=smpp_json($value);
        if(fwrite($h,$bytes)!==strlen($bytes)||!fflush($h)) throw new RuntimeException('OUTPUT_WRITE_FAILED');
        if(function_exists('fsync')&&!fsync($h)) throw new RuntimeException('OUTPUT_SYNC_FAILED');
    } finally { fclose($h); }
}
function smpp_select(PDO $db,string $sql,array $args=[]): array
{
    if(!str_starts_with($sql,'SELECT ')||str_contains($sql,';')) throw new RuntimeException('SELECT_ONLY');
    $stmt=$db->prepare($sql);$stmt->execute($args);return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function smpp_id(mixed $value): int
{
    if((!is_int($value)&&!is_string($value))||preg_match('/^[1-9][0-9]*$/D',(string)$value)!==1||filter_var($value,FILTER_VALIDATE_INT)===false) {
        throw new RuntimeException('INVALID_PLAN_ID');
    }
    return (int)$value;
}
function smpp_native_from_hex(mixed $hex): ?string
{
    if(!is_string($hex)||$hex===''||strlen($hex)>256||strlen($hex)%2!==0||preg_match('/^[0-9A-F]+$/D',$hex)!==1) return null;
    $raw=hex2bin($hex);
    if(!is_string($raw)||$raw===''||strlen($raw)>128||preg_match('//u',$raw)!==1||preg_match('/[\x00-\x1f\x7f]/',$raw)) return null;
    return $raw;
}
function smpp_evidence_set_sha(array $rows): string
{
    $items=[];
    foreach($rows as $row){
        $sha=$row['evidence_sha256']??null;
        if(is_string($sha)&&preg_match('/^[0-9a-f]{64}$/D',$sha)===1) $items[$sha]=true;
    }
    $items=array_keys($items);sort($items,SORT_STRING);
    return hash('sha256',smpp_json($items));
}
/** @return array{candidates:array,holds:array,counts:array} */
function smpp_classify(array $plans,array $rows): array
{
    $planById=[];
    foreach($plans as $row){
        $id=smpp_id($row['id']??null);$code=$row['code']??null;$name=$row['name_ru']??null;$active=(int)($row['is_active']??0);
        if(!is_string($code)||$code===''||strlen($code)>64||!is_string($name)||$name===''||strlen($name)>255||!in_array($active,[0,1],true)) throw new RuntimeException('INVALID_PLAN_ROW');
        if(isset($planById[$id])) throw new RuntimeException('DUPLICATE_PLAN_ID');
        $planById[$id]=['id'=>$id,'code'=>$code,'nameRu'=>$name,'active'=>$active===1];
    }
    $groups=[];$invalid=[];
    foreach($rows as $index=>$row){
        $native=smpp_native_from_hex($row['external_hex']??null);
        if($native===null){$invalid[]=['rowIndex'=>$index,'reason'=>'invalid_native_key','externalKeySha256'=>hash('sha256',(string)($row['external_hex']??''))];continue;}
        $groups[$native][]=$row;
    }
    ksort($groups,SORT_STRING);$candidates=[];$holds=$invalid;
    foreach($groups as $native=>$items){
        $planIds=[];$badPlan=false;$explicit=0;
        foreach($items as $row){
            try{$pid=smpp_id($row['meal_id']??null);}catch(Throwable){$badPlan=true;continue;}
            $plan=$planById[$pid]??null;
            if($plan===null||!$plan['active']){$badPlan=true;continue;}
            $planIds[$pid]=true;
            $ref=$row['evidence_ref']??null;$sha=$row['evidence_sha256']??null;$reviewed=$row['reviewed_by']??null;
            if(!is_string($ref)||trim($ref)===''||!is_string($sha)||preg_match('/^[0-9a-f]{64}$/D',$sha)!==1||!is_string($reviewed)||trim($reviewed)===''){$badPlan=true;continue;}
            $token=';tv-meal:'.$native.'->'.$plan['code'];
            if(str_contains($ref,$token)) $explicit++;
        }
        $ids=array_keys($planIds);sort($ids,SORT_NUMERIC);
        $base=['nativeId'=>$native,'rowCount'=>count($items),'planIds'=>$ids,'explicitEvidenceCount'=>$explicit,'evidenceSetSha256'=>smpp_evidence_set_sha($items)];
        if($badPlan){$holds[]=$base+['reason'=>'invalid_or_inactive_plan_or_evidence'];continue;}
        if(count($ids)!==1){$holds[]=$base+['reason'=>'conflicting_plan_ids'];continue;}
        if($explicit<1){$holds[]=$base+['reason'=>'missing_explicit_tv_meal_evidence'];continue;}
        $plan=$planById[$ids[0]];
        $candidates[]=$base+['plan'=>['id'=>$plan['id'],'code'=>$plan['code'],'nameRu'=>$plan['nameRu']]];
    }
    $reasons=[];foreach($holds as $hold){$r=(string)$hold['reason'];$reasons[$r]=($reasons[$r]??0)+1;}ksort($reasons,SORT_STRING);
    return ['candidates'=>$candidates,'holds'=>$holds,'counts'=>['planRows'=>count($plans),'acceptedLegacyRows'=>count($rows),'validNativeKeys'=>count($groups),'candidateNativeKeys'=>count($candidates),'heldNativeKeys'=>count($holds),'holdReasons'=>$reasons]];
}
function smpp_snapshot(PDO $db): array
{
    if($db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'||$db->inTransaction()) throw new RuntimeException('DEDICATED_MYSQL_REQUIRED');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
    try{
        $plans=smpp_select($db,'SELECT id,code,name_ru,is_active FROM anytour_meal_plans ORDER BY id LIMIT 101');
        if(count($plans)>100) throw new RuntimeException('PLAN_LIMIT');
        $totalRows=(int)smpp_select($db,"SELECT COUNT(*) AS n FROM anytour_stay_mappings WHERE namespace='legacy_catalog' AND kind='meal' AND key_kind='code' AND state='accepted'")[0]['n'];
        if($totalRows>SMPP_ROW_LIMIT) throw new RuntimeException('LEGACY_ROW_LIMIT');
        $rows=smpp_select($db,"SELECT HEX(external_key) AS external_hex,meal_id,evidence_ref,evidence_sha256,reviewed_by FROM anytour_stay_mappings WHERE namespace='legacy_catalog' AND kind='meal' AND key_kind='code' AND state='accepted' ORDER BY external_key,meal_id,evidence_sha256 LIMIT ".(SMPP_ROW_LIMIT+1));
        if(count($rows)!==$totalRows) throw new RuntimeException('LEGACY_ROW_COUNT_DRIFT');
        $slots=implode(',',array_fill(0,count(SMPP_RELATION_TABLES),'?'));
        $present=smpp_select($db,'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$slots.') ORDER BY TABLE_NAME',SMPP_RELATION_TABLES);
        $present=array_fill_keys(array_column($present,'TABLE_NAME'),true);$schema=[];
        foreach(SMPP_RELATION_TABLES as $table)$schema[$table]=isset($present[$table])?'present':'absent';
        $classification=smpp_classify($plans,$rows);
        $db->rollBack();
        return ['schema'=>$schema,'planRows'=>$plans,'acceptedLegacyRowCount'=>$totalRows,'acceptedLegacyRowsSha256'=>hash('sha256',smpp_json($rows))]+$classification;
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function smpp_self_test(): void
{
    $plans=[
        ['id'=>1,'code'=>'no-meals','name_ru'=>'Без питания','is_active'=>1],
        ['id'=>2,'code'=>'breakfast','name_ru'=>'Завтраки','is_active'=>1],
        ['id'=>7,'code'=>'all-inclusive','name_ru'=>'Всё включено','is_active'=>1],
        ['id'=>8,'code'=>'retired','name_ru'=>'Старое','is_active'=>0],
    ];
    $row=fn(string $native,int $plan,string $ref,string $sha)=>['external_hex'=>strtoupper(bin2hex($native)),'meal_id'=>$plan,'evidence_ref'=>$ref,'evidence_sha256'=>str_repeat($sha,64),'reviewed_by'=>'fixture'];
    $rows=[
        $row('7',7,'review://x;tv-meal:7->all-inclusive','a'),
        $row('7',7,'review://y','b'),
        $row('2',2,'review://z;tv-meal:2->breakfast','c'),
        $row('9',7,'review://no-token','d'),
        $row('10',7,'review://a;tv-meal:10->all-inclusive','e'),
        $row('10',2,'review://b;tv-meal:10->breakfast','f'),
        $row('11',8,'review://c;tv-meal:11->retired','1'),
        ['external_hex'=>'00','meal_id'=>1,'evidence_ref'=>'review://bad','evidence_sha256'=>str_repeat('2',64),'reviewed_by'=>'fixture'],
    ];
    $result=smpp_classify($plans,$rows);
    if(array_map('strval',array_column($result['candidates'],'nativeId'))!==['2','7']) {fwrite(STDERR,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n"); throw new RuntimeException('SELF_CANDIDATES');}
    if(($result['counts']['holdReasons']['missing_explicit_tv_meal_evidence']??0)!==1) throw new RuntimeException('SELF_NO_TOKEN');
    if(($result['counts']['holdReasons']['conflicting_plan_ids']??0)!==1) throw new RuntimeException('SELF_CONFLICT');
    if(($result['counts']['holdReasons']['invalid_or_inactive_plan_or_evidence']??0)!==1) throw new RuntimeException('SELF_INACTIVE');
    if(($result['counts']['holdReasons']['invalid_native_key']??0)!==1) throw new RuntimeException('SELF_INVALID_NATIVE');
    echo "SEARCH3_MEAL_PROVIDER_MAPPING_PLAN_SELF_TEST_OK candidates=2 holds=4 writes=0\n";
}
function smpp_main(array $argv): int
{
    if(PHP_SAPI!=='cli') return 1;
    if($argv===['--self-test']){smpp_self_test();return 0;}
    if($argv!==['--execute']) throw new RuntimeException('EXPLICIT_EXECUTION_REQUIRED');
    $dir=realpath((string)getenv('SEARCH3_OPERATION_DIR'));$root=realpath((string)getenv('ANYTOUR_ROOT'));$source=(string)getenv('SEARCH3_SOURCE_SHA');
    if(!$dir||!$root||basename($dir)!==SMPP_OPERATION||str_starts_with($dir,$root.'/')||preg_match('/^[a-f0-9]{40}$/D',$source)!==1) throw new RuntimeException('OPERATION_SCOPE');
    $reservation=json_decode((string)file_get_contents($dir.'/payload/reservation.json'),true,16,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==SMPP_OPERATION||($reservation['source_sha']??null)!==$source||($reservation['script_sha256']??null)!==hash_file('sha256',__FILE__)) throw new RuntimeException('RESERVATION_MISMATCH');
    $base=['operation'=>SMPP_OPERATION,'source_sha'=>$source,'at_utc'=>gmdate('c'),'provider_calls'=>0,'database_writes'=>0,'schema_writes'=>0,'mapping_writes'=>0,'site_file_writes'=>0,'no_replay'=>true];
    smpp_save($dir.'/started.json',$base+['state'=>'started_before_db']);
    try{
        ob_start();try{require_once $root.'/data/db-v1.php';$db=v2_data_db();}finally{ob_end_clean();}
        $snapshot=smpp_snapshot($db);$result=$base+$snapshot+['state'=>'completed_read_only'];
        smpp_save($dir.'/result.json',$result);
        smpp_save($dir.'/receipt.json',$base+['state'=>'completed_read_only','result_sha256'=>hash_file('sha256',$dir.'/result.json')]);
        echo smpp_json(['operation'=>SMPP_OPERATION,'state'=>'completed_read_only','candidates'=>$result['counts']['candidateNativeKeys'],'holds'=>$result['counts']['heldNativeKeys']]);return 0;
    }catch(Throwable $e){$code=preg_match('/^[A-Z_]{3,100}$/D',$e->getMessage())?$e->getMessage():'READ_ONLY_PLAN_FAILED';smpp_save($dir.'/receipt.json',$base+['state'=>'failed_read_only','error_code'=>$code,'error_class'=>get_class($e)]);fwrite(STDERR,$code."\n");return 1;}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) exit(smpp_main(array_slice($argv,1)));
