<?php
require_once __DIR__.'/../v2/sales-leaders-v1.php';
function check(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$kremlin=sales_leader_match(['name'=>'Kremlin Palace','country'=>['name'=>'Турция']]);
check(is_array($kremlin),'Kremlin Palace must match');
check(($kremlin['rank']??0)===1,'Kremlin Palace must keep first country rank');
$asteria=sales_leader_match(['name'=>'Asteria Family Resort Belek','country'=>['name'=>'Турция']]);
check(is_array($asteria),'Current hotel name must match source name with Ex alias');
$uae=sales_leader_match(['name'=>'Address Beach Resort','country'=>['name'=>'Объединенные Арабские Эмираты']]);
check(is_array($uae),'UAE country alias must match');
$unknown=sales_leader_match(['name'=>'Definitely Unknown Hotel','country'=>['name'=>'Турция']]);
check($unknown===null,'Unknown hotel must not be marked as a leader');
$enriched=sales_leader_enrich_results([['id'=>1,'name'=>'Kremlin Palace','country'=>['name'=>'Турция']],['id'=>2,'name'=>'Definitely Unknown Hotel','country'=>['name'=>'Турция']]]);
check(($enriched[0]['salesLeader']??false)===true,'Known hotel must be enriched');
check(!isset($enriched[1]['salesLeader']),'Unknown hotel must remain untouched');
echo "sales leaders v1: OK\n";
