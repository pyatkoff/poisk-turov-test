<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_global_cross_provider_rescue.php';
function hmgcr_t(bool $ok,string $m): void {if(!$ok)throw new RuntimeException($m);}

$p=hmgcr_pair('Кача Лагуна','Kacha Laguna',[],[]);
hmgcr_t($p['exact_bag']===true && $p['shared']===2 && $p['cross_script']===true,'translit exact identity failed');
hmgcr_t(hmgcr_route($p,null,false,1.0)==='cross_provider_translit_exact','translit exact route failed');

$q=hmgcr_pair('Sunrise Garden Beach','Sunrise Garden',[],[]);
hmgcr_t($q['critical_ok']===false,'BEACH qualifier loss not blocked');
hmgcr_t(hmgcr_route($q,20.0,true,1.0)===null,'qualifier mismatch accepted');

$geo=hmgcr_pair('V Grand Resort Sahl Hasheesh','SRNTY Sahl Hashish',['Sahl Hasheesh'],['Сахл Хашиш']);
hmgcr_t($geo['shared']===0,'geography leaked into identity tokens');
hmgcr_t(hmgcr_route($geo,40.0,true,1.0)===null,'geography-only identity accepted');

$f=hmgcr_pair('Sunrise Crystal Bay','Sunrise Crystal Bay Select',[],[]);
hmgcr_t($f['shared']===3 && $f['token_diff']===1,'fuzzy pair setup failed');
hmgcr_t(hmgcr_route($f,500.0,false,0.30)==='cross_provider_high_overlap_direct_geo','direct-geo fuzzy route failed');

$n=hmgcr_pair('Alpha Beta Gamma Delta Epsilon','Alpha Beta Gamma Delta Epsilon Zeta',[],[]);
hmgcr_t(hmgcr_route($n,null,false,0.30)==='cross_provider_high_overlap_no_geo','no-geo strong route failed');

$one=hmgcr_pair('Kacha Resort','Kacha Hotel',['Koh Chang'],['Koh Chang']);
hmgcr_t($one['exact_bag']===true && $one['shared']===1,'single-token setup failed');
hmgcr_t(hmgcr_route($one,50.0,true,1.0)==='cross_provider_single_token_ultratight','single-token ultratight route failed');
hmgcr_t(hmgcr_route($one,6000.0,true,1.0)===null,'>5km coordinate guard failed');

[$exact,$fuzzy]=hmgcr_source_candidate_ids(['Kacha Resort','Kacha Hotel'],[],4,['exact'=>[],'token'=>[4=>['kacha'=>[77=>true]]]]);
hmgcr_t($exact===[] && $fuzzy===[],'one repeated alias token inflated fuzzy hit count');

$hotels=[1=>['id'=>1,'country_id'=>4,'name'=>'KACHA LAGUNA','region_name'=>'Koh Chang','subregion_name'=>'Koh Chang','latitude'=>12.0,'longitude'=>102.0],2=>['id'=>2,'country_id'=>4,'name'=>'KACHA LAGUNA','region_name'=>'Koh Chang','subregion_name'=>'Koh Chang','latitude'=>12.0,'longitude'=>102.0]];
$names=[1=>['KACHA LAGUNA'],2=>['KACHA LAGUNA']];
$idx=hmgcr_build_index([1=>true,2=>true],$hotels,$names);
$d=hmgcr_decide(['names'=>['Kacha Laguna'],'places'=>['Koh Chang'],'latitude'=>12.0,'longitude'=>102.0],4,$idx,$hotels,$names,[],[]);
hmgcr_t($d['bucket']==='near' && $d['reason']==='opposite_provider_exact_ambiguous','multiple exact bridge targets not blocked');

echo "hotel-match-global-cross-provider-rescue-test: OK\n";
