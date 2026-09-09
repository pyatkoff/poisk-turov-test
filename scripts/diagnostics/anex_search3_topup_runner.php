<?php
/** One fixed Tourvisor hotelIds experiment; paired helpers are loaded as a library by the caller. */

function anex_topup_input($input): array
{
    $keys = ['experiment_id','preview_source_sha','hotel_ids'];
    if (!is_array($input) || count($input)!==count($keys) || array_diff($keys,array_keys($input))
        || ($input['experiment_id'] ?? null)!=='catalog_hotel_topup_20260909'
        || ($input['preview_source_sha'] ?? null)!=='2545982789ada8db8c4be18be0c1159f624633bc'
        || ($input['hotel_ids'] ?? null)!==[445,9365,56094]) {
        throw new RuntimeException('TOPUP_INVALID_INPUT');
    }
    return $input;
}

function anex_topup_criteria(array $input, array $local): array
{
    anex_topup_input($input);
    if (anex_paired_id($local['departure_id'] ?? null)!==1 || anex_paired_id($local['country_id'] ?? null)!==1) {
        throw new RuntimeException('TOPUP_LOCAL_IDENTITY_MISMATCH');
    }
    return ['departureId'=>1,'countryId'=>1,'dateFrom'=>'2026-09-18','dateTo'=>'2026-09-18',
        'nightsFrom'=>8,'nightsTo'=>8,'adults'=>2,'childs'=>[],'currency'=>'RUB',
        'onlyCharter'=>false,'onlyDirect'=>false,'meal'=>'7','hotelIds'=>$input['hotel_ids']];
}

/** The same effective registry used by the preview must still accept every selected local identity. */
function anex_topup_catalog(array $rows, array $mappingRows, callable $resolve, array $secrets): array
{
    $wanted=[445,9365,56094]; $out=[]; $accepted=[];
    if (count($rows)!==3 || count($mappingRows)>1000) throw new RuntimeException('TOPUP_CATALOG_IDENTITY_REQUIRED');
    foreach ($mappingRows as $row) {
        if (!is_array($row)) throw new RuntimeException('TOPUP_CATALOG_IDENTITY_REQUIRED');
        $external=anex_paired_id($row['anex_hotel_id'] ?? null);
        $local=anex_paired_id($row['catalog_hotel_id'] ?? null);
        if ($external!==null && in_array($local,$wanted,true) && $resolve('anex_online',$external)===$local) {
            $accepted[$local]=true;
        }
    }
    foreach ($rows as $row) {
        if (!is_array($row)) throw new RuntimeException('TOPUP_CATALOG_IDENTITY_REQUIRED');
        $id=anex_paired_id($row['id'] ?? null); $name=anex_paired_text($row['name'] ?? null,$secrets);
        if (!in_array($id,$wanted,true) || isset($out[$id]) || $name===null || !isset($accepted[$id])
            || anex_paired_id($row['country_id'] ?? null)!==1 || !in_array($row['is_active'] ?? null,[1,'1'],true)) {
            throw new RuntimeException('TOPUP_CATALOG_IDENTITY_REQUIRED');
        }
        $out[$id]=['hotel_id'=>$id,'name'=>$name,'country_id'=>1,'active'=>true,
            'accepted_mapping_verified'=>true,'identity_source'=>'effective_preview_registry'];
    }
    return array_map(static function (int $id) use ($out): array { return $out[$id]; },$wanted);
}

function anex_topup_meal_match(?string $meal): ?bool
{
    if ($meal===null) return null;
    $meal=trim((string)preg_replace('/[\s_\-]+/u',' ',$meal));
    if (preg_match('/\A(?:ai|all|all inclusive|uai|ultra all inclusive|ai without alcohol|all inclusive without alcohol|вс[её] включено|ультра вс[её] включено|вс[её] включено без алкоголя)\z/iu',$meal)) return true;
    if (preg_match('/\A(?:ro|bb|hb|fb|sc|room only|bed and breakfast|half board|full board|self catering|без питания|завтрак|завтраки|полупансион|полный пансион)\z/iu',$meal)) return false;
    return null;
}

