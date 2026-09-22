<?php
declare(strict_types=1);

require_once __DIR__.'/../scripts/diagnostics/hotel_match_tv_samo_side4_baseline_current_v10.php';

$baseline=[
    ['tv_hotel_id'=>'9337','samo_hotel_id'=>'343342','tv_name'=>'SIDE YESILOZ HOTEL','samo_name'=>'Side Yesiloz Hotel',
        'name_exact'=>true,'operator_overlap'=>['operators'=>['biblio','funsun']],'hotel_evidence_class'=>'exact'],
    ['tv_hotel_id'=>'28660','samo_hotel_id'=>'2000023127','tv_name'=>'SELENIUM HOTEL','samo_name'=>'Selenium Hotel',
        'name_exact'=>true,'operator_overlap'=>['operators'=>['biblio']],'hotel_evidence_class'=>'exact'],
    ['tv_hotel_id'=>'37532','samo_hotel_id'=>'2000029524','tv_name'=>'SIRMA HOTEL','samo_name'=>'Sirma Hotel',
        'name_exact'=>true,'operator_overlap'=>['operators'=>[]],'hotel_evidence_class'=>'exact'],
];
$pairs=hma7_baseline_pairs(['hotel_candidates'=>$baseline]);
if(count($pairs)!==3) throw new RuntimeException('count');
$common3=array_values(array_filter($pairs,fn($c)=>$c['tier']==='baseline_exact_common3'));
if(count($common3)!==1||$common3[0]['tv_hotel_id']!=='9337'||$common3[0]['samo_hotel_id']!=='343342') throw new RuntimeException('membership');
$tiers=[];foreach($pairs as $pair)$tiers[$pair['tv_hotel_id']]=$pair['tier'];
if(($tiers['9337']??null)!=='baseline_exact_common3'||($tiers['28660']??null)!=='baseline_exact_biblio_only'||($tiers['37532']??null)!=='baseline_exact_no_common3') throw new RuntimeException('tiers');
echo "ok\n";
