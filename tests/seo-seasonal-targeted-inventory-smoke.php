<?php
declare(strict_types=1);

function targeted_inventory_fail(string $m):never{fwrite(STDERR,"SEO_SEASONAL_TARGETED_INVENTORY_FAIL:$m\n");exit(1);}
$cli=__DIR__.'/../v2/data/collect-seo-seasonal-preview-inventory-v1.php';
$out=[];$code=0;
exec('php '.escapeshellarg($cli).' --dry-run=1 --now-date=2026-09-03 2>&1',$out,$code);
if($code!==0)targeted_inventory_fail('dry_run_exit_'.$code);
$json=json_decode(implode("\n",$out),true);
if(!is_array($json)||($json['state']??'')!=='review_only_seasonal_target_plan')targeted_inventory_fail('state');
if(($json['business_timezone']??'')!=='Europe/Moscow'||($json['business_date']??'')!=='2026-09-03')targeted_inventory_fail('business_clock');
if(($json['target_count']??0)!==4||count($json['targets']??[])!==4)targeted_inventory_fail('count');
$by=[];foreach($json['targets'] as $row)$by[(string)($row['preview_key']??'')]=$row;
$expected=[
    'antalya-september'=>['page_key'=>'resort_month:1:4:20:2026-09','country_id'=>4,'region_id'=>20,'date_from'=>'2026-09-04','date_to'=>'2026-09-24'],
    'maldives-september'=>['page_key'=>'month:1:8:2026-09','country_id'=>8,'region_id'=>null,'date_from'=>'2026-09-04','date_to'=>'2026-09-24'],
    'antalya-october'=>['page_key'=>'resort_month:1:4:20:2026-10','country_id'=>4,'region_id'=>20,'date_from'=>'2026-10-01','date_to'=>'2026-10-21'],
    'maldives-october'=>['page_key'=>'month:1:8:2026-10','country_id'=>8,'region_id'=>null,'date_from'=>'2026-10-01','date_to'=>'2026-10-21'],
];
foreach($expected as $key=>$want){
    $row=$by[$key]??null;if(!is_array($row))targeted_inventory_fail('missing_'.$key);
    foreach($want as $field=>$value)if(($row[$field]??null)!==$value)targeted_inventory_fail($key.'_'.$field);
    if(($row['departure_id']??0)!==1)targeted_inventory_fail($key.'_departure');
}
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed'] as $flag)if(($json[$flag]??true)!==false)targeted_inventory_fail('boundary_'.$flag);
if(($json['tourvisor_calls_allowed']??true)!==false)targeted_inventory_fail('dry_run_network_boundary');

// The production host and GitHub runner may use UTC or another OS timezone. The
// collector must still derive its implicit business date from Europe/Moscow.
$out=[];$code=0;
exec('TZ=Pacific/Honolulu php '.escapeshellarg($cli).' --dry-run=1 2>&1',$out,$code);
if($code!==0)targeted_inventory_fail('host_timezone_exit_'.$code);
$clock=json_decode(implode("\n",$out),true);
$expectedBusinessDate=(new DateTimeImmutable('today',new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
if(!is_array($clock)||($clock['business_timezone']??'')!=='Europe/Moscow'||($clock['business_date']??'')!==$expectedBusinessDate)targeted_inventory_fail('host_timezone_drift');

// Once September is over only the two October identities remain active.
$out=[];$code=0;exec('php '.escapeshellarg($cli).' --dry-run=1 --now-date=2026-10-01 2>&1',$out,$code);
if($code!==0)targeted_inventory_fail('october_exit');
$oct=json_decode(implode("\n",$out),true);
if(!is_array($oct)||($oct['target_count']??0)!==2)targeted_inventory_fail('october_count');
$keys=array_column($oct['targets']??[],'preview_key');sort($keys,SORT_STRING);
if($keys!==['antalya-october','maldives-october'])targeted_inventory_fail('october_scope');
foreach($oct['targets'] as $row)if(($row['date_from']??'')!=='2026-10-02'||($row['date_to']??'')!=='2026-10-22')targeted_inventory_fail('october_window');

$out=[];$code=0;exec('php '.escapeshellarg($cli).' --dry-run=1 --now-date=2026-11-01 2>&1',$out,$code);
if($code!==0)targeted_inventory_fail('expired_exit');
$expired=json_decode(implode("\n",$out),true);
if(!is_array($expired)||($expired['target_count']??-1)!==0)targeted_inventory_fail('expired_target_leak');
echo "SEO_SEASONAL_TARGETED_INVENTORY_OK targets=4 exact=4 octoberRollover=2 timezone=Europe/Moscow networkInDryRun=0 publication=0\n";
