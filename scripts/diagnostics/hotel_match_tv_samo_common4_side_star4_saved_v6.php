<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_tv_samo_common4_side_star4_samo_v5.php';

const HMC6_OP='hotel-match-tv-samo-common4-side-star4-saved-1971-20260922-v6';
const HMC6_SOURCE_OP='hotel-match-tv-samo-common4-side-star4-samo-1971-20260922-v5';

function hmc6_samo_rows(string $sourceDir): array {
    $result=json_decode((string)file_get_contents($sourceDir.'/result.json'),true,32,JSON_THROW_ON_ERROR);
    $receipt=json_decode((string)file_get_contents($sourceDir.'/receipt.json'),true,32,JSON_THROW_ON_ERROR);
    $plan=json_decode((string)file_get_contents($sourceDir.'/search-plan.json'),true,32,JSON_THROW_ON_ERROR);
    if(($result['operation']??null)!==HMC6_SOURCE_OP||($result['state']??null)!=='terminal_failed_no_replay'
        ||($result['reason']??null)!=='samo_zero_page_shape')throw new RuntimeException('source_result');
    if(($receipt['operation']??null)!==HMC6_SOURCE_OP||!($receipt['provider_accessed']??false)
        ||!($receipt['no_replay']??false))throw new RuntimeException('source_receipt');
    if(($plan['route']['resort']??null)!=='Side'||($plan['route']['tv_region']['id']??null)!==23
        ||($plan['route']['samo_townto']['id']??null)!==20)throw new RuntimeException('source_route');
    if(($plan['date_from']??null)!==HMC_DATE_FROM||($plan['date_to']??null)!==HMC_DATE_TO
        ||($plan['nights']??null)!==HMC_NIGHTS||($plan['adults']??null)!==HMC_ADULTS
        ||($plan['children']??null)!==HMC_CHILDREN)throw new RuntimeException('source_scope');
    if(($plan['tv_operator_ids']??null)!==[13,18,25,43]
        ||array_map('strval',$plan['samo_operator_ids']??[])!==['5','115','315','342'])throw new RuntimeException('source_operators');

    $byId=[5=>['family'=>'anex','name'=>'ANEX'],115=>['family'=>'biblio','name'=>'Biblio Globus'],
        315=>['family'=>'funsun','name'=>'FUN&SUN'],342=>['family'=>'intourist','name'=>'Intourist']];
    $metas=glob($sourceDir.'/evidence-private/*-samo-price.meta.json')?:[];sort($metas,SORT_STRING);
    if(!$metas)throw new RuntimeException('source_price_missing');
    $rows=[];$pages=[];$hashes=[];$expected=1;$terminal=false;
    foreach($metas as $metaPath){
        $m=json_decode((string)file_get_contents($metaPath),true,32,JSON_THROW_ON_ERROR);
        $bin=preg_replace('/\.meta\.json$/','.bin',$metaPath);$raw=(string)file_get_contents($bin);
        if(hash('sha256',$raw)!==($m['raw_sha256']??null)||($m['source']??null)!=='samo-price'
            ||($m['http_status']??null)!==200)throw new RuntimeException('source_price_hash');
        $meta=$m['meta']??[];$page=(int)($meta['page']??0);$params=$meta['params']??[];
        if($terminal||$page!==$expected++||($meta['star']??null)!==4)throw new RuntimeException('source_price_sequence');
        $exact=['TOWNFROMINC'=>1,'STATEINC'=>5,'CHECKIN_BEG'=>'20261005','CHECKIN_END'=>'20261011',
            'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'STARS'=>(string)($params['STARS']??''),
            'OPERATORS'=>'5,115,315,342','TOWNTOINC'=>'20','PACKETTYPE'=>0,'PAGE'=>$page,'GROUP_BY'=>32];
        foreach($exact as $k=>$v)if(($params[$k]??null)!==$v)throw new RuntimeException('source_price_params');
        if(!preg_match('/^[1-9][0-9]*$/D',(string)$params['STARS']))throw new RuntimeException('source_star');
        $reply=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        if(!is_array($reply)||isset($reply['error'])||($reply['PAGE']??null)!==$page
            ||!is_int($reply['PAGES_COUNT']??null)||$reply['PAGES_COUNT']<0||!is_array($reply['PRICES']??null)
            ||count($reply['PRICES'])>2000)throw new RuntimeException('source_price_shape');
        $count=count($reply['PRICES']);
        if($reply['PAGES_COUNT']===0){
            if($count!==0||$page===1)throw new RuntimeException('source_terminal_shape');
            $terminal=true;
        }else{
            if($count===0||$reply['PAGES_COUNT']<$page)throw new RuntimeException('source_nonterminal_shape');
            foreach($reply['PRICES'] as $rawRow)if(is_array($rawRow)&&($row=hmc_samo_offer_row($rawRow,HMC_DATE_FROM,$byId))!==null)$rows[]=$row;
        }
        $pages[]=['page'=>$page,'pages_count'=>$reply['PAGES_COUNT'],'rows'=>$count,'terminal_empty'=>$terminal];
        $hashes[]=$m['raw_sha256'];
    }
    if(!$terminal)throw new RuntimeException('source_not_exhausted');
    return ['rows'=>$rows,'pages'=>$pages,'response_hashes'=>$hashes,'terminal_page'=>count($pages)];
}

