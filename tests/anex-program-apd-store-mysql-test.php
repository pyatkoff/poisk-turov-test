<?php
declare(strict_types=1);

require_once __DIR__.'/../app/integrations/anex-program-apd-store.php';

$dsn=trim((string)getenv('ANEX_PROGRAM_STORE_MYSQL_DSN'));
if($dsn==='' || !str_starts_with($dsn,'mysql:')) throw new RuntimeException('MYSQL_DSN_REQUIRED');
$db=new PDO($dsn,(string)getenv('ANEX_PROGRAM_STORE_MYSQL_USER'),(string)getenv('ANEX_PROGRAM_STORE_MYSQL_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_STRINGIFY_FETCHES=>false,
]);

foreach(['anytour_anex_apd_rates','anytour_anex_program_contexts','anytour_anex_programs'] as $table){
    $db->exec('DROP TABLE IF EXISTS '.$table);
}
$sql=file_get_contents(__DIR__.'/../v2/data/migrations/20260918-anex-program-apd-cache.sql');
if(!is_string($sql)||$sql==='')throw new RuntimeException('MIGRATION_MISSING');
$sql=preg_replace('/^\s*--.*$/m','',$sql)??$sql;
foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement){
    $statement=trim($statement);
    if($statement!=='')$db->exec($statement);
}

$now=new DateTimeImmutable('2026-09-18T01:00:00Z');
$fact=[
    'supplier_program_id'=>778,
    'departure_id'=>1,
    'country_id'=>4,
    'supplier_currency_id'=>3,
    'flight_class'=>'charter',
    'departure_date'=>'2026-10-12',
    'nights'=>7,
];

$first=AnyTourAnexProgramApdStoreV1::recordProgram($db,$fact,$now);
if(($first['flight_class']??null)!=='charter'||(int)($first['observation_count']??0)!==1){
    throw new RuntimeException('MYSQL_PROGRAM_FIRST');
}
$second=AnyTourAnexProgramApdStoreV1::recordProgram($db,$fact,$now->modify('+1 minute'));
if((int)($second['observation_count']??0)!==2)throw new RuntimeException('MYSQL_PROGRAM_REPEAT');

$programCount=(int)$db->query('SELECT COUNT(*) FROM anytour_anex_programs')->fetchColumn();
$context=$db->query('SELECT * FROM anytour_anex_program_contexts WHERE supplier_program_id=778')->fetch(PDO::FETCH_ASSOC);
if($programCount!==1||!is_array($context)||(int)$context['observation_count']!==2||$context['flight_class']!=='charter'){
    throw new RuntimeException('MYSQL_CONTEXT_REPEAT');
}

$regular=array_replace($fact,['flight_class'=>'regular']);
$mixed=AnyTourAnexProgramApdStoreV1::recordProgram($db,$regular,$now->modify('+2 minutes'));
if(($mixed['flight_class']??null)!=='mixed')throw new RuntimeException('MYSQL_PROGRAM_MIXED');
$ctx=$db->query('SELECT flight_class,observation_count FROM anytour_anex_program_contexts WHERE supplier_program_id=778')->fetch(PDO::FETCH_ASSOC);
if(!is_array($ctx)||$ctx['flight_class']!=='mixed'||(int)$ctx['observation_count']!==3)throw new RuntimeException('MYSQL_CONTEXT_MIXED');

echo "ANEX_PROGRAM_STORE_MYSQL_OK native_prepares=1 program_rows=1 context_rows=1 observations=3 mixed=1\n";
