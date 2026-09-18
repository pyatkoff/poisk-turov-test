<?php
declare(strict_types=1);

const HMG_OPERATION='hotel-match-user-seen-name-geo-former-1971-20260918-v1';
const HMG_MAX_ACTIVE=200000;
const HMG_MAX_ALIAS=1000000;

function hmg_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function hmg_table(PDO $pdo,string $t): bool {
    $q=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$t]);return $q->fetchColumn()!==false;
}
function hmg_cols(PDO $pdo,string $t): array {
    $q=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$t]);$o=[];foreach($q->fetchAll(PDO::FETCH_COLUMN) as $c)$o[(string)$c]=true;return $o;
}
function hmg_norm_country(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');$v=strtr($v,['ё'=>'е','&'=>' ','+'=>' ']);
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;$v=trim(preg_replace('/\s+/u',' ',$v)??$v);
    $map=[
      'turkey'=>'турция','türkiye'=>'турция','turkiye'=>'турция','egypt'=>'египет',
      'uae'=>'оаэ','united arab emirates'=>'оаэ','emirates'=>'оаэ',
      'thailand'=>'таиланд','vietnam'=>'вьетнам','maldives'=>'мальдивы','cuba'=>'куба',
      'sri lanka'=>'шри ланка','china'=>'китай','mauritius'=>'маврикий','tanzania'=>'танзания',
      'qatar'=>'катар','russia'=>'россия','russian federation'=>'россия','indonesia'=>'индонезия',
      'india'=>'индия','tunisia'=>'тунис','morocco'=>'марокко','seychelles'=>'сейшелы',
      'oman'=>'оман','georgia'=>'грузия','armenia'=>'армения','azerbaijan'=>'азербайджан',
      'uzbekistan'=>'узбекистан','kazakhstan'=>'казахстан','bahrain'=>'бахрейн','jordan'=>'иордания',
    ];
    return $map[$v]??$v;
}
function hmg_add(array &$set,mixed $v): void {
    if(is_scalar($v)&&trim((string)$v)!=='')$set[trim((string)$v)]=true;
}
function hmg_states(mixed $v,array &$states,int $depth=0): void {
    if($depth>10||!is_array($v))return;
    foreach($v as $k=>$x){
        $key=mb_strtolower((string)$k,'UTF-8');
        if(in_array($key,['statekey','state_key'],true)){
            if(is_int($x))$x=(string)$x;
            if(is_string($x)&&preg_match('/^[1-9][0-9]{0,9}$/D',$x))$states[$x]=true;
        }
        if(is_array($x))hmg_states($x,$states,$depth+1);
    }
}
function hmg_name_variants(string $name): array {
    $name=trim($name);if($name==='')return [];
    $out=['current'=>$name];
    $without=preg_replace('/\s*\([^)]*\)\s*/u',' ', $name)??$name;
    if(trim($without)!=='')$out['without_parenthetical']=trim($without);
    if(preg_match('/^(.*?)\b(?:EX\.?|FORMER(?:LY)?)\b/i',$name,$m)&&trim($m[1])!=='')
        $out['before_former_marker']=trim($m[1]," \t\n\r\0\x0B-–—()");
    if(preg_match_all('/\(\s*(?:EX\.?|FORMER(?:LY)?)\s*[:\-]?\s*([^)]{2,})\)/iu',$name,$ms))
        foreach($ms[1] as $i=>$former)if(trim($former)!=='')$out['former_parenthetical_'.$i]=trim($former);
    $clean=[];foreach($out as $kind=>$v){$n=hmpw_norm((string)$v,true);if($n!=='')$clean[$kind]=(string)$v;}
    return $clean;
}
function hmg_key_records(array $names,array $geo): array {
    $geoForms=hmpw_geo($geo);$records=[];
    foreach(array_keys($names) as $name){
        foreach(hmg_name_variants((string)$name) as $kind=>$variant){
            $base=hmpw_norm($variant,true);if($base==='')continue;
            foreach(hmpw_keys($variant,$geoForms) as $key){
                $key=(string)$key;if($key==='')continue;
                $geoStripped=$key!==$base;
                $id=$key.'|'.$kind.'|'.($geoStripped?'1':'0');
                $records[$id]=['key'=>$key,'variant_kind'=>$kind,'source_name'=>(string)$name,
                    'variant'=>$variant,'geo_stripped'=>$geoStripped,'tokens'=>count(preg_split('/\s+/u',$key,-1,PREG_SPLIT_NO_EMPTY)?:[])];
            }
        }
    }
    return array_values($records);
}
function hmg_geo_compatible(array $sourceGeo,array $targetGeo): bool {
    $a=array_fill_keys(hmpw_geo($sourceGeo),true);$b=array_fill_keys(hmpw_geo($targetGeo),true);
    if(!$a||!$b)return false;
    return (bool)array_intersect_key($a,$b);
}
function hmg_source_row(string $provider,string $external,array $names,array $geo,array $countryIds,array $states=[]): array {
    $countries=array_values(array_unique(array_filter(array_map('intval',$countryIds),fn($x)=>$x>0)));sort($countries,SORT_NUMERIC);
    return ['provider'=>$provider,'external_hotel_id'=>$external,'names'=>$names,'geo'=>$geo,'country_ids'=>$countries,
        'state_keys'=>array_values(array_keys($states))];
}

