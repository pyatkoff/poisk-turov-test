<?php
/** Fixed Tourvisor ANEX-only segments; paired helpers are loaded as a library by the caller. */

function anex_segment_input($input): array
{
    $keys = ['experiment_id','case_id','date','nights','adults','currency'];
    if (!is_array($input) || count($input)!==count($keys) || array_diff($keys,array_keys($input))
        || ($input['experiment_id'] ?? null)!=='one_day_anex_segments_20260908'
        || !in_array($input['case_id'] ?? null,['tv_alanya','tv_5star','tv_alanya_5star'],true)
        || $input['date']!=='2026-09-16' || $input['nights']!==7
        || $input['adults']!==2 || $input['currency']!=='RUB') {
        throw new RuntimeException('SEGMENT_INVALID_INPUT');
    }
    return $input;
}

function anex_segment_region(array $rows, int $countryId): array
{
    if (count($rows)>1000 || ($rows!==[] && array_keys($rows)!==range(0,count($rows)-1))) {
        throw new RuntimeException('SEGMENT_TV_REGIONS_SHAPE');
    }
    $found = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $id = anex_paired_id($row['id'] ?? null);
        $name = anex_paired_operator_name($row['name'] ?? null);
        $name = strtr($name,['Алания'=>'алания','АЛАНИЯ'=>'алания','Аланья'=>'аланья','АЛАНЬЯ'=>'аланья']);
        if ($id===null || !in_array($name,['alanya','алания','аланья'],true)) continue;
        $country = $row['countryId'] ?? ($row['country']['id'] ?? null);
        if ($country!==null && anex_paired_id($country)!==$countryId) continue;
        $found[$id] = ['id'=>$id,'name'=>anex_paired_text($row['name']),
            'country_id'=>$countryId,'identity_evidence'=>'exact_name_in_country_scoped_dictionary'];
    }
    if (count($found)!==1) throw new RuntimeException('SEGMENT_TV_REGION_NOT_UNIQUE');
    return array_values($found)[0];
}

function anex_segment_criteria(array $input, array $local, array $operator, ?array $region): array
{
    anex_segment_input($input);
    $criteria = ['departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id'],
        'dateFrom'=>$input['date'],'dateTo'=>$input['date'],'nightsFrom'=>7,'nightsTo'=>7,
        'adults'=>2,'childs'=>[],'currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false,
        'operatorIds'=>[$operator['id']]];
    if ($input['case_id']!=='tv_5star') {
        if ($region===null || anex_paired_id($region['id'] ?? null)===null) throw new RuntimeException('SEGMENT_REGION_REQUIRED');
        $criteria['regionIds'] = [$region['id']];
    }
    if ($input['case_id']!=='tv_alanya') $criteria['hotelCategory'] = '5';
    return $criteria;
}

/** Preserve the complete bounded response's hotel identities and facets before offer validation. */
function anex_segment_raw_response(array $groups, array $secrets): array
{
    if (count($groups)>1000 || ($groups!==[] && array_keys($groups)!==range(0,count($groups)-1))) {
        throw new RuntimeException('SEGMENT_TV_RESULTS_SHAPE');
    }
    $rows = []; $ids = []; $tours = 0;
    foreach ($groups as $hotel) {
        if (!is_array($hotel) || !is_array($hotel['tours'] ?? null)) throw new RuntimeException('SEGMENT_TV_RESULTS_SHAPE');
        $id = anex_paired_id($hotel['id'] ?? null);
        if ($id!==null) $ids[$id] = $id;
        $count = count($hotel['tours']); $tours += $count;
        $country = is_array($hotel['country'] ?? null) ? $hotel['country'] : [];
        $region = is_array($hotel['region'] ?? null) ? $hotel['region'] : [];
        $subregion = is_array($hotel['subRegion'] ?? null) ? $hotel['subRegion'] : [];
        $category = $hotel['category'] ?? null;
        $rows[] = ['hotel_id'=>$id,'name'=>anex_paired_text($hotel['name'] ?? null,$secrets),
            'country_id'=>anex_paired_id($country['id'] ?? null),
            'region_id'=>anex_paired_id($region['id'] ?? null),
            'region_name'=>anex_paired_text($hotel['region'] ?? null,$secrets),
            'subregion_id'=>anex_paired_id($subregion['id'] ?? null),
            'subregion_name'=>anex_paired_text($hotel['subRegion'] ?? null,$secrets),
            'hotel_category'=>(is_int($category) || is_string($category) || is_float($category))
                ? anex_paired_text((string)$category,$secrets,32) : null,
            'tour_count'=>$count];
    }
    return ['hotel_count'=>count($groups),'tour_count'=>$tours,'hotel_ids'=>array_values($ids),'hotels'=>$rows];
}

