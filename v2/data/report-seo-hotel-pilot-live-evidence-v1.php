<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/seo-hotel-pilot-live-evidence-v1.php';
$fetch=static function(string $url):array{
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'AnyTour-SEO-Hotel-Pilot-Evidence/1.0']);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=(string)curl_error($ch);curl_close($ch);
    return ['status'=>$status,'body'=>$body===false?'':$body,'error'=>$error];
};
$result=v2_seo_collect_hotel_pilot_live_evidence($fetch,time());
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
exit(($result['state']??'')==='review_only_hotel_pilot_live_evidence_ready'?0:3);
