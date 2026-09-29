<?php
declare(strict_types=1);

function hmsl_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmsl_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmsl_hash(array $v):string{$x=[];foreach($v as $a){$a=(string)$a;if($a!=='')$x[$a]=1;}$x=array_keys($x);sort($x,SORT_STRING);return hash('sha256',hmsl_json($x));}
function hmsl_excluded(string $v):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($v))===1;}
function hmsl_cols(PDO $db,string $t):array{
    hmsl_need(preg_match('/^[A-Za-z0-9_]{1,64}$/D',$t)===1,'table');
    $s=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $s->execute([$t]);$o=[];foreach($s->fetchAll(PDO::FETCH_COLUMN)?:[] as $c)if(is_string($c))$o[$c]=1;return $o;
}
/** Pure reconciliation used by focused tests. */
function hmsl_reconcile(array $active,array $accepted,array $obs,array $price,array $store):array{
    $p=[];foreach($price as $id){$id=(int)$id;if(isset($active[$id]))$p[$id]=1;}
    $s=[];foreach($store as $id){$id=(int)$id;if(isset($active[$id]))$s[$id]=1;}
    $keys=[];$by=[];$om=[];$mappedKeys=0;$unresolved=0;$collisions=0;$catalog=[];$catalogUnresolved=[];$catalogMapped=[];
    foreach($obs as $r){
        $ns=trim((string)($r['supplier_namespace']??''));$ext=trim((string)($r['external_hotel_id']??''));if($ns===''||$ext==='')continue;
        $k=$ns.'|'.$ext;if(isset($keys[$k]))continue;$keys[$k]=1;$by[$ns]=($by[$ns]??0)+1;if($ns==='andromeda_catalog')$catalog[$ext]=1;
        $targets=[];foreach(array_keys($accepted[$k]??[]) as $id){$id=(int)$id;if(isset($active[$id]))$targets[$id]=1;}
        if(count($targets)===1){$id=(int)array_key_first($targets);$om[$id]=1;$mappedKeys++;if($ns==='andromeda_catalog')$catalogMapped[$id]=1;}
        elseif(count($targets)>1){$collisions++;}
        else{$unresolved++;if($ns==='andromeda_catalog')$catalogUnresolved[$ext]=1;}
    }
    ksort($by);$union=$p+$s+$om;
    return [
        'mapped_local_hotels'=>[
            'accounted_30d'=>count($union),'price_history'=>count($p),'offer_store'=>count($s),'later_resolved_observations'=>count($om),
            'price_only'=>count(array_diff_key($p,$s,$om)),'offer_store_only'=>count(array_diff_key($s,$p,$om)),'observation_only'=>count(array_diff_key($om,$p,$s)),
            'price_offer_overlap'=>count(array_intersect_key($p,$s)),'price_observation_overlap'=>count(array_intersect_key($p,$om)),
            'offer_observation_overlap'=>count(array_intersect_key($s,$om)),'set_sha256'=>hmsl_hash(array_keys($union))
        ],
        'observation_journal'=>['identity_keys'=>count($keys),'mapped_identity_keys_now'=>$mappedKeys,'unresolved_identity_keys'=>$unresolved,'source_collision_keys'=>$collisions,'by_namespace'=>$by,'catalog_identity_keys'=>count($catalog),'catalog_mapped_local_hotels_now'=>count($catalogMapped),'catalog_unresolved_identity_keys'=>count($catalogUnresolved)],
        'unique_hotel_accounting_range'=>[
            'lower_bound_mapped_local_hotels'=>count($union),
            'upper_bound_if_every_unresolved_identity_is_distinct_new_hotel'=>count($union)+$unresolved,
            'exact'=>$unresolved===0,'warning'=>'upper_bound_is_not_an_exact_unique_hotel_count'
        ],
        'hashes'=>['observed_identity_keys_sha256'=>hmsl_hash(array_keys($keys)),'catalog_unresolved_sha256'=>hmsl_hash(array_keys($catalogUnresolved))]
    ];
}
function hmsl_cache_catalog(string $root,int $cut):array{
    $config=$root.'/_preview/search3-anex-candidate/.andromeda-private.php';hmsl_need(is_file($config)&&!is_link($config),'cache_config');
    $cfg=require $config;hmsl_need(is_array($cfg)&&is_string($cfg['catalog_path']??null),'cache_catalog');
    $dir=dirname($cfg['catalog_path']).'/searches';hmsl_need(is_dir($dir)&&!is_link($dir),'cache_searches');
    $ids=[];$files=0;$bytes=0;$pages=0;$invalid=0;$first=null;$last=null;
    foreach(new DirectoryIterator($dir) as $f){
        if($f->isDot()||!$f->isFile()||$f->isLink()||!str_ends_with($f->getFilename(),'.json'))continue;
        $size=$f->getSize();hmsl_need($size>0&&$size<=16777216,'cache_page_size');$files++;$bytes+=$size;hmsl_need($files<=25000&&$bytes<=1073741824,'cache_scan_budget');
        try{$j=json_decode((string)file_get_contents($f->getPathname()),true,64,JSON_THROW_ON_ERROR);}catch(Throwable){$invalid++;continue;}
        $store=$j['store']??null;$snap=is_array($store)?($store['snapshot']??null):null;$created=is_array($store)?($store['created_at']??null):null;
        if(!is_array($snap)||($snap['provider']??null)!=='andromeda'||!is_int($created)||$created<$cut||!is_array($snap['offers']??null))continue;
        $pages++;$first=$first===null?$created:min($first,$created);$last=$last===null?$created:max($last,$created);
        foreach($snap['offers'] as $o){if(!is_array($o)||($o['supplier_namespace']??null)!=='andromeda_catalog')continue;$id=(string)($o['external_hotel_id']??'');if(preg_match('/^[1-9][0-9]{0,31}$/D',$id)===1)$ids[$id]=max($ids[$id]??0,$created);}
    }
    hmsl_need($invalid===0,'cache_invalid_json');ksort($ids,SORT_NATURAL);
    return ['ids'=>$ids,'meta'=>['installed'=>true,'catalog_identity_keys_30d'=>count($ids),'files_scanned'=>$files,'bytes_scanned'=>$bytes,'live30_pages'=>$pages,'invalid_json'=>$invalid,'first_created_at'=>$first,'last_created_at'=>$last,'set_sha256'=>hmsl_hash(array_keys($ids))]];
}
function hmsl_catalog_reconcile(array $active,array $accepted,array $obs,array $price,array $store,array $cacheIds):array{
    $mapped=[];foreach(array_merge($price,$store) as $id){$id=(int)$id;if(isset($active[$id]))$mapped[$id]=1;}
    foreach($obs as $r){$ns=trim((string)($r['supplier_namespace']??''));$ext=trim((string)($r['external_hotel_id']??''));if($ns===''||$ext==='')continue;$targets=[];foreach(array_keys($accepted[$ns.'|'.$ext]??[]) as $id){$id=(int)$id;if(isset($active[$id]))$targets[$id]=1;}if(count($targets)===1)$mapped[(int)array_key_first($targets)]=1;}
    $persistent=[];
    foreach($accepted as $key=>$targets){if(!str_starts_with($key,'andromeda_catalog|'))continue;$ok=false;foreach(array_keys($targets) as $id)if(isset($mapped[(int)$id])){$ok=true;break;}if($ok)$persistent[substr($key,19)]=1;}
    foreach($obs as $r)if(($r['supplier_namespace']??null)==='andromeda_catalog'&&trim((string)($r['external_hotel_id']??''))!=='')$persistent[trim((string)$r['external_hotel_id'])]=1;
    $cache=[];foreach(array_keys($cacheIds) as $id){$id=(string)$id;if($id!=='')$cache[$id]=1;}
    $union=$persistent+$cache;$acceptedCount=0;$unresolved=0;$collisions=0;
    foreach(array_keys($union) as $ext){$targets=[];foreach(array_keys($accepted['andromeda_catalog|'.$ext]??[]) as $id){$id=(int)$id;if(isset($active[$id]))$targets[$id]=1;}if(count($targets)===1)$acceptedCount++;elseif(count($targets)>1)$collisions++;else$unresolved++;}
    return ['persistent_catalog_identity_keys'=>count($persistent),'retained_cache_catalog_identity_keys'=>count($cache),'overlap_identity_keys'=>count(array_intersect_key($persistent,$cache)),'persistent_only_identity_keys'=>count(array_diff_key($persistent,$cache)),'cache_only_identity_keys'=>count(array_diff_key($cache,$persistent)),'union_catalog_identity_keys'=>count($union),'accepted_exact_identity_keys_now'=>$acceptedCount,'unresolved_identity_keys_now'=>$unresolved,'collision_identity_keys_now'=>$collisions,'persistent_sha256'=>hmsl_hash(array_keys($persistent)),'cache_sha256'=>hmsl_hash(array_keys($cache)),'union_sha256'=>hmsl_hash(array_keys($union))];
}
function hmsl_execute(PDO $db,string $root,?DateTimeImmutable $now=null):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$now=($now??new DateTimeImmutable('now',new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));$cut=$now->modify('-30 days')->format('Y-m-d H:i:s');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $active=[];foreach($db->query('SELECT id,country_name FROM catalog_hotels WHERE is_active=1')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['id'];if($id>0&&!hmsl_excluded((string)$r['country_name']))$active[$id]=1;}hmsl_need($active!==[],'active_empty');
        $accepted=[];foreach($db->query("SELECT supplier_namespace,external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r){$ns=trim((string)$r['supplier_namespace']);$ext=trim((string)$r['external_hotel_id']);$id=(int)$r['local_hotel_id'];if($ns!==''&&$ext!==''&&$id>0)$accepted[$ns.'|'.$ext][$id]=1;}
        $obs=[];$obsMeta=['installed'=>false,'identity_keys_30d'=>0,'first'=>null,'last'=>null];$c=hmsl_cols($db,'andromeda_search_hotel_observations');
        if(isset($c['supplier_namespace'],$c['external_hotel_id'],$c['observed_at_utc'])){$obsMeta['installed']=true;$q=$db->prepare('SELECT supplier_namespace,external_hotel_id,MAX(observed_at_utc) observed_at FROM andromeda_search_hotel_observations WHERE observed_at_utc>=? GROUP BY supplier_namespace,external_hotel_id ORDER BY supplier_namespace,external_hotel_id');$q->execute([$cut]);$obs=$q->fetchAll(PDO::FETCH_ASSOC)?:[];$obsMeta['identity_keys_30d']=count($obs);$q=$db->prepare('SELECT MIN(observed_at_utc),MAX(observed_at_utc) FROM andromeda_search_hotel_observations WHERE observed_at_utc>=?');$q->execute([$cut]);[$obsMeta['first'],$obsMeta['last']]=$q->fetch(PDO::FETCH_NUM)?:[null,null];}
        $price=[];$priceMeta=['installed'=>false,'rows_30d'=>0,'distinct_local_hotels_30d'=>0,'first'=>null,'last'=>null];$c=hmsl_cols($db,'anytour_offer_price_observations');
        if(isset($c['provider'],$c['provider_local_hotel_id'],$c['observed_at'])){$priceMeta['installed']=true;$q=$db->prepare("SELECT DISTINCT provider_local_hotel_id FROM anytour_offer_price_observations WHERE provider='andromeda' AND observed_at>=? AND provider_local_hotel_id IS NOT NULL");$q->execute([$cut]);$price=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)?:[]);$q=$db->prepare("SELECT COUNT(*),COUNT(DISTINCT provider_local_hotel_id),MIN(observed_at),MAX(observed_at) FROM anytour_offer_price_observations WHERE provider='andromeda' AND observed_at>=?");$q->execute([$cut]);$r=$q->fetch(PDO::FETCH_NUM)?:[0,0,null,null];[$priceMeta['rows_30d'],$priceMeta['distinct_local_hotels_30d'],$priceMeta['first'],$priceMeta['last']]=[(int)$r[0],(int)$r[1],$r[2],$r[3]];}
        $store=[];$storeMeta=['installed'=>false,'rows_30d'=>0,'distinct_local_hotels_30d'=>0,'first'=>null,'last'=>null];$c=hmsl_cols($db,'anytour_offers');
        if(isset($c['provider'],$c['legacy_hotel_id'],$c['last_seen_at'])){$storeMeta['installed']=true;$q=$db->prepare("SELECT DISTINCT legacy_hotel_id FROM anytour_offers WHERE provider='andromeda' AND last_seen_at>=? AND legacy_hotel_id IS NOT NULL");$q->execute([$cut]);$store=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)?:[]);$q=$db->prepare("SELECT COUNT(*),COUNT(DISTINCT legacy_hotel_id),MIN(last_seen_at),MAX(last_seen_at) FROM anytour_offers WHERE provider='andromeda' AND last_seen_at>=?");$q->execute([$cut]);$r=$q->fetch(PDO::FETCH_NUM)?:[0,0,null,null];[$storeMeta['rows_30d'],$storeMeta['distinct_local_hotels_30d'],$storeMeta['first'],$storeMeta['last']]=[(int)$r[0],(int)$r[1],$r[2],$r[3]];}
        $cache=hmsl_cache_catalog($root,$now->getTimestamp()-30*86400);$rec=hmsl_reconcile($active,$accepted,$obs,$price,$store);$rec['catalog_identity_reconciliation']=hmsl_catalog_reconcile($active,$accepted,$obs,$price,$store,$cache['ids']);$db->rollBack();
        return ['schema_version'=>1,'source'=>'hotel-match-samo-live30-persistence-reconcile-v1','state'=>'completed_read_only_samo_live30_persistence_reconcile','generated_at_utc'=>$now->format('c'),'window_start_utc'=>$cut,'storage'=>['unresolved_observations'=>$obsMeta,'price_history'=>$priceMeta,'offer_store'=>$storeMeta,'retained_search_cache'=>$cache['meta']],'reconciliation'=>$rec,'historical_completeness_proven'=>false,'unknown_history_gap'=>true,'limitations'=>['observation_journal'=>'records only rows unresolved at capture time','price_history'=>'records only mapped rows that reached persistence while history storage existed','offer_store'=>'mutable offer storage, not an immutable search journal','unique_count'=>'do not add unresolved identity keys to mapped local hotels as an exact unique-hotel count'],'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $r=hmsl_reconcile([1=>1,2=>1,3=>1],['andromeda_catalog|A'=>[1=>1],'operator_115|B'=>[2=>1]],[['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'A'],['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'U'],['supplier_namespace'=>'operator_115','external_hotel_id'=>'B']],[1,3],[1,2]);
        hmsl_need($r['mapped_local_hotels']['accounted_30d']===3,'mapped_union');hmsl_need($r['observation_journal']['unresolved_identity_keys']===1,'unresolved');hmsl_need($r['unique_hotel_accounting_range']['lower_bound_mapped_local_hotels']===3&&$r['unique_hotel_accounting_range']['upper_bound_if_every_unresolved_identity_is_distinct_new_hotel']===4,'range');echo "HMSLPR_SELFTEST_OK\n";exit;
    }
    hmsl_need($mode==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');hmsl_need(is_dir($root),'root');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');echo hmsl_json(hmsl_execute(v2_data_db(),$root))."\n";
}
