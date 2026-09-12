<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const HMGCR_OPERATION = 'hotel-match-global-cross-provider-rescue-1971-20260911-v1';
const HMGCR_COORD_BLOCK_M = 5000.0;
const HMGCR_COORD_DIRECT_M = 1000.0;
const HMGCR_COORD_SINGLE_M = 80.0;
const HMGCR_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function hmgcr_latin(string $v): string {
    $v=mb_strtolower($v,'UTF-8');
    return strtr($v,[
        'щ'=>'shch','ш'=>'sh','ч'=>'ch','ц'=>'ts','ю'=>'yu','я'=>'ya','ё'=>'e','ж'=>'zh','х'=>'kh',
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l',
        'м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','ы'=>'y','э'=>'e',
        'ь'=>'','ъ'=>'','ї'=>'yi','і'=>'i','є'=>'ye','ґ'=>'g',
    ]);
}
function hmgcr_script(string $v): string {
    $c=(bool)preg_match('/\p{Cyrillic}/u',$v);$l=(bool)preg_match('/[A-Za-z]/u',$v);
    return $c&&!$l?'cyr':($l&&!$c?'lat':($c&&$l?'mixed':'other'));
}
function hmgcr_cross_script(string $a,string $b): bool {
    $x=hmgcr_script($a);$y=hmgcr_script($b);
    return ($x==='cyr'&&$y==='lat')||($x==='lat'&&$y==='cyr')||($x==='mixed'&&in_array($y,['cyr','lat'],true))||($y==='mixed'&&in_array($x,['cyr','lat'],true));
}
function hmgcr_weak(): array {
    static $x=null;if($x!==null)return $x;
    $x=array_fill_keys(['hotel','hotels','resort','resorts','spa','the','and','club','apart','aparts','aparthotel','apartments','apartment','suite','suites','city','family','view','royal','grand','blue','sun','sea','holiday','elite','adult','adults','only','wellness','aqua','aquapark','park'],true);
    return $x;
}
function hmgcr_place_token_set(array $places): array {
    $out=[];foreach($places as $place){$n=fc_norm(hmgcr_latin((string)$place));foreach(explode(' ',$n) as $t)if($t!=='')$out[$t]=true;}return $out;
}
function hmgcr_place_like(string $token,array $places): bool {
    if(isset($places[$token]))return true;if(strlen($token)<4)return false;
    foreach(array_keys($places) as $p){if(strlen($p)<4)continue;$d=levenshtein($token,$p);if($d<=1)return true;similar_text($token,$p,$pct);if($pct>=86.0)return true;}return false;
}
function hmgcr_tokens(string $name,array $places=[]): array {
    $drop=hmgcr_place_token_set($places);$weak=hmgcr_weak();$out=[];
    foreach(fc_tokens($name,true) as $token){$t=fc_norm(hmgcr_latin((string)$token));if($t===''||hmgcr_place_like($t,$drop)||isset($weak[$t]))continue;$out[$t]=true;}
    return array_keys($out);
}
function hmgcr_critical(string $name): array {
    $crit=['annex'=>true,'beach'=>true,'garden'=>true,'north'=>true,'south'=>true];$out=[];
    foreach(fc_tokens($name,false) as $token){$t=fc_norm(hmgcr_latin((string)$token));if(isset($crit[$t]))$out[$t]=true;}
    $x=array_keys($out);sort($x,SORT_STRING);return $x;
}
function hmgcr_key(array $tokens): string {$x=$tokens;sort($x,SORT_STRING);return implode(' ',$x);}
function hmgcr_ordered(array $small,array $large): bool {if(!$small||count($small)>count($large))return false;$i=0;foreach($large as $t){if(isset($small[$i])&&$small[$i]===$t)$i++;if($i===count($small))return true;}return false;}
function hmgcr_place(array $source,array $target): bool {
    $a=[];$b=[];foreach($source as $v){$n=fc_norm(hmgcr_latin((string)$v));if($n!=='')$a[$n]=true;}foreach($target as $v){$n=fc_norm(hmgcr_latin((string)$v));if($n!=='')$b[$n]=true;}return (bool)array_intersect_key($a,$b);
}
function hmgcr_pair(string $source,string $target,array $sourcePlaces,array $targetPlaces): array {
    $s=hmgcr_tokens($source,$sourcePlaces);$t=hmgcr_tokens($target,$targetPlaces);$shared=array_values(array_intersect($s,$t));
    $den=count($s)+count($t);$score=$den?2.0*count($shared)/$den:0.0;$so=array_values(array_diff($s,$t));$to=array_values(array_diff($t,$s));
    $critical=hmgcr_critical($source)===hmgcr_critical($target);$exact=$s&&$t&&hmgcr_key($s)===hmgcr_key($t);$ordered=hmgcr_ordered($s,$t)||hmgcr_ordered($t,$s);$anchor=isset($s[0],$t[0])&&$s[0]===$t[0];
    return ['score'=>round($score,6),'shared'=>count($shared),'shared_tokens'=>$shared,'source_tokens'=>$s,'target_tokens'=>$t,'source_only'=>$so,'target_only'=>$to,'token_diff'=>count($so)+count($to),'critical_ok'=>$critical,'exact_bag'=>$exact,'ordered'=>$ordered,'anchor_ok'=>$anchor,'cross_script'=>hmgcr_cross_script($source,$target),'source'=>$source,'target'=>$target];
}
function hmgcr_best_pair(array $sourceNames,array $targetNames,array $sourcePlaces,array $targetPlaces): array {
    $best=['score'=>0.0,'shared'=>0,'critical_ok'=>false,'exact_bag'=>false,'ordered'=>false,'anchor_ok'=>false,'source'=>'','target'=>''];
    foreach($sourceNames as $s){$s=trim((string)$s);if($s==='')continue;foreach($targetNames as $t){$t=trim((string)$t);if($t==='')continue;$p=hmgcr_pair($s,$t,$sourcePlaces,$targetPlaces);if(!($p['critical_ok']??false))continue;if((int)$p['exact_bag']>(int)($best['exact_bag']??false)||((bool)$p['exact_bag']===(bool)($best['exact_bag']??false)&&($p['shared']>$best['shared']||($p['shared']===$best['shared']&&$p['score']>$best['score']))))$best=$p;}}
    return $best;
}
function hmgcr_route(array $pair,?float $distance,bool $place,float $margin): ?string {
    if(!($pair['critical_ok']??false)||(int)($pair['shared']??0)<1)return null;
    if($distance!==null&&$distance>HMGCR_COORD_BLOCK_M)return null;
    $shared=(int)$pair['shared'];$direct=$place||($distance!==null&&$distance<=HMGCR_COORD_DIRECT_M);
    if(($pair['exact_bag']??false)&&$shared>=2&&($direct||$shared>=3))return ($pair['cross_script']??false)?'cross_provider_translit_exact':'cross_provider_generic_free_exact';
    if(($pair['exact_bag']??false)&&$shared===1&&$place&&$distance!==null&&$distance<=HMGCR_COORD_SINGLE_M)return 'cross_provider_single_token_ultratight';
    if($direct&&$shared>=2&&(float)$pair['score']>=0.80&&$margin>=0.15&&(int)$pair['token_diff']<=2&&(($pair['anchor_ok']??false)||($pair['ordered']??false)))return 'cross_provider_high_overlap_direct_geo';
    if(!$direct&&$shared>=3&&(float)$pair['score']>=0.90&&$margin>=0.20&&(int)$pair['token_diff']<=1&&(($pair['anchor_ok']??false)||($pair['ordered']??false)))return 'cross_provider_high_overlap_no_geo';
    return null;
}
function hmgcr_build_index(array $bridgeSet,array $hotels,array $names): array {
    $exact=[];$token=[];$targets=[];
    foreach(array_keys($bridgeSet) as $id){$id=(int)$id;if(!isset($hotels[$id]))continue;$h=$hotels[$id];$country=(int)$h['country_id'];$places=[(string)$h['region_name'],(string)$h['subregion_name']];$targets[$id]=true;
        foreach($names[$id]??[(string)$h['name']] as $name){$tokens=hmgcr_tokens((string)$name,$places);if(!$tokens)continue;$key=hmgcr_key($tokens);if($key!=='')$exact[$country][$key][$id]=true;foreach($tokens as $t)$token[$country][$t][$id]=true;}
    }
    return ['exact'=>$exact,'token'=>$token,'targets'=>$targets];
}
function hmgcr_source_candidate_ids(array $sourceNames,array $sourcePlaces,int $country,array $index): array {
    $exact=[];$sourceTokens=[];
    foreach($sourceNames as $name){$tokens=hmgcr_tokens((string)$name,$sourcePlaces);if(!$tokens)continue;$key=hmgcr_key($tokens);foreach(array_keys($index['exact'][$country][$key]??[]) as $id)$exact[(int)$id]=true;foreach($tokens as $t)$sourceTokens[$t]=true;}
    $hits=[];foreach(array_keys($sourceTokens) as $t)foreach(array_keys($index['token'][$country][$t]??[]) as $id)$hits[(int)$id]=($hits[(int)$id]??0)+1;
    $fuzzy=[];foreach($hits as $id=>$n)if($n>=2)$fuzzy[(int)$id]=true;
    return [array_map('intval',array_keys($exact)),array_map('intval',array_keys($fuzzy))];
}
function hmgcr_decide(array $source,int $country,array $index,array $hotels,array $names,array $sameProviderClaims,array $excluded=[]): array {
    [$exactIds,$fuzzyIds]=hmgcr_source_candidate_ids($source['names'],$source['places'],$country,$index);$pool=[];
    foreach(array_unique(array_merge($exactIds,$fuzzyIds)) as $id){$id=(int)$id;if(!isset($hotels[$id])||(int)$hotels[$id]['country_id']!==$country||isset($sameProviderClaims[$id])||isset($excluded[$id]))continue;$target=$hotels[$id];$targetPlaces=[(string)$target['region_name'],(string)$target['subregion_name']];$pair=hmgcr_best_pair($source['names'],$names[$id]??[(string)$target['name']],$source['places'],$targetPlaces);if(!($pair['critical_ok']??false))continue;$distance=fc_dist($source['latitude'],$source['longitude'],$target['latitude']??null,$target['longitude']??null);$place=hmgcr_place($source['places'],$targetPlaces);$pool[]=['id'=>$id,'pair'=>$pair,'distance_m'=>$distance===null?null:round($distance,2),'place_match'=>$place,'target'=>$target];}
    if(!$pool)return ['bucket'=>'near','reason'=>'no_opposite_provider_bridge_candidate'];
    $exactPool=array_values(array_filter($pool,static fn($x)=>(bool)($x['pair']['exact_bag']??false)));if(count($exactPool)>1)return ['bucket'=>'near','reason'=>'opposite_provider_exact_ambiguous','candidate_ids'=>array_map(static fn($x)=>(int)$x['id'],$exactPool)];if(count($exactPool)===1)$pool=$exactPool;
    usort($pool,static fn($a,$b)=>$b['pair']['shared']<=>$a['pair']['shared'] ?: $b['pair']['score']<=>$a['pair']['score'] ?: $a['id']<=>$b['id']);
    $best=$pool[0];$second=$pool[1]??null;$margin=$second?((float)$best['pair']['score']-(float)$second['pair']['score']):1.0;
    if(($best['pair']['exact_bag']??false)&&$best['distance_m']!==null&&(float)$best['distance_m']>HMGCR_COORD_BLOCK_M)return ['bucket'=>'hard_conflict','reason'=>'unique_bridge_exact_coordinate_conflict_gt_5km','target_local_hotel_id'=>$best['id'],'distance_m'=>$best['distance_m'],'pair'=>$best['pair']];
    $route=hmgcr_route($best['pair'],$best['distance_m']===null?null:(float)$best['distance_m'],(bool)$best['place_match'],$margin);
    if($route===null)return ['bucket'=>'near','reason'=>count($pool)>1&&$margin<0.15?'bridge_winner_margin_small':'bridge_identity_or_geo_insufficient','target_local_hotel_id'=>$best['id'],'score_margin'=>round($margin,6),'distance_m'=>$best['distance_m'],'pair'=>$best['pair']];
    if(count($pool)>1&&$margin<0.15)return ['bucket'=>'near','reason'=>'bridge_winner_margin_small','target_local_hotel_id'=>$best['id'],'score_margin'=>round($margin,6),'distance_m'=>$best['distance_m'],'pair'=>$best['pair']];
    return ['bucket'=>'prepared','reason'=>$route,'target_local_hotel_id'=>$best['id'],'target_name'=>$best['target']['name'],'target_region'=>$best['target']['region_name'],'target_subregion'=>$best['target']['subregion_name'],'distance_m'=>$best['distance_m'],'place_match'=>$best['place_match'],'score_margin'=>round($margin,6),'pair'=>$best['pair']];
}
function hmgcr_andromeda_source(array $identity,?array $obs): array {
    $prior=fc_evidence($identity['evidence_json']??'');$s=$prior['source']??[];if(!is_array($s))$s=[];$g=$prior['geography']??[];if(!is_array($g))$g=[];$coord=$s;if($obs)$coord+=$obs;[$lat,$lon]=mbr_coord($coord);
    return ['names'=>array_values(array_unique(array_filter([(string)($s['name']??''),(string)($s['lName']??''),(string)($obs['hotel_name']??'')],static fn($v)=>trim($v)!==''))),'places'=>array_values(array_unique(array_filter([(string)($s['town']??''),(string)($g['town']??''),(string)($g['parent']??''),(string)($obs['region_name']??'')],static fn($v)=>trim($v)!==''))),'latitude'=>$lat,'longitude'=>$lon,'category'=>mbr_numeric_category($s),'live'=>$obs!==null];
}
function hmgcr_review(PDO $db,string $operation=HMGCR_OPERATION): array {
    if($operation!==HMGCR_OPERATION)throw new RuntimeException('HMGCR_OPERATION_SCOPE');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);[$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);$shaCountry=fc_sha_countries($db);[$anexLocal,$andromedaLocal]=mbr_local_sets($db);$anIndex=hmgcr_build_index($anexLocal,$hotels,$names);$andIndex=hmgcr_build_index($andromedaLocal,$hotels,$names);
        $andClaims=[];foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$andClaims[(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];
        $anClaims=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_ASSOC) as $r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];foreach($db->query("SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r)$anClaims[(int)$r['catalog_hotel_id']][]=(int)$r['anex_hotel_id'];
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$mapped=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;
        $latest=[];$andObsCount=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];$andObsCount[$id]=($andObsCount[$id]??0)+1;if(!isset($latest[$id]))$latest[$id]=$o;}
        $prepared=[];$near=[];$hard=[];$stats=['andromeda_examined'=>0,'anex_observed_examined'=>0,'anex_staging_examined'=>0,'protected'=>0,'country_unknown'=>0,'prepared'=>0,'prepared_andromeda'=>0,'prepared_anex'=>0,'prepared_live'=>0,'hard_conflict'=>0,'near'=>0,'route_exact'=>0,'route_translit_exact'=>0,'route_single'=>0,'route_fuzzy_geo'=>0,'route_fuzzy_no_geo'=>0];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$country=(int)($obs['country_id']??0);if(!isset(HMGCR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(HMGCR_CORE8[$country])){$stats['country_unknown']++;continue;}$stats['andromeda_examined']++;$source=hmgcr_andromeda_source($r,$obs);$d=hmgcr_decide($source,$country,$anIndex,$hotels,$names,$andClaims,[]);$row=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'live'=>(int)($andObsCount[$external]??0)>0,'observation_count'=>(int)($andObsCount[$external]??0),'source_names'=>$source['names'],'source_places'=>$source['places'],'source_category'=>$source['category']]+$d;if($d['bucket']==='prepared')$prepared[]=$row;elseif($d['bucket']==='hard_conflict')$hard[]=$row;else$near[]=$row;}
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;$seen=[];$observations=$db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC);
        $processAnex=function(int $id,int $country,array $obs,array $stage,bool $observed)use(&$prepared,&$near,&$hard,&$stats,$andIndex,$hotels,$names,$anClaims,$excluded,$manual,$mapped){if(isset($manual[$id])||isset($mapped[$id])){$stats['protected']++;return;}$source=['names'=>array_values(array_unique(array_filter([(string)($obs['hotel_name']??''),(string)($stage['api_name']??''),(string)($stage['xml_name']??''),(string)($stage['xml_alternate_name']??'')],static fn($v)=>trim($v)!==''))),'places'=>array_values(array_unique(array_filter([(string)($stage['api_region']??''),(string)($stage['api_town']??'')],static fn($v)=>trim($v)!==''))),'latitude'=>$stage['latitude']??null,'longitude'=>$stage['longitude']??null];$d=hmgcr_decide($source,$country,$andIndex,$hotels,$names,$anClaims,$excluded[$id]??[]);$row=['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'live'=>$observed,'observation_count'=>(int)($obs['search_count']??0),'source_names'=>$source['names'],'source_places'=>$source['places']]+$d;if($d['bucket']==='prepared')$prepared[]=$row;elseif($d['bucket']==='hard_conflict')$hard[]=$row;else$near[]=$row;};
        foreach($observations as $o){$id=(int)$o['anex_hotel_id'];if(isset($seen[$id]))continue;$seen[$id]=true;$country=(int)$o['country_id'];if(!isset(HMGCR_CORE8[$country]))continue;$stats['anex_observed_examined']++;$processAnex($id,$country,$o,$staging[$id]??[],true);}
        foreach($staging as $id=>$s){$id=(int)$id;if(isset($seen[$id])||isset($manual[$id])||isset($mapped[$id]))continue;$country=(int)(fc_country($s['api_country']??'')??0);if(!isset(HMGCR_CORE8[$country]))continue;$stats['anex_staging_examined']++;$processAnex($id,$country,[],$s,false);}
        $byTarget=[];foreach($prepared as $i=>$r)$byTarget[$r['provider'].':'.(int)$r['target_local_hotel_id']][]=$i;$demote=[];foreach($byTarget as $idxs)if(count($idxs)>1)foreach($idxs as $i)$demote[$i]=true;if($demote){$kept=[];foreach($prepared as $i=>$r){if(isset($demote[$i])){$r['bucket']='near';$r['reason']='same_provider_target_collision';$near[]=$r;}else$kept[]=$r;}$prepared=$kept;}
        foreach($prepared as $r){$stats['prepared']++;if($r['provider']==='andromeda')$stats['prepared_andromeda']++;else$stats['prepared_anex']++;if($r['live'])$stats['prepared_live']++;if($r['reason']==='cross_provider_generic_free_exact')$stats['route_exact']++;elseif($r['reason']==='cross_provider_translit_exact')$stats['route_translit_exact']++;elseif($r['reason']==='cross_provider_single_token_ultratight')$stats['route_single']++;elseif($r['reason']==='cross_provider_high_overlap_direct_geo')$stats['route_fuzzy_geo']++;elseif($r['reason']==='cross_provider_high_overlap_no_geo')$stats['route_fuzzy_no_geo']++;}
        $stats['near']=count($near);$stats['hard_conflict']=count($hard);$reasonCounts=[];foreach(array_merge($near,$hard) as $r)$reasonCounts[$r['reason']]=($reasonCounts[$r['reason']]??0)+1;ksort($reasonCounts);usort($prepared,static fn($a,$b)=>(($a['live']?0:1)<=>($b['live']?0:1))?:($b['observation_count']<=>$a['observation_count'])?:strcmp((string)$a['provider'],(string)$b['provider'])?:strcmp((string)$a['external_id'],(string)$b['external_id']));$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'mode'=>'global_cross_provider_current_read_only','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'catalog_scope'=>$scope,'bridge_targets'=>['anex'=>count($anIndex['targets']),'andromeda'=>count($andIndex['targets'])],'stats'=>$stats,'blocked_reasons'=>$reasonCounts,'prepared'=>$prepared,'hard_conflicts'=>$hard,'near'=>$near,'guards'=>['core8_only'=>true,'opposite_provider_bridge_required'=>true,'same_provider_target_claim_blocks'=>true,'pair_exclusions_protected'=>true,'coordinate_conflict_auto_block_m'=>HMGCR_COORD_BLOCK_M,'generic_hotel_resort_spa_not_identity'=>true,'critical_qualifiers_preserved'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH'],'stars_not_identity'=>true,'russia_abkhazia_excluded'=>true]];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: use guarded CURRENT review workflow\n");exit(64);}
