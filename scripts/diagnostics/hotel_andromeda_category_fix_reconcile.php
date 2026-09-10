<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__.'/hotel_full_catalog_reconcile.php';

const ACF_OPERATION = 'hotel-andromeda-category-fix-1759-20260911-v1';

function acf_tokens($v): array { return fc_tokens($v, true); }
function acf_similarity($a,$b): array { $a=array_fill_keys(array_unique(acf_tokens($a)),1);$b=array_fill_keys(array_unique(acf_tokens($b)),1);$i=count(array_intersect_key($a,$b));$u=count($a+$b);return [$u?$i/$u:0.0,$i]; }
function acf_numeric_category(array $source): ?int {
    foreach(['category','star','stars','starName','star_name'] as $key){
        if(!array_key_exists($key,$source))continue;
        $v=trim((string)$source[$key]);
        if(preg_match('/^([1-5])(?:\s*(?:\*|★|stars?))?$/iu',$v,$m))return (int)$m[1];
    }
    return null;
}
function acf_old_category(array $source): ?int { foreach(['category','star','stars','starKey','starName','star_name'] as $key)if(array_key_exists($key,$source)&&preg_match('/([1-5])/',(string)$source[$key],$m))return (int)$m[1];return null; }
function acf_places(array $hotels): array { $out=[];foreach($hotels as $id=>$h)foreach([$h['region_name']??'',$h['subregion_name']??''] as $p){$p=fc_norm($p);if($p!=='')$out[(int)$h['country_id']][$p][(int)$id]=1;}return $out; }
function acf_reconcile(PDO $db,string $operation): array {
    fc_require_tables($db);$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET SESSION innodb_lock_wait_timeout=20');$before=fc_coverage($db);$committed=false;$writes=0;
    try{
        $db->beginTransaction();[$hotels,$names,$strict,$broad,$scope]=fc_catalog($db);$places=acf_places($hotels);$shaCountry=fc_sha_countries($db);$rows=[];
        $update=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=?");
        $stats=['pending'=>0,'fuzzy_winners'=>0,'old_category_conflicts'=>0,'accepted_false_type_conflicts'=>0,'true_numeric_mismatch'=>0,'not_false_category_conflict'=>0];
        $q=$db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id FOR UPDATE");
        while($r=$q->fetch(PDO::FETCH_ASSOC)){
            $stats['pending']++;$prior=fc_evidence($r['evidence_json']);$source=$prior['source']??[];if(!is_array($source))$source=[];$country=$shaCountry[$r['catalog_sha256']]??null;if(!$country||!isset(FC_COUNTRIES[$country]))continue;
            $original=array_values(array_unique(array_map('intval',$prior['candidate_ids']??[])));$pool=[];
            if($original){foreach($original as $id)if(isset($hotels[$id])&&(int)$hotels[$id]['country_id']===$country)$pool[$id]=1;}
            else{$geo=$prior['geography']??[];foreach([$source['town']??'',$geo['town']??'',$geo['parent']??''] as $p){$p=fc_norm($p);foreach(array_keys($places[$country][$p]??[]) as $id)$pool[(int)$id]=1;}}
            $sourceNames=array_values(array_filter([$source['name']??'',$source['lName']??'']));$geo=$prior['geography']??[];$best=[0.0,0,null];$second=0.0;
            foreach(array_keys($pool) as $id){$h=$hotels[$id];if(!fc_place([$source['town']??'',$geo['town']??'',$geo['parent']??''],[$h['region_name'],$h['subregion_name']]))continue;$score=0.0;$shared=0;foreach($sourceNames as $a)foreach($names[$id]??[] as $b){[$s,$n]=acf_similarity($a,$b);if($s>$score){$score=$s;$shared=$n;}}if($score>$best[0]){$second=$best[0];$best=[$score,$shared,(int)$id];}elseif($score>$second)$second=$score;}
            if($best[2]===null||$best[0]<0.82||$best[1]<2||$best[0]-$second<0.15)continue;$stats['fuzzy_winners']++;
            $target=(int)$best[2];$targetCategory=$hotels[$target]['category']===null?null:(int)$hotels[$target]['category'];$old=acf_old_category($source);$new=acf_numeric_category($source);
            if($old&&$targetCategory&&$old!==$targetCategory)$stats['old_category_conflicts']++;else continue;
            if($new!==null){$stats['true_numeric_mismatch']++;continue;}
            $label=trim((string)($source['star']??''));if($label===''||preg_match('/^[1-5](?:\s*(?:\*|★|stars?))?$/iu',$label)){$stats['not_false_category_conflict']++;continue;}
            $promotion=['operation_id'=>$operation,'source'=>'category_semantics_fix','rule'=>'nonnumeric_accommodation_type_not_star_rating','country_id'=>$country,'target'=>$target,'source_star'=>$label,'source_starKey'=>$source['starKey']??null,'old_parsed_star'=>$old,'target_category'=>$targetCategory,'score'=>$best[0],'margin'=>$best[0]-$second];$evidence=['prior_evidence'=>$prior,'promotion'=>$promotion];$json=fc_json($evidence);$hash=hash('sha256',$json);
            $update->execute([$target,$hash,$json,(string)$r['external_hotel_id'],$r['evidence_sha256']]);if($update->rowCount()!==1)throw new RuntimeException('andromeda_concurrent_change');$rows[(string)$r['external_hotel_id']]=[$target,$hash];$stats['accepted_false_type_conflicts']++;$writes++;
        }
        $db->commit();$committed=true;$verify=$db->prepare("SELECT local_hotel_id,decision_status,evidence_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=?");foreach($rows as $id=>$expected){$verify->execute([$id]);$v=$verify->fetch(PDO::FETCH_ASSOC);if(!$v||(int)$v['local_hotel_id']!==$expected[0]||$v['decision_status']!=='accepted'||$v['evidence_sha256']!==$expected[1])throw new RuntimeException('andromeda_readback');}
        return ['status'=>'completed','operation_id'=>$operation,'andromeda'=>$stats,'accepted_total'=>$writes,'database_writes'=>$writes,'readback_verified'=>true,'supplier_calls'=>0,'before'=>$before,'after'=>fc_coverage($db)];
    }catch(Throwable $e){if(!$committed&&$db->inTransaction())$db->rollBack();return ['status'=>$committed?'committed_readback_failed':'failed_rolled_back','operation_id'=>$operation,'database_writes'=>$committed?$writes:0,'readback_verified'=>false,'supplier_calls'=>0,'reason'=>in_array($e->getMessage(),['andromeda_concurrent_change','required_transactional_table_missing','country_contract_changed'],true)?$e->getMessage():'runtime_failure'];}
}
function acf_main(): array {$raw=file_get_contents('php://stdin',false,null,0,4097);$req=json_decode((string)$raw,true,16,JSON_THROW_ON_ERROR);if(($req['operation_id']??'')!==ACF_OPERATION||!in_array($req['phase']??'',['apply','receipt'],true))throw new RuntimeException('request_scope');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');$config=require $root.'/_preview/search3-anex-candidate/.andromeda-private.php';$private=realpath(dirname($config['catalog_path']));if(!$private||strpos($private,$root.DIRECTORY_SEPARATOR)===0)throw new RuntimeException('private_state');$dir=$private.'/'.ACF_OPERATION;if($req['phase']==='receipt'){if(is_file($dir.'/result.json'))return json_decode((string)file_get_contents($dir.'/result.json'),true,64,JSON_THROW_ON_ERROR);return ['status'=>is_file($dir.'/reservation.json')?'reserved_or_unknown':'not_started','operation_id'=>ACF_OPERATION,'supplier_calls'=>0];}if(is_dir($dir)||!mkdir($dir,0700))throw new RuntimeException('operation_already_reserved');fc_write_once($dir.'/reservation.json',['operation_id'=>ACF_OPERATION,'state'=>'reserved_before_database','scope'=>FC_COUNTRIES,'supplier_calls'=>0]);$helper=realpath($root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php'));if(!$helper||strpos($helper,$root.DIRECTORY_SEPARATOR)!==0)throw new RuntimeException('db_helper');require_once $helper;$db=v2_data_db();$result=acf_reconcile($db,ACF_OPERATION);fc_write_once($dir.'/result.json',$result);return $result;}
if(!defined('ACF_LIBRARY_ONLY')){error_reporting(0);ob_start();try{$out=acf_main();}catch(Throwable $e){$out=['status'=>'failed','operation_id'=>ACF_OPERATION,'reason'=>in_array($e->getMessage(),['operation_already_reserved'],true)?$e->getMessage():'runtime_failure','supplier_calls'=>0];}while(ob_get_level())ob_end_clean();echo fc_json($out),"\n";exit(in_array($out['status']??'', ['completed','empty'],true)?0:1);}