function anex_topup_tour(array $tour, array $secrets, ?string &$rejection): ?array
{
    $rejection=null;
    if (anex_paired_date($tour['date'] ?? null)!=='2026-09-18' || !in_array($tour['nights'] ?? null,[8,'8'],true)) {
        $rejection='date_or_nights'; return null;
    }
    if ((array_key_exists('adults',$tour) && !in_array($tour['adults'],[2,'2'],true))
        || (array_key_exists('children',$tour) && !in_array($tour['children'],[0,'0'],true))
        || (array_key_exists('childs',$tour) && $tour['childs']!==[] && $tour['childs']!==0 && $tour['childs']!=='0')) {
        $rejection='party'; return null;
    }
    $price=anex_paired_price($tour['price'] ?? null);
    if ($price===null || (array_key_exists('currency',$tour) && $tour['currency']!=='RUB')) {
        $rejection='price_or_currency'; return null;
    }
    $meal=anex_paired_text($tour['meal'] ?? null,$secrets);
    return ['date'=>'2026-09-18','nights'=>8,'adults'=>2,'children'=>0,'traveller_context_source'=>'request',
        'price'=>$price,'currency'=>'RUB','currency_source'=>array_key_exists('currency',$tour) ? 'response' : 'request',
        'meal'=>$meal,'meal_filter_match'=>anex_topup_meal_match($meal),
        'room'=>anex_paired_text($tour['roomType'] ?? null,$secrets),
        'placement'=>anex_paired_text($tour['placement'] ?? null,$secrets),
        'operator'=>anex_paired_text($tour['operator'] ?? null,$secrets),
        'final_price_verified'=>false,'same_package_as_anex_verified'=>false];
}

