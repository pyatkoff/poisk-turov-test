<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/tourvisor-operator-fuel-passive-intake.php';

function tvpf_ok(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}

function tvpf_diagnostic(string $directory, string $stage, string $id): array
{
    $path = $directory.'/tourvisor-fuel-intake-receipt-v1-'.$stage.'-'.hash('sha256',$id).'.json';
    tvpf_ok(is_file($path) && filesize($path)<=2048, 'bounded diagnostic exists');
    tvpf_ok((fileperms($path)&0777)===0600, 'diagnostic stays private');
    $value = json_decode((string)file_get_contents($path),true,8,JSON_THROW_ON_ERROR);
    $keys = array_keys($value); sort($keys);
    $expected = ['schema_version','source','stage','outcome','code','observed_at','expires_at',
        'elapsed_ms','offer_ref_digest','normalized_response_sha256','observation_count','rule_confirmed'];
    sort($expected);
    tvpf_ok($keys===$expected && $value['schema_version']===1, 'only safe diagnostic fields retained');
    tvpf_ok($value['stage']===$stage && $value['offer_ref_digest']===hash('sha256','tourvisor:tour:'.$id), 'diagnostic bound to own stage and offer');
    tvpf_ok(is_int($value['elapsed_ms']) && $value['elapsed_ms']>=0, 'local intake duration retained');
    tvpf_ok($value['expires_at']===$value['observed_at']+86400, 'diagnostic freshness explicit');
    return $value;
}

function tvpf_segment(string $from, string $to, string $date, string $flight): array
{
    return [
        'departure'=>['date'=>$date,'port'=>['id'=>$from,'shortName'=>$from]],
        'arrival'=>['date'=>$date,'port'=>['id'=>$to,'shortName'=>$to]],
        'company'=>['id'=>'ZF','name'=>'AZUR air'],
        'number'=>$flight,
        'fuelCharges'=>[['amount'=>16874,'currency'=>'RUB','name'=>'fuel']],
    ];
}

function tvpf_tour(string $id, int $hotel, int $nights): array
{
    return [
        'id'=>$id,'adults'=>2,'childs'=>0,'currency'=>'RUB','date'=>'2026-10-11',
        'fuelCharge'=>33748,'operator'=>['name'=>'Intourist','russianName'=>'Интурист','fullName'=>'Интурист'],
        'departure'=>['id'=>1,'name'=>'Москва'],'nights'=>$nights,'hotel'=>['id'=>$hotel],'price'=>176951,
    ];
}

