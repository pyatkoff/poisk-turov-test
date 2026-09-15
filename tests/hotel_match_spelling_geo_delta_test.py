#!/usr/bin/env python3
"""Guard regressions and an independent replay of sealed evidence in memory only."""
import hashlib,json,os,subprocess,sys,tempfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
sys.path.insert(0,str(ROOT/'scripts/diagnostics'))
import hotel_match_spelling_geo_delta_bundle as delta

PHP=r'''<?php
putenv('MATCH_SPELLING_LIBRARY=1');require $argv[1];
function check($ok,$name){if(!$ok)throw new RuntimeException($name);}
check(sgd_one_token(['kleopatra','blue','hawaii'],['kleopatra','blue','hawai']),'letter_variant');
foreach([
 [['royal','beach'],['royal','garden']],
 [['grand','sunrise','2'],['grand','sunrise','3']],
 [['north','villa'],['south','villa']],
 [['royal','gardens'],['royal','garden']],
 [['surf','sea','breeze'],['surf','breeze']],
 [['operla','airport','trademark'],['operla','airoport','trandemark']],
] as $p)check(!sgd_one_token($p[0],$p[1]),'semantic_or_multiple_change');
$name='Kleopatra Blue Hawaii';$local='KLEOPATRA BLUE HAWAI';$row=['external_hotel_id'=>'1','evidence_sha256'=>str_repeat('a',64)];$ev=['source'=>['name'=>$name]];
$plan=['1'=>['local_id'=>10,'country_id'=>4,'evidence_sha256'=>str_repeat('a',64),'score'=>.98,'margin_lower_bound'=>.18,'holds'=>[]]];
$hotels=[10=>['id'=>10,'country_id'=>4,'name'=>$local,'latitude'=>36,'longitude'=>31]];$forms=[10=>[$local]];$index=[4=>[]];$allow=['status'=>'ok','ids'=>[10]];
check(sgd_review($row,$ev,4,$allow,$plan,$hotels,$index,[],$forms)['route']==='guard_passed_prepared','spelling_and_geo');
foreach(['occupied','manual','geo','coordinate','changed','near_competitor','filtered_competitor','preliminary_hold','numeric'] as $case){
 $rr=$row;$e=$ev;$a=$allow;$occ=[];$hh=$hotels;$ff=$forms;$pp=$plan;
 if($case==='occupied')$occ=[10=>['2']];if($case==='manual')$e['nested']=['manual'=>true];if($case==='geo')$a['ids']=[11];
 if($case==='coordinate')$e['source']+=['latitude'=>1,'longitude'=>1];if($case==='changed')$rr['evidence_sha256']=str_repeat('b',64);
 if(in_array($case,['near_competitor','filtered_competitor'],true)){$hh[11]=['id'=>11,'country_id'=>4,'name'=>'Kleopatra Blue Hawaii Beach'];$ff[11]=[$case==='near_competitor'?'Kleopatra Blue Hawaii':'KleopatraBlueHawaii'];}
 if($case==='preliminary_hold')$pp['1']['holds']=['qualifier_mismatch'];if($case==='numeric')$e['source']['name']='Kleopatra Blue Hawai 2';
 check(sgd_review($rr,$e,4,$a,$pp,$hh,$index,$occ,$ff)['route']==='held','hold_'.$case);
}
$r=json_decode(file_get_contents($argv[2]),true,128,JSON_THROW_ON_ERROR);$p=json_decode(file_get_contents($argv[3]),true,128,JSON_THROW_ON_ERROR);$by=[];
foreach($r['routes'] as $rows)foreach($rows as $x)$by[(string)$x['external_hotel_id']]=$x;
foreach($p['eligible_rows'] as $x){$old=$by[$x['external_hotel_id']];if($old['route']==='guard_passed_prepared')continue;
 check(!$old['dossier_preliminary_holds'],'sealed_preliminary_clear');check(count($old['holds'])===2,'sealed_only_primary_holds');
 $rank=sgd_rank($old['names'],(int)$old['country_id'],$r['local_hotels'],$r['local_alias_forms']);
 check($rank['target']===$x['local_id']&&$rank['score']>=.94&&$rank['margin']>=.12,'whole_country_rank');
 check(sgd_one_token($rank['match']['source']['tokens'],$rank['match']['local']['tokens']),'whole_country_winning_token');
}
echo 'spelling gates + 11 sealed countrywide candidates PASS\n';
'''

def main():
 fixture=Path(sys.argv[1]);raw=fixture.read_bytes()
 assert hashlib.sha256(raw).hexdigest()=='b51edba926e5e8c974b04d12adb23e5c3029691716641ea52e929b268b47e074'
 bundle=delta.build()
 assert bundle.count('UPDATE andromeda_hotel_identities SET')==1
 assert 'FOR UPDATE' in bundle and 'SERIALIZABLE' in bundle
 assert bundle.index("mpg_write($dir.'/precommit.json'")<bundle.index('$up->execute')<bundle.index('$db->commit()')<bundle.index("'post_commit_readback'=>$readback")
 with tempfile.TemporaryDirectory() as tmp:
  p=Path(tmp);(p/'bundle.php').write_text(bundle);(p/'test.php').write_text(PHP)
  subprocess.run(['php','-l',str(p/'bundle.php')],check=True)
  subprocess.run(['php','-d','allow_url_fopen=0',str(p/'test.php'),str(p/'bundle.php'),str(fixture),str(ROOT/delta.PLAN)],check=True,timeout=180)
 print('sealed plan, current guards, unfiltered competitors, transaction/readback contract PASS')

if __name__=='__main__':main()
