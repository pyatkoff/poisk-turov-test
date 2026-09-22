<?php
declare(strict_types=1);

const SEED_OPERATION='int-andromeda-program30-tour34-fuel-seed-20260922-v1';
const PROBE_V1='int-andromeda-program30-tour34-getflights-20260922-v1';
const PROBE_V2='int-andromeda-program30-tour34-getflights-20260922-v2';

function seed_fail(string $reason): never {
    throw new RuntimeException($reason);
}
function seed_json(string $path,int $max=65536): array {
    if(!is_file($path)||is_link($path))seed_fail('probe_missing');
    $size=filesize($path);
    if(!is_int($size)||$size<2||$size>$max)seed_fail('probe_invalid');
    $v=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
    if(!is_array($v))seed_fail('probe_invalid');
    return $v;
}
function seed_money(mixed $v): string {
    if(is_int($v))$v=(string)$v;
    if(!is_string($v)||preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,8})?\z/D',$v)!==1)seed_fail('money_invalid');
    return $v;
}
function seed_rate(array $result): string {
    $rows=$result['search_surcharge_estimate']['operator_currency_rates_reported']??null;
    if(!is_array($rows)||!array_is_list($rows))seed_fail('fx_missing');
    $by=[];
    foreach($rows as $row){
        if(!is_array($row))continue;
        $currency=$row['currency']??null;$rate=$row['rate']??null;
        if(is_string($currency)&&is_string($rate))$by[$currency]=seed_money($rate);
    }
    if(($by['EUR']??null)!=='1'||!isset($by['RUB'])||(float)$by['RUB']<=0)seed_fail('fx_invalid');
    return $by['RUB'];
}
function seed_probe(array $r,string $operation,string $source): array {
    if(($r['schema_version']??null)!==1||($r['source']??null)!==$source||($r['operation']??null)!==$operation
        ||($r['status']??null)!=='complete'||($r['outcome']??null)!=='targeted_get_flights_observed_no_selection_applied'
        ||($r['operation_replayed']??null)!==false||($r['final_price_verified']??null)!==false)seed_fail('probe_contract');
    $calls=$r['supplier_calls']??null;
    if(!is_array($calls)||($calls['package']??null)!==1||($calls['get_flights']??null)!==1
        ||($calls['changeservice']??null)!==0||($calls['calc']??null)!==0||($calls['booking']??null)!==0
        ||($r['database_reads']??null)!==0||($r['database_writes']??null)!==0||($r['mapping_writes']??null)!==0)seed_fail('probe_authority');
    $t=$r['target']??null;
    if(!is_array($t)||($t['operator']??null)!=='Intourist'||($t['program_key']??null)!=='30'||($t['tour_key']??null)!=='34'
        ||($t['retained_group_offer_count']??null)!==12||($r['package_freight_external']??null)!==0
        ||!is_string($t['selected_offer_ref_sha256']??null)||preg_match('/\A[a-f0-9]{64}\z/D',$t['selected_offer_ref_sha256'])!==1
        ||!is_string($t['spo_key']??null)||$t['spo_key']==='')seed_fail('target_invalid');

    $fuel=$r['fuel_surcharges_reported']??null;
    if(!is_array($fuel)||count($fuel)!==2)seed_fail('fuel_pair');
    $routes=[];
    foreach($fuel as $row){
        if(!is_array($row)||!in_array((string)($row['route_index']??''),['0','1'],true)
            ||seed_money($row['amount']??null)!=='170'||($row['currency']??null)!=='EUR'
            ||($row['required_reported']??null)!==true||($row['packet_reported']??null)!==false)seed_fail('fuel_row');
        $routes[(string)$row['route_index']]=true;
    }
    if(count($routes)!==2)seed_fail('fuel_routes');

    $sel=$r['cheapest_selection']??null;
    if(!is_array($sel)||($sel['candidate_counts']??null)!==[4,4]||!is_array($sel['selected_flights']??null))seed_fail('flight_selection');
    $flights=[];
    foreach($sel['selected_flights'] as $row){
        if(!is_array($row)||!in_array((string)($row['direction']??''),['0','1'],true))seed_fail('flight_row');
        $nums=$row['flight_numbers']??null;$markup=$row['markup']??null;
        if(!is_array($nums)||count($nums)!==1||!is_array($markup)
            ||seed_money($markup['amount']??null)!=='340.00'||($markup['currency']??null)!=='EUR')seed_fail('flight_markup');
        $flights[(string)$row['direction']]=$nums[0];
    }
    if(($flights['0']??null)!=='TK 3003'||($flights['1']??null)!=='TK 3006')seed_fail('flight_pair');

    $rate=seed_rate($r);
    $listing=$t['selected_listing_price']??null;
    if(!is_array($listing)||($listing['currency']??null)!=='RUB')seed_fail('listing_invalid');
    seed_money($listing['amount']??null);

    return [
        'operation'=>$operation,
        'offer_ref_digest'=>$t['selected_offer_ref_sha256'],
        'spo_key'=>$t['spo_key'],
        'listing_amount'=>$listing['amount'],
        'rate'=>$rate,
        'flight_pair'=>['outbound'=>['flight'=>'TK 3003'],'return'=>['flight'=>'TK 3006']],
        'evidence'=>[
            'operation'=>$operation,
            'target'=>['operator'=>'Intourist','program_key'=>'30','tour_key'=>'34','spo_key'=>$t['spo_key']],
            'fuel'=>['route0'=>'170 EUR','route1'=>'170 EUR','required'=>true,'packet'=>false],
            'flight_pair'=>['TK 3003','TK 3006'],
            'rate'=>['EUR'=>'1','RUB'=>$rate],
            'listing'=>['amount'=>$listing['amount'],'currency'=>'RUB'],
        ],
    ];
}
function seed_atomic_writer(string $path,array $value): bool {
    $bytes=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $tmp=$path.'.seed-'.bin2hex(random_bytes(8));
    $f=fopen($tmp,'x');
    if(!$f)seed_fail('stage_open');
    try{
        chmod($tmp,0600);
        if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f))seed_fail('stage_write');
        if(function_exists('fsync')&&!fsync($f))seed_fail('stage_sync');
    }finally{fclose($f);}
    if(hash_file('sha256',$tmp)!==hash('sha256',$bytes))seed_fail('stage_hash');
    if(!rename($tmp,$path))seed_fail('stage_rename');
    chmod($path,0600);
    return true;
}
function seed_install(string $source,string $destination): void {
    if(!is_file($source)||is_link($source)||file_exists($destination)||is_link($destination))seed_fail('install_precondition');
    $bytes=file_get_contents($source);
    if(!is_string($bytes)||strlen($bytes)<2)seed_fail('install_source');
    $tmp=$destination.'.install-'.bin2hex(random_bytes(8));
    $f=fopen($tmp,'x');if(!$f)seed_fail('install_open');
    try{
        chmod($tmp,0600);
        if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f))seed_fail('install_write');
        if(function_exists('fsync')&&!fsync($f))seed_fail('install_sync');
    }finally{fclose($f);}
    if(hash_file('sha256',$tmp)!==hash('sha256',$bytes))seed_fail('install_hash');
    if(!rename($tmp,$destination))seed_fail('install_rename');
    chmod($destination,0600);
    if(hash_file('sha256',$destination)!==hash('sha256',$bytes))seed_fail('install_readback');
}

