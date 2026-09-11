<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_current_bulk_review.php';

const PCBR_OPERATION = 'hotel-match-pending-candidate-bridge-review-1971-20260911-v3';

function pcbr_compat(array $sourceNames, array $targetNames): array {
    $best=['score'=>0.0,'shared'=>0,'strict'=>false,'broad'=>false,'source'=>'','target'=>''];
    foreach($sourceNames as $source){$source=trim((string)$source);if($source==='')continue;foreach($targetNames as $target){$target=trim((string)$target);if($target==='')continue;
        $strict=fc_key($source,false,false)!==''&&fc_key($source,false,false)===fc_key($target,false,false);
        $broad=fc_key($source,true,true)!==''&&fc_key($source,true,true)===fc_key($target,true,true);
        [$score,$shared]=mbr_similarity($source,$target);
        if($strict||(!$best['strict']&&($broad&&!$best['broad']||($broad===$best['broad']&&($score>$best['score']||($score===$best['score']&&$shared>$best['shared']))))))$best=['score'=>round($score,6),'shared'=>$shared,'strict'=>$strict,'broad'=>$broad,'source'=>$source,'target'=>$target];
    }}
    return $best;
}

function pcbr_region_tokens(string $region): array {
    static $map = [
        'Дахаб'=>['dahab'],'Хургада'=>['hurghada'],'Шарм-эль-Шейх'=>['sharm','el','sheikh'],'Таба'=>['taba'],'Александрия'=>['alexandria'],'Матрух'=>['matrouh','matruh'],
        'Бангкок'=>['bangkok'],'Краби'=>['krabi'],'Пхукет'=>['phuket'],'Паттайя'=>['pattaya'],
        'Стамбул'=>['istanbul'],'Сиде'=>['side'],'Кемер'=>['kemer'],'Анталья'=>['antalya'],'Белек'=>['belek'],'Бодрум'=>['bodrum'],'Аланья'=>['alanya'],'Фетхие'=>['fethiye'],'Чешме'=>['cesme'],
        'Дубай'=>['dubai'],'Абу-Даби'=>['abu','dhabi'],'Аджман'=>['ajman'],'Шарджа'=>['sharjah'],'Рас-эль-Хайма'=>['ras','al','khaimah'],
        'Варадеро'=>['varadero'],'Гавана'=>['havana'],
        'Калутара'=>['kalutara'],'Хиккадува'=>['hikkaduwa'],'Унаватуна'=>['unawatuna'],'Коломбо'=>['colombo'],'Канди'=>['kandy'],
        'Фукуок'=>['phu','quoc'],'Нячанг'=>['nha','trang'],'Фантьет'=>['phan','thiet'],'Муйне'=>['mui','ne'],'Дананг'=>['da','nang'],
    ];
    return $map[$region] ?? [];
}

function pcbr_identity_tokens(string $name): array {
    $name = mb_strtolower(trim($name),'UTF-8');
    $name = preg_replace('/\b[1-5]\s*\*/u',' ',$name) ?? $name;
    $name = preg_replace('/\bdoubletree\b/u','double tree',$name) ?? $name;
    $name = preg_replace('/\s*\(\s*(?:ex|ех)\s*\.?\s*[^()]+\)\s*$/ui',' ',$name) ?? $name;
    preg_match_all('/[a-z0-9]+/u',$name,$m);
    $generic=['hotel'=>1,'hotels'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'at'=>1];
    $variants=['centre'=>'center','blu'=>'blue','heights'=>'height','villas'=>'villa','suites'=>'suite','residences'=>'residence','towers'=>'tower','sports'=>'sport'];
    $out=[];
    foreach($m[0] as $token){if(isset($generic[$token]))continue;$out[]=$variants[$token]??$token;}
    return $out;
}

function pcbr_without_tokens(array $tokens,array $drop): array {
    $set=array_fill_keys($drop,true);$out=[];foreach($tokens as $t)if(!isset($set[$t]))$out[]=$t;return $out;
}

