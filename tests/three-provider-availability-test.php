<?php
declare(strict_types=1);
require __DIR__ . '/../app/integrations/three-provider-availability.php';

$checks=0;
function availability_check(bool $ok): void
{
    global $checks;
    ++$checks;
    if (!$ok) throw new RuntimeException('availability_check_'.$checks);
}

$raw=['hotel'=>'YYYY','flight_outbound_economy'=>'Y','flight_return_economy'=>'R'];
$anex=AnyTourThreeProviderAvailability::fromSearch('anex',$raw);
availability_check($anex['provider']==='anex');
availability_check($anex['hotel']['raw']==='YYYY'&&$anex['hotel']['canonical_state']==='unknown');
availability_check($anex['hotel']['canonical_verified']===false&&$anex['hotel']['evidence_state']==='raw_only');
availability_check($anex['flight_outbound_economy']['raw']==='Y');
availability_check($anex['flight_return_economy']['raw']==='R');
availability_check($anex['offer_availability_verified']===false);
availability_check($anex['selection_eligible']===false&&$anex['booking_eligible']===false);
availability_check($raw===['hotel'=>'YYYY','flight_outbound_economy'=>'Y','flight_return_economy'=>'R']);

foreach(['tourvisor','andromeda'] as $provider){
    $missing=AnyTourThreeProviderAvailability::fromSearch($provider,[
        'hotel'=>null,'flight_outbound_economy'=>null,'flight_return_economy'=>null,
    ]);
    availability_check($missing['provider']===$provider);
    availability_check($missing['hotel']===['raw'=>null,'canonical_state'=>'unknown','canonical_verified'=>false,'evidence_state'=>'missing']);
}

$bad=[
    fn()=>AnyTourThreeProviderAvailability::fromSearch('other',$raw),
    fn()=>AnyTourThreeProviderAvailability::fromSearch('anex',['hotel'=>'Y']),
    fn()=>AnyTourThreeProviderAvailability::fromSearch('anex',$raw+['extra'=>null]),
    fn()=>AnyTourThreeProviderAvailability::fromSearch('anex',array_replace($raw,['hotel'=>''])),
    fn()=>AnyTourThreeProviderAvailability::fromSearch('anex',array_replace($raw,['hotel'=>"Y\nN"])),
    fn()=>AnyTourThreeProviderAvailability::fromSearch('anex',array_replace($raw,['hotel'=>'123'])),
    fn()=>AnyTourThreeProviderAvailability::fromSearch('anex',array_replace($raw,['hotel'=>true])),
    fn()=>AnyTourThreeProviderAvailability::fromSearch('anex',array_replace($raw,['hotel'=>str_repeat('A',41)])),
];
foreach($bad as $case){
    try{$case();availability_check(false);}catch(InvalidArgumentException $e){availability_check(true);}
}

echo 'Three-provider availability evidence: '.$checks." checks passed; supplier/DB/selection=0.\n";
