<?php
declare(strict_types=1);
require_once __DIR__.'/../app/integrations/anex-local-offer-demand.php';
function ck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function reject(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException){return;}throw new RuntimeException($label);}
$base=[
 'departure_id'=>'1','country_id'=>'4','region_id'=>'20','departure_date'=>'2026-09-22','nights'=>'7','adults'=>'2',
 'children_count'=>'0','child_ages_signature'=>'','searches'=>'107','observations'=>'562','last_seen'=>'2026-09-19 20:16:12',
];
$child=array_replace($base,['region_id'=>null,'departure_date'=>'2026-09-23','children_count'=>'2','child_ages_signature'=>'7,3','searches'=>'4']);
$out=AnyTourAnexLocalOfferDemandV1::normalizeRows([$base,$base,$child],10);
ck(count($out)===2,'dedupe');
ck($out[0]['departureId']===1&&$out[0]['countryId']===4&&$out[0]['regionId']===20&&$out[0]['childAges']===[],'base');
ck($out[1]['regionId']===null&&$out[1]['childAges']===[3,7],'child-ages-sorted');
ck(AnyTourAnexLocalOfferDemandV1::normalizeRows([$base,$child],1)===[array_replace($out[0])],'limit');
$params=AnyTourAnexLocalOfferDemandV1::searchParams($out[0]);
ck($params['departureId']==='1'&&$params['countryId']==='4'&&$params['regionIds']===['20']
    &&$params['dateFrom']==='2026-09-22'&&$params['dateTo']==='2026-09-22'
    &&$params['nightsFrom']===7&&$params['nightsTo']===7&&$params['childs']===[],'search-params');
$filtered=AnyTourAnexLocalOfferDemandV1::withoutFreshScopes($out,static fn(array $scope):bool=>$scope['regionId']===20,10);
ck(count($filtered)===1&&$filtered[0]['regionId']===null,'fresh-filter');
$limitedAfterFresh=AnyTourAnexLocalOfferDemandV1::withoutFreshScopes($out,static fn(array $scope):bool=>$scope['regionId']===20,1);
ck(count($limitedAfterFresh)===1&&$limitedAfterFresh[0]['regionId']===null,'limit-after-fresh');
reject(fn()=>AnyTourAnexLocalOfferDemandV1::normalizeRows([array_replace($base,['children_count'=>'1','child_ages_signature'=>''])],10),'age-count');
reject(fn()=>AnyTourAnexLocalOfferDemandV1::normalizeRows([array_replace($base,['departure_date'=>'2026-02-30'])],10),'date');
reject(fn()=>AnyTourAnexLocalOfferDemandV1::normalizeRows([$base],0),'bad-limit');
echo "ANEX_LOCAL_OFFER_DEMAND_OK exact=1 dedupe=1 children=1 fresh_skip=1 invalid=1\n";