function tvpf_flights(): array
{
    return [
        'error'=>null,
        'flights'=>[[
            'forward'=>[tvpf_segment('VKO','AYT','2026-10-11','ZF1001')],
            'backward'=>[tvpf_segment('AYT','VKO','2026-10-18','ZF1002')],
            'dateForward'=>'2026-10-11','dateBackward'=>'2026-10-18',
            'fuelCharge'=>['value'=>33748,'currency'=>'RUB'],'isDefault'=>true,
            'price'=>['value'=>176951,'currency'=>'RUB'],
        ]],
        'info'=>['flags'=>['noFlight'=>false,'noInsurance'=>false,'noMeal'=>false,'noTransfer'=>false],'surcharges'=>[]],
    ];
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE anytour_offers (
    id INTEGER PRIMARY KEY AUTOINCREMENT, provider TEXT NOT NULL, offer_ref_digest TEXT NOT NULL,
    is_active INTEGER NOT NULL, expires_at TEXT NOT NULL, last_seen_at TEXT NOT NULL,
    operator_json TEXT NOT NULL, checkin TEXT NOT NULL, adults INTEGER NOT NULL, children INTEGER NOT NULL,
    child_ages_json TEXT NOT NULL, payload_json TEXT NOT NULL, payload_sha256 TEXT NOT NULL
)');
$insert = $db->prepare('INSERT INTO anytour_offers
    (provider,offer_ref_digest,is_active,expires_at,last_seen_at,operator_json,checkin,adults,children,child_ages_json,payload_json,payload_sha256)
    VALUES (:provider,:offer,1,:expires,:seen,:operator,:checkin,:adults,:children,:ages,:payload,:payload_sha)');
$add = static function(string $tourId, array $party = ['adults'=>2,'children'=>0,'child_ages'=>[]]) use ($insert): void {
    $payload = json_encode(['tour'=>$tourId,'party'=>$party], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $insert->execute([
        'provider'=>'tourvisor','offer'=>hash('sha256','tourvisor:tour:'.$tourId),
        'expires'=>'2026-09-23 20:00:00','seen'=>'2026-09-22 20:00:00',
        'operator'=>json_encode(['raw'=>'Интурист'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        'checkin'=>'2026-10-11','adults'=>$party['adults'],'children'=>$party['children'],
        'ages'=>json_encode($party['child_ages'],JSON_THROW_ON_ERROR),
        'payload'=>$payload,'payload_sha'=>hash('sha256',$payload),
    ]);
};

$id1 = '43282561000937';
$id2 = '43282575574005';
$add($id1); $add($id2);
$root = sys_get_temp_dir().'/tourvisor-passive-fuel-'.bin2hex(random_bytes(6));
$directory = $root.'/searches';
mkdir($directory,0700,true);
$now = new DateTimeImmutable('2026-09-22T20:00:00Z');

$tour1 = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id1,tvpf_tour($id1,1006,7),$db,$directory,$now);
tvpf_ok(($tour1['status']??null)==='tour_retained','first tour retained');
$fuel1 = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id1,tvpf_flights(),$directory,$now);
tvpf_ok(($fuel1['observationCount']??null)===1 && ($fuel1['ruleConfirmed']??null)===false,'one observation is not a rule');

$tour2 = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id2,tvpf_tour($id2,1010,14),$db,$directory,$now->modify('+30 seconds'));
tvpf_ok(($tour2['status']??null)==='tour_retained','second tour retained');
$fuel2 = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id2,tvpf_flights(),$directory,$now->modify('+30 seconds'));
tvpf_ok(($fuel2['observationCount']??null)===2 && ($fuel2['ruleConfirmed']??null)===true,'two hotels/nights confirm one rule');
$tourDiagnostic = tvpf_diagnostic($directory,'tour',$id1);
tvpf_ok($tourDiagnostic['code']==='tour_retained' && $tourDiagnostic['outcome']==='retained', 'successful tour diagnostic');
tvpf_ok($tourDiagnostic['observed_at']===$now->getTimestamp(), 'diagnostic observation time retained');
tvpf_ok($tourDiagnostic['observation_count']===null && $tourDiagnostic['rule_confirmed']===null, 'tour-only diagnostic does not invent a rule result');
$oneDiagnostic = tvpf_diagnostic($directory,'flights',$id1);
tvpf_ok($oneDiagnostic['observation_count']===1 && $oneDiagnostic['rule_confirmed']===false, 'one actual observation stays unconfirmed');
$twoDiagnostic = tvpf_diagnostic($directory,'flights',$id2);
tvpf_ok($twoDiagnostic['observation_count']===2 && $twoDiagnostic['rule_confirmed']===true, 'actual persisted count and rule result retained');
tvpf_ok($twoDiagnostic['normalized_response_sha256']===AnyTourOperatorFuelRuleEvidenceV1::hash(tvpf_flights()), 'normalized response provenance retained');

$rules = glob($directory.'/operator-fuel-rule-v2-*.json');
tvpf_ok(is_array($rules) && count($rules)===1,'one existing rule-store envelope');
$stored = json_decode((string)file_get_contents($rules[0]),true,64,JSON_THROW_ON_ERROR);
tvpf_ok(count($stored['observations']??[])===2,'two independent observations stored');
tvpf_ok(($stored['direction']['operator_family']??null)==='intourist'
    && is_string($stored['direction']['market']??null)
    && is_string($stored['direction']['destination']??null),'operator+direction store key retained');
tvpf_ok(($stored['observations'][0]['scope']['party']['child_ages']??null)===[],'exact party retained in provenance');
tvpf_ok(!isset($stored['observations'][0]['scope']['hotel'])&&!isset($stored['observations'][0]['scope']['nights']),'hotel/nights absent from provenance scope');
tvpf_ok(glob($directory.'/tourvisor-fuel-pending-v1-*.json')===[],'paired pending state removed');
tvpf_ok((int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn()===2,'runtime made no DB writes');

$missing = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour('999999',tvpf_tour('999999',1111,7),$db,$directory,$now);
tvpf_ok(($missing['reason']??null)==='offer_context_missing','unknown offer fails closed');
$unpaired = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights('999999',tvpf_flights(),$directory,$now);
tvpf_ok(($unpaired['reason']??null)==='tour_pair_missing','unpaired flights fail closed');
$skipDiagnostic = tvpf_diagnostic($directory,'flights','999999');
tvpf_ok($skipDiagnostic['code']==='tour_pair_missing' && $skipDiagnostic['outcome']==='skipped', 'skip reason retained');
tvpf_ok($skipDiagnostic['observation_count']===null && $skipDiagnostic['rule_confirmed']===null, 'missing result is not zero/false');

$id3 = '43282575574006'; $add($id3);
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id3,tvpf_tour($id3,1011,7),$db,$directory,$now);
$invalidFlights = tvpf_flights();
$invalidFlights['flights'][0]['forward'] = [];
$invalidFlights['private_marker'] = 'must not be copied to diagnostic';
try {
    AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id3,$invalidFlights,$directory,$now);
    throw new RuntimeException('Missing expected default-flight rejection');
} catch (InvalidArgumentException $error) {
    tvpf_ok($error->getMessage()==='TV_FUEL_FORWARD', 'original validation exception preserved');
}
$rejected = tvpf_diagnostic($directory,'flights',$id3);
tvpf_ok($rejected['outcome']==='rejected' && $rejected['code']==='TV_FUEL_FORWARD', 'exact intake rejection retained');
tvpf_ok($rejected['observation_count']===null && $rejected['rule_confirmed']===null, 'rejected capture cannot mint a rule result');
tvpf_ok(!str_contains(json_encode($rejected,JSON_THROW_ON_ERROR),'private_marker'), 'raw response not retained in diagnostic');
tvpf_ok(json_decode((string)file_get_contents($rules[0]),true,64,JSON_THROW_ON_ERROR)===$stored, 'rejected capture leaves rule evidence unchanged');

