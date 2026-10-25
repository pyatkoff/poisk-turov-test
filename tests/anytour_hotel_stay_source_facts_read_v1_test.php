<?php
declare(strict_types=1);
$source=(string)getenv('LOCAL_STAY_SOURCE_ROOT');if ($source===''||realpath($source)!==$source) throw new RuntimeException('Immutable SOURCE fixture required');
$stage=sys_get_temp_dir().'/anytour-source-facts-'.bin2hex(random_bytes(8));mkdir($stage.'/scripts/catalog',0700,true);mkdir($stage.'/v2/data',0700,true);
foreach (['anytour_hotel_stay_offer_candidates_v2.php','anytour_hotel_stay_source_facts_read_v1.php'] as $name) copy(__DIR__.'/../scripts/catalog/'.$name,$stage.'/scripts/catalog/'.$name);
foreach (['anytour-hotel-stay-catalog-v2.php','anytour-provider-identity-bridge-v1.php','db-v1.php'] as $name) copy($source.'/v2/data/'.$name,$stage.'/v2/data/'.$name);
require_once $stage.'/scripts/catalog/anytour_hotel_stay_source_facts_read_v1.php';
$checks=0;$case=0;$offer=0;
function facts_check(bool $ok,string $label): void {global $checks;++$checks;if (!$ok) throw new RuntimeException('SOURCE_FACTS_CHECK_FAILED:'.$label);}
// Removing only the additive region must reconstruct the accepted historical SOURCE bytes.
$main=(string)file_get_contents(__DIR__.'/../scripts/catalog/anytour_hotel_stay_offer_candidates_v2.php');
$start=strpos($main,'    // SOURCE_FACTS_ADDITIVE_BEGIN:');$end=strpos($main,'    // SOURCE_FACTS_ADDITIVE_END');
facts_check($start!==false&&$end!==false,'single additive region');
$original=substr($main,0,$start-1).substr($main,$end+strlen('    // SOURCE_FACTS_ADDITIVE_END')+1);
facts_check(hash('sha256',$original)==='7a555c9665d3917160eab1f01ac60a42530f854294fb36b2daf08f89fce7d40b','all historical Candidate bytes preserved');
$server=(string)getenv('ANYTOUR_STAY_SOURCE_FACTS_V1_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server)) throw new RuntimeException('Dedicated loopback MySQL fixture required');
$password=(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false];
$root=new PDO($server,'root',$password,$options);
function facts_fixture(string $class=PDO::class): PDO {
    global $server,$password,$options,$root,$source,$case;
    $name='anytour_source_facts_fixture_'.(++$case);$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
    $db=new $class(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'root',$password,$options);
    foreach (['20260916-anytour-canonical-catalog.sql','20260916-anytour-offer-store.sql'] as $file) {
        $sql=preg_replace('/^\s*--.*$/m','',(string)file_get_contents($source.'/v2/data/migrations/'.$file))??'';
        foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement) if (preg_match('/^CREATE TABLE IF NOT EXISTS (anytour_hotels|anytour_hotel_sources|anytour_offers|anytour_offer_scope_state) \(/',trim($statement))) $db->exec(trim($statement));
    }return $db;
}
function facts_hotel(PDO $db,int $legacy,string $name): int {
    $raw=AnyTourHotelStaySourceFactsReadV1::json(['name'=>$name]);
    $db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$raw,hash('sha256',$raw)]);$id=(int)$db->lastInsertId();
    $alias=['accepted_local_hotel_id'=>$legacy,'canonical_hotel_id'=>$id,'derived_from_namespace'=>'legacy_catalog','derived_from_source_sha256'=>hash('sha256','fixture'),'schema_version'=>1];
    $raw=AnyTourHotelStaySourceFactsReadV1::json($alias);
    $db->prepare("INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES('anytour_local_id',?,?,'canonical_local_alias_v1',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([(string)$legacy,$id,$raw,hash('sha256',$raw)]);return $id;
}
function facts_offer(PDO $db,int $hotel,int $legacy,string $tour,DateTimeImmutable $seen,string $operator='OP-A',string $room='STANDARD LAND VIEW (NON-REFUNDABLE)',string $meal='AI - Все включено',bool $complete=true): int {
    global $offer;$n=++$offer;$scope=hash('sha256','scope-'.$n);$token=hash('sha256','refresh-'.$n);
    $identity=['search_ref_digest'=>hash('sha256','search-'.$n),'offer_ref_digest'=>hash('sha256','tourvisor:tour:'.$tour),'provider_hotel_ref_digest'=>hash('sha256','tourvisor:hotel:'.$legacy)];
    $payload=['schema_version'=>1,'provider'=>'tourvisor','identity'=>$identity,'operator'=>['raw'=>$operator],
        'tour'=>['room'=>['raw'=>$room],'meal'=>['raw'=>$meal]],'money'=>['price'=>'999999.99'],'traveller'=>['email'=>'PRIVATE_FIXTURE_PII']];
    $raw=AnyTourHotelStaySourceFactsReadV1::json($payload);$at=$seen->format('Y-m-d H:i:s');
    $row=['anytour_hotel_id'=>$hotel,'legacy_hotel_id'=>$legacy,'provider'=>'tourvisor','scope_sha256'=>$scope,
        'search_ref_digest'=>$identity['search_ref_digest'],'offer_ref_digest'=>$identity['offer_ref_digest'],'provider_hotel_ref_digest'=>$identity['provider_hotel_ref_digest'],
        'identity_sha256'=>hash('sha256',AnyTourHotelStaySourceFactsReadV1::json(['provider'=>'tourvisor','identity'=>$identity])),
        'operator_json'=>'{}','operator_sha256'=>hash('sha256','{}'),'checkin'=>'2027-01-01','nights'=>7,'adults'=>2,'children'=>0,
        'child_ages_json'=>'[]','party_sha256'=>hash('sha256','[]'),'meal_json'=>'{}','room_json'=>'{}','placement_json'=>'{}',
        'display_price'=>'999999.99','currency'=>'RUB','final_price_ready'=>0,'final_price_verified'=>0,'payload_json'=>$raw,'payload_sha256'=>hash('sha256',$raw),
        'observed_at'=>$at,'source_context_expires_at'=>$seen->modify('+15 minutes')->format('Y-m-d H:i:s'),'last_refresh_token'=>$token,
        'last_seen_at'=>$at,'expires_at'=>$seen->modify('+2 hours')->format('Y-m-d H:i:s'),'is_active'=>1];
    $db->prepare('INSERT INTO anytour_offers (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));$id=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO anytour_offer_scope_state(provider,scope_sha256,active_refresh_token,latest_complete_refresh_token,revision,updated_at) VALUES('tourvisor',?,?,?,1,?)")->execute([$scope,null,$complete?$token:null,$at]);return $id;
}
function facts_identity(PDO $db): string {$r=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch();return hash('sha256',AnyTourHotelStaySourceFactsReadV1::json(['database'=>$r['selected_database'],'serverUuid'=>$r['server_uuid']]));}
$site=$stage.'/anytoour.ru';mkdir($site.'/data',0700,true);copy($source.'/v2/data/db-v1.php',$site.'/data/db-v1.php');file_put_contents($site.'/config.php',"<?php // FICTIONAL PRIVATE CONFIG\n");
function facts_parents(PDO $db): array {
    global $site;$protected=AnyTourHotelStaySourceFactsReadV1::fingerprint($site);$id=facts_identity($db);
    $context=['status'=>'complete','mode'=>'local-stay-install-context-read-v1','operation_id'=>AnyTourHotelStaySourceFactsReadV1::CONTEXT_OPERATION,'source_sha'=>AnyTourHotelStaySourceFactsReadV1::PARENT_SOURCE,
        'local_stay_install_context'=>['protectedBefore'=>$protected,'protectedAfter'=>$protected,'data'=>['receipt'=>[
            'schemaVersion'=>1,'source'=>'anytour-hotel-stay-install-context-read-v1','state'=>'completed_read_only','operationId'=>AnyTourHotelStaySourceFactsReadV1::CONTEXT_OPERATION,
            'controlSha'=>AnyTourHotelStaySourceFactsReadV1::CONTEXT_CONTROL,'sourceSha'=>AnyTourHotelStaySourceFactsReadV1::PARENT_SOURCE,'databaseSha256'=>$id,
            'connectionAuthority'=>'current_root_config_and_accepted_helper','helperAccepted'=>true,'phpExecuted'=>true,'oldDatabaseIdentityKnown'=>false,'applyAuthorized'=>false,
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,'currentProtectedBefore'=>$protected,'currentProtectedAfter'=>$protected]]]];
    $failure=['mode'=>'local-stay-context-install-v1','status'=>'terminal_nonzero_no_replay','operation_id'=>AnyTourHotelStaySourceFactsReadV1::FAILURE_OPERATION,'source_sha'=>AnyTourHotelStaySourceFactsReadV1::PARENT_SOURCE,
        'schema_write_outcome_unknown'=>false,'booking_calls'=>0,'database_writes'=>0,'lead_calls'=>0,'mapping_writes'=>0,'profile_writes'=>0,'schema_writes'=>0,'schema_writes_acknowledged'=>0,'supplier_calls'=>0,
        'local_stay_context_install'=>['data'=>['receipt'=>['schemaVersion'=>1,'source'=>'anytour-hotel-stay-context-install-v1','operationId'=>AnyTourHotelStaySourceFactsReadV1::FAILURE_OPERATION,
            'controlSha'=>AnyTourHotelStaySourceFactsReadV1::FAILURE_CONTROL,'sourceSha'=>AnyTourHotelStaySourceFactsReadV1::PARENT_SOURCE,'state'=>'preflight_refused','reason'=>'privileges',
            'databaseSha256'=>$id,'contextDatabaseSha256'=>$id,'contextResultSha256'=>hash('sha256',AnyTourHotelStaySourceFactsReadV1::json($context)),
            'ddlOutcomeUnknown'=>false,'attemptedTables'=>[],'createdTables'=>[],'unknownTables'=>[],'journalSha256'=>null,
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'automaticAccepts'=>0,'protectedBefore'=>$protected,'protectedAfter'=>$protected]]]];return [$context,$failure];
}
function facts_run(PDO $db,array $parents,string $state): array {
    global $site;$r=AnyTourHotelStaySourceFactsReadV1::read($db,$parents[0],$parents[1],$site,$parents[0]['local_stay_install_context']['data']['receipt']['databaseSha256']);
    facts_check($r['receipt']['state']===$state,'state '.$state.' actual '.$r['receipt']['state']);
    foreach (['supplierCalls','databaseWrites','schemaWrites','profileWrites','mappingWrites','automaticAccepts'] as $key) facts_check($r['receipt'][$key]===0,'typed0 '.$key);
    facts_check($r['receipt']['importHeld']===true&&$r['receipt']['priceAuthority']===false,'no import or price authority');
    facts_check($r['conceptLookup']===['state'=>'not_read','reason'=>'source_facts_only','rooms'=>null,'meals'=>null,'mappings'=>null],'unknown concept lookup not an empty dictionary');
    $parts=$r;unset($parts['receipt']);facts_check($r['receipt']['factsSha256']===hash('sha256',AnyTourHotelStaySourceFactsReadV1::json($parts)),'exact six-component hash');return $r;
}
function facts_fingerprint(PDO $db): string {$all=[];foreach (['anytour_hotels','anytour_hotel_sources','anytour_offers','anytour_offer_scope_state'] as $table) $all[$table]=$db->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();return hash('sha256',AnyTourHotelStaySourceFactsReadV1::json($all));}

$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$db=facts_fixture();facts_check(count($db->query('SHOW TABLES')->fetchAll())===4,'only four source tables exist, all three V2 absent');$parents=facts_parents($db);
$db->exec('RENAME TABLE anytour_offer_scope_state TO held_source_state');$missing=facts_run($db,$parents,'source_schema_missing');facts_check($missing['receipt']['missingTables']===['anytour_offer_scope_state'],'exact missing source table, no stay dependency');$db->exec('RENAME TABLE held_source_state TO anytour_offer_scope_state');
facts_run($db,$parents,'anchor_missing');$a=facts_hotel($db,101,'PRIVATE FIRST CLASS PROFILE');$b=facts_hotel($db,202,'DIFFERENT REGION PROFILE');
$anchor=facts_offer($db,$a,101,AnyTourHotelStaySourceFactsReadV1::TOUR,$now->modify('-5 days'));
$old=facts_run($db,$parents,'completed_read_only');facts_check($old['receipt']['anchorExpiredRows']===1&&$old['receipt']['currentValidatedOffers']===0,'expired anchor yields historical facts without current offers');
$one=facts_offer($db,$a,101,'current-one',$now,'OP-A','DELUXE SEA VIEW - NON REFUNDABLE');
facts_offer($db,$a,101,'current-two',$now,'OP-B','DELUXE SEA VIEW - NON REFUNDABLE');facts_offer($db,$b,202,'202202202',$now,'OP-A','DELUXE SEA VIEW - NON REFUNDABLE');
facts_offer($db,$a,101,'incomplete',$now,'OP-A','INCOMPLETE ROOM','BB',false);facts_offer($db,$a,101,'expired',$now->modify('-1 day'),'OP-A','EXPIRED ROOM');
$before=facts_fingerprint($db);$r=facts_run($db,$parents,'completed_read_only');facts_check(facts_fingerprint($db)===$before,'all source sentinel bytes unchanged');
facts_check($r['receipt']['currentValidatedOffers']===2&&$r['receipt']['currentFactRows']===2&&count($r['currentCohorts'])===2,'only current complete same-hotel rows');
facts_check(array_unique(array_column($r['sourceFacts'],'hotelId'))===[$a],'different hotel never grouped by same label');
$rooms=array_values(array_filter($r['draftConcepts'],static fn($d)=>$d['raw']==='DELUXE SEA VIEW - NON REFUNDABLE'));
facts_check(count($rooms)===2&&$rooms[0]['scope']['operatorKey']!==$rooms[1]['scope']['operatorKey'],'qualifiers preserved and different operators get distinct drafts');
$otherFacts=(new AnyTourHotelStayOfferCandidatesV2($db))->sourceOnly('202202202',$now);facts_check(array_unique(array_column($otherFacts['draftConcepts'],'hotelId'))===[$b]&&$otherFacts['draftConcepts'][0]['draftId']!==$rooms[0]['draftId'],'same raw labels on a different hotel remain separate draft scope');
foreach ($r['draftConcepts'] as $draft) facts_check($draft['canonicalId']===null&&$draft['nameRu']===null&&$draft['targetLocalKey']===null&&$draft['canonicalFacts']===null&&$draft['status']==='manual_review_required','no invented canonical concept');
$public=AnyTourHotelStaySourceFactsReadV1::json($r);facts_check(!str_contains($public,'999999.99')&&!str_contains($public,'PRIVATE_FIXTURE_PII')&&!str_contains($public,'PRIVATE FIRST CLASS')&&!str_contains($public,'checkin')&&!str_contains($public,$site),'prices/travel/PII/profile/paths never exported');
facts_check($r['privilegeProof']['state']==='direct_metadata_observed'&&$r['privilegeProof']['SELECT']===true,'bounded direct privilege booleans observed');
$other=facts_fixture();facts_run($other,$parents,'database_identity');
$bad=$parents;$bad[1]['local_stay_context_install']['data']['receipt']['reason']='another_reason';facts_run($db,$bad,'context_contract');
$db->prepare('UPDATE anytour_offers SET payload_sha256=? WHERE id=?')->execute([str_repeat('0',64),$anchor]);facts_run($db,$parents,'anchor_payload_invalid');$db->exec('UPDATE anytour_offers SET payload_sha256=SHA2(payload_json,256)');
$conflict=facts_offer($db,$b,202,AnyTourHotelStaySourceFactsReadV1::TOUR,$now->modify('-4 days'));facts_run($db,$parents,'anchor_identity_ambiguous');$db->prepare('DELETE FROM anytour_offers WHERE id=?')->execute([$conflict]);
$db->prepare('UPDATE anytour_offers SET payload_sha256=? WHERE id=?')->execute([str_repeat('0',64),$one]);facts_run($db,$parents,'current_payload_invalid');$db->exec('UPDATE anytour_offers SET payload_sha256=SHA2(payload_json,256)');
$config=file_get_contents($site.'/config.php');file_put_contents($site.'/config.php','changed');facts_run($db,$parents,'protected_drift');file_put_contents($site.'/config.php',$config);
$db->beginTransaction();$nested=facts_run($db,$parents,'caller_transaction');facts_check($db->inTransaction(),'caller transaction retained');$db->rollBack();

final class FactsNoPrivilegeMetadataPdo extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false {if (str_contains($query,'information_schema.USER_PRIVILEGES')) throw new PDOException('PRIVATE GRANT ERROR');return parent::prepare($query,$options);}
}
$limited=facts_fixture(FactsNoPrivilegeMetadataPdo::class);$h=facts_hotel($limited,303,'PRIVATE PROFILE');facts_offer($limited,$h,303,AnyTourHotelStaySourceFactsReadV1::TOUR,$now->modify('-5 days'));
$without=facts_run($limited,facts_parents($limited),'completed_read_only');facts_check($without['privilegeProof']===['state'=>'unavailable','scope'=>'direct_global_or_schema','CREATE'=>null,'REFERENCES'=>null,'SELECT'=>null,'TRIGGER'=>null,'allRequired'=>null],'privilege query failure sanitized independently from source SELECT');
facts_check(!str_contains(AnyTourHotelStaySourceFactsReadV1::json($without),'PRIVATE GRANT ERROR'),'no raw grant error');
for ($i=0;$i<51;++$i) facts_offer($limited,$h,303,'cohort-'.$i,$now,'OP-A','distinct room '.$i);
facts_run($limited,facts_parents($limited),'cohort_limit');

// Real source-row overflow must stop before validating malformed surplus payloads.
$columns=array_column($db->query('SHOW COLUMNS FROM anytour_offers')->fetchAll(),'Field');$columns=array_values(array_diff($columns,['id']));
$digits='(SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)';
$expressions=array_map(static fn($c)=>$c==='identity_sha256'?"SHA2(CONCAT('surplus-',a.n,':',b.n,':',c.n,':',d.n,':',e.n),256)":'o.`'.$c.'`',$columns);
$db->prepare('INSERT INTO anytour_offers (`'.implode('`,`',$columns).'`) SELECT '.implode(',',$expressions).' FROM anytour_offers o CROSS JOIN '.$digits.' a CROSS JOIN '.$digits.' b CROSS JOIN '.$digits.' c CROSS JOIN '.$digits.' d CROSS JOIN '.$digits.' e WHERE o.id=? LIMIT 15001')->execute([$one]);
facts_run($db,$parents,'current_row_limit');
echo 'HOTEL_STAY_SOURCE_FACTS_READ_OK checks='.$checks.' database=NATIVE_MYSQL supplier_calls=0 application_writes=0 schema_writes=0'.PHP_EOL;
