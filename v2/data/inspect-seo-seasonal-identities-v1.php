<?php
/**
 * Read-only exact identity inventory for currently unexpired month/resort-month snapshots.
 * No Tourvisor calls, writes, prices, hotel names or offer payloads are emitted.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/db-v1.php';
require_once dirname(__DIR__) . '/seo-seasonal-identity-v1.php';

function seasonal_identity_arg(array $argv,string $name):?string
{
    foreach($argv as $arg) if(str_starts_with($arg,'--'.$name.'=')) return trim(substr($arg,strlen($name)+3));
    return null;
}
$raw=seasonal_identity_arg($argv,'countries')??seasonal_identity_arg($argv,'country')??'';
$countries=[];
foreach(preg_split('/\s*,\s*/',$raw,-1,PREG_SPLIT_NO_EMPTY)?:[] as $value){
    $id=filter_var($value,FILTER_VALIDATE_INT);
    if($id!==false&&(int)$id>0)$countries[]=(int)$id;
}
$countries=array_values(array_unique($countries));
if($countries===[]){fwrite(STDERR,"Usage: php v2/data/inspect-seo-seasonal-identities-v1.php --countries=1,4,8\n");exit(2);}
$limit=(int)(seasonal_identity_arg($argv,'limit')??'500');
if($limit<1||$limit>5000){fwrite(STDERR,"SEO_SEASONAL_IDENTITY_FAIL:limit must be 1-5000\n");exit(2);}

$pdo=v2_data_db();
$placeholders=implode(',',array_fill(0,count($countries),'?'));
$sql="SELECT s.page_key,s.page_type,s.country_id,s.region_id,s.departure_id,s.departure_year,s.departure_month,
             s.offer_count,s.observed_at,s.expires_at,
             UNIX_TIMESTAMP(NOW()) AS evidence_checked_at_epoch,
             UNIX_TIMESTAMP(s.expires_at) AS expires_at_epoch,
             TIMESTAMPDIFF(SECOND,NOW(),s.expires_at) AS freshness_seconds
        FROM seo_offer_snapshots s
       WHERE s.country_id IN ($placeholders)
         AND s.page_type IN ('month','resort_month')
         AND s.expires_at > NOW()
         AND s.offer_count > 0
         AND s.currency='RUB'
       ORDER BY s.country_id,s.page_type,s.departure_year,s.departure_month,s.region_id,s.departure_id
       LIMIT $limit";
$stmt=$pdo->prepare($sql);
$stmt->execute($countries);
$out=v2_seo_seasonal_identity_inventory($stmt->fetchAll(PDO::FETCH_ASSOC)?:[]);
$out['requested_country_ids']=$countries;
$out['limit']=$limit;
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
$require=(int)(seasonal_identity_arg($argv,'require-identities')??'0');
if($require>0&&($out['identity_count']??0)<$require){
    fwrite(STDERR,"SEO_SEASONAL_IDENTITY_FAIL:require_identities expected={$require} actual=".($out['identity_count']??0)."\n");exit(3);
}
