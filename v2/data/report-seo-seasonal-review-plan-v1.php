<?php
/**
 * Review-only CLI for turning fresh production seasonal evidence into an explicit
 * small plan against a verified destination family. No routes/copy/feed/indexing.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/seo-seasonal-coverage-readiness-v1.php';
require_once dirname(__DIR__) . '/seo-seasonal-family-binding-v1.php';
require_once dirname(__DIR__) . '/seo-seasonal-review-plan-v1.php';
require_once dirname(__DIR__) . '/seo-content-pilot-turkey-v1.php';
require_once dirname(__DIR__) . '/seo-content-pilot-kemer-v1.php';
require_once dirname(__DIR__) . '/seo-content-pilot-antalya-v1.php';
require_once dirname(__DIR__) . '/seo-content-pilot-side-v1.php';
require_once dirname(__DIR__) . '/seo-content-pilot-belek-v1.php';
require_once dirname(__DIR__) . '/seo-content-pilot-alanya-v1.php';

function seasonal_plan_arg(array $argv,string $name):?string
{
    foreach($argv as $arg) if(str_starts_with($arg,'--'.$name.'=')) return trim(substr($arg,strlen($name)+3));
    return null;
}
function seasonal_plan_json(string $path,string $label):array
{
    $raw=@file_get_contents($path);
    if($raw===false||trim($raw)==='') throw new InvalidArgumentException($label.' input is empty');
    $decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($decoded)) throw new InvalidArgumentException($label.' input must be an object');
    return $decoded;
}
function seasonal_plan_family(string $family):array
{
    if($family!=='turkey') throw new InvalidArgumentException('Unsupported verified seasonal family');
    return [
        v2_seo_content_pilot_turkey(),
        [v2_seo_content_pilot_antalya(),v2_seo_content_pilot_kemer(),v2_seo_content_pilot_belek(),v2_seo_content_pilot_side(),v2_seo_content_pilot_alanya()],
    ];
}

try {
    $readinessPath=seasonal_plan_arg($argv,'readiness')??'';
    $identitiesPath=seasonal_plan_arg($argv,'identities')??'';
    $family=seasonal_plan_arg($argv,'family')??'';
    $rawKeys=seasonal_plan_arg($argv,'page-keys')??'';
    if($readinessPath===''||$identitiesPath===''||$family===''||$rawKeys==='') {
        throw new InvalidArgumentException('Usage requires --readiness --identities --family --page-keys');
    }
    [$country,$resorts]=seasonal_plan_family($family);
    $countryId=(int)($country['data']['search_state']['country']??0);
    if($countryId<=0) throw new InvalidArgumentException('Verified family has no country identity');

    $readiness=seasonal_plan_json($readinessPath,'readiness');
    $inventory=seasonal_plan_json($identitiesPath,'identities');
    if(($inventory['state']??'')!=='review_only_seasonal_identity_inventory') throw new InvalidArgumentException('Identity inventory state is invalid');
    if(($inventory['publication_allowed']??true)!==false||($inventory['copy_allowed']??true)!==false||($inventory['publication_candidates']??null)!==[]) {
        throw new InvalidArgumentException('Identity inventory crossed publication boundary');
    }

    $filtered=$inventory;
    $filtered['identities']=array_values(array_filter($inventory['identities']??[],static fn($row):bool=>is_array($row)&&(int)($row['country_id']??0)===$countryId));
    $filtered['identity_count']=count($filtered['identities']);
    $filtered['blocked']=[];
    $filtered['blocked_count']=0;
    if($filtered['identity_count']===0) throw new InvalidArgumentException('No identities for verified family country');

    $policy=[
        'country_id'=>$countryId,
        'min_month_identities'=>(int)(seasonal_plan_arg($argv,'min-month-identities')??'1'),
        'min_resort_month_identities'=>(int)(seasonal_plan_arg($argv,'min-resort-month-identities')??'1'),
        'min_freshness_seconds'=>(int)(seasonal_plan_arg($argv,'min-freshness-seconds')??'1800'),
        'min_offers_per_snapshot'=>(int)(seasonal_plan_arg($argv,'min-offers-per-snapshot')??'1'),
    ];
    $coverage=v2_seo_seasonal_coverage_assess($readiness,$policy);
    if(($coverage['review_ready']??false)!==true) throw new InvalidArgumentException('Seasonal coverage is not review-ready: '.implode(',',array_map('strval',$coverage['errors']??[])));
    $binding=v2_seo_seasonal_family_binding($country,$resorts,$filtered);
    $keys=array_values(array_filter(array_map('trim',preg_split('/\s*,\s*/',$rawKeys,-1,PREG_SPLIT_NO_EMPTY)?:[]),static fn(string $v):bool=>$v!==''));
    $maxItems=(int)(seasonal_plan_arg($argv,'max-items')??'12');
    $plan=v2_seo_seasonal_review_plan($coverage,$binding,$keys,null,$maxItems);
    $plan['family']=$family;
    $plan['source_identity_count']=$filtered['identity_count'];
    $plan['family_bound_count']=(int)($binding['bound_count']??0);
    $plan['family_blocked_count']=(int)($binding['blocked_count']??0);
    $plan['coverage_score']=(int)($coverage['score']??0);
    echo json_encode($plan,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
    $require=(int)(seasonal_plan_arg($argv,'require-items')??'0');
    if($require>0&&($plan['item_count']??0)<$require){fwrite(STDERR,"SEO_SEASONAL_PLAN_CLI_FAIL:require_items expected={$require} actual=".($plan['item_count']??0)."\n");exit(3);}
} catch(Throwable $e) {
    fwrite(STDERR,'SEO_SEASONAL_PLAN_CLI_FAIL:'.$e->getMessage()."\n");
    exit(2);
}
