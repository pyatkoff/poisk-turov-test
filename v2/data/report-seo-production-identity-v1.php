<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/seo-production-identity-collector-v1.php';

$fetch=static function(string $url):array{
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'AnyTour-SEO-Identity/1.0']);
    $body=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
    $error=(string)curl_error($ch);
    curl_close($ch);
    if($body===false)$body='';
    return ['status'=>$status,'body'=>$body,'error'=>$error];
};
$result=v2_seo_collect_production_identity($fetch,time());
echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
exit(($result['integrity_ok']??false)===true?0:2);
