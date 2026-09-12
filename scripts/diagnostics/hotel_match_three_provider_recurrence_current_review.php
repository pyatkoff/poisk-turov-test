<?php
declare(strict_types=1);

/**
 * MATCH #1971: reconcile immutable synchronized 3-provider recurrence evidence
 * against one CURRENT REPEATABLE READ / READ ONLY DB snapshot.
 *
 * Read-only by construction: no supplier clients, no INSERT/UPDATE/DELETE.
 */
const HMRCR_OPERATION = 'hotel-match-three-provider-recurrence-current-review-1971-20260912-v1';
const HMRCR_EVIDENCE_SHA256 = 'e78a092225a3d976345cc6f1eafd9d5d170bcfe34dd608c66de1cd99f843d98f';
const HMRCR_COUNTRY_ID = 4;
const HMRCR_POLICY = 'owner_exact_and_strong_20260908';

function hmrcr_fail(string $reason): array {
    return ['status'=>'failed','operation_id'=>HMRCR_OPERATION,'reason'=>$reason,
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,
        'anex_calls'=>0,'andromeda_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];
}
function hmrcr_norm(string $v, bool $generic=false): string {
    $v=str_replace(['Ё','ё'],['Е','е'],$v);
    $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);
    $v=(string)(preg_replace('/\bex\.?\s*/iu',' ',$v)??$v);
    $v=(string)(preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v);
    $parts=array_values(array_filter(preg_split('/\s+/u',trim($v))?:[],static fn($x)=>$x!==''));
    if($generic)$parts=array_values(array_filter($parts,static fn($x)=>!in_array($x,['hotel','resort','spa'],true)));
    sort($parts,SORT_STRING);
    return implode(' ',$parts);
}
function hmrcr_aliases(string $name): array {
    $raw=[$name,(string)(preg_replace('/\s*\([^)]*\)\s*/u',' ',$name)??$name)];
    if(preg_match_all('/\((?:EX\.?\s*)?([^)]{2,100})\)/iu',$name,$m))foreach($m[1] as $x)$raw[]=trim((string)$x);
    $out=[];
    foreach($raw as $x)foreach([false,true] as $g){$k=hmrcr_norm((string)$x,$g);if($k!=='')$out[$k]=true;}
    return array_keys($out);
}
function hmrcr_shared_alias(array $leftNames,array $rightNames): array {
    $l=[];$r=[];
    foreach($leftNames as $name)foreach(hmrcr_aliases((string)$name) as $k)$l[$k]=true;
    foreach($rightNames as $name)foreach(hmrcr_aliases((string)$name) as $k)$r[$k]=true;
    $shared=array_values(array_intersect(array_keys($l),array_keys($r)));
    usort($shared,static fn($a,$b)=>strlen($b)<=>strlen($a) ?: strcmp($a,$b));
    return $shared;
}
function hmrcr_sig_tokens(string $key): int {
    $parts=preg_split('/\s+/u',trim(hmrcr_norm($key,true)))?:[];
    return count(array_values(array_filter($parts,static fn($x)=>$x!=='')));
}
function hmrcr_num($v): ?float {
    if($v===null||$v==='')return null;
    if(!is_numeric($v))return null;
    $n=(float)$v; return is_finite($n)?$n:null;
}
function hmrcr_coord(array $row): array {
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['hotelLatitude','hotelLongitude']] as $p){
        if(!array_key_exists($p[0],$row)||!array_key_exists($p[1],$row))continue;
        $a=hmrcr_num($row[$p[0]]);$b=hmrcr_num($row[$p[1]]);
        if($a!==null&&$b!==null&&abs($a)<=90&&abs($b)<=180)return[$a,$b];
    }
    return[null,null];
}
function hmrcr_haversine(?float $a,?float $b,?float $c,?float $d): ?float {
    if($a===null||$b===null||$c===null||$d===null)return null;
    $r=6371000.0;$p1=deg2rad($a);$p2=deg2rad($c);$dp=deg2rad($c-$a);$dl=deg2rad($d-$b);
    $x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;
    return 2*$r*asin(min(1.0,sqrt($x)));
}
function hmrcr_evidence(): array {
    if(!defined('HMRCR_EVIDENCE_B64'))throw new RuntimeException('evidence_not_embedded');
    $raw=base64_decode((string)constant('HMRCR_EVIDENCE_B64'),true);
    if(!is_string($raw)||hash('sha256',$raw)!==HMRCR_EVIDENCE_SHA256)throw new RuntimeException('evidence_sha_mismatch');
    $d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(($d['v']??null)!==1||($d['summary']??null)!==[10,233,71,50,41,32,13,46,40,27]||count($d['tuples']??[])!==71)throw new RuntimeException('evidence_contract_changed');
    if(($d['scope'][2]??null)!==HMRCR_COUNTRY_ID)throw new RuntimeException('evidence_country_changed');
    return $d;
}
function hmrcr_in(array $values): array {
    $values=array_values(array_unique($values,SORT_REGULAR));
    if(!$values)return['NULL',[]];
    return[implode(',',array_fill(0,count($values),'?')),$values];
}
function hmrcr_index_rows(PDO $db,string $sql,array $params,callable $key): array {
    $q=$db->prepare($sql);$q->execute($params);$out=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$out[$key($row)][]=$row;
    return$out;
}
function hmrcr_review(PDO $db,array $ev): array {
    $tuples=$ev['tuples'];$tvIds=[];$anexIds=[];$andrIds=[];
    foreach($tuples as $t){$tvIds[]=(int)$t[0];$anexIds[]=(int)$t[1];if((string)$t[3]==='andromeda_catalog')$andrIds[]=(string)$t[2];}
    [$tvSql,$tvP]=hmrcr_in($tvIds);[$anSql,$anP]=hmrcr_in($anexIds);[$andrSql,$andrP]=hmrcr_in($andrIds);

    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $hotels=[];$q=$db->prepare("SELECT h.id,h.country_id,h.name,h.normalized_name,h.region_name,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.id IN ($tvSql)");
        $q->execute($tvP);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$hotels[(int)$r['id']]=$r;
        $aliases=[];$q=$db->prepare("SELECT hotel_id,alias,normalized_alias FROM hotel_aliases WHERE hotel_id IN ($tvSql) ORDER BY hotel_id,id");$q->execute($tvP);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['hotel_id'];foreach(['alias','normalized_alias'] as $k)if(trim((string)$r[$k])!=='')$aliases[$id][]=(string)$r[$k];}

        $anDec=hmrcr_index_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE anex_hotel_id IN ($anSql)",$anP,static fn($r)=>(int)$r['anex_hotel_id']);
        $anMap=hmrcr_index_rows($db,"SELECT anex_hotel_id,catalog_hotel_id,enabled,scope,approval_policy FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($anSql)",$anP,static fn($r)=>(int)$r['anex_hotel_id']);
        $anEx=hmrcr_index_rows($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($anSql)",$anP,static fn($r)=>(int)$r['anex_hotel_id']);
        $anStage=hmrcr_index_rows($db,"SELECT * FROM anex_hotels WHERE anex_hotel_id IN ($anSql)",$anP,static fn($r)=>(int)$r['anex_hotel_id']);
        $anObs=hmrcr_index_rows($db,"SELECT * FROM anex_search_hotel_observations WHERE anex_hotel_id IN ($anSql) ORDER BY search_count DESC,last_seen_utc DESC",$anP,static fn($r)=>(int)$r['anex_hotel_id']);

        $andr=[];$andrObs=[];
        if($andrIds){
            $andr=hmrcr_index_rows($db,"SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($andrSql)",$andrP,static fn($r)=>(string)$r['external_hotel_id']);
            $andrObs=hmrcr_index_rows($db,"SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($andrSql) ORDER BY observed_at_utc DESC",$andrP,static fn($r)=>(string)$r['external_hotel_id']);
        }

        // Same-provider target occupancy from CURRENT accepted/effective identities.
        $anOccup=[];$decided=[];
        foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id,decision_status FROM anex_hotel_decisions")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $id=(int)$r['anex_hotel_id'];$decided[$id]=true;
            if($r['decision_status']==='accepted'&&$r['catalog_hotel_id']!==null)$anOccup[(int)$r['catalog_hotel_id']][$id]=true;
        }
        foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1 AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $id=(int)$r['anex_hotel_id'];if(isset($decided[$id]))continue;$anOccup[(int)$r['catalog_hotel_id']][$id]=true;
        }
        $andrOccup=[];
        foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$andrOccup[(int)$r['local_hotel_id']][(string)$r['external_hotel_id']]=true;

        $buckets=['already_triple'=>[],'safe_missing_anex'=>[],'safe_missing_andromeda'=>[],'both_unlinked_tv_anchor'=>[],'protected_or_conflict'=>[],'not_current'=>[]];
        $reasons=[];$safeTargets=[];$live=0;
        foreach($tuples as $t){
            [$tv,$anex,$ae,$ns,$tvName,$anName,$andrName,$alias,$obs,$dates,$mask]=$t;
            $tv=(int)$tv;$anex=(int)$anex;$ae=(string)$ae;$obs=(int)$obs;$dates=(int)$dates;
            $base=['tourvisor_id'=>$tv,'anex_hotel_id'=>$anex,'andromeda_namespace'=>(string)$ns,'andromeda_external_id'=>$ae,'observation_count'=>$obs,'distinct_date_count'=>$dates,'alias_key'=>(string)$alias];
            $target=$hotels[$tv]??null;
            if(!$target||(int)$target['is_active']!==1||(int)$target['country_id']!==HMRCR_COUNTRY_ID){$base['reason']='tourvisor_local_target_not_current_turkey';$buckets['not_current'][]=$base;$reasons[$base['reason']]=($reasons[$base['reason']]??0)+1;continue;}
            $localNames=array_merge([(string)$target['name'],(string)($target['normalized_name']??'')],$aliases[$tv]??[]);
            $nameChecks=[
                'tv'=>hmrcr_shared_alias($localNames,[(string)$tvName]),
                'anex'=>hmrcr_shared_alias($localNames,[(string)$anName]),
                'andromeda'=>hmrcr_shared_alias($localNames,[(string)$andrName]),
            ];
            if(!$nameChecks['tv']||!$nameChecks['anex']||!$nameChecks['andromeda']){$base['reason']='current_local_name_alias_no_longer_supports_tuple';$buckets['protected_or_conflict'][]=$base;$reasons[$base['reason']]=($reasons[$base['reason']]??0)+1;continue;}
            $sig=hmrcr_sig_tokens((string)$alias);$base['significant_alias_tokens']=$sig;$base['local_name']=$target['name'];
            [$tl,$to]=hmrcr_coord($target);$distances=[];
            $stage=($anStage[$anex][0]??[]);[$al,$ao]=hmrcr_coord($stage);$ad=hmrcr_haversine($al,$ao,$tl,$to);if($ad!==null)$distances['anex_m']=(int)round($ad);
            $ai=($andr[$ae][0]??[]);$evidence=json_decode((string)($ai['evidence_json']??''),true);if(!is_array($evidence))$evidence=[];
            $src=is_array($evidence['source']??null)?$evidence['source']:[];
            $aobs=($andrObs[$ae][0]??[]);[$dl,$do]=hmrcr_coord($src+$aobs);$dd=hmrcr_haversine($dl,$do,$tl,$to);if($dd!==null)$distances['andromeda_m']=(int)round($dd);
            $base['coordinate_distances_m']=$distances;
            if(($ad!==null&&$ad>5000)||($dd!==null&&$dd>5000)){$base['reason']='coordinate_conflict_gt_5km';$buckets['protected_or_conflict'][]=$base;$reasons[$base['reason']]=($reasons[$base['reason']]??0)+1;continue;}
            foreach($anObs[$anex]??[] as $o)if((int)($o['search_count']??0)>0){$base['anex_search_count']=(int)$o['search_count'];$base['anex_last_seen_utc']=$o['last_seen_utc']??null;$live++;break;}

            // ANEX current state: manual decision has precedence over policy.
            $anState='missing';$anReason='no_current_mapping';
            if(isset($anDec[$anex])){
                $accepted=array_values(array_filter($anDec[$anex],static fn($r)=>$r['decision_status']==='accepted'&&$r['catalog_hotel_id']!==null));
                if(count($accepted)===1&&(int)$accepted[0]['catalog_hotel_id']===$tv){$anState='same';$anReason='manual_accepted_same';}
                elseif($accepted){$anState='conflict';$anReason='manual_accepted_other';}
                else{$anState='protected';$anReason='manual_decision_protected';}
            }else{
                $targets=[];foreach($anMap[$anex]??[] as $r)if((int)$r['enabled']===1&&$r['catalog_hotel_id']!==null)$targets[(int)$r['catalog_hotel_id']]=true;
                if(count($targets)===1&&isset($targets[$tv])){$anState='same';$anReason='policy_mapping_same';}
                elseif($targets){$anState='conflict';$anReason='existing_policy_mapping_other';}
            }
            foreach($anEx[$anex]??[] as $x)if((int)$x['catalog_hotel_id']===$tv&&$anState==='missing'){$anState='protected';$anReason='pair_exclusion_target';}
            if($anState==='missing'){
                $occ=array_keys($anOccup[$tv]??[]);$occ=array_values(array_filter($occ,static fn($id)=>$id!==$anex));
                if($occ){$anState='conflict';$anReason='anex_target_occupied_by_other';$base['anex_target_occupants']=array_slice($occ,0,10);}
            }

            // Andromeda current state; operator-specific temporary ids are never registry-write candidates.
            $drState='missing';$drReason='pending_null_local';
            if((string)$ns!=='andromeda_catalog'){$drState='protected';$drReason='unsupported_operator_specific_namespace';}
            elseif(!isset($andr[$ae])){$drState='not_current';$drReason='andromeda_identity_row_missing';}
            else{
                $accepted=array_values(array_filter($andr[$ae],static fn($r)=>$r['decision_status']==='accepted'&&$r['local_hotel_id']!==null));
                $conflict=array_values(array_filter($andr[$ae],static fn($r)=>$r['decision_status']==='conflict'));
                if(count($accepted)===1&&(int)$accepted[0]['local_hotel_id']===$tv){$drState='same';$drReason='accepted_same';}
                elseif($accepted){$drState='conflict';$drReason='accepted_other';}
                elseif($conflict){$drState='protected';$drReason='andromeda_conflict_protected';}
            }
            if($drState==='missing'){
                $occ=array_keys($andrOccup[$tv]??[]);$occ=array_values(array_filter($occ,static fn($id)=>(string)$id!==$ae));
                if($occ){$drState='conflict';$drReason='andromeda_target_occupied_by_other';$base['andromeda_target_occupants']=array_slice($occ,0,10);}
            }
            $base['anex_state']=$anState;$base['anex_reason']=$anReason;$base['andromeda_state']=$drState;$base['andromeda_reason']=$drReason;

            if($anState==='same'&&$drState==='same'){$bucket='already_triple';$base['reason']='both_current_same_target';}
            elseif($anState==='same'&&$drState==='missing'){
                if($obs>=2&&$dates>=2&&$sig>=2){$bucket='safe_missing_andromeda';$base['reason']='current_anex_bridge_plus_recurrent_synchronized_tv_anchor';$safeTargets[]=['provider'=>'andromeda','external_id'=>$ae,'local_hotel_id'=>$tv,'observations'=>$obs,'dates'=>$dates];}
                else{$bucket='protected_or_conflict';$base['reason']=$sig<2?'low_information_alias_needs_geo_or_operator_evidence':'recurrence_support_below_safe_threshold';}
            }elseif($drState==='same'&&$anState==='missing'){
                if($obs>=2&&$dates>=2&&$sig>=2){$bucket='safe_missing_anex';$base['reason']='current_andromeda_bridge_plus_recurrent_synchronized_tv_anchor';$safeTargets[]=['provider'=>'anex','external_id'=>$anex,'local_hotel_id'=>$tv,'observations'=>$obs,'dates'=>$dates];}
                else{$bucket='protected_or_conflict';$base['reason']=$sig<2?'low_information_alias_needs_geo_or_operator_evidence':'recurrence_support_below_safe_threshold';}
            }elseif($anState==='missing'&&$drState==='missing'){$bucket='both_unlinked_tv_anchor';$base['reason']='synchronized_tv_anchor_requires_operator_hotelcode_or_current_bridge';}
            elseif($anState==='not_current'||$drState==='not_current'){$bucket='not_current';$base['reason']=$anState==='not_current'?$anReason:$drReason;}
            else{$bucket='protected_or_conflict';$base['reason']='existing_protection_or_identity_conflict';}
            $buckets[$bucket][]=$base;$reasons[$base['reason']]=($reasons[$base['reason']]??0)+1;
        }
        foreach($buckets as &$rows)usort($rows,static fn($a,$b)=>($b['observation_count']<=>$a['observation_count'])?:($b['distinct_date_count']<=>$a['distinct_date_count'])?:($a['tourvisor_id']<=>$b['tourvisor_id']));unset($rows);
        arsort($reasons);$counts=[];foreach($buckets as $k=>$v)$counts[$k]=count($v);
        $coverage=[];
        foreach([
            'anex_links'=>"SELECT COUNT(*) FROM anex_hotel_search_mappings WHERE enabled=1",
            'andromeda_accepted'=>"SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL",
            'andromeda_pending'=>"SELECT COUNT(*) FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL"
        ] as $k=>$sql)$coverage[$k]=(int)$db->query($sql)->fetchColumn();
        $db->commit();
        return ['status'=>'completed','operation_id'=>HMRCR_OPERATION,'mode'=>'current_db_repeatable_read_read_only',
            'input'=>['slices'=>10,'observations'=>233,'tuples'=>71,'sha256'=>HMRCR_EVIDENCE_SHA256],
            'examined'=>71,'counts'=>$counts,'reason_counts'=>$reasons,'safe_targets'=>$safeTargets,'live_tuple_hits'=>$live,
            'coverage'=>$coverage,'buckets'=>$buckets,
            'guards'=>['country_id'=>4,'exact_tourvisor_local_id_required'=>true,'current_local_alias_revalidated'=>true,'generic_identity_tokens'=>['HOTEL','RESORT','SPA'],'significant_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'coordinate_conflict_auto_block_m'=>5000,'manual_decisions_preserved'=>true,'pair_exclusions_preserved'=>true,'existing_mappings_preserved'=>true,'same_provider_target_occupancy_blocked'=>true,'recurrence_alone_accepts_identity'=>false,'safe_missing_requires_observations_gte'=>2,'safe_missing_requires_dates_gte'=>2,'safe_missing_requires_significant_tokens_gte'=>2],
            'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}

if(PHP_SAPI==='cli'&&($argv[1]??'')==='--self-test'){
    $a=hmrcr_aliases('MERIL BEACH HOTEL TURUNC ADULTS ONLY 16+');
    if(!in_array('16 adults beach meril only turunc',$a,true))throw new RuntimeException('qualifier_test');
    if(hmrcr_sig_tokens('hotel spa')!==0||hmrcr_sig_tokens('beach hotel meril')!==2)throw new RuntimeException('generic_test');
    echo "MATCH_RECURRENCE_CURRENT_TEST_OK\n";exit(0);
}
if(PHP_SAPI==='cli'&&($argv[1]??'')==='--live'){
    try{
        $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('server_root_invalid');
        $helper=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $helper;
        $result=hmrcr_review(v2_data_db(),hmrcr_evidence());
        echo 'HMRCR_RESULT:'.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    }catch(Throwable $e){
        echo 'HMRCR_RESULT:'.json_encode(hmrcr_fail('current_review_failed'),JSON_UNESCAPED_SLASHES).PHP_EOL;exit(2);
    }
}
