<?php
declare(strict_types=1);

const HMTN_OPERATION = 'hotel-match-tv-native-current-reconcile-1971-20260918-v1';
const HMTN_SOURCE_OPERATION = 'hotel-match-user-seen-tv-detail-saturation-1971-20260918-v3';
const HMTN_SOURCE_SHA256 = '62399dcae24ebd48d795818e318c4ca97231b4147879d261d67bc5961e516869';
const HMTN_SOURCE_BYTES = 57477;
const HMTN_ROW_LIMIT = 100000;
const HMTN_EXCLUDED_COUNTRIES = [46=>true,47=>true];

function hmtn_rows(PDO $db,string $sql,array $params=[]): array {
    $s=$db->prepare($sql); $s->execute(array_values($params));
    $r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
    if(count($r)>HMTN_ROW_LIMIT) throw new RuntimeException('row_budget');
    return $r;
}
function hmtn_table(PDO $db,string $name): bool {
    $s=$db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$name]); return $s->fetchColumn()!==false;
}
function hmtn_norm(string $v): string {
    $v=trim($v);
    $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);
    $v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a','ö'=>'o','ô'=>'o','ó'=>'o','ò'=>'o','õ'=>'o','ü'=>'u','ú'=>'u','ù'=>'u','û'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','İ'=>'i','ñ'=>'n','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function hmtn_name_key(string $v): string {
    $generic=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'spa'=>1];
    $t=preg_split('/\s+/u',hmtn_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[];
    $t=array_values(array_filter($t,static fn($x)=>!isset($generic[$x])));
    return implode(' ',$t);
}
function hmtn_country_key(string $v): string {
    $n=hmtn_norm($v);
    $groups=[
      ['турция','turkey','turkiye','türkiye'],['египет','egypt'],['оаэ','uae','united arab emirates','объединенные арабские эмираты'],
      ['мальдивы','maldives'],['вьетнам','vietnam'],['таиланд','тайланд','thailand'],['куба','cuba'],['шри ланка','sri lanka'],
      ['катар','qatar'],['китай','china'],['маврикий','mauritius'],['индонезия','indonesia'],['тунис','tunisia'],['индия','india'],
      ['танзания','tanzania'],['узбекистан','uzbekistan'],['марокко','morocco'],['сейшелы','seychelles']
    ];
    foreach($groups as $i=>$g) foreach($g as $x) if($n===hmtn_norm($x)) return 'g'.($i+1);
    return $n;
}
function hmtn_dist($a,$b,$c,$d): ?float {
    foreach([$a,$b,$c,$d] as $x) if($x===null||$x==='') return null;
    $a=(float)$a;$b=(float)$b;$c=(float)$c;$d=(float)$d;
    if(!is_finite($a)||!is_finite($b)||!is_finite($c)||!is_finite($d)||abs($a)>90||abs($c)>90||abs($b)>180||abs($d)>180) return null;
    $p=array_map('deg2rad',[$a,$b,$c,$d]);[$a,$b,$c,$d]=$p;
    $x=sin(($c-$a)/2)**2+cos($a)*cos($c)*sin(($d-$b)/2)**2;
    return 6371000*2*asin(min(1,sqrt($x)));
}
function hmtn_place_match(array $src,array $dst): bool {
    $a=[];$b=[];foreach($src as $v){$n=hmtn_norm((string)$v);if($n!=='')$a[$n]=true;}foreach($dst as $v){$n=hmtn_norm((string)$v);if($n!=='')$b[$n]=true;}
    return (bool)array_intersect_key($a,$b);
}
function hmtn_save(string $path,array $v): string {
    $raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $f=@fopen($path,'x+b'); if(!$f) throw new RuntimeException('durable_exists');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');}finally{fclose($f);} return hash('sha256',$raw);
}
function hmtn_pair_key(int $local,string $native): string {return $local.'|'.$native;}

