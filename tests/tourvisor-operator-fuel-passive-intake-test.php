<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/tourvisor-operator-fuel-passive-intake.php';
require_once __DIR__ . '/../v2/api-anex-search3-preview.php';

function tvpf_ok(bool $value, string $message): void
{
    $GLOBALS['tvpf_checks'] = ($GLOBALS['tvpf_checks'] ?? 0) + 1;
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

function tvpf_capture_diagnostic(string $header, string $outcome, string $code): array
{
    $prefix = 'X-AnyTour-Fuel-Monitoring: ';
    if (str_starts_with($header, $prefix)) $header = substr($header, strlen($prefix));
    tvpf_ok(strlen($header) <= 2048 && preg_match('/\A[A-Za-z0-9_-]+\z/D', $header) === 1, 'bounded encoded capture diagnostic');
    $value = json_decode(base64_decode(strtr($header, '-_', '+/'), true), true, 8, JSON_THROW_ON_ERROR);
    $keys = array_keys($value); sort($keys);
    $expected = ['schema_version','source','stage','outcome','code','as_of','elapsed_ms',
        'complete_population','accuracy_evaluated','final_price_verified'];
    if ($code === 'tour_pair_missing') $expected[] = 'prior_tour';
    sort($expected);
    tvpf_ok($keys === $expected && $value['schema_version'] === 1
        && $value['source'] === 'tourvisor_passive_fuel_capture_diagnostic', 'distinct exact diagnostic schema without aggregate counts');
    tvpf_ok($value['stage'] === 'flights' && $value['outcome'] === $outcome && $value['code'] === $code, 'allowlisted helper outcome');
    tvpf_ok(is_int($value['as_of']) && is_int($value['elapsed_ms']) && $value['elapsed_ms'] >= 0, 'local processing time only');
    tvpf_ok($value['complete_population'] === false && $value['accuracy_evaluated'] === false
        && $value['final_price_verified'] === false, 'capture diagnostic cannot mint population or price authority');
    if ($code === 'tour_pair_missing') {
        tvpf_ok(is_array($value['prior_tour'] ?? null), 'missing pair has explicit prior-tour availability');
        $prior = $value['prior_tour'];
        if (($prior['outcome'] ?? null) === 'unavailable') tvpf_ok($prior === ['outcome'=>'unavailable'], 'unavailable has no invented prior facts');
        else {
            $priorKeys=array_keys($prior); sort($priorKeys);
            tvpf_ok($priorKeys===['age_seconds','code','outcome'] && is_int($prior['age_seconds'])
                && $prior['age_seconds']>=0 && $prior['age_seconds']<900, 'only bounded prior-tour context exported');
        }
    }
    return $value;
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

function tvpf_session(array $tour, array $flights, DateTimeImmutable $now, array $ages = []): array
{
    $id = (string)$tour['id']; $ref = 'tourvisor:'.hash('sha256','tourvisor:tour:'.$id);
    $at = $now->getTimestamp(); $search = str_repeat('f',32);
    $tourId = str_repeat('a',32); $flightsId = str_repeat('b',32);
    $replies = []; $requests = []; $records = [];
    foreach (['tv_tour'=>[$tourId,$tour],'tv_flights'=>[$flightsId,$flights]] as $action=>[$rid,$data]) {
        $requests[$action] = ['action'=>$action,'provider'=>'tourvisor','request_id'=>$rid,
            'generation'=>3,'search_ref'=>$search,'offer_ref'=>$ref];
        $replies[$action] = ['provider'=>'tourvisor','generation'=>3,'search_ref'=>$search,'data'=>$data];
        $records[$rid] = ['state'=>'ready','expires_at'=>$at+30,'reply'=>$replies[$action]];
    }
    $session = ['tourvisor_context_generation'=>3,'tourvisor_requests'=>$records,'tourvisor_context'=>[
        'generation'=>3,'search_ref'=>$search,'state'=>'ready','expires_at'=>$at+60,
        'params'=>['adults'=>2,'childs'=>$ages],
        'offers'=>[$ref=>['id'=>$id,'date'=>$tour['date'],'hotel'=>['uiId'=>$tour['hotel']['id']],
            'operators'=>['Интурист','Intourist'],'operations'=>['tv_tour'=>$tourId,'tv_flights'=>$flightsId],
            'checked'=>['tour'=>$tour,'observed_at'=>$at-1,'expires_at'=>$at+30]]],
    ]];
    return [$requests,$replies,$session,$ref];
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

// Only the isolated localhost server below enters this fixture. CI's CLI entry
// always runs the full suite, even if the environment variable is inherited.
if (PHP_SAPI === 'cli-server' && getenv('ANYTOUR_TEST_PASSIVE_FUEL_HTTP') === '1') {
    tvpf_ok(!ini_get('allow_url_fopen'), 'URL fopen remains disabled');
    $case = $_GET['case'] ?? '';
    tvpf_ok(in_array($case, ['retained','skipped','prior_skipped','rejected','session'], true), 'known HTTP fixture');
    if (in_array($case,['prior_skipped','session'],true)) $id1='999997';
    $tour = tvpf_tour($id1,1006,7); $tour['fuelCharge'] = '0.00';
    $flights = tvpf_flights(); $flights['flights'][0]['fuelCharge']['value'] = '0.00';
    if ($case === 'session') {
        [$requests,$replies,$source] = tvpf_session($tour,$flights,$now);
        anytour_anex_search3_tv_passive($requests['tv_tour'],$replies['tv_tour'],$source,$directory,$now->getTimestamp());
    } elseif ($case !== 'skipped') AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id1,$tour,$db,$directory,$now);
    if ($case === 'rejected') $flights['flights'][0]['fuelCharge']['value'] = 'not-money';
    $rejected = false;
    try {
        if ($case === 'session') {
            anytour_anex_search3_tv_passive($requests['tv_flights'],$replies['tv_flights'],$source,$directory,$now->getTimestamp());
            $receipt = ['ok'=>true];
        } else $receipt = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id1,$flights,$directory,$now);
        tvpf_ok(in_array($case,['skipped','prior_skipped'],true) ? $receipt['ok'] === false : $receipt['ok'] === true, 'HTTP capture outcome');
    } catch (InvalidArgumentException $error) {
        tvpf_ok($case === 'rejected' && $error->getMessage() === 'TV_FUEL_AMOUNT' && $error->getCode() === 7104,
            'expected malformed fixture');
        $rejected = true;
    }
    tvpf_ok($case !== 'rejected' || $rejected, 'malformed fixture actually rejected');
    array_map('unlink',array_filter(glob($directory.'/*')?:[], 'is_file'));
    rmdir($directory); rmdir($root);
    // Like the existing gateway, passive rejection cannot replace supplier JSON.
    http_response_code(200); header('Content-Type: application/json');
    echo json_encode($flights,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    return;
}

$tour1 = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id1,tvpf_tour($id1,1006,7),$db,$directory,$now);
tvpf_ok(($tour1['status']??null)==='tour_retained','first tour retained');
$fuel1 = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id1,tvpf_flights(),$directory,$now);
tvpf_ok(($fuel1['observationCount']??null)===1 && ($fuel1['ruleConfirmed']??null)===false,'one observation is not a rule');

