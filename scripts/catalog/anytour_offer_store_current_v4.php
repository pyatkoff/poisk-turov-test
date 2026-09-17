<?php
/** One-shot CURRENT READ ONLY inspection of AnyTour DB-first store using the merged identity bridge. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const ANYTOUR_OFFER_CURRENT_V4_OPERATION = 'anytour-offer-store-current-2690-20260917-v4';
const ANYTOUR_OFFER_CURRENT_V4_SOURCE = '6bd72255d5713f55e9df62dcc3132b2f740fce17';
const ANYTOUR_OFFER_CURRENT_V4_PROVIDERS = ['tourvisor','anex','andromeda'];
const ANYTOUR_OFFER_CURRENT_V4_MAX_LATEST_ROWS = 50000;

function anytour_offer_current_v4_int(mixed $value): int
{
    return is_numeric($value) ? (int)$value : 0;
}

function anytour_offer_current_v4_table_meta(PDO $db, array $names): array
{
    $sql='SELECT TABLE_NAME,ENGINE,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('
        .implode(',',array_fill(0,count($names),'?')).')';
    $stmt=$db->prepare($sql); $stmt->execute($names); $out=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$out[(string)$row['TABLE_NAME']]=$row;
    return $out;
}

function anytour_offer_current_v4_collect(PDO $db): array
{
    if($db->inTransaction() || $db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('Dedicated MySQL connection required');
    $tables=['anytour_catalog_control','anytour_hotels','anytour_hotel_sources','andromeda_hotel_identities',
        'anytour_offer_store_control','anytour_offer_refreshes','anytour_offer_scope_state','anytour_offers'];
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try {
        $meta=anytour_offer_current_v4_table_meta($db,$tables);
        foreach($tables as $name){
            if(($meta[$name]['ENGINE']??null)!=='InnoDB'||($meta[$name]['TABLE_TYPE']??null)!=='BASE TABLE')throw new RuntimeException('Required table unavailable: '.$name);
        }
        $catalogVersion=(int)$db->query('SELECT schema_version FROM anytour_catalog_control WHERE singleton_id=1')->fetchColumn();
        $storeVersion=(int)$db->query('SELECT schema_version FROM anytour_offer_store_control WHERE singleton_id=1')->fetchColumn();
        $activeHotels=(int)$db->query('SELECT COUNT(*) FROM anytour_hotels WHERE is_active=1')->fetchColumn();
        $legacyLinks=(int)$db->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='legacy_catalog'")->fetchColumn();
        $directAndromeda=(int)$db->query("SELECT COUNT(*) FROM anytour_hotel_sources WHERE namespace='provider_ref_digest:andromeda'")->fetchColumn();
        $acceptedAndromeda=(int)$db->query("SELECT COUNT(*) FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchColumn();

        $tableRows=[];
        foreach(['anytour_offer_refreshes','anytour_offer_scope_state','anytour_offers'] as $name)$tableRows[$name]=(int)$db->query("SELECT COUNT(*) FROM `$name`")->fetchColumn();

        $ready="o.is_active=1 AND o.final_price_ready=1 AND o.currency='RUB' AND o.display_price>0 AND o.expires_at>UTC_TIMESTAMP()";
        $latest=$ready." AND s.latest_complete_refresh_token IS NOT NULL AND s.latest_complete_refresh_token=o.last_refresh_token";
        $aggregateSql="SELECT o.provider,COUNT(*) raw_rows,SUM($ready) ready_rows,SUM($latest) latest_ready_rows,"
            ."COUNT(DISTINCT CASE WHEN $latest THEN o.scope_sha256 END) latest_ready_scopes "
            ."FROM anytour_offers o LEFT JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256 GROUP BY o.provider ORDER BY o.provider";
        $providers=[];
        foreach($db->query($aggregateSql)->fetchAll(PDO::FETCH_ASSOC) as $row){
            $providers[(string)$row['provider']]=['rawRows'=>(int)$row['raw_rows'],'readyUnexpiredRows'=>anytour_offer_current_v4_int($row['ready_rows']),
                'latestReadyRows'=>anytour_offer_current_v4_int($row['latest_ready_rows']),'latestReadyScopes'=>(int)$row['latest_ready_scopes'],
                'currentVisibleRows'=>0,'currentVisibleHotels'=>0,'currentVisibleScopes'=>0];
        }
        foreach(ANYTOUR_OFFER_CURRENT_V4_PROVIDERS as $provider)$providers[$provider]??=['rawRows'=>0,'readyUnexpiredRows'=>0,'latestReadyRows'=>0,'latestReadyScopes'=>0,'currentVisibleRows'=>0,'currentVisibleHotels'=>0,'currentVisibleScopes'=>0];

        $latestCount=0; foreach($providers as $stats)$latestCount+=(int)$stats['latestReadyRows'];
        if($latestCount>ANYTOUR_OFFER_CURRENT_V4_MAX_LATEST_ROWS)throw new RuntimeException('Latest ready cohort exceeds bounded audit');
        $identityRows=[];
        if($latestCount>0){
            $sql="SELECT o.anytour_hotel_id,o.legacy_hotel_id,o.provider,o.provider_hotel_ref_digest,o.scope_sha256 "
                ."FROM anytour_offers o JOIN anytour_offer_scope_state s ON s.provider=o.provider AND s.scope_sha256=o.scope_sha256 "
                ."WHERE $latest ORDER BY o.provider,o.scope_sha256,o.id LIMIT ".ANYTOUR_OFFER_CURRENT_V4_MAX_LATEST_ROWS;
            $identityRows=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            if(count($identityRows)!==$latestCount)throw new RuntimeException('Latest cohort count changed inside snapshot');
            $visible=AnyTourProviderIdentityBridgeV1::filterOfferRows($db,$identityRows);
            $hotels=[];$scopes=[];
            foreach($visible as $row){
                $p=(string)$row['provider']; if(!isset($providers[$p]))continue;
                ++$providers[$p]['currentVisibleRows'];
                $hotels[$p][(int)$row['anytour_hotel_id']]=true;
                $scopes[$p][(string)$row['scope_sha256']]=true;
            }
            foreach($providers as $p=>&$stats){$stats['currentVisibleHotels']=count($hotels[$p]??[]);$stats['currentVisibleScopes']=count($scopes[$p]??[]);}unset($stats);
        }
        ksort($providers);

        $refreshes=[];
        foreach($db->query('SELECT provider,status,COUNT(*) rows_count,MAX(started_at) newest_started_at,MAX(completed_at) newest_completed_at FROM anytour_offer_refreshes GROUP BY provider,status ORDER BY provider,status')->fetchAll(PDO::FETCH_ASSOC) as $row){
            $refreshes[]=['provider'=>$row['provider'],'status'=>$row['status'],'rows'=>(int)$row['rows_count'],'newestStartedAt'=>$row['newest_started_at'],'newestCompletedAt'=>$row['newest_completed_at']];
        }
        $scopeState=[];
        foreach($db->query('SELECT provider,COUNT(*) scopes,SUM(active_refresh_token IS NOT NULL) active_refreshes,SUM(latest_complete_refresh_token IS NOT NULL) completed_scopes,MAX(updated_at) newest_updated_at FROM anytour_offer_scope_state GROUP BY provider ORDER BY provider')->fetchAll(PDO::FETCH_ASSOC) as $row){
            $scopeState[(string)$row['provider']]=['scopes'=>(int)$row['scopes'],'activeRefreshes'=>anytour_offer_current_v4_int($row['active_refreshes']),
                'completedScopes'=>anytour_offer_current_v4_int($row['completed_scopes']),'newestUpdatedAt'=>$row['newest_updated_at']];
        }

        $overall=['rawRows'=>0,'readyUnexpiredRows'=>0,'latestReadyRows'=>0,'currentVisibleRows'=>0,'currentVisibleHotels'=>0,'currentVisibleScopes'=>0];
        $allVisibleHotels=[];$allVisibleScopes=[];
        foreach($providers as $p=>$stats){
            foreach(['rawRows','readyUnexpiredRows','latestReadyRows','currentVisibleRows'] as $k)$overall[$k]+=(int)$stats[$k];
            if($stats['currentVisibleRows']>0){
                foreach($identityRows as $row){
                    if((string)$row['provider']!==$p)continue;
                }
            }
        }
        if($latestCount>0){
            $visible=AnyTourProviderIdentityBridgeV1::filterOfferRows($db,$identityRows);
            foreach($visible as $row){$allVisibleHotels[(int)$row['anytour_hotel_id']]=true;$allVisibleScopes[(string)$row['scope_sha256']]=true;}
        }
        $overall['currentVisibleHotels']=count($allVisibleHotels);$overall['currentVisibleScopes']=count($allVisibleScopes);

        $db->commit();
        return ['status'=>'current_read_only','observedAt'=>gmdate('c'),'offerStoreVersion'=>$storeVersion,
            'canonical'=>['schemaVersion'=>$catalogVersion,'activeHotels'=>$activeHotels,'legacyCatalogLinks'=>$legacyLinks,
                'directAndromedaLinks'=>$directAndromeda,'acceptedAndromedaRows'=>$acceptedAndromeda],
            'tables'=>$tableRows,'overall'=>$overall,'providers'=>$providers,'refreshes'=>$refreshes,'scopeState'=>$scopeState,
            'databaseWrites'=>0,'supplierCalls'=>0,'publicFileWrites'=>0,'populationAuthorized'=>false,'migrationAuthorized'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function anytour_offer_current_v4_write_result(string $directory,array $result): string
{
    $bytes=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $out=fopen($directory.'/result.json','x+b');
    if(!$out||fwrite($out,$bytes)!==strlen($bytes)||!fflush($out)||!fsync($out))throw new RuntimeException('Durable result failed');
    fclose($out); return hash('sha256',$bytes);
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){
    $directory='';
    try{
        if($argc!==1||basename(dirname(__DIR__,2))!=='payload')throw new RuntimeException('Fixed private invocation required');
        $directory=dirname(__DIR__,3);
        if(basename($directory)!==ANYTOUR_OFFER_CURRENT_V4_OPERATION||!is_file($directory.'/reservation.json'))throw new RuntimeException('Reserved operation required');
        $reservation=json_decode((string)file_get_contents($directory.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
        if(($reservation['operation']??null)!==ANYTOUR_OFFER_CURRENT_V4_OPERATION||($reservation['dataSource']??null)!==ANYTOUR_OFFER_CURRENT_V4_SOURCE||($reservation['attempt']??null)!==1||($reservation['noReplay']??false)!==true)throw new RuntimeException('Operation provenance mismatch');
        if(is_file($directory.'/started.json')||is_file($directory.'/result.json'))throw new RuntimeException('Existing state; no replay');
        $started=fopen($directory.'/started.json','x+b'); if(!$started)throw new RuntimeException('Cannot mark start');
        $bytes=json_encode(['status'=>'started','operation'=>ANYTOUR_OFFER_CURRENT_V4_OPERATION,'noReplay'=>true],JSON_THROW_ON_ERROR)."\n";
        if(fwrite($started,$bytes)!==strlen($bytes)||!fflush($started)||!fsync($started))throw new RuntimeException('Durable start failed'); fclose($started);
        $root=rtrim((string)getenv('HOME'),'/').'/www/anytoour.ru';
        foreach(['ANYTOUR_DATA_DSN','ANYTOUR_DATA_DB_USER','ANYTOUR_DATA_DB_PASSWORD','ANYTOUR_DATA_DB_HOST','ANYTOUR_DATA_DB_NAME','ANYTOUR_DATA_DB_PORT'] as $key)if(getenv($key)!==false&&getenv($key)!=='')throw new RuntimeException('Unexpected DB override');
        require_once $root.'/config.php'; require_once __DIR__.'/../../v2/data/db-v1.php'; require_once __DIR__.'/../../v2/data/anytour-provider-identity-bridge-v1.php';
        $cfg=v2_data_db_config(); if(!str_starts_with((string)$cfg['dsn'],'mysql:')||trim((string)$cfg['user'])==='')throw new RuntimeException('Explicit project MySQL configuration required');
        $db=new PDO($cfg['dsn'],$cfg['user'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>5]);
        $result=anytour_offer_current_v4_collect($db); $sha=anytour_offer_current_v4_write_result($directory,$result);
        echo 'ANYTOUR_OFFER_CURRENT_V4_VERIFIED result_sha256='.$sha.' raw='.$result['overall']['rawRows'].' visible='.$result['overall']['currentVisibleRows']."\n";
    }catch(Throwable $e){
        if($directory!==''&&!is_file($directory.'/result.json')){
            $safe=['status'=>'stopped_read_only','errorClass'=>get_class($e),'databaseWrites'=>0,'supplierCalls'=>0,'publicFileWrites'=>0,'noReplay'=>true];
            try{anytour_offer_current_v4_write_result($directory,$safe);}catch(Throwable){}
        }
        fwrite(STDERR,'ANYTOUR_OFFER_CURRENT_V4_STOPPED class='.get_class($e)."\n"); exit(1);
    }
}
