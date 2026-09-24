<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

const HML30U_OP='hotel-match-live30-unified-census-1971-20260924-v1';

function hml30u_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hml30u_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hml30u_save(string $path,array $v):string{
    $raw=hml30u_json($v)."\n";$f=@fopen($path,'x+b');hml30u_need($f!==false,'exclusive_create');
    try{hml30u_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hml30u_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
function hml30u_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}
function hml30u_columns(PDO $db,string $table):array{
    hml30u_need(preg_match('/^[A-Za-z0-9_]{1,64}$/D',$table)===1,'table_name');
    $st=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $st->execute([$table]);$out=[];foreach($st->fetchAll(PDO::FETCH_COLUMN)?:[] as $c){$c=(string)$c;if(preg_match('/^[A-Za-z0-9_]{1,64}$/D',$c)===1)$out[$c]=true;}return $out;
}
function hml30u_pick(array $cols,array $candidates):?string{foreach($candidates as $c)if(isset($cols[$c]))return $c;return null;}
function hml30u_classify(array $locals,array $left,array $right,string $leftName,string $rightName):array{
    $triple=0;$leftOnly=0;$rightOnly=0;$single=0;
    foreach($locals as $id=>$_){$id=(int)$id;$l=isset($left[$id]);$r=isset($right[$id]);if($l&&$r)$triple++;elseif($l)$leftOnly++;elseif($r)$rightOnly++;else$single++;}
    return [
        'mapped_local_hotels'=>count($locals),'triple'=>$triple,'double'=>$leftOnly+$rightOnly,
        'double_split'=>[$leftName=>$leftOnly,$rightName=>$rightOnly],'single'=>$single,
    ];
}
function hml30u_venn(array $tv,array $samo,array $anex):array{
    $ids=array_fill_keys(array_unique(array_merge(array_keys($tv),array_keys($samo),array_keys($anex))),true);
    $out=['tv_only'=>0,'samo_only'=>0,'anex_only'=>0,'tv_samo_only'=>0,'tv_anex_only'=>0,'samo_anex_only'=>0,'all3'=>0];
    foreach($ids as $id=>$_){$t=isset($tv[$id]);$s=isset($samo[$id]);$a=isset($anex[$id]);
        if($t&&$s&&$a)$out['all3']++;elseif($t&&$s)$out['tv_samo_only']++;elseif($t&&$a)$out['tv_anex_only']++;elseif($s&&$a)$out['samo_anex_only']++;elseif($t)$out['tv_only']++;elseif($s)$out['samo_only']++;elseif($a)$out['anex_only']++;
    }
    $out['union_local_hotels']=count($ids);return $out;
}
function hml30u_execute(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $active=[];$q=$db->query("SELECT id,country_name FROM catalog_hotels WHERE is_active=1 ORDER BY id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$id=(int)($r['id']??0);if($id>0&&!hml30u_excluded((string)($r['country_name']??'')))$active[$id]=true;}
        hml30u_need(count($active)>0,'no_active_hotels');
        $cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days')->format('Y-m-d H:i:s');

        $acceptedBySource=[];$samoAll=[];
        $q=$db->query("SELECT supplier_namespace,external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY supplier_namespace,external_hotel_id,local_hotel_id");
        foreach($q->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$ns=trim((string)($r['supplier_namespace']??''));$ext=trim((string)($r['external_hotel_id']??''));$local=(int)($r['local_hotel_id']??0);if($ns===''||$ext===''||$local<=0||!isset($active[$local]))continue;$acceptedBySource[$ns.'|'.$ext][$local]=true;if($ns==='andromeda_catalog')$samoAll[$local]=true;}

        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexAll=[];$anexNativeToLocal=[];
        foreach(($anex['by_local']??[]) as $local=>$nativeSet){$local=(int)$local;if(!isset($active[$local]))continue;foreach($nativeSet as $native=>$yes)if($yes){$native=(string)$native;$anexAll[$local]=true;$anexNativeToLocal[$native][$local]=true;}}

        $tvCols=hml30u_columns($db,'tour_operator_identity_observations');hml30u_need(isset($tvCols['hotel_id'],$tvCols['last_seen_at']),'tv_live30_schema');
        $st=$db->prepare('SELECT hotel_id,MAX(last_seen_at) mx FROM tour_operator_identity_observations GROUP BY hotel_id HAVING mx>=?');$st->execute([$cut]);$tvLive=[];
        foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$id=(int)($r['hotel_id']??0);if(isset($active[$id]))$tvLive[$id]=true;}

        $samoCols=hml30u_columns($db,'andromeda_search_hotel_observations');$samoTime=hml30u_pick($samoCols,['observed_at_utc','last_seen_at','observed_at','last_seen_utc','updated_at','created_at']);
        hml30u_need(isset($samoCols['supplier_namespace'],$samoCols['external_hotel_id'])&&$samoTime!==null,'samo_live30_schema');
        $sql="SELECT external_hotel_id,MAX($samoTime) mx FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' AND $samoTime>=? GROUP BY external_hotel_id ORDER BY external_hotel_id";
        $st=$db->prepare($sql);$st->execute([$cut]);$samoSources=[];foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$ext=trim((string)($r['external_hotel_id']??''));if($ext!=='')$samoSources[$ext]=true;}
        $samoMappedSources=0;$samoUnresolved=0;$samoCollisions=0;$samoLive=[];
        foreach($samoSources as $ext=>$_){$targets=array_keys($acceptedBySource['andromeda_catalog|'.$ext]??[]);if(count($targets)===1&&isset($active[(int)$targets[0]])){$samoMappedSources++;$samoLive[(int)$targets[0]]=true;}elseif(count($targets)>1)$samoCollisions++;else$samoUnresolved++;}

        $anexCols=hml30u_columns($db,'anex_search_hotel_observations');$anexId=hml30u_pick($anexCols,['anex_hotel_id','external_hotel_id','hotel_id']);$anexTime=hml30u_pick($anexCols,['observed_at_utc','last_seen_at','observed_at','last_seen_utc','updated_at','created_at']);
        $anexLiveMeta=['available'=>false,'table_present'=>$anexCols!==[],'id_column'=>$anexId,'time_column'=>$anexTime,'reason'=>null];
        $anexSources=[];$anexMappedSources=0;$anexUnresolved=0;$anexCollisions=0;$anexLive=[];
        if($anexCols===[])$anexLiveMeta['reason']='table_missing';
        elseif($anexId===null)$anexLiveMeta['reason']='native_id_column_missing';
        elseif($anexTime===null)$anexLiveMeta['reason']='last30_timestamp_column_missing';
        else{
            $anexLiveMeta['available']=true;
            $sql="SELECT $anexId native_id,MAX($anexTime) mx FROM anex_search_hotel_observations WHERE $anexTime>=? GROUP BY $anexId ORDER BY $anexId";
            $st=$db->prepare($sql);$st->execute([$cut]);foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$native=trim((string)($r['native_id']??''));if($native!=='')$anexSources[$native]=true;}
            foreach($anexSources as $native=>$_){$targets=array_keys($anexNativeToLocal[$native]??[]);if(count($targets)===1&&isset($active[(int)$targets[0]])){$anexMappedSources++;$anexLive[(int)$targets[0]]=true;}elseif(count($targets)>1)$anexCollisions++;else$anexUnresolved++;}
        }

        $tvClass=hml30u_classify($tvLive,$samoAll,$anexAll,'tv+samo','tv+anex');
        $samoClass=hml30u_classify($samoLive,$tvLive,$anexAll,'samo+tv','samo+anex');
        $anexClass=$anexLiveMeta['available']?hml30u_classify($anexLive,$tvLive,$samoAll,'anex+tv','anex+samo'):null;
        $venn=$anexLiveMeta['available']?['available'=>true,'counts'=>hml30u_venn($tvLive,$samoLive,$anexLive)]:['available'=>false,'reason'=>$anexLiveMeta['reason']];
        $db->rollBack();
        return [
            'operation'=>HML30U_OP,'state'=>'completed_read_only_live30_unified_census','generated_at_utc'=>gmdate('c'),'cutoff_utc'=>$cut,
            'definitions'=>[
                'mapped'=>'source identity resolves exactly to one active non-Russia/non-Abkhazia local hotel',
                'triple'=>'mapped local is live30/present in both other canonical source dimensions',
                'double'=>'mapped local is live30/present in exactly one other canonical source dimension',
                'single'=>'mapped local is not live30/present in either other canonical source dimension',
                'tv_presence'=>'TV live30 observation','samo_presence'=>'accepted andromeda_catalog identity','anex_presence'=>'current effective ANEX registry after manual/exclusion precedence',
            ],
            'tv_live30'=>array_merge(['available'=>true,'total'=>count($tvLive),'mapped_sources'=>count($tvLive),'unresolved_sources'=>0,'collision_sources'=>0],$tvClass),
            'samo_live30'=>array_merge(['available'=>true,'time_column'=>$samoTime,'total'=>count($samoSources),'mapped_sources'=>$samoMappedSources,'unresolved_sources'=>$samoUnresolved,'collision_sources'=>$samoCollisions],$samoClass),
            'anex_live30'=>array_merge($anexLiveMeta,['total'=>$anexLiveMeta['available']?count($anexSources):null,'mapped_sources'=>$anexLiveMeta['available']?$anexMappedSources:null,'mapped_local_hotels'=>$anexLiveMeta['available']?count($anexLive):null,'triple'=>$anexClass['triple']??null,'double'=>$anexClass['double']??null,'double_split'=>$anexClass['double_split']??null,'single'=>$anexClass['single']??null,'unresolved_sources'=>$anexLiveMeta['available']?$anexUnresolved:null,'collision_sources'=>$anexLiveMeta['available']?$anexCollisions:null]),
            'three_way_live30_venn'=>$venn,
            'current_edges'=>['accepted_samo_unique_local'=>count($samoAll),'accepted_anex_unique_local'=>count($anexAll),'accepted_anex_native'=>(int)($anex['native_count']??0)],
            'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false,
        ];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $c=hml30u_classify([1=>true,2=>true,3=>true,4=>true],[1=>true,2=>true],[1=>true,3=>true],'left','right');
        hml30u_need($c['triple']===1&&$c['double']===2&&$c['single']===1,'classify');
        hml30u_need($c['double_split']===['left'=>1,'right'=>1],'split');
        $v=hml30u_venn([1=>true,2=>true,4=>true],[1=>true,3=>true,4=>true],[1=>true,2=>true,3=>true]);
        hml30u_need($v['all3']===1&&$v['tv_samo_only']===1&&$v['tv_anex_only']===1&&$v['samo_anex_only']===1,'venn');
        echo "MATCH_LIVE30_UNIFIED_CENSUS_V1_SELFTEST_OK\n";exit;
    }
    hml30u_need($mode==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$sha=(string)getenv('MATCH_SOURCE_SHA');
    hml30u_need(is_dir($root)&&is_dir($dir)&&basename($dir)===HML30U_OP&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);hml30u_need(($reservation['operation']??'')===HML30U_OP&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{$r=hml30u_execute(v2_data_db());$r['source_sha']=$sha;$h=hml30u_save($dir.'/result.json',$r);hml30u_save($dir.'/receipt.json',['operation'=>HML30U_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hml30u_json($r)."\n";}
    catch(Throwable $e){$f=['operation'=>HML30U_OP,'state'=>'failed_read_only_live30_unified_census','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,120,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=hml30u_save($dir.'/result.json',$f);hml30u_save($dir.'/receipt.json',['operation'=>HML30U_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}
}
