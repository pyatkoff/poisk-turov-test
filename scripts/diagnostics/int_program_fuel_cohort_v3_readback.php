<?php
declare(strict_types=1);

const PFV3_OPERATION='int-andromeda-intourist-program-fuel-20260923-v2';
const PFV3_PROGRAM='30';
const PFV3_TOUR='34';
const PFV3_EXPECTED_READY=12;
const PFV3_EXPECTED_STORED=193;
const PFV3_MAX_FILES=100000;
const PFV3_MAX_BYTES=3000000;

function pfv3_fail(string $reason): never { throw new RuntimeException($reason); }
function pfv3_json(string $path,int $max=PFV3_MAX_BYTES): ?array {
    if(!is_file($path)||is_link($path)) return null;
    $size=filesize($path);
    if(!is_int($size)||$size<2||$size>$max) return null;
    try {
        $v=json_decode((string)file_get_contents($path),true,96,JSON_THROW_ON_ERROR);
        return is_array($v)?$v:null;
    } catch(Throwable $ignored) { return null; }
}
function pfv3_ref(mixed $v): ?string {
    if(is_int($v)&&$v>0) return (string)$v;
    return is_string($v)&&preg_match('/\A[1-9][0-9]{0,18}\z/D',$v)===1?$v:null;
}
function pfv3_text(mixed $v,int $max=180): ?string {
    if(!is_string($v)) return null;
    $v=trim($v);
    return $v!==''&&strlen($v)<=$max&&!preg_match('/[\x00-\x1F\x7F]/',$v)?$v:null;
}
function pfv3_digest(mixed $v): ?string {
    return is_string($v)&&preg_match('/\A[a-f0-9]{64}\z/D',$v)===1?$v:null;
}
function pfv3_money(mixed $v): ?string {
    if(is_int($v)) $v=(string)$v;
    return is_string($v)&&preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D',$v)===1?$v:null;
}
function pfv3_set_add(array &$set,mixed $value): void {
    $key=is_scalar($value)||$value===null?(string)$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $set[$key]=$value;
}
function pfv3_values(array $set): array {
    ksort($set,SORT_STRING);
    return array_values($set);
}

if(PHP_SAPI!=='cli') pfv3_fail('cli_required');
$home=rtrim((string)getenv('HOME'),'/');
if($home==='') pfv3_fail('home_missing');
$root=$home.'/www/anytoour.ru';
$opDir=$home.'/.anytoour-int-executor/'.PFV3_OPERATION;
$res=pfv3_json($opDir.'/reservation.json',65536);
$terminal=pfv3_json($opDir.'/result.json',1048576);
if(!is_array($res)||($res['operation_id']??null)!==PFV3_OPERATION
    ||!is_array($terminal)||($terminal['operation_id']??null)!==PFV3_OPERATION
    ||($terminal['status']??null)!=='complete') pfv3_fail('operation_receipt_missing');
$from=$res['reserved_at']??null;
$to=filemtime($opDir.'/result.json');
if(!is_int($from)||$from<1||!is_int($to)||$to<$from) pfv3_fail('operation_window_invalid');

$local=$terminal['local_readback'][0]??null;
$scope=is_array($local)?pfv3_digest($local['scopeDigest']??null):null;
if($scope===null
    ||($local['status']??null)!=='complete'
    ||($local['providerOfferCounts']['andromeda']??null)!==PFV3_EXPECTED_STORED
    ||($local['offerCount']??null)!==PFV3_EXPECTED_STORED
    ||($local['storedOfferCount']??null)!==PFV3_EXPECTED_STORED
    ||($local['withheldOfferCount']??null)!==0) pfv3_fail('operation_local_receipt_invalid');

$generation=2100000000-(hexdec(substr(hash('sha256',PFV3_OPERATION),0,6))%1000000);
$searches=$home.'/.anytoour-andromeda/searches';
if(!is_dir($searches)||is_link($searches)) pfv3_fail('searches_invalid');