/** Keep all returned hotel identities, including unexpected IDs; only invalid tour facts are excluded from minima. */
function anex_topup_response(array $groups, array $secrets): array
{
    if (count($groups)>1000 || ($groups!==[] && array_keys($groups)!==range(0,count($groups)-1))) {
        throw new RuntimeException('TOPUP_TV_RESULTS_SHAPE');
    }
    $hotels=[]; $ids=[]; $unknown=0; $rawTours=0; $validTours=0;
    $mealCounts=['matched'=>0,'mismatched'=>0,'unverified'=>0];
    foreach ($groups as $hotel) {
        if (!is_array($hotel) || !is_array($hotel['tours'] ?? null)
            || ($hotel['tours']!==[] && array_keys($hotel['tours'])!==range(0,count($hotel['tours'])-1))) {
            throw new RuntimeException('TOPUP_TV_RESULTS_SHAPE');
        }
        $id=anex_paired_id($hotel['id'] ?? null);
        if ($id===null) $unknown++; else $ids[$id]=$id;
        $count=count($hotel['tours']); $rawTours+=$count;
        if ($rawTours>25000) throw new RuntimeException('TOPUP_TV_TOURS_LIMIT');
        $country=anex_paired_id($hotel['countryId'] ?? (is_array($hotel['country'] ?? null) ? ($hotel['country']['id'] ?? null) : null));
        $row=['hotel_id'=>$id,'name'=>anex_paired_text($hotel['name'] ?? null,$secrets),
            'country_id'=>$country,'raw_tour_count'=>$count,'accepted_tour_count'=>0,'rejected_tour_count'=>0,
            'rejections'=>[],'minimum_price_rub'=>null,'samples'=>[],
            'meal_filter_counts'=>['matched'=>0,'mismatched'=>0,'unverified'=>0],
            'meal_mismatched_samples'=>[],'meal_unverified_samples'=>[]];
        foreach ($hotel['tours'] as $tour) {
            $rejection=null; $sample=null;
            if ($id===null || ($country!==null && $country!==1)) $rejection='hotel_identity';
            elseif (!is_array($tour)) $rejection='tour_shape';
            else $sample=anex_topup_tour($tour,$secrets,$rejection);
            if ($sample===null) {
                $row['rejected_tour_count']++; $key=$rejection ?? 'tour_shape';
                $row['rejections'][$key]=($row['rejections'][$key] ?? 0)+1; continue;
            }
            $mealStatus=$sample['meal_filter_match']===true ? 'matched' : ($sample['meal_filter_match']===false ? 'mismatched' : 'unverified');
            $row['meal_filter_counts'][$mealStatus]++; $mealCounts[$mealStatus]++;
            if ($mealStatus!=='matched') {
                $key='meal_'.$mealStatus; $row['rejected_tour_count']++;
                $row['rejections'][$key]=($row['rejections'][$key] ?? 0)+1;
                if (count($row[$key.'_samples'])<2) $row[$key.'_samples'][]=$sample;
                continue;
            }
            $row['accepted_tour_count']++; $validTours++;
            if ($row['minimum_price_rub']===null || (float)$sample['price']<(float)$row['minimum_price_rub']) {
                $row['minimum_price_rub']=$sample['price'];
            }
            $row['samples'][]=$sample;
            usort($row['samples'],static function (array $a,array $b): int { return (float)$a['price']<=>(float)$b['price']; });
            $row['samples']=array_slice($row['samples'],0,3);
        }
        $hotels[]=$row;
    }
    $returned=array_values($ids); $requested=[445,9365,56094];
    $unexpected=array_values(array_diff($returned,$requested));
    $countryMismatch=array_values(array_unique(array_column(array_filter($hotels,static function (array $row): bool {
        return $row['country_id']!==null && $row['country_id']!==1;
    }),'hotel_id')));
    return ['raw_response'=>['hotel_count'=>count($groups),'tour_count'=>$rawTours,'hotel_ids'=>$returned,'hotels'=>$hotels],
        'filter_verification'=>['requested_in_initial_search'=>true,'local_hotel_post_filter_applied'=>false,
            'requested_hotel_ids'=>$requested,'returned_hotel_ids'=>$returned,'unexpected_hotel_ids'=>$unexpected,
            'missing_requested_hotel_ids'=>array_values(array_diff($requested,$returned)),
            'missing_identity_rows'=>$unknown,'country_mismatch_hotel_ids'=>$countryMismatch,
            'response_respects_hotel_ids'=>$unexpected!==[] ? false : (($unknown>0 || $groups===[]) ? null : true),
            'meal'=>['requested'=>'7','check_scope'=>'tours_with_valid_date_nights_party_and_rub_price',
                'counts'=>$mealCounts,'response_respects_meal'=>$mealCounts['mismatched']>0 ? false
                    : (($mealCounts['unverified']>0 || $mealCounts['matched']===0) ? null : true)]],
        'summary'=>['returned_hotels'=>count($groups),'returned_unique_hotels'=>count($returned),
            'requested_hotels_returned'=>count(array_intersect($requested,$returned)),
            'valid_tours'=>$validTours,'rejected_tours'=>$rawTours-$validTours,
            'meal_filter_counts'=>$mealCounts,'sample_limit_per_hotel'=>3,'meal_rejection_sample_limit_per_type'=>2]];
}

