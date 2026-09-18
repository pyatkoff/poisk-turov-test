<?php
declare(strict_types=1);

const HMS_OPERATION='hotel-match-user-seen-residual-alias-state-country-1971-20260918-v1';
const HMS_MAX_ROWS=120000;

function hms_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
    if(count($r)>HMS_MAX_ROWS)throw new RuntimeException('row_budget');return $r;
}
function hms_table(PDO $pdo,string $table): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$table]);return $s->fetchColumn()!==false;
}
function hms_cols(PDO $pdo,string $table): array {
    $s=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $s->execute([$table]);$o=[];foreach($s->fetchAll(PDO::FETCH_COLUMN) as $c)$o[(string)$c]=true;return $o;
}
function hms_norm(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');$v=strtr($v,['ё'=>'е','&'=>' ','+'=>' ']);
    $v=preg_replace('/[^\\p{L}\\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\\s+/u',' ',$v)??$v);
}
function hms_key(string $v): string {
    $tokens=preg_split('/\\s+/u',hms_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[];
    $drop=['hotel'=>1,'resort'=>1,'spa'=>1,'отель'=>1,'гостиница'=>1];$out=[];
    foreach($tokens as $t)if(!isset($drop[$t]))$out[]=$t;return implode(' ',$out);
}
function hms_country_name(string $v): string {
    $n=hms_norm($v);$a=[
      'turkey'=>'турция','türkiye'=>'турция','turkiye'=>'турция','egypt'=>'египет',
      'uae'=>'оаэ','united arab emirates'=>'оаэ','emirates'=>'оаэ','thailand'=>'таиланд',
      'vietnam'=>'вьетнам','maldives'=>'мальдивы','cuba'=>'куба','sri lanka'=>'шри ланка',
      'china'=>'китай','mauritius'=>'маврикий','tanzania'=>'танзания','qatar'=>'катар',
      'russia'=>'россия','russian federation'=>'россия','indonesia'=>'индонезия','india'=>'индия',
      'tunisia'=>'тунис','morocco'=>'марокко','seychelles'=>'сейшелы','oman'=>'оман',
    ];return $a[$n]??$n;
}
function hms_add_name(array &$set,mixed $v): void {
    if(is_string($v)&&trim($v)!=='')$set[trim($v)]=true;
}
function hms_collect_sources(mixed $v,array &$names,array &$states,int $depth=0): void {
    if($depth>8||!is_array($v))return;
    foreach($v as $k=>$x){
        if((string)$k==='source'&&is_array($x)){
            foreach(['name','lName','hotel','hotel_name'] as $f)hms_add_name($names,$x[$f]??'');
            foreach(['stateKey','state_key'] as $f){
                $sv=$x[$f]??null;if(is_int($sv))$sv=(string)$sv;
                if(is_string($sv)&&preg_match('/^[1-9][0-9]{0,9}$/D',$sv))$states[$sv]=true;
            }
        }
        if(is_array($x))hms_collect_sources($x,$names,$states,$depth+1);
    }
}
function hms_source_candidate(
    string $provider,string $external,array $names,array $countryIds,array $nameIndex,array $aliasIndex,array $frontier,array $pairExclusions=[]
): array {
    $countryIds=array_values(array_unique(array_filter(array_map('intval',$countryIds),fn($x)=>$x>0)));
    if(count($countryIds)!==1)return ['status'=>'blocked','reason'=>count($countryIds)>1?'country_authority_conflict':'country_authority_missing'];
    $country=$countryIds[0];$canonicalTargets=[];$aliasTargets=[];$canonicalKeys=[];$aliasKeys=[];
    foreach(array_keys($names) as $name){
        $key=hms_key((string)$name);if($key==='')continue;
        $canon=array_keys($nameIndex[$country][$key]??[]);
        if(count($canon)>1)return ['status'=>'blocked','reason'=>'canonical_key_ambiguous'];
        if(count($canon)===1){$canonicalTargets[(int)$canon[0]]=true;$canonicalKeys[$key]=true;continue;}
        $alias=array_keys($aliasIndex[$country][$key]??[]);
        if(count($alias)>1)return ['status'=>'blocked','reason'=>'alias_key_ambiguous'];
        if(count($alias)===1){$aliasTargets[(int)$alias[0]]=true;$aliasKeys[$key]=true;}
    }
    $targets=$canonicalTargets+$aliasTargets;
    if(count($targets)!==1)return ['status'=>'blocked','reason'=>count($targets)>1?'source_names_disagree':'no_unique_local_key'];
    $target=(int)array_key_first($targets);if(!isset($frontier[$target]))return ['status'=>'blocked','reason'=>'target_not_user_seen_frontier'];
    if(isset($pairExclusions[$external.'|'.$target]))return ['status'=>'blocked','reason'=>'pair_excluded'];
    $mode=isset($canonicalTargets[$target])?'canonical_exact':'alias_exact';
    return ['status'=>'candidate','provider'=>$provider,'external_hotel_id'=>$external,'local_hotel_id'=>$target,
        'country_id'=>$country,'match_mode'=>$mode,
        'matched_keys'=>array_values(array_keys($mode==='canonical_exact'?$canonicalKeys:$aliasKeys)),
        'source_names'=>array_values(array_keys($names))];
}

if(in_array('--self-test',$argv??[],true)){
    if(hms_key('Royal Seginus Hotel')!=='royal seginus')throw new RuntimeException('key');
    if(hms_country_name('Mauritius')!=='маврикий')throw new RuntimeException('country');
    echo "MATCH_USER_SEEN_RESIDUAL_ALIAS_STATE_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=realpath((string)getenv('MATCH_OPERATION_DIR'));
if(!$root||!$opDir)throw new RuntimeException('runtime_paths');
require_once $opDir.'/payload/anex-search-mapping-registry.php';
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbf;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['tour_price_observations','catalog_hotels','hotel_aliases','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities'] as $t)
    if(!hms_table($pdo,$t))throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $anexTargets=[];foreach(hms_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $r){
        $eid=(string)$r['anex_hotel_id'];$target=$registry->resolve('anex_online',$eid,'preview');if(is_int($target)&&$target>0)$anexTargets[$target]=true;
    }
    $andTargets=[];foreach(hms_rows($pdo,"SELECT DISTINCT i.local_hotel_id FROM andromeda_hotel_identities i
      JOIN catalog_hotels h ON h.id=i.local_hotel_id
      WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL") as $r){
        $id=(int)$r['local_hotel_id'];if($id>0)$andTargets[$id]=true;
    }

    $obs=hms_rows($pdo,"SELECT hotel_id,MAX(observed_at) last_observed_at,COUNT(*) observation_rows
      FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id");
    $seen=[];foreach($obs as $r){$id=(int)$r['hotel_id'];if($id>0)$seen[$id]=['last_observed_at'=>(string)$r['last_observed_at'],'observation_rows'=>(int)$r['observation_rows']];}
    $seenIds=array_keys($seen);$sp=implode(',',array_fill(0,count($seenIds),'?'));
    $frontier=[];foreach(hms_rows($pdo,"SELECT id,country_id,country_name,name,region_name,subregion_name,is_active FROM catalog_hotels WHERE id IN ($sp)",$seenIds) as $r){
        $id=(int)$r['id'];if((int)$r['is_active']!==1||isset($anexTargets[$id])||isset($andTargets[$id]))continue;
        $frontier[$id]=['hotel_id'=>$id,'country_id'=>(int)$r['country_id'],'country_name'=>(string)$r['country_name'],
          'name'=>(string)$r['name'],'region_name'=>(string)$r['region_name'],'subregion_name'=>(string)$r['subregion_name'],
          'last_observed_at'=>$seen[$id]['last_observed_at'],'observation_rows'=>$seen[$id]['observation_rows']];
    }
    if(!$frontier)throw new RuntimeException('frontier_empty');

    $frontierCountries=[];foreach($frontier as $r)$frontierCountries[(int)$r['country_id']]=true;
    $countryIds=array_keys($frontierCountries);sort($countryIds,SORT_NUMERIC);$cp=implode(',',array_fill(0,count($countryIds),'?'));
    $active=hms_rows($pdo,"SELECT id,country_id,country_name,name FROM catalog_hotels WHERE is_active=1 AND country_id IN ($cp)",$countryIds);
    $nameIndex=[];$countryNameToId=[];
    foreach($active as $r){
        $id=(int)$r['id'];$cid=(int)$r['country_id'];$key=hms_key((string)$r['name']);if($key!=='')$nameIndex[$cid][$key][$id]=true;
        $cn=hms_country_name((string)$r['country_name']);if($cn!=='')$countryNameToId[$cn][$cid]=true;
    }

    $frontierIds=array_keys($frontier);$fp=implode(',',array_fill(0,count($frontierIds),'?'));
    $wantedAlias=[];foreach(hms_rows($pdo,"SELECT a.hotel_id,a.alias,h.country_id FROM hotel_aliases a
      JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND a.hotel_id IN ($fp)",$frontierIds) as $r){
        $cid=(int)$r['country_id'];$id=(int)$r['hotel_id'];$key=hms_key((string)$r['alias']);
        if($key!=='')$wantedAlias[$cid][$key][$id]=true;
    }
    $aliasIndex=[];
    foreach($wantedAlias as $cid=>$keys)foreach($keys as $key=>$ids){
        foreach(array_keys($nameIndex[$cid][$key]??[]) as $id)$aliasIndex[$cid][$key][(int)$id]=true;
    }
    if($wantedAlias){
        $stmt=$pdo->prepare("SELECT a.hotel_id,a.alias,h.country_id FROM hotel_aliases a
          JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($cp)");
        $stmt->execute($countryIds);$streamed=0;$matchedAliasRows=0;
        while($r=$stmt->fetch(PDO::FETCH_ASSOC)){
            ++$streamed;$cid=(int)$r['country_id'];$key=hms_key((string)$r['alias']);
            if($key!==''&&isset($wantedAlias[$cid][$key])){
                $aliasIndex[$cid][$key][(int)$r['hotel_id']]=true;++$matchedAliasRows;
            }
        }
        $stmt->closeCursor();
    }else{$streamed=0;$matchedAliasRows=0;}

    $pairExclusions=[];if(hms_table($pdo,'anex_review_pair_exclusions'))
      foreach(hms_rows($pdo,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions') as $r)
        $pairExclusions[(string)$r['anex_hotel_id'].'|'.(int)$r['catalog_hotel_id']]=true;
    $manualAnex=[];foreach(hms_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_decisions') as $r)$manualAnex[(string)$r['anex_hotel_id']]=true;

    $anexNames=[];$anexCountries=[];
    if(hms_table($pdo,'anex_search_hotel_observations')){
        $c=hms_cols($pdo,'anex_search_hotel_observations');$select=['anex_hotel_id'];
        foreach(['hotel_name','country_id','country_name'] as $x)if(isset($c[$x]))$select[]=$x;
        foreach(hms_rows($pdo,'SELECT '.implode(',',$select).' FROM anex_search_hotel_observations') as $r){
            $id=(string)$r['anex_hotel_id'];if(!isset($anexNames[$id]))$anexNames[$id]=[];hms_add_name($anexNames[$id],$r['hotel_name']??'');
            if(isset($r['country_id'])&&(int)$r['country_id']>0)$anexCountries[$id][(int)$r['country_id']]=true;
            if(isset($r['country_name'])){foreach(array_keys($countryNameToId[hms_country_name((string)$r['country_name'])]??[]) as $cid)$anexCountries[$id][(int)$cid]=true;}
        }
    }
    if(hms_table($pdo,'anex_hotels')){
        $c=hms_cols($pdo,'anex_hotels');$select=['anex_hotel_id'];
        foreach(['api_name','xml_name','xml_alternate_name','api_country'] as $x)if(isset($c[$x]))$select[]=$x;
        foreach(hms_rows($pdo,'SELECT '.implode(',',$select).' FROM anex_hotels') as $r){
            $id=(string)$r['anex_hotel_id'];if(!isset($anexNames[$id]))$anexNames[$id]=[];
            foreach(['api_name','xml_name','xml_alternate_name'] as $x)hms_add_name($anexNames[$id],$r[$x]??'');
            if(isset($r['api_country']))foreach(array_keys($countryNameToId[hms_country_name((string)$r['api_country'])]??[]) as $cid)$anexCountries[$id][(int)$cid]=true;
        }
    }
    $anexCandidates=[];$anexBlocked=[];
    foreach($anexNames as $id=>$names){
        if(isset($manualAnex[$id])||$registry->resolve('anex_online',$id,'preview')!==null)continue;
        $r=hms_source_candidate('anex',$id,$names,array_keys($anexCountries[$id]??[]),$nameIndex,$aliasIndex,$frontier,$pairExclusions);
        if($r['status']==='candidate')$anexCandidates[]=$r;else$anexBlocked[$r['reason']]=($anexBlocked[$r['reason']]??0)+1;
    }

    $stateCountries=[];$stateConflict=[];
    $andCols=hms_cols($pdo,'andromeda_hotel_identities');$acceptedSelect=['external_hotel_id','local_hotel_id','evidence_json'];
    foreach(['supplier_namespace'] as $x)if(isset($andCols[$x]))$acceptedSelect[]=$x;
    $acceptedRows=hms_rows($pdo,'SELECT '.implode(',',$acceptedSelect)." FROM andromeda_hotel_identities
      WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL");
    $localCountry=[];foreach($active as $r)$localCountry[(int)$r['id']]=(int)$r['country_id'];
    foreach($acceptedRows as $r){
        $lid=(int)$r['local_hotel_id'];$cid=$localCountry[$lid]??0;if($cid<1)continue;$names=[];$states=[];
        try{$e=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR);hms_collect_sources($e,$names,$states);}catch(Throwable){}
        foreach(array_keys($states) as $state)$stateCountries[$state][$cid]=true;
    }
    $stateCountryUnique=[];
    foreach($stateCountries as $state=>$countries){
        if(count($countries)===1)$stateCountryUnique[$state]=(int)array_key_first($countries);
        else$stateConflict[$state]=array_map('intval',array_keys($countries));
    }

    $andObsNames=[];$andObsCountries=[];
    if(hms_table($pdo,'andromeda_search_hotel_observations')){
        $c=hms_cols($pdo,'andromeda_search_hotel_observations');$select=['external_hotel_id'];
        foreach(['supplier_namespace','hotel_name','country_id','country_name'] as $x)if(isset($c[$x]))$select[]=$x;
        foreach(hms_rows($pdo,'SELECT '.implode(',',$select).' FROM andromeda_search_hotel_observations') as $r){
            if(isset($r['supplier_namespace'])&&(string)$r['supplier_namespace']!=='andromeda_catalog')continue;$id=(string)$r['external_hotel_id'];
            if(!isset($andObsNames[$id]))$andObsNames[$id]=[];hms_add_name($andObsNames[$id],$r['hotel_name']??'');
            if(isset($r['country_id'])&&(int)$r['country_id']>0)$andObsCountries[$id][(int)$r['country_id']]=true;
            if(isset($r['country_name']))foreach(array_keys($countryNameToId[hms_country_name((string)$r['country_name'])]??[]) as $cid)$andObsCountries[$id][(int)$cid]=true;
        }
    }

    $pendingSelect=['external_hotel_id','decision_status','local_hotel_id','evidence_json'];
    foreach(['supplier_namespace','country_id'] as $x)if(isset($andCols[$x]))$pendingSelect[]=$x;
    $andCandidates=[];$andBlocked=[];$stateDerived=0;
    foreach(hms_rows($pdo,'SELECT '.implode(',',$pendingSelect)." FROM andromeda_hotel_identities
      WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL") as $r){
        $id=(string)$r['external_hotel_id'];$names=$andObsNames[$id]??[];$countries=$andObsCountries[$id]??[];$states=[];
        if(isset($r['country_id'])&&(int)$r['country_id']>0)$countries[(int)$r['country_id']]=true;
        try{$e=json_decode((string)$r['evidence_json'],true,64,JSON_THROW_ON_ERROR);hms_collect_sources($e,$names,$states);}catch(Throwable){}
        if(!$countries&&$states){
            foreach(array_keys($states) as $state)if(isset($stateCountryUnique[$state]))$countries[$stateCountryUnique[$state]]=true;
            if($countries)$stateDerived++;
        }
        $rr=hms_source_candidate('andromeda',$id,$names,array_keys($countries),$nameIndex,$aliasIndex,$frontier);
        if($rr['status']==='candidate'){$rr['country_authority']=isset($andObsCountries[$id])?'observation':(($r['country_id']??null)?'identity':'accepted_stateKey_consensus');$andCandidates[]=$rr;}
        else$andBlocked[$rr['reason']]=($andBlocked[$rr['reason']]??0)+1;
    }

    $targets=[];foreach($anexCandidates as $r)$targets[$r['local_hotel_id']]['anex'][]=$r;
    foreach($andCandidates as $r)$targets[$r['local_hotel_id']]['andromeda'][]=$r;
    $targetRows=[];foreach($targets as $local=>$providers){
        $targetRows[]=$frontier[$local]+['providers'=>array_map(fn($rows)=>array_values(array_map(fn($x)=>$x['external_hotel_id'],$rows)),$providers),
          'match_modes'=>array_values(array_unique(array_map(fn($x)=>$x['match_mode'],array_merge(...array_values($providers))))),
          'source_identity_count'=>array_sum(array_map('count',$providers))];
    }
    usort($targetRows,fn($a,$b)=>$b['observation_rows']<=>$a['observation_rows'] ?: strcmp($b['last_observed_at'],$a['last_observed_at']) ?: $a['hotel_id']<=>$b['hotel_id']);

    $canonicalCount=count(array_filter(array_merge($anexCandidates,$andCandidates),fn($r)=>$r['match_mode']==='canonical_exact'));
    $aliasCount=count($anexCandidates)+count($andCandidates)-$canonicalCount;
    $result=[
      'operation'=>HMS_OPERATION,'status'=>'read_only_complete',
      'frontier_count'=>count($frontier),'frontier_country_ids'=>$countryIds,'active_catalog_rows_indexed'=>count($active),
      'frontier_alias_keys'=>array_sum(array_map('count',$wantedAlias)),'alias_rows_streamed'=>$streamed,'alias_rows_relevant'=>$matchedAliasRows,
      'accepted_state_keys_unique'=>count($stateCountryUnique),'accepted_state_keys_conflicted'=>count($stateConflict),'pending_rows_country_derived_from_stateKey'=>$stateDerived,
      'anex_candidate_identities'=>count($anexCandidates),'andromeda_candidate_identities'=>count($andCandidates),
      'canonical_candidate_identities'=>$canonicalCount,'alias_candidate_identities'=>$aliasCount,
      'candidate_identity_count'=>count($anexCandidates)+count($andCandidates),'candidate_unique_local_hotels'=>count($targetRows),
      'candidate_both_provider_local_hotels'=>count(array_filter($targetRows,fn($r)=>isset($r['providers']['anex'],$r['providers']['andromeda']))),
      'anex_blocked_counts'=>$anexBlocked,'andromeda_blocked_counts'=>$andBlocked,'state_key_conflicts'=>$stateConflict,
      'candidate_targets'=>$targetRows,'anex_candidates'=>$anexCandidates,'andromeda_candidates'=>$andCandidates,
      'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,
    ];
    $pdo->rollBack();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