$privateError = new RuntimeException('opaque database detail must not be retained');
$brokenDb = new class($privateError) extends PDO {
    public function __construct(private Throwable $error) {}
    public function setAttribute(int $attribute, mixed $value): bool { return true; }
    public function prepare(string $query, array $options = []): PDOStatement|false { throw $this->error; }
};
try {
    AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour('999998',tvpf_tour('999998',1111,7),$brokenDb,$directory,$now);
    throw new RuntimeException('Missing expected database error');
} catch (RuntimeException $error) {
    tvpf_ok($error===$privateError, 'original exception object preserved');
}
$redacted = tvpf_diagnostic($directory,'tour','999998');
tvpf_ok($redacted['outcome']==='rejected' && $redacted['code']==='unclassified_error', 'unrecognized exception redacted');
tvpf_ok(!str_contains(json_encode($redacted,JSON_THROW_ON_ERROR),$privateError->getMessage()), 'exception text not persisted');

$sentinel = $root.'/sentinel'; file_put_contents($sentinel,'unchanged');
$blockedPath = $directory.'/tourvisor-fuel-intake-receipt-v1-tour-'.hash('sha256',$id1).'.json';
unlink($blockedPath); symlink($sentinel,$blockedPath);
$unchangedResult = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id1,tvpf_tour($id1,1006,7),$db,$directory,$now);
tvpf_ok($unchangedResult===$tour1 && file_get_contents($sentinel)==='unchanged', 'diagnostic write failure cannot change success or follow a link');
unlink($blockedPath);
$blockedErrorPath = $directory.'/tourvisor-fuel-intake-receipt-v1-tour-'.hash('sha256','999998').'.json';
unlink($blockedErrorPath); symlink($sentinel,$blockedErrorPath);
try {
    AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour('999998',tvpf_tour('999998',1111,7),$brokenDb,$directory,$now);
    throw new RuntimeException('Missing expected database error');
} catch (RuntimeException $error) {
    tvpf_ok($error===$privateError && file_get_contents($sentinel)==='unchanged', 'diagnostic write failure preserves original exception');
}
unlink($blockedErrorPath); unlink($sentinel);

