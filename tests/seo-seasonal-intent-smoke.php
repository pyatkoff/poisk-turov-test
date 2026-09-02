<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-seasonal-intent-v1.php';
function intent_fail(string $m):void{fwrite(STDERR,"SEO_SEASONAL_INTENT_FAIL:$m\n");exit(1);}
$base=[
 'page_key'=>'resort_month:1:4:20:2026-09',
 'search_state'=>['country'=>4,'region'=>20],
 'publication_allowed'=>false,'indexation_allowed'=>false,'sitemap_allowed'=>false,'canonical_allowed'=>false,'route_launch_allowed'=>false,'publication_candidates'=>[],
];
$commercial=$base+['path'=>'/_preview/seo2/seasonal/antalya-september/','page_role'=>'commercial_tour_landing','search_intent'=>'commercial_transactional'];
$guide=$base+['path'=>'/_preview/seo2/seasonal/antalya-september-guide/','page_role'=>'informational_guide','search_intent'=>'informational'];
$r=v2_seo_seasonal_intent_registry(['commercial'=>$commercial,'guide'=>$guide]);
if(($r['state']??'')!=='review_intent_registry_ready'||($r['page_count']??0)!==2) intent_fail('registry');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_allowed','route_launch_allowed'] as $f) if(($r[$f]??true)!==false) intent_fail('boundary_'.$f);
if(($r['publication_candidates']??null)!==[]) intent_fail('candidates');
$bad=$commercial;$bad['search_intent']='informational';
if((v2_seo_seasonal_intent_contract($bad)['review_ready']??true)!==false) intent_fail('role_intent');
$leak=$commercial;$leak['indexation_allowed']=true;
if((v2_seo_seasonal_intent_contract($leak)['review_ready']??true)!==false) intent_fail('index_leak');
$wrong=$commercial;$wrong['search_state']['region']=21;
if((v2_seo_seasonal_intent_contract($wrong)['review_ready']??true)!==false) intent_fail('region');
$dupe=$guide;$dupe['path']=$commercial['path'];
if((v2_seo_seasonal_intent_registry(['commercial'=>$commercial,'guide'=>$dupe])['review_ready']??true)!==false) intent_fail('duplicate_path');
echo "SEO_SEASONAL_INTENT_OK pages=2 commercial=1 informational=1 publication=0 indexation=0\n";
