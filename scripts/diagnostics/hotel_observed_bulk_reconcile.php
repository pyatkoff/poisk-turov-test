<?php
declare(strict_types=1);

const HB_OPERATION = 'hotel-observed-bulk-1759-20260911-v1';
const HB_POLICY = 'owner_exact_and_strong_20260908';

function hb_json($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function hb_hash($v): string { return hash('sha256', is_string($v) ? $v : hb_json($v)); }
function hb_norm($v): string {
    $v = trim((string)$v);
    if ($v === '') return '';
    if (function_exists('v2_data_normalize_text')) return trim((string)v2_data_normalize_text($v));
    $v = mb_strtolower($v, 'UTF-8');
    $v = strtr($v, ['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    preg_match_all('/[\p{L}\p{N}]+/u', $v, $m);
    return implode(' ', $m[0]);
}
function hb_write_once(string $path, array $value): void {
    $raw=hb_json($value); $f=@fopen($path,'x'); if(!$f) throw new RuntimeException('operation_already_reserved');
    @chmod($path,0600);
    try { if(fwrite($f,$raw)!==strlen($raw)||!fflush($f)) throw new RuntimeException('receipt_write'); if(function_exists('fsync')&&!fsync($f)) throw new RuntimeException('receipt_sync'); }
    finally { fclose($f); }
}
function hb_tables(PDO $pdo): void {
    $need=['catalog_hotels','hotel_aliases','anex_search_hotel_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_search_hotel_observations','andromeda_hotel_identities'];
    $marks=implode(',',array_fill(0,count($need),'?'));
    $q=$pdo->prepare("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)");$q->execute($need);$got=$q->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach($need as $table) if(strtoupper((string)($got[$table]??''))!=='INNODB') throw new RuntimeException('required_transactional_table_missing');
}
function hb_placeholders(array $ids): string { if(!$ids) throw new RuntimeException('empty_scope'); return implode(',',array_fill(0,count($ids),'?')); }
function hb_name_index(PDO $pdo,array $countries): array {
    sort($countries,SORT_NUMERIC); $countries=array_values(array_unique(array_map('intval',$countries))); if(!$countries||count($countries)>100) throw new RuntimeException('country_scope');
    $marks=hb_placeholders($countries);
    $q=$pdo->prepare("SELECT id,country_id,name,normalized_name,category,is_active FROM catalog_hotels WHERE country_id IN ($marks) AND is_active=1 ORDER BY country_id,id FOR UPDATE");$q->execute($countries);
    $hotels=[];$index=[];$count=0;
    while($h=$q->fetch(PDO::FETCH_ASSOC)){ if(++$count>100000)throw new RuntimeException('hotel_scope_limit');$id=(int)$h['id'];$country=(int)$h['country_id'];$hotels[$id]=$h;foreach(array_unique(array_filter([hb_norm($h['normalized_name']),hb_norm($h['name'])])) as $n)$index[$country][$n][$id]=true; }
    $q=$pdo->prepare("SELECT a.hotel_id,a.alias,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id IN ($marks) AND h.is_active=1 ORDER BY h.country_id,a.hotel_id,a.id FOR UPDATE");$q->execute($countries);$aliases=0;
    while($a=$q->fetch(PDO::FETCH_ASSOC)){ if(++$aliases>200000)throw new RuntimeException('alias_scope_limit');$n=hb_norm($a['normalized_alias']?:$a['alias']);if($n!=='')$index[(int)$a['country_id']][$n][(int)$a['hotel_id']]=true; }
    return [$hotels,$index,['countries'=>count($countries),'hotels'=>$count,'aliases'=>$aliases]];
}
function hb_candidate(array $index,int $country,string $name): ?int {
    $n=hb_norm($name); if($n==='')return null; $ids=array_map('intval',array_keys($index[$country][$n]??[])); sort($ids,SORT_NUMERIC); return count($ids)===1?$ids[0]:null;
}
function hb_coverage(PDO $pdo): array {
    require_once getcwd().'/_preview/search3-anex-candidate/app/integrations/anex-search-mapping-registry.php';
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$anex=[];
    $ids=$pdo->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id){$target=$registry->resolve('anex_online',(string)$id,'preview');if($target!==null)$anex[$target]=true;}
    $rows=$pdo->query("SELECT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);$andr=array_fill_keys(array_map('intval',$rows),true);
    $triple=count(array_intersect_key($anex,$andr));return ['anex_links'=>$registry->count(),'anex_unique_local'=>count($anex),'andromeda_unique_local'=>count($andr),'all_three'=>$triple,'anex_tv_only'=>count($anex)-$triple,'andromeda_tv_only'=>count($andr)-$triple,'exactly_two'=>count($anex)+count($andr)-2*$triple];
}
function hb_reconcile(PDO $pdo,string $operation): array {
    hb_tables($pdo);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('SET SESSION innodb_lock_wait_timeout=15');
    $before=hb_coverage($pdo);$pdo->beginTransaction();$committed=false;
    try {
        $anexObs=$pdo->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id LIMIT 50001 FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);if(count($anexObs)>50000)throw new RuntimeException('anex_observation_limit');
        $andrObs=$pdo->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id LIMIT 50001 FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);if(count($andrObs)>50000)throw new RuntimeException('andromeda_observation_limit');
        $countries=[];foreach($anexObs as $r)$countries[(int)$r['country_id']]=true;foreach($andrObs as $r)$countries[(int)$r['country_id']]=true;if(!$countries){$pdo->rollBack();return ['status'=>'empty','operation_id'=>$operation,'supplier_calls'=>0,'database_writes'=>0,'before'=>$before,'after'=>$before];}
        [$hotels,$index,$catalog]=hb_name_index($pdo,array_keys($countries));
        $manual=[];foreach($pdo->query('SELECT anex_hotel_id FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN) as $id)$manual[(int)$id]=true;
        $existing=[];foreach($pdo->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN) as $id)$existing[(int)$id]=true;
        $excluded=[];foreach($pdo->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $insert=$pdo->prepare('INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,\'exact\',\'preview\',?,?,?,1)');
        $anexStats=['observed'=>count($anexObs),'eligible'=>0,'accepted'=>0,'ambiguous_or_missing'=>0,'manual'=>0,'existing'=>0,'pair_excluded'=>0];$anexRows=[];$mapDigest=hb_hash([$operation,'server_current_exact_observed_v1']);
        foreach($anexObs as $o){$id=(int)$o['anex_hotel_id'];if(isset($manual[$id])){$anexStats['manual']++;continue;}if(isset($existing[$id])){$anexStats['existing']++;continue;}$anexStats['eligible']++;$target=hb_candidate($index,(int)$o['country_id'],(string)$o['hotel_name']);if($target===null){$anexStats['ambiguous_or_missing']++;continue;}if(isset($excluded[$id][$target])){$anexStats['pair_excluded']++;continue;}$digest=hb_hash(['operation'=>$operation,'provider'=>'anex','id'=>$id,'country'=>(int)$o['country_id'],'hotel_name'=>(string)$o['hotel_name'],'target'=>$target,'rule'=>'unique_exact_current_name_or_alias']);$insert->execute([$id,$target,HB_POLICY,$digest,$mapDigest]);$anexRows[$id]=[$target,$digest];$anexStats['accepted']++;}
        $pending=$pdo->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);$pendingBy=[];foreach($pending as $r)$pendingBy[(string)$r['external_hotel_id']]=$r;
        $latest=[];foreach($andrObs as $o){$id=(string)$o['external_hotel_id'];if(!isset($latest[$id]))$latest[$id]=$o;}
        $update=$pdo->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=?");
        $andrStats=['observation_events'=>count($andrObs),'unique_observed'=>count($latest),'pending_observed'=>0,'accepted'=>0,'ambiguous_or_missing'=>0,'category_conflict'=>0,'not_pending'=>0];$andrRows=[];
        foreach($latest as $id=>$o){$old=$pendingBy[$id]??null;if(!$old){$andrStats['not_pending']++;continue;}$andrStats['pending_observed']++;$target=hb_candidate($index,(int)$o['country_id'],(string)$o['hotel_name']);if($target===null){$andrStats['ambiguous_or_missing']++;continue;}$h=$hotels[$target]??null;if(!$h||(int)$h['country_id']!==(int)$o['country_id'])throw new RuntimeException('target_country_changed');$sc=$o['category']===null?null:(int)$o['category'];$tc=$h['category']===null?null:(int)$h['category'];if($sc!==null&&$tc!==null&&$sc>0&&$tc>0&&$sc!==$tc){$andrStats['category_conflict']++;continue;}$prior=json_decode((string)$old['evidence_json'],true,64,JSON_THROW_ON_ERROR);$e=['prior_evidence'=>$prior,'promotion'=>['operation_id'=>$operation,'source'=>'live_search_observation','observation_sha256'=>$o['observation_sha256'],'observed_at_utc'=>$o['observed_at_utc'],'hotel_name'=>$o['hotel_name'],'country_id'=>(int)$o['country_id'],'target'=>$target,'rule'=>'unique_exact_current_name_or_alias','category_check'=>'equal_or_unknown']];$ej=hb_json($e);$eh=hash('sha256',$ej);$update->execute([$target,$eh,$ej,$id,$old['evidence_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('andromeda_concurrent_change');$andrRows[$id]=[$target,$eh];$andrStats['accepted']++;}
        $pdo->commit();$committed=true;
        $q=$pdo->prepare("SELECT catalog_hotel_id,source_row_digest,enabled FROM anex_hotel_search_mappings WHERE anex_hotel_id=? AND scope='preview' AND approval_policy=?");foreach($anexRows as $id=>$expected){$q->execute([$id,HB_POLICY]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||(int)$r['catalog_hotel_id']!==$expected[0]||$r['source_row_digest']!==$expected[1]||(int)$r['enabled']!==1)throw new RuntimeException('anex_readback');}
        $q=$pdo->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");foreach($andrRows as $id=>$expected){$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||(int)$r['local_hotel_id']!==$expected[0]||$r['decision_status']!=='accepted'||$r['evidence_sha256']!==$expected[1])throw new RuntimeException('andromeda_readback');}
        $after=hb_coverage($pdo);return ['status'=>'completed','operation_id'=>$operation,'rule'=>'unique_exact_current_name_or_alias_in_country','catalog_scope'=>$catalog,'anex'=>$anexStats,'andromeda'=>$andrStats,'accepted_total'=>$anexStats['accepted']+$andrStats['accepted'],'readback_verified'=>true,'supplier_calls'=>0,'database_writes'=>$anexStats['accepted']+$andrStats['accepted'],'before'=>$before,'after'=>$after];
    } catch(Throwable $e){if(!$committed&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function hb_main(): array {
    $raw=file_get_contents('php://stdin',false,null,0,4097);$req=json_decode((string)$raw,true,16,JSON_THROW_ON_ERROR);if(($req['operation_id']??'')!==HB_OPERATION||!in_array($req['phase']??'',['apply','receipt'],true))throw new RuntimeException('request_scope');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');$config=require $root.'/_preview/search3-anex-candidate/.andromeda-private.php';$private=realpath(dirname($config['catalog_path']));if(!$private||strpos($private,$root.DIRECTORY_SEPARATOR)===0)throw new RuntimeException('private_state');$dir=$private.'/'.HB_OPERATION;
    if($req['phase']==='receipt'){if(is_file($dir.'/result.json'))return json_decode(file_get_contents($dir.'/result.json'),true,64,JSON_THROW_ON_ERROR);return ['status'=>is_file($dir.'/reservation.json')?'reserved_or_unknown':'not_started','operation_id'=>HB_OPERATION];}
    if(is_dir($dir)||!mkdir($dir,0700))throw new RuntimeException('operation_already_reserved');hb_write_once($dir.'/reservation.json',['operation_id'=>HB_OPERATION,'state'=>'reserved_before_database','rule'=>'unique_exact_current_name_or_alias_in_country','supplier_calls'=>0]);
    $helper=realpath($root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php'));if(!$helper||strpos($helper,$root.DIRECTORY_SEPARATOR)!==0)throw new RuntimeException('db_helper');require_once $helper;$pdo=v2_data_db();$result=hb_reconcile($pdo,HB_OPERATION);hb_write_once($dir.'/result.json',$result);return $result;
}
if(!defined('HB_LIBRARY_ONLY')){error_reporting(0);ob_start();try{$out=hb_main();}catch(Throwable $e){$out=['status'=>'failed','operation_id'=>HB_OPERATION,'reason'=>in_array($e->getMessage(),['operation_already_reserved','required_transactional_table_missing','country_scope','hotel_scope_limit','alias_scope_limit'],true)?$e->getMessage():'runtime_failure','supplier_calls'=>0];}while(ob_get_level())ob_end_clean();echo hb_json($out),"\n";exit(($out['status']??'')==='completed'||($out['status']??'')==='empty'?0:1);}