function anex_topup_main(): array
{
    $started=microtime(true); $pdo=null; $tvRequests=[]; $secrets=[];
    $report=['schema_version'=>1,'experiment_id'=>'catalog_hotel_topup_20260909','scope'=>'preview',
        'status'=>'probe_unavailable','ok'=>false,'requests'=>['tourvisor'=>0,'anex'=>0,'total'=>0],'no_request'=>true,
        'coverage_limits'=>['full_supplier_inventory'=>false,'final_price_verified'=>false,'same_package_as_anex_verified'=>false,
            'hotel_limit'=>100,'status_poll_limit'=>8,'continuation_requested'=>false,'supplier_total_known'=>false],
        'side_effects'=>['mapping_writes'=>0,'catalog_writes'=>0,'observation_writes'=>0,'content_requests'=>0]];
    try {
        if (PHP_SAPI!=='cli') throw new RuntimeException('TOPUP_CLI_ONLY');
        $raw=file_get_contents('php://stdin',false,null,0,4097);
        if (!is_string($raw) || strlen($raw)>4096) throw new RuntimeException('TOPUP_INVALID_INPUT');
        $input=anex_topup_input(json_decode($raw,true,8)); $report['input']=$input;
        $accountHome=(string)getenv('HOME'); $root=realpath($accountHome.'/www/anytoour.ru');
        $preview=realpath($accountHome.'/www/anytoour.ru/_preview/search3-anex-candidate');
        if (!$root || !$preview || $preview!==$root.'/_preview/search3-anex-candidate'
            || !in_array(realpath((string)getcwd()),[$root,$preview],true)) throw new RuntimeException('TOPUP_INVALID_RUNTIME');
        require_once $accountHome.'/.anytoour-anex/search3-preview.php';
        $manifest=json_decode((string)file_get_contents($preview.'/anex-preview-manifest.json'),true);
        if (!defined('ANYTOUR_ANEX_PREVIEW_ENABLED') || ANYTOUR_ANEX_PREVIEW_ENABLED!==true
            || !defined('ANEX_PREVIEW_SOURCE_SHA') || ANEX_PREVIEW_SOURCE_SHA!==$input['preview_source_sha']
            || ($manifest['source_sha'] ?? null)!==$input['preview_source_sha']) throw new RuntimeException('TOPUP_PREVIEW_CONFIGURATION');
        $report['preview_source_sha']=ANEX_PREVIEW_SOURCE_SHA;
        if (defined('ANEX_API_TOKEN') && is_string(ANEX_API_TOKEN)) $secrets[]=ANEX_API_TOKEN;
        require_once $preview.'/app/integrations/anex-search-mapping-registry.php';
        $helper=is_file($root.'/data/db-v1.php') ? $root.'/data/db-v1.php' : $root.'/v2/data/db-v1.php';
        require_once $helper; $pdo=v2_data_db();
        if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql') throw new RuntimeException('TOPUP_DATABASE_UNAVAILABLE');
        $report['preservation_before']=anex_paired_preservation($pdo);
        $pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
        try {
            $lookup=$pdo->prepare('SELECT d.id AS departure_id,c.id AS country_id FROM catalog_departures d CROSS JOIN catalog_countries c'
                .' WHERE d.is_active=1 AND c.is_active=1 AND d.name IN (?,?) AND c.name IN (?,?) LIMIT 2');
            $lookup->execute(['Москва','Moscow','Египет','Egypt']); $local=$lookup->fetchAll(PDO::FETCH_ASSOC);
            if (count($local)!==1) throw new RuntimeException('TOPUP_LOCAL_IDENTITY_NOT_UNIQUE');
            $criteria=anex_topup_criteria($input,$local[0]);
            $hotels=$pdo->query('SELECT id,name,country_id,is_active FROM catalog_hotels WHERE id IN (445,9365,56094) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $candidates=$pdo->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE catalog_hotel_id IN (445,9365,56094)'
                .' UNION SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE catalog_hotel_id IN (445,9365,56094) LIMIT 1001')->fetchAll(PDO::FETCH_ASSOC);
            $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
            $report['catalog']=anex_topup_catalog($hotels,$candidates,$registry->previewResolver(),$secrets);
        } finally { $pdo->rollBack(); }
        $report['criteria']=$criteria;
        $tvHelper=is_file($root.'/data/tourvisor-client-v1.php') ? $root.'/data/tourvisor-client-v1.php' : $root.'/v2/data/tourvisor-client-v1.php';
        require_once $tvHelper; $token=v2_data_tourvisor_token();
        if ($token==='') throw new RuntimeException('TOPUP_TV_TOKEN_REQUIRED');
        $secrets[]=$token; $deadline=microtime(true)+160;
        $search=anex_paired_tv_get('/tours/search',$criteria,$token,$deadline,$tvRequests);
        $searchId=$search['searchId'] ?? null;
        if ((!is_int($searchId) && !is_string($searchId)) || !preg_match('/\A[1-9][0-9]{0,17}\z/D',(string)$searchId)) {
            throw new RuntimeException('TOPUP_TV_SEARCH_ID_REQUIRED');
        }
        $report['search_started']=true; $report['search_status_samples']=[]; $complete=false;
        for ($poll=0;$poll<8 && microtime(true)<$deadline-30;++$poll) {
            sleep($poll===0 ? 1 : 10);
            $status=anex_paired_tv_get('/tours/search/'.$searchId.'/status',['operatorStatus'=>false],$token,$deadline,$tvRequests);
            $report['search_status_samples'][]=anex_paired_metrics($status,$secrets);
            $complete=(is_numeric($status['progress'] ?? null) && (float)$status['progress']>=100)
                || (is_string($status['status'] ?? null) && strtolower($status['status'])==='complete');
            if ($complete) break;
        }
        $report['coverage_limits']['search_complete']=$complete;
        $report['coverage_limits']['status_poll_count']=count($report['search_status_samples']);
        usleep(1050000);
        $groups=anex_paired_tv_get('/tours/search/'.$searchId,['limit'=>100],$token,$deadline,$tvRequests);
        $report+=anex_topup_response($groups,$secrets);
        $report['coverage_limits']+=['hotel_limit_reached'=>count($groups)>=100,
            'selected_subset_only'=>true,'operator_filter_requested'=>false];
        $report['status']='ok'; $report['ok']=true;
    } catch (Throwable $error) {
        $code=$error->getMessage();
        $report['error_code']=preg_match('/\A(?:TOPUP|PAIRED|ANEX)_[A-Z_]{1,70}\z/D',$code) ? $code : 'TOPUP_PROBE_ERROR';
    } finally {
        if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if ($pdo instanceof PDO && isset($report['preservation_before'])) {
            try {
                $report['preservation_after']=anex_paired_preservation($pdo);
                $report['preservation_verified']=$report['preservation_before']===$report['preservation_after'];
                if (!$report['preservation_verified']) throw new RuntimeException('TOPUP_PRESERVATION_CHANGED');
            } catch (Throwable $ignored) {
                $report['ok']=false; $report['status']='probe_unavailable'; $report['error_code']='TOPUP_PRESERVATION_UNVERIFIED';
            }
        }
        $report['requests']=['tourvisor'=>count($tvRequests),'anex'=>0,'total'=>count($tvRequests),'tourvisor_log'=>$tvRequests];
        $report['no_request']=count($tvRequests)===0; $report['elapsed_ms']=(int)round((microtime(true)-$started)*1000);
        $report['generated_at_utc']=gmdate('c');
    }
    $json=json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $failure=$json===false || strlen($json)>1500000 ? 'TOPUP_OUTPUT_LIMIT' : null;
    foreach ($secrets as $secret) if ($secret!=='' && $json!==false && strpos($json,$secret)!==false) $failure='TOPUP_OUTPUT_REDACTED';
    if ($failure!==null) return ['schema_version'=>1,'experiment_id'=>'catalog_hotel_topup_20260909',
        'status'=>'probe_unavailable','ok'=>false,'error_code'=>$failure,
        'requests'=>$report['requests'],'no_request'=>$report['no_request']];
    return $report;
}

if (!defined('ANYTOUR_ANEX_TOPUP_LIBRARY_ONLY')) {
    error_reporting(0); ob_start(); $anexTopupReport=anex_topup_main(); ob_end_clean();
    echo json_encode($anexTopupReport,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
    exit(($anexTopupReport['ok'] || ($anexTopupReport['preservation_verified'] ?? false)) ? 0 : 1);
}
