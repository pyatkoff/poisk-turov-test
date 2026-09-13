<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/anex_tourvisor_hotelcode_evidence_collect.php';

const RVR_OPERATION = 'hotel-match-global-tourvisor-reverse-id-2333-20260913-v1';
const RVR_SOURCE_OPERATION = 'hotel-match-global-tourvisor-sold-dates-2333-20260913-v1';
const RVR_COORD_BLOCK_M = 5000.0;

function rvr_sig_tokens(string $s): array {
    $s=fc_norm($s);$parts=preg_split('/[^a-zа-я0-9]+/ui',$s,-1,PREG_SPLIT_NO_EMPTY)?:[];$drop=['hotel'=>1,'resort'=>1,'spa'=>1,'отель'=>1];$out=[];
    foreach($parts as $p){$p=mb_strtolower($p,'UTF-8');if(isset($drop[$p])||mb_strlen($p,'UTF-8')<2)continue;$out[$p]=true;}return $out;
}
function rvr_qualifiers(string $s): array {
    $t=rvr_sig_tokens($s);$out=[];foreach(['annex','beach','garden','north','south','east','west','club','family','adult','adults','aqua','royal','grand','premium','select','collection','palace','village'] as $q)if(isset($t[$q]))$out[$q]=true;return $out;
}
function rvr_qualifier_conflict(array $sources,string $target): bool {
    $a=[];foreach($sources as $s)$a+=rvr_qualifiers((string)$s);$b=rvr_qualifiers($target);foreach(array_keys($a) as $q)if(!isset($b[$q]))return true;foreach(array_keys($b) as $q)if(!isset($a[$q]))return true;return false;
}
function rvr_name_evidence(array $sources,string $target): array {
    $tkStrict=fc_key($target,false,false);$tkBroad=fc_key($target,true,true);$bestShared=0;$exact=false;$broad=false;
    $tt=rvr_sig_tokens($target);
    foreach($sources as $s){$s=(string)$s;if($s==='')continue;if($tkStrict!==''&&fc_key($s,false,false)===$tkStrict)$exact=true;if($tkBroad!==''&&fc_key($s,true,true)===$tkBroad)$broad=true;$bestShared=max($bestShared,count(array_intersect_key(rvr_sig_tokens($s),$tt)));}
    return ['exact'=>$exact,'broad'=>$broad,'shared_tokens'=>$bestShared];
}
function rvr_current_context(PDO $db): array {
    $current=ate_current_seeds($db);$seed=[];foreach($current['seeds'] as $s)$seed[(int)$s['anex_hotel_id']]=$s;
    [$hotels]=$tmp=mbr_catalog($db);[$manual,$existing,$excluded,$occupied]=gtv_current_guards($db);
    return ['seeds'=>$seed,'coverage'=>$current['coverage'],'hotels'=>$hotels,'manual'=>$manual,'existing'=>$existing,'excluded'=>$excluded,'occupied'=>$occupied];
}
function rvr_review(PDO $db,array $source,string $operation): array {
    if($operation!==RVR_OPERATION)throw new RuntimeException('operation_scope');if(($source['status']??'')!=='completed'||($source['operation_id']??'')!==RVR_SOURCE_OPERATION)throw new RuntimeException('source_invalid');
    $ctx=rvr_current_context($db);$calls=['result_reads'=>0,'tour_detail_calls'=>0,'anex_page_gets'=>0,'search_starts'=>0,'continue_calls'=>0];$errors=[];$unique=[];$searches=[];
    foreach(($source['searches']??[]) as $s){if(!is_array($s))continue;$sid=(int)($s['search_id']??0);$op=(int)($s['operator_id']??0);$country=(int)($s['country_id']??0);if($sid<1||$op<1)continue;
        try{$rows=gtv_result_rows($sid,$calls['result_reads']);}catch(Throwable $e){$errors[]=['search_id'=>$sid,'stage'=>'result','error'=>$e->getMessage()];continue;}
        $kept=0;foreach($rows as $hotel){$tv=(int)($hotel['id']??0);if($tv<1)continue;$tour=ate_first_anex_tour_id($hotel,$op);if(!$tour)continue;$key=$tv.'|'.$tour;if(!isset($unique[$key]))$unique[$key]=['tv_hotel_id'=>$tv,'tour_id'=>$tour,'operator_id'=>$op,'country_id'=>$country,'hotel'=>$hotel,'search_ids'=>[],'dates'=>[]];$unique[$key]['search_ids'][$sid]=true;$d=trim((string)($s['date']??''));if($d!=='')$unique[$key]['dates'][$d]=true;$kept++;}
        $searches[]=['search_id'=>$sid,'country_id'=>$country,'date'=>$s['date']??null,'result_hotels'=>count($rows),'anex_tour_rows'=>$kept];
    }
    $direct=[];$safe=[];$blocked=[];$resolvedOther=0;
    foreach($unique as $u){usleep(650000);try{$detail=v2_data_tv_get('/tours/'.rawurlencode((string)$u['tour_id']),['currency'=>'RUB']);$calls['tour_detail_calls']++;}catch(Throwable $e){$errors[]=['tv_hotel_id'=>$u['tv_hotel_id'],'stage'=>'detail','error'=>$e->getMessage()];continue;}
        $link=trim((string)($detail['operatorLink']??''));$aid=$link!==''?ate_operator_hotellist($link):null;if(!$aid)continue;if(!isset($ctx['seeds'][$aid])){$resolvedOther++;continue;}$seed=$ctx['seeds'][$aid];$tvRow=$u['hotel'];$country=(int)$u['country_id'];$target=$ctx['hotels'][(int)$u['tv_hotel_id']]??null;
        $r=['anex_hotel_id'=>$aid,'tv_hotel_id'=>(int)$u['tv_hotel_id'],'tv_hotel_name'=>(string)($tvRow['name']??''),'country_id'=>$country,'operator_link_host'=>(string)(parse_url($link,PHP_URL_HOST)??''),'search_count'=>(int)($seed['search_count']??0),'observed'=>(bool)($seed['observed']??false),'source_names'=>array_values($seed['source_names']??[]),'source_places'=>array_values($seed['source_places']??[]),'search_ids'=>array_map('intval',array_keys($u['search_ids'])),'sold_dates'=>array_values(array_keys($u['dates']))];sort($r['sold_dates']);
        if(!$target||$country!==(int)($seed['country_id']??0)||$country!==(int)($target['country_id']??0)){$r['evidence_class']='country_or_target_conflict';$blocked[]=$r;$direct[]=$r;continue;}
        $distance=fc_dist($seed['latitude']??null,$seed['longitude']??null,$tvRow['latitude']??null,$tvRow['longitude']??null);$place=fc_place($seed['source_places']??[],ate_tv_places($tvRow));$name=rvr_name_evidence($seed['source_names']??[],(string)($tvRow['name']??''));$qual=rvr_qualifier_conflict($seed['source_names']??[],(string)($tvRow['name']??''));$r['distance_m']=$distance;$r['place_match']=$place;$r['name_evidence']=$name;$r['qualifier_conflict']=$qual;
        if($distance!==null&&$distance>RVR_COORD_BLOCK_M){$r['evidence_class']='coordinate_conflict';$blocked[]=$r;$direct[]=$r;continue;}if($qual){$r['evidence_class']='qualifier_conflict';$blocked[]=$r;$direct[]=$r;continue;}if(isset($ctx['manual'][$aid])){$r['evidence_class']='manual_protected';$blocked[]=$r;$direct[]=$r;continue;}if(isset($ctx['existing'][$aid])){$r['evidence_class']='mapping_appeared';$blocked[]=$r;$direct[]=$r;continue;}if(isset($ctx['excluded'][$aid][(int)$u['tv_hotel_id']])){$r['evidence_class']='pair_exclusion';$blocked[]=$r;$direct[]=$r;continue;}
        $occupants=array_values(array_filter($ctx['occupied'][(int)$u['tv_hotel_id']]??[],static fn($x)=>$x!==$aid));if($occupants){$r['evidence_class']='target_occupied_same_provider';$r['occupants']=$occupants;$blocked[]=$r;$direct[]=$r;continue;}
        $semantic=$name['exact']||($name['broad']&&($place||($distance!==null&&$distance<=5000)))||($distance!==null&&$distance<=1000)||($place&&$name['shared_tokens']>=2);
        $pageCode=null;$pageStatus='not_fetched';if(ate_allowed_anex_page($link)&&$calls['anex_page_gets']<220){usleep(700000);$page=ate_fetch_anex_page($link);$calls['anex_page_gets']++;$pageStatus=(string)($page['status']??'unknown');if($pageStatus==='ok'){$h=ate_page_hotelcode_evidence((string)$page['body']);if(($h['status']??'')==='confirmed')$pageCode=(int)$h['hotel_code'];}}
        $r['page_status']=$pageStatus;$r['page_hotel_code']=$pageCode;if($pageCode!==null&&$pageCode!==$aid){$r['evidence_class']='page_hotelcode_conflict';$blocked[]=$r;$direct[]=$r;continue;}
        if($semantic){$r['evidence_class']=$pageCode===$aid?'direct_hotellist_plus_page_hotelcode_semantic':'direct_hotellist_plus_semantic';$safe[]=$r;}else{$r['evidence_class']='direct_hotellist_needs_semantic_corroboration';$blocked[]=$r;}$direct[]=$r;
    }
    usort($safe,static fn($a,$b)=>(int)$b['search_count']<=>(int)$a['search_count'] ?: (int)$a['anex_hotel_id']<=>(int)$b['anex_hotel_id']);
    return ['status'=>'completed','operation_id'=>$operation,'mode'=>'saved_tourvisor_search_reverse_operator_id_current_guarded','database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'search_starts'=>0,'search_continue_calls'=>0,'supplier_calls_total'=>array_sum($calls),'supplier'=>$calls,'source_search_count'=>count($searches),'unique_tourvisor_tours'=>count($unique),'current_unresolved_count'=>count($ctx['seeds']),'direct_unresolved_hits'=>count($direct),'safe_count'=>count($safe),'blocked_count'=>count($blocked),'resolved_or_nonqueue_operator_ids'=>$resolvedOther,'coverage'=>$ctx['coverage'],'searches'=>$searches,'errors'=>$errors,'safe_candidates'=>$safe,'blocked_direct_hits'=>$blocked,'all_direct_hits'=>$direct,'guards'=>['operator_hotellist_first'=>true,'country_required'=>true,'coordinate_conflict_block_m'=>RVR_COORD_BLOCK_M,'symmetric_significant_qualifiers'=>true,'semantic_corroboration_after_direct_id'=>true,'manual_existing_exclusion_occupancy_protected'=>true,'saved_search_creation_replayed'=>false,'database_write'=>false]];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"library_only: guarded workflow required\n");exit(64);}