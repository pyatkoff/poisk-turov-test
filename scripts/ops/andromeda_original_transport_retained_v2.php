<?php
declare(strict_types=1);

/** Historical evidence binding, NOT a current quote/selection authorization. */
function anytour_original_retained_bind_v2(array $item, array $record, array $first, array $page): array
{
    $binding = $record['context'] ?? [];
    $ref = $item['ref']; $number = $item['page']; $cohort = $item['created'];
    $generation = $binding['generation'] ?? null;
    if (!is_int($generation) || $generation < 1 || ($binding['search_ref'] ?? null) !== $ref
        || ($binding['page'] ?? null) !== $number || ($binding['offer_ref'] ?? null) !== $item['offer']) {
        throw new RuntimeException('PACKAGE_FILENAME_BINDING');
    }
    foreach ([[$first, 1], [$page, $number]] as [$state, $expectedPage]) {
        $store = $state['store'] ?? []; $snapshot = $store['snapshot'] ?? [];
        if (($state['search_ref'] ?? null) !== $ref || ($store['search_ref'] ?? null) !== $ref
            || ($state['generation'] ?? null) !== $generation || ($store['generation'] ?? null) !== $generation
            || !in_array($state['status'] ?? null, ['complete', 'partial'], true)
            || ($snapshot['page'] ?? null) !== $expectedPage
            || ($state['criteria']['PAGE'] ?? null) !== $expectedPage
            || ($store['criteria']['PAGE'] ?? null) !== $expectedPage) {
            throw new RuntimeException('PAGE_IDENTITY_BINDING');
        }
        $a = $state['criteria']; $b = $store['criteria']; ksort($a); ksort($b);
        if ($a !== $b) throw new RuntimeException('PAGE_CRITERIA_BINDING');
    }
    $firstCreated = $first['store']['created_at'] ?? null;
    $pageCreated = $page['store']['created_at'] ?? null;
    if (!is_int($firstCreated) || (string)$firstCreated !== $cohort) throw new RuntimeException('FIRST_COHORT_BINDING');
    if (!is_int($pageCreated) || $pageCreated < $firstCreated) throw new RuntimeException('PAGE_TIME_ORDER');
    $firstCriteria = $first['criteria']; $pageCriteria = $page['criteria'];
    unset($firstCriteria['PAGE'], $pageCriteria['PAGE']); ksort($firstCriteria); ksort($pageCriteria);
    if ($firstCriteria !== $pageCriteria) throw new RuntimeException('COHORT_CRITERIA_BINDING');
    $matches = [];
    foreach (($page['store']['snapshot']['offers'] ?? []) as $offer) {
        if (is_array($offer) && ($offer['offer_ref'] ?? null) === $item['offer']) $matches[] = $offer;
    }
    if (count($matches) !== 1) throw new RuntimeException('PRICE_OFFER_BINDING');
    $offer = $matches[0];
    if (isset($binding['operator_ref']) && $binding['operator_ref'] !== ($offer['operator_ref'] ?? null)) {
        throw new RuntimeException('OPERATOR_BINDING');
    }
    if (isset($binding['local_hotel_id']) && $binding['local_hotel_id'] !== ($offer['local_hotel_id'] ?? null)) {
        throw new RuntimeException('HOTEL_BINDING');
    }
    return ['offer' => $offer, 'cohort_created_at' => $firstCreated,
        'page_created_at' => $pageCreated, 'v1_time_equality_would_reject' => $pageCreated !== $firstCreated];
}

