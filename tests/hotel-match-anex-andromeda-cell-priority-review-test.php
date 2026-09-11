<?php
declare(strict_types=1);
define('FC_LIBRARY_ONLY', true);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_anex_andromeda_cell_priority_review.php';
function t(bool $v,string $m):void{if(!$v){fwrite(STDERR,"FAIL: $m\n");exit(1);}}

t(hmacpr_shared_place(['Sharm El Sheikh'],['SHARM EL SHEIKH'])==='Sharm El Sheikh','shared resort survives normalization');
t(hmacpr_cell_key(1,'Sharm El Sheikh')==='1|sharm el sheikh','stable cell key');
$c=hmacpr_new_cell(1,'Sharm El Sheikh');$c['exact_alias_overlap']=10;$c['missing_third_potential']=5;$c['recurring_pairs']=4;$c['unresolved_overlap']=3;$c['live_priority_pairs']=6;$c['ambiguous_sources']=1;$c['coordinate_conflicts']=1;
t(hmacpr_score($c)===64,'policy score arithmetic');
$a=['names'=>hmadcr_expand_names(['APERION BEACH (EX. SEA PARADISE)']),'places'=>['Side'],'latitude'=>36.713018,'longitude'=>31.563078,'local_ids'=>[],'live'=>true,'observation_count'=>3,'country_id'=>4];
$b=['names'=>hmadcr_expand_names(['APERION BEACH']),'places'=>['Side'],'latitude'=>36.7130180,'longitude'=>31.5630780,'local_ids'=>[],'live'=>true,'observation_count'=>2,'country_id'=>4];
$idx=hmamgr_index(['8121'=>$b]);$pool=hmamgr_candidates($a,['8121'=>$b],$idx);t(isset($pool['8121']),'former-name candidate survives');t(($pool['8121']['route']??null)!=='coordinate_conflict','format-only coordinate difference not conflict');
$bad=$b;$bad['names']=hmadcr_expand_names(['APERION GARDEN']);$idx=hmamgr_index(['8121'=>$bad]);$pool=hmamgr_candidates($a,['8121'=>$bad],$idx);t(!isset($pool['8121'])||($pool['8121']['route']??null)===null,'meaningful qualifier not erased');
$far=$b;$far['latitude']=37.5;$far['longitude']=32.5;$idx=hmamgr_index(['8121'=>$far]);$pool=hmamgr_candidates($a,['8121'=>$far],$idx);t(($pool['8121']['route']??null)==='coordinate_conflict','>5km blocks discovery edge');
echo "ANEX-Andromeda cell priority review tests passed\n";
