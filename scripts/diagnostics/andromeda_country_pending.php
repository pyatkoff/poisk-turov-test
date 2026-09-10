<?php
// New saved-pending promotion policy. The completed ce_plan/capture/import remain immutable.
const CE_PENDING_OPERATION = 'andromeda-1759-country6-pending-20260910-v1';
const CE_PENDING_REQUEST = '9e89e6585b01205ac9a0b1bc47f91c99585eb8cc8863fc89e3b463c3b3d9d687';
function ce_pending_forms($value): array {
    $value=(string)$value;
    if(preg_match('/\s*\(\s*(?:ex\.?\s+|ex\.\s*|ех\.?\s+|ех\.\s*)([^()]+)\)\s*$/ui',$value,$m,PREG_OFFSET_CAPTURE))
        return [trim(substr($value,0,$m[0][1])),trim($m[1][0])];
    return [$value];
}
function ce_pending_norm($value): string {return str_replace('й','и',ce_norm($value));}
function ce_pending_name($value): string {
    $stop=['hotel','hotels','resort','resorts','spa','the','and'];
    $words=array_values(array_diff(explode(' ',ce_pending_norm($value)),$stop,['']));
    sort($words,SORT_STRING);return implode(' ',$words);
}
function ce_pending_names($value): array {
    return array_values(array_unique(array_filter(array_map('ce_pending_name',ce_pending_forms($value)))));
}
function ce_pending_place($value): string {
    $value=preg_replace('/\s+(?:о\.|остров|island)\s*$/ui','',(string)$value);
    return str_replace(' ','',ce_pending_norm($value));
}
function ce_pending_qualifiers($value): array {
    $words=explode(' ',ce_pending_name(ce_pending_forms($value)[0]));
    $result=array_values(array_intersect($words,['annex','adults','only','family','beach','garden','palace','park','harem','villas','apart','apartments','suites','wing']));
    sort($result,SORT_STRING);return array_values(array_unique($result));
}
function ce_pending_geo(array $source,array $target,array $towns): ?array {
    $town=$towns[(string)($source['townKey']??'')]??null;
    if(!$town||(string)($town['state']??'')!==(string)$source['stateKey']||ce_pending_place($town['name']??'')!==ce_pending_place($source['town']??''))return null;
    $sourceKeys=array_values(array_filter([ce_pending_place($town['name']??''),ce_pending_place($town['lName']??'')]));
    $region=ce_pending_place($target['region_name']??'');$sub=ce_pending_place($target['subregion_name']??'');$country=ce_pending_place($source['state']??'');
    $kind=null;
    // Exact specific town beats differing broad supplier sales-region taxonomy.
    if($sub!==''&&in_array($sub,$sourceKeys,true))$kind='exact_specific_town';
    else {
        $known=[];foreach($towns as $t)if((string)($t['state']??'')===(string)$source['stateKey']&&$sub!==''&&in_array($sub,[ce_pending_place($t['name']??''),ce_pending_place($t['lName']??'')],true))$known[]=$t;
        if($region!==''&&$region!==$country&&in_array($region,$sourceKeys,true)){
            if($known&&!array_filter($known,fn($t)=>in_array($region,[ce_pending_place($t['RegionName']??''),ce_pending_place($t['RegionLName']??'')],true)))return null;
            $kind='exact_region_town';
        }elseif($region!==''&&$region!==$country&&in_array($region,[ce_pending_place($town['RegionName']??''),ce_pending_place($town['RegionLName']??'')],true)){
            foreach($known as $t)if(!empty($t['Region'])&&!empty($town['Region'])&&(string)$t['Region']!==(string)$town['Region'])return null;
            $kind='official_parent';
        }
    }
    return $kind===null?null:['kind'=>$kind,'town_id'=>(string)$town['id'],'local_region'=>$target['region_name'],'local_subregion'=>$target['subregion_name']];
}
function ce_pending_target(array $hotel): array {
    return ['id'=>(int)$hotel['id'],'name'=>$hotel['name'],'country_id'=>(int)$hotel['country_id'],'category'=>(int)$hotel['category'],'region_name'=>$hotel['region_name'],'subregion_name'=>$hotel['subregion_name']];
}
function ce_pending_rows(array $data,array $plan,array $local): array {
    if(($local['complete']??false)!==true||(int)$local['country_id']!==(int)$data['country_id']||ce_hash($data['catalog'])!==$plan['catalog_sha256'])throw new RuntimeException('pending_country_scope');
    $hotels=[];$index=[];
    foreach($local['hotels'] as $h){$id=(int)$h['id'];if($id<1||isset($hotels[$id])||(int)$h['country_id']!==(int)$data['country_id']||(int)$h['is_active']!==1)throw new RuntimeException('pending_local_identity');$hotels[$id]=$h;foreach(ce_pending_names($h['name']) as $key)$index[$key][$id]=true;}
    foreach($local['aliases'] as $a){$id=(int)$a['hotel_id'];if(!isset($hotels[$id]))throw new RuntimeException('pending_orphan_alias');foreach(ce_pending_names($a['alias']) as $key)$index[$key][$id]=true;}
    $towns=[];$placeWords=explode(' ',ce_pending_norm($data['country_name']));
    foreach($data['catalog']['payload']['TOWNTO'] as $t){if(isset($towns[(string)$t['id']]))throw new RuntimeException('pending_duplicate_town');$towns[(string)$t['id']]=$t;foreach(['name','lName','RegionName','RegionLName'] as $key)$placeWords=array_merge($placeWords,explode(' ',ce_pending_norm($t[$key]??'')));}
    $generic=explode(' ','beach garden palace park plaza grand royal boutique city central pool view sea inn apartment apartments villa villas suite suites residence residences house guest guesthouse club luxury retreat stay new old by at on of in for island beachfront waterfront country king queen prince princess');
    $nonDistinct=array_fill_keys(array_merge($generic,$placeWords),true);$selected=[];$seen=[];
    foreach($plan['rows'] as $row){
        $id=(string)$row['external_hotel_id'];if(isset($seen[$id]))throw new RuntimeException('pending_duplicate_source');$seen[$id]=true;if($row['decision_status']!=='pending'||$row['local_hotel_id']!==null)continue;
        $e=json_decode($row['evidence_json'],true,64,JSON_THROW_ON_ERROR);$source=$e['source'];if((string)$source['id']!==$id||(string)$source['stateKey']!==(string)$data['supplier_country_id'])continue;
        $sourceKeys=array_values(array_unique(array_merge(ce_pending_names($source['name']),ce_pending_names($source['lName']??''))));$candidates=[];$matches=[];
        foreach($sourceKeys as $key)foreach(array_keys($index[$key]??[]) as $localId){$candidates[$localId]=true;$matches[$key]=true;}
        if(count($candidates)!==1)continue;$target=$hotels[array_key_first($candidates)];
        if(ce_pending_qualifiers($source['name'])!==ce_pending_qualifiers($target['name'])||!ctype_digit((string)($source['star']??''))||(int)$source['star']<1||(int)$source['star']!==(int)$target['category'])continue;
        $distinct=false;foreach(array_keys($matches) as $key)foreach(explode(' ',$key) as $word)if(!isset($nonDistinct[$word])&&mb_strlen($word,'UTF-8')>=5&&preg_match('/\p{L}/u',$word))$distinct=true;
        if(!$distinct)continue;$geo=ce_pending_geo($source,$target,$towns);if($geo===null)continue;
        $keys=array_keys($matches);sort($keys,SORT_STRING);
        $selected[]=['external_hotel_id'=>$id,'local_hotel_id'=>(int)$target['id'],'expected_evidence_sha256'=>hash('sha256',$row['evidence_json']),'target'=>ce_pending_target($target),'match_keys'=>$keys,'geography'=>$geo,'previous_reason'=>$e['reason']];
    }
    usort($selected,fn($a,$b)=>strlen($a['external_hotel_id'])<=>strlen($b['external_hotel_id'])?:strcmp($a['external_hotel_id'],$b['external_hotel_id']));return $selected;
}
function ce_pending_request(array $bundle): array {
    $actual=array_keys($bundle);$expected=array_keys(CE_COUNTRIES);sort($actual);sort($expected);if($actual!==$expected)throw new RuntimeException('pending_country_allowlist');$countries=[];$count=0;
    foreach(CE_COUNTRIES as $slug=>$label){$value=$bundle[$slug];$data=$value['capture'];$plan=$value['plan'];$rows=ce_pending_rows($data,$plan,$data['local']);$count+=count($rows);$countries[$slug]=['country_id'=>(int)$data['country_id'],'supplier_country_id'=>(int)$data['supplier_country_id'],'capture_sha256'=>ce_hash($data),'plan_sha256'=>ce_hash($plan),'catalog_sha256'=>$plan['catalog_sha256'],'rows'=>$rows];}
    return ['operation_id'=>CE_PENDING_OPERATION,'policy'=>'explicit_former_names_full_competition_and_official_geography_v1','supplier_calls'=>0,'count'=>$count,'countries'=>$countries];
}
function ce_pending_import(PDO $db,array $request,array $bundle): array {
    if(ce_hash($request)!==CE_PENDING_REQUEST||$request['operation_id']!==CE_PENDING_OPERATION||ce_hash(ce_pending_request($bundle))!==CE_PENDING_REQUEST)throw new RuntimeException('pending_manifest');
    $engine=$db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities'")->fetchColumn();if(strtoupper((string)$engine)!=='INNODB')throw new RuntimeException('pending_schema');
    $db->exec('SET SESSION innodb_lock_wait_timeout=10');$db->beginTransaction();$committed=false;
    try {
        $all=$db->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 100001 FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);if(count($all)>100000)throw new RuntimeException('identity_limit');
        $wanted=[];$original=[];$current=[];
        foreach($request['countries'] as $slug=>$country){foreach($country['rows'] as $r){if(isset($wanted[$r['external_hotel_id']]))throw new RuntimeException('cross_country_duplicate');$wanted[$r['external_hotel_id']]=[$r,$country];}
            if(!$country['rows'])continue;$data=$bundle[$slug]['capture'];$plan=$bundle[$slug]['plan'];$local=ce_local($db,$country['country_id'],true);
            foreach(ce_pending_rows($data,$plan,$local) as $r)$current[$r['external_hotel_id']]=$r;
            foreach($plan['rows'] as $r)$original[$r['external_hotel_id']]=$r;
        }
        $oldIndex=[];$preserve=[];foreach($all as $row){$key=$row['supplier_namespace'].':'.$row['external_hotel_id'];if($row['supplier_namespace']==='andromeda_catalog'&&isset($wanted[$row['external_hotel_id']]))$oldIndex[$row['external_hotel_id']]=$row;else$preserve[$key]=ce_hash($row);}
        $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_json=?,evidence_sha256=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND local_hotel_id IS NULL AND decision_status='pending' AND evidence_sha256=? AND catalog_sha256=?");$new=[];
        foreach($wanted as $key=>[$r,$country]){$id=(string)$key;$old=$oldIndex[$key]??null;$saved=$original[$key]??null;
            if(!$old||!$saved||!isset($current[$key])||ce_hash($current[$key])!==ce_hash($r)||$old['decision_status']!=='pending'||$old['local_hotel_id']!==null||$old['evidence_json']!==$saved['evidence_json']||hash('sha256',$old['evidence_json'])!==$r['expected_evidence_sha256']||$old['evidence_sha256']!==$r['expected_evidence_sha256']||$old['catalog_sha256']!==$country['catalog_sha256'])throw new RuntimeException('pending_current_guard');
            $evidence=['operation_id'=>CE_PENDING_OPERATION,'request_sha256'=>CE_PENDING_REQUEST,'prior_evidence'=>json_decode($old['evidence_json'],true,64,JSON_THROW_ON_ERROR),'proof'=>$r];$json=ce_json($evidence);$hash=hash('sha256',$json);
            $update->execute([$r['local_hotel_id'],$json,$hash,$id,$r['expected_evidence_sha256'],$country['catalog_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('pending_update');
            $new[$id]=['external_hotel_id'=>$id,'local_hotel_id'=>$r['local_hotel_id'],'country_id'=>$country['country_id'],'decision_status'=>'accepted','evidence_sha256'=>$hash];
        }
        $after=ce_existing($db,false);if(count($after)!==count($all))throw new RuntimeException('pending_count');foreach($preserve as $key=>$hash)if(($after[$key]??null)!==$hash)throw new RuntimeException('pending_preservation');
        $db->commit();$committed=true;$read=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
        foreach($new as $id=>$r){$read->execute([(string)$id]);$v=$read->fetch(PDO::FETCH_ASSOC);if(!$v||(int)$v['local_hotel_id']!==$r['local_hotel_id']||$v['decision_status']!=='accepted'||$v['evidence_sha256']!==$r['evidence_sha256'])throw new RuntimeException('pending_readback');}
        $counts=$db->query("SELECT decision_status,COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['status'=>'accepted','operation_id'=>CE_PENDING_OPERATION,'request_sha256'=>CE_PENDING_REQUEST,'updated'=>count($new),'readback_verified'=>true,'other_identities_unchanged'=>true,'other_count'=>count($preserve),'preservation_sha256'=>ce_hash($preserve),'rows'=>array_values($new),'counts'=>$counts,'live_coverage'=>ce_coverage($db),'supplier_calls'=>0];
    }catch(Throwable $e){if(!$committed&&$db->inTransaction())$db->rollBack();throw new RuntimeException($committed?'committed_readback_unconfirmed':'transaction_rolled_back');}
}
function ce_pending_execute(array $request): array {
    if(PHP_SAPI!=='cli'||ce_hash($request)!==CE_PENDING_REQUEST)throw new RuntimeException('pending_manifest');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    $config=require $root.'/_preview/search3-anex-candidate/.andromeda-private.php';$private=realpath(dirname($config['catalog_path']));if(!$private)throw new RuntimeException('private');$stage=$private.'/'.CE_PENDING_OPERATION;
    if(is_dir($stage)||!mkdir($stage,0700))throw new RuntimeException('prior_pending_operation_do_not_replay');ce_save($stage.'/reservation.json',['operation_id'=>CE_PENDING_OPERATION,'request_sha256'=>CE_PENDING_REQUEST]);$bundle=[];
    foreach(CE_COUNTRIES as $slug=>$name){$old=$private.'/'.CE_OPERATION.'-'.$slug;$done=ce_read($old.'/result.json');if($done['status']!=='imported'||$done['readback_verified']!==true)throw new RuntimeException('incomplete_original_import');$bundle[$slug]=['capture'=>ce_read($old.'/capture.json'),'plan'=>ce_read($old.'/plan.json')];}
    $helper=realpath($root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php'));if(!$helper||strpos($helper,$root.'/')!==0)throw new RuntimeException('db_helper');require_once $helper;
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$result=ce_pending_import($db,$request,$bundle);ce_save($stage.'/result.json',$result);return $result;
}
