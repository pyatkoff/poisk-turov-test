<?php
declare(strict_types=1);

const HATV30_OPERATION='hotel-match-anex-user-seen-tv30-current-plan-1971-20260919-v2';
const HATV30_BATCH=30;
const HATV30_OPERATOR_ID=13;

function hatv30_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql); $s->execute(array_values($params)); return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function hatv30_table(PDO $pdo,string $t): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$t]); return $s->fetchColumn()!==false;
}
function hatv30_scalar(mixed $v,int $max=255): string {
    return is_scalar($v)?mb_substr(trim((string)$v),0,$max,'UTF-8'):'';
}
function hatv30_opaque(string $name): bool {
    $n=mb_strtoupper($name,'UTF-8');
    foreach(['FORTUNA','ROULETTE','РУЛЕТКА','РУЛЕТ'] as $x) if(mb_strpos($n,$x,0,'UTF-8')!==false) return true;
    return false;
}
function hatv30_excluded_country(string $name): bool {
    $n=mb_strtolower(trim($name),'UTF-8');
    return in_array($n,['россия','абхазия'],true);
}
function hatv30_prior_provider_touched(): array {
    $ids=[
        112358,60006,81356,60957,92738,130835,60968,70035,89073,114210,59991,81750,92745,60985,99759,60940,60947,70424,95553,60257,69289,69290,81197,100475,127325,68625,99760,101025,101026,59990,
        1063,1441,
        66037,950,1580,42576,2191,75538,72865,1244,115349,75791,152266,132017,42596,103212,42592,131058,42581,75871,156593,9443,147573,9427,99582,157197,62868,967,132716,1151,1546,1500
    ];
    return array_fill_keys($ids,true);
}
if(in_array('--self-test',$argv??[],true)){
    if(HATV30_BATCH!==30 || HATV30_OPERATOR_ID!==13) throw new RuntimeException('constants');
    if(!hatv30_opaque('FORTUNA MARMARIS') || !hatv30_opaque('Roulette 5*') || hatv30_opaque('HOTEL SU')) throw new RuntimeException('opaque');
    if(!hatv30_excluded_country('Россия') || hatv30_excluded_country('Турция')) throw new RuntimeException('country');
    echo "MATCH_ANEX_USER_SEEN_TV30_CURRENT_PLAN_V1_SELFTEST_OK\n"; exit(0);
}
if(PHP_SAPI!=='cli') exit(2);
$root=realpath((string)getenv('ANYTOUR_ROOT')); if(!$root) throw new RuntimeException('root');
$opDir=realpath((string)getenv('MATCH_OPERATION_DIR')); if(!$opDir) throw new RuntimeException('operation_dir');
$registryFile=$opDir.'/payload/anex-search-mapping-registry.php';
if(!is_file($registryFile)) throw new RuntimeException('registry');
require_once $registryFile;
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
require_once $dbf;
$pdo=v2_data_db(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['tour_price_observations','catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','andromeda_hotel_identities'] as $t) if(!hatv30_table($pdo,$t)) throw new RuntimeException('missing_'.$t);

