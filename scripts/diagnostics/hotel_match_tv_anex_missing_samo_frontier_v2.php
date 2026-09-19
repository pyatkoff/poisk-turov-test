<?php
declare(strict_types=1);

const HMMS_OP='hotel-match-tv-anex-missing-samo-frontier-1971-20260919-v2';
const HMMS_POLICY='owner_exact_and_strong_20260908';
const HMMS_MAX_ROWS=500000;
const HMMS_CONTEXT_OUTPUT_LIMIT=250;

function hmms_req(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmms_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function hmms_write_once(string $path,array $v):string{
    $raw=hmms_json($v);$f=@fopen($path,'x+b');hmms_req(is_resource($f),'exclusive_output');
    try{hmms_req(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))hmms_req(fsync($f),'output_sync');rewind($f);hmms_req(stream_get_contents($f)===$raw,'output_readback');}
    finally{fclose($f);}
    return hash('sha256',$raw);
}
function hmms_read_json(string $path):array{
    hmms_req(!is_link($path)&&realpath($path)===$path&&is_file($path),'input_path');
    $raw=file_get_contents($path);hmms_req(is_string($raw)&&strlen($raw)<=1048576,'input_read');
    $v=json_decode($raw,true,64,JSON_THROW_ON_ERROR);hmms_req(is_array($v),'input_shape');return $v;
}
function hmms_q(PDO $db,string $sql,array $args=[]):array{
    $q=$db->prepare($sql);$q->execute(array_values($args));$rows=$q->fetchAll(PDO::FETCH_ASSOC)?:[];
    hmms_req(count($rows)<=HMMS_MAX_ROWS,'row_budget');return $rows;
}
function hmms_norm(string $v):string{
    $v=trim($v);
    if(function_exists('mb_strtolower'))$v=mb_strtolower($v,'UTF-8'); else $v=strtolower($v);
    $v=strtr($v,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function hmms_opaque(string $name):bool{return preg_match('/(?:^|[^\p{L}])(?:FORTUNA|ROULETTE|ФОРТУНА|РУЛЕТКА)(?:$|[^\p{L}])/iu',$name)===1;}
function hmms_excluded_market(string $country):bool{
    $n=hmms_norm($country);
    return in_array($n,['россия','russia','russian federation','абхазия','abkhazia'],true);
}
function hmms_positive_id(mixed $v):?string{
    if(!is_scalar($v))return null;$s=trim((string)$v);
    return preg_match('/^[1-9][0-9]{0,31}$/D',$s)?$s:null;
}
function hmms_evidence(mixed $raw):array{
    if(!is_string($raw)||$raw==='')return[];
    try{$v=json_decode($raw,true,96,JSON_THROW_ON_ERROR);return is_array($v)?$v:[];}catch(Throwable){return[];}
}
function hmms_bridge_ids(mixed $node,array &$out,int $depth=0):void{
    if($depth>16||!is_array($node))return;
    foreach($node as $k=>$v){
        $key=is_string($k)?$k:'';
        if(in_array($key,['andromeda_hotel_id','andromedaHotelId'],true)){
            $id=hmms_positive_id($v);if($id!==null)$out[$id]=true;
        }
        if(is_array($v))hmms_bridge_ids($v,$out,$depth+1);
    }
}
function hmms_chunks(array $ids,int $n=500):array{
    $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids,SORT_NUMERIC);
    return array_chunk($ids,$n);
}
function hmms_by_ids(PDO $db,string $prefix,array $ids):array{
    $out=[];foreach(hmms_chunks($ids) as $chunk){if(!$chunk)continue;$ph=implode(',',array_fill(0,count($chunk),'?'));foreach(hmms_q($db,$prefix."($ph)",$chunk) as $r)$out[]=$r;}return$out;
}
function hmms_ctx_key(array $r):string{
    return implode('|',[
        (int)$r['departure_id'],(int)$r['country_id'],(string)$r['departure_date'],(int)$r['nights'],
        (int)$r['adults'],(int)$r['children_count'],(string)$r['child_ages_signature']
    ]);
}
function hmms_main():void{
    hmms_req(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===HMMS_OP,'operation_guard');
    $sha=(string)getenv('MATCH_SOURCE_SHA');hmms_req(preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'source_sha');
    $dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.HMMS_OP;
    $reservation=hmms_read_json($dir.'/reservation.json');
    hmms_req(($reservation['operation_id']??'')===HMMS_OP&&($reservation['source_sha']??'')===$sha&&($reservation['state']??'')==='reserved_before_db_access','reservation_guard');
    $root=realpath(getcwd());hmms_req(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
    $dbPath=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');hmms_req(is_file($dbPath),'db_runtime');
    require_once $dbPath;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $out=['operation_id'=>HMMS_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0];
    try{
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        foreach(['catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities','tour_price_observations'] as $t){
            $r=hmms_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);
            hmms_req(count($r)===1&&strtoupper((string)$r[0]['ENGINE'])==='INNODB','table_contract_'.$t);
        }

        $anexByLocal=[];$anexAuthority=[];
        $automatic=hmms_q($db,"SELECT m.anex_hotel_id,m.catalog_hotel_id,m.approval_policy
          FROM anex_hotel_search_mappings m
          LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id
          WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy=?
            AND d.anex_hotel_id IS NULL
            AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)",[HMMS_POLICY]);
        foreach($automatic as $r){$local=(int)$r['catalog_hotel_id'];$aid=(int)$r['anex_hotel_id'];if($local>0&&$aid>0){$anexByLocal[$local][$aid]=true;$anexAuthority[$local][$aid]='mapping';}}
        $manual=hmms_q($db,"SELECT d.anex_hotel_id,d.catalog_hotel_id
          FROM anex_hotel_decisions d
          WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL
            AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)");
        foreach($manual as $r){$local=(int)$r['catalog_hotel_id'];$aid=(int)$r['anex_hotel_id'];if($local>0&&$aid>0){$anexByLocal[$local][$aid]=true;$anexAuthority[$local][$aid]='manual';}}

        $samoByLocal=[];$samoRows=hmms_q($db,"SELECT external_hotel_id,local_hotel_id
          FROM andromeda_hotel_identities
          WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL");
        foreach($samoRows as $r){$local=(int)$r['local_hotel_id'];if($local>0)$samoByLocal[$local][(string)$r['external_hotel_id']]=true;}
        $tripleLocals=array_intersect_key($anexByLocal,$samoByLocal);
        $missingIds=array_values(array_map('intval',array_keys(array_diff_key($anexByLocal,$samoByLocal))));sort($missingIds,SORT_NUMERIC);

        $catalog=[];
        $prefix="SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ";
        foreach(hmms_by_ids($db,$prefix,$missingIds) as $r)$catalog[(int)$r['id']]=$r;

        $op5=[];
        foreach(hmms_q($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json
          FROM andromeda_hotel_identities WHERE supplier_namespace='operator_5'") as $r){
            $id=hmms_positive_id($r['external_hotel_id']??null);if($id===null)continue;
            $bridges=[];hmms_bridge_ids(hmms_evidence($r['evidence_json']??''),$bridges);
            $op5[$id]=[
                'external_hotel_id'=>$id,
                'decision_status'=>(string)$r['decision_status'],
                'local_hotel_id'=>$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'],
                'evidence_sha256'=>(string)$r['evidence_sha256'],
                'andromeda_hotel_ids'=>array_values(array_keys($bridges)),
            ];
        }
        $andCatalogByExternal=[];
        foreach(hmms_q($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256
          FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'") as $r){
            $andCatalogByExternal[(string)$r['external_hotel_id']]=[
                'decision_status'=>(string)$r['decision_status'],
                'local_hotel_id'=>$r['local_hotel_id']===null?null:(int)$r['local_hotel_id'],
                'evidence_sha256'=>(string)$r['evidence_sha256'],
            ];
        }

        $userStats=[];
        foreach(hmms_chunks($missingIds) as $chunk){
            if(!$chunk)continue;$ph=implode(',',array_fill(0,count($chunk),'?'));
            foreach(hmms_q($db,"SELECT hotel_id,COUNT(*) observation_rows,
                SUM(CASE WHEN operator_id=13 THEN 1 ELSE 0 END) anex_observation_rows,
                MAX(observed_at) last_seen_at
                FROM tour_price_observations
                WHERE source='user_search' AND hotel_id IN ($ph)
                GROUP BY hotel_id",$chunk) as $r){
                $userStats[(int)$r['hotel_id']]=[
                    'observation_rows'=>(int)$r['observation_rows'],
                    'anex_observation_rows'=>(int)$r['anex_observation_rows'],
                    'last_seen_at'=>(string)$r['last_seen_at'],
                ];
            }
        }

        $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
        $contexts=[];$latestFuture=[];
        foreach(hmms_chunks($missingIds) as $chunk){
            if(!$chunk)continue;$ph=implode(',',array_fill(0,count($chunk),'?'));$args=array_merge([$today],$chunk);
            $rows=hmms_q($db,"SELECT hotel_id,country_id,region_id,subregion_id,departure_id,departure_date,nights,adults,children_count,child_ages_signature,search_id,tour_id,observed_at
              FROM tour_price_observations
              WHERE source='user_search' AND operator_id=13 AND departure_date>=? AND hotel_id IN ($ph)
              ORDER BY observed_at DESC,hotel_id", $args);
            foreach($rows as $r){
                $hid=(int)$r['hotel_id'];if(!isset($anexByLocal[$hid]))continue;
                if(!isset($latestFuture[$hid]))$latestFuture[$hid]=[
                    'departure_id'=>(int)$r['departure_id'],'country_id'=>(int)$r['country_id'],
                    'region_id'=>$r['region_id']===null?null:(int)$r['region_id'],'subregion_id'=>$r['subregion_id']===null?null:(int)$r['subregion_id'],
                    'departure_date'=>(string)$r['departure_date'],'nights'=>(int)$r['nights'],'adults'=>(int)$r['adults'],
                    'children_count'=>(int)$r['children_count'],'child_ages_signature'=>(string)$r['child_ages_signature'],
                    'search_id'=>(int)$r['search_id'],'tour_id'=>$r['tour_id']===null?null:(string)$r['tour_id'],'observed_at'=>(string)$r['observed_at']
                ];
                $k=hmms_ctx_key($r);
                if(!isset($contexts[$k]))$contexts[$k]=[
                    'departure_id'=>(int)$r['departure_id'],'country_id'=>(int)$r['country_id'],'departure_date'=>(string)$r['departure_date'],
                    'nights'=>(int)$r['nights'],'adults'=>(int)$r['adults'],'children_count'=>(int)$r['children_count'],
                    'child_ages_signature'=>(string)$r['child_ages_signature'],'observation_rows'=>0,'targets'=>[]
                ];
                $contexts[$k]['observation_rows']++;
                $contexts[$k]['targets'][$hid]=array_values(array_map('intval',array_keys($anexByLocal[$hid])));
            }
        }

        $frontier=[];$excluded=['missing_catalog'=>0,'inactive'=>0,'market'=>0,'opaque'=>0];$routeCounts=[];$byCountry=[];
        foreach($missingIds as $local){
            $h=$catalog[$local]??null;
            if(!$h){$excluded['missing_catalog']++;continue;}
            if((int)$h['is_active']!==1){$excluded['inactive']++;continue;}
            if(hmms_excluded_market((string)$h['country_name'])){$excluded['market']++;continue;}
            if(hmms_opaque((string)$h['name'])){$excluded['opaque']++;continue;}
            $aids=array_values(array_map('intval',array_keys($anexByLocal[$local])));sort($aids,SORT_NUMERIC);
            $typed=[];$bridgeCatalog=[];$conflict=false;
            foreach($aids as $aid){
                $row=$op5[(string)$aid]??null;if(!$row)continue;
                $item=$row;unset($item['evidence_json']);
                foreach($row['andromeda_hotel_ids'] as $bridgeId){
                    $bridgeCatalog[$bridgeId]=$andCatalogByExternal[$bridgeId]??['decision_status'=>'absent','local_hotel_id'=>null,'evidence_sha256'=>null];
                    if(($bridgeCatalog[$bridgeId]['decision_status']??'')==='accepted' && (int)($bridgeCatalog[$bridgeId]['local_hotel_id']??0)!==$local)$conflict=true;
                }
                if($row['decision_status']==='accepted'&&$row['local_hotel_id']!==null&&$row['local_hotel_id']!==$local)$conflict=true;
                $typed[]=$item;
            }
            $stats=$userStats[$local]??['observation_rows'=>0,'anex_observation_rows'=>0,'last_seen_at'=>null];
            $route=$conflict?'saved_evidence_conflict_hold':($bridgeCatalog?'saved_direct_bridge_review':(isset($latestFuture[$local])?'query_ready_user_seen_anex':($stats['observation_rows']>0?'user_seen_needs_anex_context':'catalog_only')));
            $routeCounts[$route]=($routeCounts[$route]??0)+1;
            $country=(string)$h['country_name'];$byCountry[$country]=($byCountry[$country]??0)+1;
            $frontier[]=[
                'tv_hotel_id'=>$local,'hotel_name'=>(string)$h['name'],'country_id'=>(int)$h['country_id'],'country_name'=>$country,
                'region_id'=>$h['region_id']===null?null:(int)$h['region_id'],'region_name'=>(string)$h['region_name'],
                'subregion_id'=>$h['subregion_id']===null?null:(int)$h['subregion_id'],'subregion_name'=>(string)$h['subregion_name'],
                'category'=>$h['category']===null?null:(int)$h['category'],'anex_hotel_ids'=>$aids,
                'anex_authority'=>array_map(fn($id)=>$anexAuthority[$local][$id]??'unknown',$aids),
                'user_search'=>$stats,'latest_future_anex_context'=>$latestFuture[$local]??null,
                'operator_5_facts'=>$typed,'saved_andromeda_bridge_ids'=>$bridgeCatalog,'route'=>$route,
                'safe_to_query_provider'=>$route==='query_ready_user_seen_anex','safe_to_write_now'=>false,
            ];
        }
        ksort($routeCounts,SORT_STRING);arsort($byCountry,SORT_NUMERIC);
        usort($frontier,static function($a,$b){
            $rank=['saved_direct_bridge_review'=>0,'query_ready_user_seen_anex'=>1,'user_seen_needs_anex_context'=>2,'catalog_only'=>3,'saved_evidence_conflict_hold'=>4];
            $ra=$rank[$a['route']]??9;$rb=$rank[$b['route']]??9;if($ra!==$rb)return $ra<=>$rb;
            $ao=(int)$a['user_search']['observation_rows'];$bo=(int)$b['user_search']['observation_rows'];if($ao!==$bo)return $bo<=>$ao;
            $aa=(int)$a['user_search']['anex_observation_rows'];$ba=(int)$b['user_search']['anex_observation_rows'];if($aa!==$ba)return $ba<=>$aa;
            return $a['tv_hotel_id']<=>$b['tv_hotel_id'];
        });

        $contextRows=[];
        foreach($contexts as $c){
            $targets=[];$native=[];
            foreach($c['targets'] as $local=>$ids){
                $local=(int)$local;if(!isset($catalog[$local])||(int)$catalog[$local]['is_active']!==1||hmms_excluded_market((string)$catalog[$local]['country_name'])||hmms_opaque((string)$catalog[$local]['name']))continue;
                foreach($ids as $id)$native[(string)$id]=true;$targets[]=['tv_hotel_id'=>$local,'anex_hotel_ids'=>$ids];
            }
            if(!$targets)continue;
            usort($targets,fn($a,$b)=>$a['tv_hotel_id']<=>$b['tv_hotel_id']);
            $nativeIds=array_values(array_map('intval',array_keys($native)));sort($nativeIds,SORT_NUMERIC);
            $c['targets']=$targets;$c['target_hotel_count']=count($targets);$c['native_anex_id_count']=count($nativeIds);$c['native_anex_ids']=$nativeIds;
            $contextRows[]=$c;
        }
        usort($contextRows,fn($a,$b)=>($b['target_hotel_count']<=>$a['target_hotel_count'])?:($b['observation_rows']<=>$a['observation_rows'])?:strcmp($a['departure_date'],$b['departure_date']));
        $totalContextGroups=count($contextRows);$contextRows=array_slice($contextRows,0,HMMS_CONTEXT_OUTPUT_LIMIT);

        $db->commit();
        $out+= [
            'state'=>'completed_read_only','read_at_utc'=>gmdate('c'),'today_moscow'=>$today,
            'coverage_global'=>[
                'tv_anex_unique_local'=>count($anexByLocal),'tv_samo_unique_local'=>count($samoByLocal),
                'tv_anex_samo_triple'=>count($tripleLocals),'tv_anex_missing_samo'=>count($missingIds),
                'tv_samo_missing_anex'=>count($samoByLocal)-count($tripleLocals),
            ],
            'missing_samo_global'=>count($missingIds),'physical_active_nonexcluded_frontier'=>count($frontier),
            'excluded_from_executable_frontier'=>$excluded,'route_counts'=>$routeCounts,'by_country'=>$byCountry,
            'user_seen_frontier_count'=>count(array_filter($frontier,fn($x)=>(int)$x['user_search']['observation_rows']>0)),
            'query_ready_future_anex_context_count'=>count(array_filter($frontier,fn($x)=>$x['route']==='query_ready_user_seen_anex')),
            'saved_direct_bridge_review_count'=>count(array_filter($frontier,fn($x)=>$x['route']==='saved_direct_bridge_review')),
            'context_group_count'=>$totalContextGroups,'top_future_anex_context_groups'=>$contextRows,'frontier'=>$frontier,
        ];
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        $msg=$e->getMessage();$out['reason']=preg_match('/^[A-Za-z0-9_:.-]+$/D',$msg)?$msg:'sanitized_error';
    }
    $shaResult=hmms_write_once($dir.'/result.json',$out);
    $receipt=['operation_id'=>HMMS_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$shaResult,
        'readback_verified'=>hash('sha256',(string)file_get_contents($dir.'/result.json'))===$shaResult,
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0,'no_replay'=>true];
    hmms_write_once($dir.'/receipt.json',$receipt);
    echo json_encode(['state'=>$out['state'],'coverage_global'=>$out['coverage_global']??null,'physical_frontier'=>$out['physical_active_nonexcluded_frontier']??null,'route_counts'=>$out['route_counts']??null,'context_groups'=>$out['context_group_count']??null,'result_sha256'=>$shaResult],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    exit($out['state']==='completed_read_only'?0:2);
}

if(($argv[1]??'')==='--self-test'){
    hmms_req(hmms_opaque('Fortuna Antalya'),'opaque');
    hmms_req(!hmms_opaque('Fortune Hotel'),'not_false_opaque');
    hmms_req(hmms_excluded_market('Россия')&&hmms_excluded_market('Abkhazia'),'market');
    hmms_req(!hmms_excluded_market('Turkey'),'market_other');
    $b=[];hmms_bridge_ids(['provider_bridges'=>[['andromeda_hotel_id'=>'177152'],['andromedaHotelId'=>2000060795]]],$b);
    hmms_req(array_map('strval',array_keys($b))===['177152','2000060795'],'bridge_ids');
    hmms_req(hmms_positive_id('-1')===null&&hmms_positive_id('4158')==='4158','positive_id');
    echo "hotel-match-tv-anex-missing-samo-frontier-v2: PASS\n";exit(0);
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)hmms_main();
