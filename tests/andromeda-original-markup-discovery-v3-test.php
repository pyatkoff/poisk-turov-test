<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/scripts/ops/andromeda_original_markup_discovery_v3.php';
$n=0;$ok=static function(bool $v,string $s)use(&$n):void{++$n;if(!$v)throw new RuntimeException('FAIL '.$s);};
$tree=['markup'=>160,'currency'=>'USD'];foreach(['detail','details','transport','transports','variant','variants']as$key)$tree=[$key=>[$tree]];$r=anytour_original_markup_scan_v3($tree);
$ok($r['markup_count']===1,'unknown wrapper found');$m=$r['markup'][0];
$ok($m['inside_transport']===true,'transport provenance');$ok($m['fields']['markup']['value']==='160','real numeric value');
$ok($m['fields']['currency']['value']==='USD','currency');
$ok(str_contains($m['path'],'.variant[0]'),'wrapper path');$ok($r['details_key_count']===2,'details counters');
foreach([0,'0','0.00']as$v){$r=anytour_original_markup_scan_v3(['transport'=>['details'=>['markup'=>$v]]]);$ok($r['markup'][0]['fields']['markup']['state']==='reported_zero','zero preserved');}
$r=anytour_original_markup_scan_v3(['transport'=>['details'=>['markup'=>null]]]);$ok($r['markup'][0]['fields']['markup']['state']==='null','null retained');
$r=anytour_original_markup_scan_v3(['transport'=>['details'=>['markup'=>true]]]);$ok($r['markup'][0]['fields']['markup']['state']==='invalid_boolean','bool not money');
$r=anytour_original_markup_scan_v3(['wrapper'=>['transport'=>['markup'=>['amount'=>42,'currency'=>'EUR','secret'=>'PRIVATE']]]]);
$ok($r['markup_count']===1,'arbitrary nesting');$ok(!str_contains(json_encode($r),'PRIVATE'),'only whitelisted values');
$r=anytour_original_markup_scan_v3(['clients'=>[['markup'=>999]],'sid'=>['markup'=>999],'transport'=>['details'=>['markup'=>'PRIVATE_SECRET']]]);
$ok($r['markup_count']===1,'private subtree excluded');$ok(!str_contains(json_encode($r),'PRIVATE'),'unparsed string hidden');
$r=anytour_original_markup_scan_v3(['claimDocument'=>[['services'=>[['markup'=>45]]]]]);$ok(!$r['markup'][0]['inside_transport'],'service markup separate');
$r=anytour_original_markup_scan_v3(['transport'=>['details'=>['currency'=>'USD']]]);$ok($r['markup_count']===0,'absent not zero');
$r=anytour_original_markup_scan_v3(['transport'=>['details'=>['markup'=>-5]]]);$ok($r['markup'][0]['fields']['markup']['value']==='-5','signed amount');
$r=anytour_original_markup_scan_v3(['transport'=>['details'=>['markup'=>'1e6']]]);$ok($r['markup'][0]['fields']['markup']['state']==='unparsed','no string coercion');
$deep=['markup'=>1];for($i=0;$i<35;++$i)$deep=['x'=>$deep];
try{anytour_original_markup_scan_v3($deep);$ok(false,'depth');}catch(RuntimeException $e){$ok($e->getMessage()==='MARKUP_SCAN_COMPLEXITY','depth bound');}
echo 'ORIGINAL_MARKUP_DISCOVERY_V3_OK checks='.$n.PHP_EOL;
