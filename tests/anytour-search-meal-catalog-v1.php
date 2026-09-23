<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/anytour-search-meal-catalog-v1.php';
require_once __DIR__.'/../v2/data/anytour-hotel-stay-catalog-v2.php';
$checks=0;
function sm_check(bool $value,string $name): void {global $checks;$checks++;if(!$value)throw new RuntimeException('SEARCH_MEAL_TEST:'.$name);}
function sm_error(callable $call,string $code): void {try{$call();}catch(Throwable $e){sm_check($e->getMessage()===$code,$code.':'.$e->getMessage());return;}throw new RuntimeException('Expected '.$code);}
final class MealRows extends PDOStatement
{
    private array $rows=[];
    public function __construct(private MealPdo $db,private string $sql) {}
    public function execute(?array $params=null): bool
    {
        $params??=[];$this->db->queries[]=[$this->sql,$params];
        $this->rows=str_contains($this->sql,'information_schema')?[[count(array_intersect($params,$this->db->tables))]]:$this->db->rows;
        return true;
    }
    public function fetchColumn(int $column=0): mixed {return $this->rows[0][$column]??null;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array {return $this->rows;}
}
final class MealPdo extends PDO
{
    public array $queries=[],$rows=[],$tables=['anytour_meal_plans','anytour_search_meal_provider_mappings_v1','anytour_search_meal_memberships_v1','anytour_hotel_meal_concepts_v2','anytour_stay_mappings'];
    public function __construct(){}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {if(!str_starts_with($query,'SELECT '))throw new RuntimeException('Writes forbidden');return new MealRows($this,$query);}
}
$fake=new MealPdo();$reader=new AnyTourSearchMealCatalogV1($fake);
$e=['evidence_ref'=>'fixture://meal','evidence_sha256'=>hash('sha256','meal'),'reviewed_by'=>'fixture'];
$fake->rows=[['id'=>501,'code'=>'ai','name_ru'=>'Same label','external_id'=>'7']+$e,['id'=>502,'code'=>'uai','name_ru'=>'Same label','external_id'=>'9']+$e];
sm_check($reader->nativeIds('tourvisor','global',[501,502])===['7','9'],'two IDs union');
sm_check($reader->nativeIds('tourvisor','global',[502])===['9'],'one selected local ID');
sm_check($reader->nativeIds('tourvisor','global',[502,502])===['9'],'dedup');
sm_check($reader->nativeIds('tourvisor','global',[])===[],'explicit clear');
sm_check($reader->catalogue('tourvisor','global')['plans'][0]['nameRu']==='Same label','own display label');
sm_error(fn()=>$reader->nativeIds('tourvisor','global',[501,700]),'SEARCH_MEAL_UNMAPPED');
sm_error(fn()=>$reader->nativeIds('tourvisor','global',['AI']),'SEARCH_MEAL_ID');
sm_error(fn()=>$reader->catalogue('bad','global'),'SEARCH_MEAL_SCOPE');
sm_error(fn()=>$reader->catalogue('tourvisor','../secret'),'SEARCH_MEAL_SCOPE');
$fake->rows[1]['external_id']='7';sm_error(fn()=>$reader->catalogue('tourvisor','global'),'SEARCH_MEAL_CONFLICT');
$fake->rows[1]['external_id']=null;sm_error(fn()=>$reader->nativeIds('tourvisor','global',[501,502]),'SEARCH_MEAL_UNMAPPED');
$fake->rows[0]['evidence_sha256']='invalid';sm_error(fn()=>$reader->nativeIds('tourvisor','global',[501]),'SEARCH_MEAL_UNMAPPED');
// CURRENT fallback: reviewed legacy_catalog rows explicitly carry tv-meal evidence.
// Equal labels remain irrelevant; UAI is Tourvisor 9, not local plan ID 8.
$fake->tables=['anytour_meal_plans','anytour_stay_mappings'];
$fake->rows=[['id'=>7,'code'=>'all-inclusive','name_ru'=>'Всё включено','external_id'=>'7','evidence_ref'=>'stay-v4:x;tv-meal:7->all-inclusive','evidence_sha256'=>hash('sha256','ai'),'reviewed_by'=>'fixture'],
 ['id'=>8,'code'=>'ultra-all-inclusive','name_ru'=>'Ультра всё включено','external_id'=>'9','evidence_ref'=>'stay-v4:x;tv-meal:9->ultra-all-inclusive','evidence_sha256'=>hash('sha256','uai'),'reviewed_by'=>'fixture']];
sm_check($reader->nativeIds('tourvisor','global',[7,8])===['7','9'],'CURRENT reviewed legacy Tourvisor fallback');
sm_check($reader->catalogue('tourvisor','global')['available']===true,'CURRENT fallback is an installed read authority');
sm_check($reader->catalogue('anex','global')['available']===false,'CURRENT Tourvisor evidence is never borrowed by ANEX');
$fake->tables=['anytour_meal_plans'];sm_check($reader->catalogue('tourvisor','global')['available']===false,'no mapping authority stays unavailable');
echo "SEARCH_MEAL_PURE_OK checks=$checks no_writes=1\n";

$dsn=(string)getenv('ANYTOUR_HOTEL_STAY_V2_TEST_DSN');if($dsn==='')exit(0);
$db=new PDO($dsn,(string)getenv('ANYTOUR_HOTEL_STAY_V2_TEST_USER'),(string)getenv('ANYTOUR_HOTEL_STAY_V2_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_STRINGIFY_FETCHES=>false]);
function sm_sql(PDO $db,string $name): void
{
    $sql=preg_replace('/^\s*--.*$/m','',file_get_contents(__DIR__.'/../v2/data/migrations/'.$name));
    foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql) as $stmt)if(trim($stmt)!=='')$db->exec($stmt);
}
// Only the existing disposable CI database; never an application DSN or runtime migration.
sm_check(str_contains($dsn,'anytour_hotel_stay_v2_fixture'),'fixture DSN required');
sm_sql($db,'20260916-anytour-stay-catalog.sql');
$live=new AnyTourSearchMealCatalogV1($db);
sm_check($live->catalogue('tourvisor','global')['available']===true,'existing stay mapping table is a read-only CURRENT Tourvisor authority');
sm_error(fn()=>$live->nativeIds('tourvisor','global',[7]),'SEARCH_MEAL_UNMAPPED');
sm_sql($db,'20260921-anytour-search-meal-mappings.sql');
$db->beginTransaction();
try{
    $db->exec("INSERT INTO anytour_meal_plans(id,code,name_ru,family_code,qualifiers_json) VALUES(501,'test-ai','Same label','test','{}'),(502,'test-uai','Same label','test','{}')");
    $insert=$db->prepare('INSERT INTO anytour_search_meal_provider_mappings_v1(provider,scope_key,external_id,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) VALUES(?,?,?,?,\'accepted\',?,?,\'fixture\',UTC_TIMESTAMP())');
    foreach(['tourvisor'=>['7','9'],'anex'=>['73','99'],'andromeda'=>['301','302']] as $provider=>$codes){
        $scope=$provider==='tourvisor'?'global':'operator:123';
        foreach([501,502] as $i=>$id)$insert->execute([$provider,$scope,$codes[$i],$id,'fixture://native',hash('sha256',$provider.$i)]);
        sm_check($live->nativeIds($provider,$scope,[501,502])===$codes,'MYSQL provider qualified two IDs');
        $p=array_column($live->catalogue($provider,$scope)['plans'],null,'id');
        sm_check($p[501]['nativeIds']===[$codes[0]]&&$p[502]['nativeIds']===[$codes[1]],'MYSQL incoming native→local same catalogue');
        sm_check($p[501]['nameRu']===$p[502]['nameRu'],'MYSQL equal names remain distinct');
        sm_error(fn()=>$live->nativeIds($provider,'other-scope',[501]),'SEARCH_MEAL_UNMAPPED');
    }
    $insert->execute(['tourvisor','global','8',501,'fixture://second-code',hash('sha256','second')]);
    sm_check($live->nativeIds('tourvisor','global',[501,502])===['7','8','9'],'MYSQL one local category to several native values');
    $before=$live->catalogue('tourvisor','global')['revision'];
    $db->exec("UPDATE anytour_search_meal_provider_mappings_v1 SET state='conflict',meal_plan_id=NULL WHERE provider='tourvisor' AND external_id='9'");
    sm_error(fn()=>$live->nativeIds('tourvisor','global',[501,502]),'SEARCH_MEAL_UNMAPPED');
    sm_check($before!==$live->catalogue('tourvisor','global')['revision'],'MYSQL current mapping revision changes');
    $j='{"fixture":true}';$db->prepare('INSERT INTO anytour_hotels(profile_json,profile_sha256,revision,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$j,hash('sha256',$j)]);
    $hotelId=(int)$db->lastInsertId();$stay=new AnyTourHotelStayCatalogV2($db);$conceptId=$stay->createConcept('meal',$hotelId,'test-hotel-ai','Hotel concept, not search label',[]);
    $concept=$stay->meals($hotelId)[0];
    $db->prepare("INSERT INTO anytour_search_meal_memberships_v1(anytour_hotel_id,meal_concept_id,meal_concept_revision,meal_plan_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) VALUES(?,?,?,501,'accepted','fixture://member',?,'fixture',UTC_TIMESTAMP())")->execute([$hotelId,$conceptId,$concept['revision'],hash('sha256','member')]);
    $offer=['stayMatch'=>['source'=>'anytour-hotel-stay-v2','exactScope'=>true,'meal'=>['status'=>'accepted','canonical'=>$concept]]];
    $group=['anytourHotelId'=>$hotelId,'offers'=>[$offer]];
    $mapped=$live->attachSearchPlans([$group]);sm_check($mapped[0]['offers'][0]['searchMeal']['plan']['id']===501,'MYSQL reviewed hotel membership → global plan');
    sm_check($mapped[0]['offers'][0]['stayMatch']===$offer['stayMatch'],'MYSQL exact hotel concept untouched');
    foreach(['pending','conflict'] as $state){$bad=$offer;$bad['stayMatch']['meal']['status']=$state;$group['offers']=[$offer,$bad];$rows=$live->attachSearchPlans([$group]);sm_check(!isset($rows[0]['offers'][1]['searchMeal']),'MYSQL bad sibling cannot borrow valid membership');}
    $db->prepare('UPDATE anytour_hotel_meal_concepts_v2 SET revision=revision+1 WHERE id=?')->execute([$conceptId]);
    sm_check(!isset($live->attachSearchPlans([$group])[0]['offers'][0]['searchMeal']),'MYSQL changed concept invalidates old membership');
    $db->exec('UPDATE anytour_meal_plans SET is_active=0 WHERE id=501');sm_error(fn()=>$live->nativeIds('tourvisor','global',[501]),'SEARCH_MEAL_UNMAPPED');
    echo "SEARCH_MEAL_MYSQL_OK checks=$checks provider_IDs=1 incoming_memberships=1 labels_as_identity=0\n";
}finally{$db->rollBack();}
