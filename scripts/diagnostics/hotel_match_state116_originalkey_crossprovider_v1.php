<?php
declare(strict_types=1);

const HM_OP = 'hotel-match-state116-originalkey-crossprovider-1971-20260916-v1';
const HM_STATE = '116';
const HM_COUNTRY = 12;
const HM_OPERATOR_NAMESPACES = ['operator_5','operator_315','operator_342','operator_115'];

function hm_json(array $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}
function hm_write(string $path,array $v): string {
    $raw=hm_json($v); $f=@fopen($path,'x+b'); if(!$f) throw new RuntimeException('durable_open');
    try { if(fwrite($f,$raw)!==strlen($raw)||!fflush($f)) throw new RuntimeException('durable_write'); if(function_exists('fsync')&&!fsync($f)) throw new RuntimeException('durable_sync'); rewind($f); if(stream_get_contents($f)!==$raw) throw new RuntimeException('durable_readback'); }
    finally { fclose($f); }
    return hash('sha256',$raw);
}
function hm_db_path(string $root): string {
    foreach([$root.'/data/db-v1.php',$root.'/v2/data/db-v1.php'] as $p) if(is_file($p)) return $p;
    throw new RuntimeException('db_missing');
}
function hm_query(PDO $db,string $sql,array $args=[]): array {
    $q=$db->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function hm_ev($raw): array {
    if(!is_string($raw)||$raw==='') return [];
    try { $v=json_decode($raw,true,128,JSON_THROW_ON_ERROR); return is_array($v)?$v:[]; } catch(Throwable) { return []; }
}
function hm_source(array $e): array { return is_array($e['source']??null)?$e['source']:$e; }
function hm_first(array $a,array $keys): ?string {
    foreach($keys as $k) if(isset($a[$k])&&is_scalar($a[$k])&&trim((string)$a[$k])!=='') return trim((string)$a[$k]);
    return null;
}
function hm_manual_marker($v): bool {
    if(!is_array($v)) return false;
    foreach($v as $k=>$x){
        $lk=mb_strtolower((string)$k,'UTF-8');
        if(in_array($lk,['manual','manual_decision','excluded','exclusion','rejected','protected','pair_exclusion'],true)){
            if($x===true||$x===1||$x==='1'||(is_string($x)&&trim($x)!==''&&!in_array(mb_strtolower(trim($x),'UTF-8'),['false','none','no','0'],true))) return true;
        }
        if(is_array($x)&&hm_manual_marker($x)) return true;
    }
    return false;
}
function hm_norm(string $s): string {
    $s=mb_strtolower($s,'UTF-8');
    $s=strtr($s,['ё'=>'е','é'=>'e','è'=>'e','á'=>'a','à'=>'a','ä'=>'a','ö'=>'o','ü'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','ñ'=>'n']);
    $s=preg_replace('/\s*\(\s*(?:ex|ех)\.?\s+[^)]*\)\s*$/u',' ',$s)??$s;
    $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;
    return trim(preg_replace('/\s+/u',' ',$s)??$s);
}
function hm_tokens(string $s): array {
    $s=hm_norm($s); if($s==='') return [];
    $generic=['hotel','hotels','resort','resorts','spa','the','and','by','отель','спа'];
    return array_values(array_unique(array_diff(explode(' ',$s),$generic)));
}
function hm_qualifiers(string $s): array {
    $q=['annex','beach','garden','gardens','north','south','east','west','adult','adults','only','aqua','aquapark','aquamarine','pool','private'];
    $r=array_values(array_intersect(hm_tokens($s),$q)); sort($r,SORT_STRING); return $r;
}
function hm_numbers(string $s): array {
    preg_match_all('/(?<!\p{L})\d+(?!\p{L})/u',hm_norm($s),$m); $r=array_values(array_unique($m[0]??[])); sort($r,SORT_STRING); return $r;
}
function hm_name_guard(?string $source,?string $local): array {
    if($source===null||$local===null||trim($source)===''||trim($local)==='') return ['ok'=>true,'reason'=>'name_missing_nonblocking'];
    $sq=hm_qualifiers($source); $lq=hm_qualifiers($local); if($sq!==$lq) return ['ok'=>false,'reason'=>'meaningful_qualifier_conflict','source_qualifiers'=>$sq,'local_qualifiers'=>$lq];
    $sn=hm_numbers($source); $ln=hm_numbers($local); if(($sn||$ln)&&$sn!==$ln) return ['ok'=>false,'reason'=>'numeric_conflict','source_numbers'=>$sn,'local_numbers'=>$ln];
    return ['ok'=>true,'reason'=>'qualifier_numeric_compatible'];
}
function hm_num($v): ?float { return is_scalar($v)&&is_numeric((string)$v)?(float)$v:null; }
function hm_coord_pairs(array $e): array {
    $out=[]; $sources=[]; $s=hm_source($e); if($s)$sources[]=['kind'=>'source','v'=>$s];
    foreach(['geography','geo','coordinates'] as $k) if(is_array($e[$k]??null)) $sources[]=['kind'=>$k,'v'=>$e[$k]];
    foreach($sources as $x){ $a=$x['v']; $lat=hm_num($a['latitude']??$a['lat']??$a['hotelLatitude']??null); $lon=hm_num($a['longitude']??$a['lng']??$a['lon']??$a['hotelLongitude']??null); if($lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180&&!($lat==0.0&&$lon==0.0)) $out[]=['kind'=>$x['kind'],'lat'=>$lat,'lon'=>$lon]; }
    return $out;
}
function hm_km(float $a,float $b,float $c,float $d): float {
    $r=6371.0088; $p1=deg2rad($a); $p2=deg2rad($c); $h=sin(deg2rad($c-$a)/2)**2+cos($p1)*cos($p2)*sin(deg2rad($d-$b)/2)**2; return 2*$r*asin(min(1,sqrt($h)));
}
function hm_known_bridge_ids(array $e,string $ns,int $rowLocal): array {
    $schema=(string)($e['schema']??''); $ids=[];
    if($schema==='operator-original-price-bridge/1'){
        foreach(($e['provider_bridges']??[]) as $f){
            if(!is_array($f)) continue;
            $id=$f['andromeda_hotel_id']??null; $op=(string)($f['operator_key']??'');
            if(!is_scalar($id)||!preg_match('/^[1-9][0-9]{0,19}$/D',(string)$id)) continue;
            if(($f['action']??null)!=='price'||($f['is_operator_hotel_key']??true)!==false) continue;
            if('operator_'.$op!==$ns) continue;
            $ids[(string)$id]=['schema'=>$schema,'request_sha256'=>$f['request_sha256']??null,'response_sha256'=>$f['response_sha256']??null];
        }
    } elseif($schema==='received-nonanex-exact-missing-native/1'){
        $s=is_array($e['source']??null)?$e['source']:[]; $d=is_array($e['decision']??null)?$e['decision']:[];
        $id=$s['andromeda_hotel_id']??null;
        if(($s['supplier_namespace']??null)===$ns&&is_scalar($id)&&preg_match('/^[1-9][0-9]{0,19}$/D',(string)$id)&&((int)($d['local_hotel_id']??0)===$rowLocal)){
            $ids[(string)$id]=['schema'=>$schema,'request_sha256'=>$s['request_sha256']??null,'response_sha256'=>$s['response_sha256']??null];
        }
    }
    return $ids;
}
function hm_state(array $e): string {
    $s=hm_source($e); return (string)(hm_first($s,['stateKey','state_id','countryKey'])??'');
}

if(in_array('--self-test',$argv??[],true)){
    $a=['schema'=>'operator-original-price-bridge/1','provider_bridges'=>[['andromeda_hotel_id'=>'2001','operator_key'=>'115','action'=>'price','is_operator_hotel_key'=>false]]];
    $b=hm_known_bridge_ids($a,'operator_115',9); if(!isset($b['2001'])) throw new RuntimeException('bridge_selftest');
    $g=hm_name_guard('North Beach Resort 2','NORTH BEACH HOTEL 2'); if(!$g['ok']) throw new RuntimeException('name_selftest');
    $g=hm_name_guard('North Beach Resort 2','South Beach Hotel 2'); if($g['ok']) throw new RuntimeException('qualifier_selftest');
    if(round(hm_km(6.9271,79.8612,6.9271,79.8612),6)!==0.0) throw new RuntimeException('distance_selftest');
    echo "PASS\n"; exit(0);
}

$op=(string)getenv('OPERATION_ID'); $sha=(string)getenv('MATCH_SOURCE_SHA');
if($op!==HM_OP||!preg_match('/^[0-9a-f]{40}$/D',$sha)) throw new RuntimeException('operation_environment');
$root=realpath(getcwd()); $home=rtrim((string)getenv('HOME'),'/'); $base=$home.'/.anytoour-match/operations';
if(!$root||basename($root)!=='anytoour.ru'||!is_dir($base)) throw new RuntimeException('root_guard');
$dir=$base.'/'.$op; if(!mkdir($dir,0700)) throw new RuntimeException('operation_exists_no_replay');
hm_write($dir.'/reservation.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'reserved_before_db_access','read_only'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);

$db=null;
try {
    require_once hm_db_path($root); $db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');

    $pending=[];
    foreach(hm_query($db,"SELECT external_hotel_id,evidence_json,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id") as $r){
        $e=hm_ev($r['evidence_json']??''); if(hm_state($e)!==HM_STATE) continue; $pending[(string)$r['external_hotel_id']]=['row'=>$r,'evidence'=>$e];
    }

    $support=[]; $operatorRows=0; $operatorRowsKnownSchema=0;
    foreach(hm_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,evidence_json,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace IN ('operator_5','operator_315','operator_342','operator_115') AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY supplier_namespace,external_hotel_id") as $r){
        $operatorRows++; $ns=(string)$r['supplier_namespace']; $local=(int)$r['local_hotel_id']; $e=hm_ev($r['evidence_json']??''); $ids=hm_known_bridge_ids($e,$ns,$local); if($ids)$operatorRowsKnownSchema++;
        foreach($ids as $aid=>$meta){ if(!isset($pending[$aid])) continue; $support[$aid][$local][]=['supplier_namespace'=>$ns,'native_hotel_id'=>(string)$r['external_hotel_id'],'evidence_sha256'=>(string)$r['evidence_sha256'],'schema'=>$meta['schema'],'request_sha256'=>$meta['request_sha256'],'response_sha256'=>$meta['response_sha256']]; }
    }

    $localIds=[]; foreach($support as $byLocal) foreach(array_keys($byLocal) as $id) $localIds[(int)$id]=true;
    $hotels=[]; if($localIds) foreach(hm_query($db,'SELECT id,country_id,name,latitude,longitude,is_active FROM catalog_hotels WHERE id IN ('.implode(',',array_fill(0,count($localIds),'?')).') ORDER BY id',array_keys($localIds)) as $h) $hotels[(int)$h['id']]=$h;
    $occupied=[]; foreach(hm_query($db,"SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id,external_hotel_id") as $r) $occupied[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
    $db->exec('ROLLBACK');

    $rows=[]; $counts=[]; $clean=[];
    foreach($pending as $aid=>$p){
        $e=$p['evidence']; $s=hm_source($e); $name=hm_first($s,['name','hotelName','title']); $byLocal=$support[$aid]??[]; $route='no_operator_originalkey_bridge'; $target=null; $details=[];
        if(hm_manual_marker($e)) $route='hold_source_manual_or_protected';
        elseif(count($byLocal)>1){ $route='hold_ambiguous_operator_local_targets'; $details['candidate_local_ids']=array_map('intval',array_keys($byLocal)); }
        elseif(count($byLocal)===1){
            $target=(int)array_key_first($byLocal); $h=$hotels[$target]??null;
            if(!$h||(int)$h['is_active']!==1||(int)$h['country_id']!==HM_COUNTRY) $route='hold_target_country_or_activity';
            else {
                $others=array_values(array_filter($occupied[$target]??[],static fn($x)=>$x!==$aid));
                if($others){ $route='hold_target_occupied_by_other_andromeda'; $details['occupying_andromeda_ids']=$others; }
                else {
                    $ng=hm_name_guard($name,(string)$h['name']);
                    if(!$ng['ok']){ $route='hold_'.$ng['reason']; $details['name_guard']=$ng; }
                    else {
                        $dist=[]; foreach(hm_coord_pairs($e) as $c){ $hlat=hm_num($h['latitude']); $hlon=hm_num($h['longitude']); if($hlat===null||$hlon===null||abs($hlat)>90||abs($hlon)>180||($hlat==0.0&&$hlon==0.0)) break; $dist[$c['kind']]=round(hm_km($c['lat'],$c['lon'],$hlat,$hlon),3); }
                        $bad=array_filter($dist,static fn($d)=>$d>5.0); if($bad){ $route='hold_coordinate_conflict_gt5km'; $details['distances_km']=$dist; }
                        else { $route='guard_passed_originalkey_crossprovider'; $details['distances_km']=$dist; $clean[]=['external_hotel_id'=>$aid,'source_name'=>$name,'local_hotel_id'=>$target,'local_name'=>(string)$h['name'],'support'=>$byLocal[$target],'distances_km'=>$dist]; }
                    }
                }
            }
        }
        $counts[$route]=($counts[$route]??0)+1;
        $rows[]=['external_hotel_id'=>$aid,'source_name'=>$name,'candidate_local_id'=>$target,'route'=>$route,'operator_support'=>$target!==null?($byLocal[$target]??[]):[],'details'=>$details];
    }
    ksort($counts,SORT_STRING); usort($clean,static fn($a,$b)=>strcmp($a['external_hotel_id'],$b['external_hotel_id']));
    $result=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','schema'=>'state116-originalkey-crossprovider-census/1','sealed_state'=>HM_STATE,'sealed_country_id'=>HM_COUNTRY,'current_pending_state116'=>count($pending),'operator_accepted_rows_scanned'=>$operatorRows,'operator_rows_with_known_originalkey_schema'=>$operatorRowsKnownSchema,'bridge_source_rows'=>count($support),'route_counts'=>$counts,'prepared_count'=>count($clean),'prepared'=>$clean,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'safe_to_write_now'=>false,'no_replay'=>true];
    $digest=hm_write($dir.'/result.json',$result); hm_write($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$digest,'readback_verified'=>true,'current_pending_state116'=>count($pending),'prepared_count'=>count($clean),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
    echo json_encode(['state'=>'completed_read_only','pending'=>count($pending),'prepared_count'=>count($clean),'route_counts'=>$counts],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $e){
    try { if($db instanceof PDO&&$db->inTransaction()) $db->rollBack(); } catch(Throwable) {}
    $failure=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','error_class'=>get_class($e),'reason'=>preg_match('/^[a-z0-9_]+$/D',$e->getMessage())?$e->getMessage():'sanitized_error','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
    $digest=hm_write($dir.'/failure.json',$failure); hm_write($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','failure_sha256'=>$digest,'readback_verified'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
    fwrite(STDERR,"STATE116_CROSSPROVIDER_FAILED\n"); exit(2);
}
