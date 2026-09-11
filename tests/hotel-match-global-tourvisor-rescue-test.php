<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_global_tourvisor_rescue.php';

function hmgtr_assert(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }

$exact=hmgcr_pair('Koh Chang Kacha Resort & Spa','KOH CHANG KACHA RESORT & SPA',[],[]);
hmgtr_assert($exact['exact_bag']===true,'exact bag expected');
hmgtr_assert(hmgtr_route($exact,400.0,true,1.0)==='tourvisor_generic_free_exact_direct_geo','exact direct geo expected');

$fuzzy=hmgcr_pair('Sunrise Arabian Beach Resort','Sunrise Arabian Beach Hotel Resort',[],[]);
hmgtr_assert($fuzzy['shared']>=3,'fuzzy identity tokens expected');
hmgtr_assert(hmgtr_route($fuzzy,800.0,true,0.25)==='tourvisor_high_overlap_direct_geo' || str_contains((string)hmgtr_route($fuzzy,800.0,true,0.25),'exact'),'direct geo route expected');

$single=hmgcr_pair('Annex Hotel','Annex Resort',[],[]);
hmgtr_assert(hmgtr_route($single,40.0,true,1.0)==='tourvisor_single_token_ultratight_place','single token must need ultratight place');
hmgtr_assert(hmgtr_route($single,60.0,true,1.0)===null,'single token over 50m must block');

$conflict=hmgcr_pair('Kacha Beach Resort','Kacha Beach Hotel',[],[]);
hmgtr_assert(hmgtr_route($conflict,6000.0,true,1.0)===null,'>5km must block route');

$qualifier=hmgcr_pair('Royal Garden','Royal Beach',[],[]);
hmgtr_assert($qualifier['critical_ok']===false,'critical qualifier mismatch must block');

$nogeo=hmgcr_pair('Alpha Bravo Charlie Delta','Alpha Bravo Charlie Resort',[],[]);
hmgtr_assert(hmgtr_route($nogeo,null,false,1.0)===null,'no-geo fuzzy must not auto-prepare');

fwrite(STDOUT,"hotel-match-global-tourvisor-rescue-test: ok\n");
