<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const GGR_OPERATION = 'hotel-match-global-graph-review-2333-20260913-v1';

function ggr_evidence_layers(array $e): array {
    $out=[]; $stack=[$e]; $seen=0;
    while ($stack && $seen++ < 8) {
        $x=array_pop($stack); if (!is_array($x)) continue; $out[]=$x;
        foreach (['prior_evidence','previous_evidence'] as $k) if (isset($x[$k]) && is_array($x[$k])) $stack[]=$x[$k];
    }
    return $out;
}
function ggr_source_geo(array $e): array {
    $names=[];$places=[];$coords=[];
    foreach (ggr_evidence_layers($e) as $x) {
        $s=$x['source']??[]; if (!is_array($s)) $s=[];
        $g=$x['geography']??[]; if (!is_array($g)) $g=[];
        foreach (['name','lName','hotel_name','alternate_name'] as $k) if (trim((string)($s[$k]??''))!=='') $names[]=(string)$s[$k];
        foreach (['town','region','parent','place'] as $k) foreach ([$s[$k]??'', $g[$k]??''] as $v) if (trim((string)$v)!=='') $places[]=(string)$v;
        [$lat,$lon]=mbr_coord($s); if ($lat!==null && $lon!==null) $coords[]=[$lat,$lon];
    }
    return [array_values(array_unique($names)),array_values(array_unique($places)),$coords];
}
function ggr_translit(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');
    $v=strtr($v,['щ'=>'shch','ш'=>'sh','ч'=>'ch','ц'=>'ts','ю'=>'yu','я'=>'ya','ё'=>'e','ж'=>'zh','х'=>'kh','а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','ы'=>'y','э'=>'e','ь'=>'','ъ'=>'']);
    $v=preg_replace('/\s*\(\s*(?:ex|ех)\s*\.?\s*[^()]+\)\s*$/ui',' ',$v);
    $v=preg_replace('/\b(hotel|resort|spa)\b/ui',' ',$v);
    $v=preg_replace('/[^a-z0-9]+/',' ',$v);
    return trim(preg_replace('/\s+/',' ',$v));
}
function ggr_tokens(string $v): array { $k=ggr_translit($v); return $k===''?[]:explode(' ',$k); }
function ggr_qualifiers(string $v): array {
    $set=[]; $tokens=array_fill_keys(ggr_tokens($v),true);
    foreach (['annex','beach','garden','north','south','east','west','club','palace','village','family','adults','adult','aqua','royal','grand','premium','select','collection'] as $q) if(isset($tokens[$q]))$set[$q]=true;
    return $set;
}
function ggr_qualifier_conflict(array $sourceNames, array $targetNames): bool {
    $s=[];$t=[];foreach($sourceNames as $n)$s+=ggr_qualifiers((string)$n);foreach($targetNames as $n)$t+=ggr_qualifiers((string)$n);
    foreach(array_keys($s) as $q) if(!isset($t[$q])) return true;
    return false;
}
function ggr_exact_name(array $sourceNames, array $targetNames): bool {
    $t=[];foreach($targetNames as $n){$k=ggr_translit((string)$n);if($k!=='')$t[$k]=true;}
    foreach($sourceNames as $n){$k=ggr_translit((string)$n);if($k!==''&&isset($t[$k]))return true;}
    return false;
}
function ggr_learn_geo(PDO $db,array $hotels): array {
    $counts=[];$rows=0;
    $q=$db->query("SELECT local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL");
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$lid=(int)$r['local_hotel_id'];if(!isset($hotels[$lid]))continue;$e=fc_evidence($r['evidence_json']??'');[, $places]=ggr_source_geo($e);$targets=[];foreach([$hotels[$lid]['region_name']??'',$hotels[$lid]['subregion_name']??''] as $p){$k=ggr_translit((string)$p);if($k!=='')$targets[$k]=true;}foreach($places as $p){$s=ggr_translit((string)$p);if($s==='')continue;foreach(array_keys($targets) as $t)$counts[$s][$t]=($counts[$s][$t]??0)+1;}$rows++;}
    $model=[];foreach($counts as $s=>$dist){$total=array_sum($dist);arsort($dist);$topKey=(string)array_key_first($dist);$top=(int)reset($dist);$model[$s]=['total'=>$total,'top'=>$topKey,'top_count'=>$top,'confidence'=>$total?round($top/$total,6):0,'dist'=>$dist];}
    return [$model,$rows];
}
function ggr_geo_evidence(array $sourcePlaces,array $target,array $model,?int $distance): array {
    if($distance!==null && $distance<=1000)return ['type'=>'coordinate','distance_m'=>$distance];
    if(fc_place(array_map('strval',$sourcePlaces),[(string)($target['region_name']??''),(string)($target['subregion_name']??'')]))return ['type'=>'direct_place'];
    $targetKeys=[];foreach([$target['region_name']??'',$target['subregion_name']??''] as $v){$k=ggr_translit((string)$v);if($k!=='')$targetKeys[$k]=true;}
    $best=null;foreach($sourcePlaces as $p){$s=ggr_translit((string)$p);if($s===''||!isset($model[$s]))continue;$m=$model[$s];foreach(array_keys($targetKeys) as $tk){$support=(int)($m['dist'][$tk]??0);$total=(int)$m['total'];$conf=$total?$support/$total:0;if($support>=3&&$conf>=0.95&&($best===null||$support>$best['support']))$best=['type'=>'learned_place','source_place'=>$p,'target_place'=>$tk,'support'=>$support,'total'=>$total,'confidence'=>round($conf,6)];}}
    return $best??['type'=>'none'];
}
function ggr_provider_name_index(PDO $db,string $provider,array $hotels): array {
    $idx=[];$localNames=[];
    if($provider==='anex'){
        $map=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC) as $r)$map[(int)$r['anex_hotel_id']]=(int)$r['catalog_hotel_id'];
        $st=[];foreach($db->query('SELECT anex_hotel_id,api_name,xml_name,xml_alternate_name FROM anex_hotels')->fetchAll(PDO::FETCH_ASSOC) as $r)$st[(int)$r['anex_hotel_id']]=$r;
        $obs=[];foreach($db->query('SELECT anex_hotel_id,hotel_name FROM anex_search_hotel_observations')->fetchAll(PDO::FETCH_ASSOC) as $r)$obs[(int)$r['anex_hotel_id']][]=(string)$r['hotel_name'];
        foreach($map as $eid=>$lid){if(!isset($hotels[$lid]))continue;$src=[];foreach(['api_name','xml_name','xml_alternate_name'] as $k)if(trim((string)($st[$eid][$k]??''))!=='')$src[]=(string)$st[$eid][$k];foreach($obs[$eid]??[] as $n)$src[]=$n;$src[]=(string)$hotels[$lid]['name'];foreach($src as $n){$k=ggr_translit($n);if($k!==''){$idx[(int)$hotels[$lid]['country_id']][$k][$lid]=true;$localNames[$lid][$n]=true;}}}
    }else{
        foreach($db->query("SELECT local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r){$lid=(int)$r['local_hotel_id'];if(!isset($hotels[$lid]))continue;$e=fc_evidence($r['evidence_json']??'');[$src]=ggr_source_geo($e);$src[]=(string)$hotels[$lid]['name'];foreach($src as $n){$k=ggr_translit($n);if($k!==''){$idx[(int)$hotels[$lid]['country_id']][$k][$lid]=true;$localNames[$lid][$n]=true;}}}
    }
    return [$idx,$localNames];
}
function ggr_bridge_target(int $country,array $sourceNames,array $index): ?int {
    $ids=[];foreach($sourceNames as $n){$k=ggr_translit((string)$n);if($k==='')continue;foreach(array_keys($index[$country][$k]??[]) as $id)$ids[(int)$id]=true;}return count($ids)===1?(int)array_key_first($ids):null;
}
function ggr_safe_candidate(array $row,array $source,array $target,array $targetNames,array $model,string $sourceClass): ?array {
    $guard=mbr_target_guard($source,$target);if($guard['coordinate_conflict'])return null;
    $sourceNames=array_map('strval',$row['source_names']??[]);$sourcePlaces=array_map('strval',$row['source_places']??[]);
    if(ggr_qualifier_conflict($sourceNames,$targetNames))return null;
    $exact=ggr_exact_name($sourceNames,$targetNames);$best=$row['best']??null;$fuzzy=is_array($best)&&((float)($best['score']??0)>=0.92)&&((int)($best['shared']??0)>=2)&&((float)($row['margin']??0)>=0.25);
    if(!$exact&&!$fuzzy)return null;
    $geo=ggr_geo_evidence($sourcePlaces,$target,$model,$guard['distance_m']);if(($geo['type']??'none')==='none')return null;
    $tokens=0;foreach($sourceNames as $n)$tokens=max($tokens,count(ggr_tokens($n)));if($tokens<2&&($geo['type']??'')!=='coordinate')return null;
    return $row+['bucket'=>'safe_consolidated_delta','graph_rule'=>$sourceClass,'name_evidence'=>$exact?'exact_transliterated':'strong_fuzzy_large_margin','geo_evidence'=>$geo,'guard'=>$guard,'target'=>mbr_row_target($target)];
}
function ggr_review(PDO $db,string $operation): array {
    if($operation!==GGR_OPERATION)throw new RuntimeException('operation_scope');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);[$hotels,$names,$strict,$broad,$places,$catalogScope]=mbr_catalog($db);$shaCountry=fc_sha_countries($db);[$geoModel,$learnRows]=ggr_learn_geo($db,$hotels);[$anexIndex,$anexProviderNames]=ggr_provider_name_index($db,'anex',$hotels);[$androIndex,$androProviderNames]=ggr_provider_name_index($db,'andromeda',$hotels);
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;
        $safe=[];$conflicts=[];$needs=[];$seen=[];$reason=[];$exam=['anex'=>0,'andromeda'=>0];
        $considerAnex=function(int $id,int $country,array $source)use(&$safe,&$conflicts,&$needs,&$reason,&$exam,$hotels,$names,$strict,$broad,$places,$androIndex,$androProviderNames,$excluded,$geoModel){$exam['anex']++;$row=mbr_review_anex($source,$id,$country,$hotels,$names,$strict,$broad,$places);$targetId=(int)($row['target']['local_hotel_id']??0);if(!$targetId)$targetId=ggr_bridge_target($country,$row['source_names']??[],$androIndex);if($targetId&&isset($excluded[$id][$targetId])){$row['bucket']='hard_conflict';$row['reason']='pair_exclusion_protected';$conflicts[]=$row;return;}if($targetId&&isset($hotels[$targetId])){$targetNames=$names[$targetId]??[(string)$hotels[$targetId]['name']];foreach(array_keys($androProviderNames[$targetId]??[]) as $n)$targetNames[]=$n;$c=ggr_safe_candidate($row,$source,$hotels[$targetId],$targetNames,$geoModel,'anex_full_graph');if($c!==null){$safe[]=$c;return;}}if(($row['bucket']??'')==='hard_conflict')$conflicts[]=$row;else{$needs[]=$row;$r=(string)($row['reason']??'unknown');$reason[$r]=($reason[$r]??0)+1;}};
        foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(int)$o['anex_hotel_id'];$country=(int)$o['country_id'];if(!isset(MBR_CORE8[$country])||isset($manual[$id])||isset($existing[$id]))continue;$seen[$id]=true;$s=$staging[$id]??[];$considerAnex($id,$country,['observed'=>true,'search_count'=>(int)$o['search_count'],'last_seen_utc'=>$o['last_seen_utc'],'names'=>[$o['hotel_name'],$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null]);}
        foreach($staging as $id=>$s){if(isset($seen[$id])||isset($manual[$id])||isset($existing[$id]))continue;$country=fc_country($s['api_country']??'');if(!$country||!isset(MBR_CORE8[$country]))continue;$considerAnex((int)$id,$country,['observed'=>false,'search_count'=>0,'names'=>[$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null]);}
        $latest=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$eid=(string)$o['external_hotel_id'];if(!isset($latest[$eid]))$latest[$eid]=$o;}
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$eid=(string)$r['external_hotel_id'];$ob=$latest[$eid]??null;$country=(int)($ob['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;$exam['andromeda']++;$row=mbr_review_andromeda($r,$ob,$country,$hotels,$names,$strict,$places);$prior=fc_evidence($r['evidence_json']??'');[$evNames,$evPlaces,$coords]=ggr_source_geo($prior);$source=['names'=>array_values(array_unique(array_merge($row['source_names']??[],$evNames))),'places'=>array_values(array_unique(array_merge($row['source_places']??[],$evPlaces)))];if($ob)$source+=$ob;if($coords){$source['latitude']=$coords[0][0];$source['longitude']=$coords[0][1];}$targetId=(int)($row['target']['local_hotel_id']??0);if(!$targetId)$targetId=ggr_bridge_target($country,$row['source_names']??[],$anexIndex);if($targetId&&isset($hotels[$targetId])){$targetNames=$names[$targetId]??[(string)$hotels[$targetId]['name']];foreach(array_keys($anexProviderNames[$targetId]??[]) as $n)$targetNames[]=$n;$row['source_names']=$source['names'];$row['source_places']=$source['places'];$c=ggr_safe_candidate($row,$source,$hotels[$targetId],$targetNames,$geoModel,'andromeda_full_graph');if($c!==null){$safe[]=$c;continue;}}if(($row['bucket']??'')==='hard_conflict')$conflicts[]=$row;else{$needs[]=$row;$rr=(string)($row['reason']??'unknown');$reason[$rr]=($reason[$rr]??0)+1;}}
        $dedup=[];foreach($safe as $r){$k=(string)$r['provider'].':'.(string)$r['external_id'];$dedup[$k]=$r;}$safe=array_values($dedup);usort($safe,static fn($a,$b)=>(int)($b['search_count']??0)<=>(int)($a['search_count']??0) ?: strcmp((string)$a['provider'],(string)$b['provider']) ?: strcmp((string)$a['external_id'],(string)$b['external_id']));
        $provider=['anex'=>0,'andromeda'=>0];$rules=[];$geoTypes=[];foreach($safe as $r){$provider[$r['provider']]++;$rules[$r['graph_rule']]=($rules[$r['graph_rule']]??0)+1;$gt=(string)($r['geo_evidence']['type']??'none');$geoTypes[$gt]=($geoTypes[$gt]??0)+1;}ksort($reason);ksort($rules);ksort($geoTypes);
        $db->commit();return ['status'=>'completed','operation_id'=>$operation,'mode'=>'server_current_full_core8_graph_read_only','database_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'catalog_scope'=>$catalogScope,'coverage'=>$coverage,'learned_geo'=>['accepted_rows'=>$learnRows,'source_places'=>count($geoModel)],'examined'=>$exam,'counts'=>['safe_consolidated_delta'=>count($safe),'safe_anex'=>$provider['anex'],'safe_andromeda'=>$provider['andromeda'],'needs_external_evidence'=>count($needs),'hard_conflict'=>count($conflicts)],'safe_by_rule'=>$rules,'safe_geo_evidence'=>$geoTypes,'remaining_reason_counts'=>$reason,'safe_candidates'=>$safe,'guards'=>['manual_protected'=>true,'existing_mapping_protected'=>true,'pair_exclusion_protected'=>true,'coordinate_conflict_block_m'=>5000,'qualifier_preservation'=>true,'single_token_learned_geo_auto_accept'=>false,'learned_geo_min_support'=>3,'learned_geo_min_confidence'=>0.95,'fuzzy_min_score'=>0.92,'fuzzy_min_shared_tokens'=>2,'fuzzy_min_margin'=>0.25,'database_write'=>false]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();return ['status'=>'failed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'reason'=>in_array($e->getMessage(),['operation_scope','country_contract_changed','hotel_scope_limit','alias_scope_limit'],true)?$e->getMessage():'runtime_failure','error_class'=>get_class($e)];}
}

if (PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    $root=realpath(__DIR__.'/../..'); require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $out=ggr_review(v2_data_db(),getenv('OPERATION_ID')?:GGR_OPERATION);echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n";exit(($out['status']??'')==='completed'?0:1);
}
