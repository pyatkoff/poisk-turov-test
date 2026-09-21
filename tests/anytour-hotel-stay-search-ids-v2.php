<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/anytour-hotel-stay-catalog-v2.php';
// PDO query/row fixture only. No database, provider or mapping mutation.
final class SearchIdsRows extends PDOStatement
{
    public function __construct(private SearchIdsPdo $owner) {}
    public function execute(?array $params=null): bool {$this->owner->params=$params??[];return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array {return $this->owner->rows;}
}
final class SearchIdsPdo extends PDO
{
    public array $rows=[],$params=[],$queries=[];
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if (!str_starts_with($query,'SELECT ')) throw new RuntimeException('Test forbids writes');
        $this->queries[]=$query;return new SearchIdsRows($this);
    }
}
$checks=0;
function check(bool $condition,string $message): void {global $checks;$checks++;if(!$condition)throw new RuntimeException($message);}
function refuses(callable $call,string $code): void {try{$call();}catch(Throwable $e){check($e->getMessage()===$code,'Unexpected error: '.$e->getMessage());return;}throw new RuntimeException('Expected '.$code);}
function mapped(int $id,string $code): array
{
    return ['id'=>(string)$id,'anytour_hotel_id'=>'4234','local_key'=>'concept-'.$id,
        'name_ru'=>'Локальное питание','facts_json'=>'{}','revision'=>'2','is_active'=>'1',
        'hotel_active'=>'1','source_hotel'=>'4234','acquired_via'=>'native-catalog',
        'source_json'=>'{}','source_sha256'=>str_repeat('a',64),
        'mapping_id'=>(string)($id+1000),'mapping_state'=>'accepted','key_kind'=>'code','external_key'=>$code];
}
$pdo=new SearchIdsPdo();$catalog=new AnyTourHotelStayCatalogV2($pdo);
$providers=['tourvisor'=>['7','8'],'anex_online'=>['73','84'],'andromeda'=>['301','302']];
foreach($providers as $namespace=>$codes){
    $scope=['namespace'=>$namespace,'hotelKey'=>'native-hotel-'.$namespace,'operatorKey'=>'native-operator-'.$namespace];
    $pdo->rows=[mapped(501,$codes[0]),mapped(502,$codes[1])];
    $result=$catalog->searchCodes($scope,4234,'meal',[501,502]);
    check($result['complete']===true && $result['codes']===$codes,'Both selected local IDs must be translated, never first-only');
    check(array_column($result['items'],'localId')===[501,502],'Retain requested canonical IDs');
    check($result['items'][0]['canonical']['hotelId']===4234,'Canonical hotel scope');
    check($result['items'][0]['canonical']['nameRu']==='Локальное питание','Local label is presentation only');
    check($pdo->params===[$namespace,$scope['hotelKey'],$scope['operatorKey'],'meal',4234,501,502],'Exact source/hotel/operator selection binds');
    check(str_contains(end($pdo->queries),"m.key_kind='code'") && str_contains(end($pdo->queries),'m.meal_concept_id=c.id'),'Use existing code mappings, not labels');
    $pdo->rows=[mapped(501,$codes[0])];$one=$catalog->searchCodes($scope,4234,'meal',[501,501]);
    check($one['codes']===[$codes[0]] && count($one['items'])===1,'Deduplicate the same selected local ID');
}
$scope=['namespace'=>'tourvisor','hotelKey'=>'hotel-a','operatorKey'=>'operator-a'];
$pdo->rows=[mapped(501,'007'),mapped(501,'7'),mapped(502,'8')];
$all=$catalog->searchCodes($scope,4234,'meal',[501,502]);
check($all['codes']===['007','7','8'],'One-to-many native mapping preserves exact code bytes');
$pdo->rows=[mapped(901,'room-tv-4')];$room=$catalog->searchCodes($scope,4234,'room',[901]);
check($room['codes']===['room-tv-4'] && $room['items'][0]['canonical']['kind']==='room','Rooms use the same bidirectional mechanism');
check(str_contains(end($pdo->queries),'m.room_concept_id=c.id') && str_contains(end($pdo->queries),'FROM anytour_hotel_room_concepts_v2'),'Correct room table and column');
$pdo->rows=[mapped(501,'7')];$partial=$catalog->searchCodes($scope,4234,'meal',[501,502]);
check($partial['complete']===false && $partial['codes']===[],'Missing second mapping cannot silently remove the meal constraint');
check($partial['items'][1]['status']==='target-unavailable','Absent/foreign local target stays explicit');
foreach(['mapping_id'=>null,'mapping_state'=>'pending','source_hotel'=>null,'hotel_active'=>'0','is_active'=>'0'] as $field=>$value){
    $row=mapped(501,'7');$row[$field]=$value;$pdo->rows=[$row];$r=$catalog->searchCodes($scope,4234,'meal',[501]);
    check($r['complete']===false && $r['codes']===[],'Unresolved/inactive mappings never become supplier IDs');
}
$pdo->rows=[mapped(501,'7')];$pdo->rows[0]['source_hotel']='4235';
check($catalog->searchCodes($scope,4234,'meal',[501])['items'][0]['status']==='source-drift','Changed source hotel blocks the request');
$pdo->rows=[mapped(501,'7')];$unprovenAlias=$scope;$unprovenAlias['namespace']='anytour_local_id';
check($catalog->searchCodes($unprovenAlias,4234,'meal',[501])['items'][0]['status']==='source-drift','Derived local alias requires existing evidence validation');
$pdo->rows=[mapped(501,'7'),mapped(502,'7')];refuses(fn()=>$catalog->searchCodes($scope,4234,'meal',[501,502]),'HOTEL_STAY_V2_CONFLICTING_MAPPING');
$pdo->rows=[mapped(501,'AI')];$pdo->rows[0]['key_kind']='label';refuses(fn()=>$catalog->searchCodes($scope,4234,'meal',[501]),'HOTEL_STAY_V2_SEARCH_CODE');
$pdo->rows=[mapped(501,'7')];$pdo->rows[0]['anytour_hotel_id']='999';refuses(fn()=>$catalog->searchCodes($scope,4234,'meal',[501]),'HOTEL_STAY_V2_SEARCH_SCOPE');
$pdo->rows=array_fill(0,10001,mapped(501,'7'));refuses(fn()=>$catalog->searchCodes($scope,4234,'meal',[501]),'HOTEL_STAY_V2_SEARCH_LIMIT');
$before=count($pdo->queries);$clear=$catalog->searchCodes($scope,4234,'meal',[]);
check($clear['complete']===true && $clear['codes']===[] && count($pdo->queries)===$before,'Explicit clear makes no lookup');
foreach([['AI'],[0],[-1],[true],['01']] as $ids)refuses(fn()=>$catalog->searchCodes($scope,4234,'meal',$ids),'HOTEL_STAY_V2_ID');
refuses(fn()=>$catalog->searchCodes($scope,4234,'meal',range(1,101)),'HOTEL_STAY_V2_SEARCH_SELECTION');
refuses(fn()=>$catalog->searchCodes($scope,4234,'price',[501]),'HOTEL_STAY_V2_SEARCH_SELECTION');
check(count($pdo->queries)===$before,'Invalid selection fails before SQL');
echo "Existing V2 reverse local IDs → exact native codes: $checks checks PASS; SQL row fixtures only, provider/database writes 0.\n";