$tour2 = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id2,tvpf_tour($id2,1010,14),$db,$directory,$now->modify('+30 seconds'));
tvpf_ok(($tour2['status']??null)==='tour_retained','second tour retained');
$fuel2 = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id2,tvpf_flights(),$directory,$now->modify('+30 seconds'));
tvpf_ok(($fuel2['observationCount']??null)===2 && ($fuel2['ruleConfirmed']??null)===false,'two hotels/nights remain monitoring observations');
$tourDiagnostic = tvpf_diagnostic($directory,'tour',$id1);
tvpf_ok($tourDiagnostic['code']==='tour_retained' && $tourDiagnostic['outcome']==='retained', 'successful tour diagnostic');
tvpf_ok($tourDiagnostic['observed_at']===$now->getTimestamp(), 'diagnostic observation time retained');
tvpf_ok($tourDiagnostic['observation_count']===null && $tourDiagnostic['rule_confirmed']===null, 'tour-only diagnostic does not invent a rule result');
$oneDiagnostic = tvpf_diagnostic($directory,'flights',$id1);
tvpf_ok($oneDiagnostic['observation_count']===1 && $oneDiagnostic['rule_confirmed']===false, 'one actual observation stays unconfirmed');
$twoDiagnostic = tvpf_diagnostic($directory,'flights',$id2);
tvpf_ok($twoDiagnostic['observation_count']===2 && $twoDiagnostic['rule_confirmed']===false, 'persisted monitoring count does not mint pricing authority');
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

// Existing native capture path, same invalid default even when another pair has fuel.
foreach ([
    'flight-missing'=>[null,7101,'TV_FUEL_AMOUNT_FLIGHT_MISSING'],
    'flight-shape'=>[['private-amount-canary'],7102,'TV_FUEL_AMOUNT_FLIGHT_SHAPE'],
    'value-missing'=>[['currency'=>'RUB'],7103,'TV_FUEL_AMOUNT_VALUE_MISSING'],
    'value-format'=>[['value'=>'private-amount-canary','currency'=>'RUB'],7104,'TV_FUEL_AMOUNT_VALUE_FORMAT'],
    'negative'=>[['value'=>-1,'currency'=>'RUB'],7104,'TV_FUEL_AMOUNT_VALUE_FORMAT'],
    'tour-format'=>[tvpf_flights()['flights'][0]['fuelCharge'],7106,'TV_FUEL_AMOUNT_TOUR_VALUE'],
] as $kind=>[$fuel,$tag,$code]) {
    $tour = tvpf_tour($id3,1011,7);
    if ($kind === 'tour-format') $tour['fuelCharge'] = 'private-amount-canary';
    AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id3,$tour,$db,$directory,$now);
    $bad = tvpf_flights();
    $alternative = $bad['flights'][0]; $alternative['isDefault'] = false;
    $bad['flights'][] = $alternative;
    $bad['flights'][0]['fuelCharge'] = $fuel;
    $bad['private_marker'] = 'private-amount-canary';
    $before = serialize($bad);
    $pendingFiles = glob($directory.'/tourvisor-fuel-pending-v1-*.json');
    $pendingBefore = array_map('file_get_contents',$pendingFiles);
    try {
        AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id3,$bad,$directory,$now);
        throw new RuntimeException('Missing original amount rejection: '.$kind);
    } catch (InvalidArgumentException $error) {
        tvpf_ok($error->getMessage()==='TV_FUEL_AMOUNT' && $error->getCode()===$tag, 'original amount exception preserved: '.$kind);
    }
    $diagnostic = tvpf_diagnostic($directory,'flights',$id3);
    tvpf_ok($diagnostic['outcome']==='rejected' && $diagnostic['code']===$code, 'allowlisted exact amount branch retained: '.$kind);
    tvpf_ok($diagnostic['observation_count']===null && $diagnostic['rule_confirmed']===null, 'amount failure cannot mint count or rule: '.$kind);
    tvpf_ok(!str_contains(json_encode($diagnostic,JSON_THROW_ON_ERROR),'private-amount-canary'), 'no raw amount, payload or private text retained: '.$kind);
    tvpf_ok($diagnostic['normalized_response_sha256']===AnyTourOperatorFuelRuleEvidenceV1::hash($bad)
        && serialize($bad)===$before, 'raw money and provenance unchanged: '.$kind);
    tvpf_ok(array_map('file_get_contents',$pendingFiles)===$pendingBefore, 'rejected amount leaves pending bytes unchanged: '.$kind);
    tvpf_ok(json_decode((string)file_get_contents($rules[0]),true,64,JSON_THROW_ON_ERROR)===$stored, 'alternative pair never borrowed into rule: '.$kind);
}

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
tvpf_ok($dateSecond['observationCount']===2 && $dateSecond['ruleConfirmed']===false,
    'two independent mixed-format offers are retained without automatic rule promotion');
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

