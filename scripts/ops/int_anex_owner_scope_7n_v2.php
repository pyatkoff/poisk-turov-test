<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'||$argc!==2){fwrite(STDERR,"CLI only\n");exit(2);}
$site=realpath($argv[1]);$runtime=realpath(dirname(__DIR__,2));
if($site===false||!is_dir($site)||basename($site)!=='anytoour.ru'||$runtime===false)throw new RuntimeException('ANEX_OWNER_SCOPE_7N_V2_ROOT');

$private=dirname($site,2).'/.anytoour-anex/search3-preview.php';
if(!is_file($private)||is_link($private))throw new RuntimeException('ANEX_OWNER_SCOPE_7N_V2_PRIVATE_CONFIG');
require_once $private;
if(!defined('ANEX_API_TOKEN')||!defined('ANEX_B2B_TOKEN'))throw new RuntimeException('ANEX_OWNER_SCOPE_7N_V2_PRIVATE_CONFIG');

$config=$site.'/config.php';if(!is_file($config)||is_link($config))throw new RuntimeException('ANEX_OWNER_SCOPE_7N_V2_PROJECT_CONFIG');
require_once $config;
$dbFile=is_file($site.'/data/db-v1.php')?$site.'/data/db-v1.php':$site.'/v2/data/db-v1.php';
require_once $dbFile;
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

$counts=static function(PDO $db):array{
    $one=static fn(string $sql):int=>(int)$db->query($sql)->fetchColumn();
    return[
        'programs'=>$one('SELECT COUNT(*) FROM anytour_anex_programs'),
        'contexts'=>$one('SELECT COUNT(*) FROM anytour_anex_program_contexts'),
        'apd'=>$one('SELECT COUNT(*) FROM anytour_anex_apd_rates'),
        'anex_rows'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex'"),
        'anex_active'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1"),
        'anex_ready'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND final_price_ready=1"),
        'anex_active_ready'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1 AND final_price_ready=1"),
        'anex_confirmation'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND final_price_ready=0 AND final_price_verified=0"),
        'anex_active_confirmation'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1 AND final_price_ready=0 AND final_price_verified=0"),
        'anex_active_confirmation_state'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1 AND final_price_ready=0 AND final_price_verified=0 AND JSON_VALID(payload_json)=1 AND JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.listingPriceState'))='search_price_confirmation_required'"),
        'all_offers'=>$one('SELECT COUNT(*) FROM anytour_offers'),
    ];
};
$before=$counts($db);

putenv('ANYTOUR_PROJECT_ROOT='.$site);
putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE='.$runtime.'/v2/data/anytour-offer-snapshot-ingest-v1.php');
$collectorScript=$runtime.'/scripts/ops/anex_local_offer_collect.php';
if(!is_file($collectorScript)||is_link($collectorScript))throw new RuntimeException('ANEX_OWNER_SCOPE_7N_V2_COLLECTOR');

$oldArgv=$argv;$oldArgc=$argc;
$argv=[
    $collectorScript,'--departure=1','--country=4',
    '--date-from=2026-09-18','--date-to=2026-09-24',
    '--nights=7','--adults=2','--meal=7',
    '--max-expands=120','--max-apd=600','--generation=25061862',
];
$argc=count($argv);
ob_start();
try{require $collectorScript;$raw=trim((string)ob_get_clean());}
catch(Throwable $e){ob_end_clean();$argv=$oldArgv;$argc=$oldArgc;throw $e;}
$argv=$oldArgv;$argc=$oldArgc;
$collector=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
if(!is_array($collector)||($collector['status']??null)!=='complete')throw new RuntimeException('ANEX_OWNER_SCOPE_7N_V2_COLLECTOR_RECEIPT');

$after=$counts($db);$delta=[];foreach($before as $k=>$v)$delta[$k]=$after[$k]-$v;
if(($collector['regular_concrete_candidates']??0)>0 && $delta['anex_confirmation']<1){
    throw new RuntimeException('ANEX_REGULAR_NOT_PERSISTED');
}
if($after['anex_active_confirmation']!==$after['anex_active_confirmation_state']){
    throw new RuntimeException('ANEX_REGULAR_STATE_MISMATCH');
}
$result=[
    'status'=>'completed','provider'=>'anex',
    'scope'=>['departure_id'=>1,'country_id'=>4,'date_from'=>'2026-09-18','date_to'=>'2026-09-24','nights'=>7,'adults'=>2,'meal'=>'7'],
    'before'=>$before,'after'=>$after,'delta'=>$delta,'collector'=>$collector,
    'booking_calls'=>0,'lead_calls'=>0,'mapping_writes'=>0,'search3_publication'=>0,'production_webroot_writes'=>0,'replay_allowed'=>false,
];
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