function anex_segment_filter_verification(array $raw, array $criteria): array
{
    $out = ['requested_in_initial_search'=>true,'local_post_filter_applied'=>false];
    $anyMismatch = false; $anyUnknown = false;
    foreach (['region','category'] as $facet) {
        $requested = $facet==='region' ? ($criteria['regionIds'][0] ?? null) : ($criteria['hotelCategory'] ?? null);
        $check = ['requested'=>$requested,'matched_ids'=>[],'mismatched_ids'=>[],'missing_ids'=>[],
            'missing_identity_rows'=>0,'status'=>$requested===null ? 'not_requested' : 'unobservable'];
        if ($requested!==null) {
            foreach ($raw['hotels'] as $row) {
                $value = $facet==='region' ? $row['region_id'] : $row['hotel_category'];
                if ($row['hotel_id']===null) { $check['missing_identity_rows']++; continue; }
                $key = $value===null ? 'missing_ids' : ((string)$value===(string)$requested ? 'matched_ids' : 'mismatched_ids');
                $check[$key][] = $row['hotel_id'];
            }
            foreach (['matched_ids','mismatched_ids','missing_ids'] as $key) $check[$key] = array_values(array_unique($check[$key]));
            $unknown = $check['missing_ids']!==[] || $check['missing_identity_rows']>0 || $raw['hotel_count']===0;
            if ($check['mismatched_ids']!==[]) { $check['status']='mismatch'; $anyMismatch=true; }
            elseif ($unknown) { $check['status']='unobservable'; }
            else $check['status']='verified';
            $anyUnknown = $anyUnknown || $unknown;
        }
        $out[$facet] = $check;
    }
    $out['response_respects_requested_filters'] = $anyMismatch ? false : ($anyUnknown ? null : true);
    return $out;
}

