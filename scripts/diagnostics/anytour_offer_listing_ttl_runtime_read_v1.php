<?php
declare(strict_types=1);

function anytour_ttl_runtime_parse_args(array $argv): array
{
    $args=[];
    foreach(array_slice($argv,1) as $arg){
        if($arg==='--self-test'){$args['self-test']='1';continue;}
        if(!str_starts_with($arg,'--')||!str_contains($arg,'='))throw new InvalidArgumentException('ANYTOUR_TTL_RUNTIME_ARG');
        [$key,$value]=explode('=',substr($arg,2),2);
        if($key===''||array_key_exists($key,$args))throw new InvalidArgumentException('ANYTOUR_TTL_RUNTIME_ARG');
        $args[$key]=$value;
    }
    return $args;
}

function anytour_ttl_runtime_required(array $args,string $key):string
{
    $value=$args[$key]??null;
    if(!is_string($value)||$value==='')throw new InvalidArgumentException('ANYTOUR_TTL_RUNTIME_ARG_'.strtoupper(str_replace('-','_',$key)));
    return $value;
}

function anytour_ttl_runtime_path_meta(string $site,string $relative,string $expectedRoot,?string $expectedRelative):array
{
    if($relative===''||str_starts_with($relative,'/')||str_contains($relative,'..'))throw new InvalidArgumentException('ANYTOUR_TTL_RUNTIME_PATH');
    $actual=$site.'/'.$relative;
    $expectedHash=null;
    if($expectedRelative!==null){
        if($expectedRelative===''||str_starts_with($expectedRelative,'/')||str_contains($expectedRelative,'..'))throw new InvalidArgumentException('ANYTOUR_TTL_RUNTIME_PATH');
        $expected=$expectedRoot.'/'.$expectedRelative;
        if(!is_file($expected)||is_link($expected))throw new RuntimeException('ANYTOUR_TTL_RUNTIME_EXPECTED');
        $size=filesize($expected);
        if(!is_int($size)||$size<1||$size>4*1024*1024)throw new RuntimeException('ANYTOUR_TTL_RUNTIME_EXPECTED');
        $expectedHash=hash_file('sha256',$expected);
        if(!is_string($expectedHash))throw new RuntimeException('ANYTOUR_TTL_RUNTIME_EXPECTED');
    }
    if(is_link($actual))return ['path'=>$relative,'status'=>'symlink','sha256'=>null,'bytes'=>null,'expected_sha256'=>$expectedHash,'matches_expected'=>false];
    if(!file_exists($actual))return ['path'=>$relative,'status'=>'absent','sha256'=>null,'bytes'=>null,'expected_sha256'=>$expectedHash,'matches_expected'=>false];
    if(!is_file($actual))return ['path'=>$relative,'status'=>'not_file','sha256'=>null,'bytes'=>null,'expected_sha256'=>$expectedHash,'matches_expected'=>false];
    $size=filesize($actual);
    if(!is_int($size)||$size<1||$size>4*1024*1024)return ['path'=>$relative,'status'=>'size_invalid','sha256'=>null,'bytes'=>$size,'expected_sha256'=>$expectedHash,'matches_expected'=>false];
    $sha=hash_file('sha256',$actual);
    if(!is_string($sha))throw new RuntimeException('ANYTOUR_TTL_RUNTIME_HASH');
    return ['path'=>$relative,'status'=>'file','sha256'=>$sha,'bytes'=>$size,'expected_sha256'=>$expectedHash,'matches_expected'=>$expectedHash!==null&&hash_equals($expectedHash,$sha)];
}

function anytour_ttl_runtime_sql_int(mixed $value,string $error):int
{
    if(is_int($value))return $value;
    if(is_string($value)&&preg_match('/^-?[0-9]+$/D',$value)===1)return (int)$value;
    throw new RuntimeException($error);
}

