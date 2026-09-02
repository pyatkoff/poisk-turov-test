<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-ds2-reference-acceptance-v1.php';
function fail_ds2_cli(string $x):never{fwrite(STDERR,"SEO_DS2_RENDER_CLI_FAIL:$x\n");exit(1);} 
$now=1788376500;$d=v2_seo_ds2_reference_acceptance_dossier();$rows=[];
foreach(['destination','hotel_tours'] as $family)foreach([375,430,768,1024,1440] as $width){$row=['family'=>$family,'path'=>$d['reference_pages'][$family]['path'],'viewport_width'=>$width,'captured_at_epoch'=>$now-60,'source_ref'=>'fixture://render/'.$family.'/'.$width,'http_ok'=>true,'no_horizontal_overflow'=>true,'primary_action_height_ok'=>true,'search_handoff_contract_ok'=>true,'editorial_hierarchy_ok'=>true,'fresh_claim_boundary_ok'=>true];if($family==='hotel_tours')$row+=['review_status_ok'=>true,'noindex_ok'=>true,'out_of_sitemap_ok'=>true,'publication_candidate_absent'=>true];$rows[]=$row;}
$tmp=tempnam(sys_get_temp_dir(),'ds2-render-');file_put_contents($tmp,json_encode(['rows'=>$rows],JSON_THROW_ON_ERROR));
$cli=__DIR__.'/../v2/data/report-seo-ds2-render-evidence-v1.php';$cmd='php '.escapeshellarg($cli).' --evidence='.escapeshellarg($tmp).' --now-epoch='.$now.' --require-ready 2>&1';exec($cmd,$out,$code);@unlink($tmp);if($code!==0)fail_ds2_cli('ready_exit');$json=json_decode(implode("\n",$out),true);if(($json['state']??'')!=='review_only_ds2_render_evidence_ready'||($json['expected_capture_count']??0)!==10)fail_ds2_cli('ready_state');
$tmp=tempnam(sys_get_temp_dir(),'ds2-render-');$rows[5]['noindex_ok']=false;file_put_contents($tmp,json_encode(['rows'=>$rows],JSON_THROW_ON_ERROR));$out=[];$code=0;exec('php '.escapeshellarg($cli).' --evidence='.escapeshellarg($tmp).' --now-epoch='.$now.' --require-ready 2>&1',$out,$code);@unlink($tmp);if($code!==3)fail_ds2_cli('hotel_boundary');
echo "SEO_DS2_RENDER_EVIDENCE_CLI_OK captures=10 hotelTours=noindex_review_only\n";
