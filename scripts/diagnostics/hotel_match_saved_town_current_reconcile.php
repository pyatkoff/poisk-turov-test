<?php
declare(strict_types=1);

const MST_OP = 'hotel-match-saved-town-current-reconcile-1971-20260915-v1';
const MST_PARENT_OP = 'hotel-match-saved-andromeda-evidence-1971-20260915-v1';
const MST_PARENT_RESULT_SHA = 'e5cb4eeed062882779c37059e19f12b4e3e92badb45a6069b86d228d8d5534d7';

function mst_json(array $x): string { return json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function mst_write(string $path,array $x): string {
    $raw=mst_json($x);$f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('exclusive_output');
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('output_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('output_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('output_readback');}finally{fclose($f);}return hash('sha256',$raw);
}
function mst_query(PDO $db,string $sql,array $args=[]): array {$q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);}
function mst_ev(string $raw): array {try{$x=json_decode($raw,true,64,JSON_THROW_ON_ERROR);return is_array($x)?$x:[];}catch(Throwable){return[];}}
function mst_fold(string $s): string {
    $s=function_exists('mb_strtolower')?mb_strtolower(trim($s),'UTF-8'):strtolower(trim($s));
    return strtr($s,['Ё'=>'е','ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','ö'=>'o','ô'=>'o','ó'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','İ'=>'i','ñ'=>'n','ý'=>'y','í'=>'i','ï'=>'i','ž'=>'z','š'=>'s','č'=>'c']);
}
function mst_tokens(string $name): array {
    $parts=preg_split('/\b(?:ex|former|formerly)\b\.?/iu',$name)?:[];$out=[];
    foreach($parts as $part){$s=mst_fold($part);$s=str_replace(["'",'’'],'',$s);$s=preg_replace('/\baquapark\b/u','aqua park',$s)??$s;
        $t=preg_split('/[^\p{L}\p{N}]+/u',$s,-1,PREG_SPLIT_NO_EMPTY)?:[];$t=array_values(array_diff($t,['hotel','hotels','resort','resorts','spa','отель','отели','the','a','an','by','and']));
        if(!$t)continue;$compact=implode('',$t);if(count($t)<2||mb_strlen($compact,'UTF-8')<8)continue;
        $q=array_values(array_intersect($t,['annex','annexe','beach','garden','gardens','north','south','east','west','mountain','posh','family','junior','deluxe','aqua','park','palace','royal','grand','premium','select','bay','island','village']));sort($q);
        preg_match_all('/\d+/u',implode(' ',$t),$n);$out[$compact]=['compact'=>$compact,'qualifiers'=>$q,'numbers'=>$n[0],'raw'=>trim($part)];
    }return$out;
}
function mst_geo(string $s): string {
    $s=mst_fold($s);$s=strtr($s,['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ь'=>'','ъ'=>'']);
    $s=preg_replace('/[^a-z0-9]+/u',' ',strtolower($s))??strtolower($s);return trim(preg_replace('/\s+/',' ',$s)??$s);
}
function mst_protected($v,string $key='',int $depth=0): bool {
    if($depth>20)return true;if(is_array($v)){foreach($v as $k=>$x)if(mst_protected($x,(string)$k,$depth+1))return true;return false;}
    if(!preg_match('/manual|exclude|exclusion|conflict|reject|review/i',$key))return false;if(is_bool($v))return$v;if(is_numeric($v))return(float)$v!=0;return is_string($v)&&trim($v)!==''&&!in_array(strtolower(trim($v)),['false','none','no','null'],true);
}
function mst_points($v,array &$out,int $depth=0): void {
    if(!is_array($v)||$depth>20)return;$a=$v['latitude']??$v['lat']??null;$b=$v['longitude']??$v['lng']??$v['lon']??null;
    if(is_numeric($a)&&is_numeric($b)&&abs((float)$a)<=90&&abs((float)$b)<=180&&!((float)$a==0.0&&(float)$b==0.0))$out[]=[(float)$a,(float)$b];foreach($v as $x)if(is_array($x))mst_points($x,$out,$depth+1);
}
function mst_distance(array $p,array $h): ?float {$c=$h['latitude']??null;$d=$h['longitude']??null;if(!is_numeric($c)||!is_numeric($d))return null;[$a,$b,$c,$d]=array_map('deg2rad',[$p[0],$p[1],(float)$c,(float)$d]);return 6371.0*2.0*asin(min(1.0,sqrt(sin(($c-$a)/2)**2+cos($a)*cos($c)*sin(($d-$b)/2)**2)));}
function mst_parent_rows(array $parent): array {
    $pending=[];foreach($parent['current_pending']??[] as $r)if(is_array($r))$pending[(string)$r['external_hotel_id']]=$r;
    $out=[];foreach($parent['evidence_rows']??[] as $r){if(!is_array($r)||($r['source_kind']??'')!=='saved_HOTELS_TOWNTO')continue;$id=(string)($r['external_hotel_id']??'');if(!isset($pending[$id]))continue;
        $town=[];foreach($r['typed_town_links']??[] as $link)if(is_array($link)&&($link['conflict']??true)===false){$df=$link['dictionary_fields']??[];foreach(['name','lName'] as $k)if(is_string($df[$k]??null)&&trim($df[$k])!=='')$town[trim($df[$k])]=true;}
        $names=[];foreach([$r['hotel_fields']??[],$pending[$id]['current_source']??[]] as $src)if(is_array($src))foreach(['name','lName','hotel','hotel_name','hotelName','original_name'] as $k)if(is_string($src[$k]??null)&&trim($src[$k])!=='')$names[trim($src[$k])]=true;
        $out[$id]=['external_hotel_id'=>$id,'country_id'=>(int)$r['country_id'],'names'=>array_keys($names),'town_labels'=>array_keys($town),'parent_retained_file_sha256'=>$r['retained_file_sha256']??null,'parent_evidence_sha256'=>$pending[$id]['evidence_sha256']??null];
    }ksort($out,SORT_STRING);return$out;
}
function mst_classify(array $src,array $row,array $ev,array $hotels,array $forms,array $index,array $occupancy): array {
    $id=$src['external_hotel_id'];$holds=[];$support=[];$candidate=[];
    if(($row['decision_status']??'')!=='pending'||$row['local_hotel_id']!==null)return['route'=>'hold','reason'=>'not_current_pending'];
    if(!hash_equals((string)$src['parent_evidence_sha256'],(string)$row['evidence_sha256']))$holds[]='source_evidence_changed_since_parent';
    if(mst_protected($ev))$holds[]='manual_review_conflict_marker';
    foreach($src['names'] as $name)foreach(mst_tokens((string)$name) as $key=>$sf)foreach($index[(int)$src['country_id']][$key]??[] as $target=>$locals){foreach($locals as $lf)if($sf['qualifiers']===$lf['qualifiers']&&$sf['numbers']===$lf['numbers']){$candidate[(int)$target]=true;break;}}
    if(count($candidate)!==1){$holds[]=count($candidate)===0?'countrywide_exact_alias_missing':'countrywide_exact_alias_ambiguous';return['route'=>'hold','reason'=>'current_guard_hold','holds'=>array_values(array_unique($holds)),'candidate_ids'=>array_slice(array_map('intval',array_keys($candidate)),0,20)];}
    $target=(int)array_key_first($candidate);$h=$hotels[$target]??null;if(!$h)return['route'=>'hold','reason'=>'current_local_missing','target'=>$target,'holds'=>['current_local_missing']];
    if((int)$h['country_id']!==(int)$src['country_id'])$holds[]='country_conflict';
    foreach($occupancy[$target]??[] as $other)if($other!==$id)$holds[]='same_provider_target_occupied';
    $geoTarget=[];foreach(['region_name','subregion_name'] as $k)if(is_string($h[$k]??null)&&trim($h[$k])!=='')$geoTarget[mst_geo($h[$k])]=true;
    $geoSource=[];foreach($src['town_labels'] as $x)if(($g=mst_geo((string)$x))!=='')$geoSource[$g]=true;
    $geoMatch=array_values(array_intersect(array_keys($geoSource),array_keys($geoTarget)));if(!$geoMatch)$holds[]='typed_town_not_direct_target_region';else$support[]='saved_HOTELS_TOWNTO_direct_geography';
    $points=[];mst_points($ev,$points);$dist=[];foreach($points as $p){$d=mst_distance($p,$h);if($d!==null){$dist[]=$d;if($d>5.0)$holds[]='coordinate_conflict_gt5km';}}
    $holds=array_values(array_unique($holds));if(!$holds)$support[]='countrywide_unique_exact_compact_alias';
    return['route'=>$holds?'hold':'guard_passed_prepared','reason'=>$holds?'current_guard_hold':'unique_exact_alias_plus_direct_typed_town','target'=>$target,'target_name'=>$h['name'],'source_names'=>$src['names'],'source_towns'=>$src['town_labels'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],'holds'=>$holds,'support'=>$support,'distance_km'=>$dist?min($dist):null,'safe_to_write_now'=>false];
}

if(getenv('MATCH_TEST_LIBRARY')==='1')return;
if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
$op=(string)getenv('MATCH_OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');if($op!==MST_OP||!preg_match('/^[a-f0-9]{40}$/D',$sha))throw new RuntimeException('operation_guard');
$home=(string)getenv('HOME');$dir=$home.'/.anytoour-match/operations/'.MST_OP;$reservation=mst_ev((string)@file_get_contents($dir.'/reservation.json'));if(($reservation['operation_id']??'')!==MST_OP||($reservation['source_sha']??'')!==$sha||($reservation['state']??'')!=='reserved_before_db_access')throw new RuntimeException('reservation_guard');
$parentPath=$home.'/.anytoour-match/operations/'.MST_PARENT_OP.'/result.json';$parentRaw=(string)@file_get_contents($parentPath);if($parentRaw===''||hash('sha256',$parentRaw)!==MST_PARENT_RESULT_SHA)throw new RuntimeException('parent_result_guard');$parent=mst_ev($parentRaw);if(($parent['state']??'')!=='completed_read_only'||($parent['read_at_utc']??'')==='')throw new RuntimeException('parent_state');$sources=mst_parent_rows($parent);if(count($sources)!==529)throw new RuntimeException('parent_coverage');
$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
    $hotels=[];$forms=[];$index=[];foreach(mst_query($db,'SELECT id,country_id,name,region_name,subregion_name,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN (1,4) ORDER BY country_id,id') as $h){$id=(int)$h['id'];$hotels[$id]=$h;$forms[$id]=[(string)$h['name']];}
    foreach(mst_query($db,'SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN (1,4) ORDER BY a.hotel_id,a.id') as $a){$id=(int)$a['hotel_id'];if(isset($forms[$id]))$forms[$id][]=(string)$a['alias'];}
    foreach($forms as $id=>$list){$cid=(int)$hotels[$id]['country_id'];foreach(array_unique($list) as $name)foreach(mst_tokens((string)$name) as $key=>$f)$index[$cid][$key][$id][]=$f;}
    $rows=[];foreach(mst_query($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id") as $r)$rows[(string)$r['external_hotel_id']]=$r;
    $occupancy=[];foreach($rows as $id=>$r)if($r['local_hotel_id']!==null&&($r['decision_status']??'')==='accepted')$occupancy[(int)$r['local_hotel_id']][]=$id;
    $outRows=[];$reasons=[];$passes=[];$noLonger=[];foreach($sources as $id=>$src){if(!isset($rows[$id])){$outRows[]=['external_hotel_id'=>$id,'route'=>'hold','reason'=>'identity_missing_current'];$reasons['identity_missing_current']=($reasons['identity_missing_current']??0)+1;continue;}$r=$rows[$id];if(($r['decision_status']??'')!=='pending'||$r['local_hotel_id']!==null){$noLonger[]=$id;continue;}$ev=mst_ev((string)$r['evidence_json']);$c=mst_classify($src,$r,$ev,$hotels,$forms,$index,$occupancy);$c=['external_hotel_id'=>$id,'country_id'=>$src['country_id'],'evidence_sha256'=>$r['evidence_sha256']]+$c;$outRows[]=$c;$reason=(string)($c['reason']??'unknown');$reasons[$reason]=($reasons[$reason]??0)+1;if(($c['route']??'')==='guard_passed_prepared')$passes[]=$c;}
    $targetCounts=[];foreach($passes as $p)$targetCounts[(int)$p['target']]=($targetCounts[(int)$p['target']]??0)+1;if($targetCounts){foreach($outRows as &$r)if(($r['route']??'')==='guard_passed_prepared'&&($targetCounts[(int)$r['target']]??0)>1){$r['route']='hold';$r['reason']='duplicate_planned_target';$r['holds'][]='duplicate_planned_target';}unset($r);}
    $passes=array_values(array_filter($outRows,fn($r)=>($r['route']??'')==='guard_passed_prepared'));$reasons=[];foreach($outRows as $r){$k=(string)($r['reason']??'unknown');$reasons[$k]=($reasons[$k]??0)+1;}ksort($reasons);
    $result=['schema'=>'saved-town-current-reconcile/1','operation_id'=>MST_OP,'source_sha'=>$sha,'claim_comment_id'=>5682384436,'state'=>'completed_read_only','parent_operation_id'=>MST_PARENT_OP,'parent_result_sha256'=>MST_PARENT_RESULT_SHA,'parent_dossiers'=>count($sources),'examined_current_pending'=>count($outRows),'no_longer_pending'=>count($noLonger),'guard_passed_prepared'=>count($passes),'held'=>count($outRows)-count($passes),'reason_counts'=>$reasons,'prepared_rows'=>$passes,'rows'=>$outRows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'readback_verified'=>true,'safe_to_write_now'=>false,'no_replay'=>true,'read_at_utc'=>gmdate('c'),'transaction'=>'REPEATABLE READ / READ ONLY'];
    $db->commit();$hash=mst_write($dir.'/result.json',$result);mst_write($dir.'/receipt.json',['operation_id'=>MST_OP,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$hash,'readback_verified'=>true,'prepared_count'=>count($passes),'no_replay'=>true,'created_at'=>gmdate('c')]);echo 'MATCH_SAVED_TOWN_RECONCILE '.count($passes)."/".count($outRows)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if(!is_file($dir.'/failure.json'))mst_write($dir.'/failure.json',['operation_id'=>MST_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','error_class'=>get_class($e),'no_replay'=>true]);throw$e;}
