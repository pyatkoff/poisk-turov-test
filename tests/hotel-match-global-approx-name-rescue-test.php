<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_global_approx_name_rescue.php';

function hmgan_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }

$pair=hmgan_pair('Steigenberger Alcazar','Steigenbergr Alcazar Hotel',[],[]);
hmgan_assert($pair['aligned']>=2,'typo pair must align two identity tokens');
hmgan_assert($pair['fuzzy_score']>=HMGAN_FUZZY_MIN,'typo pair fuzzy score');
hmgan_assert($pair['char_similarity']>=HMGAN_CHAR_MIN,'typo pair char similarity');
hmgan_assert(hmgan_route($pair,null,true,0.20)==='approx_name_direct_place','place-confirmed typo pair should prepare');
hmgan_assert(hmgan_route($pair,null,false,0.20)===null,'no-geo approximate pair must not prepare');
hmgan_assert(hmgan_route($pair,900.0,false,0.20)==='approx_name_direct_coordinate','<=1km coordinate may confirm approximate pair');
hmgan_assert(hmgan_route($pair,6000.0,true,0.20)===null,'>5km must block approximate route');
hmgan_assert(hmgan_route($pair,null,true,0.05)===null,'small winner margin must block');

$critical=hmgan_pair('Alpha Beach Resort','Alpha Garden Hotel',[],[]);
hmgan_assert($critical['critical_ok']===false,'critical BEACH/GARDEN mismatch must block');
hmgan_assert(hmgan_route($critical,100.0,true,1.0)===null,'critical mismatch cannot route');

$single=hmgan_pair('Movenpick Hotel','Movenpik Resort',[],[]);
hmgan_assert($single['aligned']===1,'single fuzzy token expected');
hmgan_assert(hmgan_route($single,20.0,true,1.0)===null,'single-token approximate identity must never auto-prepare');

$structural=hmgan_pair('Green Villa Boutique','Green Palace Hotel',[],[]);
hmgan_assert($structural['aligned']<2,'structural generics must not create two identity anchors');
hmgan_assert(hmgan_route($structural,10.0,true,1.0)===null,'structural-only similarity must block');

$former=hmgan_pair('Aperion Beach Hotel (EX. Sea Paradise)','APERION BEACH RESORT',[],[]);
hmgan_assert($former['critical_ok']===true,'former-name marker must not break current-name qualifier');
hmgan_assert(in_array('aperion',$former['source_tokens'],true),'current identity token must remain');

$idx=hmgan_build_index(
    [10=>['country_id'=>4,'name'=>'Steigenberger Alcazar','region_name'=>'Sharm','subregion_name'=>'Nabq']],
    [10=>['Steigenberger Alcazar']]
);
$candidates=hmgan_candidate_ids(['Steigenbergr Alcazar'],['Sharm'],4,$idx);
hmgan_assert($candidates===[10],'trigram retrieval must recover typo candidate without exact token requirement');

fwrite(STDOUT,"hotel-match-global-approx-name-rescue-test: ok\n");
