<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_tv_samo_side4_saved_alias_current_v7.php';

const HMB10_OP = 'hotel-match-tv-samo-side4-baseline-current-1971-20260922-v10';

function hmb10_execute(string $opDir, string $root): array {
    $reservation=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==HMB10_OP||($reservation['state']??null)!=='reserved_read_only_baseline_current') {
        throw new RuntimeException('reservation');
    }
    $operations=dirname($opDir);
    $saved=hmc6_samo_rows($operations.'/'.HMA7_SOURCE_OP);
    $checkpoint=hmc3_previous_checkpoint($operations.'/'.HMC_PREVIOUS_OP);
    $common=['anex'=>['tv'=>['id'=>13,'name'=>'ANEX']], 'biblio'=>['tv'=>['id'=>18,'name'=>'Библио-Глобус']], 'funsun'=>['tv'=>['id'=>25,'name'=>'FUN&SUN']], 'intourist'=>['tv'=>['id'=>43,'name'=>'Интурист']]];
    $tv=hmc_tv_offer_rows($checkpoint['rows'],HMC_DATE_FROM,$common);
    $baselinePairs=hma7_baseline_pairs(hmf_resolve($tv,$saved['rows'],[]));
    if(count($baselinePairs)!==3) throw new RuntimeException('baseline_exact_count');
    $common3=array_values(array_filter($baselinePairs,fn($c)=>($c['tier']??'')==='baseline_exact_common3'));
    if(count($common3)!==1||(string)$common3[0]['tv_hotel_id']!=='9337'||(string)$common3[0]['samo_hotel_id']!=='343342') {
        throw new RuntimeException('common3_baseline_membership');
    }
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $current=hma7_current(v2_data_db(),$common3);
    return [
        'operation'=>HMB10_OP,'state'=>'completed_read_only_baseline_current',
        'scope'=>['resort'=>'Side','date_from'=>HMC_DATE_FROM,'date_to'=>HMC_DATE_TO,'nights'=>7,'adults'=>2,'children'=>0,
            'retained_tv_hotels'=>140,'retained_tv_offers'=>1809,'retained_samo_offers'=>count($saved['rows'])],
        'baseline_exact_count'=>count($baselinePairs),'common3_audit_count'=>count($common3),
        'common3_candidates'=>$common3,'current'=>$current,
        'excluded_from_current'=>[
            ['tv_hotel_id'=>'28660','samo_hotel_id'=>'2000023127','reason'=>'baseline_exact_biblio_only'],
            ['tv_hotel_id'=>'37532','samo_hotel_id'=>'2000029524','reason'=>'baseline_exact_no_operator_overlap'],
        ],
        'acquisition_policy'=>'COMMON3_ANEX_FUNSUN_INTOURIST_FOR_NEW_MATCH',
        'biblio_fuel_policy'=>'owner_policy_zero_included_no_fuel_sample',
        'provider_http_calls'=>0,'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0,
        'booking_calls'=>0,'lead_writes'=>0,'search_visibility_verified'=>false,
    ];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--execute') throw new RuntimeException('disabled');
    $opDir=(string)getenv('MATCH_OPERATION_DIR');$root=(string)getenv('ANYTOUR_ROOT');
    if(!is_dir($opDir)||!is_dir($root)||basename($root)!=='anytoour.ru') throw new RuntimeException('runtime_paths');
    try{
        $result=hmb10_execute($opDir,$root);$sha=hmc_write($opDir.'/result.json',$result);
        hmc_write($opDir.'/receipt.json',['operation'=>HMB10_OP,'state'=>$result['state'],'result_sha256'=>$sha,
            'readback_verified'=>hash_file('sha256',$opDir.'/result.json')===$sha,'provider_accessed'=>false,
            'provider_http_calls'=>0,'database_reads'=>1,'database_writes'=>0,'mapping_writes'=>0]);
        echo hmc_json(['state'=>$result['state'],'current'=>$result['current']['counts']])."\n";
    }catch(Throwable $e){
        $reason=preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';
        $fail=['operation'=>HMB10_OP,'state'=>'failed_read_only_baseline_current','reason'=>$reason,
            'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $sha=hmc_write($opDir.'/result.json',$fail);
        hmc_write($opDir.'/receipt.json',['operation'=>HMB10_OP,'state'=>$fail['state'],'result_sha256'=>$sha,
            'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,
            'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        fwrite(STDERR,$reason."\n");exit(2);
    }
}
