<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/andromeda-hotel-resolver.php';
require_once __DIR__.'/../app/integrations/andromeda-normalizer.php';
$n=0; function okr($v):void{global $n;++$n;if(!$v)throw new RuntimeException('CHECK_'.$n);}
function badr(callable $f,string $m):void{try{$f();}catch(UnexpectedValueException $e){okr($e->getMessage()===$m);return;}throw new RuntimeException('EXPECTED');}
$v=str_repeat('a',64);
$rows=[
 ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'3414','decision_status'=>'accepted','catalog_hotel_id'=>'900','existing_catalog_hotel_id'=>'900'],
 ['supplier_namespace'=>'operator_5','external_hotel_id'=>'817','decision_status'=>'accepted','catalog_hotel_id'=>'901','existing_catalog_hotel_id'=>'901'],
 ['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>'999','decision_status'=>'rejected','catalog_hotel_id'=>null,'existing_catalog_hotel_id'=>null],
];
$r=AnyTourAndromedaHotelResolver::fromRows($rows,$v); okr($r->version()===$v);
$offer=function($ns,$id){return ['provider'=>'andromeda','supplier_namespace'=>$ns,'external_hotel_id'=>$id,'local_hotel_id'=>null,'selection_enabled'=>false];};
$page=['provider'=>'andromeda','offers'=>[$offer('andromeda_catalog','3414'),$offer('operator_5','817'),$offer('andromeda_catalog','999'),$offer('andromeda_catalog','817')],'selection_enabled'=>false];
$out=$r->apply($page);
okr(array_column($out['offers'],'local_hotel_id')===[900,901,null,null]);
okr($out['mapped_offer_count']===2 && $out['mapping_version']===$v);
okr($out['selection_enabled']===false && $out['offers'][0]['selection_enabled']===false);
okr($page['offers'][0]['local_hotel_id']===null);
badr(fn()=>AnyTourAndromedaHotelResolver::fromRows([$rows[0],$rows[0]],$v),'ANDROMEDA_MAPPING_INVALID');
$x=$rows[0];$x['existing_catalog_hotel_id']='901';
badr(fn()=>AnyTourAndromedaHotelResolver::fromRows([$x],$v),'ANDROMEDA_MAPPING_INVALID');
$x=$rows[0];$x['decision_status']='pending';
badr(fn()=>AnyTourAndromedaHotelResolver::fromRows([$x],$v),'ANDROMEDA_MAPPING_INVALID');
badr(fn()=>AnyTourAndromedaHotelResolver::fromRows([],'bad'),'ANDROMEDA_MAPPING_INVALID');
$x=$page;$x['offers'][0]['local_hotel_id']=900;
badr(fn()=>$r->apply($x),'ANDROMEDA_PROJECTION_INVALID');
$x=$page;$x['selection_enabled']=true;
badr(fn()=>$r->apply($x),'ANDROMEDA_PROJECTION_INVALID');
echo "Andromeda hotel resolver: $n checks passed\n";
