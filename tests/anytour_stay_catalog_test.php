<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/anytour-stay-catalog-v1.php';
$count=0;
function check(bool $ok,string $message): void {global $count; $count++; if (!$ok) throw new RuntimeException($message);}
function rejects(callable $fn,string $message,?string $class=null): void {
    try {$fn();} catch (Throwable $e) {check($class===null || $e instanceof $class,$message.': wrong exception '.get_class($e)); return;}
    check(false,$message.': did not reject');
}
$scope=['namespace'=>'tourvisor','hotelKey'=>'00015','operatorKey'=>'5'];
$reference=['kind'=>'room','keyKind'=>'code','externalKey'=>'001'];
check(AnyTourStayCatalog::scope($scope)===$scope,'source identities stay exact');
check(AnyTourStayCatalog::reference($reference)===$reference,'room key leading zeros stay exact');
foreach (['AI','ai','AI-WITHOUT ALCOHOL','HB+','STANDARD POOL VIEW WITH PRIVATE POOL','  Standard  Sea View  ',"O'Reilly <room>"] as $key) {
    check(AnyTourStayCatalog::reference(['kind'=>'meal','keyKind'=>'label','externalKey'=>$key])['externalKey']===$key,'raw key is not translated/trimmed');
}
foreach ([null,1,true,'',"\ninvalid",str_repeat('x',513),"\xff"] as $value) {
    rejects(fn()=>AnyTourStayCatalog::reference(['kind'=>'room','keyKind'=>'code','externalKey'=>$value]),'bad external reference',InvalidArgumentException::class);
}
foreach ([['kind'=>'hotel','keyKind'=>'code','externalKey'=>'1'],['kind'=>'room','keyKind'=>'fuzzy','externalKey'=>'A'],[]] as $value) {
    rejects(fn()=>AnyTourStayCatalog::reference($value),'bad reference kind',InvalidArgumentException::class);
}
foreach ([['namespace'=>'Tourvisor','hotelKey'=>'1','operatorKey'=>'5'],['namespace'=>'tv','hotelKey'=>1,'operatorKey'=>'5'],
    ['namespace'=>'tv','hotelKey'=>'1','operatorKey'=>''],['namespace'=>'tv','hotelKey'=>'1','operatorKey'=>5],[]] as $value) {
    rejects(fn()=>AnyTourStayCatalog::scope($value),'typed source context is required',InvalidArgumentException::class);
}
$facts=['maxOccupancy'=>3,'view'=>'Море','building'=>null,'bedrooms'=>0,'areaM2'=>36.5];
$copy=$facts; $normalized=AnyTourStayCatalog::roomFacts($facts);
check($copy===$facts,'facts are not mutated');
check($normalized['building']===null && $normalized['bedrooms']===0,'unknown is not zero and a studio can have zero bedrooms');
foreach ([['view'=>false],['maxOccupancy'=>0],['maxOccupancy'=>'3'],['bedrooms'=>-1],['areaM2'=>INF],['areaM2'=>0],
    ['price'=>123450],['nights'=>7],['adults'=>2],['view'=>str_repeat('a',256)]] as $value) {
    rejects(fn()=>AnyTourStayCatalog::roomFacts($value),'invalid or offer-specific fact',InvalidArgumentException::class);
}
$pure=$count;
if (in_array('--unit-only',$argv,true)) {
    if (getenv('CI')) throw new RuntimeException('CI must run the full disposable MySQL suite');
    echo "ANYTOUR_STAY_PURE_OK checks=$pure sql=NOT_RUN\n"; exit;
}
// Fail closed. This suite never reads application/server credentials or a non-fixture database.
$dsn=(string)getenv('ANYTOUR_STAY_TEST_DSN');
if (!extension_loaded('pdo_mysql') || $dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anytour_stay_fixture;charset=utf8mb4') {
    throw new RuntimeException('Full suite requires explicit local disposable MySQL fixture DSN and pdo_mysql');
}
$pdo=new PDO($dsn,'root',(string)getenv('ANYTOUR_STAY_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
check($pdo->query('SELECT DATABASE()')->fetchColumn()==='anytour_stay_fixture','fixture identity');
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchColumn()===0,'fresh fixture only');
$root=dirname(__DIR__);
$pdo->exec(file_get_contents($root.'/v2/data/migrations/20260916-anytour-canonical-catalog.sql'));
$ddl=file_get_contents($root.'/v2/data/migrations/20260916-anytour-stay-catalog.sql');
$pdo->exec($ddl);
check((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND ENGINE='InnoDB'")->fetchColumn()===7,'all seven canonical/stay tables are real InnoDB');
// Room/meal installation neither rewrites the existing hotel version nor invents source mappings.
check((int)$pdo->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn()===1,'hotel schema version preserved');
check((int)$pdo->query('SELECT COUNT(*) FROM anytour_stay_mappings')->fetchColumn()===0,'zero invented supplier mappings');
check((int)$pdo->query('SELECT COUNT(*) FROM anytour_hotel_rooms')->fetchColumn()===0,'generic categories do not create hotel rooms');
$catalog=new AnyTourStayCatalog($pdo);
$meals=$catalog->meals(); check(count($meals)===10,'ten distinct local meal categories');
$byCode=array_column($meals,null,'code');
foreach (['half-board-plus','full-board-plus','ultra-all-inclusive','soft-all-inclusive','alcohol-free-all-inclusive'] as $code) {
    check($byCode[$code]['id']!==$byCode[$byCode[$code]['familyCode']]['id'],'broader family is not exact plan identity');
}
check($byCode['half-board-plus']['nameRu']==='Полупансион плюс','Russian plus label');
check($byCode['soft-all-inclusive']['qualifiers']===['variant'=>'soft'],'soft is not silently alcohol-free');
check($byCode['half-board-plus']['qualifiers']===['variant'=>'plus'],'specific beverages are not invented for plus');
$hotelInsert=$pdo->prepare('INSERT INTO anytour_hotels (profile_json,profile_sha256,created_at,updated_at) VALUES (?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
$hotels=[];
foreach (['Fixture hotel A','Fixture hotel B'] as $name) {
    $json=json_encode(['name'=>$name],JSON_THROW_ON_ERROR); $hotelInsert->execute([$json,hash('sha256',$json)]); $hotels[]=(int)$pdo->lastInsertId();
}
[$a,$b]=$hotels;
$sourceInsert=$pdo->prepare('INSERT INTO anytour_hotel_sources (namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES (?,?,?,\'fixture-reviewed\',\'{}\',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
foreach ([['tourvisor','00015',$a],['tourvisor','15',$b],['anex','00015',$a],['andromeda','00015',$a]] as $s) $sourceInsert->execute([...$s,hash('sha256','{}')]);
$baselineSources=$pdo->query('SELECT * FROM anytour_hotel_sources ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$baselineHotels=$pdo->query('SELECT * FROM anytour_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$beforeSchema=$pdo->query('SHOW CREATE TABLE anytour_hotel_sources')->fetch(PDO::FETCH_NUM)[1];
rejects(fn()=>$catalog->createRoom($a,'sea','Стандарт · море','standard',[]),'room write cannot auto-commit');
$pdo->beginTransaction();
$sea=$catalog->createRoom($a,'sea','Стандарт · море','standard',['view'=>'Море','building'=>'Основной','bedrooms'=>1]);
$land=$catalog->createRoom($a,'land','Стандарт · территория','standard',['view'=>'Территория']);
$other=$catalog->createRoom($b,'sea','Стандарт · море','standard',['view'=>'Море']);
$pdo->commit();
check($sea!==$land && $sea!==$other,'hotel room types have separate database IDs');
check(count($catalog->rooms($a))===2 && count($catalog->rooms($b))===1,'rooms belong to one hotel');
check($catalog->rooms($a)[0]['facts']['building']==='Основной','specific building retained');
check(!array_key_exists('maxOccupancy',$catalog->rooms($a)[1]['facts']),'unknown capacity is not guessed');
$pdo->beginTransaction(); $catalog->createRoom($a,'rollback','Тест отката',null,[]); $pdo->rollBack();
check(count($catalog->rooms($a))===2,'caller rollback owns room writes');
$pdo->beginTransaction();
rejects(fn()=>$catalog->createRoom($a,'sea','Другое имя',null,[]),'existing room is not overwritten',PDOException::class);
rejects(fn()=>$catalog->createRoom($a,'bad-category','Номер','unreviewed',[]),'room category FK',PDOException::class);
$pdo->rollBack();
$evidence=['ref'=>'fixture-reviewed-dossier','sha256'=>hash('sha256','fixture evidence'),'reviewedBy'=>'fixture-reviewer'];
$roomRef=['kind'=>'room','keyKind'=>'code','externalKey'=>'STD SV'];
$mealRef=['kind'=>'meal','keyKind'=>'code','externalKey'=>'AI'];
$unknown=['kind'=>'room','keyKind'=>'code','externalKey'=>'STANDARD'];
$allInclusive=$byCode['all-inclusive']['id'];
$beforeInputs=serialize([$scope,$roomRef,$mealRef]);
rejects(fn()=>$catalog->recordDecision($scope,$roomRef,$a,'accepted',$sea,$evidence),'mapping write requires transaction');
$pdo->beginTransaction();
$catalog->recordDecision($scope,$roomRef,$a,'accepted',$sea,$evidence);
$catalog->recordDecision($scope,$mealRef,$a,'accepted',$allInclusive,$evidence);
$negative=['kind'=>'room','keyKind'=>'label','externalKey'=>'Ambiguous Standard'];
$catalog->recordDecision($scope,$negative,$a,'rejected',null,$evidence);
$pending=['kind'=>'meal','keyKind'=>'code','externalKey'=>'Premium AI'];
$catalog->recordDecision($scope,$pending,$a,'pending',null,$evidence);
$conflict=['kind'=>'room','keyKind'=>'code','externalKey'=>'Conflicting room'];
$catalog->recordDecision($scope,$conflict,$a,'conflict',null,$evidence);
$pdo->commit();
$refs=[$roomRef,$mealRef,$unknown,$negative,$pending,$conflict,$roomRef];
$result=$catalog->resolve($scope,$refs);
check(array_column($result['items'],'status')===['accepted','accepted','unmapped','rejected','pending','conflict','accepted'],'explicit exact statuses and request order');
check($result['hotelId']===$a,'canonical hotel ID is resolved, not copied from provider');
check($result['items'][0]['canonical']['id']===$sea,'correct local room identity');
check($result['items'][1]['canonical']['id']===$allInclusive,'correct local meal identity');
check($result['items'][0]['canonical']===$result['items'][6]['canonical'],'duplicate references preserve output order');
foreach ([2,3,4,5] as $i) check($result['items'][$i]['canonical']===null,'unresolved is never substituted');
check(serialize([$scope,$roomRef,$mealRef])===$beforeInputs,'source/offer reference input unchanged');
foreach ([['namespace'=>'anex'],['namespace'=>'andromeda'],['operatorKey'=>'005'],['operatorKey'=>'43'],['hotelKey'=>'15']] as $difference) {
    $res=$catalog->resolve(array_replace($scope,$difference),[$roomRef,$mealRef]);
    check(array_column($res['items'],'status')===['unmapped','unmapped'],'namespace/operator/hotel are all exact');
}
foreach ([['kind'=>'room','keyKind'=>'label','externalKey'=>'STD SV'],['kind'=>'meal','keyKind'=>'code','externalKey'=>'ai'],
    ['kind'=>'meal','keyKind'=>'code','externalKey'=>'UAI'],['kind'=>'meal','keyKind'=>'code','externalKey'=>'HB+']] as $ref) {
    check($catalog->resolve($scope,[$ref])['items'][0]['status']==='unmapped','no case folding, code/label fallback or automatic meal mapping');
}
// Different source keys may converge only through independently recorded decisions.
$pdo->beginTransaction();
foreach (['anex'=>'SR/SEA','andromeda'=>'1001'] as $ns=>$externalRoom) {
    $providerScope=array_replace($scope,['namespace'=>$ns]);
    $providerRoom=['kind'=>'room','keyKind'=>'code','externalKey'=>$externalRoom];
    $catalog->recordDecision($providerScope,$providerRoom,$a,'accepted',$sea,$evidence);
}
$pdo->commit();
foreach (['anex'=>'SR/SEA','andromeda'=>'1001'] as $ns=>$externalRoom) {
    $providerScope=array_replace($scope,['namespace'=>$ns]);
    $providerRoom=['kind'=>'room','keyKind'=>'code','externalKey'=>$externalRoom];
    check($catalog->resolve($providerScope,[$providerRoom])['items'][0]['canonical']['id']===$sea,
        'three sources share one local room only after exact reviewed mapping');
}
$pdo->beginTransaction();
rejects(fn()=>$catalog->recordDecision($scope,$unknown,$a,'accepted',$other,$evidence),'wrong hotel room cannot be mapped');
rejects(fn()=>$catalog->recordDecision($scope,$unknown,$b,'accepted',$other,$evidence),'stale expected hotel cannot be mapped');
rejects(fn()=>$catalog->recordDecision($scope,$roomRef,$a,'accepted',$land,$evidence),'existing accepted mapping cannot be overwritten',PDOException::class);
rejects(fn()=>$catalog->recordDecision($scope,$negative,$a,'accepted',$sea,$evidence),'negative review protected from overwrite',PDOException::class);
rejects(fn()=>$catalog->recordDecision($scope,$unknown,$a,'accepted',$sea,[]),'review evidence required',InvalidArgumentException::class);
rejects(fn()=>$catalog->recordDecision($scope,$unknown,$a,'pending',$sea,$evidence),'nonaccepted record cannot carry a target',InvalidArgumentException::class);
$pdo->rollBack();
$pdo->beginTransaction(); $catalog->recordDecision($scope,$unknown,$a,'accepted',$sea,$evidence); $pdo->rollBack();
check($catalog->resolve($scope,[$unknown])['items'][0]['status']==='unmapped','caller rollback owns mapping writes');
$pdo->exec('UPDATE anytour_hotel_rooms SET is_active=0 WHERE id='.$sea);
check($catalog->resolve($scope,[$roomRef])['items'][0]['status']==='target-unavailable','inactive room withheld');
$pdo->exec('UPDATE anytour_hotel_rooms SET is_active=1 WHERE id='.$sea);
$pdo->exec('UPDATE anytour_meal_plans SET is_active=0 WHERE id='.$allInclusive);
check($catalog->resolve($scope,[$mealRef])['items'][0]['status']==='target-unavailable','inactive meal withheld');
$pdo->exec('UPDATE anytour_meal_plans SET is_active=1 WHERE id='.$allInclusive);
$pdo->exec('UPDATE anytour_hotels SET is_active=0 WHERE id='.$a);
check($catalog->resolve($scope,[$roomRef])['items'][0]['status']==='hotel-unresolved','inactive hotel withheld');
check($catalog->rooms($a)===[],'inactive hotel has no public room list');
$pdo->exec('UPDATE anytour_hotels SET is_active=1 WHERE id='.$a);
$changeSource=$pdo->prepare('UPDATE anytour_hotel_sources SET anytour_hotel_id=? WHERE namespace=? AND external_key=?');
$changeSource->execute([$b,'tourvisor','00015']);
check($catalog->resolve($scope,[$roomRef,$mealRef])['items'][0]['status']==='source-drift','changed source relation never transfers room identity');
check($catalog->resolve($scope,[$mealRef])['items'][0]['canonical']===null,'changed source relation cannot reuse meal decision');
$changeSource->execute([$a,'tourvisor','00015']);
$missingScope=array_replace($scope,['hotelKey'=>'999']);
check($catalog->resolve($missingScope,[$roomRef])['hotelId']===null,'unknown source hotel has no numeric-ID fallback');
// Database guards protect integrity even when a future tool bypasses this PHP repository.
rejects(fn()=>$pdo->exec('UPDATE anytour_stay_mappings SET room_id='.$other.' WHERE kind=\'room\' AND state=\'accepted\''),'cross-hotel room composite FK',PDOException::class);
rejects(fn()=>$pdo->exec('UPDATE anytour_stay_mappings SET meal_id='.$allInclusive.' WHERE kind=\'room\' AND state=\'accepted\''),'one kind cannot combine two targets',PDOException::class);
rejects(fn()=>$pdo->exec('UPDATE anytour_stay_mappings SET state=\'rejected\' WHERE kind=\'room\' AND state=\'accepted\''),'negative decisions cannot keep active targets',PDOException::class);
rejects(fn()=>$pdo->exec('DELETE FROM anytour_hotel_rooms WHERE id='.$sea),'referenced local room cannot disappear',PDOException::class);
$refs100=array_fill(0,100,$roomRef);
$queriesBefore=(int)$pdo->query("SHOW SESSION STATUS LIKE 'Com_stmt_execute'")->fetch(PDO::FETCH_NUM)[1];
$hundred=$catalog->resolve($scope,$refs100);
$queriesAfter=(int)$pdo->query("SHOW SESSION STATUS LIKE 'Com_stmt_execute'")->fetch(PDO::FETCH_NUM)[1];
check(count($hundred['items'])===100 && $queriesAfter-$queriesBefore===1,'100 references use one prepared SELECT');
foreach ([[],array_fill(0,101,$roomRef),['key'=>$roomRef],[null]] as $bad) rejects(fn()=>$catalog->resolve($scope,$bad),'batch validation',InvalidArgumentException::class);
check($pdo->query('SELECT * FROM anytour_hotel_sources ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$baselineSources,'existing source rows unchanged after fixture restores');
check($pdo->query('SELECT * FROM anytour_hotels ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)===$baselineHotels,'canonical profiles untouched');
check($pdo->query('SHOW CREATE TABLE anytour_hotel_sources')->fetch(PDO::FETCH_NUM)[1]===$beforeSchema,'no hotel-source schema alteration');
rejects(fn()=>$pdo->exec($ddl),'existing installation requires inspection, no implicit overwrite',PDOException::class);
check(count($catalog->meals())===10,'refused migration replay leaves reviewed labels intact');
echo "ANYTOUR_STAY_CATALOG_OK checks=$count pure=$pure sql=REAL_MYSQL seed_meals=10 hotel_scoped_rooms=1 exact_mappings=1 batch100_selects=1 live_db_writes=0 supplier_calls=0\n";