$expired = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id1,tvpf_flights(),$directory,$now->modify('+901 seconds'));
tvpf_ok(($expired['reason']??null)==='tour_pair_expired','expired pending pair stays rejected');
$expiryDiagnostic = tvpf_diagnostic($directory,'flights',$id1);
tvpf_ok($expiryDiagnostic['code']==='tour_pair_expired' && $expiryDiagnostic['outcome']==='skipped', 'current expiry reason replaces the previous same-stage snapshot');
tvpf_ok($expiryDiagnostic['rule_confirmed']===null && $expiryDiagnostic['observed_at']===$now->getTimestamp()+901, 'old success cannot be reused as current rule authority');

$api = file_get_contents(__DIR__.'/../v2/api-v2.php');
tvpf_ok(is_string($api)&&str_contains($api,'tourvisor_fuel_passive_tour($id, $data);'),'tour handoff wired');
tvpf_ok(str_contains($api,'tourvisor_fuel_passive_flights($id, $data);'),'flights handoff wired');
foreach (['tour','flights'] as $action) {
    $next = $action==='tour' ? "case 'flights':" : "case 'rooms':";
    $start = strpos($api,"case '".$action."':"); $end = strpos($api,$next,$start===false?0:$start+1);
    tvpf_ok($start!==false&&$end!==false&&$end>$start,$action.' case bounds');
    $body = substr($api,$start,$end-$start);
    tvpf_ok(substr_count($body,'tv_get(')===1,$action.' keeps one supplier call');
    tvpf_ok(strpos($body,'tv_get(')<strpos($body,'tourvisor_fuel_passive_')&&strpos($body,'tourvisor_fuel_passive_')<strpos($body,'out($data);'),$action.' response-transparent order');
}

