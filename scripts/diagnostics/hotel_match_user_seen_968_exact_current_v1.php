<?php
declare(strict_types=1);

const HMU_OPERATION='hotel-match-user-seen-968-exact-current-candidates-1971-20260918-v1';
const HMU_MAX_ROWS=100000;

function hmu_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));$r=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
    if(count($r)>HMU_MAX_ROWS)throw new RuntimeException('row_budget');
    return $r;
}
function hmu_table(PDO $pdo,string $table): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$table]);return $s->fetchColumn()!==false;
}
function hmu_cols(PDO $pdo,string $table): array {
    $s=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $s->execute([$table]);$out=[];foreach($s->fetchAll(PDO::FETCH_COLUMN) as $c)$out[(string)$c]=true;return $out;
}
function hmu_norm(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');$v=strtr($v,['ё'=>'е','&'=>' ','+'=>' ']);
    $v=preg_replace('/[^\\p{L}\\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\\s+/u',' ',$v)??$v);
}
function hmu_key(string $v): string {
    $tokens=preg_split('/\\s+/u',hmu_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[];
    $drop=['hotel'=>1,'resort'=>1,'spa'=>1,'отель'=>1,'гостиница'=>1];$out=[];
    foreach($tokens as $t)if(!isset($drop[$t]))$out[]=$t;
    return implode(' ',$out);
}
function hmu_country_name(string $v): string {
    $n=hmu_norm($v);
    $aliases=[
        'turkey'=>'турция','türkiye'=>'турция','turkiye'=>'турция',
        'egypt'=>'египет','uae'=>'оаэ','united arab emirates'=>'оаэ','emirates'=>'оаэ',
        'thailand'=>'таиланд','vietnam'=>'вьетнам','maldives'=>'мальдивы','cuba'=>'куба',
        'sri lanka'=>'шри ланка','abkhazia'=>'абхазия',
    ];
    return $aliases[$n]??$n;
}
function hmu_walk_names(mixed $v,array &$out,int $depth=0,string $key=''): void {
    if($depth>6)return;
    if(is_array($v)){foreach($v as $k=>$x)hmu_walk_names($x,$out,$depth+1,(string)$k);return;}
    if(!is_string($v))return;
    if(preg_match('/(?:^|_)(?:name|lname|hotel|hotel_name)$/i',$key) && trim($v)!=='')$out[trim($v)]=true;
}
function hmu_add_name(array &$set,mixed $v): void {
    if(is_string($v)&&trim($v)!=='')$set[trim($v)]=true;
}
function hmu_candidate_for_source(
    string $provider,string $external,array $names,array $countryIds,array $localIndex,array $frontier,array $pairExclusions=[]
): array {
    $countryIds=array_values(array_unique(array_filter(array_map('intval',$countryIds),fn($x)=>$x>0)));
    if(count($countryIds)!==1)return ['status'=>'blocked','reason'=>'country_authority_not_unique'];
    $country=$countryIds[0];$targets=[];$matchedKeys=[];
    foreach(array_keys($names) as $name){
        $key=hmu_key((string)$name);if($key==='')continue;
        $locals=array_keys($localIndex[$country][$key]??[]);
        if(count($locals)===1){$targets[(int)$locals[0]]=true;$matchedKeys[$key]=true;}
        elseif(count($locals)>1)return ['status'=>'blocked','reason'=>'local_key_ambiguous'];
    }
    if(count($targets)!==1)return ['status'=>'blocked','reason'=>count($targets)>1?'source_names_disagree':'no_unique_local_key'];
    $target=(int)array_key_first($targets);
    if(!isset($frontier[$target]))return ['status'=>'blocked','reason'=>'target_not_user_seen_frontier'];
    if(isset($pairExclusions[$external.'|'.$target]))return ['status'=>'blocked','reason'=>'pair_excluded'];
    return ['status'=>'candidate','provider'=>$provider,'external_hotel_id'=>$external,'local_hotel_id'=>$target,
        'country_id'=>$country,'matched_keys'=>array_values(array_keys($matchedKeys)),'source_names'=>array_values(array_keys($names))];
}

if(in_array('--self-test',$argv??[],true)){
    if(hmu_key('Porto Bello Hotel Resort & Spa')!=='porto bello')throw new RuntimeException('key');
    if(hmu_country_name('Turkey')!=='турция')throw new RuntimeException('country');
    echo "MATCH_USER_SEEN_968_EXACT_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$opDir=realpath((string)getenv('MATCH_OPERATION_DIR'));
if(!$root||!$opDir)throw new RuntimeException('runtime_paths');
require_once $opDir.'/payload/anex-search-mapping-registry.php';
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbf;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

foreach(['tour_price_observations','catalog_hotels','hotel_aliases','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities'] as $t)
    if(!hmu_table($pdo,$t))throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);

    $acceptedAnexTargets=[];
    $anexIds=hmu_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions');
    foreach($anexIds as $r){
        $id=(string)($r['anex_hotel_id']??'');if($id==='')continue;
        $target=$registry->resolve('anex_online',$id,'preview');if(is_int($target)&&$target>0)$acceptedAnexTargets[$target]=true;
    }
    $acceptedAndromedaTargets=[];
    foreach(hmu_rows($pdo,"SELECT DISTINCT i.local_hotel_id FROM andromeda_hotel_identities i
        JOIN catalog_hotels h ON h.id=i.local_hotel_id
        WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL") as $r){
        $id=(int)$r['local_hotel_id'];if($id>0)$acceptedAndromedaTargets[$id]=true;
    }

    $obs=hmu_rows($pdo,"SELECT o.hotel_id,MAX(o.observed_at) last_observed_at,COUNT(*) observation_rows
        FROM tour_price_observations o WHERE o.source='user_search' GROUP BY o.hotel_id");
    $seen=[];foreach($obs as $r){$id=(int)$r['hotel_id'];if($id>0)$seen[$id]=['last_observed_at'=>(string)$r['last_observed_at'],'observation_rows'=>(int)$r['observation_rows']];}
    if(!$seen)throw new RuntimeException('no_user_search_observations');

    $seenIds=array_keys($seen);$sp=implode(',',array_fill(0,count($seenIds),'?'));
    $localRows=hmu_rows($pdo,"SELECT id,country_id,country_name,name,region_name,subregion_name,is_active FROM catalog_hotels WHERE id IN ($sp)",$seenIds);
    $frontier=[];$localById=[];
    foreach($localRows as $r){
        $id=(int)$r['id'];$localById[$id]=$r;
        if((int)$r['is_active']!==1||isset($acceptedAnexTargets[$id])||isset($acceptedAndromedaTargets[$id]))continue;
        $frontier[$id]=['hotel_id'=>$id,'country_id'=>(int)$r['country_id'],'country_name'=>(string)$r['country_name'],
            'name'=>(string)$r['name'],'region_name'=>(string)$r['region_name'],'subregion_name'=>(string)$r['subregion_name'],
            'last_observed_at'=>$seen[$id]['last_observed_at'],'observation_rows'=>$seen[$id]['observation_rows']];
    }

    $frontierCountries=[];foreach($frontier as $r)if((int)$r['country_id']>0)$frontierCountries[(int)$r['country_id']]=true;
    if(!$frontierCountries)throw new RuntimeException('frontier_country_missing');
    $countryIds=array_keys($frontierCountries);sort($countryIds,SORT_NUMERIC);
    $cp=implode(',',array_fill(0,count($countryIds),'?'));
    $active=hmu_rows($pdo,"SELECT id,country_id,country_name,name FROM catalog_hotels WHERE is_active=1 AND country_id IN ($cp)",$countryIds);
    $localIndex=[];$countryNameToId=[];
    foreach($active as $r){
        $id=(int)$r['id'];$cid=(int)$r['country_id'];$key=hmu_key((string)$r['name']);
        if($cid>0&&$key!=='')$localIndex[$cid][$key][$id]=true;
        $cn=hmu_country_name((string)$r['country_name']);if($cid>0&&$cn!=='')$countryNameToId[$cn][$cid]=true;
    }

    $pairExclusions=[];
    if(hmu_table($pdo,'anex_review_pair_exclusions')){
        foreach(hmu_rows($pdo,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions') as $r)
            $pairExclusions[(string)$r['anex_hotel_id'].'|'.(int)$r['catalog_hotel_id']]=true;
    }
    $manualAnex=[];foreach(hmu_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_decisions') as $r)$manualAnex[(string)$r['anex_hotel_id']]=true;

    $anexNames=[];$anexCountries=[];
    if(hmu_table($pdo,'anex_search_hotel_observations')){
        $c=hmu_cols($pdo,'anex_search_hotel_observations');$select=['anex_hotel_id'];
        foreach(['hotel_name','country_id','country_name','region_name'] as $x)if(isset($c[$x]))$select[]=$x;
        foreach(hmu_rows($pdo,'SELECT '.implode(',',$select).' FROM anex_search_hotel_observations') as $r){
            $id=(string)($r['anex_hotel_id']??'');if($id==='')continue;
            if(!isset($anexNames[$id]))$anexNames[$id]=[];
            hmu_add_name($anexNames[$id],$r['hotel_name']??'');
            if(isset($r['country_id'])&&(int)$r['country_id']>0)$anexCountries[$id][(int)$r['country_id']]=true;
            if(isset($r['country_name'])){
                $n=hmu_country_name((string)$r['country_name']);
                foreach(array_keys($countryNameToId[$n]??[]) as $cid)$anexCountries[$id][(int)$cid]=true;
            }
        }
    }
    if(hmu_table($pdo,'anex_hotels')){
        $c=hmu_cols($pdo,'anex_hotels');$select=['anex_hotel_id'];
        foreach(['api_name','xml_name','xml_alternate_name','api_country'] as $x)if(isset($c[$x]))$select[]=$x;
        foreach(hmu_rows($pdo,'SELECT '.implode(',',$select).' FROM anex_hotels') as $r){
            $id=(string)($r['anex_hotel_id']??'');if($id==='')continue;
            if(!isset($anexNames[$id]))$anexNames[$id]=[];
            foreach(['api_name','xml_name','xml_alternate_name'] as $x)hmu_add_name($anexNames[$id],$r[$x]??'');
            if(isset($r['api_country'])){
                $n=hmu_country_name((string)$r['api_country']);
                foreach(array_keys($countryNameToId[$n]??[]) as $cid)$anexCountries[$id][(int)$cid]=true;
            }
        }
    }

    $anexCandidates=[];$anexBlocked=[];
    foreach($anexNames as $id=>$names){
        if(isset($manualAnex[$id])||$registry->resolve('anex_online',$id,'preview')!==null)continue;
        $r=hmu_candidate_for_source('anex',(string)$id,$names,array_keys($anexCountries[$id]??[]),$localIndex,$frontier,$pairExclusions);
        if($r['status']==='candidate')$anexCandidates[]=$r;else$anexBlocked[$r['reason']]=($anexBlocked[$r['reason']]??0)+1;
    }

    $andObsNames=[];$andObsCountries=[];
    if(hmu_table($pdo,'andromeda_search_hotel_observations')){
        $c=hmu_cols($pdo,'andromeda_search_hotel_observations');$select=['external_hotel_id'];
        foreach(['supplier_namespace','hotel_name','country_id','country_name'] as $x)if(isset($c[$x]))$select[]=$x;
        foreach(hmu_rows($pdo,'SELECT '.implode(',',$select).' FROM andromeda_search_hotel_observations') as $r){
            if(isset($r['supplier_namespace'])&&(string)$r['supplier_namespace']!=='andromeda_catalog')continue;
            $id=(string)($r['external_hotel_id']??'');if($id==='')continue;
            if(!isset($andObsNames[$id]))$andObsNames[$id]=[];
            hmu_add_name($andObsNames[$id],$r['hotel_name']??'');
            if(isset($r['country_id'])&&(int)$r['country_id']>0)$andObsCountries[$id][(int)$r['country_id']]=true;
            if(isset($r['country_name'])){
                $n=hmu_country_name((string)$r['country_name']);
                foreach(array_keys($countryNameToId[$n]??[]) as $cid)$andObsCountries[$id][(int)$cid]=true;
            }
        }
    }

    $andCols=hmu_cols($pdo,'andromeda_hotel_identities');
    $sel=['external_hotel_id','decision_status','local_hotel_id'];
    foreach(['supplier_namespace','country_id','evidence_json'] as $x)if(isset($andCols[$x]))$sel[]=$x;
    $andCandidates=[];$andBlocked=[];
    foreach(hmu_rows($pdo,'SELECT '.implode(',',$sel)." FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL") as $r){
        $id=(string)$r['external_hotel_id'];$names=$andObsNames[$id]??[];$countries=$andObsCountries[$id]??[];
        if(isset($r['country_id'])&&(int)$r['country_id']>0)$countries[(int)$r['country_id']]=true;
        $raw=(string)($r['evidence_json']??'');if($raw!==''){
            try{$e=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(is_array($e))hmu_walk_names($e,$names);}
            catch(Throwable){}
        }
        $rr=hmu_candidate_for_source('andromeda',$id,$names,array_keys($countries),$localIndex,$frontier);
        if($rr['status']==='candidate')$andCandidates[]=$rr;else$andBlocked[$rr['reason']]=($andBlocked[$rr['reason']]??0)+1;
    }

    $targetProviders=[];foreach($anexCandidates as $r)$targetProviders[$r['local_hotel_id']]['anex'][]=$r['external_hotel_id'];
    foreach($andCandidates as $r)$targetProviders[$r['local_hotel_id']]['andromeda'][]=$r['external_hotel_id'];
    $targetRows=[];
    foreach($targetProviders as $local=>$providers){
        $f=$frontier[$local];$targetRows[]=$f+[
            'providers'=>$providers,'provider_count'=>count($providers),
            'source_identity_count'=>array_sum(array_map('count',$providers)),
        ];
    }
    usort($targetRows,fn($a,$b)=>$b['observation_rows']<=>$a['observation_rows'] ?: strcmp($b['last_observed_at'],$a['last_observed_at']) ?: $a['hotel_id']<=>$b['hotel_id']);

    $result=[
        'operation'=>HMU_OPERATION,'status'=>'read_only_complete',
        'user_seen_unmapped_frontier_count'=>count($frontier),'frontier_country_ids'=>$countryIds,'active_catalog_rows_indexed'=>count($active),'alias_matching_in_this_pass'=>false,
        'anex_candidate_identity_count'=>count($anexCandidates),
        'andromeda_candidate_identity_count'=>count($andCandidates),
        'candidate_identity_count'=>count($anexCandidates)+count($andCandidates),
        'candidate_unique_local_hotels'=>count($targetRows),
        'candidate_both_provider_local_hotels'=>count(array_filter($targetRows,fn($r)=>isset($r['providers']['anex'],$r['providers']['andromeda']))),
        'anex_blocked_counts'=>$anexBlocked,'andromeda_blocked_counts'=>$andBlocked,
        'candidate_targets'=>$targetRows,'anex_candidates'=>$anexCandidates,'andromeda_candidates'=>$andCandidates,
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,
    ];
    $pdo->rollBack();
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();throw $e;
}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
