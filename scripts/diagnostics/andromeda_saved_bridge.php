<?php
/** Loaded after the existing registry and CE_LIBRARY_ONLY country helpers. */
const BR_OPERATION='andromeda-1759-maldives-bridge61-20260910-v1';
const BR_REQUEST='941ad3dccbdd4d4c374bcf572016cc1234a535bfe56a5bcacbf1df99e8eac2b4';
function br_payload(array $request): void {
    if(ce_hash($request)!==BR_REQUEST||($request['operation_id']??'')!==BR_OPERATION||count($request['rows']??[])!==61)throw new RuntimeException('reviewed_request_changed');
}
function br_import(PDO $db,array $request,array $data,array $savedPlan): array {
    br_payload($request);
    if((int)$data['country_id']!==8||(int)$data['supplier_country_id']!==73)throw new RuntimeException('country_scope');
    $engine=$db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities'")->fetchColumn();
    if(strtoupper((string)$engine)!=='INNODB')throw new RuntimeException('schema');
    $db->exec('SET SESSION innodb_lock_wait_timeout=10');$db->beginTransaction();$committed=false;
    try {
        $all=$db->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 100001 FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        if(count($all)>100000)throw new RuntimeException('identity_limit');
        $wanted=[];$anexIds=[];foreach($request['rows'] as $r){$wanted[(string)$r['external_hotel_id']]=$r;foreach($r['anex_bridges'] as $b)$anexIds[(int)$b['id']]=true;}
        $index=[];$preserve=[];foreach($all as $old){$key=$old['supplier_namespace'].':'.$old['external_hotel_id'];$index[$key]=$old;if($old['supplier_namespace']!=='andromeda_catalog'||!isset($wanted[(string)$old['external_hotel_id']]))$preserve[$key]=ce_hash($old);}
        $ids=array_keys($anexIds);sort($ids,SORT_NUMERIC);$marks=implode(',',array_fill(0,count($ids),'?'));
        foreach(['anex_search_hotel_observations','anex_hotel_decisions','anex_hotel_search_mappings','anex_review_pair_exclusions'] as $table){
            $s=$db->prepare('SELECT anex_hotel_id FROM '.$table.' WHERE anex_hotel_id IN ('.$marks.') ORDER BY anex_hotel_id FOR UPDATE');$s->execute($ids);$s->fetchAll();
        }
        $local=ce_local($db,8,true);$current=ce_plan($data,$local);$currentRows=[];$original=[];$hotels=[];$towns=[];
        foreach($current['rows'] as $r)$currentRows[(string)$r['external_hotel_id']]=$r;
        foreach($savedPlan['rows'] as $r)$original[(string)$r['external_hotel_id']]=$r;
        foreach($local['hotels'] as $h)$hotels[(int)$h['id']]=$h;
        foreach($data['catalog']['payload']['TOWNTO'] as $t)$towns[(string)$t['id']]=$t;
        $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);$updates=[];
        foreach($wanted as $key=>$r){
            $external=(string)$key;$old=$index['andromeda_catalog:'.$external]??null;$saved=$original[$key]??null;
            if(!$old||!$saved||$old['decision_status']!=='pending'||$old['local_hotel_id']!==null||$old['catalog_sha256']!==$request['catalog_sha256']||$old['evidence_sha256']!==$r['expected_evidence_sha256']||hash('sha256',$old['evidence_json'])!==$r['expected_evidence_sha256']||$old['evidence_json']!==$saved['evidence_json'])throw new RuntimeException('stale_identity');
            $prior=json_decode($old['evidence_json'],true,64,JSON_THROW_ON_ERROR);
            $now=json_decode($currentRows[$key]['evidence_json']??'null',true,64,JSON_THROW_ON_ERROR);
            if(($prior['reason']??'')!=='geography_unknown'||($now['candidate_ids']??[])!==[$r['local_hotel_id']]||ce_hash($prior['source'])!==ce_hash($r['source'])||ce_hash($now['source'])!==ce_hash($r['source']))throw new RuntimeException('current_candidate_changed');
            $target=$hotels[$r['local_hotel_id']]??null;
            if(!$target||(int)$target['country_id']!==8||(int)$target['is_active']!==1||$target['name']!==$r['target_name']||ce_norm($target['region_name'])!==ce_norm('Мальдивы')||ce_norm($target['subregion_name'])!=='')throw new RuntimeException('target_geography_changed');
            $source=$r['source'];$town=$towns[(string)$source['townKey']]??null;
            if(!$town||(string)$town['state']!=='73'||(string)$source['stateKey']!=='73'||ce_hash($town)!==ce_hash($r['official_town'])||ce_norm($town['name'])!==ce_norm($source['town'])||ce_norm($source['town'])===ce_norm('Мальдивы'))throw new RuntimeException('official_geography_changed');
            $sourceNames=array_filter([ce_name($source['name']),ce_name($source['lName']??'')]);
            foreach($r['anex_bridges'] as $b){
                if($registry->resolve('anex_online',(string)$b['id'],'preview')!==$r['local_hotel_id']||$b['catalog_hotel_id']!==$r['local_hotel_id']||ce_norm($b['country'])!==ce_norm('Мальдивы')||ce_norm($b['town'])!==ce_norm($source['town'])||!array_intersect($sourceNames,array_filter([ce_name($b['name']),ce_name($b['alternate_name'])])))throw new RuntimeException('anex_bridge_changed');
            }
            $evidence=['prior_evidence'=>$prior,'source'=>'accepted_anex_same_atoll_bridge_20260910','operation_id'=>BR_OPERATION,'request_sha256'=>BR_REQUEST,'anex_bridges'=>$r['anex_bridges'],'official_town'=>$town,'target_name'=>$r['target_name'],'source_pending_sha256'=>$request['source_pending_sha256']];
            $json=ce_json($evidence);$updates[$key]=['external_hotel_id'=>$external,'local_hotel_id'=>$r['local_hotel_id'],'decision_status'=>'accepted','evidence_sha256'=>hash('sha256',$json),'evidence_json'=>$json,'previous_evidence_sha256'=>$r['expected_evidence_sha256']];
        }
        if(count($updates)!==61)throw new RuntimeException('scope');
        $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=? AND catalog_sha256=?");
        foreach($updates as $r){$update->execute([$r['local_hotel_id'],$r['evidence_sha256'],$r['evidence_json'],$r['external_hotel_id'],$r['previous_evidence_sha256'],$request['catalog_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('conditional_update');}
        $after=ce_existing($db,false);if(count($after)!==count($all))throw new RuntimeException('row_count');foreach($preserve as $key=>$hash)if(($after[$key]??null)!==$hash)throw new RuntimeException('preservation');
        $db->commit();$committed=true;
        $query=$db->prepare("SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");$rows=[];
        foreach($updates as $r){$query->execute([$r['external_hotel_id']]);$got=$query->fetch(PDO::FETCH_ASSOC);if(!$got||(string)$got['external_hotel_id']!==$r['external_hotel_id']||(int)$got['local_hotel_id']!==$r['local_hotel_id']||$got['decision_status']!=='accepted'||$got['evidence_sha256']!==$r['evidence_sha256'])throw new RuntimeException('post_commit_readback');$got['external_hotel_id']=(string)$got['external_hotel_id'];$got['local_hotel_id']=(int)$got['local_hotel_id'];$rows[]=$got;}
        $counts=$db->query("SELECT decision_status,COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['status'=>'accepted','operation_id'=>BR_OPERATION,'request_sha256'=>BR_REQUEST,'updated'=>61,'readback_verified'=>true,'other_identities_unchanged'=>true,'preserved_count'=>count($preserve),'preservation_sha256'=>ce_hash($preserve),'rows'=>$rows,'current_andromeda_counts'=>$counts,'live_coverage'=>ce_coverage($db),'supplier_calls'=>0];
    }catch(Throwable $e){if(!$committed&&$db->inTransaction())$db->rollBack();throw new RuntimeException($committed?'committed_readback_unknown':'transaction_rolled_back');}
}
if(!defined('BR_LIBRARY_ONLY')){
    error_reporting(0);ob_start();$lock=null;
    try {
        $raw=file_get_contents('php://stdin',false,null,0,150001);if(!is_string($raw)||strlen($raw)>150000)throw new RuntimeException();$request=json_decode($raw,true,64,JSON_THROW_ON_ERROR);br_payload($request);
        $root=realpath(getcwd());if(PHP_SAPI!=='cli'||!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
        $config=require $root.'/_preview/search3-anex-candidate/.andromeda-private.php';$private=realpath(dirname($config['catalog_path']));if(!$private)throw new RuntimeException();
        $stage=$private.'/'.CE_OPERATION.'-maldives';$data=ce_read($stage.'/capture.json');$plan=ce_read($stage.'/plan.json');$priorResult=ce_read($stage.'/result.json');
        if($priorResult['status']!=='imported'||$priorResult['readback_verified']!==true||ce_hash($data)!==$request['saved_capture_sha256']||ce_hash($plan)!==$request['saved_plan_sha256']||ce_hash($data['catalog'])!==$request['catalog_sha256'])throw new RuntimeException();
        $operation=$private.'/'.BR_OPERATION;$lock=fopen($operation.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();
        if(is_dir($operation)||!mkdir($operation,0700))throw new RuntimeException('prior_bridge_operation_do_not_replay');
        ce_save($operation.'/reservation.json',['state'=>'reserved','operation_id'=>BR_OPERATION,'request_sha256'=>BR_REQUEST]);
        $helper=realpath($root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php'));if(!$helper||strpos($helper,$root.'/')!==0)throw new RuntimeException();require_once $helper;
        $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$result=br_import($db,$request,$data,$plan);ce_save($operation.'/result.json',$result);
    }catch(Throwable $e){$result=['status'=>'failed_or_unknown','operation_id'=>BR_OPERATION,'retry'=>false,'supplier_calls'=>0];}
    if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo ce_json($result);
}
