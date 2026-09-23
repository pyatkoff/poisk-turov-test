<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

function mtvr_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function mtvr_read(string $p):array{
    mtvr_need(is_file($p)&&!is_link($p),'file_missing_'.basename($p));
    $x=json_decode((string)file_get_contents($p),true,128,JSON_THROW_ON_ERROR);
    mtvr_need(is_array($x),'json_shape');return $x;
}
function mtvr_children():array{
    $home=rtrim(trim((string)getenv('HOME')),'/');mtvr_need($home!=='','home');
    $base=$home.'/.anytoour-match/operations';
    return [
        $base.'/hotel-match-live942-tv-anex-refresh-1971-20260923-o0-n350-v2',
        $base.'/hotel-match-live942-tv-anex-refresh-1971-20260923-o350-n350-v2',
        $base.'/hotel-match-live942-tv-anex-refresh-1971-20260923-o700-n242-v2',
    ];
}
function mtvr_pair_exclusions(PDO $db):array{
    try{
        $q=$db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions');
        mtvr_need($q!==false,'exclusion_query');
        $out=[];foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $a=trim((string)($r['anex_hotel_id']??''));$t=(int)($r['catalog_hotel_id']??0);
            if(preg_match('/^[1-9][0-9]{0,7}$/D',$a)&&$t>0)$out[$a.'|'.$t]=true;
        }return $out;
    }catch(PDOException $e){
        $info=$e->errorInfo??[];$missing=($info[0]??null)==='42S02'&&(int)($info[1]??0)===1146;
        $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'&&(int)($info[1]??0)===1;
        if(!$missing&&!$sqlite)throw $e;return [];
    }
}
function mtvr_collect():array{
    $byNative=[];$byTv=[];$childSummaries=[];$rawPairs=0;
    foreach(mtvr_children() as $dir){
        $resultPath=$dir.'/result.json';$receiptPath=$dir.'/receipt.json';
        $r=mtvr_read($resultPath);$q=mtvr_read($receiptPath);
        $sha=hash_file('sha256',$resultPath);
        mtvr_need(($q['result_sha256']??'')===$sha,'receipt_hash');
        mtvr_need(($r['state']??'')==='completed_read_only','child_state');
        mtvr_need((int)($r['frontier_count']??0)===942,'frontier');
        mtvr_need((int)($r['database_writes']??-1)===0&&(int)($r['mapping_writes']??-1)===0,'child_writes');
        $strict=0;
        foreach(($r['edges']??[]) as $e){
            if(!is_array($e)||($e['state']??'')!=='detail_identity_verified'||($e['link_state']??'')!=='captured_single_native')continue;
            $tv=(int)($e['tv_hotel_id']??0);$ids=$e['positive_native_candidates']??[];
            if($tv<1||!is_array($ids)||count($ids)!==1)continue;
            $native=(int)$ids[0];if($native<1)continue;
            $byNative[$native][$tv]=true;$byTv[$tv][$native]=true;$strict++;$rawPairs++;
        }
        $childSummaries[]=[
            'operation'=>(string)($r['operation']??basename($dir)),
            'searched_hotels'=>(int)($r['searched_hotels']??0),
            'provider_calls'=>(int)($r['provider_calls']??0),
            'strict_single_native_rows'=>$strict,
            'result_sha256'=>$sha,
        ];
    }
    $pairs=[];$globalNativeDup=0;$globalTvDup=0;
    foreach($byNative as $native=>$targets)if(count($targets)!==1)$globalNativeDup++;
    foreach($byTv as $tv=>$natives)if(count($natives)!==1)$globalTvDup++;
    foreach($byNative as $native=>$targets){
        if(count($targets)!==1)continue;$tv=(int)array_key_first($targets);
        if(count($byTv[$tv]??[])!==1)continue;
        $pairs[]=['anex_hotel_id'=>(int)$native,'catalog_hotel_id'=>$tv];
    }
    usort($pairs,static fn($a,$b)=>[$a['catalog_hotel_id'],$a['anex_hotel_id']]<=>[$b['catalog_hotel_id'],$b['anex_hotel_id']]);
    return [
        'child_summaries'=>$childSummaries,'raw_single_rows'=>$rawPairs,
        'native_distinct'=>count($byNative),'tv_distinct'=>count($byTv),
        'global_native_duplicate_keys'=>$globalNativeDup,'global_tv_duplicate_keys'=>$globalTvDup,
        'globally_unique_pairs'=>$pairs,
    ];
}
function mtvr_reconcile(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $col=mtvr_collect();
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);
        $decisions=[];
        foreach($db->query('SELECT anex_hotel_id,decision_status,catalog_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $a=trim((string)($r['anex_hotel_id']??''));if(preg_match('/^[1-9][0-9]{0,7}$/D',$a))$decisions[(int)$a]=$r;
        }
        $excluded=mtvr_pair_exclusions($db);
        $active=[];foreach($db->query('SELECT id,is_active FROM catalog_hotels')->fetchAll(PDO::FETCH_ASSOC)?:[] as $r)$active[(int)$r['id']]=(int)$r['is_active']===1;
        $counts=[];$rows=[];
        foreach($col['globally_unique_pairs'] as $p){
            $a=(int)$p['anex_hotel_id'];$tv=(int)$p['catalog_hotel_id'];$state='writer_ready';
            $effectiveSource=$coverage['by_native'][$a]??null;
            $targetSet=$coverage['by_local'][$tv]??[];
            $decision=$decisions[$a]??null;
            if(!($active[$tv]??false))$state='target_inactive';
            elseif(isset($excluded[$a.'|'.$tv]))$state='pair_excluded';
            elseif(is_array($decision)){
                $status=(string)($decision['decision_status']??'');$dt=(int)($decision['catalog_hotel_id']??0);
                if($status==='accepted'&&$dt===$tv)$state='resolved_same_manual';
                else $state='manual_decision_protected';
            }elseif($effectiveSource!==null&&$effectiveSource===$tv)$state='resolved_same';
            elseif($effectiveSource!==null&&$effectiveSource!==$tv)$state='source_occupied';
            elseif($targetSet!==[])$state='target_occupied';
            $counts[$state]=($counts[$state]??0)+1;
            $rows[]=[
                'anex_hotel_id'=>$a,'catalog_hotel_id'=>$tv,'state'=>$state,
                'effective_source_target'=>$effectiveSource,
                'effective_target_native_count'=>is_array($targetSet)?count($targetSet):0,
                'safe_to_write_now'=>$state==='writer_ready',
            ];
        }
        ksort($counts);$db->rollBack();
        return [
            'state'=>'completed_read_only','generated_at_utc'=>gmdate('c'),
            'raw_single_rows'=>$col['raw_single_rows'],
            'native_distinct'=>$col['native_distinct'],'tv_distinct'=>$col['tv_distinct'],
            'global_native_duplicate_keys'=>$col['global_native_duplicate_keys'],
            'global_tv_duplicate_keys'=>$col['global_tv_duplicate_keys'],
            'globally_unique_pair_count'=>count($col['globally_unique_pairs']),
            'classification_counts'=>$counts,
            'writer_ready_count'=>(int)($counts['writer_ready']??0),
            'rows'=>$rows,'child_summaries'=>$col['child_summaries'],
            'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')==='--self-test'){
        mtvr_need(hash('sha256','x')==='2d711642b726b04401627ca9fbac32f5c8530fb1903cc4db02258717921a4881','hash');
        echo "MATCH_LIVE942_TV_RECONCILE_V1_SELFTEST_OK\n";exit;
    }
    mtvr_need(($argv[1]??'')==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');mtvr_need(is_dir($root),'root');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    echo json_encode(mtvr_reconcile(v2_data_db()),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}
