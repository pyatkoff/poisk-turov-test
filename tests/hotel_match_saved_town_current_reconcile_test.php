<?php
declare(strict_types=1);
putenv('MATCH_TEST_LIBRARY=1');
require __DIR__.'/../scripts/diagnostics/hotel_match_saved_town_current_reconcile.php';
function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
ok(array_intersect_key(mst_tokens("Rocky's Boutique Resort"),mst_tokens('ROCKYS BOUTIQUE HOTEL'))!==[],'apostrophe');
ok(array_intersect_key(mst_tokens('Royal Beach 2'),mst_tokens('Royal Garden 2'))===[],'qualifier_compact');
$a=mst_tokens('AMARINA JANNAH AQUAPARK');$b=mst_tokens('Amarina Jannah Aqua Park');ok($a['amarinajannahaquapark']['qualifiers']===$b['amarinajannahaquapark']['qualifiers'],'aquapark');
ok(mst_geo('Шарм-эль-Шейх')===mst_geo('Шарм Эль Шейх'),'geo punctuation');
ok(mst_geo('Marsa Alam')===mst_geo('Марса Алам'),'geo transliteration');
ok(mst_protected(['source'=>['manual'=>true]])===true,'manual');ok(mst_protected(['conflict'=>false])===false,'false conflict');
$src=['external_hotel_id'=>'1','country_id'=>1,'names'=>['Royal Beach 2'],'town_labels'=>['Шарм Эль Шейх'],'parent_evidence_sha256'=>str_repeat('a',64)];
$row=['decision_status'=>'pending','local_hotel_id'=>null,'evidence_sha256'=>str_repeat('a',64)];$ev=['source'=>['name'=>'Royal Beach 2']];
$hotels=[10=>['country_id'=>1,'name'=>'Royal Beach 2','region_name'=>'Шарм-эль-Шейх','subregion_name'=>'','latitude'=>27.9,'longitude'=>34.3]];$forms=[10=>['Royal Beach 2']];$index=[1=>[]];foreach(mst_tokens('Royal Beach 2') as $k=>$f)$index[1][$k][10][]=$f;
$r=mst_classify($src,$row,$ev,$hotels,$forms,$index,[]);ok($r['route']==='guard_passed_prepared','pass');
$src['town_labels']=['Хургада'];$r=mst_classify($src,$row,$ev,$hotels,$forms,$index,[]);ok($r['route']==='hold'&&in_array('typed_town_not_direct_target_region',$r['holds'],true),'geo hold');
$src['town_labels']=['Шарм Эль Шейх'];$r=mst_classify($src,$row,$ev,$hotels,$forms,$index,[10=>['2']]);ok($r['route']==='hold'&&in_array('same_provider_target_occupied',$r['holds'],true),'occupancy');
echo "9 saved-town reconcile tests PASS\n";