function anytour_ttl_runtime_sample_row(array $row):array
{
    $provider=$row['provider']??null;
    if(!is_string($provider)||!in_array($provider,['tourvisor','anex','andromeda'],true))throw new RuntimeException('ANYTOUR_TTL_RUNTIME_ROW');
    foreach(['observed_at','last_seen_at','expires_at','source_context_expires_at'] as $key){
        if(!is_string($row[$key]??null)||preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D',$row[$key])!==1)
            throw new RuntimeException('ANYTOUR_TTL_RUNTIME_ROW');
    }
    return [
        'provider'=>$provider,
        'observed_at'=>$row['observed_at'],
        'last_seen_at'=>$row['last_seen_at'],
        'expires_at'=>$row['expires_at'],
        'source_context_expires_at'=>$row['source_context_expires_at'],
        'listing_ttl_seconds'=>anytour_ttl_runtime_sql_int($row['listing_ttl_seconds']??null,'ANYTOUR_TTL_RUNTIME_ROW'),
        'source_context_ttl_seconds'=>anytour_ttl_runtime_sql_int($row['source_context_ttl_seconds']??null,'ANYTOUR_TTL_RUNTIME_ROW'),
    ];
}

function anytour_ttl_runtime_self_test():void
{
    $root=sys_get_temp_dir().'/anytour-ttl-runtime-'.bin2hex(random_bytes(6));
    $site=$root.'/site';$expected=$root.'/expected';
    mkdir($site.'/v2/data',0700,true);mkdir($expected.'/v2/data',0700,true);
    file_put_contents($expected.'/v2/data/a.php','<?php echo 1;');
    file_put_contents($site.'/v2/data/a.php','<?php echo 1;');
    file_put_contents($site.'/v2/data/b.php','<?php echo 2;');
    symlink($site.'/v2/data/a.php',$site.'/v2/data/link.php');
    $same=anytour_ttl_runtime_path_meta($site,'v2/data/a.php',$expected,'v2/data/a.php');
    $missing=anytour_ttl_runtime_path_meta($site,'v2/data/missing.php',$expected,'v2/data/a.php');
    $link=anytour_ttl_runtime_path_meta($site,'v2/data/link.php',$expected,'v2/data/a.php');
    if(($same['matches_expected']??null)!==true||($missing['status']??null)!=='absent'||($link['status']??null)!=='symlink')throw new RuntimeException('ANYTOUR_TTL_RUNTIME_SELFTEST');
    $row=anytour_ttl_runtime_sample_row(['provider'=>'tourvisor','observed_at'=>'2026-09-21T14:49:20Z','last_seen_at'=>'2026-09-21T14:49:20Z','expires_at'=>'2026-09-21T15:04:20Z','source_context_expires_at'=>'2026-09-21T15:04:20Z','listing_ttl_seconds'=>'900','source_context_ttl_seconds'=>'900']);
    if($row['listing_ttl_seconds']!==900||$row['source_context_ttl_seconds']!==900)throw new RuntimeException('ANYTOUR_TTL_RUNTIME_SELFTEST');
    unlink($site.'/v2/data/link.php');unlink($site.'/v2/data/a.php');unlink($site.'/v2/data/b.php');unlink($expected.'/v2/data/a.php');rmdir($site.'/v2/data');rmdir($site.'/v2');rmdir($site);rmdir($expected.'/v2/data');rmdir($expected.'/v2');rmdir($expected);rmdir($root);
    echo "ANYTOUR_TTL_RUNTIME_READ_SELFTEST_OK paths=3 rows=1 supplier=0 db_writes=0 site_writes=0\n";
}

$args=anytour_ttl_runtime_parse_args($argv);
if(($args['self-test']??null)==='1'){anytour_ttl_runtime_self_test();exit(0);}
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(2);}

