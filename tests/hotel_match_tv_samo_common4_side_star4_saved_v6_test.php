<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_tv_samo_common4_side_star4_saved_v6.php';
function h6(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$tmp=sys_get_temp_dir().'/hmc6-'.bin2hex(random_bytes(4));mkdir($tmp.'/evidence-private',0700,true);
file_put_contents($tmp.'/result.json',json_encode(['operation'=>HMC6_SOURCE_OP,'state'=>'terminal_failed_no_replay','reason'=>'samo_zero_page_shape']));
file_put_contents($tmp.'/receipt.json',json_encode(['operation'=>HMC6_SOURCE_OP,'provider_accessed'=>true,'no_replay'=>true]));
file_put_contents($tmp.'/search-plan.json',json_encode(['route'=>['resort'=>'Side','tv_region'=>['id'=>23],'samo_townto'=>['id'=>20]],
    'date_from'=>HMC_DATE_FROM,'date_to'=>HMC_DATE_TO,'nights'=>7,'adults'=>2,'children'=>0,'tv_operator_ids'=>[13,18,25,43],'samo_operator_ids'=>['5','115','315','342']]));
$base=['TOWNFROMINC'=>1,'STATEINC'=>5,'CHECKIN_BEG'=>'20261005','CHECKIN_END'=>'20261011','NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,
    'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'STARS'=>'4','OPERATORS'=>'5,115,315,342','TOWNTOINC'=>'20','PACKETTYPE'=>0,'GROUP_BY'=>32];
$put=function(int $seq,int $page,array $reply)use($tmp,$base){$raw=json_encode($reply);$name=sprintf('%04d-samo-price',$seq);
    file_put_contents($tmp.'/evidence-private/'.$name.'.bin',$raw);file_put_contents($tmp.'/evidence-private/'.$name.'.meta.json',json_encode([
        'source'=>'samo-price','http_status'=>200,'raw_sha256'=>hash('sha256',$raw),'meta'=>['star'=>4,'page'=>$page,'params'=>$base+['PAGE'=>$page]]]]));};
$row=['id'=>'x','hotelKey'=>'900','hotel'=>'ALPHA HOTEL','operatorKey'=>115,'operator'=>'Biblio Globus','price'=>'100000','currency'=>'RUB',
    'checkIn'=>'05.10.2026','nights'=>'7','room'=>'Standard','meal'=>'AI','adult'=>'2','child'=>'0'];
$put(1,1,['PAGE'=>1,'PAGES_COUNT'=>2,'PRICES'=>[$row]]);$put(2,2,['PAGE'=>2,'PAGES_COUNT'=>2,'PRICES'=>[$row+['id'=>'y']]]);
$put(3,3,['PAGE'=>3,'PAGES_COUNT'=>0,'PRICES'=>[]]);
$x=hmc6_samo_rows($tmp);h6(count($x['rows'])===2,'rows');h6(count($x['pages'])===3&&$x['terminal_page']===3,'terminal');
h6($x['pages'][2]['terminal_empty']===true,'empty');
foreach(glob($tmp.'/evidence-private/*')?:[] as $f)unlink($f);rmdir($tmp.'/evidence-private');foreach(['result.json','receipt.json','search-plan.json'] as $f)unlink($tmp.'/'.$f);rmdir($tmp);
$source=(string)file_get_contents(__DIR__.'/hotel_match_tv_samo_common4_side_star4_saved_v6.php');
h6(!str_contains($source,'hmc_tv_call('),'no_tv_http');h6(!str_contains($source,'->price('),'no_samo_http');
echo "MATCH_TV_SAMO_COMMON4_SIDE_STAR4_SAVED_V6_TEST_OK\n";
