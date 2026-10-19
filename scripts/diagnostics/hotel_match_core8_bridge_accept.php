<?php
declare(strict_types=1);
/** One immutable MATCH operation. Run only with the verified saved-data plan capsule. */
const MATCH_OP='hotel-match-core8-bridge-accept-1971-20260912-v1';
const MATCH_PLAN_SHA='102d744bd2d1f3d9e94449a92a9ecbd1b202d613e0d7119ea2f9b24af5ac4f9c';
const MATCH_POLICY='owner_exact_and_strong_20260908';
function hmbText($value):string {
    $s=strtr((string)$value,array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY)));
    $s=strtr($s,['ё'=>'е','й'=>'и','ı'=>'i','İ'=>'i','é'=>'e','è'=>'e','ê'=>'e','É'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','Ä'=>'a','ö'=>'o','ô'=>'o','Ö'=>'o','ü'=>'u','ú'=>'u','Ü'=>'u','ç'=>'c','Ç'=>'c','ş'=>'s','Ş'=>'s','ğ'=>'g','Ğ'=>'g','&'=>' and ']);
    $s=strtolower($s);$s=preg_replace('/[\x{0300}-\x{036f}]/u','',$s);
    preg_match_all('/[\p{L}\p{N}]+/u',$s,$m);return implode(' ',$m[0]);
}
function hmbNorm($value):string {
    $drop=['hotel','hotels','resort','resorts','spa','the','and','by','otel','отель','отели','гостиница'];
    $words=array_values(array_diff(explode(' ',hmbText($value)),$drop,['']));sort($words,SORT_STRING);return implode(' ',$words);
}
function hmbForms($value):array {
    $s=(string)$value;$re='/\(\s*(?:ex|ех|formerly|быв)\s*[.:\-]?\s*([^)]*)\)/iu';preg_match_all($re,$s,$m);
    $out=[];foreach(array_merge([$s,preg_replace($re,' ',$s)],$m[1]??[]) as $v){$n=hmbNorm($v);if($n!=='')$out[$n]=true;}return array_keys($out);
}
function hmbNames(array $names):array {$out=[];foreach($names as $name)foreach(hmbForms($name) as $n)$out[$n]=true;return array_keys($out);}
function hmbCountry($value):?string {
    $s=hmbText($value);$all=['egypt'=>['египет','egypt'],'turkey'=>['турция','turkey','turkiye'],'thailand'=>['таиланд','thailand'],
        'uae'=>['оаэ','united arab emirates','uae'],'vietnam'=>['вьетнам','vietnam','viet nam'],'srilanka'=>['шри ланка','sri lanka'],'maldives'=>['мальдивы','maldives'],'cuba'=>['куба','cuba']];
    foreach($all as $cc=>$names)if(in_array($s,$names,true))return $cc;return null;
}
function hmbGeo($value):string {return implode(' ',array_values(array_diff(explode(' ',hmbText($value)),['о','остров','island','город','г',''])));}
function hmbPoint(array $r):?array {
    $a=$r['latitude']??$r['lat']??null;$b=$r['longitude']??$r['lon']??$r['lng']??null;
    if(!is_numeric($a)||!is_numeric($b))return null;$a=(float)$a;$b=(float)$b;
    return is_finite($a)&&is_finite($b)&&abs($a)<=90&&abs($b)<=180&&($a!=0||$b!=0)?[$a,$b]:null;
}
function hmbDistance(array $a,array $b):?float {
    $a=hmbPoint($a);$b=hmbPoint($b);if(!$a||!$b)return null;$p=deg2rad($a[0]);$q=deg2rad($b[0]);
    $v=sin(($q-$p)/2)**2+cos($p)*cos($q)*sin(deg2rad($b[1]-$a[1])/2)**2;return 12742*asin(min(1,sqrt($v)));
}
function hmbCanon($v){if(!is_array($v))return $v;if($v!==[]&&array_keys($v)!==range(0,count($v)-1))ksort($v,SORT_STRING);foreach($v as &$x)$x=hmbCanon($x);unset($x);return $v;}
function hmbJson($v):string{return json_encode(hmbCanon($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmbHash($v):string{return hash('sha256',hmbJson($v));}
function hmbSave(string $path,array $data):void {
    $f=fopen($path,'x');if(!$f)throw new RuntimeException('immutable_receipt_exists');chmod($path,0600);
    try{$raw=hmbJson($data)."\n";$offset=0;while($offset<strlen($raw)){$n=fwrite($f,substr($raw,$offset));if(!$n)throw new RuntimeException('receipt_write');$offset+=$n;}if(!fflush($f))throw new RuntimeException('receipt_flush');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('receipt_sync');}finally{fclose($f);}if(file_get_contents($path)!==$raw)throw new RuntimeException('receipt_readback');
}
function hmbSources(array $e,int $depth=0):array {
    if($depth>12)return[];$out=[];
    if(isset($e['name'])&&(isset($e['id'])||isset($e['hotelKey'])))$out[]=$e;
    foreach($e as $k=>$v)if(is_array($v)&&!in_array((string)$k,['candidates','targets','local','hotels','offers'],true))$out=array_merge($out,hmbSources($v,$depth+1));
    $unique=[];foreach($out as $r){$selected=array_intersect_key($r,array_flip(['id','hotelKey','name','lName','state','stateKey','town','townKey','region','regionKey','star','starKey','latitude','longitude','lat','lon','lng','address']));$unique[hmbHash($selected)]=$selected;}return array_values($unique);
}
function hmbAndSource(array $row,array $expected):?array {
    if($row['evidence_sha256']!==$expected['evidence_sha256']||$row['catalog_sha256']!==$expected['catalog_sha256']||hash('sha256',$row['evidence_json'])!==$row['evidence_sha256'])return null;
    $e=json_decode($row['evidence_json'],true,512,JSON_THROW_ON_ERROR);$sources=array_values(array_filter(hmbSources($e),fn($s)=>(string)($s['id']??$s['hotelKey']??'')===(string)$row['external_hotel_id']));
    if(count($sources)!==1)return null;$s=$sources[0];$cc=hmbCountry($s['state']??'');if(!$cc)return null;
    return ['country_class'=>$cc,'names'=>array_filter([$s['name']??null,$s['lName']??null]),'geography'=>array_filter([$s['town']??null,$s['region']??null]),'latitude'=>$s['latitude']??$s['lat']??null,'longitude'=>$s['longitude']??$s['lon']??null,'category_label'=>$s['star']??null];
}
function hmbAnexSource(string $id,array $plan,array $staging,array $observations,array $countryIds):?array {
    $b=$plan['baseline_anex'][$id]??null;if(!$b||(string)$b['external_id']!==$id)return null;
    $cc=hmbCountry($b['country']??'');if(!$cc)return null;$a=$staging[$id]??null;$expected=$plan['expected_staging'][$id]??null;
    if(($a===null)!==($expected===null)||$a!==null&&$a['source_fingerprint']!==$expected)return null;
    if($a!==null){
        if(($a['api_country']??'')!==''&&hmbCountry($a['api_country'])!==$cc)return null;
        if(!array_intersect(hmbNames([$a['xml_name']??'',$a['xml_alternate_name']??'']),hmbNames([$b['name']??'',$b['alternate_name']??''])))return null;
    }
    $o=$observations[$id]??null;if($o&&($countryIds[(string)$o['country_id']]??null)!==$cc)return null;
    return ['country_class'=>$cc,'names'=>array_filter([$b['name']??null,$b['alternate_name']??null,$a['api_name']??null,$a['xml_name']??null,$a['xml_alternate_name']??null,$o['hotel_name']??null]),'geography'=>array_filter([$b['town']??null,$a['api_town']??null,$a['api_region']??null]),'latitude'=>$a['latitude']??null,'longitude'=>$a['longitude']??null];
}
function hmbProof(array $source,array $target,array $bridge,array $index,array $aliases):array {
    $cc=hmbCountry($target['country_name']??'');$lid=(int)$target['id'];
    if(!$cc||$source['country_class']!==$cc||$bridge['country_class']!==$cc||(int)$target['is_active']!==1)return ['ok'=>false,'reason'=>'country_or_active_guard'];
    $sn=hmbNames($source['names']);$bn=hmbNames($bridge['names']);$native=array_values(array_intersect($sn,$aliases[$lid]??[]));
    if(!$native||!array_intersect($sn,$bn))return ['ok'=>false,'reason'=>'native_and_bridge_name_guard'];
    $hits=[];foreach($sn as $n)foreach(array_keys($index[$cc][$n]??[]) as $id)$hits[(int)$id]=true;
    if(count($hits)!==1||!isset($hits[$lid]))return ['ok'=>false,'reason'=>'current_full_native_competition'];
    $specific=static fn($v)=>$v!==''&&hmbCountry($v)===null;
    $sg=array_filter(array_map('hmbGeo',$source['geography']),$specific);$bg=array_filter(array_map('hmbGeo',$bridge['geography']),$specific);$geography=array_values(array_unique(array_intersect($sg,$bg)));
    if(!$geography)return ['ok'=>false,'reason'=>'current_cross_provider_geography_guard'];
    $distance=hmbDistance($source,$target);$bridgeDistance=hmbDistance($bridge,$target);
    if($distance!==null&&$distance>1||$bridgeDistance!==null&&$bridgeDistance>5)return ['ok'=>false,'reason'=>'current_coordinate_guard'];
    sort($native,SORT_STRING);sort($geography,SORT_STRING);
    return ['ok'=>true,'native_exact_keys'=>$native,'cross_provider_exact_keys'=>array_values(array_intersect($sn,$bn)),'country'=>$cc,'geography'=>$geography,'distance_km'=>$distance,'bridge_distance_km'=>$bridgeDistance];
}
function hmbReadback(PDO $db,array $written):array {
    $confirmed=[];
    foreach($written as $w){
        if($w['provider']==='anex'){$q=$db->prepare('SELECT catalog_hotel_id,scope,approval_policy,enabled,mapping_digest,source_row_digest FROM anex_hotel_search_mappings WHERE anex_hotel_id=?');$q->execute([$w['external_id']]);$all=$q->fetchAll(PDO::FETCH_ASSOC);$r=count($all)===1?$all[0]:null;
            $ok=$r&&(int)$r['catalog_hotel_id']===$w['local_id']&&(int)$r['enabled']===1&&$r['scope']==='preview'&&$r['approval_policy']===MATCH_POLICY&&$r['mapping_digest']===$w['mapping_digest']&&$r['source_row_digest']===$w['source_row_digest'];
        }else{$q=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");$q->execute([$w['external_id']]);$r=$q->fetch(PDO::FETCH_ASSOC);$ok=$r&&(int)$r['local_hotel_id']===$w['local_id']&&$r['decision_status']==='accepted'&&$r['evidence_sha256']===$w['evidence_sha256'];}
        if(!$ok)throw new RuntimeException('post_commit_row_readback');$confirmed[]=$w;
    }
    return $confirmed;
}
function hmbSelfTest():void {
    if(hmbNorm('Unique Blossom HOTEL & SPA')!==hmbNorm('Unique Blossom Resort')||hmbNorm('X Beach')===hmbNorm('X Garden')||hmbNorm('X NORTH')===hmbNorm('X SOUTH'))throw new RuntimeException('normalization_fixture');
    if(!in_array('blossom unique',hmbForms('Renamed (EX. Unique Blossom Hotel)'),true)||hmbCountry('Россия')!==null||hmbCountry('Турция')!=='turkey')throw new RuntimeException('country_alias_fixture');
    $s=['country_class'=>'turkey','names'=>['Unique Blossom'],'geography'=>['Анталья']];$h=['id'=>1,'country_name'=>'Турция','is_active'=>1,'latitude'=>36.8,'longitude'=>30.8];$idx=['turkey'=>['blossom unique'=>[1=>true]]];$aliases=[1=>['blossom unique']];
    if(!hmbProof($s,$h,$s,$idx,$aliases)['ok'])throw new RuntimeException('positive_fixture');
    $bad=$s;$bad['country_class']='egypt';if(hmbProof($s,$h,$bad,$idx,$aliases)['ok'])throw new RuntimeException('cross_country_fixture');
    $bad=$s;$bad['latitude']=38;$bad['longitude']=30.8;if(hmbProof($bad,$h,$s,$idx,$aliases)['ok'])throw new RuntimeException('coordinate_fixture');
    $bad=$s;$bad['geography']=['Турция'];if(hmbProof($bad,$h,$bad,$idx,$aliases)['ok'])throw new RuntimeException('country_only_geo_fixture');
    if(hmbProof($s,$h,$s,$idx,[1=>['different native']])['ok'])throw new RuntimeException('native_identity_fixture');
    $idx['turkey']['blossom unique'][2]=true;if(hmbProof($s,$h,$s,$idx,$aliases)['ok'])throw new RuntimeException('ambiguity_fixture');
    echo "MATCH guarded proof fixtures PASS; DB0/network0\n";
}
if(defined('HM_OFFLINE_LIBRARY')&&HM_OFFLINE_LIBRARY===true)return;
if(in_array('--self-test',$_SERVER['argv']??[],true)){hmbSelfTest();exit;}
error_reporting(0);ob_start();$db=null;$stage=null;$committed=false;$commitAttempted=false;$written=[];$skipped=[];
try {
    if(PHP_SAPI!=='cli'||!defined('HM_WRITE_PLAN_B64'))throw new RuntimeException('capsule_required');
    $raw=base64_decode(HM_WRITE_PLAN_B64,true);if($raw===false||hash('sha256',$raw)!==MATCH_PLAN_SHA)throw new RuntimeException('plan_sha_guard');
    $plan=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if($plan['operation_id']!==MATCH_OP||$plan['cap']!==400||count($plan['rows'])!==352)throw new RuntimeException('plan_scope');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    $home=realpath((string)getenv('HOME'));if(!$home)throw new RuntimeException('home');
    $base=$home.'/.anytoour-match/operations';if(!is_dir($base)&&!mkdir($base,0700,true))throw new RuntimeException('receipt_root');
    $stage=$base.'/'.MATCH_OP;if(file_exists($stage)||!mkdir($stage,0700)){$stage=null;throw new RuntimeException('prior_operation_no_replay');}
    hmbSave($stage.'/reservation.json',['operation_id'=>MATCH_OP,'plan_sha256'=>MATCH_PLAN_SHA,'status'=>'reserved_before_db_access','no_replay'=>true]);
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    foreach(['anex_hotel_search_mappings','andromeda_hotel_identities'] as $table){$q=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);if(strtoupper((string)$q->fetchColumn())!=='INNODB')throw new RuntimeException('transactional_engine_required');}
    $db->exec('SET SESSION innodb_lock_wait_timeout=10');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->beginTransaction();
    $maps=$db->query('SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $dec=$db->query('SELECT * FROM anex_hotel_decisions ORDER BY anex_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $ex=$db->query('SELECT * FROM anex_review_pair_exclusions ORDER BY anex_hotel_id,catalog_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $andAll=$db->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $oldAnd=[];$and=[];foreach($andAll as $r){$key=$r['supplier_namespace'].':'.$r['external_hotel_id'];$oldAnd[$key]=hmbHash($r);if($r['supplier_namespace']==='andromeda_catalog')$and[(string)$r['external_hotel_id']]=$r;}
    $manual=[];foreach(array_merge($dec,$ex) as $r)$manual[(string)$r['anex_hotel_id']]=true;
    $mapIndex=[];$oldMaps=[];foreach($maps as $r){$mapIndex[(string)$r['anex_hotel_id']][]=$r;$oldMaps[] = hmbHash($r);}
    $aids=array_keys($plan['baseline_anex']);$marks=implode(',',array_fill(0,count($aids),'?'));
    $q=$db->prepare('SELECT * FROM anex_hotels WHERE anex_hotel_id IN ('.$marks.') FOR UPDATE');$q->execute($aids);$staging=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$staging[(string)$r['anex_hotel_id']]=$r;
    $q=$db->prepare('SELECT * FROM anex_search_hotel_observations WHERE anex_hotel_id IN ('.$marks.') FOR UPDATE');$q->execute($aids);$observations=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$observations[(string)$r['anex_hotel_id']]=$r;
    $lids=array_values(array_unique(array_column($plan['rows'],'local_id')));$q=$db->prepare('SELECT id FROM catalog_hotels WHERE id IN ('.implode(',',array_fill(0,count($lids),'?')).') FOR UPDATE');$q->execute($lids);$q->fetchAll();
    $hotels=[];$countryIds=[];$index=[];$aliases=[];
    foreach($db->query('SELECT id,name,country_id,country_name,region_name,subregion_name,category,latitude,longitude,is_active FROM catalog_hotels WHERE is_active=1')->fetchAll(PDO::FETCH_ASSOC) as $r){$cc=hmbCountry($r['country_name']);if(!$cc)continue;$lid=(int)$r['id'];$hotels[$lid]=$r;$countryIds[(string)$r['country_id']]=$cc;$aliases[$lid]=hmbForms($r['name']);}
    foreach($db->query('SELECT hotel_id,alias FROM hotel_aliases')->fetchAll(PDO::FETCH_ASSOC) as $r){$lid=(int)$r['hotel_id'];if(isset($hotels[$lid]))$aliases[$lid]=array_values(array_unique(array_merge($aliases[$lid],hmbForms($r['alias']))));}
    foreach($aliases as $lid=>$names){$cc=hmbCountry($hotels[$lid]['country_name']);foreach($names as $n)$index[$cc][$n][$lid]=true;}
    $ins=$db->prepare('INSERT INTO anex_hotel_search_mappings(anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled) VALUES(?,?,?,?,?,?,?,1)');
    $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_json=?,evidence_sha256=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND local_hotel_id IS NULL AND decision_status='pending' AND evidence_sha256=? AND catalog_sha256=?");
    $changedAnd=[];
    foreach($plan['rows'] as $p){
        $id=(string)$p['external_id'];$bid=(string)$p['bridge_id'];$lid=(int)$p['local_id'];$s=$b=null;$reason=null;$target=$hotels[$lid]??null;
        if(!$target)$reason='target_missing';
        elseif($p['provider']==='anex'){
            if(isset($mapIndex[$id])||isset($manual[$id]))$reason='current_mapping_manual_exclusion';
            else{$s=hmbAnexSource($id,$plan,$staging,$observations,$countryIds);$br=$and[$bid]??null;
                if(!$br||$br['decision_status']!=='accepted'||(int)$br['local_hotel_id']!==$lid)$reason='current_bridge_not_accepted';
                else $b=hmbAndSource($br,$plan['expected_andromeda'][$bid]);}
        }elseif($p['provider']==='andromeda'){
            $ar=$and[$id]??null;$bm=$mapIndex[$bid]??[];
            if(!$ar||$ar['decision_status']!=='pending'||$ar['local_hotel_id']!==null)$reason='current_andromeda_protected';
            elseif(count($bm)!==1||isset($manual[$bid])||(int)$bm[0]['catalog_hotel_id']!==$lid||(int)$bm[0]['enabled']!==1||$bm[0]['scope']!=='preview')$reason='current_anex_bridge_guard';
            else{$s=hmbAndSource($ar,$plan['expected_andromeda'][$id]);$b=hmbAnexSource($bid,$plan,$staging,$observations,$countryIds);$star=(string)($s['category_label']??'');if(preg_match('/^[1-5]$/D',$star)&&(int)$star!==(int)$target['category'])$reason='category_needs_more_evidence';}
        }else $reason='provider_guard';
        if(!$reason&&(!$s||!$b))$reason='current_source_evidence_changed';
        $proof=$reason?['ok'=>false,'reason'=>$reason]:hmbProof($s,$target,$b,$index,$aliases);
        if(!$proof['ok']){$skipped[]=['provider'=>$p['provider'],'external_id'=>$id,'reason'=>$proof['reason']];continue;}
        $proof['bridge_id']=$bid;$proof['operation_id']=MATCH_OP;$proof['plan_sha256']=MATCH_PLAN_SHA;
        if($p['provider']==='anex'){
            $sd=hmbHash(['source_id'=>$id,'source'=>$s,'proof'=>$proof]);$mapping=['anex_hotel_id'=>(int)$id,'catalog_hotel_id'=>$lid,'match_class'=>'strong_candidate','scope'=>'preview','approval_policy'=>MATCH_POLICY,'source_row_digest'=>$sd];$md=hmbHash($mapping);
            $ins->execute([(int)$id,$lid,'strong_candidate','preview',MATCH_POLICY,$sd,$md]);if($ins->rowCount()!==1)throw new RuntimeException('anex_insert_count');
            $written[]=['provider'=>'anex','external_id'=>$id,'local_id'=>$lid,'source_row_digest'=>$sd,'mapping_digest'=>$md,'proof'=>$proof];
        }else{
            $old=$and[$id];$json=hmbJson(['operation_id'=>MATCH_OP,'plan_sha256'=>MATCH_PLAN_SHA,'prior_evidence'=>json_decode($old['evidence_json'],true,512,JSON_THROW_ON_ERROR),'proof'=>$proof]);$eh=hash('sha256',$json);
            $update->execute([$lid,$json,$eh,$id,$old['evidence_sha256'],$old['catalog_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('andromeda_update_count');$changedAnd['andromeda_catalog:'.$id]=true;
            $written[]=['provider'=>'andromeda','external_id'=>$id,'local_id'=>$lid,'evidence_sha256'=>$eh,'proof'=>$proof];
        }
    }
    $afterMaps=$db->query('SELECT * FROM anex_hotel_search_mappings ORDER BY anex_hotel_id,catalog_hotel_id')->fetchAll(PDO::FETCH_ASSOC);$preservedMaps=[];
    foreach($afterMaps as $r)if(isset($mapIndex[(string)$r['anex_hotel_id']]))$preservedMaps[]=hmbHash($r);
    if($preservedMaps!==$oldMaps||count($afterMaps)!==count($maps)+count(array_filter($written,fn($w)=>$w['provider']==='anex')))throw new RuntimeException('anex_preservation');
    $afterAnd=$db->query('SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id')->fetchAll(PDO::FETCH_ASSOC);if(count($afterAnd)!==count($andAll))throw new RuntimeException('andromeda_count');
    foreach($afterAnd as $r){$key=$r['supplier_namespace'].':'.$r['external_hotel_id'];if(!isset($changedAnd[$key])&&($oldAnd[$key]??null)!==hmbHash($r))throw new RuntimeException('andromeda_preservation');}
    hmbSave($stage.'/precommit-intent.json',['operation_id'=>MATCH_OP,'plan_sha256'=>MATCH_PLAN_SHA,'written'=>$written,'skipped'=>$skipped,'no_replay'=>true]);
    $commitAttempted=true;$db->commit();$committed=true;
    $db->exec('START TRANSACTION READ ONLY');$readback=hmbReadback($db,$written);$db->exec('ROLLBACK');
    $counts=['anex'=>0,'andromeda'=>0];foreach($written as $w)++$counts[$w['provider']];
    $out=['status'=>'accepted','operation_id'=>MATCH_OP,'plan_sha256'=>MATCH_PLAN_SHA,'planned_count'=>count($plan['rows']),'written_count'=>count($written),'provider_counts'=>$counts,'skipped_count'=>count($skipped),'written'=>$readback,'skipped'=>$skipped,'readback_verified'=>true,'existing_mappings_and_other_identities_preserved'=>true,'database_writes'=>count($written),'mapping_writes'=>count($written),'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
    hmbSave($stage.'/result.json',$out);
}catch(Throwable $e){
    try{if($db instanceof PDO&&$db->inTransaction())$db->rollBack();}catch(Throwable $ignored){}
    $out=['status'=>$commitAttempted?'commit_outcome_requires_readback':'rolled_back_or_not_started','operation_id'=>MATCH_OP,'safe_message'=>(preg_match('/^[a-z_]+(?:[0-9]+)?$/D',$e->getMessage())?$e->getMessage():'guard_or_storage_failure'),'commit_confirmed'=>$committed,'commit_attempted'=>$commitAttempted,'mapping_writes'=>$commitAttempted?null:0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
    if($stage&&is_dir($stage)&&!file_exists($stage.'/failure.json')){try{hmbSave($stage.'/failure.json',$out);}catch(Throwable $ignored){}}
}
while(ob_get_level())ob_end_clean();echo 'MATCH_BRIDGE_ACCEPT:'.hmbJson($out)."\n";exit($out['status']==='accepted'?0:2);