function pcbr_strict_pair(array $sourceNames,array $targetNames,string $targetRegion): ?array {
    $region=pcbr_region_tokens($targetRegion);
    $benign=['adult','adults','only','16'];
    $critical=['annex'=>1,'beach'=>1,'garden'=>1,'north'=>1,'south'=>1];
    foreach($sourceNames as $source){$source=trim((string)$source);if($source==='')continue;$s=pcbr_identity_tokens($source);if(!$s)continue;
        foreach($targetNames as $target){$target=trim((string)$target);if($target==='')continue;$t=pcbr_identity_tokens($target);if(!$t)continue;
            $sc=[];$tc=[];foreach($s as $v)if(isset($critical[$v]))$sc[$v]=true;foreach($t as $v)if(isset($critical[$v]))$tc[$v]=true;if(array_keys($sc)!==array_keys($tc))continue;
            $drop=array_values(array_unique(array_merge($region,$benign)));
            $sn=pcbr_without_tokens($s,$drop);$tn=pcbr_without_tokens($t,$drop);
            if(count(array_unique($sn))<2||$sn!==$tn)continue;
            return ['source'=>$source,'target'=>$target,'identity_tokens'=>$sn,'removed_region_tokens'=>$region,'rule'=>'ordered_identity_plus_direct_geo_existing_anex_tourvisor'];
        }
    }
    return null;
}

