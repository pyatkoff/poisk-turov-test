<?php
declare(strict_types=1);
putenv('INT_INTOURIST_GROUPS_LIBRARY_ONLY=1');
require_once __DIR__.'/../scripts/diagnostics/int_intourist_retained_program_groups_v1.php';

function irpg_ok(bool $value,string $label):void{if(!$value)throw new RuntimeException($label);}
function irpg_offer(string $operator,int $local,string $program,string $tour,string $spo,string $tourLabel,bool $freight=false):array{
    return [
        'operator'=>$operator,'local_hotel_id'=>$local,
        'transport_context'=>[
            'program_ref'=>$program,'program_label'=>'Promo',
            'tour_ref'=>$tour,'tour_label'=>$tourLabel,
            'spo_ref'=>$spo,'spo_label'=>'SPO '.$spo,
            'freight_external'=>$freight,
        ],
        'offer_ref'=>'offer_'.hash('sha256',$operator.'|'.$local.'|'.$program.'|'.$tour.'|'.$spo),
    ];
}
$offers=[
    irpg_offer('Intourist',101,'15','3119','5001','[TR]Y Бодрум/MOW-BJV'),
    irpg_offer('Интурист',102,'15','3119','5002','[TR]Y Бодрум/MOW-BJV'),
    irpg_offer('Intourist',103,'15','3119','5002','[TR]Y Бодрум/MOW-BJV'),
    irpg_offer('Intourist',104,'30','34','6001','[TR] Анталья Чартер/MOW-AYT'),
    irpg_offer('Intourist',105,'30','34','6002','[TR] Анталья Чартер/MOW-AYT'),
    irpg_offer('Intourist',106,'25','172','7001','[TR] Стамбул/MOW-IST'),
    irpg_offer('FUN&SUN',107,'114','78','8001','Turkey Antalya MOW'),
];
$r=irpg_aggregate($offers);
irpg_ok($r['offer_count']===6,'only Intourist');
irpg_ok($r['mapped_count']===6,'mapped');
irpg_ok($r['group_count']===3,'groups');
irpg_ok(count($r['probe_ready_groups'])===1,'one next probe group');
$next=$r['probe_ready_groups'][0];
irpg_ok($next['program_key']==='15'&&$next['tour_key']==='3119','Bodrum selected');
irpg_ok($next['offer_count']===3&&$next['distinct_spo_count']===2,'independent SPO count');
$closed=array_values(array_filter($r['groups'],static fn(array $g):bool=>$g['program_key']==='30'&&$g['tour_key']==='34'))[0];
irpg_ok($closed['distinct_spo_count']===2&&$closed['probe_ready']===false,'30/34 factual but excluded');
$istanbul=array_values(array_filter($r['groups'],static fn(array $g):bool=>$g['program_key']==='25'&&$g['tour_key']==='172'))[0];
irpg_ok($istanbul['distinct_spo_count']===1&&$istanbul['probe_ready']===false,'single SPO blocked');
$encoded=json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
irpg_ok(!str_contains($encoded,'offer_'),'no offer ids exposed');
irpg_ok(irpg_generation(IRPG_TARGET_OPERATION)>0,'generation');
echo "INT_INTOURIST_RETAINED_PROGRAM_GROUPS_OK supplier=0 db=0 server_writes=0\n";
