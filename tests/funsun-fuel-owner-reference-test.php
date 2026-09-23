<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/funsun-fuel-owner-reference.php';

function fsassert(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function amount(string $destination, string $flight, string $date, ?string $carrier=null): ?string {
    $row=AnyTourFunSunFuelOwnerReferenceV1::leg('FUN&SUN',$destination,$flight,$date,$carrier);
    return is_array($row) ? ($row['amount'] ?? null) : null;
}

// Antalya AZUR weekday/date boundaries.
fsassert(amount('TR-AYT','ZF 3001','2026-09-19')==='80.00','ZF3001 Saturday');
fsassert(amount('TR-AYT','ZF 3001','2026-09-20')==='70.00','ZF3001 Sunday');
fsassert(amount('TR-AYT','ZF 3001','2026-10-04')==='70.00','ZF3001 later period');
fsassert(amount('TR-AYT','ZF 3002','2026-09-29')==='80.00','ZF3002 Tuesday');
fsassert(amount('TR-AYT','ZF 3002','2026-09-30')==='70.00','ZF3002 Wednesday');
fsassert(amount('TR-AYT','ZF 3003','2026-09-21')==='70.00','ZF3003 Monday');
fsassert(amount('TR-AYT','ZF 3003','2026-09-22')==='80.00','ZF3003 Tuesday');
fsassert(amount('TR-AYT','ZF 3004','2026-10-31')===null,'ZF3004 end boundary');

// Other Antalya carriers and Cyrillic PC normalization.
fsassert(amount('TR-AYT','S7 3747','2026-09-27')==='130.00','S73747');
fsassert(amount('TR-AYT','РС 1580','2026-09-26')==='110.00','PC1580 Cyrillic normalization');
fsassert(amount('TR-AYT','U6 1556','2026-10-01')==='100.00','U61556 Thursday');
fsassert(amount('TR-AYT','U6 1556','2026-10-02')==='80.00','U61556 Friday');
fsassert(amount('TR-AYT','U6 3555','2026-09-23')==='70.00','U63555 Wednesday');
fsassert(amount('TR-AYT','U6 3555','2026-09-25')==='80.00','U63555 Friday');
fsassert(amount('TR-AYT','U6 3556','2026-10-01')==='80.00','U63556 Thursday');
fsassert(amount('TR-AYT','U6 3556','2026-10-02')==='70.00','U63556 Friday');
fsassert(amount('TR-AYT','U6 3559','2026-09-21')==='70.00','U63559 Monday');
fsassert(amount('TR-AYT','U6 3559','2026-09-22')==='80.00','U63559 Tuesday');
fsassert(amount('TR-AYT','U6 3572','2026-12-31')==='70.00','U63572 2026');
fsassert(amount('TR-AYT','U6 3572','2027-01-01')==='80.00','U63572 2027');
fsassert(amount('TR-AYT','XC 9117','2027-10-25')==='110.00','Corendon outbound set');
fsassert(amount('TR-AYT','XC 9118','2027-10-25')==='110.00','Corendon return set');
fsassert(amount('TR-AYT','XC 9999','2027-10-25')===null,'Corendon set must stay exact');

// Dalaman / Bodrum.
fsassert(amount('TR-DLM','PC 1459','2026-09-17')==='80.00','Dalaman Pegasus');
fsassert(amount('TR-DLM','ZF 411','2026-10-30')==='70.00','Dalaman AZUR');
fsassert(amount('TR-BJV','TK 3123','2026-09-21')==='125.00','Bodrum Monday');
fsassert(amount('TR-BJV','TK 3123','2026-09-22')==='105.00','Bodrum Tuesday');
fsassert(amount('TR-BJV','TK 3026','2026-09-23')===null,'Bodrum pre-validity');
fsassert(amount('TR-BJV','TK 3026','2026-09-24')==='70.00','Bodrum validity start');

// Egypt exact destination/flight/date.
fsassert(amount('EG-SSH','MS 728','2026-05-18')==='50.00','Egypt SSH outbound');
fsassert(amount('EG-SSH','MS 727','2027-02-28')==='50.00','Egypt SSH return');
fsassert(amount('EG-HRG','MS 728','2026-12-01')==='50.00','Egypt HRG outbound');
fsassert(amount('EG-HRG','MS 728','2027-03-01')===null,'Egypt validity end');

// Thailand/Vietnam carrier-wide reference where owner did not provide dates.
fsassert(amount('TH','WZ 123','2026-09-23')==='20.00','Thailand Red Wings');
fsassert(amount('TH','ZF 9001','2026-09-23')==='20.00','Thailand AZUR prefix');
fsassert(amount('TH','XX 1','2026-09-23','AZUR air')==='20.00','Thailand AZUR carrier');
fsassert(amount('VN','WZ 456','2026-09-23')==='20.00','Vietnam Red Wings');
fsassert(amount('VN','XX 2','2026-09-23','VietJet')==='20.00','Vietnam VietJet carrier');

// The retained supplier probe transport pair must not become 140 EUR per leg.
$round=AnyTourFunSunFuelOwnerReferenceV1::roundTrip(
    'FUN&SUN','TR-AYT','U6 3555','2026-09-29','ZF 3004','2026-10-06',
    ['adults'=>2,'children'=>0,'child_ages'=>[]]
);
fsassert(is_array($round),'probe pair unresolved');
fsassert($round['legs']['outbound']['amount']==='80.00','probe outbound official fuel');
fsassert($round['legs']['return']['amount']==='70.00','probe return official fuel');
fsassert($round['per_eligible_passenger_roundtrip_amount']==='150.00','probe pair per passenger');
fsassert($round['party_fuel_native_total']==='300.00','probe pair party fuel');
fsassert(!array_key_exists('final_price_verified',$round),'reference must not verify final price');

// Children >=2 use adult fuel; infants are excluded and kept separate.
$mixed=AnyTourFunSunFuelOwnerReferenceV1::roundTrip(
    'FUN&SUN','TR-AYT','U6 3555','2026-09-29','ZF 3004','2026-10-06',
    ['adults'=>2,'children'=>2,'child_ages'=>[1,7]]
);
fsassert(is_array($mixed),'mixed party unresolved');
fsassert($mixed['eligible_passenger_count']===3 && $mixed['infant_count']===1,'eligible/infant split');
fsassert($mixed['party_fuel_native_total']==='450.00','mixed party fuel');
fsassert($mixed['infant_reference']['amount']==='35.00'
    && $mixed['infant_reference']['currency']==='EUR'
    && $mixed['infant_reference']['unit']==='per_infant_unspecified','Turkey infant reference');

// Do not invent one-way semantics where the owner did not supply them.
$egyptInfant=AnyTourFunSunFuelOwnerReferenceV1::infantReference('EG-SSH');
$thaiInfant=AnyTourFunSunFuelOwnerReferenceV1::infantReference('TH');
fsassert($egyptInfant['amount']==='70.00' && $egyptInfant['unit']==='per_infant_one_way','Egypt infant');
fsassert($thaiInfant['amount']==='100.00' && $thaiInfant['unit']==='per_infant_unspecified','Thailand infant');
fsassert($thaiInfant['separate_from_fuel']===true,'infant must stay separate');

echo "FUNSUN_FUEL_OWNER_REFERENCE_OK strict_transport=1 probe_direction_140_rejected=1 infant_separate=1 supplier=0 db=0\n";
