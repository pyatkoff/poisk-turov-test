<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/integrations/andromeda-anytour-offer-autosave.php';

function bg_ok(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
function bg_dir():string{
    $root=sys_get_temp_dir().'/bg-fuel-policy-'.bin2hex(random_bytes(5));
    $dir=$root.'/searches'; if(!mkdir($dir,0700,true))throw new RuntimeException('mkdir'); return $dir;
}
function bg_cleanup(string $dir):void{
    $root=dirname($dir);
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}
    rmdir($root);
}
function bg_offer(string $suffix,string $operator,int $local,string $external):array{
    return [
        'offer_ref'=>'offer_'.hash('sha256','bg-'.$suffix),
        'operator'=>$operator,
        'supplier_namespace'=>'andromeda_catalog',
        'external_hotel_id'=>$external,
        'local_hotel_id'=>$local,
        'check_in'=>'2026-10-10','nights'=>7,'adults'=>2,'children'=>1,
        'meal'=>['raw_label'=>'AI','label'=>'AI'],
        'room_raw'=>'Standard','placement_raw'=>'2AD+1CH',
        'price'=>['amount'=>'185125','currency'=>'RUB'],
    ];
}
function bg_state(string $ref,int $created,array $offers):array{
    return [
        'status'=>'complete','search_ref'=>$ref,'generation'=>1,
        'store'=>[
            'version'=>1,'search_ref'=>$ref,'generation'=>1,'created_at'=>$created,'expires_at'=>$created+900,
            'snapshot'=>[
                'provider'=>'andromeda','search_ref'=>$ref,'generation'=>1,'page'=>1,'pages_count'=>1,
                'offers'=>$offers,'rejected'=>[],'selection_enabled'=>false,
            ],
        ],
    ];
}
function bg_request(array $ages):array{
    return ['generation'=>1,'page'=>1,'params'=>[
        'departureId'=>1,'countryId'=>4,'dateFrom'=>'2026-10-10','dateTo'=>'2026-10-10',
        'nightsFrom'=>7,'nightsTo'=>7,'adults'=>2,'childs'=>$ages,'currency'=>'RUB',
    ]];
}

$adultChild=AnyTourBiblioFuelOwnerPolicyV1::forParty('Biblio Globus',[
    'adults'=>2,'children'=>1,'child_ages'=>[5],
]);
bg_ok(is_array($adultChild),'biblio policy missing');
bg_ok($adultChild['amount']==='0.00'&&$adultChild['base_relation']==='included','fuel zero/included');
bg_ok($adultChild['adult_equivalent_count']===3&&$adultChild['infant_count']===0,'child2plus adult rate');
bg_ok($adultChild['infant_pricing_state']==='not_applicable','adult child got infant state');

$infant=AnyTourBiblioFuelOwnerPolicyV1::forParty('Библио-Глобус',[
    'adults'=>2,'children'=>1,'child_ages'=>[1],
]);
bg_ok($infant['adult_equivalent_count']===2&&$infant['infant_count']===1,'infant folded into adult fuel');
bg_ok($infant['infant_pricing_state']==='separate_unknown','infant missing separate state');
bg_ok(AnyTourBiblioFuelOwnerPolicyV1::forParty('FUN&SUN',[
    'adults'=>2,'children'=>1,'child_ages'=>[5],
])===null,'policy leaked to other operator');

$dir=bg_dir();
try{
    $ref=hash('sha256','bg-cohort');$created=time()-30;
    $state=bg_state($ref,$created,[
        bg_offer('biblio','Biblio Globus',101,'100'),
        bg_offer('funsun','FUN&SUN',102,'101'),
    ]);
    file_put_contents($dir.'/'.$ref.'-1.json',json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    chmod($dir.'/'.$ref.'-1.json',0600);

    $mapping=static function(array $offers):array{
        $out=[];foreach($offers as $offer){
            $out[json_encode([$offer['supplier_namespace'],(string)$offer['external_hotel_id']],JSON_THROW_ON_ERROR)]=$offer['local_hotel_id'];
        }return $out;
    };
    $canonical=static fn(array $ids):array=>array_combine($ids,array_map(static fn(int $id):int=>$id+900,$ids));
    $pricing=static fn(array $state,int $created,array $offer,array $current):?array=>null;
    $save=static function(string $path,array $value):bool{
        $ok=file_put_contents($path,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)!==false;
        if($ok)chmod($path,0600);return $ok;
    };
    $ingests=[];
    $ingest=static function(string $provider,array $search,array $rows,DateTimeImmutable $at)use(&$ingests):array{
        $ingests[]=$rows;return ['status'=>'completed','provider'=>$provider,'row_count'=>count($rows)];
    };

    $result=AnyTourAndromedaOfferAutosaveV1::consume(
        bg_request([5]),$dir,$ref,1,new DateTimeImmutable('now',new DateTimeZone('UTC')),
        $mapping,$canonical,$pricing,$save,$ingest
    );
    bg_ok($result['published']===true,'cohort not published');
    bg_ok($result['readyOfferCount']===0&&$result['confirmationRequiredOfferCount']===2,'policy promoted final price');
    bg_ok(count($ingests)===1&&count($ingests[0])===2,'cohort rows lost');

    $byOperator=[];
    foreach($ingests[0] as $row){
        $dto=$row['dto'];$byOperator[$dto['operator']['raw']]=$dto;
        bg_ok($dto['finalPriceReady']===false&&$dto['finalPrice']===null,'confirmation promoted');
        bg_ok($dto['final_price_verified']===false&&$dto['selection_state']==='disabled'&&$dto['booking_enabled']===false,'authority promoted');
        bg_ok($dto['price']==='185125'&&$dto['currency']==='RUB','supplier base changed');
    }
    $bg=$byOperator['Biblio Globus'];
    bg_ok($bg['money']['fuel_charge_reported']===['amount'=>'0.00','currency'=>'RUB','source'=>'biblio_owner_policy'],'BG fuel fact missing');
    bg_ok($bg['money']['search_price_fuel_relation']==='included','BG relation not included');
    bg_ok(($bg['money']['operator_fuel_policy']['source']??null)==='owner_policy','BG provenance missing');
    bg_ok(($bg['money']['operator_fuel_policy']['adult_equivalent_count']??null)===3,'BG child classification lost');
    bg_ok(!array_key_exists('search_price_with_surcharge',$bg['money'])&&$bg['money']['arithmetic_applied']===false,'BG zero caused arithmetic');

    $fun=$byOperator['FUN&SUN'];
    bg_ok($fun['money']['fuel_charge_reported']===null&&$fun['money']['search_price_fuel_relation']==='unknown','BG policy leaked to FUN&SUN');
    bg_ok(!array_key_exists('operator_fuel_policy',$fun['money']),'BG metadata leaked');

    $again=AnyTourAndromedaOfferAutosaveV1::consume(
        bg_request([5]),$dir,$ref,1,new DateTimeImmutable('now',new DateTimeZone('UTC')),
        $mapping,$canonical,$pricing,$save,$ingest
    );
    bg_ok($again['reason']==='already_published'&&count($ingests)===1,'owner policy checkpoint not idempotent');
}finally{bg_cleanup($dir);}

echo "PASS Biblio fuel owner policy; supplier=0 DB=0\n";
