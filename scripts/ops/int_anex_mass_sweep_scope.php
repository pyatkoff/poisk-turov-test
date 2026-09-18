<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||$argc!==5){fwrite(STDERR,"CLI only\n");exit(2);}
$site=realpath($argv[1]);$runtime=realpath($argv[2]);$nights=(int)$argv[3];$generation=(int)$argv[4];
if($site===false||basename($site)!=='anytoour.ru'||$runtime===false||$nights<1||$nights>28||$generation<1)throw new RuntimeException('ANEX_SWEEP_INPUT');
$private=dirname($site,2).'/.anytoour-anex/search3-preview.php';
if(!is_file($private)||is_link($private))throw new RuntimeException('ANEX_SWEEP_PRIVATE');
require_once $private;
if(!defined('ANEX_API_TOKEN')||!defined('ANEX_B2B_TOKEN'))throw new RuntimeException('ANEX_SWEEP_PRIVATE');
require_once $site.'/config.php';
$dbFile=is_file($site.'/data/db-v1.php')?$site.'/data/db-v1.php':$site.'/v2/data/db-v1.php';require_once $dbFile;
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$count=static function(PDO $db):array{
  $one=static fn(string $q):int=>(int)$db->query($q)->fetchColumn();
  return[
    'rows'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex'"),
    'active'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND is_active=1"),
    'ready'=>$one("SELECT COUNT(*) FROM anytour_offers WHERE provider='anex' AND final_price_ready=1"),
    'programs'=>$one('SELECT COUNT(*) FROM anytour_anex_programs'),
    'contexts'=>$one('SELECT COUNT(*) FROM anytour_anex_program_contexts'),
    'apd'=>$one('SELECT COUNT(*) FROM anytour_anex_apd_rates')
  ];
};
$before=$count($db);
putenv('ANYTOUR_PROJECT_ROOT='.$site);
putenv('ANYTOUR_LOCAL_SNAPSHOT_INGEST_FILE='.$runtime.'/v2/data/anytour-offer-snapshot-ingest-v1.php');
$collector=$runtime.'/scripts/ops/anex_local_offer_collect.php';
$oldArgv=$argv;$oldArgc=$argc;
$argv=[
 $collector,'--departure=1','--country=4','--date-from=2026-09-19','--date-to=2026-10-09',
 '--nights='.(string)$nights,'--adults=2','--meal=7','--max-expands=60','--max-apd=300',
 '--generation='.(string)$generation
];$argc=count($argv);
ob_start();
try{require $collector;$raw=trim((string)ob_get_clean());}catch(Throwable $e){ob_end_clean();$argv=$oldArgv;$argc=$oldArgc;throw $e;}
$argv=$oldArgv;$argc=$oldArgc;
$collectorResult=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
if(!is_array($collectorResult)||($collectorResult['status']??null)!=='complete')throw new RuntimeException('ANEX_SWEEP_COLLECTOR');
$after=$count($db);$delta=[];foreach($before as $k=>$v)$delta[$k]=$after[$k]-$v;
echo json_encode([
 'status'=>'completed','nights'=>$nights,'before'=>$before,'after'=>$after,'delta'=>$delta,
 'collector'=>$collectorResult,'supplier_stage'=>true,'booking_calls'=>0,'lead_calls'=>0,
 'mapping_writes'=>0,'search3_publication'=>0,'production_webroot_writes'=>0,'replay_allowed'=>false
],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
