<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/operator-program-fuel-registry.php';

function pfr_ok(bool $v,string $m):void{if(!$v)throw new RuntimeException('PFR:'.$m);}
function pfr_write(string $path,array $value):bool{
    return file_put_contents($path,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)!==false;
}
function pfr_offer(string $program='30',string $tour='34',string $price='98415'):array{
    return [
        'provider'=>'andromeda','operator'=>'Intourist','offer_ref'=>'offer_'.str_repeat('a',64),
        'adults'=>2,'children'=>0,
        'transport_context'=>[
            'program_ref'=>$program,'program_label'=>'Промо Цена',
            'tour_ref'=>$tour,'tour_label'=>'[TR] Анталья Чартер/MOW-AYT',
            'spo_ref'=>'20531660','spo_label'=>'MOW-26837-AYT PROMO',
            'freight_external'=>false,
        ],
        'price'=>['amount'=>$price,'currency'=>'RUB','kind'=>'offer','fees'=>'unknown','final'=>false],
    ];
}
function pfr_obs(string $offerSalt,string $evidenceSalt,int $observed,string $rate,string $source='andromeda_get_flights'):array{
    return [
        'key'=>['operator_family'=>'intourist','program_key'=>'30','tour_key'=>'34'],
        'unit'=>'per_person_one_way','amount'=>'85','currency'=>'EUR','direction_count'=>2,
        'base_relation'=>'excluded',
        'flight_pair'=>['outbound'=>['flight'=>'TK 3003'],'return'=>['flight'=>'TK 3006']],
        'offer_ref_digest'=>hash('sha256',$offerSalt),
        'evidence_sha256'=>hash('sha256',$evidenceSalt),
        'source'=>$source,'observed_at'=>$observed,'expires_at'=>$observed+86400,
        'exchange'=>['from'=>'EUR','to'=>'RUB','rate'=>$rate,'observed_at'=>$observed,
            'expires_at'=>$observed+86400,'evidence_sha256'=>hash('sha256','fx-'.$evidenceSalt)],
    ];
}

$dir=sys_get_temp_dir().'/program-fuel-'.bin2hex(random_bytes(5)).'/searches';
mkdir($dir,0700,true);
AnyTourOperatorProgramFuelRegistryV1::append($dir,pfr_obs('offer-a','evidence-a',1000,'99.26'),'pfr_write');
AnyTourOperatorProgramFuelRegistryV1::append($dir,pfr_obs('offer-b','evidence-b',1100,'99.49'),'pfr_write');

$offer=pfr_offer();
$two=['adults'=>2,'children'=>0,'child_ages'=>[]];
$r=AnyTourOperatorProgramFuelRegistryV1::priceForOffer($dir,$offer,$two,1200);
pfr_ok(is_array($r),'two adults applies');
pfr_ok($r['party_surcharge']['amount']==='33826.60','latest fx two adults');
pfr_ok($r['search_price_with_surcharge']['amount']==='132241.60','two adults total');
pfr_ok($r['program_rule']['amount']==='85.00'&&$r['program_rule']['unit']==='per_person_one_way','85 normalized');
pfr_ok($r['program_rule']['passenger_count']===2&&$r['program_rule']['direction_count']===2,'2x2 scale');
pfr_ok($r['program_rule']['applied_native_total']==='340.00','340 derived not stored base');
pfr_ok($r['program_rule']['flight_pair']===['outbound'=>['flight'=>'TK 3003'],'return'=>['flight'=>'TK 3006']],'flight corroboration');
pfr_ok($r['program_rule']['exchange']['rate']==='99.49','latest fresh fx');

$child=['adults'=>2,'children'=>1,'child_ages'=>[5]];
$childOffer=pfr_offer('30','34','100000');$childOffer['children']=1;
$c=AnyTourOperatorProgramFuelRegistryV1::priceForOffer($dir,$childOffer,$child,1200);
pfr_ok($c['program_rule']['passenger_count']===3,'child2plus counts');
pfr_ok($c['party_surcharge']['amount']==='50739.90','3 passengers x2 x85');
pfr_ok($c['search_price_with_surcharge']['amount']==='150739.90','child total');

$infant=['adults'=>2,'children'=>1,'child_ages'=>[1]];
pfr_ok(AnyTourOperatorProgramFuelRegistryV1::priceForOffer($dir,$offer,$infant,1200)===null,'infant separate');
pfr_ok(AnyTourOperatorProgramFuelRegistryV1::priceForOffer($dir,pfr_offer('31','34'),$two,1200)===null,'program mismatch');
pfr_ok(AnyTourOperatorProgramFuelRegistryV1::priceForOffer($dir,pfr_offer('30','42'),$two,1200)===null,'tour mismatch');

$dir2=sys_get_temp_dir().'/program-fuel-conflict-'.bin2hex(random_bytes(5)).'/searches';
mkdir($dir2,0700,true);
AnyTourOperatorProgramFuelRegistryV1::append($dir2,pfr_obs('offer-a','evidence-a',1000,'99.26'),'pfr_write');
$bad=pfr_obs('offer-b','evidence-b',1100,'99.49');$bad['flight_pair']['return']['flight']='TK 9999';
AnyTourOperatorProgramFuelRegistryV1::append($dir2,$bad,'pfr_write');
pfr_ok(AnyTourOperatorProgramFuelRegistryV1::priceForOffer($dir2,$offer,$two,1200)===null,'flight conflict holds');

foreach([$dir,$dir2] as $d){foreach(glob($d.'/*')?:[] as $p)unlink($p);rmdir($d);rmdir(dirname($d));}
echo "PASS exact operator program fuel registry\n";