// Persist a complete monitoring cohort, including the differing fifth sample.
// Four matches out of five do not themselves authorize a reusable price rule.
$monitorDirectory=$root.'/monitor/searches'; mkdir($monitorDirectory,0700,true);
$monitorIds=[$id1,$id2,$id3,'43282575574007','43282575574008'];
$monitorHeaders=[];
$captureHeader=static function(string $line) use (&$monitorHeaders): void { $monitorHeaders[]=$line; };
$add($monitorIds[3]); $add($monitorIds[4]);
$dbCount=(int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn();
foreach ($monitorIds as $i=>$id) {
    $tour=tvpf_tour($id,1011,7);
    $tour['private_marker']='must-not-enter-observation';
    if ($i===4) $tour['fuelCharge']=1;
    $beforeTour=$tour; $flights=tvpf_flights(); $beforeFlights=$flights;
    AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id,$tour,$db,$monitorDirectory,$now);
    $captured=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id,$flights,$monitorDirectory,$now,$captureHeader);
    tvpf_ok($captured['observationCount']===$i+1 && $captured['ruleConfirmed']===false,
        'every valid comparison retained without automatic pricing authority');
    tvpf_ok($tour===$beforeTour && $flights===$beforeFlights, 'monitoring cannot change supplier response money');
}
$monitorPath=(glob($monitorDirectory.'/operator-fuel-rule-v2-*.json'))[0];
$monitorBytes=file_get_contents($monitorPath);
$monitorRows=json_decode($monitorBytes,true,64,JSON_THROW_ON_ERROR)['observations'];
tvpf_ok(count($monitorHeaders)===5 && str_starts_with($monitorHeaders[4],'X-AnyTour-Fuel-Monitoring: '),
    'one safe header emitted per successful normal capture');
$monitorEncoded=substr($monitorHeaders[4],strlen('X-AnyTour-Fuel-Monitoring: '));
$monitorSummary=json_decode(base64_decode(strtr($monitorEncoded,'-_','+/'),true),true,16,JSON_THROW_ON_ERROR);
tvpf_ok(strlen($monitorEncoded)<=2048 && $monitorSummary['retained_count']===5
    && $monitorSummary['fresh_count']===5 && $monitorSummary['buckets'][0]['paired_count']===5
    && $monitorSummary['buckets'][0]['matched_count']===4 && $monitorSummary['buckets'][0]['different_count']===1
    && $monitorSummary['buckets'][0]['absolute_difference_minor_sum']===3374700,
    'header derives the current retained denominator and exact stage difference');
tvpf_ok($monitorSummary['accuracy_evaluated']===false && $monitorSummary['final_price_verified']===false
    && !str_contains(json_encode($monitorSummary),$monitorIds[4])
    && !str_contains(json_encode($monitorSummary),'private_marker'), 'aggregate header is redacted and descriptive');
tvpf_ok(count($monitorRows)===5 && count(array_filter($monitorRows,
    static fn(array $row): bool=>$row['monitoring']['comparison']==='matched'))===4,
    'monitoring denominator retains both matches and differences');
$differentRows=array_values(array_filter($monitorRows,
    static fn(array $row): bool=>$row['monitoring']['comparison']==='different'));
tvpf_ok(count($differentRows)===1 && $differentRows[0]['monitoring']===['tour_amount'=>'1','flight_amount'=>'33748',
    'currency'=>'RUB','comparison'=>'different'], 'different amounts survive canonical store readback');
tvpf_ok((fileperms($monitorPath)&0777)===0600 && !str_contains($monitorBytes,'private_marker'),
    'only bounded normalized comparison facts enter private store');
$diagnostic=tvpf_diagnostic($monitorDirectory,'flights',$monitorIds[4]);
tvpf_ok($diagnostic['outcome']==='retained' && $diagnostic['observation_count']===5
    && $diagnostic['rule_confirmed']===false, 'disagreement is a retained observation, not an error');
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($monitorIds[4],$tour,$db,$monitorDirectory,$now);
$repeated=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($monitorIds[4],$flights,$monitorDirectory,$now,$captureHeader);
tvpf_ok($repeated['status']==='unchanged' && $repeated['observationCount']===5
    && file_get_contents($monitorPath)===$monitorBytes, 'same evidence does not inflate monitoring denominator');