if(in_array('--self-test',$argv??[],true)){
    if(hmtn_name_key('DELUXE SEA VIEW HOTEL')!=='deluxe sea view') throw new RuntimeException('name_generic');
    if(hmtn_name_key('FAMILY SUITE')!=='family suite') throw new RuntimeException('qualifier_preserved');
    if(hmtn_country_key('Turkey')!==hmtn_country_key('Turkiye')) throw new RuntimeException('country_alias');
    $d=hmtn_dist(36.713018,31.563078,36.713018,31.563078); if($d===null||$d>1) throw new RuntimeException('distance');
    echo "MATCH_TV_NATIVE_CURRENT_RECONCILE_SELFTEST_OK\n"; exit(0);
}
if(PHP_SAPI!=='cli') exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));
$opDir=realpath((string)getenv('MATCH_OPERATION_DIR'));
$src=realpath((string)getenv('MATCH_SOURCE_RESULT'));
$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if(!$root||!$opDir||!$src||!is_file($src)||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha)) throw new RuntimeException('runtime_guard');
if(filesize($src)!==HMTN_SOURCE_BYTES||hash_file('sha256',$src)!==HMTN_SOURCE_SHA256) throw new RuntimeException('source_hash_guard');
$res=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
if(($res['operation']??'')!==HMTN_OPERATION||($res['source_sha256']??'')!==HMTN_SOURCE_SHA256) throw new RuntimeException('reservation_guard');
$source=json_decode((string)file_get_contents($src),true,128,JSON_THROW_ON_ERROR);
if(($source['operation']??'')!==HMTN_SOURCE_OPERATION||($source['state']??'')!=='completed_read_only'||(int)($source['http_attempts']??-1)!==2705||($source['rate_limited']??null)!==false||count($source['unique_pairs']??[])!==45||count($source['direct_evidence']??[])!==74) throw new RuntimeException('source_contract');

$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php'; require_once $dbf;
$db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['catalog_hotels','catalog_hotel_details','tour_price_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities'] as $t) if(!hmtn_table($db,$t)) throw new RuntimeException('missing_'.$t);

$sourcePairs=[];$sourceExternal=[];$sourceLocal=[];$direct=[];
foreach($source['unique_pairs'] as $p){
    $local=(int)($p['local_hotel_id']??0);$native=trim((string)($p['native_hotel_id']??''));$family=(string)($p['operator_family']??'');
    if($local<1||!preg_match('/^[1-9][0-9]{0,31}$/D',$native)||$family!=='anex') throw new RuntimeException('source_pair_shape');
    $k=hmtn_pair_key($local,$native);$sourcePairs[$k]=['local_hotel_id'=>$local,'native_hotel_id'=>$native];$sourceExternal[$native][$local]=true;$sourceLocal[$local][$native]=true;
}
foreach($sourceExternal as $x) if(count($x)!==1) throw new RuntimeException('source_external_collision');
foreach($sourceLocal as $x) if(count($x)!==1) throw new RuntimeException('source_local_collision');
foreach($source['direct_evidence'] as $e){$local=(int)($e['local_hotel_id']??0);$native=trim((string)($e['native_hotel_id']??''));$k=hmtn_pair_key($local,$native);if(!isset($sourcePairs[$k]))continue;$direct[$k][]=['tour_id'=>(string)($e['tour_id']??''),'source_field'=>(string)($e['source_field']??''),'native_host'=>(string)($e['native_host']??''),'native_path'=>(string)($e['native_path']??''),'native_params'=>array_values(array_map('strval',$e['native_params']??[])),'user_observation_weight'=>(int)($e['user_observation_weight']??0),'local_hotel_name'=>(string)($e['local_hotel_name']??'')];}
foreach($sourcePairs as $k=>$_) if(!isset($direct[$k])) throw new RuntimeException('missing_direct_evidence');

