<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_pending_candidate_bridge_review.php';

const HMPNB_OPERATION = 'hotel-match-provider-name-bridge-review-2333-20260913-v1';

function hmpnb_key(string $name): string {
    $tokens=pcbr_identity_tokens(htxr_latin($name));
    return implode(' ',$tokens);
}
function hmpnb_critical(string $name): array {
    $critical=['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1];$out=[];
    foreach(pcbr_identity_tokens(htxr_latin($name)) as $t)if(isset($critical[$t]))$out[$t]=1;
    $k=array_keys($out);sort($k,SORT_STRING);return $k;
}
function hmpnb_review(PDO $db,string $operation=HMPNB_OPERATION): array {
    if($operation!==HMPNB_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);$shaCountry=fc_sha_countries($db);[$latest,$counts,$lastSeen]=mlp_observations($db);
        $anex=[];$mappingRows=$db->query("SELECT m.anex_hotel_id,m.catalog_hotel_id,h.api_name,h.xml_name,h.xml_alternate_name,h.api_country,h.api_region,h.api_town FROM anex_hotel_search_mappings m LEFT JOIN anex_hotels h ON h.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 ORDER BY m.anex_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
        $obs=[];foreach($db->query("SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(int)$o['anex_hotel_id'];if(!isset($obs[$id]))$obs[$id]=$o;}
        foreach($mappingRows as $r){$id=(int)$r['anex_hotel_id'];$local=(int)$r['catalog_hotel_id'];if(!isset($hotels[$local]))continue;$country=(int)$hotels[$local]['country_id'];if(!isset(MBR_CORE8[$country]))continue;
            $sourceNames=array_values(array_unique(array_filter([(string)($r['api_name']??''),(string)($r['xml_name']??''),(string)($r['xml_alternate_name']??''),(string)($obs[$id]['hotel_name']??'')],static fn($v)=>trim($v)!=='')));
            foreach($sourceNames as $name){$key=hmpnb_key($name);if($key===''||count(explode(' ',$key))<2)continue;$anex[$country][$key][$local][$id][]=$name;}
        }
        $stats=['pending_examined'=>0,'provider_name_key_hit'=>0,'unique_local'=>0,'critical_guard_block'=>0,'category_guard_block'=>0,'safe'=>0,'safe_observed'=>0,'ambiguous_local'=>0];$safe=[];$evidence=[];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $external=(string)$r['external_hotel_id'];$o=$latest[$external]??null;$country=(int)($o['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;$stats['pending_examined']++;
            [$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($r,$o);$locals=[];$matches=[];
            foreach($sourceNames as $sourceName){$key=hmpnb_key((string)$sourceName);if($key===''||count(explode(' ',$key))<2)continue;foreach($anex[$country][$key]??[] as $local=>$byAnex){foreach($byAnex as $aid=>$anexNames){foreach($anexNames as $anexName){if(hmpnb_critical((string)$sourceName)!==hmpnb_critical((string)$anexName))continue;$locals[(int)$local]=1;$matches[]=['source_name'=>(string)$sourceName,'anex_hotel_id'=>(int)$aid,'anex_name'=>(string)$anexName,'local_hotel_id'=>(int)$local,'identity_key'=>$key];}}}}
            if(!$matches)continue;$stats['provider_name_key_hit']++;if(count($locals)!==1){$stats['ambiguous_local']++;continue;}$local=(int)array_key_first($locals);$stats['unique_local']++;$target=$hotels[$local];
            $criticalOk=true;foreach($matches as $m){if((int)$m['local_hotel_id']!==$local)continue;if(hmpnb_critical($m['source_name'])!==hmpnb_critical($m['anex_name'])){$criticalOk=false;break;}}if(!$criticalOk){$stats['critical_guard_block']++;continue;}
            $targetCat=$target['category']===null?null:(int)$target['category'];if($sourceCategory!==null&&$targetCat!==null&&$sourceCategory!==$targetCat){$stats['category_guard_block']++;$evidence[]=['external_id'=>$external,'country_id'=>$country,'reason'=>'category_mismatch','source_category'=>$sourceCategory,'target_category'=>$targetCat,'target'=>mbr_row_target($target),'matches'=>$matches];continue;}
            $guard=mbr_target_guard($coordSource,$target);if($guard['coordinate_conflict'])continue;
            $row=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'observation_count'=>(int)($counts[$external]??0),'last_seen_utc'=>$lastSeen[$external]??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,'target'=>mbr_row_target($target),'guard'=>$guard,'matches'=>$matches,'rule'=>'exact_provider_provider_identity_unique_accepted_anex_local_category_guard'];$safe[]=$row;$stats['safe']++;if((int)($counts[$external]??0)>0)$stats['safe_observed']++;
        }
        usort($safe,static fn($a,$b)=>(int)$b['observation_count']<=>(int)$a['observation_count'] ?: strcmp($a['external_id'],$b['external_id']));$coverage=fc_coverage($db);$db->commit();return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'catalog_scope'=>$scope,'coverage'=>$coverage,'stats'=>$stats,'safe'=>$safe,'evidence_only'=>$evidence];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
