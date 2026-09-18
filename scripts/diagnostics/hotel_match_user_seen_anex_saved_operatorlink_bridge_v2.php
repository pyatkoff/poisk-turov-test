<?php
declare(strict_types=1);

const HMSA_OPERATION='hotel-match-user-seen-anex-saved-operatorlink-bridge-1971-20260918-v2';
const HMSA_OPERATOR_ID=13;
const HMSA_ROW_LIMIT=200000;
const HMSA_EXCLUDED_COUNTRIES=[46=>true,47=>true];

function hmsa_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
    if(count($r)>HMSA_ROW_LIMIT)throw new RuntimeException('row_budget');
    return $r;
}
function hmsa_table(PDO $pdo,string $t): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$t]);return $s->fetchColumn()!==false;
}
function hmsa_scalar(mixed $v,int $max=255): string {
    return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';
}
function hmsa_norm(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');$v=strtr($v,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function hmsa_product_hold(string $name): ?string {
    $raw=mb_strtolower(trim($name),'UTF-8');$n=hmsa_norm($name);
    if(preg_match('/^(roulette|рулетка)(\s|$)/u',$n))return 'roulette_product_marker';
    if(!preg_match('/^(fortuna|фортуна)(\s|$)/u',$n))return null;
    if(preg_match('/^(fortuna|фортуна)\s+[1-5]\s*[*★]/u',$raw))return 'fortuna_star_product_marker';
    $tokens=preg_split('/\s+/u',$n,-1,PREG_SPLIT_NO_EMPTY)?:[];
    $physical=(bool)preg_match('/\b(hotel|hotels|otel|отель|resort)\b/u',$n);
    return $physical&&count($tokens)>=3?null:'fortuna_product_marker';
}
function hmsa_anex_host(string $host): bool {
    $h=strtolower(trim($host,'. '));
    return $h==='anextour.ru'||str_ends_with($h,'.anextour.ru');
}
function hmsa_link_parts(array $row): array {
    $url=hmsa_scalar($row['operator_link']??'',2048);
    $host=strtolower(hmsa_scalar($row['operator_link_host']??'',255));
    $path=hmsa_scalar($row['operator_link_path']??'',1200);
    $query=hmsa_scalar($row['operator_link_query']??'',1800);
    if($url!==''){
        $p=parse_url($url);
        if(is_array($p)){
            if($host==='')$host=strtolower(hmsa_scalar($p['host']??'',255));
            if($path==='')$path=hmsa_scalar($p['path']??'',1200);
            if($query==='')$query=hmsa_scalar($p['query']??'',1800);
        }
    }
    return ['url'=>$url,'host'=>$host,'path'=>$path,'query'=>$query];
}
function hmsa_native_evidence(array $row): array {
    $p=hmsa_link_parts($row);
    if(!hmsa_anex_host($p['host']))return ['status'=>'unsafe_or_non_anex_host','host'=>$p['host'],'path'=>$p['path']];
    if($p['query']==='')return ['status'=>'no_query','host'=>$p['host'],'path'=>$p['path']];
    parse_str($p['query'],$q);
    $ids=['anex_online'=>[],'samo_hotelcode'=>[],'other_native'=>[]];
    foreach($q as $key=>$value){
        $k=strtolower((string)$key);$vals=is_array($value)?$value:[$value];
        foreach($vals as $v){
            $s=trim((string)$v);
            if(!preg_match('/^[1-9][0-9]{0,31}$/D',$s))continue;
            if($k==='hotellist')$ids['anex_online'][$s]=true;
            elseif($k==='hotelcode')$ids['samo_hotelcode'][$s]=true;
            elseif($k==='hotels'||$k==='f4')$ids['other_native'][$s]=true;
        }
    }
    $out=['status'=>'parsed','host'=>$p['host'],'path'=>$p['path'],'anex_online_ids'=>array_map('strval',array_keys($ids['anex_online'])),'samo_hotelcode_ids'=>array_map('strval',array_keys($ids['samo_hotelcode'])),'other_native_ids'=>array_map('strval',array_keys($ids['other_native']))];
    if(count($out['anex_online_ids'])>1)$out['status']='anex_online_query_conflict';
    if(count($out['samo_hotelcode_ids'])>1)$out['status']='samo_hotelcode_query_conflict';
    return $out;
}
function hmsa_num(mixed $v): ?float {
    if($v===null||$v===''||!is_numeric($v))return null;$n=(float)$v;return is_finite($n)?$n:null;
}
function hmsa_dist(mixed $lat1,mixed $lon1,mixed $lat2,mixed $lon2): ?float {
    $a=hmsa_num($lat1);$b=hmsa_num($lon1);$c=hmsa_num($lat2);$d=hmsa_num($lon2);
    if($a===null||$b===null||$c===null||$d===null)return null;
    $r=6371000.0;$p1=deg2rad($a);$p2=deg2rad($c);$dp=deg2rad($c-$a);$dl=deg2rad($d-$b);
    $x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;
    return 2*$r*atan2(sqrt($x),sqrt(max(0.0,1-$x)));
}
function hmsa_durable(string $path,array $v): string {
    $raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_exists');
    try{
        if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');
        rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');
    }finally{fclose($f);}
    return hash('sha256',$raw);
}
function hmsa_safe_external(array $rows,int $local,array $manual,array $mapped,array $decisions,array $excluded): array {
    $ids=[];foreach($rows as $r)foreach(($r['native']['anex_online_ids']??[]) as $x)$ids[(string)$x]=true;
    if(count($ids)!==1)return ['safe'=>false,'reason'=>count($ids)>1?'local_multiple_hotellist_ids':'no_hotellist_id','ids'=>array_keys($ids)];
    $ext=(int)array_key_first($ids);
    if(isset($manual[$ext]))return ['safe'=>false,'reason'=>'manual_decision_exists','external_id'=>$ext];
    if(isset($mapped[$ext])&&(int)$mapped[$ext]!==$local)return ['safe'=>false,'reason'=>'external_mapped_elsewhere','external_id'=>$ext,'other_local'=>(int)$mapped[$ext]];
    foreach($decisions[$ext]??[] as $d){
        $st=(string)($d['decision_status']??'');$dl=$d['catalog_hotel_id']===null?null:(int)$d['catalog_hotel_id'];
        if($st==='accepted'&&$dl===$local)continue;
        return ['safe'=>false,'reason'=>'external_decision_hold','external_id'=>$ext,'decision_status'=>$st,'decision_local'=>$dl];
    }
    if(isset($excluded[$ext][$local]))return ['safe'=>false,'reason'=>'pair_excluded','external_id'=>$ext];
    return ['safe'=>true,'external_id'=>$ext];
}

