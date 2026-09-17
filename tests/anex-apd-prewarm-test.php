<?php
declare(strict_types=1);

require_once __DIR__.'/../app/integrations/anex-program-apd-store.php';
require_once __DIR__.'/../app/integrations/anex-apd-prewarm.php';

$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE anytour_anex_programs (supplier_program_id INTEGER,departure_id INTEGER,country_id INTEGER,supplier_currency_id INTEGER,flight_class TEXT,first_seen_at TEXT,last_seen_at TEXT,first_departure_date TEXT,last_departure_date TEXT,observation_count INTEGER,PRIMARY KEY(supplier_program_id,departure_id,country_id,supplier_currency_id))");
$db->exec("CREATE TABLE anytour_anex_program_contexts (supplier_program_id INTEGER,departure_id INTEGER,country_id INTEGER,supplier_currency_id INTEGER,date_beg TEXT,nights INTEGER,flight_class TEXT,first_seen_at TEXT,last_seen_at TEXT,observation_count INTEGER,PRIMARY KEY(supplier_program_id,departure_id,country_id,supplier_currency_id,date_beg,nights))");
$db->exec("CREATE TABLE anytour_anex_apd_rates (supplier_program_id INTEGER,date_beg TEXT,nights INTEGER,supplier_currency_id INTEGER,context_sha256 TEXT UNIQUE,apd_state TEXT,total_count INTEGER,row_count INTEGER,price_adult TEXT,price_child TEXT,cashrate TEXT,price_converted_adult TEXT,price_converted_child TEXT,observed_at TEXT,expires_at TEXT,refresh_count INTEGER,PRIMARY KEY(supplier_program_id,date_beg,nights,supplier_currency_id))");

$now=new DateTimeImmutable('2026-09-18T03:00:00Z');
$base=['departure_id'=>1,'country_id'=>4,'supplier_currency_id'=>3,'nights'=>7];
AnyTourAnexProgramApdStoreV1::recordProgram($db,$base+['supplier_program_id'=>778,'flight_class'=>'charter','departure_date'=>'2026-09-20'],$now->modify('-1 hour'));
AnyTourAnexProgramApdStoreV1::recordProgram($db,$base+['supplier_program_id'=>2637,'flight_class'=>'charter','departure_date'=>'2026-10-10'],$now->modify('-1 hour'));
AnyTourAnexProgramApdStoreV1::recordProgram($db,$base+['supplier_program_id'=>7385,'flight_class'=>'regular','departure_date'=>'2026-09-20'],$now->modify('-1 hour'));

$fresh=['supplier_program_id'=>2637,'date_beg'=>'2026-10-10','nights'=>7,'supplier_currency_id'=>3];
AnyTourAnexProgramApdStoreV1::recordApd($db,$fresh,['data'=>[['price_adult'=>'1','price_chd'=>'1','price_converted_adult'=>'14537.6','price_converted_chd'=>'14537.6']],'totalCount'=>1],$now,$now->modify('+3 hours'));

$calls=[];
$result=AnyTourAnexApdPrewarmV1::run(
  $db,1,4,new DateTimeImmutable('2026-09-18'),new DateTimeImmutable('2026-10-31'),$now->modify('-2 days'),$now,100,
  static function(array $criteria) use (&$calls):array{
    $calls[]=$criteria;
    return ['data'=>[['price_adult'=>'1','price_chd'=>'1','price_converted_adult'=>'10384','price_converted_chd'=>'10384']],'totalCount'=>1];
  }
);
if($result['status']!=='complete'||$result['queued']!==1||count($calls)!==1||(int)$calls[0]['supplier_program_id']!==778)throw new RuntimeException('PREWARM_QUEUE');
$saved=AnyTourAnexProgramApdStoreV1::readApd($db,['supplier_program_id'=>778,'date_beg'=>'2026-09-20','nights'=>7,'supplier_currency_id'=>3],$now);
if($saved['state']!=='rate'||$saved['rates']['adult']!=='10384'||!$saved['fresh'])throw new RuntimeException('PREWARM_SAVE');
if(AnyTourAnexApdPrewarmV1::ttlSeconds('2026-09-20',$now)!==3600)throw new RuntimeException('TTL_NEAR');
if(AnyTourAnexApdPrewarmV1::ttlSeconds('2026-09-30',$now)!==3*3600)throw new RuntimeException('TTL_MEDIUM');
if(AnyTourAnexApdPrewarmV1::ttlSeconds('2026-10-10',$now)!==8*3600)throw new RuntimeException('TTL_LATER');
if(AnyTourAnexApdPrewarmV1::ttlSeconds('2026-11-20',$now)!==18*3600)throw new RuntimeException('TTL_FAR');

echo "ANEX_APD_PREWARM_OK queued=1 regular_skipped=1 fresh_skipped=1 ttl=proximity\n";