if(in_array('--self-test',$argv??[],true)){
    define('HMPW_LIBRARY_ONLY',true);require_once __DIR__.'/hotel_match_strong_prewrite_audit.php';
    $v=hmg_name_variants('Sherwood Premio (EX. Sherwood Prize Hotel)');
    if(!isset($v['former_parenthetical_0'])||hmpw_norm($v['former_parenthetical_0'],true)!=='SHERWOOD PRIZE')throw new RuntimeException('former');
    $k=hmg_key_records(['Martinenz Hotel Istanbul'=>true],['Istanbul']);
    if(!in_array('MARTINENZ',array_column($k,'key'),true))throw new RuntimeException('geo_key');
    echo "MATCH_USER_SEEN_NAME_GEO_FORMER_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=realpath((string)getenv('MATCH_OPERATION_DIR'));
if(!$root||!$opDir)throw new RuntimeException('runtime_paths');
define('HMPW_LIBRARY_ONLY',true);
require_once $opDir.'/payload/hotel_match_strong_prewrite_audit.php';
require_once $opDir.'/payload/anex-search-mapping-registry.php';
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbf;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['tour_price_observations','catalog_hotels','hotel_aliases','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities'] as $t)
    if(!hmg_table($pdo,$t))throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $anexTargets=[];
    foreach(hmg_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $r){
        $eid=(string)$r['anex_hotel_id'];$target=$registry->resolve('anex_online',$eid,'preview');if(is_int($target)&&$target>0)$anexTargets[$target]=true;
    }
    $andTargets=[];
    foreach(hmg_rows($pdo,"SELECT DISTINCT i.local_hotel_id FROM andromeda_hotel_identities i
      JOIN catalog_hotels h ON h.id=i.local_hotel_id
      WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL") as $r){
        $id=(int)$r['local_hotel_id'];if($id>0)$andTargets[$id]=true;
    }

    $obs=hmg_rows($pdo,"SELECT hotel_id,MAX(observed_at) last_observed_at,COUNT(*) observation_rows
      FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id");
    $seen=[];foreach($obs as $r){$id=(int)$r['hotel_id'];if($id>0)$seen[$id]=['last_observed_at'=>(string)$r['last_observed_at'],'observation_rows'=>(int)$r['observation_rows']];}
    $seenIds=array_keys($seen);$sp=implode(',',array_fill(0,count($seenIds),'?'));
    $frontier=[];foreach(hmg_rows($pdo,"SELECT id,country_id,country_name,name,region_name,subregion_name,is_active FROM catalog_hotels WHERE id IN ($sp)",$seenIds) as $r){
        $id=(int)$r['id'];if((int)$r['is_active']!==1||isset($anexTargets[$id])||isset($andTargets[$id]))continue;
        $frontier[$id]=['hotel_id'=>$id,'country_id'=>(int)$r['country_id'],'country_name'=>(string)$r['country_name'],
          'name'=>(string)$r['name'],'region_name'=>(string)$r['region_name'],'subregion_name'=>(string)$r['subregion_name'],
          'last_observed_at'=>$seen[$id]['last_observed_at'],'observation_rows'=>$seen[$id]['observation_rows']];
    }
    if(!$frontier)throw new RuntimeException('frontier_empty');
    $frontierCountries=[];foreach($frontier as $r)$frontierCountries[(int)$r['country_id']]=true;
    $countryIds=array_keys($frontierCountries);sort($countryIds,SORT_NUMERIC);$cp=implode(',',array_fill(0,count($countryIds),'?'));

    $countryNameToId=[];
    foreach(hmg_rows($pdo,"SELECT DISTINCT country_id,country_name FROM catalog_hotels WHERE is_active=1 AND country_id IN ($cp)",$countryIds) as $r){
        $n=hmg_norm_country((string)$r['country_name']);if($n!=='')$countryNameToId[$n][(int)$r['country_id']]=true;
    }

    // Accepted Andromeda stateKey -> country consensus.
    $stateCountries=[];
    foreach(hmg_rows($pdo,"SELECT i.evidence_json,h.country_id FROM andromeda_hotel_identities i
      JOIN catalog_hotels h ON h.id=i.local_hotel_id
      WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL") as $r){
        $states=[];try{$e=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR);hmg_states($e,$states);}catch(Throwable){}
        foreach(array_keys($states) as $state)$stateCountries[$state][(int)$r['country_id']]=true;
    }
    $stateUnique=[];$stateConflicts=[];
    foreach($stateCountries as $state=>$countries){if(count($countries)===1)$stateUnique[$state]=(int)array_key_first($countries);else$stateConflicts[$state]=array_map('intval',array_keys($countries));}

    $manualAnex=[];foreach(hmg_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_decisions') as $r)$manualAnex[(string)$r['anex_hotel_id']]=true;
    $pairExclusions=[];if(hmg_table($pdo,'anex_review_pair_exclusions'))
      foreach(hmg_rows($pdo,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions') as $r)
        $pairExclusions[(string)$r['anex_hotel_id'].'|'.(int)$r['catalog_hotel_id']]=true;

    // Current ANEX source evidence.
    $anexNames=[];$anexGeo=[];$anexCountries=[];
    if(hmg_table($pdo,'anex_search_hotel_observations')){
        $c=hmg_cols($pdo,'anex_search_hotel_observations');$sel=['anex_hotel_id'];
        foreach(['hotel_name','country_id','country_name','region_name'] as $x)if(isset($c[$x]))$sel[]=$x;
        foreach(hmg_rows($pdo,'SELECT '.implode(',',$sel).' FROM anex_search_hotel_observations') as $r){
            $id=(string)$r['anex_hotel_id'];if(isset($manualAnex[$id])||$registry->resolve('anex_online',$id,'preview')!==null)continue;
            if(!isset($anexNames[$id])){$anexNames[$id]=[];$anexGeo[$id]=[];}
            hmg_add($anexNames[$id],$r['hotel_name']??'');hmg_add($anexGeo[$id],$r['region_name']??'');
            if(isset($r['country_id'])&&(int)$r['country_id']>0)$anexCountries[$id][(int)$r['country_id']]=true;
            if(isset($r['country_name']))foreach(array_keys($countryNameToId[hmg_norm_country((string)$r['country_name'])]??[]) as $cid)$anexCountries[$id][(int)$cid]=true;
        }
    }
    if(hmg_table($pdo,'anex_hotels')){
        $c=hmg_cols($pdo,'anex_hotels');$sel=['anex_hotel_id'];
        foreach(['api_name','xml_name','xml_alternate_name','api_country','api_region','api_town'] as $x)if(isset($c[$x]))$sel[]=$x;
        foreach(hmg_rows($pdo,'SELECT '.implode(',',$sel).' FROM anex_hotels') as $r){
            $id=(string)$r['anex_hotel_id'];if(isset($manualAnex[$id])||$registry->resolve('anex_online',$id,'preview')!==null)continue;
            if(!isset($anexNames[$id])){$anexNames[$id]=[];$anexGeo[$id]=[];}
            foreach(['api_name','xml_name','xml_alternate_name'] as $x)hmg_add($anexNames[$id],$r[$x]??'');
            foreach(['api_region','api_town'] as $x)hmg_add($anexGeo[$id],$r[$x]??'');
            if(isset($r['api_country']))foreach(array_keys($countryNameToId[hmg_norm_country((string)$r['api_country'])]??[]) as $cid)$anexCountries[$id][(int)$cid]=true;
        }
    }

    // Current Andromeda observations + pending identity evidence.
    $andObsNames=[];$andObsGeo=[];$andObsCountries=[];
    if(hmg_table($pdo,'andromeda_search_hotel_observations')){
        $c=hmg_cols($pdo,'andromeda_search_hotel_observations');$sel=['external_hotel_id'];
        foreach(['supplier_namespace','hotel_name','country_id','country_name','region_name'] as $x)if(isset($c[$x]))$sel[]=$x;
        foreach(hmg_rows($pdo,'SELECT '.implode(',',$sel).' FROM andromeda_search_hotel_observations') as $r){
            if(isset($r['supplier_namespace'])&&(string)$r['supplier_namespace']!=='andromeda_catalog')continue;$id=(string)$r['external_hotel_id'];
            if(!isset($andObsNames[$id])){$andObsNames[$id]=[];$andObsGeo[$id]=[];}
            hmg_add($andObsNames[$id],$r['hotel_name']??'');hmg_add($andObsGeo[$id],$r['region_name']??'');
            if(isset($r['country_id'])&&(int)$r['country_id']>0)$andObsCountries[$id][(int)$r['country_id']]=true;
            if(isset($r['country_name']))foreach(array_keys($countryNameToId[hmg_norm_country((string)$r['country_name'])]??[]) as $cid)$andObsCountries[$id][(int)$cid]=true;
        }
    }
    $andCols=hmg_cols($pdo,'andromeda_hotel_identities');$sel=['external_hotel_id','evidence_json'];
    foreach(['country_id'] as $x)if(isset($andCols[$x]))$sel[]=$x;
    $andSources=[];
    foreach(hmg_rows($pdo,'SELECT '.implode(',',$sel)." FROM andromeda_hotel_identities
      WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL") as $r){
        $id=(string)$r['external_hotel_id'];$names=$andObsNames[$id]??[];$geo=$andObsGeo[$id]??[];$countries=$andObsCountries[$id]??[];$states=[];
        if(isset($r['country_id'])&&(int)$r['country_id']>0)$countries[(int)$r['country_id']]=true;
        try{$e=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR);if(is_array($e)){hmpw_walk($e,$names,$geo);hmg_states($e,$states);}}catch(Throwable){}
        if(!$countries)foreach(array_keys($states) as $state)if(isset($stateUnique[$state]))$countries[$stateUnique[$state]]=true;
        $andSources[$id]=hmg_source_row('andromeda',$id,$names,$geo,array_keys($countries),$states);
    }

    $sources=[];
    foreach($anexNames as $id=>$names)$sources['anex:'.$id]=hmg_source_row('anex',(string)$id,$names,$anexGeo[$id]??[],array_keys($anexCountries[$id]??[]));
    foreach($andSources as $id=>$src)$sources['andromeda:'.$id]=$src;

    // Build only source keys that have one current country authority and >=2 significant tokens.
    $wanted=[];$sourceRecords=[];$blocked=['country_authority'=>0,'low_information'=>0,'no_names'=>0];
    foreach($sources as $sk=>$src){
        if(count($src['country_ids'])!==1){$blocked['country_authority']++;continue;}
        if(!$src['names']){$blocked['no_names']++;continue;}
        $cid=$src['country_ids'][0];$records=hmg_key_records($src['names'],array_keys($src['geo']));
        $usable=[];foreach($records as $rec){if($rec['tokens']<2)continue;$usable[]=$rec;$wanted[$cid][$rec['key']]=true;}
        if(!$usable){$blocked['low_information']++;continue;}
        $sourceRecords[$sk]=['source'=>$src,'records'=>$usable];
    }

    // Global current active local index, but only for keys any source can actually use.
    $localById=[];$index=[];
    $stmt=$pdo->prepare("SELECT id,country_id,country_name,name,region_name,subregion_name FROM catalog_hotels WHERE is_active=1 AND country_id IN ($cp)");
    $stmt->execute($countryIds);$activeCount=0;
    while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
        if(++$activeCount>HMG_MAX_ACTIVE)throw new RuntimeException('active_catalog_bound');
        $id=(int)$r['id'];$cid=(int)$r['country_id'];$localById[$id]=$r;
        foreach(hmg_key_records([(string)$r['name']=>true],[(string)$r['region_name'],(string)$r['subregion_name']]) as $rec)
            if(isset($wanted[$cid][$rec['key']]))$index[$cid][$rec['key']][$id][]=['source'=>'name']+$rec;
    }
    $stmt->closeCursor();

    $aliasStmt=$pdo->prepare("SELECT a.hotel_id,a.alias,h.country_id,h.region_name,h.subregion_name FROM hotel_aliases a
      JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($cp)");
    $aliasStmt->execute($countryIds);$aliasCount=0;$relevantAliases=0;
    while($r=$aliasStmt->fetch(PDO::FETCH_ASSOC)){
        if(++$aliasCount>HMG_MAX_ALIAS)throw new RuntimeException('alias_bound');
        $id=(int)$r['hotel_id'];$cid=(int)$r['country_id'];
        foreach(hmg_key_records([(string)$r['alias']=>true],[(string)$r['region_name'],(string)$r['subregion_name']]) as $rec){
            if(!isset($wanted[$cid][$rec['key']]))continue;
            $index[$cid][$rec['key']][$id][]=['source'=>'alias']+$rec;++$relevantAliases;
        }
    }
    $aliasStmt->closeCursor();

    $prepared=[];$held=[];$targetCounts=[];
    foreach($sourceRecords as $sk=>$bundle){
        $src=$bundle['source'];$cid=$src['country_ids'][0];$matches=[];$evidence=[];
        foreach($bundle['records'] as $rec){
            foreach($index[$cid][$rec['key']]??[] as $lid=>$localEvidence){
                $lid=(int)$lid;
                $geoNeeded=(bool)$rec['geo_stripped'];
                if($geoNeeded){
                    $target=$localById[$lid]??null;if(!$target||!hmg_geo_compatible(array_keys($src['geo']),[(string)$target['region_name'],(string)$target['subregion_name']]))continue;
                }
                $matches[$lid]=true;$evidence[$lid][]=['source_key'=>$rec,'local_evidence'=>$localEvidence];
            }
        }
        $reasons=[];
        if(count($matches)!==1)$reasons[]=count($matches)>1?'multiple_local_targets':'no_unique_current_target';
        if(!$reasons){
            $target=(int)array_key_first($matches);
            if(!isset($frontier[$target]))$reasons[]='target_not_user_seen_frontier';
            if($src['provider']==='anex'&&isset($pairExclusions[$src['external_hotel_id'].'|'.$target]))$reasons[]='pair_excluded';
        }else $target=0;
        $row=['provider'=>$src['provider'],'external_hotel_id'=>$src['external_hotel_id'],'country_id'=>$cid,
          'proposed_local_id'=>$target?:null,'source_names'=>array_values(array_keys($src['names'])),'source_geo'=>array_values(array_keys($src['geo'])),
          'matched_evidence'=>$target?($evidence[$target]??[]):[],'status'=>$reasons?'held':'prepared_current_recheck_not_accepted','hold_reasons'=>$reasons];
        if($reasons)$held[]=$row;else{$prepared[]=$row;$targetCounts[$src['provider'].'|'.$target]=($targetCounts[$src['provider'].'|'.$target]??0)+1;}
    }

    // Same-provider duplicate target never enters prepared set.
    $final=[];$duplicateHeld=[];
    foreach($prepared as $row){
        $k=$row['provider'].'|'.$row['proposed_local_id'];
        if(($targetCounts[$k]??0)>1){$row['status']='held';$row['hold_reasons']=['same_provider_duplicate_target'];$duplicateHeld[]=$row;}
        else$final[]=$row;
    }
    $held=array_merge($held,$duplicateHeld);
    $targets=[];foreach($final as $r)$targets[(int)$r['proposed_local_id']][$r['provider']][]=$r['external_hotel_id'];
    $targetRows=[];foreach($targets as $id=>$providers)$targetRows[]=$frontier[$id]+['providers'=>$providers,'source_identity_count'=>array_sum(array_map('count',$providers))];
    usort($targetRows,fn($a,$b)=>$b['observation_rows']<=>$a['observation_rows'] ?: strcmp($b['last_observed_at'],$a['last_observed_at']) ?: $a['hotel_id']<=>$b['hotel_id']);

    $counts=['anex'=>0,'andromeda'=>0,'former'=>0,'geo_edge'=>0,'plain'=>0];
    foreach($final as $r){
        $counts[$r['provider']]++;
        $former=false;$geo=false;
        foreach($r['matched_evidence'] as $e){$kind=$e['source_key']['variant_kind'];if(str_contains($kind,'former'))$former=true;if($e['source_key']['geo_stripped'])$geo=true;}
        if($former)$counts['former']++;elseif($geo)$counts['geo_edge']++;else$counts['plain']++;
    }

    $result=[
      'operation'=>HMG_OPERATION,'status'=>'read_only_complete','frontier_count'=>count($frontier),
      'frontier_country_ids'=>$countryIds,'source_rows_considered'=>count($sources),'source_rows_with_usable_keys'=>count($sourceRecords),
      'active_local_rows_scanned'=>$activeCount,'alias_rows_scanned'=>$aliasCount,'relevant_alias_key_hits'=>$relevantAliases,
      'state_key_unique_count'=>count($stateUnique),'state_key_conflict_count'=>count($stateConflicts),
      'prepared_identity_count'=>count($final),'prepared_unique_local_hotels'=>count($targetRows),
      'prepared_both_provider_local_hotels'=>count(array_filter($targetRows,fn($r)=>isset($r['providers']['anex'],$r['providers']['andromeda']))),
      'prepared_counts'=>$counts,'preindex_blocked_counts'=>$blocked,
      'held_count'=>count($held),'prepared_rows'=>$final,'held_rows'=>$held,'candidate_targets'=>$targetRows,
      'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,
    ];
    $pdo->rollBack();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