// The existing CI job supplies a disposable MySQL database AFTER the original
// V2 schema test. Exercise this new SQL against those real tables as well.
$dsn=(string)getenv('ANYTOUR_HOTEL_STAY_V2_TEST_DSN');
if ($dsn!=='') {
    $db=new PDO($dsn,(string)getenv('ANYTOUR_HOTEL_STAY_V2_TEST_USER'),(string)getenv('ANYTOUR_HOTEL_STAY_V2_TEST_PASSWORD'),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_STRINGIFY_FETCHES=>false,
    ]);
    $db->beginTransaction();
    try {
        $json='{"fixture":"reverse-search-codes"}';
        $db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$json,hash('sha256',$json)]);
        $hotel=(int)$db->lastInsertId();$native=new AnyTourHotelStayCatalogV2($db);
        $ai=$native->createConcept('meal',$hotel,'ai','Всё включено',[]);
        $uai=$native->createConcept('meal',$hotel,'uai','Ультра всё включено',[]);
        $room=$native->createConcept('room',$hotel,'family','Семейный',[]);
        $labelOnly=$native->createConcept('meal',$hotel,'premium','Премиальное питание',[]);
        $evidence=['ref'=>'fixture://reverse-search-codes','sha256'=>hash('sha256','reverse-search-codes'),'reviewedBy'=>'fixture'];
        foreach($providers as $namespace=>$codes) {
            $scope=['namespace'=>$namespace,'hotelKey'=>'reverse-hotel-'.$namespace,'operatorKey'=>'reverse-operator-'.$namespace];
            $db->prepare('INSERT INTO anytour_hotel_sources(namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,first_seen_at,last_seen_at) VALUES(?,?,?,\'fixture\',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$namespace,$scope['hotelKey'],$hotel,$json,hash('sha256',$json)]);
            foreach([$ai,$uai] as $i=>$localId) $native->recordDecision($scope,['kind'=>'meal','keyKind'=>'code','externalKey'=>$codes[$i]],$hotel,'accepted',$localId,$evidence);
            $native->recordDecision($scope,['kind'=>'room','keyKind'=>'code','externalKey'=>'room-'.$namespace],$hotel,'accepted',$room,$evidence);
            $native->recordDecision($scope,['kind'=>'meal','keyKind'=>'label','externalKey'=>'AI PREMIUM'], $hotel,'accepted',$labelOnly,$evidence);
            $result=$native->searchCodes($scope,$hotel,'meal',[$ai,$uai]);
            check($result['complete']===true && $result['codes']===$codes,'MYSQL exact provider codes for both local meals');
            $back=$native->resolve($scope,array_map(fn($code)=>['kind'=>'meal','keyKind'=>'code','externalKey'=>$code],$result['codes']));
            check(array_column(array_column($back['items'],'canonical'),'id')===[$ai,$uai],'MYSQL local→native→local round trip');
            check($native->searchCodes($scope,$hotel,'room',[$room])['codes']===['room-'.$namespace],'MYSQL scoped room code');
            $missing=$native->searchCodes($scope,$hotel,'meal',[$ai,$labelOnly]);
            check(!$missing['complete'] && $missing['codes']===[] && $missing['items'][1]['status']==='unmapped','MYSQL label-only mapping never broadens a request');
            $other=$scope;$other['operatorKey']='other-operator';
            check(!$native->searchCodes($other,$hotel,'meal',[$ai])['complete'],'MYSQL no other-operator borrowing');
            $other=$scope;$other['namespace']='other-source';
            check($native->searchCodes($other,$hotel,'meal',[$ai])['items'][0]['status']==='hotel-unresolved','MYSQL no other-source borrowing');
        }
        $native->recordDecision($scope,['kind'=>'meal','keyKind'=>'code','externalKey'=>'007'],$hotel,'accepted',$ai,$evidence);
        $more=$native->searchCodes($scope,$hotel,'meal',[$ai]);
        check($more['codes']===['007',$codes[0]],'MYSQL multiple native codes retain byte identity');
        $missing=$native->searchCodes($scope,$hotel,'meal',[$ai,999999999]);
        check(!$missing['complete'] && $missing['codes']===[],'MYSQL nonexistent/foreign local ID blocks partial request');
        $db->prepare('UPDATE anytour_hotel_meal_concepts_v2 SET is_active=0 WHERE id=?')->execute([$uai]);
        check(!$native->searchCodes($scope,$hotel,'meal',[$ai,$uai])['complete'],'MYSQL inactive local concept blocks selection');
        $db->prepare('UPDATE anytour_hotels SET is_active=0 WHERE id=?')->execute([$hotel]);
        check(!$native->searchCodes($scope,$hotel,'meal',[$ai])['complete'],'MYSQL inactive hotel blocks selection');
        echo "HOTEL_STAY_SEARCH_CODES_MYSQL_OK checks=$checks local_native_round_trip=1 provider_scope=1 label_fallback=0\n";
    } finally { $db->rollBack(); }
}
