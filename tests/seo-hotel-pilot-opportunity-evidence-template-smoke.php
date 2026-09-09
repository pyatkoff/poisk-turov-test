<?php
declare(strict_types=1);
function template_fail(string $m):void{fwrite(STDERR,"SEO_HOTEL_PILOT_EVIDENCE_TEMPLATE_FAIL:$m\n");exit(1);}
exec('php '.escapeshellarg(__DIR__.'/../v2/data/report-seo-hotel-pilot-opportunity-evidence-template-v1.php'),$out,$code);if($code!==0)template_fail('exit');
$r=json_decode(implode("\n",$out),true);if(!is_array($r)||($r['state']??'')!=='review_only_evidence_collection_template'||count($r['rows']??[])!==9)template_fail('shape');
$paths=[];foreach($r['rows'] as $row){$path=(string)($row['path']??'');if($path===''||isset($paths[$path]))template_fail('path');$paths[$path]=true;if(($row['query_cluster']??null)!=='')template_fail('invented_cluster');if(($row['demand']['status']??'')!=='unknown'||($row['uniqueness']['decision']??'')!=='unknown')template_fail('invented_evidence');}
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)template_fail('boundary_'.$flag);
if(($r['publication_candidates']??null)!==[]||($r['explicit_user_indexation_approval_required']??false)!==true)template_fail('approval_boundary');
echo "SEO_HOTEL_PILOT_EVIDENCE_TEMPLATE_OK exact9=1 fabricatedEvidence=0 publication=0 indexation=0\n";
