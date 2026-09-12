<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/../scripts/diagnostics/hotel_match_andromeda_star_bridge_review.php';

function hmasbr_t(bool $ok,string $msg): void {if(!$ok)throw new RuntimeException($msg);}

hmasbr_t(hmasbr_route(250.0,false,false,['sianji','being'])==='generic_free_exact_plus_direct_coordinate','two-token coordinate route');
hmasbr_t(hmasbr_route(80.0,false,true,['dara'])==='single_token_ultratight_coordinate_anchor','single-token ultra-tight bridge route');
hmasbr_t(hmasbr_route(null,true,false,['koh','chang'])==='generic_free_exact_plus_place','place route');
hmasbr_t(hmasbr_route(null,false,true,['crystal','sands'])==='generic_free_exact_plus_anex_tourvisor_bridge','bridge route');
hmasbr_t(hmasbr_route(null,true,true,['dara'])==='single_token_place_plus_anex_tourvisor_bridge','single-token place plus bridge');
hmasbr_t(hmasbr_route(6000.0,true,true,['titanic','palace'])===null,'over 5km blocks');
hmasbr_t(hmasbr_route(null,false,true,['dara'])===null,'single token bridge alone blocks');
hmasbr_t(hmasbr_route(500.0,false,false,['dara'])===null,'single token loose coordinate blocks');

$tokens=hmasbr_identity_tokens(['Dundar Hotel & SPA'],['DUNDAR'],['Istanbul'],['Antalya']);
hmasbr_t($tokens===['dundar'],'HOTEL RESORT SPA are non-identifying');
$tokens=hmasbr_identity_tokens(['North Beach Garden Hotel'],['NORTH BEACH GARDEN'],[],[]);
hmasbr_t(in_array('north',$tokens,true)&&in_array('beach',$tokens,true)&&in_array('garden',$tokens,true),'significant qualifiers preserved');
$tokens=hmasbr_identity_tokens(['Koh Chang Paradise Hill'],['KOH CHANG PARADISE HILL'],['Koh Chang'],['Koh Chang']);
hmasbr_t(in_array('paradise',$tokens,true)&&in_array('hill',$tokens,true)&&!in_array('koh',$tokens,true)&&!in_array('chang',$tokens,true),'geography not identity');

echo "ok\n";