tvpf_ok(count($monitorHeaders)===6 && $monitorHeaders[4]===$monitorHeaders[5],
    'idempotent observation emits the same bounded summary');
// An unavailable exporter cannot change persistence, receipts or supplier money.
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($monitorIds[4],$tour,$db,$monitorDirectory,$now);
$emitFailed=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($monitorIds[4],$flights,$monitorDirectory,$now,
    static function(): void { throw new RuntimeException('sink unavailable'); });
tvpf_ok($emitFailed===$repeated && file_get_contents($monitorPath)===$monitorBytes
    && $flights===$beforeFlights, 'header emitter failure leaves the normal result identical');
$headerCount=count($monitorHeaders);
$skip=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights('999999',tvpf_flights(),$monitorDirectory,$now,$captureHeader);
tvpf_ok($skip['ok']===false && count($monitorHeaders)===$headerCount+1, 'one diagnostic for skipped capture');
$skipHeader=tvpf_capture_diagnostic($monitorHeaders[$headerCount], 'skipped', 'tour_pair_missing');
tvpf_ok($skipHeader['prior_tour']===['outcome'=>'unavailable'], 'absent tour receipt is unknown, not a cause');
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($monitorIds[4],$tour,$db,$monitorDirectory,$now);
$invalidFlights=$flights; unset($invalidFlights['flights'][0]['fuelCharge']);
try {
    AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($monitorIds[4],$invalidFlights,$monitorDirectory,$now,$captureHeader);
    throw new RuntimeException('missing rejected capture');
} catch (InvalidArgumentException $error) {
    tvpf_ok($error->getMessage()==='TV_FUEL_AMOUNT' && $error->getCode()===7101
        && count($monitorHeaders)===$headerCount+2, 'rejection preserved with one diagnostic');
    tvpf_capture_diagnostic($monitorHeaders[$headerCount+1], 'rejected', 'TV_FUEL_AMOUNT_FLIGHT_MISSING');
}
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($monitorIds[4],$tour,$db,$monitorDirectory,$now);
$expired=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($monitorIds[4],$flights,$monitorDirectory,$now->modify('+900 seconds'),$captureHeader);
tvpf_ok($expired['reason']==='tour_pair_expired' && count($monitorHeaders)===$headerCount+3, 'expiry is a diagnostic, not empty population');
tvpf_capture_diagnostic($monitorHeaders[$headerCount+2], 'skipped', 'tour_pair_expired');
// A clock earlier than retained observations suppresses the aggregate. Report
// only that export is unavailable, without inventing zero rows or changing capture.
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($monitorIds[4],$tour,$db,$monitorDirectory,$now);
$unavailable=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($monitorIds[4],$flights,$monitorDirectory,$now->modify('-1 second'),$captureHeader);
tvpf_ok($unavailable['ok']===true && count($monitorHeaders)===$headerCount+4, 'successful capture with suppressed aggregate stays successful');
tvpf_capture_diagnostic($monitorHeaders[$headerCount+3], 'retained', 'monitoring_summary_unavailable');
// Consume the fixture pending pair normally; this is local data, no supplier I/O.
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($monitorIds[4],$flights,$monitorDirectory,$now);
tvpf_ok(glob($monitorDirectory.'/tourvisor-fuel-pending-v1-*.json')===[]
    && (int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn()===$dbCount,
    'monitoring consumes pending pairs without DB writes');
array_map('unlink',array_filter(glob($monitorDirectory.'/*')?:[], 'is_file'));
rmdir($monitorDirectory);rmdir(dirname($monitorDirectory));

// An arbitrary local failure and even a failing diagnostic emitter cannot replace
// the original exception or expose its private message, path, response or tour ID.
$failureDirectory=$root.'/capture-diagnostic/searches'; mkdir($failureDirectory,0700,true);
$failureLock=$failureDirectory.'/tourvisor-fuel-passive-v1.lock'; mkdir($failureLock,0700);
$privateFailure=new RuntimeException('private-intake-canary '.$id1.' '.$failureDirectory);
$failureHeaders=[];
set_error_handler(static function () use ($privateFailure): never { throw $privateFailure; });
try {
    AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id1,['private_response'=>'private-intake-canary'], $failureDirectory,$now,
        static function (string $line) use (&$failureHeaders): never { $failureHeaders[]=$line; throw new RuntimeException('private-emitter-canary'); });
    throw new RuntimeException('missing expected local failure');
} catch (RuntimeException $error) {
    tvpf_ok($error===$privateFailure, 'same original exception survives failed diagnostic emitter');
} finally { restore_error_handler(); rmdir($failureLock); }
tvpf_ok(count($failureHeaders)===1, 'one header attempt for arbitrary local failure');
$failureSummary=tvpf_capture_diagnostic($failureHeaders[0], 'rejected', 'unclassified_error');
tvpf_ok(!str_contains(json_encode($failureSummary,JSON_THROW_ON_ERROR),'canary')
    && !str_contains(json_encode($failureSummary,JSON_THROW_ON_ERROR),$id1), 'unclassified failure never exports private fields');
array_map('unlink',array_filter(glob($failureDirectory.'/*')?:[], 'is_file'));
rmdir($failureDirectory);rmdir(dirname($failureDirectory));

