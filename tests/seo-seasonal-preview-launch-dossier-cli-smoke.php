<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-seasonal-identity-v1.php';
function seasonal_dossier_cli_smoke_fail(string $m):never{fwrite(STDERR,"SEO_SEASONAL_DOSSIER_CLI_SMOKE_FAIL:$m\n");exit(1);}
$serp=__DIR__.'/../v2/data/evidence/seo-seasonal-manual-serp-2026-09-03.json';
$snapshot=json_decode((string)file_get_contents($serp),true,512,JSON_THROW_ON_ERROR);$now=(int)$snapshot['observed_at_epoch']+60;
$cli=__DIR__.'/../v2/data/report-seo-seasonal-preview-launch-dossier-v1.php';
$out=[];$code=0;exec('php '.escapeshellarg($cli).' --serp-evidence='.escapeshellarg($serp).' --now-epoch='.$now.' --require-go-review 2>&1',$out,$code);
if($code!==3)seasonal_dossier_cli_smoke_fail('missing_identity_exit_'.$code);
$json=json_decode(implode("\n",$out),true);if(!is_array($json)||($json['hold_count']??0)!==4)seasonal_dossier_cli_smoke_fail('missing_identity_hold');
$checked=$now-30;$expires=$now+3600;$freshness=$expires-$checked;
$make=static fn(string $type,string $key,int $country,?int $region,int $month,int $offers):array=>['page_type'=>$type,'page_key'=>$key,'country_id'=>$country,'region_id'=>$region,'departure_id'=>1,'departure_year'=>2026,'departure_month'=>$month,'offer_count'=>$offers,'freshness_seconds'=>$freshness,'evidence_checked_at_epoch'=>$checked,'expires_at_epoch'=>$expires,'observed_at'=>gmdate('c',$checked),'expires_at'=>gmdate('c',$expires)];
$inventory=v2_seo_seasonal_identity_inventory([
 $make('resort_month','resort_month:1:4:20:2026-09',4,20,9,3),
 $make('month','month:1:8:2026-09',8,null,9,2),
 $make('resort_month','resort_month:1:4:20:2026-10',4,20,10,3),
 $make('month','month:1:8:2026-10',8,null,10,2),
],$now);
$tmp=tempnam(sys_get_temp_dir(),'seasonal-identity-');file_put_contents($tmp,json_encode($inventory,JSON_THROW_ON_ERROR));
$out=[];$code=0;exec('php '.escapeshellarg($cli).' --serp-evidence='.escapeshellarg($serp).' --identity-evidence='.escapeshellarg($tmp).' --now-epoch='.$now.' --require-go-review 2>&1',$out,$code);@unlink($tmp);
if($code!==0)seasonal_dossier_cli_smoke_fail('go_exit_'.$code);
$json=json_decode(implode("\n",$out),true);if(!is_array($json)||($json['go_review_count']??0)!==4||($json['publication_candidates']??null)!==[])seasonal_dossier_cli_smoke_fail('go_output');
echo "SEO_SEASONAL_DOSSIER_CLI_OK holdWithoutInventory=4 goWithInventory=4 publication=0\n";