function hmc6_execute(string $opDir): array {
    $reservation=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==HMC6_OP||($reservation['state']??null)!=='reserved_read_only_saved_reconciliation')
        throw new RuntimeException('reservation');
    $operations=dirname($opDir);$sourceDir=$operations.'/'.HMC6_SOURCE_OP;$previous=$operations.'/'.HMC_PREVIOUS_OP;
    $saved=hmc6_samo_rows($sourceDir);$checkpoint=hmc3_previous_checkpoint($previous);
    $sig=hmc_tv_signatures($checkpoint['rows']);if($sig!==['hotels'=>140,'tours'=>1809])throw new RuntimeException('retained_tv_membership');
    $common=[
        'anex'=>['tv'=>['id'=>13,'name'=>'ANEX'],'samo'=>['id'=>5,'name'=>'ANEX']],
        'biblio'=>['tv'=>['id'=>18,'name'=>'Библио-Глобус'],'samo'=>['id'=>115,'name'=>'Biblio Globus']],
        'funsun'=>['tv'=>['id'=>25,'name'=>'FUN&SUN'],'samo'=>['id'=>315,'name'=>'FUN&SUN']],
        'intourist'=>['tv'=>['id'=>43,'name'=>'Интурист'],'samo'=>['id'=>342,'name'=>'Intourist']],
    ];
    $tv=hmc_tv_offer_rows($checkpoint['rows'],HMC_DATE_FROM,$common);$resolved=hmf_resolve($tv,$saved['rows'],[]);
    $families=[];foreach($saved['rows'] as $r)$families[$r['operator_family']]=($families[$r['operator_family']]??0)+1;ksort($families);
    return ['operation'=>HMC6_OP,'state'=>'completed_read_only_saved_reconciliation','source_operation'=>HMC6_SOURCE_OP,
        'scope_provenance'=>HMC_PREVIOUS_OP,'route'=>['departure'=>'Moscow','country'=>'Turkey','resort'=>'Side',
            'tv_region_id'=>23,'samo_townto'=>20,'date_from'=>HMC_DATE_FROM,'date_to'=>HMC_DATE_TO,'nights'=>7,'adults'=>2,'children'=>0],
        'tv_operator_ids'=>[13,18,25,43],'samo_operator_ids'=>['5','115','315','342'],
        'tourvisor'=>['new_calls'=>0,'source_hotels'=>$sig['hotels'],'source_tours'=>$sig['tours'],'evidence_rows'=>count($tv),
            'search_id'=>$checkpoint['search_id'],'continue_calls'=>$checkpoint['prior_continue_calls'],'last_request_count'=>$checkpoint['last_request_count'],'fully_drained'=>false],
        'andromeda'=>['new_calls'=>0,'saved_price_responses'=>count($saved['pages']),'terminal_empty_page'=>$saved['terminal_page'],
            'fully_drained'=>true,'exhaustion'=>'terminal_empty_page_pages_count_zero','page_meta'=>$saved['pages'],
            'evidence_rows'=>count($saved['rows']),'operator_rows'=>$families],
        'resolver'=>$resolved,'candidate_evidence'=>hmc_candidate_evidence($resolved,$tv,$saved['rows']),
        'private_evidence'=>['response_hashes'=>$saved['response_hashes'],'raw_payload_exported'=>false,'source_server_directory'=>HMC6_SOURCE_OP.'/evidence-private'],
        'detail_queue_state'=>'pending_current_and_fuel_reconcile','provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,
        'booking_calls'=>0,'lead_writes'=>0,'metrika_writes'=>0,'no_replay_source_preserved'=>true];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');
    $opDir=(string)getenv('MATCH_OPERATION_DIR');if($opDir===''||!is_dir($opDir))throw new RuntimeException('runtime_paths');
    try{$result=hmc6_execute($opDir);$sha=hmc_write($opDir.'/result.json',$result);
        hmc_write($opDir.'/receipt.json',['operation'=>HMC6_OP,'state'=>$result['state'],'result_sha256'=>$sha,
            'readback_verified'=>hash_file('sha256',$opDir.'/result.json')===$sha,'provider_accessed'=>false,
            'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        echo hmc_json(['state'=>$result['state'],'samo_rows'=>$result['andromeda']['evidence_rows'],
            'hotel_candidates'=>$result['resolver']['hotel_candidate_count']])."\n";
    }catch(Throwable $e){$reason=preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';
        $fail=['operation'=>HMC6_OP,'state'=>'failed_read_only_saved_reconciliation','reason'=>$reason,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $sha=hmc_write($opDir.'/result.json',$fail);hmc_write($opDir.'/receipt.json',['operation'=>HMC6_OP,'state'=>$fail['state'],
            'result_sha256'=>$sha,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
        fwrite(STDERR,$reason."\n");exit(2);}
}
