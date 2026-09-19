<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(2);}

$root=getenv('ANYTOUR_PROJECT_ROOT');
$root=is_string($root)&&$root!==''?$root:rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
if(!is_dir($root)||is_link($root)||basename($root)!=='anytoour.ru')throw new RuntimeException('ANEX_DEMAND_PROJECT_ROOT');
require_once dirname(__DIR__,2).'/app/integrations/anex-local-offer-demand.php';
$config=$root.'/config.php';if(!is_file($config)||is_link($config))throw new RuntimeException('ANEX_DEMAND_CONFIG');require_once $config;
$dbFile=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbFile;

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
        .'ORDER BY searches DESC,last_seen DESC,observations DESC LIMIT 100';
    $stmt=$db->prepare($sql);$stmt->execute(['since'=>$since,'today'=>$today,'until'=>$until]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$db->commit();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}

$scopes=AnyTourAnexLocalOfferDemandV1::normalizeRows($rows,$limit);
echo json_encode([
    'source'=>'tour_price_observations:user_search','generatedAt'=>$now->format('Y-m-d\TH:i:s\Z'),
    'lookbackHours'=>$lookback,'horizonDays'=>$horizon,'scopeCount'=>count($scopes),'scopes'=>$scopes,
    'supplierCalls'=>0,'databaseWrites'=>0,
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
