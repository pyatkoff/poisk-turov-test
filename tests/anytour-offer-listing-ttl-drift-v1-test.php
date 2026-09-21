<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/diagnostics/anytour_offer_listing_ttl_drift_v1.php';
function t(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$producer=<<<'SRC'
<?php
$expires = $dto['context']['expires_at'] ?? null;
$rows[] = ['expires_at' => gmdate('Y-m-d\\TH:i:s\\Z', $expires)];
SRC;
$ingest=<<<'SRC'
<?php
final class X { private const LOCAL_LISTING_TTL_SECONDS = 86400; private const MAX_PRODUCER_EXPIRY_SECONDS = 21600;
function f($now){$listingExpires=$now->setTimestamp($now->getTimestamp()+self::LOCAL_LISTING_TTL_SECONDS); return ['expires_at' => $listingExpires];}}
SRC;
$helper="<?php final class H { private const CONTEXT_TTL = 900; }";
$c=AnyTourOfferListingTtlDriftV1::sourceContract($producer,$ingest,$helper);
t($c['listing_ttl_seconds']===86400&&$c['source_context_ttl_seconds']===900,'source constants');
$base='2026-09-21T14:49:20Z';
$snap=['expected_ingest_sha256'=>str_repeat('a',64),'installed_ingest_sha256'=>str_repeat('b',64),'observations'=>[
 ['provider'=>'tourvisor','last_seen_at'=>$base,'expires_at'=>'2026-09-21T15:04:20Z','source_context_expires_at'=>'2026-09-21T15:04:20Z'],
 ['provider'=>'andromeda','last_seen_at'=>$base,'expires_at'=>'2026-09-22T14:49:20Z','source_context_expires_at'=>'2026-09-21T15:04:20Z'],
]];
$r=AnyTourOfferListingTtlDriftV1::classify($c,$snap);
t($r['observations'][0]['state']==='installed_ingest_drift','mismatched installed hash');
t($r['observations'][0]['listing_ttl_seconds']===900,'observed short listing');
t($r['observations'][1]['state']==='contract_consistent','24h listing short context');
$snap['installed_ingest_sha256']=$snap['expected_ingest_sha256'];
$r=AnyTourOfferListingTtlDriftV1::classify($c,$snap);
t($r['observations'][0]['state']==='bypass_writer_or_path','matching installed hash implies bypass');
unset($snap['installed_ingest_sha256']);
$r=AnyTourOfferListingTtlDriftV1::classify($c,$snap);
t($r['observations'][0]['state']==='installed_drift_or_bypass','unknown installed hash');
$bad=$snap;$bad['observations']=[['provider'=>'tourvisor','last_seen_at'=>$base,'expires_at'=>'2026-09-21T16:49:20Z','source_context_expires_at'=>'2026-09-21T15:04:20Z']];
t(AnyTourOfferListingTtlDriftV1::classify($c,$bad)['observations'][0]['state']==='listing_ttl_mismatch','other listing mismatch');
$bad=$snap;$bad['observations']=[['provider'=>'tourvisor','last_seen_at'=>$base,'expires_at'=>'2026-09-22T14:49:20Z','source_context_expires_at'=>'2026-09-21T15:49:20Z']];
t(AnyTourOfferListingTtlDriftV1::classify($c,$bad)['observations'][0]['state']==='source_context_ttl_mismatch','long source context');
foreach([['expires_at'=>$base],['source_context_expires_at'=>$base],['last_seen_at'=>'bad']] as $patch){
 $row=['provider'=>'tourvisor','last_seen_at'=>$base,'expires_at'=>'2026-09-22T14:49:20Z','source_context_expires_at'=>'2026-09-21T15:04:20Z'];
 $row=array_replace($row,$patch);$thrown=false;
 try{AnyTourOfferListingTtlDriftV1::classify($c,['observations'=>[$row]]);}catch(InvalidArgumentException){$thrown=true;}
 t($thrown,'invalid observation');
}
$thrown=false;try{AnyTourOfferListingTtlDriftV1::sourceContract($producer,str_replace('86400','900',$ingest),$helper);}catch(DomainException){$thrown=true;}t($thrown,'collapsed source contract rejected');
echo "ANYTOUR_LISTING_TTL_DRIFT_OK checks=11 expected_listing=86400 expected_context=900 tourvisor_short=installed_drift_or_bypass andromeda_24h=consistent supplier=0 db=0 writes=0\n";