// Exercise the actual helper include and gateway runtime in fresh PHP processes.
// Two physical copies of the DB helper must not redeclare its global functions.
$gatewayDirectory = $root.'/gateway';
mkdir($gatewayDirectory.'/data',0700,true);
$dbSource = __DIR__.'/../v2/data/db-v1.php';
copy($dbSource,$gatewayDirectory.'/data/db-v1.php');
$preloadedDb = $root.'/db-preloaded.php';
copy($dbSource,$preloadedDb);
$runtimeStart = strpos($api,'function tourvisor_fuel_passive_runtime(');
$runtimeEnd = strpos($api,'function tourvisor_fuel_passive_tour(',$runtimeStart===false?0:$runtimeStart);
tvpf_ok($runtimeStart!==false&&$runtimeEnd!==false&&$runtimeEnd>$runtimeStart,'runtime function bounds');
$runtime = str_replace('__DIR__',var_export($gatewayDirectory,true),substr($api,$runtimeStart,$runtimeEnd-$runtimeStart));
$passivePath = realpath(__DIR__.'/../app/integrations/tourvisor-operator-fuel-passive-intake.php');
foreach (['absent','none','complete','partial'] as $case) {
    $child = '$case='.var_export($case,true).';$preloaded='.var_export($preloadedDb,true).';'
        .'$helper='.var_export($passivePath,true).';$runtime='.var_export($runtime,true).';'
        . <<<'CHILD'
$helpers=['v2_data_db_config','v2_data_db','v2_data_normalize_text','v2_data_slug'];
if($case==='complete') require $preloaded;
if($case==='partial') eval('function v2_data_db_config(): array { throw new RuntimeException("DB must not be called"); }');
if($case!=='absent') require $helper;
$before=count(array_filter($helpers,'function_exists'));
if($before!==($case==='complete'?4:($case==='partial'?1:0))) throw new RuntimeException('Helper loaded DB globals');
eval($runtime);
if(tourvisor_fuel_passive_runtime()!==null) throw new RuntimeException('Unexpected configured runtime');
$after=count(array_filter($helpers,'function_exists'));
if($after!==($case==='none'||$case==='complete'?4:($case==='partial'?1:0))) throw new RuntimeException('Runtime helper readiness');
if($case==='complete'&&(new ReflectionFunction('v2_data_db'))->getFileName()!==$preloaded) throw new RuntimeException('Loaded DB helper replaced');
echo json_encode(['case'=>$case,'before'=>$before,'after'=>$after,'db_calls'=>0],JSON_THROW_ON_ERROR);
CHILD;
    $process = proc_open([PHP_BINARY,'-n','-d','allow_url_fopen=0','-r',$child],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    tvpf_ok(is_resource($process),$case.' subprocess starts');
    fclose($pipes[0]);$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
    tvpf_ok($status===0&&$stderr==='',$case.' include/runtime must be clean: '.$stderr);
    $result=json_decode($stdout,true,8,JSON_THROW_ON_ERROR);
    tvpf_ok(($result['case']??null)===$case&&($result['db_calls']??null)===0,$case.' no database access');
}
unlink($gatewayDirectory.'/data/db-v1.php');rmdir($gatewayDirectory.'/data');rmdir($gatewayDirectory);unlink($preloadedDb);

// A resolved search offer can retain exact identity/party before its fuel is
// known, without inserting an unpriced row or inventing a quote participant.
function tvpf_search_entry(string $id, int $hotel, DateTimeImmutable $now,
    array $party = ['adults'=>2,'children'=>0,'child_ages'=>[]]): array
{
    return ['anytour_hotel_id'=>77,'offer'=>[
        'provider'=>'tourvisor','operator'=>['raw'=>'Интурист'],
        'identity'=>[
            'offer_ref_digest'=>hash('sha256','tourvisor:tour:'.$id),
            'search_ref_digest'=>hash('sha256','tourvisor:search:123456'),
            'provider_hotel_ref_digest'=>hash('sha256','tourvisor:hotel:'.$hotel),
        ],
        'checkin'=>'2026-10-11','party'=>$party,'observed_at'=>$now->format('Y-m-d\\TH:i:s\\Z'),
        'money'=>['must_not_be_copied'=>'150824'],'final_price_verified'=>false,
    ]];
}
$contextDirectory = $root.'/context/searches'; mkdir($contextDirectory,0700,true);
$contextId = '73333333333331';
$contextEntry = tvpf_search_entry($contextId,1111,$now);
$contextResult = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$contextEntry],$contextDirectory,$now);
tvpf_ok($contextResult['contextCount']===1,'one exact resolved search context retained');
$contextPath = $contextDirectory.'/tourvisor-fuel-search-context-v1-'.hash('sha256','tourvisor:tour:'.$contextId).'.json';
$contextRaw = (string)file_get_contents($contextPath);
tvpf_ok((fileperms($contextPath)&0777)===0600 && strlen($contextRaw)<=16384,'bounded private context');
tvpf_ok(!str_contains($contextRaw,'must_not_be_copied')&&!str_contains($contextRaw,'150824')
    &&!str_contains($contextRaw,'final_price_verified'),'context carries no money or price authority');
$contextTour = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($contextId,tvpf_tour($contextId,1111,7),$db,$contextDirectory,$now);
tvpf_ok(($contextTour['status']??null)==='tour_retained','search context supplies exact party without ready-price DB row');
$contextFlights = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($contextId,tvpf_flights(),$contextDirectory,$now);
tvpf_ok(($contextFlights['observationCount']??null)===1 && ($contextFlights['ruleConfirmed']??null)===false,
    'existing strict default-flight intake retains one observation only');
tvpf_ok((int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn()===3,'context bridge makes no offer DB writes');
$unresolved = tvpf_search_entry('73333333333332',1111,$now);$unresolved['anytour_hotel_id']=null;
tvpf_ok(AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$unresolved],$contextDirectory,$now)['contextCount']===0,
    'unresolved hotel cannot create context');
$expiredContext = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($contextId,tvpf_tour($contextId,1111,7),$db,$contextDirectory,$now->modify('+901 seconds'));
tvpf_ok(($expiredContext['reason']??null)==='offer_context_missing','expired context is not renewed by quote');