try{
    $site=realpath(anytour_ttl_runtime_required($args,'site-root'));
    $expectedRoot=realpath(anytour_ttl_runtime_required($args,'expected-root'));
    $classifier=realpath(anytour_ttl_runtime_required($args,'classifier'));
    $operation=anytour_ttl_runtime_required($args,'operation-id');
    if($site===false||basename($site)!=='anytoour.ru'||$expectedRoot===false||!is_dir($expectedRoot)||$classifier===false||!is_file($classifier)||is_link($classifier)
        ||preg_match('/^[a-z0-9][a-z0-9._-]{20,160}$/D',$operation)!==1)throw new RuntimeException('ANYTOUR_TTL_RUNTIME_ROOT');
    require_once $classifier;
    if(!class_exists('AnyTourOfferListingTtlDriftV1'))throw new RuntimeException('ANYTOUR_TTL_RUNTIME_CLASSIFIER');

    $expectedFiles=[
        'v2/api-v2.php'=>'v2/api-v2.php',
        'app/integrations/tourvisor-anytour-offer-autosave.php'=>'app/integrations/tourvisor-anytour-offer-autosave.php',
        'app/integrations/anytour-offer-snapshot-producer.php'=>'app/integrations/anytour-offer-snapshot-producer.php',
        'v2/data/anytour-offer-snapshot-ingest-v1.php'=>'v2/data/anytour-offer-snapshot-ingest-v1.php',
        'v2/data/anytour-offer-store-v1.php'=>'v2/data/anytour-offer-store-v1.php',
    ];
    $runtime=[];
    foreach($expectedFiles as $actual=>$expectedRel)$runtime[$actual]=anytour_ttl_runtime_path_meta($site,$actual,$expectedRoot,$expectedRel);
    foreach([
        '_preview/search3-local-candidate/api-v2.php'=>'v2/api-v2.php',
        '_preview/search3-local-candidate/app/integrations/tourvisor-anytour-offer-autosave.php'=>'app/integrations/tourvisor-anytour-offer-autosave.php',
        '_preview/search3-local-candidate/app/integrations/anytour-offer-snapshot-producer.php'=>'app/integrations/anytour-offer-snapshot-producer.php',
        '_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php'=>'v2/data/anytour-offer-snapshot-ingest-v1.php',
        '_preview/search3-local-candidate/data/anytour-offer-store-v1.php'=>'v2/data/anytour-offer-store-v1.php',
    ] as $actual=>$expectedRel)$runtime[$actual]=anytour_ttl_runtime_path_meta($site,$actual,$expectedRoot,$expectedRel);

    require_once $site.'/config.php';
    $dbFile=is_file($site.'/data/db-v1.php')?$site.'/data/db-v1.php':$site.'/v2/data/db-v1.php';
    if(!is_file($dbFile)||is_link($dbFile))throw new RuntimeException('ANYTOUR_TTL_RUNTIME_DB_CONFIG');
    require_once $dbFile;
    if(!function_exists('v2_data_db'))throw new RuntimeException('ANYTOUR_TTL_RUNTIME_DB_CONFIG');
    $db=v2_data_db();
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $counts=[];
        $q=$db->query("SELECT provider,COUNT(*) AS row_count,SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) AS active_count FROM anytour_offers WHERE provider IN ('tourvisor','anex','andromeda') GROUP BY provider ORDER BY provider");
        while($row=$q->fetch(PDO::FETCH_ASSOC))$counts[]=['provider'=>(string)$row['provider'],'rows'=>anytour_ttl_runtime_sql_int($row['row_count'],'ANYTOUR_TTL_RUNTIME_DB'),'active'=>anytour_ttl_runtime_sql_int($row['active_count'],'ANYTOUR_TTL_RUNTIME_DB')];

        $distribution=[];
        $q=$db->query("SELECT provider,TIMESTAMPDIFF(SECOND,last_seen_at,expires_at) AS listing_ttl_seconds,TIMESTAMPDIFF(SECOND,last_seen_at,source_context_expires_at) AS source_context_ttl_seconds,COUNT(*) AS row_count,DATE_FORMAT(MAX(last_seen_at),'%Y-%m-%dT%H:%i:%sZ') AS latest_seen_at FROM anytour_offers WHERE provider IN ('tourvisor','anex','andromeda') AND last_seen_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY AND expires_at IS NOT NULL AND source_context_expires_at IS NOT NULL GROUP BY provider,listing_ttl_seconds,source_context_ttl_seconds ORDER BY provider,row_count DESC,listing_ttl_seconds,source_context_ttl_seconds LIMIT 100");
        while($row=$q->fetch(PDO::FETCH_ASSOC))$distribution[]=['provider'=>(string)$row['provider'],'listing_ttl_seconds'=>anytour_ttl_runtime_sql_int($row['listing_ttl_seconds'],'ANYTOUR_TTL_RUNTIME_DB'),'source_context_ttl_seconds'=>anytour_ttl_runtime_sql_int($row['source_context_ttl_seconds'],'ANYTOUR_TTL_RUNTIME_DB'),'rows'=>anytour_ttl_runtime_sql_int($row['row_count'],'ANYTOUR_TTL_RUNTIME_DB'),'latest_seen_at'=>(string)$row['latest_seen_at']];

        $samples=[];
        $sampleSql="SELECT provider,DATE_FORMAT(observed_at,'%Y-%m-%dT%H:%i:%sZ') AS observed_at,DATE_FORMAT(last_seen_at,'%Y-%m-%dT%H:%i:%sZ') AS last_seen_at,DATE_FORMAT(expires_at,'%Y-%m-%dT%H:%i:%sZ') AS expires_at,DATE_FORMAT(source_context_expires_at,'%Y-%m-%dT%H:%i:%sZ') AS source_context_expires_at,TIMESTAMPDIFF(SECOND,last_seen_at,expires_at) AS listing_ttl_seconds,TIMESTAMPDIFF(SECOND,last_seen_at,source_context_expires_at) AS source_context_ttl_seconds FROM anytour_offers WHERE provider=:provider AND observed_at IS NOT NULL AND last_seen_at IS NOT NULL AND expires_at IS NOT NULL AND source_context_expires_at IS NOT NULL ORDER BY last_seen_at DESC,id DESC LIMIT 5";
        $stmt=$db->prepare($sampleSql);
        foreach(['tourvisor','anex','andromeda'] as $provider){
            $stmt->execute(['provider'=>$provider]);
            $rows=[];while($row=$stmt->fetch(PDO::FETCH_ASSOC))$rows[]=anytour_ttl_runtime_sample_row($row);
            $samples[$provider]=$rows;
        }
        $db->rollBack();
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}

    $producer=file_get_contents($expectedRoot.'/app/integrations/anytour-offer-snapshot-producer.php');
    $ingest=file_get_contents($expectedRoot.'/v2/data/anytour-offer-snapshot-ingest-v1.php');
    $helper=file_get_contents($expectedRoot.'/app/integrations/tourvisor-anytour-offer-autosave.php');
    if(!is_string($producer)||!is_string($ingest)||!is_string($helper))throw new RuntimeException('ANYTOUR_TTL_RUNTIME_EXPECTED');
    $contract=AnyTourOfferListingTtlDriftV1::sourceContract($producer,$ingest,$helper);
    $tourvisorClassifier=['status'=>'no_tourvisor_sample'];
    if(($samples['tourvisor']??[])!==[]){
        $canonical=$runtime['v2/data/anytour-offer-snapshot-ingest-v1.php'];
        $snapshot=[
            'expected_ingest_sha256'=>$canonical['expected_sha256'],
            'installed_ingest_sha256'=>($canonical['status']==='file'?$canonical['sha256']:null),
            'observations'=>array_map(static fn(array $row):array=>[
                'provider'=>$row['provider'],'last_seen_at'=>$row['last_seen_at'],'expires_at'=>$row['expires_at'],'source_context_expires_at'=>$row['source_context_expires_at']
            ],$samples['tourvisor']),
        ];
        $tourvisorClassifier=AnyTourOfferListingTtlDriftV1::classify($contract,$snapshot);
    }

    $out=[
        'schema_version'=>1,
        'operation'=>$operation,
        'status'=>'completed_read_only',
        'expected_contract'=>$contract,
        'runtime_files'=>$runtime,
        'provider_counts'=>$counts,
        'recent_ttl_distribution'=>$distribution,
        'recent_samples'=>$samples,
        'tourvisor_classifier'=>$tourvisorClassifier,
        'db_transaction'=>'read_only_rolled_back',
        'supplier_calls'=>0,'db_writes'=>0,'site_writes'=>0,'mapping_writes'=>0,'lead_calls'=>0,'booking_calls'=>0,
        'replay_allowed'=>false,
    ];
    echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $error){
    $safe=preg_replace('/[^A-Z0-9_:-]+/i','_',substr($error->getMessage(),0,120));
    fwrite(STDERR,($safe!==''?$safe:'ANYTOUR_TTL_RUNTIME_FAILURE')."\n");exit(1);
}
