<?php
declare(strict_types=1);

const HMTA_OP='hotel-match-tv-samo-missing-anex-frontier-1971-20260920-v1';
const HMTA_POLICY='owner_exact_and_strong_20260908';
const HMTA_MAX_ROWS=500000;

function hmta_req(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmta_json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function hmta_write_once(string $path,array $v):string{
    $raw=hmta_json($v);$f=@fopen($path,'x+b');hmta_req(is_resource($f),'exclusive_output');
    try{hmta_req(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))hmta_req(fsync($f),'output_sync');rewind($f);hmta_req(stream_get_contents($f)===$raw,'output_readback');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmta_read_json(string $path):array{
    hmta_req(!is_link($path)&&realpath($path)===$path&&is_file($path),'input_path');
    $raw=file_get_contents($path);hmta_req(is_string($raw)&&strlen($raw)<=1048576,'input_read');
    $v=json_decode($raw,true,64,JSON_THROW_ON_ERROR);hmta_req(is_array($v),'input_shape');return $v;
}
function hmta_q(PDO $db,string $sql,array $args=[]):array{
    $q=$db->prepare($sql);$q->execute(array_values($args));$rows=$q->fetchAll(PDO::FETCH_ASSOC)?:[];
    hmta_req(count($rows)<=HMTA_MAX_ROWS,'row_budget');return $rows;
}
function hmta_norm(string $v):string{
    $v=trim($v);if(function_exists('mb_strtolower'))$v=mb_strtolower($v,'UTF-8');else$v=strtolower($v);
    $v=strtr($v,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);$v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function hmta_opaque(string $name):bool{return preg_match('/(?:^|[^\p{L}])(?:FORTUNA|ROULETTE|ФОРТУНА|РУЛЕТКА)(?:$|[^\p{L}])/iu',$name)===1;}
function hmta_excluded_market(string $country):bool{return in_array(hmta_norm($country),['россия','russia','russian federation','абхазия','abkhazia'],true);}
function hmta_chunks(array $ids,int $n=500):array{$ids=array_values(array_unique(array_map('intval',$ids)));sort($ids,SORT_NUMERIC);return array_chunk($ids,$n);}
function hmta_by_ids(PDO $db,string $prefix,array $ids):array{
    $out=[];foreach(hmta_chunks($ids) as $chunk){if(!$chunk)continue;$ph=implode(',',array_fill(0,count($chunk),'?'));foreach(hmta_q($db,$prefix."($ph)",$chunk) as $r)$out[]=$r;}return$out;
}
function hmta_main():void{
    hmta_req(PHP_SAPI==='cli'&&(string)getenv('MATCH_OPERATION_ID')===HMTA_OP,'operation_guard');
    $sha=(string)getenv('MATCH_SOURCE_SHA');hmta_req(preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'source_sha');
    $dir=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations/'.HMTA_OP;
    $reservation=hmta_read_json($dir.'/reservation.json');
    hmta_req(($reservation['operation_id']??'')===HMTA_OP&&($reservation['source_sha']??'')===$sha&&($reservation['state']??'')==='reserved_before_db_access','reservation_guard');
    $root=realpath(getcwd());hmta_req(is_string($root)&&basename($root)==='anytoour.ru','root_guard');
    $dbPath=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');hmta_req(is_file($dbPath),'db_runtime');
    require_once $dbPath;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $out=['operation_id'=>HMTA_OP,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'andromeda_calls'=>0,'direct_anex_calls'=>0];
    try{
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        foreach(['catalog_hotels','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities','tour_price_observations'] as $t){
            $r=hmta_q($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);
            hmta_req(count($r)===1&&strtoupper((string)$r[0]['ENGINE'])==='INNODB','table_contract_'.$t);
        }

        $anexByLocal=[];
        $automatic=hmta_q($db,"SELECT m.anex_hotel_id,m.catalog_hotel_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy=? AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)",[HMTA_POLICY]);
        foreach($automatic as $r){$local=(int)$r['catalog_hotel_id'];$aid=(int)$r['anex_hotel_id'];if($local>0&&$aid>0)$anexByLocal[$local][$aid]=true;}
        $manual=hmta_q($db,"SELECT d.anex_hotel_id,d.catalog_hotel_id FROM anex_hotel_decisions d WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)");
        foreach($manual as $r){$local=(int)$r['catalog_hotel_id'];$aid=(int)$r['anex_hotel_id'];if($local>0&&$aid>0)$anexByLocal[$local][$aid]=true;}

        $samoByLocal=[];$samoExternalByLocal=[];
        foreach(hmta_q($db,"SELECT external_hotel_id,local_hotel_id,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL") as $r){
            $local=(int)$r['local_hotel_id'];if($local<=0)continue;$eid=(string)$r['external_hotel_id'];$samoByLocal[$local]=true;$samoExternalByLocal[$local][$eid]=(string)$r['evidence_sha256'];
        }
        $triple=array_intersect_key($anexByLocal,$samoByLocal);
        $missingIds=array_values(array_map('intval',array_keys(array_diff_key($samoByLocal,$anexByLocal))));sort($missingIds,SORT_NUMERIC);

        $catalog=[];$prefix="SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ";
        foreach(hmta_by_ids($db,$prefix,$missingIds) as $r)$catalog[(int)$r['id']]=$r;

        $op5ByLocal=[];
        foreach(hmta_q($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='operator_5' AND local_hotel_id IS NOT NULL") as $r){
            $local=(int)$r['local_hotel_id'];if($local<=0||!isset($samoByLocal[$local]))continue;
            $op5ByLocal[$local][]=['native_anex_id'=>(string)$r['external_hotel_id'],'decision_status'=>(string)$r['decision_status'],'evidence_sha256'=>(string)$r['evidence_sha256']];
        }

        $userStats=[];
        foreach(hmta_chunks($missingIds) as $chunk){if(!$chunk)continue;$ph=implode(',',array_fill(0,count($chunk),'?'));
            foreach(hmta_q($db,"SELECT hotel_id,COUNT(*) observation_rows,SUM(CASE WHEN operator_id=13 THEN 1 ELSE 0 END) anex_observation_rows,MAX(observed_at) last_seen_at FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($ph) GROUP BY hotel_id",$chunk) as $r){
                $userStats[(int)$r['hotel_id']]=['observation_rows'=>(int)$r['observation_rows'],'anex_observation_rows'=>(int)$r['anex_observation_rows'],'last_seen_at'=>(string)$r['last_seen_at']];
            }
        }
        $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
        $latestFuture=[];
        foreach(hmta_chunks($missingIds) as $chunk){if(!$chunk)continue;$ph=implode(',',array_fill(0,count($chunk),'?'));$args=array_merge([$today],$chunk);
            foreach(hmta_q($db,"SELECT hotel_id,country_id,region_id,subregion_id,departure_id,departure_date,nights,adults,children_count,child_ages_signature,search_id,tour_id,observed_at FROM tour_price_observations WHERE source='user_search' AND operator_id=13 AND departure_date>=? AND hotel_id IN ($ph) ORDER BY observed_at DESC,hotel_id",$args) as $r){
                $hid=(int)$r['hotel_id'];if(isset($latestFuture[$hid]))continue;
                $latestFuture[$hid]=['departure_id'=>(int)$r['departure_id'],'country_id'=>(int)$r['country_id'],'region_id'=>$r['region_id']===null?null:(int)$r['region_id'],'subregion_id'=>$r['subregion_id']===null?null:(int)$r['subregion_id'],'departure_date'=>(string)$r['departure_date'],'nights'=>(int)$r['nights'],'adults'=>(int)$r['adults'],'children_count'=>(int)$r['children_count'],'child_ages_signature'=>(string)$r['child_ages_signature'],'search_id'=>(int)$r['search_id'],'tour_id'=>$r['tour_id']===null?null:(string)$r['tour_id'],'observed_at'=>(string)$r['observed_at']];
            }
        }

        $frontier=[];$excluded=['missing_catalog'=>0,'inactive'=>0,'market'=>0,'opaque'=>0];$routes=[];$countries=[];$detailReady=0;$savedOp5=0;$userSeen=0;
        foreach($missingIds as $local){
            $h=$catalog[$local]??null;if(!$h){$excluded['missing_catalog']++;continue;}
            if((int)$h['is_active']!==1){$excluded['inactive']++;continue;}
            if(hmta_excluded_market((string)$h['country_name'])){$excluded['market']++;continue;}
            if(hmta_opaque((string)$h['name'])){$excluded['opaque']++;continue;}
            $stats=$userStats[$local]??['observation_rows'=>0,'anex_observation_rows'=>0,'last_seen_at'=>null];if($stats['observation_rows']>0)$userSeen++;
            $op5=$op5ByLocal[$local]??[];if($op5)$savedOp5++;
            $future=$latestFuture[$local]??null;if($future&&$future['tour_id']!==null&&$future['tour_id']!=='')$detailReady++;
            if($op5)$route='saved_operator5_review';
            elseif($future&&$future['tour_id']!==null&&$future['tour_id']!=='')$route='saved_tour_detail_ready';
            elseif($future)$route='future_anex_context_no_tour';
            elseif($stats['anex_observation_rows']>0)$route='user_seen_anex_history_no_future';
            elseif($stats['observation_rows']>0)$route='user_seen_needs_anex_evidence';
            else$route='catalog_only';
            $routes[$route]=($routes[$route]??0)+1;$countries[(string)$h['country_name']]=($countries[(string)$h['country_name']]??0)+1;
            $frontier[]=['local_hotel_id'=>$local,'name'=>(string)$h['name'],'country'=>(string)$h['country_name'],'region'=>(string)$h['region_name'],'subregion'=>(string)$h['subregion_name'],'category'=>$h['category'],'samo_external_ids'=>array_values(array_keys($samoExternalByLocal[$local]??[])),'user_search'=>$stats,'latest_future_anex_context'=>$future,'saved_operator5_evidence'=>$op5,'route'=>$route,'safe_to_write_now'=>false];
        }
        usort($frontier,static function(array $a,array $b):int{
            $p=['saved_operator5_review'=>0,'saved_tour_detail_ready'=>1,'future_anex_context_no_tour'=>2,'user_seen_anex_history_no_future'=>3,'user_seen_needs_anex_evidence'=>4,'catalog_only'=>5];
            $x=($p[$a['route']]??9)<=>($p[$b['route']]??9);if($x!==0)return$x;
            $x=(int)($b['user_search']['observation_rows']??0)<=>(int)($a['user_search']['observation_rows']??0);if($x!==0)return$x;return$a['local_hotel_id']<=>$b['local_hotel_id'];
        });ksort($routes);ksort($countries,SORT_NATURAL|SORT_FLAG_CASE);
        $coverage=['tv_anex_unique_local'=>count($anexByLocal),'tv_samo_unique_local'=>count($samoByLocal),'tv_anex_samo_triple'=>count($triple),'tv_anex_missing_samo'=>count($anexByLocal)-count($triple),'tv_samo_missing_anex'=>count($samoByLocal)-count($triple)];
        $db->commit();
        $out+=['state'=>'completed_read_only','read_at_utc'=>gmdate('c'),'today_moscow'=>$today,'coverage_global'=>$coverage,'missing_anex_global'=>count($missingIds),'physical_active_nonexcluded_frontier'=>count($frontier),'excluded_from_executable_frontier'=>$excluded,'route_counts'=>$routes,'user_seen_frontier_count'=>$userSeen,'saved_operator5_review_count'=>$savedOp5,'saved_tour_detail_ready_count'=>$detailReady,'countries'=>$countries,'frontier'=>$frontier];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$out['reason']='read_failed';$out['error_class']=get_class($e);}
    $resultPath=$dir.'/result.json';$resultSha=hmta_write_once($resultPath,$out);
    $read=json_decode((string)file_get_contents($resultPath),true,64,JSON_THROW_ON_ERROR);hmta_req(is_array($read)&&($read['operation_id']??'')===HMTA_OP,'result_readback');
    $receipt=['operation_id'=>HMTA_OP,'source_sha'=>$sha,'result_sha256'=>$resultSha,'state'=>(string)$read['state'],'readback_verified'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'no_replay'=>true];
    hmta_write_once($dir.'/receipt.json',$receipt);
    if(($out['state']??'')!=='completed_read_only')exit(2);
}
function hmta_self_test():void{
    hmta_req(hmta_norm('  Hôtel--Ёлка ')==='hôtel елка','norm');
    hmta_req(hmta_excluded_market('Россия')&&hmta_excluded_market('Abkhazia')&&!hmta_excluded_market('Turkey'),'market');
    hmta_req(hmta_opaque('Fortuna Antalya')&&hmta_opaque('Рулетка 5*')&&!hmta_opaque('Fortune Resort'),'opaque');
    echo "HMTA_SELF_TEST_OK\n";
}
if(($argv[1]??'')==='--self-test'){hmta_self_test();exit(0);}hmta_main();
