<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-program-apd-store.php';

$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE anytour_anex_programs (
  supplier_program_id INTEGER NOT NULL,departure_id INTEGER NOT NULL,country_id INTEGER NOT NULL,supplier_currency_id INTEGER NOT NULL,
  flight_class TEXT NOT NULL,first_seen_at TEXT NOT NULL,last_seen_at TEXT NOT NULL,first_departure_date TEXT NOT NULL,last_departure_date TEXT NOT NULL,
  observation_count INTEGER NOT NULL DEFAULT 1,PRIMARY KEY(supplier_program_id,departure_id,country_id,supplier_currency_id))");
$db->exec("CREATE TABLE anytour_anex_apd_rates (
  supplier_program_id INTEGER NOT NULL,date_beg TEXT NOT NULL,nights INTEGER NOT NULL,supplier_currency_id INTEGER NOT NULL,context_sha256 TEXT NOT NULL UNIQUE,
  apd_state TEXT NOT NULL,total_count INTEGER NOT NULL,row_count INTEGER NOT NULL,price_adult TEXT NULL,price_child TEXT NULL,cashrate TEXT NULL,
  price_converted_adult TEXT NULL,price_converted_child TEXT NULL,observed_at TEXT NOT NULL,expires_at TEXT NOT NULL,refresh_count INTEGER NOT NULL DEFAULT 1,
  PRIMARY KEY(supplier_program_id,date_beg,nights,supplier_currency_id))");

$t0=new DateTimeImmutable('2026-09-17T22:30:00Z');
$program=['supplier_program_id'=>778,'departure_id'=>1,'country_id'=>4,'supplier_currency_id'=>3,'flight_class'=>'charter','departure_date'=>'2026-10-12'];
$p=AnyTourAnexProgramApdStoreV1::recordProgram($db,$program,$t0);
if($p['flight_class']!=='charter'||(int)$p['observation_count']!==1)throw new RuntimeException('PROGRAM_FIRST');
$p=AnyTourAnexProgramApdStoreV1::recordProgram($db,$program,$t0->modify('+1 minute'));
if($p['flight_class']!=='charter'||(int)$p['observation_count']!==2)throw new RuntimeException('PROGRAM_REPEAT');
$p=AnyTourAnexProgramApdStoreV1::recordProgram($db,array_replace($program,['flight_class'=>'unknown']),$t0->modify('+2 minutes'));
if($p['flight_class']!=='charter')throw new RuntimeException('PROGRAM_UNKNOWN_MUST_NOT_ERASE');
$p=AnyTourAnexProgramApdStoreV1::recordProgram($db,array_replace($program,['flight_class'=>'regular']),$t0->modify('+3 minutes'));
if($p['flight_class']!=='mixed')throw new RuntimeException('PROGRAM_CONFLICT');

$program2=array_replace($program,['supplier_program_id'=>2637,'flight_class'=>'charter','departure_date'=>'2026-10-13']);
AnyTourAnexProgramApdStoreV1::recordProgram($db,$program2,$t0);
$prewarm=AnyTourAnexProgramApdStoreV1::prewarmPrograms($db,1,4,$t0->modify('-1 day'));
if(count($prewarm)!==1||(int)$prewarm[0]['supplier_program_id']!==2637)throw new RuntimeException('PREWARM_ONLY_UNAMBIGUOUS_CHARTER');

$criteria=['supplier_program_id'=>778,'date_beg'=>'2026-10-12','nights'=>7,'supplier_currency_id'=>3];
$ratePayload=['data'=>[[
  'price_adult'=>'5000','price_chd'=>'4000','cashrate'=>'1','price_converted_adult'=>'10384','price_converted_chd'=>'8307.2'
]],'totalCount'=>1];
$r=AnyTourAnexProgramApdStoreV1::recordApd($db,$criteria,$ratePayload,$t0,$t0->modify('+6 hours'));
if($r['state']!=='rate'||!$r['fresh']||$r['rates']['adult']!=='10384'||$r['rates']['child']!=='8307.2')throw new RuntimeException('APD_RATE');
$party=(float)$r['rates']['adult']*2+(float)$r['rates']['child'];
if(abs($party-29075.2)>0.001)throw new RuntimeException('PARTY_DERIVATION');

$emptyCriteria=['supplier_program_id'=>7385,'date_beg'=>'2026-10-12','nights'=>7,'supplier_currency_id'=>3];
$e=AnyTourAnexProgramApdStoreV1::recordApd($db,$emptyCriteria,['data'=>[],'totalCount'=>0],$t0,$t0->modify('+2 hours'));
if($e['state']!=='empty'||$e['row_count']!==0||$e['rates']['adult']!==null)throw new RuntimeException('APD_EMPTY_NOT_ZERO');
if(AnyTourAnexProgramApdStoreV1::readApd($db,$emptyCriteria,$t0->modify('+3 hours'))['fresh']!==false)throw new RuntimeException('APD_EXPIRY');

$amb=AnyTourAnexProgramApdStoreV1::recordApd($db,['supplier_program_id'=>817,'date_beg'=>'2026-10-12','nights'=>7,'supplier_currency_id'=>3],
 ['data'=>[['price_adult'=>'1','price_chd'=>'1'],['price_adult'=>'2','price_chd'=>'2']],'totalCount'=>2],$t0,$t0->modify('+1 hour'));
if($amb['state']!=='ambiguous'||$amb['rates']['adult']!==null)throw new RuntimeException('APD_AMBIGUOUS');

$r2=AnyTourAnexProgramApdStoreV1::recordApd($db,$criteria,$ratePayload,$t0->modify('+10 minutes'),$t0->modify('+7 hours'));
if($r2['refresh_count']!==2)throw new RuntimeException('APD_REFRESH_COUNT');

echo "ANEX_PROGRAM_APD_STORE_OK programs=2 rate=1 empty=1 ambiguous=1 refresh=2\n";
