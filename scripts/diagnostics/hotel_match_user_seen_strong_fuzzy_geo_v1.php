<?php
declare(strict_types=1);

const HMFG_OPERATION='hotel-match-user-seen-strong-fuzzy-geo-1971-20260918-v1';
const HMFG_MAX_ACTIVE=200000;
const HMFG_MAX_ALIAS=1000000;

function hmfg_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function hmfg_table(PDO $pdo,string $t): bool {
    $q=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$t]);return $q->fetchColumn()!==false;
}
function hmfg_cols(PDO $pdo,string $t): array {
    $q=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$t]);$o=[];foreach($q->fetchAll(PDO::FETCH_COLUMN) as $c)$o[(string)$c]=true;return $o;
}
function hmfg_country_name(string $v): string {
    $v=fc_norm($v);$map=[
      'turkey'=>'турция','turkiye'=>'турция','türkiye'=>'турция','egypt'=>'египет',
      'uae'=>'оаэ','united arab emirates'=>'оаэ','emirates'=>'оаэ',
      'thailand'=>'таиланд','vietnam'=>'вьетнам','maldives'=>'мальдивы','cuba'=>'куба',
      'sri lanka'=>'шри ланка','china'=>'китай','mauritius'=>'маврикий','tanzania'=>'танзания',
      'qatar'=>'катар','russia'=>'россия','russian federation'=>'россия','indonesia'=>'индонезия',
      'india'=>'индия','tunisia'=>'тунис','morocco'=>'марокко','seychelles'=>'сейшелы',
      'oman'=>'оман','georgia'=>'грузия','armenia'=>'армения','azerbaijan'=>'азербайджан',
      'uzbekistan'=>'узбекистан','kazakhstan'=>'казахстан','bahrain'=>'бахрейн','jordan'=>'иордания',
    ];return $map[$v]??$v;
}
function hmfg_add_name(array &$set,mixed $v): void {
    if(is_scalar($v)&&trim((string)$v)!=='')$set[trim((string)$v)]=true;
}
function hmfg_walk(mixed $v,array &$names,array &$places,array &$states,array &$coords,int $depth=0): void {
    if($depth>10||!is_array($v))return;
    foreach($v as $k=>$x){
        $key=mb_strtolower((string)$k,'UTF-8');
        if(is_scalar($x)){
            if(in_array($key,['name','lname','hotel','hotelname','hotel_name'],true))hmfg_add_name($names,$x);
            if(in_array($key,['region','regionname','region_name','town','townname','town_name','city','cityname','resort','resortname'],true))hmfg_add_name($places,$x);
            if(in_array($key,['statekey','state_key'],true)){
                $s=(string)$x;if(preg_match('/^[1-9][0-9]{0,9}$/D',$s))$states[$s]=true;
            }
            if(in_array($key,['latitude','lat'],true)&&is_numeric($x)&&!isset($coords['latitude']))$coords['latitude']=(float)$x;
            if(in_array($key,['longitude','lon','lng'],true)&&is_numeric($x)&&!isset($coords['longitude']))$coords['longitude']=(float)$x;
        }elseif(is_array($x))hmfg_walk($x,$names,$places,$states,$coords,$depth+1);
    }
}
function hmfg_source(string $provider,string $external,array $names,array $places,array $countries,array $coords=[]): array {
    $countries=array_values(array_unique(array_filter(array_map('intval',$countries),fn($x)=>$x>0)));sort($countries,SORT_NUMERIC);
    $nameList=array_values(array_filter(array_map('strval',array_keys($names)),fn($x)=>preg_match('/\\p{L}/u',$x)===1));
    $placeList=array_values(array_filter(array_map('strval',array_keys($places)),fn($x)=>preg_match('/\\p{L}/u',$x)===1));
    return ['provider'=>$provider,'external_id'=>$external,'names'=>$nameList,
      'places'=>$placeList,'countries'=>$countries,
      'latitude'=>$coords['latitude']??null,'longitude'=>$coords['longitude']??null];
}
function hmfg_merge_target(array &$tokenIndex,array &$targetNames,int $country,int $local,array $names,array $wanted): bool {
    $names=array_values(array_filter(array_map('strval',$names),fn($x)=>preg_match('/\\p{L}/u',$x)===1));
    if(!$names)return false;
    $sets=hmsbf_sets($names);$kept=[];$hit=false;
    foreach($sets as $key=>$tokens){
        $use=false;foreach($tokens as $token)if(isset($wanted[$country][$token])){$use=true;break;}
        if(!$use)continue;$hit=true;$kept[$key]=$tokens;
    }
    if(!$hit)return false;
    foreach($kept as $key=>$tokens){
        $targetNames[$country][$local][$key]=$tokens;
        foreach(array_keys(array_fill_keys($tokens,true)) as $token)$tokenIndex[$country][$token][$local]=true;
    }
    return true;
}

