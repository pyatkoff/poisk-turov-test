<?php
// Executed with the checked existing ANEX registry, never as a public route.
error_reporting(0); ob_start(); $pdo=null; $committed=false; $phase='request';
function batch_canonical($value) {
    if (is_array($value)) {
        if ($value !== [] && array_keys($value) !== range(0, count($value)-1)) ksort($value, SORT_STRING);
        foreach ($value as &$item) $item=batch_ordered($item);
        unset($item);
    }
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}
function batch_ordered($value) {
    if (!is_array($value)) return $value;
    if ($value !== [] && array_keys($value) !== range(0, count($value)-1)) ksort($value, SORT_STRING);
    foreach ($value as &$item) $item=batch_ordered($item);
    unset($item); return $value;
}
function norm_text($value) {
    $value=mb_strtolower((string)$value,'UTF-8');
    $value=strtr($value,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    preg_match_all('/[\p{L}\p{N}]+/u',$value,$matches); return implode(' ',$matches[0]);
}
function name_key($value) {
    $value=preg_replace('/\s*\(\s*(?:ex|ех)\s*\.?\s+[^()]+\)\s*$/ui','',(string)$value);
    $parts=array_values(array_filter(explode(' ',norm_text($value)),fn($x)=>$x!==''&&$x!=='hotel'));
    sort($parts,SORT_STRING); return implode(' ',$parts);
}
function batch_rows($pdo) {
    return $pdo->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
}
function batch_partition($rows,$wanted) {
    $other=[];$selected=[];
    foreach($rows as $row) {
        $key=$row['supplier_namespace'].':'.$row['external_hotel_id'];
        if($row['supplier_namespace']==='andromeda_catalog'&&isset($wanted[(string)$row['external_hotel_id']])) $selected[(string)$row['external_hotel_id']]=$row;
        else $other[$key]=$row;
    }
    return [$selected,$other];
}
try {
    $raw=file_get_contents('php://stdin',false,null,0,1000001);
    if(!is_string($raw)||strlen($raw)>1000000)throw new RuntimeException();
    $req=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
    $requestHash=hash('sha256',batch_canonical($req));
    // Binds every source, target, original digest and geography, not just a count.
    if($requestHash!=='2a83b140e1ba36ad2d41168cd67abb1b3f05329e29793af41f5f5d23b7905024')throw new RuntimeException();
    $root=realpath(getcwd()); if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
    $helper=realpath($root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php'));
    if(!$helper||strpos($helper,$root.DIRECTORY_SEPARATOR)!==0)throw new RuntimeException();
    require_once $helper;
    $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=10');$pdo->beginTransaction();$phase='current_guards';
    $wanted=[];$bridgeIds=[];
    foreach($req['rows'] as $r){$wanted[$r['external_hotel_id']]=$r;foreach($r['anex_bridges'] as $b)$bridgeIds[(int)$b['anex_id']]=true;}
    $all=$pdo->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    [$index,$preserve]=batch_partition($all,$wanted);
    // Lock ANEX manual/exclusion decisions in the established observation-first order.
    $ids=array_keys($bridgeIds);sort($ids,SORT_NUMERIC);
    if($ids){$marks=implode(',',array_fill(0,count($ids),'?'));
        foreach(['anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','anex_review_pair_exclusions'] as $table){
            $s=$pdo->prepare('SELECT anex_hotel_id FROM '.$table.' WHERE anex_hotel_id IN ('.$marks.') ORDER BY anex_hotel_id FOR UPDATE');$s->execute($ids);$s->fetchAll();
        }
    }
    $hotels=$pdo->query('SELECT id,name,country_id,region_name,subregion_name,is_active FROM catalog_hotels WHERE country_id IN (1,4) ORDER BY id LIMIT 30001 FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $aliases=$pdo->query('SELECT a.hotel_id,a.alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id IN (1,4) AND h.is_active=1 ORDER BY a.hotel_id,a.alias LIMIT 100001 FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    if(count($hotels)>=30001||count($aliases)>=100001)throw new RuntimeException();
    $hotelBy=[];$nameIndex=[1=>[],4=>[]];
    foreach($hotels as $h){$id=(int)$h['id'];$hotelBy[$id]=$h;if((int)$h['is_active']===1){$k=name_key($h['name']);if($k!=='')$nameIndex[(int)$h['country_id']][$k][$id]=true;}}
    foreach($aliases as $a){$k=name_key($a['alias']);if($k!=='')$nameIndex[(int)$a['country_id']][$k][(int)$a['hotel_id']]=true;}
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $update=$pdo->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=? AND catalog_sha256=?");
    $updated=0;$newHashes=[];
    foreach($wanted as $key=>$r){
        $external=(string)$key;$old=$index[$key]??null;
        if(!$old||$old['decision_status']!=='pending'||$old['local_hotel_id']!==null||$old['evidence_sha256']!==$r['expected_evidence_sha256']||hash('sha256',$old['evidence_json'])!==$r['expected_evidence_sha256']||$old['catalog_sha256']!==$r['expected_catalog_sha256'])throw new RuntimeException();
        $prior=json_decode($old['evidence_json'],true,64,JSON_THROW_ON_ERROR);$source=$prior['source']??null;
        if(!is_array($source)||(string)($source['id']??'')!==$external||batch_canonical($source)!==batch_canonical($r['source'])||hash('sha256',batch_canonical($source))!==$r['source_row_sha256']||($source['stateKey']??null)!==([1=>3,4=>5][$r['country_id']]))throw new RuntimeException();
        $target=$hotelBy[$r['local_hotel_id']]??null;
        if(!$target||(int)$target['is_active']!==1||(int)$target['country_id']!==$r['country_id'])throw new RuntimeException();
        foreach(['name','region_name','subregion_name'] as $field)if(norm_text($r['expected_local'][$field]??'')!==norm_text($target[$field]??''))throw new RuntimeException();
        $candidates=[];
        foreach(array_unique(array_filter([name_key($source['name']),name_key($source['lName']??'')])) as $name)foreach(array_keys($nameIndex[$r['country_id']][$name]??[]) as $id)$candidates[$id]=true;
        if(count($candidates)!==1||!isset($candidates[$r['local_hotel_id']]))throw new RuntimeException();
        $geo=$r['geography'];
        if($geo['status']!=='supported'||$geo['supplier_town_id']!==$source['townKey']||norm_text($geo['supplier_town'])!==norm_text($source['town']))throw new RuntimeException();
        $places=array_filter([norm_text($geo['supplier_town']),norm_text($geo['supplier_parent']??'')]);
        if(!array_intersect($places,array_filter([norm_text($target['region_name']),norm_text($target['subregion_name'])])))throw new RuntimeException();
        foreach($r['anex_bridges'] as $bridge)if($registry->resolve('anex_online',$bridge['anex_id'],'preview')!==$r['local_hotel_id'])throw new RuntimeException();
        $evidence=['prior_evidence'=>$prior,'source'=>'validated_full_catalogue_geography_batch_20260910','operation_id'=>$req['operation_id'],'source_report_sha256'=>$req['source_report_sha256'],'source_row_sha256'=>$r['source_row_sha256'],'target'=>$r['expected_local'],'geography'=>$geo,'anex_bridges'=>$r['anex_bridges'],'category_difference'=>$r['category_difference']];
        $ejson=batch_canonical($evidence);$ehash=hash('sha256',$ejson);$newHashes[$key]=$ehash;
        $update->execute([$r['local_hotel_id'],$ehash,$ejson,$external,$r['expected_evidence_sha256'],$r['expected_catalog_sha256']]);if($update->rowCount()!==1)throw new RuntimeException();++$updated;
    }
    [$selected,$remaining]=batch_partition(batch_rows($pdo),$wanted);
    if($remaining!==$preserve||count($selected)!==92||$updated!==92)throw new RuntimeException();
    $pdo->commit();$committed=true;$phase='post_commit_readback';
    $pdo->exec('START TRANSACTION READ ONLY');
    $after=batch_rows($pdo);[$selected,$remaining]=batch_partition($after,$wanted);
    if($remaining!==$preserve||count($after)!==count($all)||count($selected)!==92)throw new RuntimeException();
    $read=[];
    foreach($selected as $key=>$row){if($row['decision_status']!=='accepted'||(int)$row['local_hotel_id']!==$wanted[$key]['local_hotel_id']||$row['evidence_sha256']!==$newHashes[$key]||hash('sha256',$row['evidence_json'])!==$newHashes[$key])throw new RuntimeException();
        $read[]=['external_hotel_id'=>(string)$key,'local_hotel_id'=>(int)$row['local_hotel_id'],'decision_status'=>'accepted','evidence_sha256'=>$row['evidence_sha256']];}
    $counts=[];$andromedaLocal=[];
    foreach($after as $row)if($row['supplier_namespace']==='andromeda_catalog'){$s=$row['decision_status'];$counts[$s]=($counts[$s]??0)+1;if($s==='accepted')$andromedaLocal[(int)$row['local_hotel_id']]=true;}
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$anexLocal=[];
    $anexIds=$pdo->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN);
    foreach($anexIds as $id){$target=$registry->resolve('anex_online',$id,'preview');if($target!==null)$anexLocal[$target]=true;}
    $triple=count(array_intersect_key($anexLocal,$andromedaLocal));
    $coverage=['anex_links'=>$registry->count(),'andromeda_links'=>$counts['accepted']??0,'anex_unique_local'=>count($anexLocal),'andromeda_unique_local'=>count($andromedaLocal),'all_three'=>$triple,'anex_tourvisor_only'=>count($anexLocal)-$triple,'andromeda_tourvisor_only'=>count($andromedaLocal)-$triple,'exactly_two'=>count($anexLocal)+count($andromedaLocal)-2*$triple];
    $pdo->exec('ROLLBACK');
    $out=['status'=>'accepted','operation_id'=>$req['operation_id'],'request_sha256'=>$requestHash,'input_count'=>92,'updated'=>$updated,'readback_verified'=>true,'other_identities_unchanged'=>true,'other_identity_count'=>count($preserve),'other_identity_sha256'=>hash('sha256',batch_canonical($preserve)),'rows'=>$read,'counts'=>$counts,'live_coverage'=>$coverage,'supplier_calls'=>0];
}catch(Throwable $error){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$out=['status'=>'failed','phase'=>$phase,'database_transaction_rolled_back'=>!$committed,'commit_completed'=>$committed,'supplier_calls'=>0];}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
exit($out['status']==='accepted'?0:1);