$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION READ ONLY');
try {
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $anexTargets=[];
    foreach(hatv30_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $r){
        $x=$registry->resolve('anex_online',(string)$r['anex_hotel_id'],'preview');
        if(is_int($x)&&$x>0) $anexTargets[$x]=true;
    }
    $andTargets=[];
    foreach(hatv30_rows($pdo,"SELECT DISTINCT i.local_hotel_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL AND h.is_active=1") as $r){
        $x=(int)$r['local_hotel_id']; if($x>0) $andTargets[$x]=true;
    }

    $rows=hatv30_rows($pdo,"SELECT o.hotel_id,o.departure_id,o.country_id,o.departure_date,o.nights,o.search_id,o.tour_id,o.observed_at,
            h.name hotel_name,h.country_name,h.region_id,h.region_name,h.subregion_id,h.subregion_name,h.is_active
        FROM tour_price_observations o
        JOIN catalog_hotels h ON h.id=o.hotel_id
        WHERE o.source='user_search'
          AND o.operator_id=?
          AND o.departure_date>=CURRENT_DATE()
          AND o.nights BETWEEN 7 AND 10
          AND o.adults=2 AND o.children_count=0
          AND o.tour_id IS NOT NULL AND o.tour_id<>''
        ORDER BY o.observed_at DESC",[HATV30_OPERATOR_ID]);

    $prior=hatv30_prior_provider_touched();
    $stats=['raw_rows'=>count($rows),'inactive'=>0,'current_anex'=>0,'current_andromeda'=>0,'prior_provider_touched'=>0,'opaque'=>0,'excluded_country'=>0,'eligible_rows'=>0];
    $eligible=[]; $hotelTotals=[]; $hotelLatest=[]; $hotelMeta=[];
    foreach($rows as $r){
        $hid=(int)$r['hotel_id']; if($hid<1) continue;
        if((int)$r['is_active']!==1){$stats['inactive']++;continue;}
        if(isset($anexTargets[$hid])){$stats['current_anex']++;continue;}
        if(isset($andTargets[$hid])){$stats['current_andromeda']++;continue;}
        if(isset($prior[$hid])){$stats['prior_provider_touched']++;continue;}
        $name=(string)$r['hotel_name']; if(hatv30_opaque($name)){$stats['opaque']++;continue;}
        if(hatv30_excluded_country((string)$r['country_name'])){$stats['excluded_country']++;continue;}
        $date=hatv30_scalar($r['departure_date']??'',10);
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) continue;
        $row=[
            'hotel_id'=>$hid,'hotel_name'=>$name,'departure_id'=>(int)$r['departure_id'],'country_id'=>(int)$r['country_id'],
            'country_name'=>(string)$r['country_name'],'region_id'=>$r['region_id']===null?null:(int)$r['region_id'],
            'region_name'=>(string)($r['region_name']??''),'subregion_id'=>$r['subregion_id']===null?null:(int)$r['subregion_id'],
            'subregion_name'=>(string)($r['subregion_name']??''),'departure_date'=>$date,'nights'=>(int)$r['nights'],
            'search_id'=>$r['search_id']===null?null:hatv30_scalar($r['search_id'],80),'tour_id'=>hatv30_scalar($r['tour_id'],220),
            'observed_at'=>(string)$r['observed_at']
        ];
        $eligible[]=$row; $stats['eligible_rows']++;
        $hotelTotals[$hid]=($hotelTotals[$hid]??0)+1;
        if(!isset($hotelLatest[$hid]) || strcmp($row['observed_at'],$hotelLatest[$hid])>0) $hotelLatest[$hid]=$row['observed_at'];
        $hotelMeta[$hid]=$row;
    }

    $pairs=[];
    foreach($eligible as $r){
        $key=$r['departure_id'].'|'.$r['country_id'];
        $pairs[$key][]=$r;
    }
    $contexts=[];
    foreach($pairs as $key=>$pr){
        $dates=[]; foreach($pr as $r) $dates[$r['departure_date']]=true; $dates=array_keys($dates); sort($dates,SORT_STRING);
        foreach($dates as $start){
            $end=(new DateTimeImmutable($start))->modify('+6 days')->format('Y-m-d');
            $byHotel=[];
            foreach($pr as $r){
                if($r['departure_date']<$start || $r['departure_date']>$end) continue;
                $hid=$r['hotel_id'];
                if(!isset($byHotel[$hid]) || strcmp($r['observed_at'],$byHotel[$hid]['observed_at'])>0) $byHotel[$hid]=$r;
            }
            if(!$byHotel) continue;
            $hotelRows=array_values($byHotel);
            usort($hotelRows,function($a,$b)use($hotelTotals,$hotelLatest){
                $wa=$hotelTotals[$a['hotel_id']]??0; $wb=$hotelTotals[$b['hotel_id']]??0;
                return $wb<=>$wa ?: strcmp($hotelLatest[$b['hotel_id']]??'',$hotelLatest[$a['hotel_id']]??'') ?: $a['hotel_id']<=>$b['hotel_id'];
            });
            $selected=array_slice($hotelRows,0,HATV30_BATCH);
            $weight=0; foreach($selected as &$x){$x['anex_user_observation_rows']=$hotelTotals[$x['hotel_id']]??0;$weight+=$x['anex_user_observation_rows'];} unset($x);
            $contexts[]=[
                'departure_id'=>$selected[0]['departure_id'],'country_id'=>$selected[0]['country_id'],'country_name'=>$selected[0]['country_name'],
                'date_from'=>$start,'date_to'=>$end,'nights_from'=>7,'nights_to'=>10,'adults'=>2,'children_count'=>0,
                'eligible_unique_hotels'=>count($byHotel),'selected_count'=>count($selected),'selected_weight'=>$weight,
                'hotel_ids'=>array_values(array_map(fn($x)=>(int)$x['hotel_id'],$selected)),'hotels'=>$selected
            ];
        }
    }
    usort($contexts,function($a,$b){
        return $b['selected_count']<=>$a['selected_count'] ?: $b['selected_weight']<=>$a['selected_weight'] ?: strcmp($b['date_from'],$a['date_from']) ?: $a['country_id']<=>$b['country_id'];
    });
    $best=$contexts[0]??null;
    $pdo->rollBack();
    $result=[
        'operation'=>HATV30_OPERATION,'status'=>'read_only_complete','operator_id'=>HATV30_OPERATOR_ID,
        'current_counts'=>['accepted_anex_local_targets'=>count($anexTargets),'accepted_andromeda_local_targets'=>count($andTargets)],
        'stats'=>$stats,'eligible_unique_hotels'=>count($hotelTotals),'context_count'=>count($contexts),
        'top_contexts'=>array_slice($contexts,0,20),'best_batch'=>$best,
        'supplier_calls'=>0,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0
    ];
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack(); throw $e;
}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