// Prior-stage context uses the existing same-offer receipt only. It cannot
// establish why the pending record is absent or create fuel/price authority.
$priorDirectory=$root.'/prior-tour/searches'; mkdir($priorDirectory,0700,true);
$priorId='999997';
$priorPath=$priorDirectory.'/tourvisor-fuel-intake-receipt-v1-tour-'.hash('sha256',$priorId).'.json';
$priorHeaders=[];
$priorSink=static function(string $line) use (&$priorHeaders): void {$priorHeaders[]=$line;};
$missing=static function(DateTimeImmutable $at) use ($priorId,$priorDirectory,$priorSink,&$priorHeaders): array {
    $before=count($priorHeaders); $flights=tvpf_flights(); $unchanged=$flights;
    $result=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($priorId,$flights,$priorDirectory,$at,$priorSink);
    tvpf_ok($result===['source'=>'tourvisor-operator-fuel-passive-intake-v1','ok'=>false,'reason'=>'tour_pair_missing']
        && $flights===$unchanged && count($priorHeaders)===$before+1, 'prior context cannot alter capture result or response');
    return tvpf_capture_diagnostic($priorHeaders[$before],'skipped','tour_pair_missing')['prior_tour'];
};
$skippedTour=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($priorId,tvpf_tour($priorId,1006,7),$db,$priorDirectory,$now);
tvpf_ok($skippedTour['reason']==='offer_context_missing', 'real tour capture records missing context');
tvpf_ok($missing($now->modify('+7 seconds'))===['outcome'=>'skipped','code'=>'offer_context_missing','age_seconds'=>7],
    'same-offer skipped tour context survives missing pair');
$priorSaved=file_get_contents($priorPath);
$priorValue=json_decode($priorSaved,true,8,JSON_THROW_ON_ERROR);
foreach (['TV_PASSIVE_PARTY_CONFLICT','unclassified_error'] as $code) {
    $record=$priorValue; $record['outcome']='rejected'; $record['code']=$code;
    file_put_contents($priorPath,json_encode($record,JSON_THROW_ON_ERROR));
    tvpf_ok($missing($now)===['outcome'=>'rejected','code'=>$code,'age_seconds'=>0], 'allowlisted tour rejection contextualized');
}
$record=$priorValue; $record['outcome']='retained'; $record['code']='tour_retained';
file_put_contents($priorPath,json_encode($record,JSON_THROW_ON_ERROR));
tvpf_ok($missing($now)===['outcome'=>'retained','code'=>'tour_retained','age_seconds'=>0],
    'previous retention is context, not proof of cause or a new pair');
tvpf_ok($missing($now->modify('+899 seconds'))['age_seconds']===899, 'last fresh second accepted');
tvpf_ok($missing($now->modify('+900 seconds'))===['outcome'=>'unavailable'], '900-second boundary is unavailable');
tvpf_ok($missing($now->modify('-1 second'))===['outcome'=>'unavailable'], 'future receipt rejected on clock rollback');
$invalidRecords=[
    'foreign-offer'=>['offer_ref_digest'=>str_repeat('a',64)],
    'wrong-source'=>['source'=>'private-canary'],
    'wrong-stage'=>['stage'=>'flights'],
    'wrong-version'=>['schema_version'=>2],
    'private-code'=>['outcome'=>'rejected','code'=>'private-canary '.$priorId],
    'code-outcome-conflict'=>['outcome'=>'retained','code'=>'offer_context_missing'],
    'expires'=>['expires_at'=>$now->getTimestamp()],
    'freshness-rewritten'=>['expires_at'=>$now->getTimestamp()+900],
    'timestamp-string'=>['observed_at'=>(string)$now->getTimestamp()],
    'invalid-response-digest'=>['normalized_response_sha256'=>'private-canary'],
    'count-in-tour'=>['observation_count'=>1],
    'rule-in-tour'=>['rule_confirmed'=>false],
    'extra-private'=>['private_data'=>'private-canary'],
];
foreach ($invalidRecords as $label=>$change) {
    file_put_contents($priorPath,json_encode(array_replace($priorValue,$change),JSON_THROW_ON_ERROR));
    tvpf_ok($missing($now)===['outcome'=>'unavailable'],'invalid prior receipt inert: '.$label);
}
$invalidRaw=['{broken', '[]', str_repeat('x',2049), json_encode(array_diff_key($priorValue,['code'=>true]),JSON_THROW_ON_ERROR)];
foreach ($invalidRaw as $raw) {
    file_put_contents($priorPath,$raw);
    tvpf_ok($missing($now)===['outcome'=>'unavailable'],'malformed/missing/oversized prior inert');
}
unlink($priorPath); $outside=$root.'/prior-canary.json'; file_put_contents($outside,$priorSaved);
symlink($outside,$priorPath);
tvpf_ok($missing($now)===['outcome'=>'unavailable'],'symlink receipt never read');
unlink($priorPath); unlink($outside);
tvpf_ok($missing($now)===['outcome'=>'unavailable'],'missing receipt unavailable');
foreach ($priorHeaders as $line) {
    $encoded=substr($line,strlen('X-AnyTour-Fuel-Monitoring: '));
    $decoded=base64_decode(strtr($encoded,'-_','+/'),true);
    tvpf_ok(!str_contains($decoded,$priorId) && !str_contains($decoded,'private-canary')
        && !str_contains($decoded,$priorDirectory) && !str_contains($decoded,'offer_ref_digest'),
        'prior context exports no private IDs/path/digest/error');
}
tvpf_ok((int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn()===$dbCount,
    'prior context adds no DB writes');
array_map('unlink',array_filter(glob($priorDirectory.'/*')?:[], 'is_file'));
rmdir($priorDirectory); rmdir(dirname($priorDirectory));

// A supplier-reported zero is retained in the same private store, never replaced
// by the positive nondefault option and never promoted to a reusable rule.
$zeroDirectory=$root.'/zero/searches'; mkdir($zeroDirectory,0700,true);
foreach ([$id1,$id2] as $i=>$id) {
    $tour=tvpf_tour($id,1011,7); $tour['fuelCharge']=$i===0 ? 0 : 33748;
    $flights=tvpf_flights(); $alternative=$flights['flights'][0]; $alternative['isDefault']=false;
    $flights['flights'][]=$alternative; $flights['flights'][0]['fuelCharge']['value']='0.00';
    $beforeTour=$tour; $beforeFlights=$flights;
    AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id,$tour,$db,$zeroDirectory,$now);
    $captured=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id,$flights,$zeroDirectory,$now);
    tvpf_ok($captured['observationCount']===$i+1 && $captured['ruleConfirmed']===false,
        'explicit default zero captured without reusable authority');
    $diagnostic=tvpf_diagnostic($zeroDirectory,'flights',$id);
    tvpf_ok($diagnostic['outcome']==='retained' && $diagnostic['observation_count']===$i+1
        && $diagnostic['rule_confirmed']===false, 'zero uses existing successful private diagnostic');
    tvpf_ok($tour===$beforeTour && $flights===$beforeFlights, 'zero capture is response transparent');
}
$zeroPath=(glob($zeroDirectory.'/operator-fuel-rule-v2-*.json'))[0];
$zeroBytes=file_get_contents($zeroPath);
$zeroRows=json_decode($zeroBytes,true,64,JSON_THROW_ON_ERROR)['observations'];
$zeroComparisons=array_column(array_column($zeroRows,'monitoring'),'comparison'); sort($zeroComparisons);
tvpf_ok(count($zeroRows)===2 && $zeroComparisons===['different','matched']
    && array_column($zeroRows,'amount')===['0.00','0.00'], 'both valid zero comparisons retained as supplied');
AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($id2,$tour,$db,$zeroDirectory,$now);
$repeatZero=AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureFlights($id2,$flights,$zeroDirectory,$now);
tvpf_ok($repeatZero['status']==='unchanged' && file_get_contents($zeroPath)===$zeroBytes
    && (int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn()===$dbCount,
    'zero repeat does not duplicate observations or write offer DB');
array_map('unlink',array_filter(glob($zeroDirectory.'/*')?:[], 'is_file'));
rmdir($zeroDirectory);rmdir(dirname($zeroDirectory));

// A valid completed private-session tour needs no legacy offer row or invented canonical hotel identity.
$sessionDirectory = $root.'/session/searches'; mkdir($sessionDirectory,0700,true);
$sessionId = '9000000001'; $sessionTour = tvpf_tour($sessionId,1006,7); $sessionTour['childs']=2;
$sessionFlights = tvpf_flights(); $sessionFlights['flights'][0]['fuelCharge']['value']=33749;
[$sessionRequests,$sessionReplies,$sessionSource,$sessionRef] = tvpf_session($sessionTour,$sessionFlights,$now,[0,0]);
$sessionBefore = $sessionSource; $replyBefore = $sessionReplies;
$dbBefore = (int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn();
$missing = AnyTourTourvisorOperatorFuelPassiveIntakeV1::captureTour($sessionId,$sessionTour,$db,$sessionDirectory,$now);
tvpf_ok($missing['reason']==='offer_context_missing', 'legacy path remains missing without an offer-store row');
anytour_anex_search3_tv_passive($sessionRequests['tv_tour'],$sessionReplies['tv_tour'],$sessionSource,$sessionDirectory,$now->getTimestamp());
$sessionPending = glob($sessionDirectory.'/tourvisor-fuel-pending-v1-*.json');
tvpf_ok(count($sessionPending)===1, 'same-session tour captured without legacy persistence');
$pending = json_decode((string)file_get_contents($sessionPending[0]),true,64,JSON_THROW_ON_ERROR);
tvpf_ok($pending['party']===['adults'=>2,'children'=>2,'child_ages'=>[0,0]], 'duplicate infant ages retained as two people');
tvpf_ok($pending['observed_at']===$now->getTimestamp()-1 && $pending['pending_expires_at']===$now->getTimestamp()+30
    && $pending['tour_response_sha256']===AnyTourOperatorFuelRuleEvidenceV1::hash($sessionTour),
    'original checked observation, shorter expiry and source digest retained');

// Another party with the same opaque tour ID cannot consume this pending tour.
$foreignSource = $sessionSource; $foreignSource['tourvisor_context']['params']['childs']=[0];
anytour_anex_search3_tv_passive($sessionRequests['tv_flights'],$sessionReplies['tv_flights'],$foreignSource,$sessionDirectory,$now->getTimestamp());
tvpf_ok(is_file($sessionPending[0]) && !(glob($sessionDirectory.'/operator-fuel-rule-v2-*.json')?:[]),
    'foreign party neither imports nor consumes the owned pending tour');
$foreignSource = $sessionSource; ++$foreignSource['tourvisor_context']['offers'][$sessionRef]['checked']['tour']['price'];
anytour_anex_search3_tv_passive($sessionRequests['tv_flights'],$sessionReplies['tv_flights'],$foreignSource,$sessionDirectory,$now->getTimestamp());
tvpf_ok(is_file($sessionPending[0]) && !(glob($sessionDirectory.'/operator-fuel-rule-v2-*.json')?:[]),
    'another checked response with the same party cannot consume the pending tour');
anytour_anex_search3_tv_passive($sessionRequests['tv_flights'],$sessionReplies['tv_flights'],$sessionSource,$sessionDirectory,$now->getTimestamp());
$sessionRules = glob($sessionDirectory.'/operator-fuel-rule-v2-*.json');
tvpf_ok(count($sessionRules)===1 && !is_file($sessionPending[0]), 'owned flights complete the existing pending lifecycle once');
$sessionBytes = file_get_contents($sessionRules[0]);
$sessionRows = json_decode($sessionBytes,true,64,JSON_THROW_ON_ERROR)['observations'];
tvpf_ok(count($sessionRows)===1 && $sessionRows[0]['scope']['party']['child_ages']===[0,0]
    && $sessionRows[0]['monitoring']['comparison']==='different', 'differing supplier fuel is monitored with the exact infant party');
tvpf_ok($sessionSource===$sessionBefore && $sessionReplies===$replyBefore
    && (int)$db->query('SELECT COUNT(*) FROM anytour_offers')->fetchColumn()===$dbBefore,
    'new path preserves response/session and performs no offer DB writes');

foreach (['foreign_generation','foreign_search','foreign_offer','changed_reply','unknown','expired','future',
    'operation_changed','wrong_hotel','missing_child','changed_tour_digest','select'] as $case) {
    $caseDirectory=$root.'/session-'.$case.'/searches'; mkdir($caseDirectory,0700,true);
    $source=$sessionBefore; $requests=$sessionRequests; $replies=$replyBefore;
    $request=&$requests['tv_tour']; $reply=&$replies['tv_tour']; $context=&$source['tourvisor_context'];
    $record=&$source['tourvisor_requests'][$request['request_id']]; $offer=&$context['offers'][$sessionRef];
    switch ($case) {
        case 'foreign_generation': ++$context['generation']; break;
        case 'foreign_search': $request['search_ref']=str_repeat('0',32); break;
        case 'foreign_offer': $request['offer_ref']='tourvisor:'.str_repeat('0',64); break;
        case 'changed_reply': ++$reply['data']['price']; break;
        case 'unknown': $record['state']='unknown'; break;
        case 'expired': $offer['checked']['expires_at']=$now->getTimestamp(); break;
        case 'future': $offer['checked']['observed_at']=$now->getTimestamp()+1; break;
        case 'operation_changed': $offer['operations']['tv_tour']=str_repeat('0',32); break;
        case 'wrong_hotel': ++$offer['hotel']['uiId']; break;
        case 'missing_child': $context['params']['childs']=[0]; break;
        case 'changed_tour_digest': ++$offer['checked']['tour']['price']; break;
        case 'select': $request['action']='tv_select'; break;
    }
    $sourceBytes=serialize($source); $replyBytes=serialize($reply);
    anytour_anex_search3_tv_passive($request,$reply,$source,$caseDirectory,$now->getTimestamp());
    tvpf_ok(!(glob($caseDirectory.'/tourvisor-fuel-pending-v1-*.json')?:[])
        && !(glob($caseDirectory.'/operator-fuel-rule-v2-*.json')?:[]), 'unowned or incomplete session cannot capture: '.$case);
    tvpf_ok(serialize($source)===$sourceBytes && serialize($reply)===$replyBytes, 'failed capture is transparent: '.$case);
    unset($request,$reply,$context,$record,$offer);
    array_map('unlink',array_filter(glob($caseDirectory.'/*')?:[], 'is_file')); rmdir($caseDirectory); rmdir(dirname($caseDirectory));
}
// Best-effort filesystem failure likewise cannot change the valid producer response.
anytour_anex_search3_tv_passive($sessionRequests['tv_tour'],$sessionReplies['tv_tour'],$sessionSource,$root.'/absent',$now->getTimestamp());
tvpf_ok($sessionSource===$sessionBefore && $sessionReplies===$replyBefore && file_get_contents($sessionRules[0])===$sessionBytes,
    'unavailable passive directory preserves successful response and existing observations');
$apiSource=file_get_contents(__DIR__.'/../v2/api-anex-search3-preview.php');
$route=substr($apiSource,strpos($apiSource,'$passiveDirectory = null;'));
tvpf_ok(strpos($route,'$passiveDirectory = $directory;')!==false
    && strpos($route,'AnyTourTourvisorPreviewGateway::handle(')<strpos($route,'$passiveSession =')
    && strpos($route,'if (!session_write_close())')<strpos($route,'if ($passiveDirectory !== null) anytour_anex_search3_tv_passive('),
    'only original transport arms capture; successful READY and session unlock precede intake');
tvpf_ok(!str_contains($route,'::captureTour(') && !str_contains($route,'::captureFlights('),
    'raw unvalidated transport and cached/select replies do not capture');
array_map('unlink',array_filter(glob($sessionDirectory.'/*')?:[], 'is_file')); rmdir($sessionDirectory); rmdir(dirname($sessionDirectory));

// Exercise native header(), not the injected sink, without enabling URL fopen
// or contacting a supplier. All HTTP stays on one ephemeral loopback server.
$httpDirectory = $root.'/http'; mkdir($httpDirectory,0700,true);
$router = $httpDirectory.'/router.php';
file_put_contents($router,"<?php putenv('ANYTOUR_TEST_PASSIVE_FUEL_HTTP=1'); require ".var_export(__FILE__,true).';');
$listener = stream_socket_server('tcp://127.0.0.1:0',$socketError,$socketMessage);
tvpf_ok(is_resource($listener), 'loopback fixture listener');
$address = stream_socket_get_name($listener,false); fclose($listener);
tvpf_ok(is_string($address) && preg_match('/\A127\.0\.0\.1:[1-9][0-9]{1,4}\z/D',$address)===1, 'loopback address only');
$command = [PHP_BINARY,'-n','-d','allow_url_fopen=0','-d','display_errors=0','-d','log_errors=1',
    '-d','extension_dir='.ini_get('extension_dir')];
foreach (['pdo','pdo_sqlite'] as $extension) {
    if (is_file(ini_get('extension_dir').'/'.$extension.'.so')) array_push($command,'-d','extension='.$extension);
}
array_push($command,'-S',$address,$router);
$process = proc_open($command,[0=>['pipe','r'],1=>['file',$httpDirectory.'/server.log','a'],
    2=>['file',$httpDirectory.'/server.log','a']],$httpPipes);
tvpf_ok(is_resource($process), 'native HTTP fixture starts'); fclose($httpPipes[0]);
try {
    $readyBy = microtime(true)+5;
    do {
        $connection = @stream_socket_client('tcp://'.$address,$socketError,$socketMessage,0.1);
        if (is_resource($connection)) { fclose($connection); break; }
        tvpf_ok(proc_get_status($process)['running'] && microtime(true)<$readyBy, 'native HTTP fixture ready');
        usleep(20000);
    } while (true);
    foreach (['retained','skipped','prior_skipped','rejected','session'] as $case) {
        $connection = stream_socket_client('tcp://'.$address,$socketError,$socketMessage,1);
        tvpf_ok(is_resource($connection), 'native HTTP fixture connection');
        stream_set_timeout($connection,5);
        fwrite($connection,"GET /?case=".$case." HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n");
        $response = stream_get_contents($connection,262145);
        $timedOut = stream_get_meta_data($connection)['timed_out']; fclose($connection);
        tvpf_ok(!$timedOut && is_string($response) && strlen($response)<=262144, 'bounded native HTTP response');
        $parts = explode("\r\n\r\n",$response,2);
        tvpf_ok(count($parts)===2 && preg_match('/\AHTTP\/1\.[01] 200 /',$parts[0])===1,
            'passive path preserves HTTP200: '.$case.' '.substr((string)file_get_contents($httpDirectory.'/server.log'),-4096));
        $expected = tvpf_flights();
        $expected['flights'][0]['fuelCharge']['value'] = $case==='rejected' ? 'not-money' : '0.00';
        tvpf_ok($parts[1]===json_encode($expected,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
            'native HTTP body byte-identical: '.$case);
        $headers = [];
        foreach (explode("\r\n",$parts[0]) as $line) {
            if (stripos($line,'X-AnyTour-Fuel-Monitoring:')===0) $headers[]=trim(substr($line,strlen('X-AnyTour-Fuel-Monitoring:')));
        }
        if (in_array($case,['retained','session'],true)) {
            tvpf_ok(count($headers)===1 && strlen($headers[0])<=2048, 'one native monitoring header');
            $summary=json_decode(base64_decode(strtr($headers[0],'-_','+/'),true),true,16,JSON_THROW_ON_ERROR);
            tvpf_ok($summary['retained_count']===1 && $summary['fresh_count']===1
                && $summary['buckets'][0]['matched_count']===1 && $summary['buckets'][0]['zero_reference_count']===1
                && $summary['buckets'][0]['absolute_difference_minor_sum']===0, 'native explicit-zero monitoring summary');
        } else {
            tvpf_ok(count($headers)===1, 'one native capture diagnostic: '.$case);
            $isSkipped=in_array($case,['skipped','prior_skipped'],true);
            $nativeDiagnostic=tvpf_capture_diagnostic($headers[0], $isSkipped ? 'skipped' : 'rejected',
                $isSkipped ? 'tour_pair_missing' : 'TV_FUEL_AMOUNT_VALUE_FORMAT');
            if ($case==='prior_skipped') tvpf_ok($nativeDiagnostic['prior_tour']===
                ['outcome'=>'skipped','code'=>'offer_context_missing','age_seconds'=>0],
                'native header carries same-offer prior tour skip without response changes');
        }
    }
} finally {
    proc_terminate($process); proc_close($process);
    array_map('unlink',glob($httpDirectory.'/*')?:[]); rmdir($httpDirectory);
}

array_map('unlink',array_filter(glob($directory.'/*')?:[], 'is_file'));
rmdir($directory); rmdir($root);
echo "TOURVISOR_PASSIVE_FUEL_OK checks=".$GLOBALS['tvpf_checks']." observations=2 rules=0 supplier_http_added=0 db_writes=0 hotels=2 nights=7,14 include_cases=4 diagnostics=success,skip,reject,redaction,write_failure,expiry prior_tour=context,age,redaction,invalid,unavailable search_context=party,identity,unknown,expiry,conflict,no_money monitoring=matched,different,unavailable session_context=missing_row,duplicate_infant,expiry,foreign_party,12_denials,transparent native_http=retained_zero,skipped,prior_skipped,rejected,session\n";