function pcbr_review(PDO $db,string $operation=PCBR_OPERATION): array {
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);
    [$anexLocal,$andromedaLocal]=mbr_local_sets($db);
    $shaCountry=fc_sha_countries($db);
    $latest=[];$observationCounts=[];
    foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(string)$o['external_hotel_id'];$observationCounts[$id]=($observationCounts[$id]??0)+1;if(!isset($latest[$id]))$latest[$id]=$o;}
    $stats=['pending_examined'=>0,'observed_pending'=>0,'observation_rows_pending'=>0,'country_unknown'=>0,'prior_candidate_rows'=>0,'place_pool_rows'=>0,'anex_bridge_pool_rows'=>0,'geo_compatible_rows'=>0,'name_compatible_rows'=>0,'prepared'=>0,'observed_prepared'=>0,'strict_prepared'=>0,'observed_strict_prepared'=>0,'category_mismatch'=>0,'strict_category_mismatch'=>0,'ambiguous'=>0,'no_direct_place'=>0];
    $prepared=[];$strictPrepared=[];
    foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
        $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$observationCount=(int)($observationCounts[$external]??0);$prior=fc_evidence($r['evidence_json']??'');$source=$prior['source']??[];if(!is_array($source))$source=[];$geo=$prior['geography']??[];if(!is_array($geo))$geo=[];
        $country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country])){$stats['country_unknown']++;continue;}$stats['pending_examined']++;if($observationCount>0){$stats['observed_pending']++;$stats['observation_rows_pending']+=$observationCount;}
        $sourceNames=array_values(array_unique(array_filter([(string)($source['name']??''),(string)($source['lName']??''),(string)($obs['hotel_name']??'')],static fn($v)=>trim($v)!=='')));
        $sourcePlaces=array_values(array_unique(array_filter([(string)($source['town']??''),(string)($geo['town']??''),(string)($geo['parent']??''),(string)($obs['region_name']??'')],static fn($v)=>trim($v)!=='')));
        $pool=[];$priorIds=array_values(array_unique(array_map('intval',$prior['candidate_ids']??[])));if($priorIds)$stats['prior_candidate_rows']++;
        foreach($priorIds as $id)if(isset($hotels[$id])&&(int)$hotels[$id]['country_id']===$country&&isset($anexLocal[$id]))$pool[$id]='prior_candidate';
        $placeIds=mbr_place_pool($places,$country,$sourcePlaces);if($placeIds)$stats['place_pool_rows']++;
        foreach($placeIds as $id)if(isset($anexLocal[$id]))$pool[$id]=isset($pool[$id])?'prior_plus_place':'place_anex_bridge';
        if(!$pool)continue;$stats['anex_bridge_pool_rows']++;
        $ranked=[];
        foreach($pool as $id=>$origin){$h=$hotels[$id];$place=fc_place($sourcePlaces,[$h['region_name'],$h['subregion_name']]);if(!$place)continue;$compat=pcbr_compat($sourceNames,$names[$id]??[$h['name']]);
            if(!$compat['broad']&&!$compat['strict']&&($compat['shared']<2||$compat['score']<0.60))continue;
            $ranked[]=['id'=>(int)$id,'origin'=>$origin,'compat'=>$compat,'target'=>mbr_row_target($h)];
        }
        if(!$ranked){$stats['no_direct_place']++;continue;}$stats['geo_compatible_rows']++;$stats['name_compatible_rows']++;
        usort($ranked,static fn($a,$b)=>(int)$b['compat']['strict']<=>(int)$a['compat']['strict'] ?: (int)$b['compat']['broad']<=>(int)$a['compat']['broad'] ?: $b['compat']['score']<=>$a['compat']['score'] ?: $b['compat']['shared']<=>$a['compat']['shared'] ?: $a['id']<=>$b['id']);
        $best=$ranked[0];$second=$ranked[1]??null;$margin=$second?($best['compat']['score']-$second['compat']['score']):1.0;
        if($second&&$best['compat']['strict']===$second['compat']['strict']&&$best['compat']['broad']===$second['compat']['broad']&&$margin<0.15){$stats['ambiguous']++;continue;}
        $sourceCat=mbr_numeric_category($source);if($sourceCat===null&&$obs)$sourceCat=mbr_numeric_category($obs);$targetCat=$hotels[$best['id']]['category']===null?null:(int)$hotels[$best['id']]['category'];$catMismatch=$sourceCat!==null&&$targetCat!==null&&$sourceCat!==$targetCat;if($catMismatch)$stats['category_mismatch']++;
        $row=['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'observation_count'=>$observationCount,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'target_local_hotel_id'=>$best['id'],'target_name'=>$hotels[$best['id']]['name'],'target_region'=>$hotels[$best['id']]['region_name'],'target_subregion'=>$hotels[$best['id']]['subregion_name'],'origin'=>$best['origin'],'name'=>$best['compat'],'score_margin'=>round($margin,6),'source_category'=>$sourceCat,'target_category'=>$targetCat,'category_mismatch'=>$catMismatch,'evidence'=>'current_pending_candidate_or_place_plus_existing_anex_tourvisor_plus_direct_geo'];
        $prepared[]=$row;$stats['prepared']++;if($observationCount>0)$stats['observed_prepared']++;
        $strictPair=pcbr_strict_pair($sourceNames,$names[$best['id']]??[$hotels[$best['id']]['name']],(string)$hotels[$best['id']]['region_name']);
        if($strictPair!==null){$row['strict_identity']=$strictPair;$row['evidence']='strict_ordered_identity_plus_direct_geo_plus_existing_anex_tourvisor';$strictPrepared[]=$row;$stats['strict_prepared']++;if($observationCount>0)$stats['observed_strict_prepared']++;if($catMismatch)$stats['strict_category_mismatch']++;}
    }
    $sort=static fn($a,$b)=>(int)($b['observation_count']??0)<=>(int)($a['observation_count']??0) ?: $a['country_id']<=>$b['country_id'] ?: strcmp($a['external_id'],$b['external_id']);usort($prepared,$sort);usort($strictPrepared,$sort);
    return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'coverage'=>fc_coverage($db),'catalog_scope'=>$scope,'stats'=>$stats,'prepared_count'=>count($prepared),'strict_prepared_count'=>count($strictPrepared),'strict_prepared'=>$strictPrepared,'prepared'=>$prepared];
}