$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
    $locals=array_values(array_unique(array_map(fn($x)=>(int)$x['local_hotel_id'],$sourcePairs)));$natives=array_values(array_unique(array_map(fn($x)=>(string)$x['native_hotel_id'],$sourcePairs)));
    $lph=implode(',',array_fill(0,count($locals),'?'));$nph=implode(',',array_fill(0,count($natives),'?'));
    $hotels=[];foreach(hmtn_rows($db,"SELECT h.id,h.country_id,h.country_name,h.name,h.region_id,h.region_name,h.subregion_id,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.id IN ($lph)",$locals) as $r)$hotels[(int)$r['id']]=$r;
    $aliases=[];if(hmtn_table($db,'hotel_aliases'))foreach(hmtn_rows($db,"SELECT hotel_id,alias FROM hotel_aliases WHERE hotel_id IN ($lph)",$locals) as $r){$v=trim((string)$r['alias']);if($v!=='')$aliases[(int)$r['hotel_id']][]=$v;}
    $seen=[];foreach(hmtn_rows($db,"SELECT hotel_id,COUNT(*) n,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($lph) GROUP BY hotel_id",$locals) as $r)$seen[(int)$r['hotel_id']]=['rows'=>(int)$r['n'],'last_seen'=>(string)$r['last_seen']];
    $maps=[];foreach(hmtn_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($nph) OR catalog_hotel_id IN ($lph)",array_merge($natives,$locals)) as $r)$maps[]=$r;
    $dec=[];foreach(hmtn_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($nph) OR catalog_hotel_id IN ($lph)",array_merge($natives,$locals)) as $r)$dec[]=$r;
    $ex=[];foreach(hmtn_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($nph) OR catalog_hotel_id IN ($lph)",array_merge($natives,$locals)) as $r)$ex[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
    $and=[];foreach(hmtn_rows($db,"SELECT local_hotel_id,external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IN ($lph)",$locals) as $r)$and[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
    $stage=[];if(hmtn_table($db,'anex_hotels'))foreach(hmtn_rows($db,"SELECT * FROM anex_hotels WHERE anex_hotel_id IN ($nph)",$natives) as $r)$stage[(string)$r['anex_hotel_id']]=$r;

    $out=[];$counts=[];
    foreach($sourcePairs as $k=>$pair){
      $local=$pair['local_hotel_id'];$native=$pair['native_hotel_id'];$h=$hotels[$local]??null;$reasons=[];$bucket='current_safe_native';
      if(!$h||((int)($h['is_active']??0))!==1){$bucket='protected_hold';$reasons[]='local_inactive_or_missing';}
      if($h&&isset(HMTN_EXCLUDED_COUNTRIES[(int)$h['country_id']])){$bucket='protected_hold';$reasons[]='excluded_country';}
      if(!isset($seen[$local])){$bucket='protected_hold';$reasons[]='no_current_user_search';}
      $same=false;$extOther=[];$localOther=[];$manualHold=[];
      foreach($maps as $m){if((int)($m['enabled']??0)!==1)continue;$e=(string)$m['anex_hotel_id'];$l=(int)$m['catalog_hotel_id'];if($e===$native&&$l===$local)$same=true;elseif($e===$native&&$l!==$local)$extOther[]=$l;elseif($e!==$native&&$l===$local)$localOther[]=$e;}
      foreach($dec as $d){$e=(string)$d['anex_hotel_id'];$l=$d['catalog_hotel_id']===null?0:(int)$d['catalog_hotel_id'];$st=(string)($d['decision_status']??'');if($st==='accepted'&&$e===$native&&$l===$local)$same=true;elseif($st==='accepted'&&$e===$native&&$l!==$local)$extOther[]=$l;elseif($st==='accepted'&&$e!==$native&&$l===$local)$localOther[]=$e;if($e===$native&&in_array($st,['conflict','rejected','excluded'],true))$manualHold[]=$st;}
      if(isset($ex[$native][$local])){$bucket='protected_hold';$reasons[]='pair_exclusion';}
      if($extOther){$bucket='protected_hold';$reasons[]='external_occupied_other_local';}
      if($localOther){$bucket='protected_hold';$reasons[]='local_occupied_other_external';}
      if($manualHold){$bucket='protected_hold';$reasons[]='manual_or_conflict_state';}
      if($same&&$bucket!=='protected_hold'){$bucket='already_same';$reasons[]='accepted_pair_exists';}
      elseif(isset($and[$local])&&$bucket==='current_safe_native'){$bucket='covered_by_andromeda';$reasons[]='accepted_andromeda_exists';}

      $s=$stage[$native]??null;$nameOk=false;$countryOk=null;$placeOk=false;$distance=null;$stageNames=[];$stagePlaces=[];
      if($s&&$h){
        foreach(['api_name','xml_name','xml_alternate_name'] as $f){$v=trim((string)($s[$f]??''));if($v!=='')$stageNames[]=$v;}
        foreach(['api_region','api_town'] as $f){$v=trim((string)($s[$f]??''));if($v!=='')$stagePlaces[]=$v;}
        $localNames=array_merge([(string)$h['name']],$aliases[$local]??[]);$lk=[];foreach($localNames as $v){$q=hmtn_name_key($v);if($q!=='')$lk[$q]=true;}foreach($stageNames as $v){$q=hmtn_name_key($v);if($q!==''&&isset($lk[$q])){$nameOk=true;break;}}
        $sc=trim((string)($s['api_country']??''));if($sc!=='')$countryOk=hmtn_country_key($sc)===hmtn_country_key((string)$h['country_name']);
        $placeOk=hmtn_place_match($stagePlaces,[(string)$h['region_name'],(string)$h['subregion_name']]);
        $distance=hmtn_dist($s['latitude']??null,$s['longitude']??null,$h['latitude']??null,$h['longitude']??null);
      }
      if($bucket==='current_safe_native'){
        if(!$s){$bucket='needs_saved_geo_or_name';$reasons[]='anex_staging_missing';}
        elseif($countryOk===false){$bucket='protected_hold';$reasons[]='country_conflict';}
        elseif($distance!==null&&$distance>5000){$bucket='protected_hold';$reasons[]='coordinate_conflict_gt5km';}
        elseif(!$nameOk){$bucket='needs_saved_geo_or_name';$reasons[]='name_unproven';}
        elseif(!($placeOk||($distance!==null&&$distance<=5000))){$bucket='needs_saved_geo_or_name';$reasons[]='geography_unproven';}
        elseif($countryOk===null){$bucket='needs_saved_geo_or_name';$reasons[]='country_unproven';}
      }
      $ev=$direct[$k];$maxWeight=max(array_map(fn($e)=>(int)$e['user_observation_weight'],$ev));$tourIds=array_values(array_unique(array_filter(array_map(fn($e)=>(string)$e['tour_id'],$ev))));sort($tourIds,SORT_STRING);
      $row=['bucket'=>$bucket,'reasons'=>array_values(array_unique($reasons)),'local_hotel_id'=>$local,'local_hotel_name'=>$h['name']??null,'country_id'=>$h===null?null:(int)$h['country_id'],'country_name'=>$h['country_name']??null,'region_name'=>$h['region_name']??null,'subregion_name'=>$h['subregion_name']??null,'native_anex_hotel_id'=>$native,'direct_evidence_rows'=>count($ev),'tour_ids'=>$tourIds,'max_user_observation_weight'=>$maxWeight,'current_user_search_rows'=>$seen[$local]['rows']??0,'current_last_user_seen'=>$seen[$local]['last_seen']??null,'andromeda_accepted_ids'=>$and[$local]??[],'anex_stage_present'=>(bool)$s,'anex_stage_names'=>$stageNames,'anex_stage_places'=>$stagePlaces,'name_exact_or_alias'=>$nameOk,'country_match'=>$countryOk,'place_match'=>$placeOk,'distance_m'=>$distance===null?null:(int)round($distance),'source_result_sha256'=>HMTN_SOURCE_SHA256,'native_anchor'=>'tourvisor_detail_operatorLink_HOTELLIST'];
      $out[]=$row;$counts[$bucket]=($counts[$bucket]??0)+1;
    }
    usort($out,fn($a,$b)=>strcmp($a['bucket'],$b['bucket'])?:($b['current_user_search_rows']<=>$a['current_user_search_rows'])?:($a['local_hotel_id']<=>$b['local_hotel_id']));ksort($counts);$db->rollBack();
    $result=['operation'=>HMTN_OPERATION,'status'=>'completed_read_only','source_operation'=>HMTN_SOURCE_OPERATION,'source_result_sha256'=>HMTN_SOURCE_SHA256,'source_result_bytes'=>HMTN_SOURCE_BYTES,'source_unique_pairs'=>45,'source_direct_evidence_rows'=>74,'current_bucket_counts'=>$counts,'current_safe_native_count'=>(int)($counts['current_safe_native']??0),'already_same_count'=>(int)($counts['already_same']??0),'covered_by_andromeda_count'=>(int)($counts['covered_by_andromeda']??0),'needs_saved_geo_or_name_count'=>(int)($counts['needs_saved_geo_or_name']??0),'protected_hold_count'=>(int)($counts['protected_hold']??0),'rows'=>$out,'tourvisor_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'request_accounting_delta'=>0,'safe_to_write_now'=>false];
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();$result=['operation'=>HMTN_OPERATION,'status'=>'failed_read_only','reason'=>preg_replace('/[^a-z0-9_\-]/i','_',substr($e->getMessage(),0,120)),'tourvisor_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'request_accounting_delta'=>0];}
$sha=hmtn_save($opDir.'/result.json',$result);hmtn_save($opDir.'/receipt.json',['operation'=>HMTN_OPERATION,'status'=>$result['status'],'source_sha'=>$sourceSha,'source_result_sha256'=>HMTN_SOURCE_SHA256,'result_sha256'=>$sha,'tourvisor_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'request_accounting_delta'=>0,'readback_verified'=>true]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit(($result['status']??'')==='completed_read_only'?0:2);