/** Run the actual CLI against an in-memory SQL double; no database/network connection. */
function queueFixture(array $rows,array $freshIds,int $limit=3,string $failure=''): array
{
    $dir=sys_get_temp_dir().'/anex-demand-queue-'.bin2hex(random_bytes(8));
    $root=$dir.'/anytoour.ru';
    mkdir($root.'/data',0700,true);
    mkdir($dir.'/scripts/ops',0700,true);
    mkdir($dir.'/app/integrations',0700,true);
    copy(__DIR__.'/../scripts/ops/anex_local_offer_demand_queue.php',$dir.'/scripts/ops/anex_local_offer_demand_queue.php');
    copy(__DIR__.'/../app/integrations/anex-local-offer-demand.php',$dir.'/app/integrations/anex-local-offer-demand.php');
    file_put_contents($root.'/config.php',"<?php\n");
    file_put_contents($root.'/data/fixture.json',json_encode(['rows'=>$rows,'fresh'=>$freshIds,'failure'=>$failure],JSON_THROW_ON_ERROR));
    $double= <<<'CODE'
<?php
final class DemandDbDouble
{
    public bool $active=false;
    public array $data;
    public array $trace=['sql'=>[],'offsets'=>[],'fresh'=>[],'commits'=>0,'rollbacks'=>0];
    public function __construct(){
        $this->data=json_decode(file_get_contents(__DIR__.'/fixture.json'),true,64,JSON_THROW_ON_ERROR);
        register_shutdown_function(function(){file_put_contents(__DIR__.'/trace.json',json_encode($this->trace,JSON_THROW_ON_ERROR));});
    }
    public function inTransaction(): bool{return $this->active;}
    public function exec(string $sql): void{
        if(!in_array($sql,['SET TRANSACTION ISOLATION LEVEL REPEATABLE READ','SET TRANSACTION READ ONLY'],true))throw new RuntimeException('fixture_unexpected_write');
        $this->trace['sql'][]=$sql;
    }
    public function beginTransaction(): void{$this->active=true;}
    public function commit(): void{$this->active=false;++$this->trace['commits'];}
    public function rollBack(): void{$this->active=false;++$this->trace['rollbacks'];}
    public function prepare(string $sql): DemandStatementDouble{
        if(!$this->active||!str_starts_with($sql,'SELECT '))throw new RuntimeException('fixture_not_readonly');
        $this->trace['sql'][]=$sql;return new DemandStatementDouble($this,$sql);
    }
}
final class DemandStatementDouble
{
    private array $params=[];
    public function __construct(private DemandDbDouble $db,private string $sql){}
    public function execute(array $params): void{
        if(!$this->db->active)throw new RuntimeException('fixture_outside_snapshot');
        $this->params=$params;
        if(str_contains($this->sql,'FROM anytour_offer_scope_state')){
            if($this->db->data['failure']==='fresh')throw new RuntimeException('fixture_freshness_failure');
            if(!isset($GLOBALS['fixtureScopes'][$params['scope']]))throw new RuntimeException('fixture_scope_digest');
            $this->db->trace['fresh'][]=$GLOBALS['fixtureScopes'][$params['scope']];
        }
    }
    public function fetchAll(int $mode): array{
        if(!str_contains($this->sql,"source='user_search'")||$mode!==PDO::FETCH_ASSOC)throw new RuntimeException('fixture_demand_source');
        if(!preg_match('/LIMIT (\d+)(?: OFFSET (\d+))?/',$this->sql,$m))throw new RuntimeException('fixture_unbounded_query');
        $offset=(int)($m[2]??0);$this->db->trace['offsets'][]=$offset;
        if($offset>0&&$this->db->data['failure']==='page')throw new RuntimeException('fixture_next_page_failure');
        return array_slice($this->db->data['rows'],$offset,(int)$m[1]);
    }
    public function fetchColumn(): int{
        $params=$GLOBALS['fixtureScopes'][$this->params['scope']];
        return in_array((int)$params['departureId'],$this->db->data['fresh'],true)?1:0;
    }
}
function v2_data_db(): DemandDbDouble{return new DemandDbDouble();}
CODE;
    file_put_contents($root.'/data/db-v1.php',$double);
    file_put_contents($root.'/data/scope.php', <<<'CODE'
<?php
final class AnyTourSearchScopeV1
{
    public static function fromParams(array $params): array{
        $digest=hash('sha256',json_encode($params,JSON_THROW_ON_ERROR));
        $GLOBALS['fixtureScopes'][$digest]=$params;
        return ['digest'=>$digest];
    }
}
CODE);
    try{
        $env=getenv();$env['ANYTOUR_PROJECT_ROOT']=$root;$env['ANYTOUR_LOCAL_SCOPE_FILE']=$root.'/data/scope.php';
        $process=proc_open(['timeout','10s',PHP_BINARY,'-d','display_errors=stderr',$dir.'/scripts/ops/anex_local_offer_demand_queue.php','--limit='.$limit],
            [0=>['file','/dev/null','r'],1=>['file',$dir.'/stdout','w'],2=>['file',$dir.'/stderr','w']],$pipes,null,$env,['bypass_shell'=>true]);
        ck(is_resource($process),'queue fixture process');$code=proc_close($process);
        return ['code'=>$code,'stdout'=>file_get_contents($dir.'/stdout'),'stderr'=>file_get_contents($dir.'/stderr'),
            'trace'=>json_decode(file_get_contents($root.'/data/trace.json'),true,64,JSON_THROW_ON_ERROR)];
    }finally{
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($dir);
    }
}
function queueRows(int $count): array
{
    global $base;$rows=[];
    for($i=1;$i<=$count;++$i)$rows[]=array_replace($base,['departure_id'=>(string)$i,'searches'=>(string)(10000-$i)]);
    return $rows;
}
$run=queueFixture(queueRows(101),range(1,100),1);
ck($run['code']===0,'paged demand exits');
$r=json_decode($run['stdout'],true,64,JSON_THROW_ON_ERROR);
echo 'FIRST_PAGE_FRONTIER '.json_encode(['selected'=>$r['scopeCount'],'readOffsets'=>$run['trace']['offsets']],JSON_THROW_ON_ERROR)."\n";
ck($r['scopeCount']===1&&$r['scopes'][0]['departureId']===101,'stale demand beyond first 100 must be selected');
ck($run['trace']['offsets']===[0,100]&&$r['freshScopesSkipped']===100,'read next ranked page after fresh top100');
ck($r['selectionStatus']==='limit_reached'&&$r['rankedScopeCount']===101,'requested limit after freshness');
ck($r['supplierCalls']===0&&$r['databaseWrites']===0,'queue has no execution authority');
$secondPage=queueFixture(queueRows(104),range(1,100),3);
$s=json_decode($secondPage['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($secondPage['code']===0&&array_column($s['scopes'],'departureId')===[101,102,103],'three lower-ranked stale scopes');
ck($s['scannedRowCount']===104&&$s['rankedScopeCount']===103,'returned rows distinct from inspected scopes');
ck(count($secondPage['trace']['fresh'])===103,'stop freshness reads as soon as limit met');

$early=queueFixture(queueRows(201),[],3);
$e=json_decode($early['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($early['code']===0&&$early['trace']['offsets']===[0]&&count($early['trace']['fresh'])===3,'no unneeded second page');
ck(array_column($e['scopes'],'departureId')===[1,2,3]&&$e['selectionStatus']==='limit_reached','ranking preserved');
$maximum=queueFixture(queueRows(201),[],100);
$m=json_decode($maximum['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($maximum['code']===0&&$maximum['trace']['offsets']===[0]&&$m['scopeCount']===100,'original maximum queue limit retained');

foreach([0,99,100,101] as $count){
    $freshRun=queueFixture(queueRows($count),$count===0?[]:range(1,$count),3);
    $f=json_decode($freshRun['stdout'],true,64,JSON_THROW_ON_ERROR);
    ck($freshRun['code']===0&&$f['scopeCount']===0&&$f['selectionStatus']==='source_exhausted','authoritative exhausted count '.$count);
    ck($freshRun['trace']['offsets']===($count<100?[0]:[0,100]),'page termination count '.$count);
    ck($f['freshScopesSkipped']===$count&&$f['rankedScopeCount']===$count,'fresh counts '.$count);
    ck($freshRun['trace']['commits']===1&&$freshRun['trace']['rollbacks']===0,'single readonly snapshot '.$count);
}
$bounded=queueFixture(queueRows(1001),range(1,1000),1);
$b=json_decode($bounded['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($bounded['code']===0&&$b['scopeCount']===0&&$b['selectionStatus']==='scan_limit_reached','bounded scan not falsely global empty');
ck($bounded['trace']['offsets']===range(0,900,100)&&$b['scannedRowCount']===1000&&$b['scannedPageCount']===10,'bounded metadata work');
ck(count($bounded['trace']['fresh'])===1000,'no reads beyond metadata budget');

$dupeRows=queueRows(102);
$dupeRows[99]=array_replace($dupeRows[99],['children_count'=>'2','child_ages_signature'=>'7,3','region_id'=>null]);
$dupeRows[100]=array_replace($dupeRows[99],['child_ages_signature'=>'3,7','searches'=>'9899']);
$dupes=queueFixture($dupeRows,range(1,99),2);
$d=json_decode($dupes['stdout'],true,64,JSON_THROW_ON_ERROR);
ck($dupes['code']===0&&array_column($d['scopes'],'departureId')===[100,102],'canonical duplicate across pages cannot consume queue limit');
ck($d['scopes'][0]['childAges']===[3,7]&&$d['scopes'][0]['regionId']===null,'exact family scope preserved');
ck(count($dupes['trace']['fresh'])===101&&$d['rankedScopeCount']===101,'duplicate not rechecked or recounted');
foreach($dupes['trace']['sql'] as $sql){
    if(str_contains($sql,'FROM tour_price_observations')){
        ck(str_contains($sql,'ORDER BY searches DESC,last_seen DESC,observations DESC,departure_id,country_id,region_id,departure_date,nights,adults,children_count,child_ages_signature'),'stable rank tie-breakers');
    }
}

foreach(['page','fresh','invalid'] as $failure){
    $badRows=queueRows(101);
    if($failure==='invalid')$badRows[100]['child_ages_signature']='7';
    $failed=queueFixture($badRows,range(1,100),1,$failure);
    ck($failed['code']!==0&&$failed['code']!==124&&$failed['stdout']==='','failure emits no executable partial queue: '.$failure);
    ck($failed['trace']['commits']===0&&$failed['trace']['rollbacks']===1,'failure rolls back read snapshot: '.$failure);
}
echo "ANEX_DEMAND_FRONTIER_PAGES_OK cli_cases=13 later_page=1 early_stop=1 canonical_dedupe=1 bounded=1 rollback=1\n";
