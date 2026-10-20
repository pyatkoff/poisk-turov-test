<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/tourvisor-operator-fuel-passive-intake.php';

function tvpf_ok(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
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

array_map('unlink',array_filter(glob($directory.'/*')?:[], 'is_file'));
rmdir($directory); rmdir($root);
echo "TOURVISOR_PASSIVE_FUEL_OK observations=2 rules=1 supplier_http_added=0 db_writes=0 hotels=2 nights=7,14 include_cases=4\n";
