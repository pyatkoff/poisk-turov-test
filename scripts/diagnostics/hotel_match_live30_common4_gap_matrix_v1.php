<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HMC4_OP='hotel-match-live30-common4-gap-matrix-1971-20260923-v1';

function hmc4_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmc4_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmc4_save(string $path,array $value):string{
    $raw=hmc4_json($value)."\n";$f=@fopen($path,'x+b');hmc4_need($f!==false,'exclusive_create');
    try{hmc4_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmc4_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hmc4_excluded(string $country):bool{
    return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;
}
function hmc4_pos(mixed $v):?string{
    if(is_int($v))$v=(string)$v;if(!is_string($v))return null;$v=trim($v);
    return preg_match('/^[1-9][0-9]{0,20}$/D',$v)===1?$v:null;
}
function hmc4_meta(int $operatorId):?array{
    return match($operatorId){
        13=>['key'=>'anex','namespace'=>'anex','accepted_namespace'=>null],
        18=>['key'=>'biblio','namespace'=>'bgoperator','accepted_namespace'=>'bgoperator'],
        25=>['key'=>'funsun','namespace'=>'operator_315','accepted_namespace'=>'operator_315'],
        43=>['key'=>'intourist','namespace'=>'operator_342','accepted_namespace'=>'operator_342'],
        default=>null,
    };
}
function hmc4_hotel_key(string $key):bool{
    return in_array(strtolower($key),['hotel','hotels','hotelid','hotel_id','hotelcode','hotel_code','hotellist','hotelkey','hotel_key'],true);
}
function hmc4_anex_host(string $host):bool{
    $host=strtolower(trim($host));
    return $host==='anextour.ru'||str_ends_with($host,'.anextour.ru');
}
function hmc4_parse_link(array $row):array{
    $op=(int)($row['operator_id']??0);$meta=hmc4_meta($op);
    if($meta===null)return ['state'=>'unsupported_operator','namespace'=>null,'candidates'=>[]];
    $ns=$meta['namespace'];$link=trim((string)($row['operator_link']??''));
    $metaHost=strtolower(trim((string)($row['operator_link_host']??'')));
    $metaQuery=trim((string)($row['operator_link_query']??''));
    if($link==='')return ['state'=>'missing_link','namespace'=>$ns,'candidates'=>[],'host'=>$metaHost?:null,'query_keys'=>[]];
    $u=parse_url($link);
    if(!is_array($u)||strtolower((string)($u['scheme']??''))!=='https'||empty($u['host'])||isset($u['user'])||isset($u['pass'])||isset($u['fragment']))
        return ['state'=>'invalid_link','namespace'=>$ns,'candidates'=>[]];
    $host=strtolower((string)$u['host']);$query=(string)($u['query']??'');
    if($metaHost!==''&&$metaHost!==$host)return ['state'=>'metadata_conflict','namespace'=>$ns,'candidates'=>[]];
    if($metaQuery!==''&&$query!==''&&$metaQuery!==$query)return ['state'=>'metadata_conflict','namespace'=>$ns,'candidates'=>[]];
    if($query==='')$query=$metaQuery;
    if($op===13&&!hmc4_anex_host($host))return ['state'=>'unexpected_anex_host','namespace'=>$ns,'candidates'=>[],'host'=>$host,'query_keys'=>[]];
    if($op===18&&!in_array($host,['bgoperator.ru','www.bgoperator.ru'],true))
        return ['state'=>'unexpected_bg_host','namespace'=>$ns,'candidates'=>[],'host'=>$host,'query_keys'=>[]];

    $keys=[];$candidates=[];$numeric=[];$ambiguousInput=0;
    foreach(explode('&',$query) as $part){
        if($part==='')continue;[$rk,$rv]=array_pad(explode('=',$part,2),2,'');
        $key=strtolower(urldecode($rk));if($key==='')continue;$keys[$key]=true;
        if(preg_match('/(?:token|jwt|auth|pass|password|secret|session|sid|cookie|signature|api[_-]?key)/i',$key))
            return ['state'=>'secret_bearing_link','namespace'=>$ns,'candidates'=>[],'host'=>$host,'query_keys'=>array_keys($keys)];
        $raw=trim(urldecode($rv));$id=hmc4_pos($raw);if($id===null)continue;$numeric[$id]=true;
        if($op===18){
            if(strcasecmp($key,'f4')===0)$candidates[$id]=true;
        }elseif(hmc4_hotel_key($key)){
            $candidates[$id]=true;$ambiguousInput++;
        }
    }
    $ids=array_keys($candidates);sort($ids,SORT_NATURAL);$qkeys=array_keys($keys);sort($qkeys,SORT_NATURAL);
    if(count($ids)===1)return ['state'=>'single_native_candidate','namespace'=>$ns,'candidates'=>$ids,'host'=>$host,'query_keys'=>$qkeys];
    if(count($ids)>1||$ambiguousInput>1)return ['state'=>'ambiguous_native_candidates','namespace'=>$ns,'candidates'=>$ids,'host'=>$host,'query_keys'=>$qkeys];
    return ['state'=>$numeric?'numeric_nonhotel_only':'no_native_candidate','namespace'=>$ns,'candidates'=>[],'host'=>$host,'query_keys'=>$qkeys];
}
function hmc4_lane(array $accepted,array $saved,array $sourceTargets):array{
    $accepted=array_values(array_unique(array_map('strval',$accepted)));sort($accepted,SORT_NATURAL);
    $exact=array_keys($saved['exact']??[]);sort($exact,SORT_NATURAL);
    $amb=array_keys($saved['ambiguous']??[]);sort($amb,SORT_NATURAL);
    $observations=(int)($saved['observations']??0);
    $state='no_retained_operator_result';$exactEvidence=false;
    if($accepted!==[]){$state='accepted_current';$exactEvidence=true;}
    elseif(count($exact)>1)$state='saved_multiple_exact';
    elseif(count($exact)===1){
        $targets=$sourceTargets[$exact[0]]??[];
        if(count($targets)>1)$state='saved_exact_source_collision';
        else{$state='saved_exact_unaccepted';$exactEvidence=true;}
    }elseif($amb!==[])$state='saved_ambiguous_native';
    elseif($observations>0)$state='observed_no_usable_native';
    return [
        'state'=>$state,
        'exact_evidence'=>$exactEvidence,
        'accepted_external_ids'=>$accepted,
        'saved_exact_external_ids'=>$exact,
        'saved_ambiguous_external_ids'=>$amb,
        'observation_count'=>$observations,
        'last_seen_at'=>(string)($saved['last_seen_at']??''),
        'operator_names'=>array_values(array_keys($saved['operator_names']??[])),
        'parse_states'=>$saved['parse_states']??[],
    ];
}
function hmc4_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $facts=[];$active=[];
        foreach($db->query("SELECT id,name,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            if(hmc4_excluded((string)($r['country_name']??'')))continue;$id=(int)$r['id'];if($id<1)continue;$facts[$id]=$r;$active[$id]=true;
        }
        hmc4_need($active!==[],'no_active_hotels');

        $samo=[];$secondary=[];$secondaryBySource=[];
        foreach($db->query("SELECT supplier_namespace,external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY supplier_namespace,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $local=(int)($r['local_hotel_id']??0);$ns=trim((string)($r['supplier_namespace']??''));$ext=trim((string)($r['external_hotel_id']??''));
            if($local<1||$ns===''||$ext===''||!isset($active[$local]))continue;
            if($ns==='andromeda_catalog')$samo[$local][$ext]=true;
            if(in_array($ns,['bgoperator','operator_315','operator_342'],true)){
                $secondary[$local][$ns][$ext]=true;$secondaryBySource[$ns.'|'.$ext][$local]=true;
            }
        }
        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexByLocal=[];
        foreach(($anex['by_local']??[]) as $local=>$set){
            $local=(int)$local;if(!isset($active[$local]))continue;
            foreach($set as $ext=>$flag)if($flag)$anexByLocal[$local][(string)$ext]=true;
        }

        $cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
        $live30=[];
        foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $id=(int)($r['hotel_id']??0);if(!isset($active[$id]))continue;$raw=trim((string)($r['last_seen_at']??''));if($raw==='')continue;
            try{$dt=new DateTimeImmutable($raw,new DateTimeZone('UTC'));}catch(Throwable){continue;}
            if($dt>=$cut)$live30[$id]=true;
        }

        $gap=[];$bucketCounts=['samo_only'=>0,'anex_only'=>0,'neither'=>0];
        foreach($live30 as $id=>$_){
            $s=!empty($samo[$id]);$a=!empty($anexByLocal[$id]);if($s&&$a)continue;
            $bucket=$s?'samo_only':($a?'anex_only':'neither');$gap[$id]=$bucket;$bucketCounts[$bucket]++;
        }

        $saved=[];$sourceTargets=[];$globalParse=[];
        $sql="SELECT id,last_seen_at,observation_count,hotel_id,operator_id,operator_name,operator_link,operator_link_host,operator_link_query
              FROM tour_operator_identity_observations
              WHERE operator_id IN (13,18,25,43)
              ORDER BY hotel_id,operator_id,last_seen_at DESC,id DESC";
        foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $tv=(int)($r['hotel_id']??0);$op=(int)($r['operator_id']??0);$meta=hmc4_meta($op);if($meta===null||!isset($active[$tv]))continue;
            $parsed=hmc4_parse_link($r);$ps=(string)($parsed['state']??'unknown');$globalParse[$op.'|'.$ps]=($globalParse[$op.'|'.$ps]??0)+1;
            if($ps==='single_native_candidate'&&count($parsed['candidates']??[])===1){
                $ext=(string)$parsed['candidates'][0];$sourceTargets[$op][$ext][$tv]=true;
            }
            if(!isset($gap[$tv]))continue;
            $saved[$tv][$op]['observations']=($saved[$tv][$op]['observations']??0)+(int)($r['observation_count']??1);
            $last=trim((string)($r['last_seen_at']??''));if(($saved[$tv][$op]['last_seen_at']??'')<$last)$saved[$tv][$op]['last_seen_at']=$last;
            $name=trim((string)($r['operator_name']??''));if($name!=='')$saved[$tv][$op]['operator_names'][$name]=true;
            $saved[$tv][$op]['parse_states'][$ps]=($saved[$tv][$op]['parse_states'][$ps]??0)+1;
            if($ps==='single_native_candidate'&&count($parsed['candidates']??[])===1)$saved[$tv][$op]['exact'][(string)$parsed['candidates'][0]]=true;
            elseif($ps==='ambiguous_native_candidates')foreach(($parsed['candidates']??[]) as $ext)$saved[$tv][$op]['ambiguous'][(string)$ext]=true;
        }

        $operators=[13=>'anex',18=>'biblio',25=>'funsun',43=>'intourist'];
        $stateCounts=[];$exactCounts=array_fill_keys(array_values($operators),0);$laneDistribution=['0'=>0,'1'=>0,'2'=>0,'3'=>0,'4'=>0];
        $rows=[];$bucketLaneDistribution=[];
        foreach($gap as $tv=>$bucket){
            $lanes=[];$exactLaneCount=0;
            foreach($operators as $op=>$key){
                if($op===13)$accepted=array_keys($anexByLocal[$tv]??[]);
                else{
                    $ns=hmc4_meta($op)['accepted_namespace'];$accepted=array_keys($secondary[$tv][$ns]??[]);
                }
                $sourceByExternal=[];foreach(($sourceTargets[$op]??[]) as $ext=>$targets)$sourceByExternal[(string)$ext]=$targets;
                $lane=hmc4_lane($accepted,$saved[$tv][$op]??[],$sourceByExternal);
                $lanes[$key]=$lane;$stateCounts[$key][$lane['state']]=($stateCounts[$key][$lane['state']]??0)+1;
                if($lane['exact_evidence']){$exactLaneCount++;$exactCounts[$key]++;}
            }
            $laneDistribution[(string)$exactLaneCount]++;$bucketLaneDistribution[$bucket][(string)$exactLaneCount]=($bucketLaneDistribution[$bucket][(string)$exactLaneCount]??0)+1;
            $f=$facts[$tv];
            $rows[]=[
                'tv_hotel_id'=>$tv,'name'=>(string)($f['name']??''),'country'=>(string)($f['country_name']??''),
                'region'=>(string)($f['region_name']??''),'subregion'=>(string)($f['subregion_name']??''),
                'category'=>(string)($f['category']??''),'gap_bucket'=>$bucket,
                'samo_anchor_count'=>count($samo[$tv]??[]),'anex_native_count'=>count($anexByLocal[$tv]??[]),
                'exact_lane_count'=>$exactLaneCount,'all_four_exact'=>$exactLaneCount===4,'lanes'=>$lanes,
            ];
        }
        foreach($stateCounts as &$m)ksort($m);unset($m);ksort($globalParse);
        foreach($bucketLaneDistribution as &$m){for($i=0;$i<=4;$i++)$m[(string)$i]=$m[(string)$i]??0;ksort($m);}unset($m);
        usort($rows,static fn(array $a,array $b):int=>[$a['exact_lane_count'],$a['gap_bucket'],$a['country'],$a['region'],$a['tv_hotel_id']]<=>[$b['exact_lane_count'],$b['gap_bucket'],$b['country'],$b['region'],$b['tv_hotel_id']]);
        $db->rollBack();

        return [
            'operation'=>HMC4_OP,'state'=>'completed_read_only_common4_gap_matrix','generated_at_utc'=>gmdate('c'),
            'definitions'=>[
                'cohort'=>'CURRENT active non-Russia/Abkhazia TV hotels observed in tour_operator_identity_observations within30d, excluding hotels that already have both accepted SAMO/andromeda_catalog and accepted effective direct ANEX',
                'accepted_current'=>'current accepted provider identity; ANEX uses effective direct registry, BG/FUN&SUN/Intourist use accepted provider namespaces',
                'saved_exact_unaccepted'=>'retained Tourvisor operatorLink contains exactly one explicit provider-native hotel id and that native id is source-unique in retained observations; not yet accepted',
                'no_retained_operator_result'=>'no retained returned operator/tour observation for this hotel+operator; this is NOT proof that a targeted search was never attempted or a provider has no tour',
            ],
            'tv_live30_total'=>count($live30),'live30_non_triple_total'=>count($gap),'gap_bucket_counts'=>$bucketCounts,
            'operator_state_counts'=>$stateCounts,'operator_exact_evidence_counts'=>$exactCounts,
            'exact_lane_count_distribution'=>$laneDistribution,'bucket_exact_lane_count_distribution'=>$bucketLaneDistribution,
            'all_four_exact_count'=>$laneDistribution['4'],'global_parse_state_counts'=>$globalParse,'rows'=>$rows,
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,
            'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $a=hmc4_parse_link(['operator_id'=>13,'operator_link'=>'https://online.anextour.ru/search?hotelCode=5844','operator_link_host'=>'online.anextour.ru','operator_link_query'=>'hotelCode=5844']);
        hmc4_need(($a['state']??'')==='single_native_candidate'&&($a['candidates'][0]??'')==='5844','anex_hotelcode');
        $a2=hmc4_parse_link(['operator_id'=>13,'operator_link'=>'https://agent.anextour.ru/search/tour?HOTELLIST=5200','operator_link_host'=>'agent.anextour.ru','operator_link_query'=>'HOTELLIST=5200']);
        hmc4_need(($a2['state']??'')==='single_native_candidate'&&($a2['candidates'][0]??'')==='5200','anex_hotellist');
        $bg=hmc4_parse_link(['operator_id'=>18,'operator_link'=>'https://www.bgoperator.ru/x?F4=12345','operator_link_host'=>'www.bgoperator.ru','operator_link_query'=>'F4=12345']);
        hmc4_need(($bg['state']??'')==='single_native_candidate'&&($bg['candidates'][0]??'')==='12345','bg');
        $bad=hmc4_parse_link(['operator_id'=>25,'operator_link'=>'https://example.test/x?session=1&hotelId=2','operator_link_host'=>'example.test','operator_link_query'=>'session=1&hotelId=2']);
        hmc4_need(($bad['state']??'')==='secret_bearing_link','secret');
        $lane=hmc4_lane([],['exact'=>['1'=>true],'observations'=>2],['1'=>[42=>true]]);
        hmc4_need($lane['state']==='saved_exact_unaccepted'&&$lane['exact_evidence']===true,'lane_exact');
        $lane2=hmc4_lane([],['observations'=>1],[]);
        hmc4_need($lane2['state']==='observed_no_usable_native','lane_no_native');
        echo "MATCH_LIVE30_COMMON4_GAP_MATRIX_V1_SELFTEST_OK\n";exit;
    }
    hmc4_need($mode==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$source=(string)getenv('MATCH_SOURCE_SHA');
    hmc4_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HMC4_OP&&preg_match('/^[a-f0-9]{40}$/D',$source)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hmc4_need(($reservation['operation']??'')===HMC4_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $result=hmc4_execute(v2_data_db());$result['source_sha']=$source;$hash=hmc4_save($dir.'/result.json',$result);
        hmc4_save($dir.'/receipt.json',['operation'=>HMC4_OP,'state'=>$result['state'],'result_sha256'=>$hash,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
        echo hmc4_json(['state'=>$result['state'],'tv_live30_total'=>$result['tv_live30_total'],'gap_total'=>$result['live30_non_triple_total'],'gap_buckets'=>$result['gap_bucket_counts'],'exact_counts'=>$result['operator_exact_evidence_counts'],'lane_distribution'=>$result['exact_lane_count_distribution']])."\n";
    }catch(Throwable $e){
        $f=['operation'=>HMC4_OP,'state'=>'failed_read_only_common4_gap_matrix','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $hash=hmc4_save($dir.'/result.json',$f);hmc4_save($dir.'/receipt.json',['operation'=>HMC4_OP,'state'=>$f['state'],'result_sha256'=>$hash,'readback_verified'=>true,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
        fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
