<?php
declare(strict_types=1);

$root=realpath(__DIR__.'/../..');
if($root===false) throw new RuntimeException('ANEX_PREWARM_ROOT');
require_once $root.'/app/integrations/anex-apd-prewarm.php';
require_once $root.'/app/integrations/anex-additional-prices-client.php';
$dbFile=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';
if(!is_file($dbFile)) throw new RuntimeException('ANEX_PREWARM_DB');
require_once $dbFile;
$home=(string)getenv('HOME');
$private=$home.'/.anytoour-anex/search3-preview.php';
if(is_file($private)) require_once $private;
if(is_file($root.'/config.php')) require_once $root.'/config.php';

function apw_arg(array $argv,string $name,?string $default=null):?string{
    $prefix='--'.$name.'=';
    foreach(array_slice($argv,1) as $arg)if(strncmp($arg,$prefix,strlen($prefix))===0)return substr($arg,strlen($prefix));
    return $default;
}
function apw_int(array $argv,string $name,int $default,int $min,int $max):int{
    $v=apw_arg($argv,$name,(string)$default);
    if(!is_string($v)||!preg_match('/\A[0-9]+\z/D',$v))throw new InvalidArgumentException('ANEX_PREWARM_ARG_'.strtoupper(str_replace('-','_',$name)));
    $n=(int)$v;if($n<$min||$n>$max)throw new InvalidArgumentException('ANEX_PREWARM_ARG_'.strtoupper(str_replace('-','_',$name)));return $n;
}
function apw_date(?string $v,string $fallback):DateTimeImmutable{
    $value=$v?:$fallback;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC'));
    if(!$d||$d->format('Y-m-d')!==$value)throw new InvalidArgumentException('ANEX_PREWARM_ARG_DATE');return $d;
}

$tz=new DateTimeZone('Europe/Moscow');
$localToday=(new DateTimeImmutable('now',$tz))->format('Y-m-d');
$dateFrom=apw_date(apw_arg($argv,'date-from'),$localToday);
$dateTo=apw_date(apw_arg($argv,'date-to'),$dateFrom->modify('+60 days')->format('Y-m-d'));
$departure=apw_int($argv,'departure-id',1,1,999999999);
$country=apw_int($argv,'country-id',4,1,999999999);
$seenHours=apw_int($argv,'seen-hours',336,1,24*90);
$limit=apw_int($argv,'limit',180,1,500);
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
$seenSince=$now->modify('-'.$seenHours.' hours');
$token=trim((string)getenv('ANEX_B2B_TOKEN'));
if($token===''&&defined('ANEX_B2B_TOKEN')&&is_string(ANEX_B2B_TOKEN))$token=trim(ANEX_B2B_TOKEN);
if($token==='')throw new RuntimeException('ANEX_PREWARM_TOKEN');
$db=v2_data_db();
if(!$db instanceof PDO)throw new RuntimeException('ANEX_PREWARM_DB');

$actualRequests=0;$logicalReads=0;$lastLogicalAt=0.0;
$result=AnyTourAnexApdPrewarmV1::run(
    $db,$departure,$country,$dateFrom,$dateTo,$seenSince,$now,$limit,
    static function(array $criteria) use ($token,&$actualRequests,&$logicalReads,&$lastLogicalAt):array{
        if($lastLogicalAt>0){
            $wait=1.05-(microtime(true)-$lastLogicalAt);
            if($wait>0)usleep((int)ceil($wait*1000000));
        }
        $lastLogicalAt=microtime(true);++$logicalReads;
        $client=new AnyTourAnexAdditionalPricesClient($token);
        try{
            $payload=$client->additionalPricesDaily([
                'page'=>1,'pageSize'=>10,'tour'=>$criteria['supplier_program_id'],
                'dateBeg'=>$criteria['date_beg'],'nights'=>$criteria['nights'],'currency'=>$criteria['supplier_currency_id'],
            ]);
            $actualRequests+=$client->requestsMade();
            return $payload;
        }catch(Throwable $error){
            $actualRequests+=$client->requestsMade();
            throw $error;
        }
    }
);
$result['logical_reads']=$logicalReads;
$result['supplier_requests']=$actualRequests;
$result['departure_id']=$departure;$result['country_id']=$country;
$result['date_from']=$dateFrom->format('Y-m-d');$result['date_to']=$dateTo->format('Y-m-d');
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
exit(($result['status']??null)==='complete'?0:1);
