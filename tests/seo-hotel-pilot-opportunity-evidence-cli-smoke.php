<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-hotel-launch-pilot-v1.php';
function cli_fail(string $m):void{fwrite(STDERR,"SEO_HOTEL_PILOT_EVIDENCE_CLI_SMOKE_FAIL:$m\n");exit(1);}
$now=1800000000;$rows=[];$n=0;
foreach(v2_seo_hotel_launch_pilot_spec()['countries'] as $bucket){
    $countryId=(int)$bucket['country_id'];
    foreach($bucket['paths'] as $path){
        $n++;$hotelId=(int)preg_replace('/^.*-(\\d+)\\/$/','$1',(string)$path);
        $pageKey="hotel:$countryId:$hotelId";$cluster="fixture hotel $hotelId tours";
        $rows[]=['path'=>$path,'page_key'=>$pageKey,'query_cluster'=>$cluster,
            'demand'=>['page_key'=>$pageKey,'query_cluster'=>$cluster,'source_class'=>'manual_serp_review','source_ref'=>'fixture-demand-'.$n,'observed_at_epoch'=>$now-60,'status'=>'confirmed','serp_intent'=>'commercial'],
            'uniqueness'=>['page_key'=>$pageKey,'query_cluster'=>$cluster,'page_path'=>$path,'source_class'=>'manual_serp_review','source_ref'=>'fixture-uniq-'.$n,'observed_at_epoch'=>$now-60,'status'=>'confirmed','decision'=>'distinct','competing_paths'=>[]]];
    }
}
$tmp=tempnam(sys_get_temp_dir(),'seo-evidence-');file_put_contents($tmp,json_encode(['rows'=>$rows],JSON_THROW_ON_ERROR));
$cmd='php '.escapeshellarg(__DIR__.'/../v2/data/report-seo-hotel-pilot-opportunity-evidence-v1.php').' --input='.escapeshellarg($tmp).' --now-epoch='.$now.' --require-ready';
exec($cmd,$out,$code);unlink($tmp);if($code!==0)cli_fail('ready_exit');
$r=json_decode(implode("\n",$out),true);if(!is_array($r)||($r['ready_count']??0)!==9||($r['blocked_count']??1)!==0)cli_fail('ready_report');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)cli_fail('boundary_'.$flag);
if(($r['publication_candidates']??null)!==[]||($r['explicit_user_indexation_approval_required']??false)!==true)cli_fail('approval_boundary');
array_pop($rows);$tmp=tempnam(sys_get_temp_dir(),'seo-evidence-');file_put_contents($tmp,json_encode($rows,JSON_THROW_ON_ERROR));
$out=[];$cmd='php '.escapeshellarg(__DIR__.'/../v2/data/report-seo-hotel-pilot-opportunity-evidence-v1.php').' --input='.escapeshellarg($tmp).' --now-epoch='.$now.' --require-ready';exec($cmd,$out,$code);unlink($tmp);if($code===0)cli_fail('blocked_exit');
echo "SEO_HOTEL_PILOT_EVIDENCE_CLI_OK exact9=1 externalJson=1 publication=0 indexation=0\n";