$retained=[];
$groups=[];
$searchRefs=[];
$fileCount=0;
$pageCount=0;
foreach(new DirectoryIterator($searches) as $entry){
    if($entry->isDot()) continue;
    if(++$fileCount>PFV3_MAX_FILES) pfv3_fail('inventory_too_large');
    if($entry->isLink()||!$entry->isFile()) continue;
    $mtime=$entry->getMTime();
    if($mtime<$from-3||$mtime>$to+3) continue;
    $state=pfv3_json($entry->getPathname());
    if(!is_array($state)||($state['generation']??null)!==$generation) continue;
    $snap=$state['store']['snapshot']??null;
    if(!is_array($snap)||($snap['provider']??null)!=='andromeda'
        ||($snap['generation']??null)!==$generation||!is_array($snap['offers']??null)) continue;
    $ref=$snap['search_ref']??null;
    $page=$snap['page']??null;
    if(!is_string($ref)||preg_match('/\A[a-f0-9]{64}\z/D',$ref)!==1||!is_int($page)||$page<1) continue;
    $searchRefs[$ref]=true;
    ++$pageCount;
    foreach($snap['offers'] as $offer){
        if(!is_array($offer)) continue;
        $offerRef=$offer['offer_ref']??null;
        if(!is_string($offerRef)||preg_match('/\Aoffer_[a-f0-9]{64}\z/D',$offerRef)!==1) continue;
        $digest=hash('sha256',$offerRef);
        $tc=is_array($offer['transport_context']??null)?$offer['transport_context']:[];
        $row=[
            'operator'=>pfv3_text($offer['operator']??null)??'unknown',
            'program_key'=>pfv3_ref($tc['program_ref']??null),
            'program_label'=>pfv3_text($tc['program_label']??null),
            'tour_key'=>pfv3_ref($tc['tour_ref']??null),
            'tour_label'=>pfv3_text($tc['tour_label']??null),
            'spo_key'=>pfv3_ref($tc['spo_ref']??null),
            'freight_external'=>is_bool($tc['freight_external']??null)?$tc['freight_external']:null,
        ];
        if(isset($retained[$digest])&&$retained[$digest]!==$row) pfv3_fail('retained_offer_conflict');
        $retained[$digest]=$row;
        $groupKey=$row['operator'].'|'.($row['program_key']??'-').'|'.($row['tour_key']??'-');
        if(!isset($groups[$groupKey])) $groups[$groupKey]=[
            'operator'=>$row['operator'],'program_key'=>$row['program_key'],'tour_key'=>$row['tour_key'],
            'program_labels'=>[],'tour_labels'=>[],'offer_count'=>0,
        ];
        ++$groups[$groupKey]['offer_count'];
        if($row['program_label']!==null) $groups[$groupKey]['program_labels'][$row['program_label']]=true;
        if($row['tour_label']!==null) $groups[$groupKey]['tour_labels'][$row['tour_label']]=true;
    }
}
if(count($searchRefs)!==1||$pageCount<1||$retained===[]) pfv3_fail('retained_cohort_not_unique');

