<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_star_semantics_current_review.php';

const HMLGS_OPERATION='hotel-match-learned-geo-star-review-2333-20260913-v2';

function hmlgs_latin(string $v): string {
    $v=mb_strtolower($v,'UTF-8');
    return strtr($v,['щ'=>'shch','ш'=>'sh','ч'=>'ch','ц'=>'ts','ю'=>'yu','я'=>'ya','ё'=>'e','ж'=>'zh','х'=>'kh','а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','ы'=>'y','э'=>'e','ь'=>'','ъ'=>'']);
}
function hmlgs_place_key(string $v): string { return fc_norm(hmlgs_latin(trim($v))); }
function hmlgs_target_keys(array $h): array {
    $out=[]; foreach([(string)($h['region_name']??''),(string)($h['subregion_name']??'')] as $v){$k=hmlgs_place_key($v);if($k!=='')$out[$k]=1;} return array_keys($out);
}
function hmlgs_review(PDO $db,string $operation=HMLGS_OPERATION): array {
    if($operation!==HMLGS_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);
        $learn=[];$accepted=0;
        foreach($db->query("SELECT external_hotel_id,local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $local=(int)$r['local_hotel_id']; if(!isset($hotels[$local]))continue; $ev=fc_evidence($r['evidence_json']??'');
            $src=$ev['prior_evidence']['source']??($ev['source']??[]); if(!is_array($src))$src=[];
            $geo=$ev['prior_evidence']['geography']??($ev['geography']??[]); if(!is_array($geo))$geo=[];
            $sp=[]; foreach([(string)($src['town']??''),(string)($geo['town']??''),(string)($geo['parent']??'')] as $v){$k=hmlgs_place_key($v);if($k!=='')$sp[$k]=1;}
            $tk=hmlgs_target_keys($hotels[$local]); if(!$sp||!$tk)continue; $accepted++;
            foreach(array_keys($sp) as $s)foreach($tk as $t)$learn[$s][$t]=($learn[$s][$t]??0)+1;
        }
        $base=hmss_review($db,HMSS_OPERATION); $rows=[];$stats=['strict_star_mismatch'=>0,'learned_geo_supported'=>0,'learned_geo_strong'=>0,'unsafe_no_geo_support'=>0];
        foreach($base['rows'] as $r){if(empty($r['strict_unique_name']))continue;$stats['strict_star_mismatch']++;$target=$r['target']??[];$targetKeys=[];foreach([(string)($target['region']??''),(string)($target['subregion']??'')] as $v){$k=hmlgs_place_key($v);if($k!=='')$targetKeys[$k]=1;}
            $best=null;foreach($r['source_places']??[] as $place){$pk=hmlgs_place_key((string)$place);if($pk===''||empty($learn[$pk]))continue;$total=array_sum($learn[$pk]);arsort($learn[$pk]);foreach($learn[$pk] as $tk=>$n){if(!isset($targetKeys[$tk]))continue;$confidence=$total>0?$n/$total:0.0;$cand=['source_place'=>$place,'source_place_key'=>$pk,'target_place_key'=>$tk,'support'=>(int)$n,'total'=>(int)$total,'confidence'=>round($confidence,6)];if($best===null||$cand['confidence']>$best['confidence']||($cand['confidence']===$best['confidence']&&$cand['support']>$best['support']))$best=$cand;}}
            if($best===null){$stats['unsafe_no_geo_support']++;continue;}$stats['learned_geo_supported']++;$strong=$best['support']>=3&&$best['confidence']>=0.80;if($strong)$stats['learned_geo_strong']++;
            $rows[]=$r+['learned_geo'=>$best,'learned_geo_strong'=>$strong,'star_policy'=>'annotation_only_not_identity_veto'];
        }
        usort($rows,static fn($a,$b)=>(int)$b['learned_geo_strong']<=>(int)$a['learned_geo_strong'] ?: $b['learned_geo']['confidence']<=>$a['learned_geo']['confidence'] ?: $b['learned_geo']['support']<=>$a['learned_geo']['support']);
        $coverage=fc_coverage($db);$db->commit();return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'accepted_training_rows'=>$accepted,'learned_place_keys'=>count($learn),'coverage'=>$coverage,'stats'=>$stats,'rows'=>$rows];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
