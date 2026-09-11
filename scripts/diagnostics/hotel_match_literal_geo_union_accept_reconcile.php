<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_full_catalog_reconcile.php';

const HMLGUAR_OPERATION='hotel-match-literal-geo-union-accept-reconcile-1971-20260912-v1';
const HMLGUAR_TARGET_OPERATION='hotel-match-literal-geo-union-accept-1971-20260912-v1';

function hmlguar_reconcile(PDO $db,string $operation=HMLGUAR_OPERATION):array{
    if($operation!==HMLGUAR_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $mappingDigest=fc_hash([HMLGUAR_TARGET_OPERATION,'literal_geo_union_server_current_v1']);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $db->exec('START TRANSACTION READ ONLY');
    try{
        $coverage=fc_coverage($db);
        $q=$db->prepare("SELECT anex_hotel_id,catalog_hotel_id,match_class,scope,approval_policy,source_row_digest,mapping_digest,enabled FROM anex_hotel_search_mappings WHERE mapping_digest=? ORDER BY anex_hotel_id");
        $q->execute([$mappingDigest]);$anex=$q->fetchAll(PDO::FETCH_ASSOC);
        $andromeda=[];
        foreach($db->query("SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as$r){
            $e=fc_evidence($r['evidence_json']??'');$promotion=$e['promotion']??null;
            if(!is_array($promotion)||(string)($promotion['operation_id']??'')!==HMLGUAR_TARGET_OPERATION)continue;
            $andromeda[]=['external_id'=>(string)$r['external_hotel_id'],'target'=>(int)$r['local_hotel_id'],'evidence_sha256'=>(string)$r['evidence_sha256']];
        }
        $an=[];foreach($anex as$r)$an[]=['external_id'=>(int)$r['anex_hotel_id'],'target'=>(int)$r['catalog_hotel_id'],'enabled'=>(int)$r['enabled'],'scope'=>(string)$r['scope'],'approval_policy'=>(string)$r['approval_policy'],'source_row_digest'=>(string)$r['source_row_digest']];
        $db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'target_operation_id'=>HMLGUAR_TARGET_OPERATION,'mode'=>'read_only_unknown_write_reconciliation','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'historical_operations_replayed'=>false,'found'=>['total'=>count($an)+count($andromeda),'anex'=>count($an),'andromeda'=>count($andromeda)],'anex'=>$an,'andromeda'=>$andromeda,'coverage_current'=>$coverage,'guards'=>['target_operation_not_replayed'=>true,'anex_exact_mapping_digest'=>true,'andromeda_exact_promotion_operation_id'=>true,'read_only'=>true]];
    }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){$root=realpath(__DIR__.'/../..');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');echo fc_json(hmlguar_reconcile(v2_data_db(),HMLGUAR_OPERATION)),"\n";}