$infantContextId='73333333333333';
$infantEntry=tvpf_search_entry($infantContextId,1111,$now,['adults'=>2,'children'=>1,'child_ages'=>[0]]);
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$infantEntry],$contextDirectory,$now);
$infantTour=tvpf_tour($infantContextId,1111,7);$infantTour['childs']=1;
tvpf_ok((AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($infantContextId,$infantTour,$db,$contextDirectory,$now)['status']??null)==='tour_retained',
    'explicit age zero from search remains exact; no infant price is asserted');
$badAges=$infantEntry;$badAges['offer']['party']['child_ages']=[];
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$badAges],$contextDirectory,$now); throw new RuntimeException('Missing age rejection'); }
catch (InvalidArgumentException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_INTEGRITY','missing ages never become an empty party'); }
$alone=$infantEntry;$alone['offer']['party']['adults']=0;
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$alone],$contextDirectory,$now); throw new RuntimeException('Missing accompanying adult rejection'); }
catch (InvalidArgumentException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_INTEGRITY','infant context requires an accompanying adult'); }
$conflictId='73333333333334';
$conflictEntry=tvpf_search_entry($conflictId,1111,$now);
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$conflictEntry],$contextDirectory,$now);
$conflictEntry['offer']['party']['adults']=3;
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$conflictEntry],$contextDirectory,$now);
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($conflictId,tvpf_tour($conflictId,1111,7),$db,$contextDirectory,$now); throw new RuntimeException('Missing context conflict'); }
catch (DomainException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_CONFLICT','ambiguous search party fails closed'); }
$dbConflict=tvpf_search_entry($id3,1011,$now,['adults'=>3,'children'=>0,'child_ages'=>[]]);
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$dbConflict],$contextDirectory,$now);
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id3,tvpf_tour($id3,1011,7),$db,$contextDirectory,$now); throw new RuntimeException('Missing DB/context conflict'); }
catch (DomainException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_CONFLICT','search cache cannot override a conflicting active DB context'); }
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($contextId,tvpf_tour($contextId,1112,7),$db,$contextDirectory,$now); throw new RuntimeException('Missing hotel conflict'); }
catch (DomainException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_CONFLICT','quoted provider hotel must match its retained search identity'); }
$foreign=$contextEntry;$foreign['offer']['provider']='anex';
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$foreign],$contextDirectory,$now); throw new RuntimeException('Missing provider rejection'); }
catch (InvalidArgumentException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_INTEGRITY','foreign provider cannot create TV context'); }
chmod($contextPath,0644);clearstatcache(true,$contextPath);
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($contextId,tvpf_tour($contextId,1111,7),$db,$contextDirectory,$now); throw new RuntimeException('Missing mode rejection'); }
catch (RuntimeException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_INTEGRITY','unsafe context file fails closed'); }
chmod($contextPath,0600);clearstatcache(true,$contextPath);
$held=$contextPath.'.held';rename($contextPath,$held);symlink($held,$contextPath);
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$contextEntry],$contextDirectory,$now); throw new RuntimeException('Missing context link rejection'); }
catch (RuntimeException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_INTEGRITY'&&file_get_contents($held)===$contextRaw,'context writer rejects links without touching their target'); }
unlink($contextPath);rename($held,$contextPath);link($contextPath,$held);clearstatcache(true,$contextPath);
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($contextId,tvpf_tour($contextId,1111,7),$db,$contextDirectory,$now); throw new RuntimeException('Missing context hardlink rejection'); }
catch (RuntimeException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_INTEGRITY','context reader rejects hardlinks'); }
unlink($held);clearstatcache(true,$contextPath);
array_map('unlink',array_filter(glob($contextDirectory.'/*')?:[], 'is_file'));
rmdir($contextDirectory);rmdir(dirname($contextDirectory));


 // A dotted supplier date must pass the real tour -> pending -> flights -> store
 // path and converge with ISO notation, without mutating either response.
$dateDirectory = $root.'/date/searches'; mkdir($dateDirectory,0700,true);
$dottedTour = tvpf_tour($id1,1006,7); $dottedTour['date'] = '11.10.2026';
$dottedFlights = tvpf_flights();
$dottedFlights['flights'][0]['dateForward'] = '11.10.2026';
$dottedFlights['flights'][0]['dateBackward'] = '18.10.2026';
foreach (['forward'=>'11.10.2026','backward'=>'18.10.2026'] as $leg=>$day) {
    foreach (['departure','arrival'] as $point) $dottedFlights['flights'][0][$leg][0][$point]['date'] = $day;
}
$rawTour = $dottedTour; $rawFlights = $dottedFlights;
tvpf_ok((AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id1,$dottedTour,$db,$dateDirectory,$now)['status']??null)==='tour_retained',
    'supported dotted tour date matches strict ISO stored context');
