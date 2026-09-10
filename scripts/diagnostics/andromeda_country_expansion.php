<?php
/** Confined six-country catalogue expansion. CLI only; never activates public countries. */
const CE_OPERATION = 'andromeda-1759-country6-20260910-v1';
const CE_COUNTRIES = ['uae'=>'ОАЭ','thailand'=>'Таиланд','vietnam'=>'Вьетнам','sri-lanka'=>'Шри-Ланка','maldives'=>'Мальдивы','cuba'=>'Куба'];
function ce_order($v) {
    if(!is_array($v))return $v;
    if($v!==[] && array_keys($v)!==range(0,count($v)-1))ksort($v,SORT_STRING);
    foreach($v as &$item)$item=ce_order($item);unset($item);return $v;
}
function ce_json($v) {return json_encode(ce_order($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function ce_hash($v) {return hash('sha256',ce_json($v));}
function ce_save($path,$value) {
    $raw=ce_json($value);if(strlen($raw)>16000000)throw new RuntimeException('private_record_limit');
    $f=fopen($path,'x');if(!$f)throw new RuntimeException('existing_operation_record');chmod($path,0600);
    try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('private_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('private_sync');}finally{fclose($f);}
}
function ce_read($path) {if(is_link($path)||!is_file($path)||filesize($path)>16000000)throw new RuntimeException('private_record');return json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR);}
function ce_id($v) {return (is_int($v)||is_string($v))&&preg_match('/^[1-9][0-9]{0,31}$/D',(string)$v);}
function ce_norm($v) {
    $v=mb_strtolower((string)$v,'UTF-8');
    $v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    preg_match_all('/[\p{L}\p{N}]+/u',$v,$m);return implode(' ',$m[0]);
}
function ce_name($v) {
    $v=preg_replace('/\s*\(\s*(?:ex|ех)\s*\.?\s+[^()]+\)\s*$/ui','',(string)$v);
    $words=array_values(array_filter(explode(' ',ce_norm($v)),fn($w)=>$w!==''&&$w!=='hotel'));
    sort($words,SORT_STRING);return implode(' ',$words);
}
function ce_local(PDO $db,int $country,bool $lock=false) {
    $suffix=$lock?' FOR UPDATE':'';
    $s=$db->prepare('SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE country_id=? AND is_active=1 ORDER BY id LIMIT 20001'.$suffix);$s->execute([$country]);$hotels=$s->fetchAll(PDO::FETCH_ASSOC);
    $s=$db->prepare('SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id=? AND h.is_active=1 ORDER BY a.hotel_id,a.alias LIMIT 50001'.$suffix);$s->execute([$country]);$aliases=$s->fetchAll(PDO::FETCH_ASSOC);
    if(!$hotels||count($hotels)>20000||count($aliases)>50000)throw new RuntimeException('local_catalogue_incomplete');
    return ['complete'=>true,'country_id'=>$country,'hotels'=>$hotels,'aliases'=>$aliases];
}
function ce_geo(array $source,array $target,array $towns) {
    $town=$towns[(string)($source['townKey']??'')]??null;
    if(!$town||(string)($town['state']??'')!==(string)$source['stateKey']||ce_norm($town['name']??'')!==ce_norm($source['town']??''))return ['status'=>'unknown'];
    $local=array_filter([ce_norm($target['region_name']??''),ce_norm($target['subregion_name']??'')]);
    $places=array_filter([ce_norm($town['name']??''),ce_norm($town['RegionName']??'')]);$parents=[];
    foreach($towns as $t)if((string)($t['state']??'')===(string)$source['stateKey']&&isset($t['Region'])&&
        (ce_norm($t['name']??'')===ce_norm($target['region_name']??'')||ce_norm($t['RegionName']??'')===ce_norm($target['region_name']??'')))$parents[(string)$t['Region']]=true;
    $status=count($parents)===1&&isset($town['Region'])&&!isset($parents[(string)$town['Region']])?'conflict':(array_intersect($local,$places)?'supported':'unknown');
    return ['status'=>$status,'town_id'=>$town['id'],'town'=>$town['name'],'parent_id'=>$town['Region']??null,'parent'=>$town['RegionName']??null,'local_region'=>$target['region_name']??null,'local_subregion'=>$target['subregion_name']??null];
}
function ce_plan(array $data,array $local) {
    $country=(int)$data['country_id'];$supplier=(string)$data['supplier_country_id'];$catalog=$data['catalog'];
    if(($local['complete']??false)!==true||(int)$local['country_id']!==$country||(string)($catalog['params']['STATEINC']??'')!==$supplier||($catalog['action']??'')!=='all')throw new RuntimeException('country_scope');
    $hotels=[];$index=[];
    foreach($local['hotels'] as $h){$id=(int)$h['id'];if($id<1||isset($hotels[$id])||(int)$h['country_id']!==$country||(int)($h['is_active']??1)!==1)throw new RuntimeException('local_identity');$hotels[$id]=$h;$key=ce_name($h['name']);if($key!=='')$index[$key][$id]=true;}
    foreach($local['aliases'] as $alias){$id=(int)$alias['hotel_id'];if(!isset($hotels[$id]))throw new RuntimeException('orphan_alias');$key=ce_name($alias['alias']);if($key!=='')$index[$key][$id]=true;}
    $towns=[];foreach($catalog['payload']['TOWNTO']??[] as $t){if(!ce_id($t['id']??null)||isset($towns[(string)$t['id']]))throw new RuntimeException('town_identity');$towns[(string)$t['id']]=$t;}
    $sourceRows=$catalog['payload']['HOTELS']??null;if(!is_array($sourceRows)||!$sourceRows||count($sourceRows)>20000)throw new RuntimeException('source_catalogue_incomplete');
    $rows=[];$seen=[];
    foreach($sourceRows as $source){
        $external=(string)($source['id']??'');if(!ce_id($external)||isset($seen[$external]))throw new RuntimeException('source_identity');$seen[$external]=true;
        $candidates=[];foreach([$source['name']??'',$source['lName']??''] as $name){$key=ce_name($name);if($key!=='')foreach(array_keys($index[$key]??[]) as $id)$candidates[$id]=true;}
        $ids=array_map('intval',array_keys($candidates));sort($ids,SORT_NUMERIC);$target=null;$status='pending';$reason='no_unique_name';$geo=['status'=>'unknown'];
        if((string)($source['stateKey']??'')!==$supplier){$status='conflict';$reason='supplier_country_conflict';}
        elseif(count($ids)===1){$h=$hotels[$ids[0]];$geo=ce_geo($source,$h,$towns);
            if($geo['status']==='supported'){$target=$ids[0];$status='accepted';$reason='unique_current_country_name_alias_and_official_geography';}
            else{$reason=$geo['status']==='conflict'?'geography_conflict':'geography_unknown';}}
        elseif(count($ids)>1)$reason='ambiguous_name';
        $evidence=['source'=>$source,'candidate_ids'=>$ids,'target_name'=>$target!==null?$hotels[$target]['name']:null,'reason'=>$reason,'geography'=>$geo,'operation_id'=>CE_OPERATION];
        $rows[]=['external_hotel_id'=>$external,'local_hotel_id'=>$target,'decision_status'=>$status,'evidence_json'=>ce_json($evidence)];
    }
    usort($rows,fn($a,$b)=>strlen($a['external_hotel_id'])<=>strlen($b['external_hotel_id'])?:strcmp($a['external_hotel_id'],$b['external_hotel_id']));
    return ['operation_id'=>CE_OPERATION,'country_id'=>$country,'supplier_country_id'=>$data['supplier_country_id'],'catalog_sha256'=>ce_hash($catalog),'rows'=>$rows];
}
function ce_summary(array $plan) {
    $counts=['accepted'=>0,'pending'=>0,'conflict'=>0];$pairs=[];$reasons=[];
    foreach($plan['rows'] as $r){++$counts[$r['decision_status']];$e=json_decode($r['evidence_json'],true,64,JSON_THROW_ON_ERROR);$reasons[$e['reason']]=($reasons[$e['reason']]??0)+1;
        if($r['decision_status']==='accepted')$pairs[]=['external_hotel_id'=>$r['external_hotel_id'],'local_hotel_id'=>$r['local_hotel_id'],'name'=>$e['source']['name'],'target_name'=>$e['target_name']];}
    return ['counts'=>$counts,'reasons'=>$reasons,'source_hotels'=>count($plan['rows']),'accepted_unique_local'=>count(array_unique(array_column($pairs,'local_hotel_id'))),'pairs'=>$pairs];
}
function ce_existing(PDO $db,bool $lock) {
    $s=$db->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id LIMIT 100001'.($lock?' FOR UPDATE':''));$rows=[];
    while($r=$s->fetch(PDO::FETCH_ASSOC)){$rows[$r['supplier_namespace'].':'.$r['external_hotel_id']]=ce_hash($r);if(count($rows)>100000)throw new RuntimeException('identity_limit');}return $rows;
}
function ce_coverage(PDO $db) {
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($db);$anex=[];
    $ids=$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id){$local=$registry->resolve('anex_online',(string)$id,'preview');if($local!==null)$anex[$local]=true;}
    $andromeda=$db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    $andromeda=array_fill_keys(array_map('intval',$andromeda),true);$triple=count(array_intersect_key($anex,$andromeda));
    return ['anex_links'=>$registry->count(),'anex_unique_local'=>count($anex),'andromeda_unique_local'=>count($andromeda),'all_three'=>$triple,'anex_tv_only'=>count($anex)-$triple,'andromeda_tv_only'=>count($andromeda)-$triple,'exactly_two'=>count($anex)+count($andromeda)-2*$triple];
}
function ce_import(PDO $db,array $data,array $plan) {
    if(ce_hash($plan)!==ce_hash(ce_plan($data,$data['local'])))throw new RuntimeException('saved_plan_changed');
    $engine=$db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='andromeda_hotel_identities'")->fetchColumn();if(strtoupper((string)$engine)!=='INNODB')throw new RuntimeException('transactional_schema_required');
    $db->exec('SET SESSION innodb_lock_wait_timeout=10');$db->beginTransaction();$committed=false;
    try{
        $before=ce_existing($db,true);$current=ce_local($db,(int)$data['country_id'],true);
        if(ce_hash(ce_plan($data,$current))!==ce_hash($plan))throw new RuntimeException('current_name_geography_changed');
        $insert=$db->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES('andromeda_catalog',?,?,?,?,?,?)");
        $new=[];$collisions=[];$counts=['accepted'=>0,'pending'=>0,'conflict'=>0];
        foreach($plan['rows'] as $r){$key='andromeda_catalog:'.$r['external_hotel_id'];if(isset($before[$key])){$collisions[]=$r['external_hotel_id'];continue;}
            $hash=hash('sha256',$r['evidence_json']);$insert->execute([$r['external_hotel_id'],$r['local_hotel_id'],$r['decision_status'],$plan['catalog_sha256'],$hash,$r['evidence_json']]);
            $new[$key]=['external_hotel_id'=>$r['external_hotel_id'],'local_hotel_id'=>$r['local_hotel_id'],'decision_status'=>$r['decision_status'],'evidence_sha256'=>$hash];++$counts[$r['decision_status']];}
        $after=ce_existing($db,false);if(count($after)!==count($before)+count($new))throw new RuntimeException('row_count');foreach($before as $k=>$hash)if(($after[$k]??null)!==$hash)throw new RuntimeException('old_identity_changed');
        $verify=$db->prepare("SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");
        $db->commit();$committed=true;
        foreach($new as $r){$verify->execute([$r['external_hotel_id']]);$v=$verify->fetch(PDO::FETCH_ASSOC);if(!$v||(string)$v['external_hotel_id']!==$r['external_hotel_id']||($v['local_hotel_id']===null?null:(int)$v['local_hotel_id'])!==$r['local_hotel_id']||$v['decision_status']!==$r['decision_status']||$v['evidence_sha256']!==$r['evidence_sha256'])throw new RuntimeException('post_commit_readback');}
        $totals=$db->query("SELECT decision_status,COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' GROUP BY decision_status")->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['status'=>'imported','operation_id'=>CE_OPERATION,'country_id'=>$data['country_id'],'supplier_country_id'=>$data['supplier_country_id'],'inserted'=>count($new),'new_counts'=>$counts,'existing_preserved'=>count($before),'preservation_sha256'=>ce_hash($before),'existing_id_collisions'=>$collisions,'readback_verified'=>true,'previous_identities_unchanged'=>true,'current_andromeda_counts'=>$totals,'live_coverage'=>ce_coverage($db),'rows'=>array_values($new),'supplier_calls'=>0];
    }catch(Throwable $e){if(!$committed&&$db->inTransaction())$db->rollBack();throw new RuntimeException($committed?'committed_readback_unconfirmed':'transaction_rolled_back');}
}
function ce_run(array $request) {
    if(PHP_SAPI!=='cli'||($request['operation_id']??'')!==CE_OPERATION||!isset(CE_COUNTRIES[$request['country']??''])||!in_array($request['phase']??'',['capture','apply','receipt'],true))throw new RuntimeException('request_scope');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    $preview=$root.'/_preview/search3-anex-candidate';$config=require $preview.'/.andromeda-private.php';
    $private=realpath(dirname($config['catalog_path']));if(!$private)throw new RuntimeException('private_configuration');
    $stage=$private.'/'.CE_OPERATION.'-'.$request['country'];$lock=fopen($stage.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('operation_lock');
    try{
        if($request['phase']==='receipt')return is_file($stage.'/result.json')?ce_read($stage.'/result.json'):['status'=>'unknown_do_not_replay','operation_id'=>CE_OPERATION];
        $helper=realpath($root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php'));if(!$helper||strpos($helper,$root.'/')!==0)throw new RuntimeException('db_helper');require_once $helper;
        $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        require_once $preview.'/app/integrations/anex-search-mapping-registry.php';
        if($request['phase']==='capture'){
            if(is_dir($stage)||!mkdir($stage,0700))throw new RuntimeException('prior_country_operation_do_not_replay');
            ce_save($stage.'/reservation.json',['operation_id'=>CE_OPERATION,'country'=>$request['country'],'phase'=>'capture_reserved']);
            $q=$db->prepare('SELECT id,name FROM catalog_countries WHERE name=? AND is_active=1');$q->execute([CE_COUNTRIES[$request['country']]]);$found=$q->fetchAll(PDO::FETCH_ASSOC);if(count($found)!==1)throw new RuntimeException('local_country_not_unique');$country=(int)$found[0]['id'];
            $db->exec('START TRANSACTION READ ONLY');try{$local=ce_local($db,$country);}finally{$db->rollBack();}
            // Use the already installed client/budget. No handler or country activation is written.
            require_once $preview.'/api-andromeda-search3-preview.php';
            $base=ce_read($config['catalog_path']);$departure=(int)$base['all']['params']['TOWNFROMINC'];if($departure<1)throw new RuntimeException('departure_context');
            $calls=0;$countryFile=$private.'/countries/'.$country.'.json';
            if(is_file($countryFile)){$prior=ce_read($countryFile);if((int)($prior['local_country_id']??0)!==$country)throw new RuntimeException('saved_country');$catalog=$prior['all'];$supplier=(int)$catalog['params']['STATEINC'];}
            else{
                ce_save($stage.'/supplier-attempt.json',['state'=>'reserved','maximum_requests'=>3]);
                $client=new AnyTourAndromedaClient(static function($url,$options)use($private,&$calls){if(++$calls>3)throw new RuntimeException('country_supplier_budget');if($calls>1)usleep(1100000);anytour_andromeda_search3_budget($private);return (new AnyTourAndromedaTransport(true))($url,$options);},true);
                $client->login($config['username'],$config['password']);$state=$client->catalog('state',['TOWNFROMINC'=>$departure]);
                $supplier=anytour_anex_search3_dictionary_id($state['STATE'],[CE_COUNTRIES[$request['country']]]);if($supplier<1)throw new RuntimeException('supplier_country_not_unique');
                $params=['TOWNFROMINC'=>$departure,'STATEINC'=>$supplier];$catalog=['action'=>'all','params'=>$params,'payload'=>$client->catalog('all',$params)];
            }
            $raw=ce_json($catalog);foreach([$config['username'],$config['password'],rawurlencode($config['username']),rawurlencode($config['password'])] as $secret)if($secret!==''&&strpos($raw,$secret)!==false)throw new RuntimeException('unsafe_catalogue');
            $data=['country_id'=>$country,'country_name'=>CE_COUNTRIES[$request['country']],'supplier_country_id'=>$supplier,'catalog'=>$catalog,'local'=>$local];$plan=ce_plan($data,$local);
            ce_save($stage.'/capture.json',$data);ce_save($stage.'/plan.json',$plan);
            $result=array_merge(['status'=>'captured_not_applied','operation_id'=>CE_OPERATION,'country'=>$request['country'],'country_id'=>$country,'country_name'=>$data['country_name'],'supplier_country_id'=>$supplier,'capture_sha256'=>ce_hash($data),'plan_sha256'=>ce_hash($plan),'local_hotels'=>count($local['hotels']),'supplier_calls'=>$calls],ce_summary($plan));
            ce_save($stage.'/capture-result.json',$result);return $result;
        }
        $saved=ce_read($stage.'/capture-result.json');foreach(['capture_sha256','plan_sha256'] as $field)if(($request[$field]??'')!==$saved[$field])throw new RuntimeException('unapproved_country_capture');
        if(is_file($stage.'/apply-reservation.json'))throw new RuntimeException('prior_apply_do_not_replay');$data=ce_read($stage.'/capture.json');$plan=ce_read($stage.'/plan.json');
        if(ce_hash($data)!==$saved['capture_sha256']||ce_hash($plan)!==$saved['plan_sha256'])throw new RuntimeException('capture_changed');
        ce_save($stage.'/apply-reservation.json',['operation_id'=>CE_OPERATION,'state'=>'reserved','capture_sha256'=>$saved['capture_sha256'],'plan_sha256'=>$saved['plan_sha256']]);
        $result=ce_import($db,$data,$plan);$result['country']=$request['country'];$result['country_name']=$data['country_name'];$result['capture_sha256']=$saved['capture_sha256'];$result['plan_sha256']=$saved['plan_sha256'];ce_save($stage.'/result.json',$result);return $result;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
if(!defined('CE_LIBRARY_ONLY')){
    error_reporting(0);ob_start();$phase='request';
    try{$raw=file_get_contents('php://stdin',false,null,0,65537);if(strlen($raw)>65536)throw new RuntimeException('request_limit');$request=json_decode($raw,true,16,JSON_THROW_ON_ERROR);$phase=$request['phase']??'request';$result=ce_run($request);}
    catch(Throwable $e){$result=['status'=>'failed','phase'=>$phase,'retry'=>false,'operation_id'=>CE_OPERATION,'reason'=>in_array($e->getMessage(),['local_country_not_unique','supplier_country_not_unique','source_catalogue_incomplete','local_catalogue_incomplete','source_identity','town_identity','current_name_geography_changed','country_scope','prior_country_operation_do_not_replay','prior_apply_do_not_replay','committed_readback_unconfirmed','transaction_rolled_back','unsafe_catalogue','country_supplier_budget','private_record_limit'],true)?$e->getMessage():'runtime_or_upstream_failure'];}
    while(ob_get_level())ob_end_clean();$out=ce_json($result);echo strlen($out)<=4000000?$out:'{"status":"output_limit_unknown","retry":false}';
}
