<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-postlaunch-feedback-v1.php';
function feedback_fail(string $m):never{fwrite(STDERR,"SEO_POSTLAUNCH_FEEDBACK_FAIL:$m\n");exit(1);}
$now=1800000000;
$cohort=v2_seo_postlaunch_feedback_cohort();
if(($cohort['domain']??'')!=='anytoour.ru'||($cohort['path_count']??0)!==8)feedback_fail('cohort');
foreach($cohort['paths'] as $path)if(str_contains($path,'/hotel/'))feedback_fail('hotel_in_cohort');

$base=[
  'domain'=>'anytoour.ru',
  'cohort_id'=>$cohort['cohort_id'],
  'launch_source_sha'=>$cohort['launch_source_sha'],
];
$rows=[];$n=0;
foreach($cohort['paths'] as $path){
  $n++;
  $rows[]=[
    'path'=>$path,
    'source_class'=>'google_search_console',
    'source_ref'=>'fixture-gsc-'.$n,
    'observed_at_epoch'=>$now-60,
    'indexation_state'=>'indexed',
    'metrics'=>['impressions'=>100+$n,'clicks'=>10,'ctr'=>0.1,'avg_position'=>5.5],
    'observed_queries'=>['fixture query '.$n],
    'cannibalization_state'=>'none',
    'competing_paths'=>[],
  ];
}
$r=v2_seo_postlaunch_feedback_validate($base+['rows'=>$rows],$now);
if(($r['state']??'')!=='postlaunch_feedback_valid'||($r['measured_count']??0)!==8)feedback_fail('measured');
if(($r['publication_candidates']??null)!==[])feedback_fail('publication_candidates');
foreach(['automatic_expand_allowed','automatic_noindex_allowed','hotel_tours_indexation_allowed','hotel_tours_sitemap_allowed','search_contract_changes','tourvisor_contract_changes','metrika_contract_changes'] as $flag)if(($r[$flag]??true)!==false)feedback_fail('boundary_'.$flag);

// Missing metrics stay explicit nulls rather than being coerced to zero.
$unknownRow=[
  'path'=>$cohort['paths'][0],
  'source_class'=>'yandex_webmaster',
  'source_ref'=>'fixture-empty-export',
  'observed_at_epoch'=>$now-60,
  'indexation_state'=>'unknown',
  'metrics'=>[],
  'cannibalization_state'=>'unknown',
];
$u=v2_seo_postlaunch_feedback_validate($base+['rows'=>[$unknownRow]],$now);
if(($u['state']??'')!=='postlaunch_feedback_valid'||($u['unknown_count']??0)!==1)feedback_fail('unknown_state');
$m=$u['rows'][0]['metrics']??[];
foreach(['impressions','clicks','ctr','avg_position'] as $key)if(!array_key_exists($key,$m)||$m[$key]!==null)feedback_fail('missing_metric_not_null_'.$key);
if(($u['missing_metrics_are_unknown_not_zero']??false)!==true)feedback_fail('unknown_policy');

// Manual SERP observations cannot masquerade as analytics exports.
$manual=$unknownRow;
$manual['source_class']='manual_serp_review';
$manual['source_ref']='fixture-manual';
$manual['metrics']=['impressions'=>123];
$bad=v2_seo_postlaunch_feedback_validate($base+['rows'=>[$manual]],$now);
if(($bad['state']??'')!=='postlaunch_feedback_invalid')feedback_fail('manual_metrics_accepted');

// Hotel-tour rows are never admitted to the launched feedback cohort.
$hotel=$unknownRow;
$hotel['path']='/country/maldives/hotel/the-westin-maldives-miriandhoo-resort-65108/';
$bad=v2_seo_postlaunch_feedback_validate($base+['rows'=>[$hotel]],$now);
if(($bad['state']??'')!=='postlaunch_feedback_invalid')feedback_fail('hotel_accepted');

// Stale evidence is retained as stale, not silently treated as current/zero.
$stale=$unknownRow;$stale['observed_at_epoch']=$now-32*86400;
$s=v2_seo_postlaunch_feedback_validate($base+['rows'=>[$stale]],$now);
if(($s['stale_count']??0)!==1||($s['rows'][0]['decision']??'')!=='HOLD')feedback_fail('stale');

// Canonical row sorting makes fingerprints stable across input order.
$rev=$rows;shuffle($rev);
$a=v2_seo_postlaunch_feedback_validate($base+['rows'=>$rows],$now);
$b=v2_seo_postlaunch_feedback_validate($base+['rows'=>$rev],$now);
if(($a['feedback_sha256']??'')!==($b['feedback_sha256']??''))feedback_fail('fingerprint_order');

echo "SEO_POSTLAUNCH_FEEDBACK_OK cohort=8 measured=8 unknownNotZero=1 hotelTours=0 autoExpand=0\n";
