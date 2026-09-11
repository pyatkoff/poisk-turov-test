<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_translit_exact_review.php';

const HMFN_OPERATION = 'hotel-match-former-name-review-1971-20260911-v1';

function hmfn_former_segments(string $name): array {
    $out=[];
    if(preg_match_all('/\(\s*(?:ex|ех|former(?:ly)?)\s*\.?\s*([^()]+)\)/ui',$name,$m)){
        foreach($m[1] as $v){$v=trim((string)$v," \t\n\r\0\x0B.,;:-");if($v!=='')$out[$v]=true;}
    }
    if(preg_match('/(?:^|[\s,;\/\-])(?:ex|ех|former(?:ly)?)\s*\.?\s+(.+)$/ui',$name,$m)){
        $v=trim((string)$m[1]," \t\n\r\0\x0B.,;:-() ");if($v!=='')$out[$v]=true;
    }
    return array_keys($out);
}
function hmfn_tokens(string $name): array { return pcbr_identity_tokens(htxr_latin($name)); }
function hmfn_key(string $name): string { return implode(' ',hmfn_tokens($name)); }
function hmfn_index(array $hotels,array $names): array {
    $idx=[];$segments=0;
    foreach($hotels as $id=>$hotel){$country=(int)$hotel['country_id'];foreach($names[$id]??[(string)$hotel['name']] as $raw){
        foreach(hmfn_former_segments((string)$raw) as $former){$key=hmfn_key($former);if($key==='')continue;$idx[$country][$key][(int)$id][$former]=true;$segments++;}
    }}
    return [$idx,$segments];
}
function hmfn_match(array $sourceNames,array $sourcePlaces,array $coordSource,int $country,array $index,array $hotels,array $excluded=[]): array {
    $matches=[];
    foreach($sourceNames as $source){$source=trim((string)$source);if($source==='')continue;$tokens=hmfn_tokens($source);$key=implode(' ',$tokens);if($key==='')continue;
        foreach($index[$country][$key]??[] as $id=>$formerNames){$id=(int)$id;if(isset($excluded[$id])||!isset($hotels[$id]))continue;$h=$hotels[$id];
            foreach(array_keys($formerNames) as $former){if(hcar_critical($source)!==hcar_critical((string)$former))continue;
                $guard=mbr_target_guard($coordSource,$h);if($guard['coordinate_conflict'])continue;
                $distance=$guard['distance_m']??null;$place=htxr_place($sourcePlaces,[(string)($h['region_name']??''),(string)($h['subregion_name']??'')]);
                $direct=($distance!==null&&(float)$distance<=1000.0)||$place;if(!$direct)continue;
                $single=count(array_values(array_unique($tokens)))===1;$token=$single?(string)$tokens[0]:'';
                $singleStrong=$single&&strlen($token)>=6&&$distance!==null&&(float)$distance<=100.0;
                if($single&&!$singleStrong)continue;
                $matches[$id]=['target_local_hotel_id'=>$id,'target_name'=>$h['name'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],'target_category'=>$h['category']===null?null:(int)$h['category'],'source_name'=>$source,'former_name'=>(string)$former,'identity_tokens'=>$tokens,'distance_m'=>$distance===null?null:round((float)$distance,2),'place_match'=>$place,'single_token_coordinate_anchor'=>$singleStrong,'rule'=>$singleStrong?'exact_former_name_single_distinctive_coordinate':'exact_former_name_multi_token_direct_geo'];
            }
        }
    }
    if(!$matches)return ['candidate'=>null,'reason'=>'no_exact_former_name'];
    if(count($matches)!==1)return ['candidate'=>null,'reason'=>'former_name_ambiguous','targets'=>count($matches)];
    return ['candidate'=>array_values($matches)[0],'reason'=>'safe'];
}
function hmfn_review(PDO $db,string $operation=HMFN_OPERATION): array {
    if($operation!==HMFN_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);[$formerIndex,$formerSegments]=hmfn_index($hotels,$names);[$anexLocal,$andromedaLocal]=mbr_local_sets($db);$shaCountry=fc_sha_countries($db);[$latest,$counts,$lastSeen]=mlp_observations($db);
        $stats=['former_segments_indexed'=>$formerSegments,'andromeda_examined'=>0,'anex_examined'=>0,'live_examined'=>0,'prepared'=>0,'prepared_andromeda'=>0,'prepared_anex'=>0,'live_prepared'=>0,'opposite_bridge_prepared'=>0,'single_token_coordinate_prepared'=>0,'category_mismatch_prepared'=>0,'no_exact_former_name'=>0,'former_name_ambiguous'=>0];$prepared=[];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$obsCount=(int)($counts[$external]??0);$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;$stats['andromeda_examined']++;if($obsCount>0)$stats['live_examined']++;
            [$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($r,$obs);$found=hmfn_match($sourceNames,$sourcePlaces,$coordSource,$country,$formerIndex,$hotels);if($found['candidate']===null){$reason=$found['reason'];if(isset($stats[$reason]))$stats[$reason]++;continue;}$c=$found['candidate'];$mismatch=$sourceCategory!==null&&$c['target_category']!==null&&$sourceCategory!==$c['target_category'];$bridge=isset($anexLocal[(int)$c['target_local_hotel_id']]);$prepared[]=array_merge(['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'observation_count'=>$obsCount,'last_seen_utc'=>$lastSeen[$external]??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,'category_mismatch'=>$mismatch,'existing_opposite_provider_bridge'=>$bridge],$c);$stats['prepared_andromeda']++;if($obsCount>0)$stats['live_prepared']++;if($bridge)$stats['opposite_bridge_prepared']++;if($c['single_token_coordinate_anchor'])$stats['single_token_coordinate_prepared']++;if($mismatch)$stats['category_mismatch_prepared']++;
        }
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;$observed=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(int)$o['anex_hotel_id'];if(!isset($observed[$id]))$observed[$id]=$o;}
        $ids=array_values(array_unique(array_merge(array_keys($observed),array_keys($staging))));sort($ids,SORT_NUMERIC);
        foreach($ids as $id){$id=(int)$id;if(isset($manual[$id])||isset($existing[$id]))continue;$s=$staging[$id]??[];$o=$observed[$id]??null;$country=(int)($o['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)(fc_country($s['api_country']??'')??0);if(!isset(MBR_CORE8[$country]))continue;$stats['anex_examined']++;$obsCount=(int)($o['search_count']??0);if($obsCount>0)$stats['live_examined']++;
            $sourceNames=array_values(array_unique(array_filter([(string)($o['hotel_name']??''),(string)($s['api_name']??''),(string)($s['xml_name']??''),(string)($s['xml_alternate_name']??'')],static fn($v)=>trim($v)!=='')));$sourcePlaces=array_values(array_unique(array_filter([(string)($s['api_region']??''),(string)($s['api_town']??''),(string)($o['region_name']??'')],static fn($v)=>trim($v)!=='')));$coordSource=array_merge($s,$o??[]);$sourceCategory=mbr_numeric_category($s);if($sourceCategory===null&&$o)$sourceCategory=mbr_numeric_category($o);
            $found=hmfn_match($sourceNames,$sourcePlaces,$coordSource,$country,$formerIndex,$hotels,$excluded[$id]??[]);if($found['candidate']===null){$reason=$found['reason'];if(isset($stats[$reason]))$stats[$reason]++;continue;}$c=$found['candidate'];$mismatch=$sourceCategory!==null&&$c['target_category']!==null&&$sourceCategory!==$c['target_category'];$bridge=isset($andromedaLocal[(int)$c['target_local_hotel_id']]);$prepared[]=array_merge(['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'observation_count'=>$obsCount,'last_seen_utc'=>$o['last_seen_utc']??null,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'source_category'=>$sourceCategory,'category_mismatch'=>$mismatch,'existing_opposite_provider_bridge'=>$bridge],$c);$stats['prepared_anex']++;if($obsCount>0)$stats['live_prepared']++;if($bridge)$stats['opposite_bridge_prepared']++;if($c['single_token_coordinate_anchor'])$stats['single_token_coordinate_prepared']++;if($mismatch)$stats['category_mismatch_prepared']++;
        }
        $stats['prepared']=count($prepared);usort($prepared,static fn($a,$b)=>(int)$b['observation_count']<=>(int)$a['observation_count'] ?: (int)$b['existing_opposite_provider_bridge']<=>(int)$a['existing_opposite_provider_bridge'] ?: strcmp((string)$a['provider'],(string)$b['provider']) ?: strcmp((string)$a['external_id'],(string)$b['external_id']));$coverage=fc_coverage($db);$db->commit();return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'catalog_scope'=>$scope,'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$prepared];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