if(in_array('--self-test',$argv??[],true)){
    define('FC_LIBRARY_ONLY',true);
    require_once __DIR__.'/hotel_match_current_supplier_fuzzy_bridge.php';
    $r=hmsbf_rank(['Grand Bagoz Hotel'],4,[4=>['grand'=>[1=>true],'bagoz'=>[1=>true]]],[4=>[1=>hmsbf_sets(['Grand Bagoz'])]]);
    if(($r['status']??'')!=='exact_key_skipped')throw new RuntimeException('exact_skip');
    $m=hmsbf_metric(['alpha','beach','palace'],['alpha','beach','palace','antalya']);
    if($m['common']!==3||$m['symmetric']<0.74)throw new RuntimeException('metric');
    echo "MATCH_USER_SEEN_STRONG_FUZZY_GEO_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=realpath((string)getenv('MATCH_OPERATION_DIR'));
if(!$root||!$opDir)throw new RuntimeException('runtime_paths');
define('FC_LIBRARY_ONLY',true);
require_once $opDir.'/payload/hotel_match_current_supplier_fuzzy_bridge.php';
require_once $opDir.'/payload/anex-search-mapping-registry.php';
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbf;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['tour_price_observations','catalog_hotels','hotel_aliases','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities'] as $t)
    if(!hmfg_table($pdo,$t))throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $anexTargets=[];
    foreach(hmfg_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $r){
        $eid=(string)$r['anex_hotel_id'];$target=$registry->resolve('anex_online',$eid,'preview');if(is_int($target)&&$target>0)$anexTargets[$target]=true;
    }
    $andTargets=[];
    foreach(hmfg_rows($pdo,"SELECT DISTINCT i.local_hotel_id FROM andromeda_hotel_identities i
      JOIN catalog_hotels h ON h.id=i.local_hotel_id
      WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL") as $r){
        $id=(int)$r['local_hotel_id'];if($id>0)$andTargets[$id]=true;
    }

    $obs=hmfg_rows($pdo,"SELECT hotel_id,MAX(observed_at) last_observed_at,COUNT(*) observation_rows
      FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id");
    $seen=[];foreach($obs as $r){$id=(int)$r['hotel_id'];if($id>0)$seen[$id]=['last_observed_at'=>(string)$r['last_observed_at'],'observation_rows'=>(int)$r['observation_rows']];}
    $seenIds=array_keys($seen);$sp=implode(',',array_fill(0,count($seenIds),'?'));
    $frontier=[];foreach(hmfg_rows($pdo,"SELECT id,country_id,country_name,name,region_name,subregion_name,is_active FROM catalog_hotels WHERE id IN ($sp)",$seenIds) as $r){
        $id=(int)$r['id'];if((int)$r['is_active']!==1||isset($anexTargets[$id])||isset($andTargets[$id]))continue;
        $frontier[$id]=['hotel_id'=>$id,'country_id'=>(int)$r['country_id'],'country_name'=>(string)$r['country_name'],
          'name'=>(string)$r['name'],'region_name'=>(string)$r['region_name'],'subregion_name'=>(string)$r['subregion_name'],
          'last_observed_at'=>$seen[$id]['last_observed_at'],'observation_rows'=>$seen[$id]['observation_rows']];
    }
    if(!$frontier)throw new RuntimeException('frontier_empty');
    $frontierCountries=[];foreach($frontier as $r)$frontierCountries[(int)$r['country_id']]=true;
    $countryIds=array_keys($frontierCountries);sort($countryIds,SORT_NUMERIC);$cp=implode(',',array_fill(0,count($countryIds),'?'));

    $countryNameToId=[];
    foreach(hmfg_rows($pdo,"SELECT DISTINCT country_id,country_name FROM catalog_hotels WHERE is_active=1 AND country_id IN ($cp)",$countryIds) as $r){
        $n=hmfg_country_name((string)$r['country_name']);if($n!=='')$countryNameToId[$n][(int)$r['country_id']]=true;
    }

    // Accepted stateKey -> current local country, no guessed numeric map.
    $stateCountries=[];
    foreach(hmfg_rows($pdo,"SELECT i.evidence_json,h.country_id FROM andromeda_hotel_identities i
      JOIN catalog_hotels h ON h.id=i.local_hotel_id
      WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL") as $r){
        $n=[];$p=[];$states=[];$coords=[];
        $ev=fc_evidence($r['evidence_json']??'');hmfg_walk($ev,$n,$p,$states,$coords);
        foreach(array_keys($states) as $state)$stateCountries[$state][(int)$r['country_id']]=true;
    }
    $stateUnique=[];$stateConflicts=[];
    foreach($stateCountries as $state=>$cs){if(count($cs)===1)$stateUnique[$state]=(int)array_key_first($cs);else$stateConflicts[$state]=array_map('intval',array_keys($cs));}

    $manualAnex=[];foreach(hmfg_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_decisions') as $r)$manualAnex[(string)$r['anex_hotel_id']]=true;
    $existingAnex=[];foreach(hmfg_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings') as $r)$existingAnex[(string)$r['anex_hotel_id']]=true;
    $excluded=[];if(hmfg_table($pdo,'anex_review_pair_exclusions'))
      foreach(hmfg_rows($pdo,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions') as $r)
        $excluded[(string)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;

    // Current source observations.
    $anexObs=[];if(hmfg_table($pdo,'anex_search_hotel_observations'))
      foreach(hmfg_rows($pdo,'SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id') as $r){
        $id=(string)$r['anex_hotel_id'];if(!isset($anexObs[$id]))$anexObs[$id]=$r;
      }
    $anexStage=[];if(hmfg_table($pdo,'anex_hotels'))
      foreach(hmfg_rows($pdo,'SELECT * FROM anex_hotels ORDER BY anex_hotel_id') as $r)$anexStage[(string)$r['anex_hotel_id']]=$r;

    $andObs=[];if(hmfg_table($pdo,'andromeda_search_hotel_observations'))
      foreach(hmfg_rows($pdo,"SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id") as $r){
        $id=(string)$r['external_hotel_id'];if(!isset($andObs[$id]))$andObs[$id]=$r;
      }

    $sources=[];
    $allAnex=$anexStage;foreach($anexObs as $id=>$o)if(!isset($allAnex[$id]))$allAnex[$id]=[];
    foreach($allAnex as $id=>$stage){
        $id=(string)$id;if(isset($manualAnex[$id])||isset($existingAnex[$id]))continue;
        $o=$anexObs[$id]??[];$names=[];$places=[];$countries=[];$coords=[];
        foreach(['api_name','xml_name','xml_alternate_name'] as $k)hmfg_add_name($names,$stage[$k]??'');
        hmfg_add_name($names,$o['hotel_name']??'');
        foreach(['api_region','api_town'] as $k)hmfg_add_name($places,$stage[$k]??'');hmfg_add_name($places,$o['region_name']??'');
        if(isset($o['country_id'])&&(int)$o['country_id']>0)$countries[(int)$o['country_id']]=true;
        if(isset($o['country_name']))foreach(array_keys($countryNameToId[hmfg_country_name((string)$o['country_name'])]??[]) as $cid)$countries[(int)$cid]=true;
        if(isset($stage['api_country']))foreach(array_keys($countryNameToId[hmfg_country_name((string)$stage['api_country'])]??[]) as $cid)$countries[(int)$cid]=true;
        if(is_numeric($stage['latitude']??null))$coords['latitude']=(float)$stage['latitude'];
        if(is_numeric($stage['longitude']??null))$coords['longitude']=(float)$stage['longitude'];
        if(count($countries)!==1||!isset($frontierCountries[(int)array_key_first($countries)])||!$names)continue;
        $sources['anex:'.$id]=hmfg_source('anex',$id,$names,$places,array_keys($countries),$coords)+['search_count'=>(int)($o['search_count']??0)];
    }

    $andCols=hmfg_cols($pdo,'andromeda_hotel_identities');$sel=['external_hotel_id','evidence_json'];
    foreach(['country_id'] as $x)if(isset($andCols[$x]))$sel[]=$x;
    foreach(hmfg_rows($pdo,'SELECT '.implode(',',$sel)." FROM andromeda_hotel_identities
      WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL") as $r){
        $id=(string)$r['external_hotel_id'];$o=$andObs[$id]??[];$names=[];$places=[];$countries=[];$states=[];$coords=[];
        hmfg_add_name($names,$o['hotel_name']??'');hmfg_add_name($places,$o['region_name']??'');
        if(isset($o['country_id'])&&(int)$o['country_id']>0)$countries[(int)$o['country_id']]=true;
        if(isset($o['country_name']))foreach(array_keys($countryNameToId[hmfg_country_name((string)$o['country_name'])]??[]) as $cid)$countries[(int)$cid]=true;
        if(isset($r['country_id'])&&(int)$r['country_id']>0)$countries[(int)$r['country_id']]=true;
        $ev=fc_evidence($r['evidence_json']??'');hmfg_walk($ev,$names,$places,$states,$coords);
        if(!$countries)foreach(array_keys($states) as $state)if(isset($stateUnique[$state]))$countries[$stateUnique[$state]]=true;
        if(count($countries)!==1||!isset($frontierCountries[(int)array_key_first($countries)])||!$names)continue;
        $sources['andromeda:'.$id]=hmfg_source('andromeda',$id,$names,$places,array_keys($countries),$coords)+['search_count'=>0];
    }

    // Build only tokens actually present in supplier source names.
    $wanted=[];
    foreach($sources as $src){
        $cid=$src['countries'][0];foreach(hmsbf_sets($src['names']) as $tokens)foreach($tokens as $token)$wanted[$cid][$token]=true;
    }

    $tokenIndex=[];$targetNames=[];$hotels=[];$activeCount=0;
    $sql="SELECT h.id,h.country_id,h.name,h.region_name,h.subregion_name,h.category,
                 COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude
            FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id
           WHERE h.is_active=1 AND h.country_id IN ($cp)";
    $stmt=$pdo->prepare($sql);$stmt->execute($countryIds);
    while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
        if(++$activeCount>HMFG_MAX_ACTIVE)throw new RuntimeException('active_catalog_bound');
        $id=(int)$r['id'];$cid=(int)$r['country_id'];
        if(hmfg_merge_target($tokenIndex,$targetNames,$cid,$id,[(string)$r['name']],$wanted))$hotels[$id]=$r;
    }
    $stmt->closeCursor();

    $aliasCount=0;$relevantAliases=0;
    $sql="SELECT a.hotel_id,a.alias,h.country_id,h.name,h.region_name,h.subregion_name,h.category,
                 COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude
            FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id
            LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id
           WHERE h.is_active=1 AND h.country_id IN ($cp)";
    $stmt=$pdo->prepare($sql);$stmt->execute($countryIds);
    while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
        if(++$aliasCount>HMFG_MAX_ALIAS)throw new RuntimeException('alias_bound');
        $id=(int)$r['hotel_id'];$cid=(int)$r['country_id'];
        if(hmfg_merge_target($tokenIndex,$targetNames,$cid,$id,[(string)$r['alias']],$wanted)){
            ++$relevantAliases;if(!isset($hotels[$id]))$hotels[$id]=['id'=>$id,'country_id'=>$cid,'name'=>$r['name'],
              'region_name'=>$r['region_name'],'subregion_name'=>$r['subregion_name'],'category'=>$r['category'],
              'latitude'=>$r['latitude'],'longitude'=>$r['longitude']];
        }
    }
    $stmt->closeCursor();

    $rows=[];$rankStats=[];$strong=0;$needs=0;
    foreach($sources as $src){
        $cid=$src['countries'][0];$rank=hmsbf_rank($src['names'],$cid,$tokenIndex,$targetNames);
        $status=(string)($rank['status']??'unknown');$rankStats[$status]=($rankStats[$status]??0)+1;
        if($status!=='ranked')continue;
        $target=hmsbf_target_row($rank,$hotels);if(!$target)continue;$local=(int)$target['id'];
        if(!isset($frontier[$local]))continue;
        $sourceForGeo=['places'=>$src['places'],'latitude'=>$src['latitude'],'longitude'=>$src['longitude']];
        $geo=hmsb_direct_geo($sourceForGeo,$target);
        $pairExcluded=$src['provider']==='anex'&&isset($excluded[$src['external_id']][$local]);
        $rowStatus=hmsbf_status($rank,$geo,$pairExcluded);
        if($rowStatus==='strong_fuzzy_geo')$strong++;elseif($rowStatus==='strong_fuzzy_needs_independent_evidence')$needs++;
        $rows[]=['provider'=>$src['provider'],'external_id'=>$src['external_id'],'country_id'=>$cid,
          'target_local_id'=>$local,'target'=>hmsb_target($target),'source_names'=>$src['names'],'source_places'=>$src['places'],
          'score'=>$rank['best']['score'],'common_tokens'=>$rank['best']['common'],
          'symmetric_coverage'=>round((float)$rank['best']['symmetric'],4),
          'best_source_key'=>$rank['best']['source_key'],'best_target_key'=>$rank['best']['target_key'],
          'runner_up_local_id'=>$rank['runner_up']['local_id']??null,'runner_up_score'=>$rank['runner_up']['score']??null,
          'margin'=>$rank['margin'],'candidate_count'=>$rank['candidate_count'],
          'qualifier_conflict'=>$rank['best']['qualifier_conflict'],'geo'=>$geo,'pair_excluded'=>$pairExcluded,
          'search_count'=>$src['search_count'],'status'=>$rowStatus,'not_write_authority'=>true];
    }
    $rows=hmsbf_collision_hold($rows);
    $prepared=array_values(array_filter($rows,fn($r)=>($r['status']??'')==='strong_fuzzy_geo'));
    $targetRows=[];$byTarget=[];
    foreach($prepared as $r)$byTarget[(int)$r['target_local_id']][$r['provider']][]=$r['external_id'];
    foreach($byTarget as $local=>$providers)$targetRows[]=$frontier[$local]+['providers'=>$providers,'source_identity_count'=>array_sum(array_map('count',$providers))];
    usort($targetRows,fn($a,$b)=>$b['observation_rows']<=>$a['observation_rows'] ?: strcmp($b['last_observed_at'],$a['last_observed_at']) ?: $a['hotel_id']<=>$b['hotel_id']);
    $statusCounts=[];$providerCounts=[];
    foreach($rows as $r){$s=$r['status'];$statusCounts[$s]=($statusCounts[$s]??0)+1;$providerCounts[$r['provider']][$s]=($providerCounts[$r['provider']][$s]??0)+1;}
    ksort($statusCounts);ksort($providerCounts);ksort($rankStats);

    $result=['operation'=>HMFG_OPERATION,'status'=>'read_only_complete','frontier_count'=>count($frontier),
      'source_rows_considered'=>count($sources),'active_local_rows_scanned'=>$activeCount,'alias_rows_scanned'=>$aliasCount,'relevant_alias_rows'=>$relevantAliases,
      'state_key_unique_count'=>count($stateUnique),'state_key_conflict_count'=>count($stateConflicts),
      'rank_status_counts'=>$rankStats,'status_counts'=>$statusCounts,'provider_status_counts'=>$providerCounts,
      'prepared_identity_count'=>count($prepared),'prepared_unique_local_hotels'=>count($targetRows),
      'prepared_both_provider_local_hotels'=>count(array_filter($targetRows,fn($r)=>isset($r['providers']['anex'],$r['providers']['andromeda']))),
      'prepared_rows'=>$prepared,'candidate_targets'=>$targetRows,
      'all_ranked_rows'=>$rows,
      'thresholds'=>['min_score'=>HMSBF_MIN_SCORE,'min_margin'=>HMSBF_MIN_MARGIN,'min_symmetric'=>HMSBF_MIN_SYMMETRIC,'min_common_tokens'=>2,'direct_geo_required'=>true],
      'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0];
    $pdo->rollBack();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
