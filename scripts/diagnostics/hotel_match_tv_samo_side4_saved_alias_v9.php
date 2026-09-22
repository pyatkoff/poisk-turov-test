<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_tv_samo_side4_saved_alias_current_v7.php';
const HMA9_OP='hotel-match-tv-samo-side4-saved-alias-1971-20260922-v9';
function hma9_execute(string $opDir): array {
    $reservation=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==HMA9_OP||($reservation['state']??null)!=='reserved_read_only_saved_alias')throw new RuntimeException('reservation');
    $operations=dirname($opDir);
    try{$saved=hmc6_samo_rows($operations.'/'.HMA7_SOURCE_OP);}catch(Throwable){throw new RuntimeException('saved_samo_reconstruction');}
    try{$checkpoint=hmc3_previous_checkpoint($operations.'/'.HMC_PREVIOUS_OP);}catch(Throwable){throw new RuntimeException('saved_tv_reconstruction');}
    $common=['anex'=>['tv'=>['id'=>13,'name'=>'ANEX']], 'biblio'=>['tv'=>['id'=>18,'name'=>'Библио-Глобус']], 'funsun'=>['tv'=>['id'=>25,'name'=>'FUN&SUN']], 'intourist'=>['tv'=>['id'=>43,'name'=>'Интурист']]];
    try{$tv=hmc_tv_offer_rows($checkpoint['rows'],HMC_DATE_FROM,$common);}catch(Throwable){throw new RuntimeException('tv_offer_projection');}
    try{$resolved=hma7_resolve($tv,$saved['rows']);}catch(Throwable){throw new RuntimeException('alias_resolver');}
    return ['operation'=>HMA9_OP,'state'=>'completed_read_only_saved_alias','source_operation'=>HMA7_SOURCE_OP,
        'scope'=>['resort'=>'Side','date_from'=>HMC_DATE_FROM,'date_to'=>HMC_DATE_TO,'nights'=>7,'adults'=>2,'children'=>0,
            'retained_tv_hotels'=>140,'retained_tv_offers'=>1809,'retained_samo_offers'=>count($saved['rows'])],
        'acquisition_policy'=>'COMMON3_ANEX_FUNSUN_INTOURIST_FOR_NEW_MATCH','biblio_history_preserved'=>true,
        'biblio_fuel_policy'=>'owner_policy_zero_included_no_fuel_sample','resolver'=>$resolved,
        'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_writes'=>0,
        'search_visibility_verified'=>false,'predecessor_failures_no_replay'=>['v7'=>35773753954,'v8'=>35774407017]];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');$opDir=(string)getenv('MATCH_OPERATION_DIR');
    if(!is_dir($opDir))throw new RuntimeException('runtime_paths');
    try{$result=hma9_execute($opDir);$sha=hmc_write($opDir.'/result.json',$result);hmc_write($opDir.'/receipt.json',['operation'=>HMA9_OP,'state'=>$result['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$opDir.'/result.json')===$sha,'provider_accessed'=>false,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hmc_json(['state'=>$result['state'],'strong'=>$result['resolver']['strong_count'],'review'=>$result['resolver']['review_count']])."\n";}
    catch(Throwable $e){$reason=preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';$fail=['operation'=>HMA9_OP,'state'=>'failed_read_only_saved_alias','reason'=>$reason,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0];$sha=hmc_write($opDir.'/result.json',$fail);hmc_write($opDir.'/receipt.json',['operation'=>HMA9_OP,'state'=>$fail['state'],'result_sha256'=>$sha,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$reason."\n");exit(2);}
}
