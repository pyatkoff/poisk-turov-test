<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(2);}

$root=getenv('ANYTOUR_PROJECT_ROOT');
$root=is_string($root)&&$root!==''?$root:rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
if(!is_dir($root)||is_link($root)||basename($root)!=='anytoour.ru')throw new RuntimeException('ANEX_DEMAND_PROJECT_ROOT');
require_once dirname(__DIR__,2).'/app/integrations/anex-local-offer-demand.php';
$config=$root.'/config.php';if(!is_file($config)||is_link($config))throw new RuntimeException('ANEX_DEMAND_CONFIG');require_once $config;
$dbFile=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbFile;
$scopeOverride=getenv('ANYTOUR_LOCAL_SCOPE_FILE');
$scopeCandidates=[];
if(is_string($scopeOverride)&&$scopeOverride!=='')$scopeCandidates[]=$scopeOverride;
$scopeCandidates[]=$root.'/_preview/search3-local-candidate/data/anytour-search-scope-v1.php';
$scopeCandidates[]=$root.'/v2/data/anytour-search-scope-v1.php';
$scopeFile=null;
foreach($scopeCandidates as $candidate){
    if(is_file($candidate)&&!is_link($candidate)){$scopeFile=realpath($candidate);break;}
}
if(!is_string($scopeFile)||$scopeFile==='')throw new RuntimeException('ANEX_DEMAND_SCOPE_SOURCE');
require_once $scopeFile;

$args=[];foreach(array_slice($argv,1) as $arg){
    if(!str_starts_with($arg,'--')||!str_contains($arg,'='))throw new InvalidArgumentException('ANEX_DEMAND_ARG');
    [$k,$v]=explode('=',substr($arg,2),2);$args[$k]=$v;
}
$int=static function(mixed $value,int $min,int $max):int{
    if(!is_string($value)||!preg_match('/\A(?:0|[1-9][0-9]*)\z/D',$value))throw new InvalidArgumentException('ANEX_DEMAND_ARG');
    $n=(int)$value;if($n<$min||$n>$max)throw new InvalidArgumentException('ANEX_DEMAND_ARG');return $n;
};
$limit=$int($args['limit']??'50',1,100);
$lookback=$int($args['lookback-hours']??'168',1,168);
$horizon=$int($args['horizon-days']??'21',1,21);
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
$since=$now->modify('-'.$lookback.' hours')->format('Y-m-d H:i:s');
$today=$now->format('Y-m-d');
$until=$now->modify('+'.$horizon.' days')->format('Y-m-d');

$db=v2_data_db();
if($db->inTransaction())throw new RuntimeException('ANEX_DEMAND_TRANSACTION');
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->exec('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try{
    $sql='SELECT departure_id,country_id,region_id,departure_date,nights,adults,children_count,child_ages_signature,'
        .'COUNT(DISTINCT search_id) searches,COUNT(*) observations,MAX(observed_at) last_seen '
        .'FROM tour_price_observations WHERE source=\'user_search\' AND observed_at>=:since '
        .'AND departure_date>=:today AND departure_date<=:until '
        .'GROUP BY departure_id,country_id,region_id,departure_date,nights,adults,children_count,child_ages_signature '
        .'ORDER BY searches DESC,last_seen DESC,observations DESC,'
        .'departure_id,country_id,region_id,departure_date,nights,adults,children_count,child_ages_signature';
    $fresh=$db->prepare(
        'SELECT COUNT(*) FROM anytour_offer_scope_state s JOIN anytour_offers o '
        .'ON o.provider=s.provider AND o.scope_sha256=s.scope_sha256 AND o.last_refresh_token=s.latest_complete_refresh_token '
        .'WHERE s.provider=\'anex\' AND s.scope_sha256=:scope AND s.latest_complete_refresh_token IS NOT NULL '
        .'AND o.is_active=1 AND o.final_price_ready=1 AND o.expires_at>:now LIMIT 1'
    );
    // Fresh popular scopes must not hide lower-ranked uncovered demand. Page only
    // metadata in this one read-only snapshot; supplier execution remains elsewhere.
    $freshCount=0;$rankedCount=0;$rowsRead=0;$pageCount=0;$seen=[];$scopes=[];
    $selectionStatus='scan_limit_reached';
    for($page=0;$page<10;++$page){
        $stmt=$db->prepare($sql.' LIMIT 100 OFFSET '.($page*100));
        $stmt->execute(['since'=>$since,'today'=>$today,'until'=>$until]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);++$pageCount;$rowsRead+=count($rows);
        if(count($rows)>100)throw new RuntimeException('ANEX_DEMAND_PAGE_SIZE');
        $ranked=AnyTourAnexLocalOfferDemandV1::normalizeRows($rows,100);
        foreach($ranked as $scope){
            $canonical=AnyTourSearchScopeV1::fromParams(AnyTourAnexLocalOfferDemandV1::searchParams($scope));
            $digest=$canonical['digest'];
            if(isset($seen[$digest]))continue;
            $seen[$digest]=true;++$rankedCount;
            $fresh->execute(['scope'=>$digest,'now'=>$now->format('Y-m-d H:i:s')]);
            if((int)$fresh->fetchColumn()>0){++$freshCount;continue;}
            $scopes[]=$scope;
            if(count($scopes)>=$limit){$selectionStatus='limit_reached';break 2;}
        }
        if(count($rows)<100){$selectionStatus='source_exhausted';break;}
    }
    $db->commit();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}

echo json_encode([
    'source'=>'tour_price_observations:user_search','generatedAt'=>$now->format('Y-m-d\TH:i:s\Z'),
    'lookbackHours'=>$lookback,'horizonDays'=>$horizon,'rankedScopeCount'=>$rankedCount,
    'freshScopesSkipped'=>$freshCount,'scopeCount'=>count($scopes),'scopes'=>$scopes,
    'selectionStatus'=>$selectionStatus,'scannedRowCount'=>$rowsRead,'scannedPageCount'=>$pageCount,
    'supplierCalls'=>0,'databaseWrites'=>0,
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
