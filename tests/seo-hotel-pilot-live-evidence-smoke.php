<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/seo-hotel-pilot-live-evidence-v1.php';
function live_pilot_fail(string $x): never { fwrite(STDERR,"SEO_HOTEL_PILOT_LIVE_FAIL:$x\n"); exit(1); }
$now=1788385000;$missingOffer=false;
$fetch=static function(string $url) use(&$missingOffer):array{
    if(str_ends_with($url,'/sitemap.xml'))return ['status'=>200,'body'=>'<urlset></urlset>'];
    $body='<meta name="robots" content="noindex,follow,max-image-preview:large"><link rel="canonical" href="'.htmlspecialchars($url,ENT_QUOTES).'">';
    if(!$missingOffer)$body.='<section class="sp-offer-snapshot"><article class="sp-offer-item sp-offer-item--hotel"></article></section>';
    return ['status'=>200,'body'=>$body];
};
$r=v2_seo_collect_hotel_pilot_live_evidence($fetch,$now);
if(($r['state']??'')!=='review_only_hotel_pilot_live_evidence_ready')live_pilot_fail('ready');
if(($r['dossier']['observed_hotel_count']??0)!==9||($r['manifest']['family_quality_floor']??0)!==100)live_pilot_fail('quality');
foreach(['publication_allowed','indexation_allowed','sitemap_allowed','canonical_launch_allowed','route_launch_allowed'] as $flag)if(($r[$flag]??true)!==false)live_pilot_fail($flag);
if(($r['publication_candidates']??null)!==[])live_pilot_fail('publication_candidates');
$missingOffer=true;$blocked=v2_seo_collect_hotel_pilot_live_evidence($fetch,$now);
if(($blocked['state']??'')!=='review_only_hotel_pilot_live_evidence_blocked')live_pilot_fail('missing_offer_not_blocked');
echo "SEO_HOTEL_PILOT_LIVE_OK hotels=9 quality=100 publication=0 missing_offer_blocks=1\n";
