<?php
declare(strict_types=1);

$project = $argv[1] ?? null;
$root = $argv[2] ?? null;
if (!is_string($project) || !is_dir($project) || !is_string($root) || $root === '') {
    throw new InvalidArgumentException('usage: test PROJECT TEMP_ROOT');
}
require $project . '/v2/api-andromeda-search3-preview.php';
require $project . '/app/integrations/andromeda-saved-pricing-evidence.php';

function evidence_check(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException('SAVED_PRICING_EVIDENCE_FAILED:' . $label);
}

$ref = str_repeat('a', 64);
$source = str_repeat('b', 40);
$now = time();
$created = $now - 2;
$criteria = [
    'TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260922','CHECKIN_END'=>'20260922',
    'ADULT'=>2,'CHILD'=>0,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'CURRENCYINC'=>643,'PAGE'=>1,
];
$row = [
    'id'=>'private-selected-offer','hotelKey'=>3414,'operatorKey'=>5,'isOperatorHotelKey'=>0,
    'price'=>83080,'currency'=>'RUB','currencyKey'=>643,'checkIn'=>'22.09.2026','nights'=>'7',
    'hotel'=>'Fixture hotel','operator'=>'Fixture operator','meal'=>'RO','mealKey'=>1,
    'room'=>'Standard','htplace'=>'DBL','adult'=>'2','child'=>'0',
    'programKey'=>'program_1','tourKey'=>'tour_1','spoKey'=>'spo_1','freightExternal'=>'Y',
];
$resolver = AnyTourAndromedaHotelResolver::fromRows([[
    'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'3414',
    'decision_status'=>'accepted','catalog_hotel_id'=>'900','existing_catalog_hotel_id'=>'900',
]], str_repeat('c',64));
$state=[];
$store=new AnyTourAndromedaOfferStore($state,true);
$store->begin($ref,1,$created);
$page=$store->capture(['PAGE'=>1,'PAGES_COUNT'=>1,'PRICES'=>[$row]],$criteria,$ref,1,$now,$resolver);
$offer=$page['offers'][0];
$context=[
    'provider'=>'andromeda','search_ref'=>$ref,'generation'=>1,'page'=>1,
    'offer_ref'=>$offer['offer_ref'],
];
$allows=static fn(array $candidate):bool => ($candidate['local_hotel_id']??null)===900;
$clock=static fn():int => $now;
$directory=$root.'/searches';
if(!mkdir($directory,0700,true))throw new RuntimeException('mkdir');
try {
    anytour_andromeda_search3_save($directory.'/'.$ref.'-1.json',['status'=>'complete','store'=>$state]);
    anytour_andromeda_search3_save($directory.'/'.$ref.'-auth.json',[
        'created_at'=>$created,'session'=>['sid'=>'private-fixture-session','expires'=>time()+1800],
    ]);
    $raw=['version'=>'1.01','claimDocument'=>[['catalogKey'=>'private-package-key']]];
    $bootstrap=static fn(string $url):array => ['status'=>200,'body'=>json_encode($raw)];
    $flight=static function(string $url,string $post)use($raw):array{
        $reply=$raw;
        $reply['claimDocument'][0]['moneys']=[['money'=>[
            ['currency'=>'RUB','rate'=>'1','isClaimCurrency'=>'true'],
        ]]];
        $reply['variants']=[['transports'=>[['transport'=>[
            ['type'=>'ttAvia','details'=>[['detail'=>[['markup'=>'14265','currency'=>'RUB']]]]],
            ['type'=>'ttAvia','details'=>[['detail'=>[['markup'=>'14265','currency'=>'RUB']]]]],
        ]]]]];
        return ['status'=>200,'body'=>json_encode($reply)];
    };
    anytour_andromeda_capture_saved_package(
        $directory,$context,$source,$allows,$bootstrap,true,$clock
    );
    $receipt=anytour_andromeda_capture_saved_package(
        $directory,$context,$source,$allows,$bootstrap,true,$clock,true,$flight
    );
    evidence_check(($receipt['surcharge']['status']??null)==='complete','estimated capture complete');

    $legacy=anytour_andromeda_read_saved_pricing(
        $directory,$state,$created,$context,$allows,$now
    );
    $read=anytour_andromeda_read_saved_pricing_with_evidence(
        $directory,$state,$created,$context,$allows,$now
    );
    evidence_check(is_array($legacy)&&($legacy['state']??null)==='estimated','legacy estimate readable');
    evidence_check(($read['pricing']??null)===$legacy,'public pricing contract unchanged');
    $meta=$read['evidence_meta']??null;
    evidence_check(is_array($meta),'metadata exposed');
    evidence_check(($meta['source_sha']??null)===$source,'source sha');
    evidence_check(($meta['source_search_ref']??null)===$ref,'search ref');
    evidence_check(($meta['source_offer_ref']??null)===$offer['offer_ref'],'offer ref');
    evidence_check(is_int($meta['observed_at']??null)&&is_int($meta['expires_at']??null),'bounded times');
    evidence_check($meta['observed_at']<=$now&&$now<$meta['expires_at'],'current metadata');
    evidence_check($meta['expires_at']<=$meta['observed_at']+300,'ttl preserved');

    $sidecar=$directory.'/'.$ref.'-'.$created.'-1-'.$offer['offer_ref'].'-surcharge-v1.json';
    $disk=json_decode((string)file_get_contents($sidecar),true,20,JSON_THROW_ON_ERROR);
    $disk['source']='invalid';
    anytour_andromeda_search3_save($sidecar,$disk);
    evidence_check(anytour_andromeda_read_saved_pricing_with_evidence(
        $directory,$state,$created,$context,$allows,$now
    )===null,'invalid exact sidecar remains fail closed');

    echo "ANDROMEDA_SAVED_PRICING_EVIDENCE_OK pricing=1 metadata=1 ttl=1 tamper=1\n";
} finally {
    if(is_dir($root)){
        $it=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach($it as $item)$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());
        @rmdir($root);
    }
}
