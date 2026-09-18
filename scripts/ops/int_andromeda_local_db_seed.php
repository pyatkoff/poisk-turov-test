<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(2);}
if($argc!==3){fwrite(STDERR,"Usage: int_andromeda_local_db_seed.php <site-root> <source-sha>\n");exit(2);}

$site=realpath($argv[1]);
$source=$argv[2];
$runtime=realpath(dirname(__DIR__,2));
if($site===false||basename($site)!=='anytoour.ru'||$runtime===false
    ||!preg_match('/\A[a-f0-9]{40}\z/D',$source))throw new RuntimeException('ANDROMEDA_SEED_ROOT');

$private=dirname($site,2).'/.anytoour-andromeda/search3-preview.php';
if(!is_file($private)||is_link($private))throw new RuntimeException('ANDROMEDA_SEED_PRIVATE_CONFIG');

require_once $site.'/config.php';
$dbFile=is_file($site.'/data/db-v1.php')?$site.'/data/db-v1.php':$site.'/v2/data/db-v1.php';
require_once $dbFile;
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$count=static function(PDO $db):array{
    $one=static function(string $sql)use($db):int{return (int)$db->query($sql)->fetchColumn();};
    return[
        'andromeda_offers'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda'"),
        'andromeda_active'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND is_active=1"),
        'andromeda_ready'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='andromeda' AND final_price_ready=1"),
        'all_offers'=>$one('SELECT COUNT(*) FROM anytour_offers'),
    ];
};
$before=$count($db);

$collector=$runtime.'/scripts/ops/andromeda_local_offer_collect.php';
if(!is_file($collector)||is_link($collector))throw new RuntimeException('ANDROMEDA_SEED_COLLECTOR');

$originalArgv=$argv;$originalArgc=$argc;
$argv=[
    $collector,
    '--site-root='.$site,
    '--private-config='.$private,
    '--source-sha='.$source,
    '--departure=1','--country=4',
    '--date-from=2026-10-14','--date-to=2026-10-14',
    '--nights=7','--adults=2','--meal=7',
    '--generation=17171826','--max-captures=6',
];
$argc=count($argv);

ob_start();
try{
    require $collector;
    $raw=trim((string)ob_get_clean());
}catch(Throwable $error){
    ob_end_clean();$argv=$originalArgv;$argc=$originalArgc;throw $error;
}
$argv=$originalArgv;$argc=$originalArgc;
$collectorReceipt=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
if(!is_array($collectorReceipt)||($collectorReceipt['status']??null)!=='complete')
    throw new RuntimeException('ANDROMEDA_SEED_COLLECTOR_RECEIPT');

$after=$count($db);$delta=[];
foreach($before as $key=>$value)$delta[$key]=$after[$key]-$value;

// Immediate milestone is a truthful nonzero local Andromeda snapshot. Readiness is
// reported independently; a missing/ambiguous surcharge must never be coerced to zero.
$completed=$delta['andromeda_offers']>0
    && $delta['andromeda_active']>0
    && ($collectorReceipt['autosave_published']??false)===true;

$result=[
    'status'=>$completed?'completed':'failed_terminal_no_replay',
    'provider'=>'andromeda',
    'scope'=>[
        'departure_id'=>1,'country_id'=>4,'date_from'=>'2026-10-14','date_to'=>'2026-10-14',
        'nights'=>7,'adults'=>2,'meal'=>'AI',
    ],
    'before'=>$before,'after'=>$after,'delta'=>$delta,'collector'=>$collectorReceipt,
    'booking_calls'=>0,'lead_calls'=>0,'mapping_writes'=>0,'search3_publication'=>0,
    'production_webroot_writes'=>0,'replay_allowed'=>false,
];
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($completed?0:3);
