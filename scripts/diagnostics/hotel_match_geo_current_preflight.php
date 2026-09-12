<?php
declare(strict_types=1);
/** MATCH #1971 — CURRENT DB preflight for the immutable 244-row geo evidence manifest. READ ONLY. */
const HMGP_OPERATION = 'hotel-match-geo-current-preflight-1971-20260912-v1';

function hmgp_columns(PDO $pdo, string $table): array {
    $q=$pdo->query('SHOW COLUMNS FROM `'.$table.'`');
    return $q ? $q->fetchAll(PDO::FETCH_COLUMN) : [];
}
function hmgp_table(PDO $pdo,string $table): bool {
    $q=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]); return $q->fetchColumn()!==false;
}
function hmgp_int($v): ?int { return is_numeric($v) && (int)$v>0 ? (int)$v : null; }
function hmgp_manifest(string $path): array {
    $raw=file_get_contents($path); if($raw===false) throw new RuntimeException('manifest_read');
    $m=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(($m['schema']??'')!=='hotel-match-geo-current-preflight-manifest/1'||($m['not_write_authority']??null)!==true) throw new RuntimeException('manifest_schema');
    foreach(['anex'=>209,'andromeda'=>35] as $provider=>$expected){
        if(!is_array($m[$provider]??null)||count($m[$provider])!==$expected) throw new RuntimeException('manifest_count_'.$provider);
        $seen=[]; foreach($m[$provider] as $pair){
            if(!is_array($pair)||count($pair)!==2||hmgp_int($pair[0])===null||hmgp_int($pair[1])===null) throw new RuntimeException('manifest_pair_'.$provider);
            $k=(string)$pair[0]; if(isset($seen[$k])) throw new RuntimeException('manifest_duplicate_'.$provider); $seen[$k]=true;
        }
    }
    return $m;
}
function hmgp_active_targets(PDO $pdo,array $manifest): array {
    $ids=[]; foreach(['anex','andromeda'] as $p) foreach($manifest[$p] as $x) $ids[(int)$x[1]]=true;
    $vals=array_keys($ids); $out=[]; foreach(array_chunk($vals,500) as $chunk){
        $q=$pdo->prepare('SELECT id,is_active FROM catalog_hotels WHERE id IN ('.implode(',',array_fill(0,count($chunk),'?')).')');
        $q->execute($chunk); foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$out[(int)$r['id']]=(int)($r['is_active']??0)===1;
    } return $out;
}
function hmgp_anex_decisions(PDO $pdo): array {
    if(!hmgp_table($pdo,'anex_hotel_decisions')) return [];
    $cols=hmgp_columns($pdo,'anex_hotel_decisions'); $wanted=array_values(array_intersect(['anex_hotel_id','catalog_hotel_id','decision_status','status','decision'],$cols));
    if(!in_array('anex_hotel_id',$wanted,true))return [];
    $rows=$pdo->query('SELECT `'.implode('`,`',$wanted).'` FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_ASSOC); $out=[];
    foreach($rows as $r){$id=hmgp_int($r['anex_hotel_id']??null);if($id!==null)$out[$id]=$r;} return $out;
}
function hmgp_anex_exclusions(PDO $pdo): array {
    if(!hmgp_table($pdo,'anex_review_pair_exclusions')) return [];
    $cols=hmgp_columns($pdo,'anex_review_pair_exclusions'); $local=in_array('catalog_hotel_id',$cols,true)?'catalog_hotel_id':(in_array('local_hotel_id',$cols,true)?'local_hotel_id':null);
    if(!in_array('anex_hotel_id',$cols,true)||$local===null)return [];
    $rows=$pdo->query('SELECT anex_hotel_id,`'.$local.'` local_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC);$out=[];
    foreach($rows as $r){$a=hmgp_int($r['anex_hotel_id']??null);$l=hmgp_int($r['local_hotel_id']??null);if($a&&$l)$out[$a][$l]=true;}return $out;
}
function hmgp_andromeda(PDO $pdo): array {
    if(!hmgp_table($pdo,'andromeda_hotel_identities'))return [];
    $cols=hmgp_columns($pdo,'andromeda_hotel_identities');$wanted=array_values(array_intersect(['supplier_namespace','external_hotel_id','local_hotel_id','decision_status'],$cols));
    if(!in_array('external_hotel_id',$wanted,true))return [];
    $rows=$pdo->query('SELECT `'.implode('`,`',$wanted).'` FROM andromeda_hotel_identities')->fetchAll(PDO::FETCH_ASSOC);$out=[];
    foreach($rows as $r){$id=(string)($r['external_hotel_id']??'');if($id!=='')$out[$id][]=$r;}return $out;
}
function hmgp_bucket(array &$result,string $provider,string $bucket,array $row): void {
    $result['counts'][$provider][$bucket]=($result['counts'][$provider][$bucket]??0)+1;
    if(in_array($bucket,['unresolved_current','target_drift','manual_protected','excluded','target_missing_or_inactive','namespace_ambiguous','conflict'],true))$result['rows'][$provider][$bucket][]=$row;
}

if(in_array('--self-test',$_SERVER['argv']??[],true)){
    $tmp=tempnam(sys_get_temp_dir(),'hmgp');file_put_contents($tmp,json_encode(['schema'=>'hotel-match-geo-current-preflight-manifest/1','not_write_authority'=>true,'anex'=>array_map(fn($i)=>[$i,$i+1000],range(1,209)),'andromeda'=>array_map(fn($i)=>[$i+10000,$i+20000],range(1,35))]));
    $m=hmgp_manifest($tmp);unlink($tmp);if(count($m['anex'])!==209||count($m['andromeda'])!==35)exit(2);echo "MATCH geo CURRENT preflight self-test PASS; network=0 database=0\n";exit(0);
}

error_reporting(0);ob_start();$pdo=null;
try{
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    $manifestPath=getenv('HMGP_MANIFEST_PATH')?:($root.'/reports/hotel-match-geo-current-preflight-manifest-1971.json');
    $manifest=hmgp_manifest($manifestPath);
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    require_once $root.'/app/integrations/anex-search-mapping-registry.php';
    $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
    $active=hmgp_active_targets($pdo,$manifest);$decisions=hmgp_anex_decisions($pdo);$exclusions=hmgp_anex_exclusions($pdo);$and=hmgp_andromeda($pdo);
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$resolve=$registry->previewResolver();
    $result=['status'=>'read_only_complete','operation_id'=>HMGP_OPERATION,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'manifest'=>['anex'=>209,'andromeda'=>35,'total'=>244],'counts'=>['anex'=>[],'andromeda'=>[]],'rows'=>['anex'=>[],'andromeda'=>[]]];
    foreach($manifest['anex'] as [$external,$target]){
        $external=(int)$external;$target=(int)$target;$row=['external_hotel_id'=>$external,'proposed_local_id'=>$target];
        if(($active[$target]??false)!==true){hmgp_bucket($result,'anex','target_missing_or_inactive',$row);continue;}
        if(isset($decisions[$external])){hmgp_bucket($result,'anex','manual_protected',$row);continue;}
        if(isset($exclusions[$external][$target])){hmgp_bucket($result,'anex','excluded',$row);continue;}
        $current=$resolve('anex_online',(string)$external);
        if(is_int($current)&&$current>0){$row['current_local_id']=$current;hmgp_bucket($result,'anex',$current===$target?'already_same':'target_drift',$row);continue;}
        hmgp_bucket($result,'anex','unresolved_current',$row);
    }
    foreach($manifest['andromeda'] as [$external,$target]){
        $eid=(string)$external;$target=(int)$target;$row=['external_hotel_id'=>$eid,'proposed_local_id'=>$target];
        if(($active[$target]??false)!==true){hmgp_bucket($result,'andromeda','target_missing_or_inactive',$row);continue;}
        $entries=$and[$eid]??[]; if(count($entries)>1){$namespaces=array_values(array_unique(array_map(fn($r)=>(string)($r['supplier_namespace']??''),$entries)));if(count($namespaces)>1){$row['namespaces']=$namespaces;hmgp_bucket($result,'andromeda','namespace_ambiguous',$row);continue;}}
        $entry=$entries[0]??null;if(is_array($entry)){$status=(string)($entry['decision_status']??'');$current=hmgp_int($entry['local_hotel_id']??null);$row['current_status']=$status;$row['current_local_id']=$current;
            if($status==='accepted'&&$current!==null){hmgp_bucket($result,'andromeda',$current===$target?'already_same':'target_drift',$row);continue;}
            if($status==='conflict'){hmgp_bucket($result,'andromeda','conflict',$row);continue;}
        }
        hmgp_bucket($result,'andromeda','unresolved_current',$row);
    }
    $pdo->rollBack();$pdo=null;
    foreach(['anex','andromeda'] as $p)ksort($result['counts'][$p]);
    $result['current_unresolved_total']=($result['counts']['anex']['unresolved_current']??0)+($result['counts']['andromeda']['unresolved_current']??0);
    $result['current_already_same_total']=($result['counts']['anex']['already_same']??0)+($result['counts']['andromeda']['already_same']??0);
    echo 'HMGP_JSON:'.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();echo 'HMGP_JSON:'.json_encode(['status'=>'failed','operation_id'=>HMGP_OPERATION,'reason'=>preg_replace('/[^A-Za-z0-9_:. -]/','?',substr($e->getMessage(),0,180)),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0],JSON_UNESCAPED_SLASHES).PHP_EOL;exit(2);}finally{ob_end_clean();}
