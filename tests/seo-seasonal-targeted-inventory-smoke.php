<?php
declare(strict_types=1);

function targeted_inventory_fail(string $m):never{fwrite(STDERR,"SEO_SEASONAL_TARGETED_INVENTORY_FAIL:$m\n");exit(1);}
$cli=__DIR__.'/../v2/data/collect-seo-seasonal-preview-inventory-v1.php';
$out=[];$code=0;
exec('php '.escapeshellarg($cli).' --dry-run=1 --now-date=2026-09-03 2>&1',$out,$code);
if($code!==0)targeted_inventory_fail('dry_run_exit_'.$code);
$json=json_decode(implode("\n",$out),true);
if(!is_array($json)||($json['state']??'')!=='review_only_seasonal_target_plan')targeted_inventory_fail('state');
if(($json['target_count']??0)!==2||count($json['targets']??[])!==2)targeted_inventory_fail('count');
$by=[];foreach($json['targets'] as $row)$by[(string)($row['preview_key']??'')]=$row;
if(($by['antalya-september']['page_key']??'')!=='resort_month:1:4:20:2026-09')targeted_inventory_fail('antalya_key');
if((int)($by['antalya-september']['country_id']??0)!==4||(int)($by['antalya-september']['region_id']??0)!==20)targeted_inventory_fail('antalya_identity');
if(($by['maldives-september']['page_key']??'')!=='month:1:8:2026-09')targeted_inventory_fail('maldives_key');
if((int)($by['maldives-september']['country_id']??0)!==8||!array_key_exists('region_id',$by['maldives-september'])||$by['maldives-september']['region_id']!==null)targeted_inventory_fail('maldives_identity');
foreach($by as $row){
    if(($row['departure_id']??0)!==1)targeted_inventory_fail('departure');
    if(($row['date_from']??'')!=='2026-09-04'||($row['date_to']??'')!=='2026-09-24')targeted_inventory_fail('window');
}
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','route_launch_allowed'] as $flag)if(($json[$flag]??true)!==false)targeted_inventory_fail('boundary_'.$flag);
if(($json['tourvisor_calls_allowed']??true)!==false)targeted_inventory_fail('dry_run_network_boundary');
$out=[];$code=0;exec('php '.escapeshellarg($cli).' --dry-run=1 --now-date=2026-10-01 2>&1',$out,$code);
if($code!==0)targeted_inventory_fail('expired_exit');
$expired=json_decode(implode("\n",$out),true);
if(!is_array($expired)||($expired['target_count']??-1)!==0)targeted_inventory_fail('expired_target_leak');
echo "SEO_SEASONAL_TARGETED_INVENTORY_OK targets=2 exact=2 networkInDryRun=0 publication=0\n";