$pendingRaw = json_decode((string)file_get_contents($dateDirectory.'/tourvisor-fuel-pending-v1-'.hash('sha256',$id1).'.json'),true,64,JSON_THROW_ON_ERROR);
tvpf_ok($pendingRaw['tour'] === $rawTour && $pendingRaw['valid_from']==='2026-10-01'
    && $pendingRaw['valid_to']==='2026-10-31', 'raw tour retained unchanged with canonical validity dates');
$dateFirst = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id1,$dottedFlights,$dateDirectory,$now);
tvpf_ok($dateFirst['observationCount']===1 && $dateFirst['ruleConfirmed']===false, 'one dotted observation is not a rule');
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id2,tvpf_tour($id2,1010,14),$db,$dateDirectory,$now);
$dateSecond = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id2,$dottedFlights,$dateDirectory,$now);
tvpf_ok($dateSecond['observationCount']===2 && $dateSecond['ruleConfirmed']===true,
    'two independent mixed-format offers confirm the same rule through canonical target dates');
tvpf_ok($dottedTour===$rawTour && $dottedFlights===$rawFlights, 'tour and flights response transparency preserved');
$dateRules = glob($dateDirectory.'/operator-fuel-rule-v2-*.json');
tvpf_ok(count($dateRules)===1, 'date formatting does not split the rule store');
$beforeDateRules = file_get_contents($dateRules[0]);
foreach ([null, '31.02.2026', '2026-02-30', '11/10/2026', '2026-10-11T00:00:00Z'] as $invalid) {
    $badDateTour = $dottedTour; $badDateTour['date']=$invalid;
    try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id1,$badDateTour,$db,$dateDirectory,$now); throw new RuntimeException('Missing invalid date rejection'); }
    catch (InvalidArgumentException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_DATE', 'invalid supplier date stays rejected'); }
}
$wrongDateTour=$dottedTour; $wrongDateTour['date']='12.10.2026';
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id1,$wrongDateTour,$db,$dateDirectory,$now); throw new RuntimeException('Missing date conflict'); }
catch (DomainException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_DATE_CONFLICT', 'valid but different day does not borrow context'); }
$badInternal=tvpf_search_entry('73333333333335',1111,$now); $badInternal['offer']['checkin']='11.10.2026';
try { AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureSearchContexts([$badInternal],$dateDirectory,$now); throw new RuntimeException('Missing internal ISO rejection'); }
catch (InvalidArgumentException $error) { tvpf_ok($error->getMessage()==='TV_PASSIVE_CONTEXT_INTEGRITY', 'internal context remains strict ISO'); }
tvpf_ok(file_get_contents($dateRules[0])===$beforeDateRules
    && glob($dateDirectory.'/tourvisor-fuel-pending-v1-*.json')===[], 'date rejections create no pending state or rule mutations');
tvpf_ok((int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn()===3, 'date conversion adds no offer DB writes');
array_map('unlink',array_filter(glob($dateDirectory.'/*')?:[], 'is_file'));
rmdir($dateDirectory);rmdir(dirname($dateDirectory));

array_map('unlink',array_filter(glob($directory.'/*')?:[], 'is_file'));
rmdir($directory); rmdir($root);
echo "TOURVISOR_PASSIVE_FUEL_OK observations=2 rules=1 supplier_http_added=0 db_writes=0 hotels=2 nights=7,14 include_cases=4 diagnostics=success,skip,reject,redaction,write_failure,expiry search_context=party,identity,unknown,expiry,conflict,no_money\n";
