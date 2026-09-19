<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/anex-local-offer-demand.php';
function ck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function reject(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException){return;}throw new RuntimeException($label);}
$base=[
 'departure_id'=>'1','country_id'=>'4','region_id'=>'20','departure_date'=>'2026-09-22','nights'=>'7','adults'=>'2',
 'children_count'=>'0','child_ages_signature'=>'','searches'=>'107','observations'=>'562','last_seen'=>'2026-09-19 20:16:12',
];
$child=array_replace($base,['region_id'=>null,'departure_date'=>'2026-09-23','children_count'=>'2','child_ages_signature'=>'7,3','searches'=>'4']);
$out=AnyTourAnexLocalOfferDemandV1::normalizeRows([$base,$base,$child],10);
ck(count($out)===2,'dedupe');
ck($out[0]['departureId']===1&&$out[0]['countryId']===4&&$out[0]['regionId']===20&&$out[0]['childAges']===[],'base');
ck($out[1]['regionId']===null&&$out[1]['childAges']===[3,7],'child-ages-sorted');
ck(AnyTourAnexLocalOfferDemandV1::normalizeRows([$base,$child],1)===[array_replace($out[0])],'limit');
reject(fn()=>AnyTourAnexLocalOfferDemandV1::normalizeRows([array_replace($base,['children_count'=>'1','child_ages_signature'=>''])],10),'age-count');
reject(fn()=>AnyTourAnexLocalOfferDemandV1::normalizeRows([array_replace($base,['departure_date'=>'2026-02-30'])],10),'date');
reject(fn()=>AnyTourAnexLocalOfferDemandV1::normalizeRows([$base],0),'bad-limit');
echo "ANEX_LOCAL_OFFER_DEMAND_OK exact=1 dedupe=1 children=1 invalid=1\n";
