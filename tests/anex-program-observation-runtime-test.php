<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/anex-program-observation-runtime.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE anytour_anex_programs (
  supplier_program_id INTEGER,departure_id INTEGER,country_id INTEGER,supplier_currency_id INTEGER,
  flight_class TEXT,first_seen_at TEXT,last_seen_at TEXT,first_departure_date TEXT,last_departure_date TEXT,
  observation_count INTEGER,PRIMARY KEY(supplier_program_id,departure_id,country_id,supplier_currency_id))");
$db->exec("CREATE TABLE anytour_anex_program_contexts (
  supplier_program_id INTEGER,departure_id INTEGER,country_id INTEGER,supplier_currency_id INTEGER,
  date_beg TEXT,nights INTEGER,flight_class TEXT,first_seen_at TEXT,last_seen_at TEXT,observation_count INTEGER,
  PRIMARY KEY(supplier_program_id,departure_id,country_id,supplier_currency_id,date_beg,nights))");
$db->exec("CREATE TABLE anytour_anex_apd_rates (
  supplier_program_id INTEGER,date_beg TEXT,nights INTEGER,supplier_currency_id INTEGER,context_sha256 TEXT UNIQUE,
  apd_state TEXT,total_count INTEGER,row_count INTEGER,price_adult TEXT,price_child TEXT,cashrate TEXT,
  price_converted_adult TEXT,price_converted_child TEXT,observed_at TEXT,expires_at TEXT,refresh_count INTEGER,
  PRIMARY KEY(supplier_program_id,date_beg,nights,supplier_currency_id))");

$now = new DateTimeImmutable('2026-09-18T01:00:00Z');
$entry = static function(string $ref,string $flight,int $observed): array {
    return [
        'observed_at'=>$observed,
        'supplier_tour_program_id'=>'778',
        'supplier_currency_id'=>'3',
        'offer'=>[
            'offer_key'=>$ref,'checkin'=>'2026-10-05','nights'=>7,'flight_type'=>$flight,
        ],
    ];
};
$state = [
    'params'=>['departureId'=>'1','countryId'=>'4'],
    'gateway'=>['saved_offers'=>['offers'=>[
        'anex_online:'.str_repeat('a',64)=>$entry('anex_online:'.str_repeat('a',64),'charter',$now->getTimestamp()-30),
        'anex_online:'.str_repeat('b',64)=>$entry('anex_online:'.str_repeat('b',64),'charter',$now->getTimestamp()-29),
        'anex_online:'.str_repeat('c',64)=>$entry('anex_online:'.str_repeat('c',64),'regular',$now->getTimestamp()-28),
        'anex_online:'.str_repeat('d',64)=>[
            'observed_at'=>$now->getTimestamp()-27,
            'supplier_tour_program_id'=>'817','supplier_currency_id'=>'3',
            'offer'=>['offer_key'=>'anex_online:'.str_repeat('d',64),'checkin'=>'2026-10-06','nights'=>8,'flight_type'=>null],
        ],
    ]]],
];

$first=AnyTourAnexProgramObservationRuntimeV1::record($db,$state,$now);
if($first['status']!=='complete'||$first['examined']!==4||$first['persisted']!==3
    ||$first['skipped_seen']!==1||$first['skipped_invalid']!==0)throw new RuntimeException('FIRST_RECEIPT');

$q=$db->query("SELECT flight_class,observation_count FROM anytour_anex_programs WHERE supplier_program_id=778");
$row=$q->fetch(PDO::FETCH_ASSOC);
if(!is_array($row)||$row['flight_class']!=='mixed'||(int)$row['observation_count']!==2)throw new RuntimeException('PROGRAM_MIXED');

$q=$db->query("SELECT flight_class,observation_count FROM anytour_anex_program_contexts WHERE supplier_program_id=778 AND date_beg='2026-10-05' AND nights=7");
$row=$q->fetch(PDO::FETCH_ASSOC);
if(!is_array($row)||$row['flight_class']!=='mixed'||(int)$row['observation_count']!==2)throw new RuntimeException('CONTEXT_MIXED');

$q=$db->query("SELECT flight_class,observation_count FROM anytour_anex_programs WHERE supplier_program_id=817");
$row=$q->fetch(PDO::FETCH_ASSOC);
if(!is_array($row)||$row['flight_class']!=='unknown'||(int)$row['observation_count']!==1)throw new RuntimeException('UNKNOWN_RETAINED');

$second=AnyTourAnexProgramObservationRuntimeV1::record($db,$state,$now->modify('+1 minute'));
if($second['persisted']!==0||$second['skipped_seen']!==4)throw new RuntimeException('SESSION_DEDUP');

$bad=$state;
$bad['params']['countryId']='bad';
$invalid=AnyTourAnexProgramObservationRuntimeV1::record($db,$bad,$now);
if($invalid['status']!=='invalid_scope'||$invalid['persisted']!==0)throw new RuntimeException('INVALID_SCOPE');

echo "ANEX_PROGRAM_OBSERVATION_RUNTIME_OK persisted=3 duplicate_offer_context=1 mixed=1 rerun=0 supplier_calls=0\n";
