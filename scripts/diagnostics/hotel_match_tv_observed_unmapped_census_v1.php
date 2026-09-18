<?php
declare(strict_types=1);

const OP='hotel-match-tourvisor-observed-unmapped-census-1971-20260918-v1';

function q(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function table_exists(PDO $pdo,string $name): bool {
    $s=$pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1");
    $s->execute([$name]);return $s->fetchColumn()!==false;
}
function observed(PDO $pdo,?string $source,bool $recent): array {
    $where=[];$params=[];
    if($source!==null){$where[]='o.source=?';$params[]=$source;}
    if($recent)$where[]='o.observed_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)';
    $sql="SELECT o.hotel_id,
                 COALESCE(h.country_id,MAX(o.country_id)) country_id,
                 COALESCE(h.country_name,'') country_name,
                 COALESCE(h.name,'') hotel_name,
                 MAX(o.observed_at) last_observed_at,
                 COUNT(*) observation_rows
            FROM tour_price_observations o
       LEFT JOIN catalog_hotels h ON h.id=o.hotel_id"
       .($where?' WHERE '.implode(' AND ',$where):'').
       " GROUP BY o.hotel_id,h.country_id,h.country_name,h.name ORDER BY o.hotel_id";
    $rows=q($pdo,$sql,$params);$out=[];
    foreach($rows as $r){
        $id=(int)($r['hotel_id']??0);if($id<1)continue;
        $out[$id]=[
            'hotel_id'=>$id,'country_id'=>(int)($r['country_id']??0),
            'country_name'=>(string)($r['country_name']??''),
            'hotel_name'=>(string)($r['hotel_name']??''),
            'last_observed_at'=>(string)($r['last_observed_at']??''),
            'observation_rows'=>(int)($r['observation_rows']??0),
        ];
    }
    return $out;
}
function coverage(array $observed,array $anex,array $andr): array {
    $ids=array_keys($observed);$a=0;$d=0;$both=0;$any=0;$unmapped=[];$countries=[];
    foreach($ids as $id){
        $ha=isset($anex[$id]);$hd=isset($andr[$id]);
        if($ha)$a++;if($hd)$d++;if($ha&&$hd)$both++;if($ha||$hd)$any++;
        if(!$ha&&!$hd)$unmapped[]=$id;
        $r=$observed[$id];$cid=(string)$r['country_id'];$name=$r['country_name'];
        if(!isset($countries[$cid]))$countries[$cid]=['country_id'=>(int)$cid,'country_name'=>$name,'observed'=>0,'mapped_any'=>0,'unmapped'=>0];
        $countries[$cid]['observed']++;
        if($ha||$hd)$countries[$cid]['mapped_any']++;else$countries[$cid]['unmapped']++;
    }
    usort($countries,fn($x,$y)=>$y['unmapped']<=>$x['unmapped'] ?: $y['observed']<=>$x['observed'] ?: $x['country_id']<=>$y['country_id']);
    $sample=[];
    foreach(array_slice($unmapped,0,50) as $id)$sample[]=$observed[$id];
    return [
        'observed_unique_hotels'=>count($ids),
        'mapped_anex_unique_hotels'=>$a,
        'mapped_andromeda_unique_hotels'=>$d,
        'mapped_both_unique_hotels'=>$both,
        'mapped_any_unique_hotels'=>$any,
        'unmapped_by_both_unique_hotels'=>count($unmapped),
        'coverage_percent'=>count($ids)?round($any*100/count($ids),2):0.0,
        'country_breakdown'=>$countries,
        'unmapped_sample'=>$sample,
    ];
}

if(PHP_SAPI!=='cli')exit(2);
$root=realpath((string)getenv('ANYTOUR_ROOT'));
$opdir=realpath((string)getenv('MATCH_OPERATION_DIR'));
if(!$root||!$opdir||!is_file($root.'/app/integrations/anex-search-mapping-registry.php'))throw new RuntimeException('runtime_paths');
require_once $opdir.'/payload/anex-search-mapping-registry.php';
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbf;
$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['tour_price_observations','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities'] as $t)
    if(!table_exists($pdo,$t))throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION READ ONLY');
try{
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $candidateIds=q($pdo,"SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions");
    $anexTargets=[];
    foreach($candidateIds as $r){
        $eid=(string)($r['anex_hotel_id']??'');
        if($eid==='')continue;$target=$registry->resolve('anex_online',$eid,'preview');
        if(is_int($target)&&$target>0)$anexTargets[$target]=true;
    }
    $andrTargets=[];
    foreach(q($pdo,"SELECT DISTINCT i.local_hotel_id
                      FROM andromeda_hotel_identities i
                      JOIN catalog_hotels h ON h.id=i.local_hotel_id
                     WHERE i.supplier_namespace='andromeda_catalog'
                       AND i.decision_status='accepted'
                       AND i.local_hotel_id IS NOT NULL") as $r){
        $id=(int)($r['local_hotel_id']??0);if($id>0)$andrTargets[$id]=true;
    }

    $sets=[
        'user_search_ever'=>observed($pdo,'user_search',false),
        'user_search_last_30d'=>observed($pdo,'user_search',true),
        'all_tourvisor_observations_ever'=>observed($pdo,null,false),
        'all_tourvisor_observations_last_30d'=>observed($pdo,null,true),
    ];
    $result=[
        'operation'=>OP,'status'=>'read_only_complete',
        'definition'=>[
            'observed'=>'DISTINCT tour_price_observations.hotel_id',
            'user_search'=>'source=user_search',
            'anex_mapping'=>'current AnyTourAnexSearchMappingRegistry preview semantics',
            'andromeda_mapping'=>'andromeda_catalog decision_status=accepted with existing local_hotel_id',
            'unmapped'=>'neither accepted ANEX nor accepted Andromeda correspondence',
        ],
        'current_mapping_universe'=>[
            'anex_external_identity_count'=>$registry->count(),
            'anex_unique_local_targets'=>count($anexTargets),
            'andromeda_unique_local_targets'=>count($andrTargets),
            'union_unique_local_targets'=>count($anexTargets+$andrTargets),
        ],
        'sets'=>[],
        'database_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,
    ];
    foreach($sets as $k=>$rows)$result['sets'][$k]=coverage($rows,$anexTargets,$andrTargets);
    $pdo->rollBack();
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