/** Inspect already retained originals. No client, config, network, DB or file mutation. */
function anytour_original_transport_retained_v2(string $directory, int $cutoff): array
{
    require_once dirname(__DIR__) . '/diagnostics/andromeda_original_transport_facts.php';
    if (is_link($directory) || !is_dir($directory) || basename($directory) !== 'searches' || $cutoff < 1) {
        throw new RuntimeException('RETAINED_TRANSPORT_DIRECTORY');
    }
    $readCount = 0;
    $read = static function(string $path) use (&$readCount): array {
        if (++$readCount > 1800 || is_link($path) || !is_file($path)) throw new RuntimeException('RETAINED_TRANSPORT_FILE');
        $size = filesize($path);
        if (!is_int($size) || $size < 2 || $size > 3000000) throw new RuntimeException('RETAINED_TRANSPORT_SIZE');
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) !== $size) throw new RuntimeException('RETAINED_TRANSPORT_READ');
        $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($data)) throw new RuntimeException('RETAINED_TRANSPORT_JSON');
        return [$data, hash('sha256', $bytes)];
    };
    $paths = []; $inventory = 0;
    foreach (new DirectoryIterator($directory) as $entry) {
        if ($entry->isDot()) continue;
        if (++$inventory > 100000) throw new RuntimeException('RETAINED_TRANSPORT_INVENTORY');
        if ($entry->isLink() || !$entry->isFile() || $entry->getMTime() > $cutoff) continue;
        if (!preg_match('/\A([a-f0-9]{64})-([0-9]{1,12})-([0-9]{1,4})-(offer_[a-f0-9]{64})-package\.json\z/D', $entry->getFilename(), $m)) continue;
        $paths[] = ['file'=>$entry->getFilename(), 'mtime'=>$entry->getMTime(), 'ref'=>$m[1], 'created'=>$m[2], 'page'=>(int)$m[3], 'offer'=>$m[4]];
    }
    usort($paths, static fn(array $a,array $b):int => ($b['mtime'] <=> $a['mtime']) ?: strcmp($a['file'],$b['file']));
    $rows=[]; $skips=[]; $first32=['examined'=>0,'bound'=>0,'recovered_page_time'=>0,'skips'=>[]];
    $examined=0; $recovered=0;
    foreach (array_slice($paths,0,600) as $item) {
        ++$examined; $top=$examined<=32; if($top)++$first32['examined'];
        try {
            [$env,$packageHash]=$read($directory.'/'.$item['file']);
            $record=$env['record']??null;
            if(!is_array($record)||($record['status']??null)!=='captured'||!is_array($record['private_package']??null))throw new RuntimeException('NOT_CAPTURED');
            [$first,$firstHash]=$read($directory.'/'.$item['ref'].'-1.json');
            if($item['page']===1){$page=$first;$pageHash=$firstHash;}
            else{[$page,$pageHash]=$read($directory.'/'.$item['ref'].'-'.$item['created'].'-'.$item['page'].'.json');}
            $joined=anytour_original_retained_bind_v2($item,$record,$first,$page);
            $offer=$joined['offer'];$tc=$offer['transport_context']??[];
            $context=['operatorKey'=>$offer['operator_ref']??null,'tourKey'=>$tc['tour_ref']??null,
                'programKey'=>$tc['program_ref']??null,'spoKey'=>$tc['spo_ref']??null,
                'checkIn'=>$offer['check_in']??null,'nights'=>$offer['nights']??null,
                'adult'=>$offer['adults']??null,'child'=>$offer['children']??null,'currency'=>$offer['price']['currency']??null];
            $original=json_encode($record['private_package'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $facts=AnyTourAndromedaOriginalTransportFacts::fromJson($original,$context);
            $money=[];$states=[];$details=[];$scopes=[];
            foreach($facts['transports'] as $transport){
                $scopes[$transport['scope']]=($scopes[$transport['scope']]??0)+1;
                $details[$transport['details_state']]=($details[$transport['details_state']]??0)+1;
                $locations=array_merge([['path'=>$transport['path'],'money_fields'=>$transport['money_fields']]],$transport['details']);
                foreach($locations as $location){
                    $fields=$location['money_fields'];$state=$fields['markup']['state']??'missing';
                    $states[$state]=($states[$state]??0)+1;
                    if($state!=='missing'||isset($fields['price'])||isset($fields['amount'])){
                        $money[]=['path'=>$location['path'],'scope'=>$transport['scope'],'fields'=>$fields];
                    }
                }
            }
            if($joined['v1_time_equality_would_reject']){++$recovered;if($top)++$first32['recovered_page_time'];}
            if($top)++$first32['bound'];
            $rows[]=['package_checkpoint_sha256'=>$packageHash,'first_page_sha256'=>$firstHash,'price_checkpoint_sha256'=>$pageHash,
                'package_sha256'=>$facts['raw_reply_sha256'],'input_representation'=>'retained_original_reencoded_not_http_bytes',
                'retained_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',$item['mtime']),'context'=>$facts['context'],
                'v1_time_equality_would_reject'=>$joined['v1_time_equality_would_reject'],
                'transport_count'=>$facts['transport_count'],'transport_scopes'=>$scopes,'details_states'=>$details,
                'markup_states'=>$states,'money_facts'=>$money,'shape_complete'=>$facts['shape_complete'],'shape_issues'=>$facts['shape_issues']];
        }catch(Throwable $e){
            $reason=$e->getMessage();if(!preg_match('/\A[A-Z_]{1,96}\z/D',$reason))$reason='INVALID_RETAINED_DATA';
            $skips[$reason]=($skips[$reason]??0)+1;if($top)$first32['skips'][$reason]=($first32['skips'][$reason]??0)+1;
        }
    }
    ksort($skips);ksort($first32['skips']);
    return ['version'=>2,'state'=>'completed_read_only','scope'=>'retained_originals_not_fresh_search',
        'cutoff_utc'=>gmdate('Y-m-d\TH:i:s\Z',$cutoff),'matched_checkpoint_files'=>count($paths),'examined'=>$examined,
        'reported'=>count($rows),'recovered_page_time'=>$recovered,'first32'=>$first32,'skips'=>$skips,'rows'=>$rows,
        'supplier_calls'=>0,'db_connections'=>0,'db_writes'=>0,'retained_file_writes'=>0,
        'get_flights_calls'=>0,'changeservice_calls'=>0,'calc_calls'=>0,'booking_calls'=>0,'finalPriceReady'=>false];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{
        if(count($argv)!==3||!ctype_digit($argv[2]))throw new RuntimeException('RETAINED_TRANSPORT_ARGUMENT');
        echo json_encode(anytour_original_transport_retained_v2($argv[1],(int)$argv[2]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    }catch(Throwable $e){fwrite(STDERR,"RETAINED_TRANSPORT_V2_FAILED\n");exit(2);}
}