if(PHP_SAPI!=='cli')seed_fail('cli_required');
$root=realpath(getcwd());
if(!$root||basename($root)!=='anytoour.ru')seed_fail('project_invalid');
$private=dirname($root,2).'/.anytoour-andromeda';
$searches=$private.'/searches';
if(!is_dir($private)||is_link($private)||!is_dir($searches)||is_link($searches))seed_fail('private_invalid');
$operationDir=$private.'/'.SEED_OPERATION;
if(file_exists($operationDir)||is_link($operationDir))seed_fail('operation_exists_no_replay');
if(!mkdir($operationDir,0700))seed_fail('operation_reserve');
file_put_contents($operationDir.'/reservation.json',json_encode([
    'operation'=>SEED_OPERATION,'feature_source'=>'1ef85df9d6c1c56f26dc5d76b8bec5f293e7483c','created_at'=>time()
],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
chmod($operationDir.'/reservation.json',0600);

$out=['schema_version'=>1,'source'=>'intourist-program30-tour34-fuel-seed-v1','operation'=>SEED_OPERATION,
    'status'=>'unknown_no_replay','supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0];
try{
    $v1Path=$private.'/'.PROBE_V1.'/result.json';
    $v2Path=$private.'/'.PROBE_V2.'/result.json';
    $a=seed_probe(seed_json($v1Path),PROBE_V1,'andromeda-program30-tour34-getflights-observe-v1');
    $b=seed_probe(seed_json($v2Path),PROBE_V2,'andromeda-program30-tour34-getflights-observe-v2');
    if($a['offer_ref_digest']===$b['offer_ref_digest']||$a['spo_key']===$b['spo_key'])seed_fail('independence_missing');

    $staging=$operationDir.'/searches';
    if(!mkdir($staging,0700))seed_fail('staging_dir');
    $samples=[[$a,$v1Path],[$b,$v2Path]];
    $stagePath=null;
    foreach($samples as [$sample,$path]){
        $observed=filemtime($path);
        if(!is_int($observed)||$observed<1)seed_fail('probe_mtime');
        $evidence=hash('sha256',json_encode($sample['evidence'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        $obs=[
            'key'=>['operator_family'=>'intourist','program_key'=>'30','tour_key'=>'34'],
            'unit'=>'per_person_one_way','amount'=>'85','currency'=>'EUR','direction_count'=>2,
            'base_relation'=>'excluded','flight_pair'=>$sample['flight_pair'],
            'offer_ref_digest'=>$sample['offer_ref_digest'],'evidence_sha256'=>$evidence,
            'source'=>'andromeda_get_flights','observed_at'=>$observed,'expires_at'=>$observed+2592000,
            'exchange'=>['from'=>'EUR','to'=>'RUB','rate'=>$sample['rate'],'observed_at'=>$observed,
                'expires_at'=>$observed+86400,'evidence_sha256'=>hash('sha256','fx|'.$sample['operation'].'|'.$sample['rate'])],
        ];
        $receipt=AnyTourOperatorProgramFuelRegistryV1::append($staging,$obs,'seed_atomic_writer');
        $stagePath=$receipt['path']??null;
    }
    if(!is_string($stagePath)||!is_file($stagePath)||dirname($stagePath)!==$staging)seed_fail('stage_registry_missing');

    $now=time();
    $synthetic=[
        'provider'=>'andromeda','operator'=>'Intourist','offer_ref'=>'offer_'.str_repeat('d',64),
        'adults'=>2,'children'=>0,
        'transport_context'=>['program_ref'=>'30','tour_ref'=>'34'],
        'price'=>['amount'=>'98415','currency'=>'RUB'],
    ];
    $party=['adults'=>2,'children'=>0,'child_ages'=>[]];
    $before=AnyTourOperatorProgramFuelRegistryV1::priceForOffer($staging,$synthetic,$party,$now);
    if(!is_array($before)||($before['program_rule']['amount']??null)!=='85.00'
        ||($before['program_rule']['applied_native_total']??null)!=='340.00'
        ||($before['program_rule']['independent_offer_count']??null)!==2
        ||($before['program_rule']['flight_pair']['outbound']['flight']??null)!=='TK 3003'
        ||($before['program_rule']['flight_pair']['return']['flight']??null)!=='TK 3006')seed_fail('stage_rule_invalid');

    $destination=$searches.'/'.basename($stagePath);
    seed_install($stagePath,$destination);
    $after=AnyTourOperatorProgramFuelRegistryV1::priceForOffer($searches,$synthetic,$party,$now);
    if($after!==$before)seed_fail('installed_rule_changed');

    $out['status']='seeded_verified';
    $out['rule']=[
        'operator_family'=>'intourist','program_key'=>'30','tour_key'=>'34',
        'amount'=>'85.00','currency'=>'EUR','unit'=>'per_person_one_way','direction_count'=>2,
        'sample_count'=>2,'flight_pair'=>['TK 3003','TK 3006'],
        'latest_fx_rate'=>$after['program_rule']['exchange']['rate']??null,
        'two_adult_native_total'=>$after['program_rule']['applied_native_total']??null,
        'two_adult_rub_surcharge'=>$after['party_surcharge']['amount']??null,
        'evidence_sha256'=>$after['program_rule']['evidence_sha256']??null,
        'registry_sha256'=>hash_file('sha256',$destination),
    ];
}catch(Throwable $e){
    $m=$e->getMessage();
    $out['reason']=is_string($m)&&preg_match('/\A[A-Za-z0-9_.:-]{1,96}\z/D',$m)===1?$m:'seed_failed';
}
file_put_contents($operationDir.'/result.json',json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
chmod($operationDir.'/result.json',0600);
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
if($out['status']!=='seeded_verified')exit(1);
