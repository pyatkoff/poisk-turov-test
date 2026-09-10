<?php
declare(strict_types=1);

const HB2_OPERATION = 'hotel-observed-bulk-1759-20260911-v2';
const HB2_POLICY = 'owner_exact_and_strong_20260908';

function hb2_json($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function hb2_hash($v): string { return hash('sha256', is_string($v) ? $v : hb2_json($v)); }
function hb2_norm($v): string {
    $v=trim((string)$v); if($v==='') return '';
    $v=mb_strtolower($v,'UTF-8');
    $v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    preg_match_all('/[\p{L}\p{N}]+/u',$v,$m); return implode(' ',$m[0]);
}
function hb2_tokens($v): array {
    $v=preg_replace('/\s*\(\s*(?:ex|ех)\s*\.?\s+[^()]+\)\s*$/ui',' ',(string)$v);
    $generic=array_fill_keys(['hotel','hotels','resort','resorts','spa','the','and','club','apart','aparthotel','apartments','apartment','suite','suites','отель','отели','резорт','ресорт','спа','клуб','апартаменты'],true);
    return array_values(array_filter(explode(' ',hb2_norm($v)),static fn($x)=>$x!==''&&!isset($generic[$x])));
}
function hb2_safe_key($v): string { return implode(' ',hb2_tokens($v)); }
function hb2_bag_key($v): string { $t=hb2_tokens($v); sort($t,SORT_STRING); return implode(' ',$t); }
function hb2_distinctive(string $key): bool {
    $t=array_values(array_filter(explode(' ',$key))); if(count($t)>=2) return true;
    return count($t)===1 && mb_strlen($t[0],'UTF-8')>=7;
}
function hb2_country_allowed($name): bool {
    $n=hb2_norm($name); return !in_array($n,['russia','россия','abkhazia','абхазия'],true);
}
function hb2_write_once(string $path,array $value): void {
    $raw=hb2_json($value);$f=@fopen($path,'x');if(!$f)throw new RuntimeException('operation_already_reserved');@chmod($path,0600);
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('receipt_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('receipt_sync');}finally{fclose($f);}
}
function hb2_tables(PDO $pdo): void {
    $need=['catalog_hotels','catalog_hotel_details','hotel_aliases','anex_hotels','anex_search_hotel_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_search_hotel_observations','andromeda_hotel_identities'];
    $marks=implode(',',array_fill(0,count($need),'?'));$q=$pdo->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)");$q->execute($need);$got=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach($need as $t)if(strtoupper((string)($got[$t]??''))!=='INNODB')throw new RuntimeException('required_transactional_table_missing');
}
function hb2_place_match(array $source,array $target): bool {
    $s=[];$t=[];foreach($source as $v){$n=hb2_norm($v);if($n!=='')$s[$n]=true;}foreach($target as $v){$n=hb2_norm($v);if($n!=='')$t[$n]=true;}return (bool)array_intersect_key($s,$t);
}
function hb2_number($v): ?float { if($v===null||$v==='')return null;$x=(float)$v;return is_finite($x)&&$x!=0.0?$x:null; }
function hb2_distance($lat1,$lon1,$lat2,$lon2): ?float {
    $p=[hb2_number($lat1),hb2_number($lon1),hb2_number($lat2),hb2_number($lon2)];if(in_array(null,$p,true))return null;
    [$a,$b,$c,$d]=array_map('deg2rad',$p);$x=sin(($c-$a)/2)**2+cos($a)*cos($c)*sin(($d-$b)/2)**2;return 6371000*2*asin(min(1,sqrt($x)));
}
function hb2_catalog(PDO $pdo,array $countries): array {
    $countries=array_values(array_unique(array_map('intval',$countries)));sort($countries,SORT_NUMERIC);if(!$countries||count($countries)>100)throw new RuntimeException('country_scope');$marks=implode(',',array_fill(0,count($countries),'?'));
    $sql="SELECT h.id,h.country_id,h.country_name,h.name,h.normalized_name,h.region_name,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.country_id IN ($marks) AND h.is_active=1 ORDER BY h.country_id,h.id FOR UPDATE";
    $q=$pdo->prepare($sql);$q->execute($countries);$hotels=[];$safe=[];$bag=[];$countryNames=[];$count=0;
    while($h=$q->fetch(PDO::FETCH_ASSOC)){if(++$count>100000)throw new RuntimeException('hotel_scope_limit');$id=(int)$h['id'];$c=(int)$h['country_id'];$hotels[$id]=$h;$countryNames[$c]=$h['country_name'];foreach([$h['name'],$h['normalized_name']] as $name){$s=hb2_safe_key($name);$b=hb2_bag_key($name);if($s!=='')$safe[$c][$s][$id]=true;if($b!=='')$bag[$c][$b][$id]=true;}}
    $q=$pdo->prepare("SELECT a.hotel_id,a.alias,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id IN ($marks) AND h.is_active=1 ORDER BY h.country_id,a.hotel_id,a.id FOR UPDATE");$q->execute($countries);$aliases=0;
    while($a=$q->fetch(PDO::FETCH_ASSOC)){if(++$aliases>200000)throw new RuntimeException('alias_scope_limit');$id=(int)$a['hotel_id'];$c=(int)$a['country_id'];foreach([$a['alias'],$a['normalized_alias']] as $name){$s=hb2_safe_key($name);$b=hb2_bag_key($name);if($s!=='')$safe[$c][$s][$id]=true;if($b!=='')$bag[$c][$b][$id]=true;}}
    return [$hotels,$safe,$bag,$countryNames,['countries'=>count($countries),'hotels'=>$count,'aliases'=>$aliases]];
}
function hb2_resolve(array $safe,array $bag,int $country,array $names): array {
    $names=array_values(array_unique(array_filter(array_map('strval',$names),static fn($x)=>trim($x)!=='')));
    foreach(['safe','bag'] as $mode){$votes=[];$keys=[];$index=$mode==='safe'?$safe:$bag;foreach($names as $name){$key=$mode==='safe'?hb2_safe_key($name):hb2_bag_key($name);if($key==='')continue;$ids=array_map('intval',array_keys($index[$country][$key]??[]));if(count($ids)===1){$votes[$ids[0]]=($votes[$ids[0]]??0)+1;$keys[$key]=true;}}
        if(count($votes)===1){$id=(int)array_key_first($votes);return ['target'=>$id,'mode'=>$mode,'votes'=>$votes[$id],'key'=>array_key_first($keys)?:''];}
        if(count($votes)>1)return ['target'=>null,'mode'=>'conflict','votes'=>array_sum($votes),'key'=>''];
    }
    return ['target'=>null,'mode'=>'none','votes'=>0,'key'=>''];
}
function hb2_coverage(PDO $pdo): array {
    $anex=[];
    $sql="SELECT m.catalog_hotel_id FROM anex_hotel_search_mappings m JOIN catalog_hotels h ON h.id=m.catalog_hotel_id LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".HB2_POLICY."' AND m.match_class IN ('exact','strong_candidate') AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)";
    foreach($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id)$anex[(int)$id]=true;
    $sql="SELECT d.catalog_hotel_id FROM anex_hotel_decisions d JOIN catalog_hotels h ON h.id=d.catalog_hotel_id WHERE d.decision_status='accepted' AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)";
    foreach($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $id)$anex[(int)$id]=true;
    $andr=[];foreach($pdo->query("SELECT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $id)$andr[(int)$id]=true;
    $triple=count(array_intersect_key($anex,$andr));
    $links=(int)$pdo->query("SELECT COUNT(*) FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".HB2_POLICY."' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)")->fetchColumn();
    $manual=(int)$pdo->query("SELECT COUNT(*) FROM anex_hotel_decisions d JOIN catalog_hotels h ON h.id=d.catalog_hotel_id WHERE d.decision_status='accepted'")->fetchColumn();
    return ['anex_links'=>$links+$manual,'anex_unique_local'=>count($anex),'andromeda_unique_local'=>count($andr),'all_three'=>$triple,'anex_tv_only'=>count($anex)-$triple,'andromeda_tv_only'=>count($andr)-$triple,'exactly_two'=>count($anex)+count($andr)-2*$triple];
}
function hb2_reconcile(PDO $pdo,string $operation): array {
    hb2_tables($pdo);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('SET SESSION innodb_lock_wait_timeout=15');$before=hb2_coverage($pdo);$committed=false;$written=0;
    try{
        $pdo->beginTransaction();
        $anexObs=$pdo->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id LIMIT 50001 FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);if(count($anexObs)>50000)throw new RuntimeException('anex_observation_limit');
        $andrObs=$pdo->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id LIMIT 50001 FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);if(count($andrObs)>50000)throw new RuntimeException('andromeda_observation_limit');
        $countries=[];foreach($anexObs as $r)$countries[(int)$r['country_id']]=true;foreach($andrObs as $r)$countries[(int)$r['country_id']]=true;if(!$countries){$pdo->rollBack();return ['status'=>'empty','operation_id'=>$operation,'supplier_calls'=>0,'database_writes'=>0,'before'=>$before,'after'=>$before];}
        [$hotels,$safe,$bag,$countryNames,$catalog]=hb2_catalog($pdo,array_keys($countries));
        $manual=[];foreach($pdo->query('SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN) as $id)$manual[(int)$id]=true;
        $existing=[];foreach($pdo->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN) as $id)$existing[(int)$id]=true;
        $excluded=[];foreach($pdo->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $staging=[];foreach($pdo->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;
        $insert=$pdo->prepare("INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,'strong_candidate','preview',?,?,?,1)");
        $anex=['observed'=>count($anexObs),'eligible'=>0,'accepted'=>0,'no_staging'=>0,'name_unresolved'=>0,'geo_conflict'=>0,'needs_corroboration'=>0,'manual'=>0,'existing'=>0,'pair_excluded'=>0,'blocked_country'=>0];$anexRows=[];$mapDigest=hb2_hash([$operation,'server_current_canonical_geo_v2']);
        foreach($anexObs as $o){$id=(int)$o['anex_hotel_id'];if(isset($manual[$id])){$anex['manual']++;continue;}if(isset($existing[$id])){$anex['existing']++;continue;}$c=(int)$o['country_id'];if(!hb2_country_allowed($countryNames[$c]??'')){$anex['blocked_country']++;continue;}$anex['eligible']++;$s=$staging[$id]??null;if(!$s){$anex['no_staging']++;continue;}$r=hb2_resolve($safe,$bag,$c,[$o['hotel_name'],$s['api_name'],$s['xml_name'],$s['xml_alternate_name']]);$target=$r['target'];if($target===null){$anex['name_unresolved']++;continue;}$h=$hotels[$target]??null;if(!$h)throw new RuntimeException('target_missing');$distance=hb2_distance($s['latitude'],$s['longitude'],$h['latitude'],$h['longitude']);if($distance!==null&&$distance>5000){$anex['geo_conflict']++;continue;}$place=hb2_place_match([$s['api_region'],$s['api_town']],[$h['region_name'],$h['subregion_name']]);$coord=$distance!==null&&$distance<=1000;$strongName=$r['mode']==='safe'&&hb2_distinctive($r['key']);if($r['mode']==='bag'&&!($coord||$place)){$anex['needs_corroboration']++;continue;}if(!$strongName&&!($coord||$place||$r['votes']>=2)){$anex['needs_corroboration']++;continue;}if(isset($excluded[$id][$target])){$anex['pair_excluded']++;continue;}
            $e=['operation'=>$operation,'provider'=>'anex','id'=>$id,'country_id'=>$c,'names'=>array_values(array_filter([$o['hotel_name'],$s['api_name'],$s['xml_name'],$s['xml_alternate_name']])), 'target'=>$target,'rule'=>'canonical_unique_current_name_alias_plus_geo','mode'=>$r['mode'],'name_votes'=>$r['votes'],'place_match'=>$place,'distance_m'=>$distance===null?null:round($distance,1)];$digest=hb2_hash($e);$insert->execute([$id,$target,HB2_POLICY,$digest,$mapDigest]);$anexRows[$id]=[$target,$digest];$anex['accepted']++;$written++;}
        $pending=[];foreach($pdo->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC) as $r)$pending[(string)$r['external_hotel_id']]=$r;
        $latest=[];foreach($andrObs as $o){$id=(string)$o['external_hotel_id'];if(!isset($latest[$id]))$latest[$id]=$o;}
        $update=$pdo->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=?");
        $andr=['observation_events'=>count($andrObs),'unique_observed'=>count($latest),'pending_observed'=>0,'accepted'=>0,'name_unresolved'=>0,'category_conflict'=>0,'needs_corroboration'=>0,'not_pending'=>0,'blocked_country'=>0];$andrRows=[];
        foreach($latest as $id=>$o){$old=$pending[$id]??null;if(!$old){$andr['not_pending']++;continue;}$andr['pending_observed']++;$c=(int)$o['country_id'];if(!hb2_country_allowed($countryNames[$c]??'')){$andr['blocked_country']++;continue;}$r=hb2_resolve($safe,$bag,$c,[$o['hotel_name']]);$target=$r['target'];if($target===null){$andr['name_unresolved']++;continue;}$h=$hotels[$target]??null;if(!$h)throw new RuntimeException('target_missing');$sc=$o['category']===null?null:(int)$o['category'];$tc=$h['category']===null?null:(int)$h['category'];if($sc!==null&&$tc!==null&&$sc>0&&$tc>0&&$sc!==$tc){$andr['category_conflict']++;continue;}$place=hb2_place_match([$o['region_name']],[$h['region_name'],$h['subregion_name']]);$category=$sc!==null&&$tc!==null&&$sc>0&&$sc===$tc;$strongName=$r['mode']==='safe'&&hb2_distinctive($r['key']);if($r['mode']==='bag'&&!$place){$andr['needs_corroboration']++;continue;}if(!$place&&!$category&&!($strongName&&$r['votes']>=1)){$andr['needs_corroboration']++;continue;}
            $prior=json_decode((string)$old['evidence_json'],true,64,JSON_THROW_ON_ERROR);$e=['prior_evidence'=>$prior,'promotion'=>['operation_id'=>$operation,'source'=>'live_search_observation_stage2','observation_sha256'=>$o['observation_sha256'],'observed_at_utc'=>$o['observed_at_utc'],'hotel_name'=>$o['hotel_name'],'country_id'=>$c,'target'=>$target,'rule'=>'canonical_unique_current_name_alias_plus_geo','mode'=>$r['mode'],'region_match'=>$place,'category_match'=>$category]];$ej=hb2_json($e);$eh=hash('sha256',$ej);$update->execute([$target,$eh,$ej,$id,$old['evidence_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('andromeda_concurrent_change');$andrRows[$id]=[$target,$eh];$andr['accepted']++;$written++;}
        $pdo->commit();$committed=true;
        $q=$pdo->prepare("SELECT catalog_hotel_id,source_row_digest,enabled,match_class FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND scope='preview' AND approval_policy=?");foreach($anexRows as $id=>$exp){$q->execute([$id,HB2_POLICY]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||(int)$r['catalog_hotel_id']!==$exp[0]||$r['source_row_digest']!==$exp[1]||(int)$r['enabled']!==1||$r['match_class']!=='strong_candidate')throw new RuntimeException('anex_readback');}
        $q=$pdo->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");foreach($andrRows as $id=>$exp){$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||(int)$r['local_hotel_id']!==$exp[0]||$r['decision_status']!=='accepted'||$r['evidence_sha256']!==$exp[1])throw new RuntimeException('andromeda_readback');}
        $after=hb2_coverage($pdo);return ['status'=>'completed','operation_id'=>$operation,'rule'=>'canonical_unique_current_name_alias_plus_geo','catalog_scope'=>$catalog,'anex'=>$anex,'andromeda'=>$andr,'accepted_total'=>$anex['accepted']+$andr['accepted'],'database_writes'=>$written,'readback_verified'=>true,'supplier_calls'=>0,'before'=>$before,'after'=>$after];
    }catch(Throwable $e){if(!$committed&&$pdo->inTransaction())$pdo->rollBack();return ['status'=>$committed?'committed_readback_failed':'failed_rolled_back','operation_id'=>$operation,'database_writes'=>$committed?$written:0,'readback_verified'=>false,'supplier_calls'=>0,'reason'=>in_array($e->getMessage(),['required_transactional_table_missing','country_scope','hotel_scope_limit','alias_scope_limit'],true)?$e->getMessage():'runtime_failure'];}
}
function hb2_main(): array {
    $raw=file_get_contents('php://stdin',false,null,0,4097);$req=json_decode((string)$raw,true,16,JSON_THROW_ON_ERROR);if(($req['operation_id']??'')!==HB2_OPERATION||!in_array($req['phase']??'',['apply','receipt'],true))throw new RuntimeException('request_scope');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');$config=require $root.'/_preview/search3-anex-candidate/.andromeda-private.php';$private=realpath(dirname($config['catalog_path']));if(!$private||strpos($private,$root.DIRECTORY_SEPARATOR)===0)throw new RuntimeException('private_state');$dir=$private.'/'.HB2_OPERATION;
    if($req['phase']==='receipt'){if(is_file($dir.'/result.json'))return json_decode(file_get_contents($dir.'/result.json'),true,64,JSON_THROW_ON_ERROR);return ['status'=>is_file($dir.'/reservation.json')?'reserved_or_unknown':'not_started','operation_id'=>HB2_OPERATION,'supplier_calls'=>0];}
    if(is_dir($dir)||!mkdir($dir,0700))throw new RuntimeException('operation_already_reserved');hb2_write_once($dir.'/reservation.json',['operation_id'=>HB2_OPERATION,'state'=>'reserved_before_database','rule'=>'canonical_unique_current_name_alias_plus_geo','supplier_calls'=>0]);
    $helper=realpath($root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php'));if(!$helper||strpos($helper,$root.DIRECTORY_SEPARATOR)!==0)throw new RuntimeException('db_helper');require_once $helper;$pdo=v2_data_db();$result=hb2_reconcile($pdo,HB2_OPERATION);hb2_write_once($dir.'/result.json',$result);return $result;
}
if(!defined('HB2_LIBRARY_ONLY')){error_reporting(0);ob_start();try{$out=hb2_main();}catch(Throwable $e){$out=['status'=>'failed','operation_id'=>HB2_OPERATION,'reason'=>in_array($e->getMessage(),['operation_already_reserved'],true)?$e->getMessage():'runtime_failure','supplier_calls'=>0];}while(ob_get_level())ob_end_clean();echo hb2_json($out),"\n";exit(in_array(($out['status']??''),['completed','empty'],true)?0:1);}
