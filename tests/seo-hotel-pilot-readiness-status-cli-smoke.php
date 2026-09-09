<?php
declare(strict_types=1);

require_once __DIR__.'/../v2/seo-hotel-launch-pilot-v1.php';

function hotel_status_cli_fail(string $message): never
{
    fwrite(STDERR,"SEO_HOTEL_PILOT_STATUS_CLI_SMOKE_FAIL:$message\n");
    exit(1);
}

$now=1788391200;$checks=[];$dossier=[];
foreach(v2_seo_hotel_launch_pilot_spec()['countries'] as $bucket){
    foreach($bucket['paths'] as $path){
        if(!preg_match('/-(\d+)\/$/',(string)$path,$m))hotel_status_cli_fail('hotel_id');
        $checks[]=['path'=>$path,'country_id'=>(int)$bucket['country_id'],'hotel_id'=>(int)$m[1],'identity_verified'=>true,'fresh_offer_evidence'=>false,'review_status_ok'=>true,'noindex_ok'=>true,'out_of_sitemap_ok'=>true];
        $dossier[]=['path'=>$path,'country_id'=>(int)$bucket['country_id'],'quality_score'=>100,'captured_at_epoch'=>$now-60,'source_ref'=>'fixture://live/'.(int)$m[1],'fresh'=>true];
    }
}
$live=['observed_at_epoch'=>$now-60,'checks'=>$checks,'dossier'=>['state'=>'review_only_hotel_pilot_evidence_ready','rows'=>$dossier,'dossier_sha256'=>hash('sha256','cli-fixture')]];
$tmp=tempnam(sys_get_temp_dir(),'seo-hotel-status-');
file_put_contents($tmp,json_encode($live,JSON_THROW_ON_ERROR));
$cli=__DIR__.'/../v2/data/report-seo-hotel-pilot-readiness-status-v1.php';
$out=[];$code=0;
exec('php '.escapeshellarg($cli).' --live-evidence='.escapeshellarg($tmp).' --now-epoch='.$now.' --require-status 2>&1',$out,$code);
if($code!==0)hotel_status_cli_fail('ready_exit_'.$code);
$json=json_decode(implode("\n",$out),true);
if(!is_array($json)||($json['state']??'')!=='review_only_hotel_pilot_status_complete')hotel_status_cli_fail('ready_state');
if(($json['hotel_count']??0)!==9||($json['state_counts']['evidence_incomplete']??0)!==9)hotel_status_cli_fail('missing_evidence_baseline');
if(($json['publication_candidates']??null)!==[]||($json['automatic_execution_allowed']??true)!==false)hotel_status_cli_fail('safety');

$broken=$live;array_pop($broken['checks']);file_put_contents($tmp,json_encode($broken,JSON_THROW_ON_ERROR));
$out=[];$code=0;
exec('php '.escapeshellarg($cli).' --live-evidence='.escapeshellarg($tmp).' --now-epoch='.$now.' --require-status 2>&1',$out,$code);
@unlink($tmp);
if($code!==3)hotel_status_cli_fail('blocked_exit_'.$code);
$json=json_decode(implode("\n",$out),true);
if(!is_array($json)||($json['state']??'')!=='review_only_hotel_pilot_status_blocked')hotel_status_cli_fail('blocked_state');

echo "SEO_HOTEL_PILOT_STATUS_CLI_OK hotels=9 fail_closed=1 publication=0\n";