function anex_segment_main(): array
{
    $started=microtime(true); $pdo=null; $tvRequests=[]; $secrets=[];
    $report=['schema_version'=>1,'experiment_id'=>'one_day_anex_segments_20260908','case_id'=>null,
        'scope'=>'preview','status'=>'probe_unavailable','ok'=>false,'offers'=>[],
        'coverage_limits'=>['full_supplier_inventory'=>false,'final_price_verified'=>false],
        'requests'=>['tourvisor'=>0,'anex'=>0,'total'=>0],'no_request'=>true];
    try {
        if (PHP_SAPI!=='cli') throw new RuntimeException('SEGMENT_CLI_ONLY');
        $raw=file_get_contents('php://stdin',false,null,0,4097);
        if (!is_string($raw) || strlen($raw)>4096) throw new RuntimeException('SEGMENT_INVALID_INPUT');
        $input=anex_segment_input(json_decode($raw,true,8));
        $report['case_id']=$input['case_id']; $report['input']=$input;
        $accountHome=(string)getenv('HOME'); $root=realpath($accountHome.'/www/anytoour.ru');
        $preview=realpath($accountHome.'/www/anytoour.ru/_preview/search3-anex-candidate');
        if (!$root || !$preview || $preview!==$root.'/_preview/search3-anex-candidate'
            || !in_array(realpath((string)getcwd()),[$root,$preview],true)) throw new RuntimeException('SEGMENT_INVALID_RUNTIME');
        require_once $accountHome.'/.anytoour-anex/search3-preview.php';
        $manifest=json_decode((string)file_get_contents($preview.'/anex-preview-manifest.json'),true);
        if (!defined('ANYTOUR_ANEX_PREVIEW_ENABLED') || ANYTOUR_ANEX_PREVIEW_ENABLED!==true
            || !defined('ANEX_PREVIEW_SOURCE_SHA') || ANEX_PREVIEW_SOURCE_SHA!=='d3bf074933f372e4e06eec3781c51b96542aa57a'
            || ($manifest['source_sha'] ?? null)!==ANEX_PREVIEW_SOURCE_SHA) throw new RuntimeException('SEGMENT_PREVIEW_CONFIGURATION');
        $report['preview_source_sha']=ANEX_PREVIEW_SOURCE_SHA;
        if (defined('ANEX_API_TOKEN') && is_string(ANEX_API_TOKEN)) $secrets[]=ANEX_API_TOKEN;
        require_once $preview.'/app/integrations/anex-search-mapping-registry.php';
        $helper=is_file($root.'/data/db-v1.php') ? $root.'/data/db-v1.php' : $root.'/v2/data/db-v1.php';
        require_once $helper;
        $pdo=v2_data_db();
        if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') throw new RuntimeException('SEGMENT_DATABASE_UNAVAILABLE');
        $report['preservation_before']=anex_paired_preservation($pdo);
        $pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
        try {
            $lookup=$pdo->prepare('SELECT d.id AS departure_id,c.id AS country_id FROM catalog_departures d CROSS JOIN catalog_countries c'
                .' WHERE d.is_active=1 AND c.is_active=1 AND d.name IN (?,?) AND c.name IN (?,?) LIMIT 2');
            $lookup->execute(['Москва','Moscow','Турция','Turkey']); $local=$lookup->fetchAll(PDO::FETCH_ASSOC);
            if (count($local)!==1) throw new RuntimeException('SEGMENT_LOCAL_IDENTITY_NOT_UNIQUE');
            $local=$local[0];
        } finally { $pdo->rollBack(); }
        $tvHelper=is_file($root.'/data/tourvisor-client-v1.php') ? $root.'/data/tourvisor-client-v1.php' : $root.'/v2/data/tourvisor-client-v1.php';
        require_once $tvHelper;
        $token=v2_data_tourvisor_token();
        if ($token==='') throw new RuntimeException('SEGMENT_TV_TOKEN_REQUIRED');
        $secrets[]=$token; $deadline=microtime(true)+160;
        $operator=anex_paired_operator(anex_paired_tv_get('/operators',[
            'departureId'=>(int)$local['departure_id'],'countryId'=>(int)$local['country_id']],$token,$deadline,$tvRequests));
        $report['operator']=$operator; $region=null;
        if ($input['case_id']!=='tv_5star') {
            usleep(1050000);
            $region=anex_segment_region(anex_paired_tv_get('/regions',['countryId'=>(int)$local['country_id']],$token,$deadline,$tvRequests),(int)$local['country_id']);
            $report['region']=$region;
        }
        $criteria=anex_segment_criteria($input,$local,$operator,$region); $report['criteria']=$criteria;
        usleep(1050000);
        $search=anex_paired_tv_get('/tours/search',$criteria,$token,$deadline,$tvRequests);
        $searchId=$search['searchId'] ?? null;
        if ((!is_int($searchId) && !is_string($searchId)) || !preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$searchId)) {
            throw new RuntimeException('SEGMENT_TV_SEARCH_ID_REQUIRED');
        }
        $report['search_handle']=(string)$searchId; $report['search_started']=true;
        $report['search_status_samples']=[]; $complete=false;
        for ($poll=0;$poll<8 && microtime(true)<$deadline-30;++$poll) {
            sleep($poll===0 ? 1 : 10);
            $status=anex_paired_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false],$token,$deadline,$tvRequests);
            $report['search_status_samples'][]=anex_paired_metrics($status,$secrets);
            $complete=(is_numeric($status['progress'] ?? null) && (float)$status['progress']>=100)
                || (is_string($status['status'] ?? null) && strtolower($status['status'])==='complete');
            if ($complete) break;
        }
        usleep(1050000);
        $groups=anex_paired_tv_get('/tours/search/'.$searchId,['limit'=>100],$token,$deadline,$tvRequests);
        $report['raw_response']=anex_segment_raw_response($groups,$secrets);
        $report['filter_verification']=anex_segment_filter_verification($report['raw_response'],$criteria);
        $summary=[]; $offers=anex_paired_tv_offers($groups,$criteria,$operator,$secrets,$summary);
        $ids=array_values(array_unique(array_column($offers,'hotel_id'))); $known=[];
        if ($ids) {
            $pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
            try {
                $read=$pdo->prepare('SELECT id FROM catalog_hotels WHERE country_id=? AND is_active=1 AND id IN ('
                    .implode(',',array_fill(0,count($ids),'?')).')');
                $read->execute(array_merge([$criteria['countryId']],$ids));
                foreach ($read->fetchAll(PDO::FETCH_COLUMN) as $id) $known[(int)$id]=true;
            } finally { $pdo->rollBack(); }
        }
        foreach ($offers as &$offer) if (isset($known[$offer['hotel_id']])) $offer['local_hotel_id']=$offer['hotel_id'];
        unset($offer);
        $report['offers']=$offers; $report['summary']=$summary;
        $report['summary']['offers']=count($offers);
        $report['summary']['unique_hotels']=count(array_unique(array_column($offers,'hotel_id')));
        $report['summary']['mapped_hotels']=count($known);
        $report['coverage_limits']+=['hotel_limit'=>100,'offer_limit'=>1500,'search_complete'=>$complete,
            'hotel_limit_reached'=>count($groups)>=100,'output_limit_reached'=>$summary['output_limit_reached'],
            'status_poll_limit'=>8,'continuation_requested'=>false,'supplier_total_known'=>false,
            'facet_filtering'=>'Requested from supplier; raw response independently checked; no local facet exclusion'];
        $report['status']='ok'; $report['ok']=true;
    } catch (Throwable $error) {
        $code=$error->getMessage();
        $report['error_code']=preg_match('/\A(?:SEGMENT|PAIRED|ANEX)_[A-Z_]{1,70}\z/D',$code) ? $code : 'SEGMENT_PROBE_ERROR';
    } finally {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if ($pdo instanceof PDO && isset($report['preservation_before'])) {
            try {
                $report['preservation_after']=anex_paired_preservation($pdo);
                $report['preservation_verified']=$report['preservation_before']===$report['preservation_after'];
                if (!$report['preservation_verified']) throw new RuntimeException('SEGMENT_PRESERVATION_CHANGED');
            } catch (Throwable $ignored) {
                $report['ok']=false; $report['status']='probe_unavailable'; $report['error_code']='SEGMENT_PRESERVATION_UNVERIFIED';
            }
        }
        $report['requests']=['tourvisor'=>count($tvRequests),'anex'=>0,'total'=>count($tvRequests),'tourvisor_log'=>$tvRequests];
        $report['no_request']=count($tvRequests)===0; $report['elapsed_ms']=(int)round((microtime(true)-$started)*1000);
        $report['generated_at_utc']=gmdate('c');
    }
    $json=json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $failure=$json===false || strlen($json)>3900000 ? 'SEGMENT_OUTPUT_LIMIT' : null;
    foreach ($secrets as $secret) if ($secret!=='' && $json!==false && strpos($json,$secret)!==false) $failure='SEGMENT_OUTPUT_REDACTED';
    if ($failure!==null) return ['schema_version'=>1,'experiment_id'=>'one_day_anex_segments_20260908',
        'case_id'=>$report['case_id'],'status'=>'probe_unavailable','ok'=>false,'error_code'=>$failure,
        'requests'=>$report['requests'],'no_request'=>$report['no_request'],'offers'=>[]];
    return $report;
}

if (!defined('ANYTOUR_ANEX_SEGMENT_LIBRARY_ONLY')) {
    error_reporting(0); ob_start(); $anexSegmentReport=anex_segment_main(); ob_end_clean();
    echo json_encode($anexSegmentReport,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
    exit(($anexSegmentReport['ok'] || ($anexSegmentReport['preservation_verified'] ?? false)) ? 0 : 1);
}