$dbPath=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
if(!is_file($dbPath)||is_link($dbPath)) pfv3_fail('db_runtime_missing');
require_once $dbPath;
$db=v2_data_db();
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('START TRANSACTION READ ONLY');
try{
    $stateQ=$db->prepare('SELECT latest_complete_refresh_token FROM anytour_offer_scope_state WHERE provider=\'andromeda\' AND scope_sha256=:scope LIMIT 1');
    $stateQ->execute(['scope'=>$scope]);
    $refresh=$stateQ->fetchColumn();
    if(!is_string($refresh)||preg_match('/\A[a-f0-9]{64}\z/D',$refresh)!==1) pfv3_fail('latest_refresh_missing');

    $q=$db->prepare(
        'SELECT offer_ref_digest,display_price,currency,final_price_ready,final_price_verified,payload_json,payload_sha256,checkin,nights,adults,children '
        .'FROM anytour_offers WHERE provider=\'andromeda\' AND scope_sha256=:scope AND last_refresh_token=:refresh '
        .'AND is_active=1 ORDER BY id ASC'
    );
    $q->execute(['scope'=>$scope,'refresh'=>$refresh]);
    $dbRows=$q->fetchAll(PDO::FETCH_ASSOC);
    if(count($dbRows)!==PFV3_EXPECTED_STORED) pfv3_fail('stored_count_mismatch');

    $ready=0;$verified=0;$confirmation=0;$targetStored=0;$targetReady=0;$targetReadyValid=0;$readyNonTarget=0;
    $retainedMissing=0;$payloadHashInvalid=0;$listingStateCounts=[];$programRuleRows=0;$badReadyBoundary=0;
    $ruleAmounts=[];$ruleCurrencies=[];$ruleUnits=[];$ruleDirections=[];$rulePassengers=[];$ruleNativeTotals=[];
    $ruleRelations=[];$ruleFlights=[];$ruleOfferCounts=[];$ruleEvidenceCounts=[];$ruleShas=[];$fxRates=[];$fuelCharges=[];
    $targetListingPrices=[];$targetDisplayPrices=[];$targetFinalPrices=[];

    foreach($dbRows as $row){
        $digest=pfv3_digest($row['offer_ref_digest']??null);
        if($digest===null) pfv3_fail('offer_digest_invalid');
        $ret=$retained[$digest]??null;
        if(!is_array($ret)){++$retainedMissing;continue;}
        $isTarget=strtolower((string)$ret['operator'])==='intourist'
            &&$ret['program_key']===PFV3_PROGRAM&&$ret['tour_key']===PFV3_TOUR;
        if($isTarget) ++$targetStored;

        $raw=(string)($row['payload_json']??'');
        $sha=pfv3_digest($row['payload_sha256']??null);
        if($sha===null||!hash_equals($sha,hash('sha256',$raw))){++$payloadHashInvalid;continue;}
        try{$payload=json_decode($raw,true,96,JSON_THROW_ON_ERROR);}catch(Throwable $e){++$payloadHashInvalid;continue;}
        if(!is_array($payload)){++$payloadHashInvalid;continue;}

        $isReady=(int)$row['final_price_ready']===1;
        $isVerified=(int)$row['final_price_verified']===1;
        if($isReady)++$ready;
        if($isVerified)++$verified;
        $state=$payload['listingPriceState']??'missing';
        $listingStateCounts[$state]=($listingStateCounts[$state]??0)+1;
        if($state==='search_price_confirmation_required')++$confirmation;

        $payloadReady=$payload['listingPriceReady']??null;
        $payloadVerified=$payload['finalPriceVerified']??null;
        if($isReady&&(!$isTarget))++$readyNonTarget;
        if($isTarget&&$isReady)++$targetReady;
        if($isReady){
            if($state!=='final_ready_estimate'||$payloadReady!==true||$payloadVerified!==false
                ||($payload['quoteState']??null)!=='unknown'||($payload['quoteEvidenceDigest']??null)!==null
                ||($payload['selection_state']??null)!=='refresh_required'||($payload['booking_enabled']??null)!==false
                ||$isVerified) ++$badReadyBoundary;
        }
        if(!$isTarget||!$isReady) continue;

        $money=is_array($payload['money']??null)?$payload['money']:[];
        $rule=is_array($money['operator_program_fuel_rule']??null)?$money['operator_program_fuel_rule']:null;
        $fuel=is_array($money['fuel_charge_reported']??null)?$money['fuel_charge_reported']:null;
        $total=is_array($money['search_price_with_surcharge']??null)?$money['search_price_with_surcharge']:null;
        $base=is_array($money['search_price']??null)?$money['search_price']:null;
        if($rule===null||$fuel===null||$total===null||$base===null) continue;
        ++$programRuleRows;
        pfv3_set_add($ruleAmounts,$rule['amount']??null);
        pfv3_set_add($ruleCurrencies,$rule['currency']??null);
        pfv3_set_add($ruleUnits,$rule['unit']??null);
        pfv3_set_add($ruleDirections,$rule['direction_count']??null);
        pfv3_set_add($rulePassengers,$rule['passenger_count']??null);
        pfv3_set_add($ruleNativeTotals,$rule['applied_native_total']??null);
        pfv3_set_add($ruleRelations,$rule['base_relation']??null);
        pfv3_set_add($ruleFlights,$rule['flight_pair']??null);
        pfv3_set_add($ruleOfferCounts,$rule['independent_offer_count']??null);
        pfv3_set_add($ruleEvidenceCounts,$rule['evidence_count']??null);
        pfv3_set_add($ruleShas,$rule['rule_sha256']??null);
        if(is_array($rule['exchange']??null))pfv3_set_add($fxRates,$rule['exchange']['rate']??null);
        pfv3_set_add($fuelCharges,$fuel['amount']??null);
        pfv3_set_add($targetListingPrices,$base['amount']??null);
        pfv3_set_add($targetFinalPrices,$total['amount']??null);
        pfv3_set_add($targetDisplayPrices,pfv3_money((string)$row['display_price']));

        $flightPair=$rule['flight_pair']??null;
        $valid=($rule['schema_version']??null)===1
            &&($rule['key']['operator_family']??null)==='intourist'
            &&($rule['key']['program_key']??null)===PFV3_PROGRAM
            &&($rule['key']['tour_key']??null)===PFV3_TOUR
            &&($rule['amount']??null)==='85.00'
            &&($rule['currency']??null)==='EUR'
            &&($rule['unit']??null)==='per_person_one_way'
            &&($rule['direction_count']??null)===2
            &&($rule['passenger_count']??null)===2
            &&($rule['applied_native_total']??null)==='340.00'
            &&($rule['base_relation']??null)==='excluded'
            &&is_int($rule['independent_offer_count']??null)&&$rule['independent_offer_count']>=2
            &&is_int($rule['evidence_count']??null)&&$rule['evidence_count']>=2
            &&($flightPair['outbound']['flight']??null)==='TK 3003'
            &&($flightPair['return']['flight']??null)==='TK 3006'
            &&($fuel['currency']??null)==='RUB'
            &&($fuel['source']??null)==='operator_program_fuel_rule'
            &&($total['currency']??null)==='RUB'
            &&($total['source']??null)==='derived_search_estimate'
            &&($money['search_price_fuel_relation']??null)==='excluded'
            &&($money['arithmetic_applied']??null)===true
            &&pfv3_money($fuel['amount']??null)!==null
            &&pfv3_money($base['amount']??null)!==null
            &&pfv3_money($total['amount']??null)!==null
            &&pfv3_money((string)$row['display_price'])===pfv3_money($total['amount'])
            &&($payload['listingPrice']??null)===pfv3_money($total['amount']);
        if($valid)++$targetReadyValid;
    }

    ksort($listingStateCounts,SORT_STRING);
    ksort($groups,SORT_STRING);
    $groupList=[];
    foreach($groups as $g){
        $g['program_labels']=array_keys($g['program_labels']);sort($g['program_labels'],SORT_STRING);
        $g['tour_labels']=array_keys($g['tour_labels']);sort($g['tour_labels'],SORT_STRING);
        $groupList[]=$g;
    }

    $out=[
        'schema_version'=>1,'source'=>'int-program-fuel-cohort-v3-readback',
        'target_operation'=>PFV3_OPERATION,'generation'=>$generation,
        'supplier_calls'=>0,'database_reads'=>1,'database_writes'=>0,'filesystem_writes'=>0,
        'scope_digest'=>$scope,'refresh_token_digest'=>hash('sha256',$refresh),
        'retained'=>['page_count'=>$pageCount,'offer_count'=>count($retained),'group_count'=>count($groups)],
        'stored'=>[
            'offer_count'=>count($dbRows),'ready_count'=>$ready,'verified_count'=>$verified,
            'confirmation_count'=>$confirmation,'listing_states'=>$listingStateCounts,
            'retained_missing_count'=>$retainedMissing,'payload_hash_invalid_count'=>$payloadHashInvalid,
        ],
        'target'=>[
            'operator'=>'Intourist','program_key'=>PFV3_PROGRAM,'tour_key'=>PFV3_TOUR,
            'stored_count'=>$targetStored,'ready_count'=>$targetReady,'ready_valid_rule_count'=>$targetReadyValid,
            'program_rule_rows'=>$programRuleRows,'ready_non_target_count'=>$readyNonTarget,
            'bad_ready_boundary_count'=>$badReadyBoundary,
        ],
        'rule'=>[
            'amounts'=>pfv3_values($ruleAmounts),'currencies'=>pfv3_values($ruleCurrencies),
            'units'=>pfv3_values($ruleUnits),'direction_counts'=>pfv3_values($ruleDirections),
            'passenger_counts'=>pfv3_values($rulePassengers),'native_totals'=>pfv3_values($ruleNativeTotals),
            'relations'=>pfv3_values($ruleRelations),'flight_pairs'=>pfv3_values($ruleFlights),
            'independent_offer_counts'=>pfv3_values($ruleOfferCounts),'evidence_counts'=>pfv3_values($ruleEvidenceCounts),
            'rule_sha256'=>pfv3_values($ruleShas),'fx_rates'=>pfv3_values($fxRates),
            'fuel_charge_rub'=>pfv3_values($fuelCharges),
        ],
        'money'=>[
            'base_search_prices'=>pfv3_values($targetListingPrices),
            'final_estimate_prices'=>pfv3_values($targetFinalPrices),
            'stored_display_prices'=>pfv3_values($targetDisplayPrices),
        ],
        'groups'=>$groupList,
    ];

    if($ready!==PFV3_EXPECTED_READY||$verified!==0||$targetStored!==PFV3_EXPECTED_READY
        ||$targetReady!==PFV3_EXPECTED_READY||$targetReadyValid!==PFV3_EXPECTED_READY
        ||$programRuleRows!==PFV3_EXPECTED_READY||$readyNonTarget!==0||$badReadyBoundary!==0
        ||$retainedMissing!==0||$payloadHashInvalid!==0) pfv3_fail('program_fuel_acceptance_failed');

    echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} finally {
    if($db->inTransaction())$db->rollBack();
}