if(in_array('--self-test',$argv??[],true)){
    $a=hmsa_native_evidence(['operator_link'=>'https://agent.anextour.ru/search/tour?HOTELLIST=8121']);
    if(($a['status']??'')!=='parsed'||($a['anex_online_ids']??[])!==['8121']||($a['samo_hotelcode_ids']??[])!==[])throw new RuntimeException('hotellist_parse');
    $b=hmsa_native_evidence(['operator_link'=>'https://online.anextour.ru/hotel/view?hotelCode=5844']);
    if(($b['samo_hotelcode_ids']??[])!==['5844']||($b['anex_online_ids']??[])!==[])throw new RuntimeException('namespace_split');
    $c=hmsa_native_evidence(['operator_link'=>'https://evil.example/search?HOTELLIST=1']);
    if(($c['status']??'')!=='unsafe_or_non_anex_host')throw new RuntimeException('host_guard');
    if(hmsa_product_hold('FORTUNA MARMARIS')===null||hmsa_product_hold('FORTUNA HOTEL PHU QUOC')!==null)throw new RuntimeException('product_guard');
    $d=hmsa_dist(36.713018,31.563078,36.713018,31.563078);if($d===null||$d>1.0)throw new RuntimeException('distance');
    echo "MATCH_SAVED_OPERATORLINK_BRIDGE_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$opDir=(string)getenv('MATCH_OPERATION_DIR');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');$root=realpath((string)getenv('ANYTOUR_ROOT'));
if(!$root||$opDir===''||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha)||!is_dir($opDir)||!is_file($opDir.'/reservation.json'))throw new RuntimeException('runtime_guard');
$res=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
if(($res['operation']??'')!==HMSA_OPERATION||($res['state']??'')!=='reserved_before_current_db_access')throw new RuntimeException('reservation_guard');
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbf;
$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$result=[];
try{
    foreach(['tour_price_observations','catalog_hotels','tour_operator_identity_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities'] as $t)if(!hmsa_table($pdo,$t))throw new RuntimeException('missing_'.$t);
    $hasAnexHotels=hmsa_table($pdo,'anex_hotels');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');

    $anexLocal=[];$mapped=[];$externalToLocals=[];
    foreach(hmsa_rows($pdo,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1") as $r){
        $aid=(int)$r['anex_hotel_id'];$lid=(int)$r['catalog_hotel_id'];if($aid>0&&$lid>0){$anexLocal[$lid]=true;$mapped[$aid]=$lid;$externalToLocals[$aid][$lid]=true;}
    }
    $manual=[];$decisions=[];
    foreach(hmsa_rows($pdo,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions") as $r){
        $aid=(int)$r['anex_hotel_id'];$manual[$aid]=true;$decisions[$aid][]=$r;
        if(($r['decision_status']??'')==='accepted'&&$r['catalog_hotel_id']!==null){$lid=(int)$r['catalog_hotel_id'];if($lid>0){$anexLocal[$lid]=true;$externalToLocals[$aid][$lid]=true;}}
    }
    $excluded=[];foreach(hmsa_rows($pdo,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions") as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
    $andLocal=[];foreach(hmsa_rows($pdo,"SELECT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r)$andLocal[(int)$r['local_hotel_id']]=true;

    $seen=[];foreach(hmsa_rows($pdo,"SELECT hotel_id,MAX(observed_at) last_seen,COUNT(*) observation_rows FROM tour_price_observations WHERE source='user_search' AND operator_id=? AND departure_date>=CURRENT_DATE GROUP BY hotel_id",[HMSA_OPERATOR_ID]) as $r){
        $id=(int)$r['hotel_id'];if($id>0)$seen[$id]=['last_seen'=>(string)$r['last_seen'],'rows'=>(int)$r['observation_rows']];
    }
    $frontier=[];
    if($seen){
        $ids=array_keys($seen);$ph=implode(',',array_fill(0,count($ids),'?'));
        foreach(hmsa_rows($pdo,"SELECT h.id,h.country_id,h.country_name,h.region_name,h.subregion_name,h.name,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.id IN ($ph)",$ids) as $r){
            $id=(int)$r['id'];$cid=(int)$r['country_id'];
            if((int)$r['is_active']!==1||isset(HMSA_EXCLUDED_COUNTRIES[$cid])||isset($anexLocal[$id])||isset($andLocal[$id])||hmsa_product_hold((string)$r['name'])!==null)continue;
            $frontier[$id]=['hotel_id'=>$id,'hotel_name'=>(string)$r['name'],'country_id'=>$cid,'country_name'=>(string)$r['country_name'],'region_name'=>(string)($r['region_name']??''),'subregion_name'=>(string)($r['subregion_name']??''),'latitude'=>$r['latitude']===null?null:(float)$r['latitude'],'longitude'=>$r['longitude']===null?null:(float)$r['longitude'],'user_observation_rows'=>$seen[$id]['rows'],'last_user_seen_at'=>$seen[$id]['last_seen']];
        }
    }

    $obsByLocal=[];$parsedRows=0;$hotellistRows=0;$hotelcodeRows=0;$unsafeRows=0;$queryConflictRows=0;
    if($frontier){
        $ids=array_keys($frontier);$ph=implode(',',array_fill(0,count($ids),'?'));$params=array_merge([HMSA_OPERATOR_ID],$ids);
        foreach(hmsa_rows($pdo,"SELECT * FROM tour_operator_identity_observations WHERE operator_id=? AND hotel_id IN ($ph) ORDER BY last_seen_at DESC,id DESC",$params) as $r){
            $lid=(int)$r['hotel_id'];if(!isset($frontier[$lid]))continue;$native=hmsa_native_evidence($r);$parsedRows++;
            if(($native['status']??'')==='unsafe_or_non_anex_host')$unsafeRows++;
            if(str_contains((string)($native['status']??''),'conflict'))$queryConflictRows++;
            if($native['anex_online_ids']??[])$hotellistRows++;
            if($native['samo_hotelcode_ids']??[])$hotelcodeRows++;
            $obsByLocal[$lid][]=['observation_id'=>(int)($r['id']??0),'last_seen_at'=>(string)($r['last_seen_at']??''),'observation_count'=>(int)($r['observation_count']??0),'operator_name'=>(string)($r['operator_name']??''),'native'=>$native];
        }
    }

    $staging=[];
    if($hasAnexHotels)foreach(hmsa_rows($pdo,"SELECT anex_hotel_id,api_name,api_region,api_town,latitude,longitude FROM anex_hotels") as $r)$staging[(int)$r['anex_hotel_id']]=$r;

    $safe=[];$holds=[];$nativeToLocals=[];$diagnosticSamo=[];
    foreach($frontier as $lid=>$local){
        $rows=$obsByLocal[$lid]??[];
        if(!$rows){$holds[]=$local+['reason'=>'no_saved_anex_operator_link'];continue;}
        $decision=hmsa_safe_external($rows,$lid,$manual,$mapped,$decisions,$excluded);
        foreach($rows as $r)foreach(($r['native']['samo_hotelcode_ids']??[]) as $code)$diagnosticSamo[$lid][$code]=true;
        if(!($decision['safe']??false)){$holds[]=$local+$decision;continue;}
        $aid=(int)$decision['external_id'];$nativeToLocals[$aid][$lid]=true;
        $stage=$staging[$aid]??null;$distance=null;
        if(is_array($stage))$distance=hmsa_dist($stage['latitude']??null,$stage['longitude']??null,$local['latitude'],$local['longitude']);
        if($distance!==null&&$distance>5000.0){$holds[]=$local+['reason'=>'staging_coordinate_conflict_gt_5km','external_id'=>$aid,'distance_m'=>round($distance,2),'staging_name'=>(string)($stage['api_name']??'')];continue;}
        $directRows=[];foreach($rows as $r)if(in_array((string)$aid,$r['native']['anex_online_ids']??[],true))$directRows[]=$r;
        $safe[]=$local+['external_hotel_id'=>$aid,'evidence_route'=>'tourvisor_saved_operator_hotellist','direct_observation_rows'=>count($directRows),'saved_operator_observation_count'=>array_sum(array_map(fn($x)=>(int)$x['observation_count'],$directRows)),'latest_operator_seen_at'=>(string)($directRows[0]['last_seen_at']??''),'operator_hosts'=>array_values(array_unique(array_map(fn($x)=>(string)$x['native']['host'],$directRows))),'operator_paths'=>array_values(array_unique(array_map(fn($x)=>(string)$x['native']['path'],$directRows))),'staging_name'=>is_array($stage)?(string)($stage['api_name']??''):null,'staging_region'=>is_array($stage)?(string)($stage['api_region']??''):null,'staging_town'=>is_array($stage)?(string)($stage['api_town']??''):null,'distance_m'=>$distance===null?null:round($distance,2),'samo_hotelcode_evidence'=>array_values(array_keys($diagnosticSamo[$lid]??[]))];
    }
    $final=[];foreach($safe as $r){$aid=(int)$r['external_hotel_id'];$lid=(int)$r['hotel_id'];if(count($nativeToLocals[$aid]??[])!==1){$holds[]=$r+['reason'=>'same_hotellist_multiple_local_targets'];continue;}$final[]=$r;}
    usort($final,fn($a,$b)=>$b['user_observation_rows']<=>$a['user_observation_rows'] ?: $b['saved_operator_observation_count']<=>$a['saved_operator_observation_count'] ?: $a['hotel_id']<=>$b['hotel_id']);
    usort($holds,fn($a,$b)=>($b['user_observation_rows']??0)<=>($a['user_observation_rows']??0) ?: ($a['hotel_id']??0)<=>($b['hotel_id']??0));
    $samoRows=[];foreach($diagnosticSamo as $lid=>$codes)foreach(array_keys($codes) as $code)$samoRows[]=['hotel_id'=>(int)$lid,'hotel_name'=>$frontier[$lid]['hotel_name']??null,'samo_hotelcode'=>(string)$code,'not_anex_namespace'=>true];

    $pdo->rollBack();
    $reasonCounts=[];foreach($holds as $h)$reasonCounts[(string)($h['reason']??'unknown')]=($reasonCounts[(string)($h['reason']??'unknown')]??0)+1;ksort($reasonCounts);
    $result=['operation'=>HMSA_OPERATION,'state'=>'completed_read_only','source_sha'=>$sourceSha,'database_writes'=>0,'mapping_writes'=>0,'provider_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true,'operator_id'=>HMSA_OPERATOR_ID,'future_user_seen_anex_frontier'=>count($frontier),'frontier_with_saved_operator_rows'=>count($obsByLocal),'saved_operator_rows_parsed'=>$parsedRows,'saved_hotellist_rows'=>$hotellistRows,'saved_samo_hotelcode_rows'=>$hotelcodeRows,'unsafe_host_rows'=>$unsafeRows,'query_conflict_rows'=>$queryConflictRows,'safe_direct_anex_candidate_count'=>count($final),'safe_direct_anex_candidates'=>$final,'diagnostic_samo_hotelcode_count'=>count($samoRows),'diagnostic_samo_hotelcodes'=>$samoRows,'hold_count'=>count($holds),'hold_reason_counts'=>$reasonCounts,'holds'=>array_slice($holds,0,500),'guards'=>['user_search_only'=>true,'future_anex_operator_only'=>true,'russia_abkhazia_excluded'=>true,'local_requires_no_current_anex_or_andromeda_acceptance'=>true,'hotellist_namespace'=>'anex_online','hotelCode_namespace'=>'samo_hotelcode_diagnostic_only','anex_domain_required'=>true,'one_local_one_hotellist'=>true,'one_hotellist_one_local'=>true,'manual_decision_blocks'=>true,'pair_exclusion_blocks'=>true,'existing_external_other_local_blocks'=>true,'staging_coordinate_conflict_gt_m'=>5000,'no_fuzzy_acceptance'=>true]];
}catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    $result=['operation'=>HMSA_OPERATION,'state'=>'failed_before_external_access','source_sha'=>$sourceSha,'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',mb_substr($e->getMessage(),0,120)),'database_writes'=>0,'mapping_writes'=>0,'provider_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
}
$sha=hmsa_durable($opDir.'/result.json',$result);
hmsa_durable($opDir.'/receipt.json',['operation'=>HMSA_OPERATION,'state'=>$result['state'],'result_sha256'=>$sha,'database_writes'=>0,'mapping_writes'=>0,'provider_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit(($result['state']??'')==='completed_read_only'?0:2);
