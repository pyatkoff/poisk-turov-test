<?php
declare(strict_types=1);

require_once __DIR__.'/../app/integrations/anex-apd-cache-runtime.php';

$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE anytour_anex_programs (supplier_program_id INTEGER,departure_id INTEGER,country_id INTEGER,supplier_currency_id INTEGER,flight_class TEXT,first_seen_at TEXT,last_seen_at TEXT,first_departure_date TEXT,last_departure_date TEXT,observation_count INTEGER,PRIMARY KEY(supplier_program_id,departure_id,country_id,supplier_currency_id))");
$db->exec("CREATE TABLE anytour_anex_program_contexts (supplier_program_id INTEGER,departure_id INTEGER,country_id INTEGER,supplier_currency_id INTEGER,date_beg TEXT,nights INTEGER,flight_class TEXT,first_seen_at TEXT,last_seen_at TEXT,observation_count INTEGER,PRIMARY KEY(supplier_program_id,departure_id,country_id,supplier_currency_id,date_beg,nights))");
$db->exec("CREATE TABLE anytour_anex_apd_rates (supplier_program_id INTEGER,date_beg TEXT,nights INTEGER,supplier_currency_id INTEGER,context_sha256 TEXT UNIQUE,apd_state TEXT,total_count INTEGER,row_count INTEGER,price_adult TEXT,price_child TEXT,cashrate TEXT,price_converted_adult TEXT,price_converted_child TEXT,observed_at TEXT,expires_at TEXT,refresh_count INTEGER,PRIMARY KEY(supplier_program_id,date_beg,nights,supplier_currency_id))");

$context=static function(int $program,string $date,int $nights=7,int $currency=3):array{
    return[
        'context_digest'=>hash('sha256',implode("\0",[(string)$program,(string)$currency,$date,(string)$nights])),
        'supplier_tour_program_id'=>(string)$program,
        'supplier_currency_id'=>(string)$currency,
        'checkin'=>$date,
        'nights'=>$nights,
    ];
};
$now=new DateTimeImmutable('2026-09-18T01:00:00Z');
$session=[];
$rate=[
    'source'=>'anex_b2b_additional_prices_daily',
    'rows'=>[[
        'price_adult'=>'40','price_chd'=>'30','cashrate'=>'92.5',
        'price_converted_adult'=>'3700','price_converted_chd'=>'2775',
    ]],
    'total_count'=>1,'truncated'=>false,
    'observed_at'=>'2026-09-18T00:59:00Z',
];

$c1=$context(778,'2026-10-05');
$write=AnyTourAnexApdCacheRuntimeV1::persist($db,$c1,$rate,$session,$now);
if($write['stored']!==true||$write['state']!=='rate')throw new RuntimeException('RATE_WRITE');
$read=AnyTourAnexApdCacheRuntimeV1::read($db,$c1,$now);
if($read['hit']!==true||$read['state']!=='rate')throw new RuntimeException('RATE_READ');
$row=$read['evidence']['rows'][0]??null;
if(!is_array($row)||$row['price_adult']!=='40'||$row['price_converted_adult']!=='3700')throw new RuntimeException('RATE_EVIDENCE');

$again=AnyTourAnexApdCacheRuntimeV1::persist($db,$c1,$rate,$session,$now);
if($again['stored']!==false||$again['reason']!=='already_persisted')throw new RuntimeException('SESSION_DEDUP');
$count=(int)$db->query("SELECT refresh_count FROM anytour_anex_apd_rates WHERE supplier_program_id=778")->fetchColumn();
if($count!==1)throw new RuntimeException('REFRESH_COUNT_DEDUP');

$c2=$context(817,'2026-09-20');
$empty=['rows'=>[],'total_count'=>0,'observed_at'=>'2026-09-18T00:58:00Z'];
AnyTourAnexApdCacheRuntimeV1::persist($db,$c2,$empty,$session,$now);
$emptyRead=AnyTourAnexApdCacheRuntimeV1::read($db,$c2,$now);
if($emptyRead['hit']!==true||$emptyRead['state']!=='empty'||$emptyRead['evidence']['rows']!==[]||$emptyRead['evidence']['total_count']!==0)throw new RuntimeException('EMPTY_NOT_ZERO');

$c3=$context(900,'2026-10-10');
$amb=['rows'=>[
    ['price_adult'=>'10','price_chd'=>'10','cashrate'=>null,'price_converted_adult'=>'1000','price_converted_chd'=>'1000'],
    ['price_adult'=>'20','price_chd'=>'20','cashrate'=>null,'price_converted_adult'=>'2000','price_converted_chd'=>'2000'],
],'total_count'=>2,'observed_at'=>'2026-09-18T00:57:00Z'];
AnyTourAnexApdCacheRuntimeV1::persist($db,$c3,$amb,$session,$now);
$ambRead=AnyTourAnexApdCacheRuntimeV1::read($db,$c3,$now);
if($ambRead['hit']!==true||$ambRead['state']!=='ambiguous'||$ambRead['evidence']['rows']!==[]||$ambRead['evidence']['truncated']!==true)throw new RuntimeException('AMBIGUOUS_READ');

$stale=AnyTourAnexApdCacheRuntimeV1::read($db,$c1,$now->modify('+9 hours'));
if($stale['hit']!==false)throw new RuntimeException('STALE_MUST_MISS');

echo "ANEX_APD_RUNTIME_CACHE_OK rate=1 empty=1 ambiguous=1 session_dedup=1 stale_miss=1 supplier_calls=0\n";
