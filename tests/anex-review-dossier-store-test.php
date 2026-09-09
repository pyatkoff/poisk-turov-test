<?php
declare(strict_types=1);
require_once __DIR__.'/../app/admin/anex-review/dossier-store.php';
require_once __DIR__.'/../app/admin/anex-review/service.php';
$dsn=getenv('ANEX_REVIEW_TEST_DSN');
if ($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=anex_review_test;charset=utf8mb4') throw new RuntimeException('test_db_required');
$db=new PDO($dsn,'root',getenv('ANEX_REVIEW_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
foreach (explode(';',(string)file_get_contents(__DIR__.'/../app/admin/anex-review/dossier-schema.sql')) as $sql) if (trim($sql)!=='') $db->exec($sql);
$checks=0;
function must(bool $v,string $m):void { global $checks;++$checks;if(!$v)throw new RuntimeException($m); }
function rejects(callable $f,string $message):void {try{$f();}catch(Throwable $e){must($e->getMessage()===$message,$e->getMessage());return;}throw new RuntimeException('expected '.$message);}
function j($v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function item(int $id,string $status='review'):array {
    $evidence=['external_id'=>$id,'status'=>$status,'reason'=>$status==='source_error'?'interrupted_result_unknown':'competing_candidates',
        'api'=>['name'=>'Saved API hotel','country'=>'Turkey','address'=>'Saved address','latitude'=>36.0,'longitude'=>30.0],
        'candidates'=>$status==='review'?[['id'=>101,'score'=>0.9,'name_similarity'=>0.98,'distance_m'=>42],['id'=>102,'score'=>0.85]]:[]];
    $raw=j($evidence);$sha=hash('sha256',$raw);
    $row=['anex_hotel_id'=>$id,'status'=>$status,'reason'=>$evidence['reason'],'automatic_acceptance'=>false,'automatic_retry'=>false,
        'observation'=>['anex_hotel_id'=>$id,'country_id'=>1,'search_count'=>1],'evidence_origin'=>'live_checkpoint',
        'evidence'=>$evidence,'evidence_row_sha256'=>$sha,'prior_fixed_queue_hints'=>[['id'=>103]]];
    return ['id'=>$id,'row_json'=>j($row),'row_digest'=>hash('sha256',j($row)),'evidence_json'=>$raw,'evidence_digest'=>$sha];
}
function envelope(int $artifact,array $rows):array{return ['protocol_version'=>1,'kind'=>'observed_dossier_import','scope'=>'preview','artifact_id'=>$artifact,
    'source_sha'=>str_repeat('a',40),'source_digest'=>str_repeat('b',64),'checkpoint_digest'=>str_repeat('c',64),'rows'=>$rows];}
function snapshot(PDO $db):array{
    $out=[];foreach(['catalog_hotels','anex_hotel_decisions','anex_hotel_search_mappings','anex_hotel_candidates','anex_hotels','anex_hotel_auto_matches'] as $t)$out[$t]=$db->query('SELECT * FROM '.$t.' ORDER BY 1,2')->fetchAll(PDO::FETCH_ASSOC);return $out;
}
$before=snapshot($db);$store=new AnexReviewDossierStore($db);$service=new AnexReviewService($db);
$version=$service->detail(30)['version'];
$batch=envelope(100,[item(30,'source_error'),item(29)]);
must($store->import($batch)['inserted']===2,'stores both records');
must($store->import($batch)['status']==='already_stored','idempotent');
must($store->latest(30)['row']['prior_fixed_queue_hints']===[['id'=>103]],'hints retained as hints');
must($service->detail(30)['candidates']===[],'unknown never promotes prior hint');
must($service->detail(30)['version']!==$version,'persistent evidence stales old page');
must(count($service->detail(29)['candidates'])===2,'live candidates replace absent staging');
must($service->detail(29)['source']['api_address']==='Saved address','source projected');
must($store->panel(29,2)['candidates']===[],'changed country fails closed');
must($store->panel(29,2)['evidence']['automated_reason']==='observed_country_changed','country reason');
must($service->queue(['status'=>'no_candidates','q'=>'29'])['total']===0,'live candidates excluded from empty filter');
must($service->queue(['status'=>'no_candidates','q'=>'30'])['total']===1,'unknown hints remain in empty filter');
$db->exec('UPDATE anex_search_hotel_observations SET country_id=2 WHERE anex_hotel_id=29');
must($service->queue(['status'=>'no_candidates','q'=>'29'])['total']===1,'queue respects changed country');
$db->exec('UPDATE anex_search_hotel_observations SET country_id=1 WHERE anex_hotel_id=29');
$db->exec('UPDATE anex_review_dossiers SET display_candidate_count=0 WHERE artifact_id=100 AND anex_hotel_id=29');
rejects(fn()=>$store->latest(29),'dossier_index_invalid');
rejects(fn()=>$store->import($batch),'dossier_index_invalid');
$db->exec('UPDATE anex_review_dossiers SET display_candidate_count=2 WHERE artifact_id=100 AND anex_hotel_id=29');
$oldpage=$service->detail(29)['version'];
$next=envelope(102,[item(29)]);$next['source_digest']=str_repeat('d',64);
must($store->import($next)['inserted']===1,'new artifact adds immutable history');
must($service->detail(29)['version']!==$oldpage,'provenance update stales evidence');
must((int)$db->query('SELECT COUNT(*) FROM anex_review_dossiers WHERE anex_hotel_id=29')->fetchColumn()===2,'history preserved');
must($store->import(envelope(101,[item(29)]))['inserted']===1,'older archive can be retained');
must($store->latest(29)['artifact_id']===102,'older import does not roll back current evidence');
$bad=$batch;$bad['source_digest']=str_repeat('e',64);rejects(fn()=>$store->import($bad),'dossier_artifact_conflict');
$bad=envelope(104,[item(29),item(29)]);rejects(fn()=>$store->import($bad),'dossier_duplicate_id');
$bad=envelope(104,[item(29)]);$bad['rows'][0]['row_json'].=' ';rejects(fn()=>$store->import($bad),'dossier_row_digest_invalid');
rejects(fn()=>$store->import(envelope(104,[item(999)])),'dossier_not_observed');
must((int)$db->query('SELECT COUNT(*) FROM anex_review_dossier_batches WHERE artifact_id=104')->fetchColumn()===0,'failed import rolls back batch');
$db->exec("CREATE TRIGGER fail_dossier BEFORE INSERT ON anex_review_dossiers FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='injected_failure'");
try{$store->import(envelope(105,[item(28)]));throw new RuntimeException('expected failure');}catch(PDOException $e){must(strpos($e->getMessage(),'injected_failure')!==false,'injected rollback');}
$db->exec('DROP TRIGGER fail_dossier');
must((int)$db->query('SELECT COUNT(*) FROM anex_review_dossier_batches WHERE artifact_id=105')->fetchColumn()===0,'atomic rollback');
// A deleted archive row must never be silently recreated under a finalized batch.
$db->exec('DELETE FROM anex_review_dossiers WHERE artifact_id=101');
rejects(fn()=>$store->import(envelope(101,[item(29)])),'dossier_history_incomplete');
$db->exec("UPDATE anex_review_dossiers SET row_json='{}' WHERE artifact_id=102 AND anex_hotel_id=29");
rejects(fn()=>$store->latest(29),'dossier_row_digest_invalid');
must(snapshot($db)===$before,'all original mappings/manual/staging/catalog preserved');
echo 'ANEX_DOSSIER_STORE_OK checks='.$checks.' supplier_calls=0 application_db=untouched'.PHP_EOL;
// Restore test-only optional schema so the existing unrelated HTTP fixture is unchanged.
$db->exec('DROP TABLE anex_review_dossiers');$db->exec('DROP TABLE anex_review_dossier_batches');
