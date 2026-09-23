<?php
declare(strict_types=1);
putenv('INT_FUNSUN_FX_SEED_LIBRARY_ONLY=1');
require_once __DIR__ . '/../scripts/diagnostics/int_funsun_direction_fx_seed_v1.php';
require_once __DIR__ . '/../app/integrations/operator-program-fuel-fx-evidence.php';

$checks=0;
$assert=static function(bool $ok,string $label)use(&$checks):void{
    ++$checks;
    if(!$ok)throw new RuntimeException('ASSERT '.$label);
};
$root=sys_get_temp_dir().'/anytour-fxseed-'.bin2hex(random_bytes(6));
$ops=$root.'/ops';$searches=$root.'/searches';
mkdir($ops,0700,true);mkdir($searches,0700,true);
$cleanup=static function(string $path)use(&$cleanup):void{
    if(!file_exists($path)&&!is_link($path))return;
    if(is_dir($path)&&!is_link($path)){foreach(scandir($path)?:[] as $n){if($n==='.'||$n==='..')continue;$cleanup($path.'/'.$n);}rmdir($path);return;}
    unlink($path);
};
$receipt=static function(string $op,int $sample,string $spo,string $offer,string $rate):array{
    return [
        'operation_id'=>$op,'mode'=>'program-fuel-probe','status'=>'complete',
        'database_writes'=>0,'production_unchanged'=>true,
        'program_fuel_probe'=>[
            'source'=>'int-andromeda-program-getflights-probe-v1','status'=>'complete',
            'final_price_verified'=>false,'database_writes'=>0,'mapping_writes'=>0,
            'supplier_calls'=>[
                'booking'=>0,'calc'=>0,'changeservice'=>0,'get_flights'=>1,'login_attempted'=>true,'package'=>1,
            ],
            'target'=>[
                'operator_family'=>'funsun','program_key'=>'114','tour_key'=>'78',
                'sample_distinct_spo_index'=>$sample,'spo_key'=>$spo,
                'retained_distinct_spo_count'=>193,'mapped_local_hotel'=>true,
                'selected_offer_ref_sha256'=>hash('sha256',$offer),
            ],
            'search_surcharge_estimate'=>[
                'provider'=>'andromeda','final_price_verified'=>false,
                'operator_currency_rates_reported'=>[
                    ['currency'=>'EUR','is_claim_currency'=>true,'rate'=>'1','source'=>'andromeda_claim_money'],
                    ['currency'=>'RUB','is_claim_currency'=>false,'rate'=>$rate,'source'=>'andromeda_claim_money'],
                ],
            ],
        ],
    ];
};
$put=static function(string $ops,string $op,array $value,int $mtime):void{
    $dir=$ops.'/'.$op;mkdir($dir,0700,true);
    $path=$dir.'/result.json';
    file_put_contents($path,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    touch($path,$mtime);
};
try{
    $now=1790184000;
    $a='int-andromeda-funsun-fx-probe-a-v1';
    $b='int-andromeda-funsun-fx-probe-b-v1';
    $put($ops,$a,$receipt($a,0,'40008514','offer-a','102.7'),$now-120);
    $put($ops,$b,$receipt($b,1,'40167253','offer-b','102.7'),$now-60);

    $seed=ifx_seed($ops,$searches,$a,$b,$now);
    $assert(($seed['status']??null)==='seeded_verified','seed status');
    $assert(($seed['rate']??null)==='102.7','seed rate');
    $assert(($seed['independent_probe_count']??null)===2,'two independent probes');
    $assert(($seed['supplier_calls']??null)===0&&($seed['database_writes']??null)===0,'seed supplier/db zero');
    $assert(($seed['fuel_rule_writes']??null)===0&&($seed['final_price_verified']??null)===false,'no fuel/final authority');

    $direction=['operator_family'=>'fun_and_sun','market'=>'departure:1','destination'=>'country:4'];
    $digest=AnyTourOperatorFuelRuleEvidenceV1::directionDigest($direction);
    $path=$searches.'/operator-direction-fx-v1-'.$digest.'.json';
    $stored=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
    $assert(($stored['source']??null)==='terminal_program_fuel_probes','stored source');
    $assert(!array_key_exists('amount',$stored)&&!array_key_exists('base_relation',$stored),'no fuel fields stored');
    $assert(($stored['probe_result_sha256']??[])===array_values(array_unique($stored['probe_result_sha256']??[])),'distinct result evidence');

    $fx=AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection(
        $searches,'FUN&SUN',['market'=>'departure:1','destination'=>'country:4'],$now
    );
    $assert(is_array($fx)&&($fx['rate']??null)==='102.7','selector consumes fx-only receipt');
    $assert(($fx['scope_sha256']??null)===$digest,'selector binds exact direction');
    $assert(!array_key_exists('amount',$fx)&&!array_key_exists('base_relation',$fx),'selector exposes no fuel facts');
    $assert(AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection(
        $searches,'FUN&SUN',['market'=>'departure:1','destination'=>'country:7'],$now
    )===null,'wrong destination blocked');
    $assert(AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection(
        $searches,'Интурист',['market'=>'departure:1','destination'=>'country:4'],$now
    )===null,'wrong operator blocked');
    $assert(AnyTourOperatorProgramFuelFxEvidenceV1::latestForDirection(
        $searches,'FUN&SUN',['market'=>'departure:1','destination'=>'country:4'],$now+90000
    )===null,'stale reference blocked');

    $again=ifx_seed($ops,$searches,$a,$b,$now);
    $assert(($again['write_state']??null)==='already_present','same evidence idempotent');

    $badRoot=$root.'/bad';$badOps=$badRoot.'/ops';$badSearch=$badRoot.'/searches';
    mkdir($badOps,0700,true);mkdir($badSearch,0700,true);
    $put($badOps,$a,$receipt($a,0,'40008514','offer-a','102.7'),$now-120);
    $put($badOps,$b,$receipt($b,1,'40167253','offer-b','103.1'),$now-60);
    try{ifx_seed($badOps,$badSearch,$a,$b,$now);$assert(false,'conflict must fail');}
    catch(RuntimeException $e){$assert($e->getMessage()==='probe_fx_conflict','conflict fail closed');}

    echo 'INT_FUNSUN_DIRECTION_FX_SEED_OK checks='.$checks." supplier=0 db=0 fuel_rule_writes=0 final_verified=false\n";
}finally{$cleanup($root);}
