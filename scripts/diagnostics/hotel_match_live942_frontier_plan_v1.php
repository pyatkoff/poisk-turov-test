<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

function m942_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function m942_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}
function m942_retained_original942():?array{
    $home=rtrim(trim((string)getenv('HOME')),'/');if($home==='')return null;
    $base=$home.'/.anytoour-match/operations';
    $names=[
        'hotel-match-live942-tv-anex-refresh-1971-20260923-o0-n350-v2',
        'hotel-match-live942-tv-anex-refresh-1971-20260923-o350-n350-v2',
        'hotel-match-live942-tv-anex-refresh-1971-20260923-o700-n242-v2',
    ];
    $plans=[];$membership=null;$first=null;
    foreach($names as $name){
        $path=$base.'/'.$name.'/plan.json';
        if(!is_file($path)||is_link($path))continue;
        $plan=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
        m942_need(is_array($plan)&&($plan['state']??'')==='original_live942_ready','retained_plan_state');
        m942_need((int)($plan['frontier_count']??0)===942&&count($plan['rows']??[])===942,'retained_plan_count');
        m942_need((int)($plan['current_missing_count']??0)===927&&(int)($plan['control_written_count']??0)===15,'retained_plan_baseline');
        $ids=[];
        foreach($plan['rows'] as $row){
            $id=(int)($row['tv_hotel_id']??0);$anchors=array_values(array_unique(array_map('intval',$row['samo_hotel_ids']??[])));
            m942_need($id>0&&$anchors!==[],'retained_plan_row');
            $ids[$id]=true;
        }
        $ids=array_keys($ids);sort($ids,SORT_NUMERIC);m942_need(count($ids)===942,'retained_plan_unique');
        if($membership===null){$membership=$ids;$first=$plan;}else m942_need($membership===$ids,'retained_plan_membership_drift');
        $plans[]=$name;
    }
    if($plans===[])return null;
    m942_need(count($plans)===3,'retained_tv_plans_incomplete_'.count($plans));
    $first['cohort_source']='retained_terminal_tv_plan';
    $first['retained_plan_operations']=$plans;
    return $first;
}
function m942_rows(PDO $db,array $ids,bool $future):array{
    $out=[];foreach(array_chunk(array_values($ids),250) as $chunk){
        $ph=implode(',',array_fill(0,count($chunk),'?'));
        $futureSql=$future?' AND departure_date>=CURDATE()':'';
        $sql="SELECT hotel_id,departure_id,country_id,departure_date,nights,adults,children_count,child_ages_signature,observed_at,source
              FROM tour_price_observations WHERE hotel_id IN ($ph)$futureSql
              ORDER BY hotel_id,(source='user_search') DESC,observed_at DESC,departure_date ASC";
        $st=$db->prepare($sql);$st->execute($chunk);
        foreach($st->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$id=(int)$r['hotel_id'];if(!isset($out[$id]))$out[$id]=$r;}
    }return $out;
}
function m942_plan(PDO $db):array{
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $facts=[];$active=[];
        foreach($db->query("SELECT id,name,country_id,country_name,region_name,subregion_name,is_active FROM catalog_hotels WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            if(m942_excluded((string)$r['country_name']))continue;$id=(int)$r['id'];if($id<1)continue;$facts[$id]=$r;$active[$id]=true;
        }
        $anchors=[];
        foreach($db->query("SELECT external_hotel_id,local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY local_hotel_id,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $id=(int)$r['local_hotel_id'];$ext=trim((string)$r['external_hotel_id']);if(isset($active[$id])&&preg_match('/^[1-9][0-9]{0,15}$/D',$ext))$anchors[$id][$ext]=true;
        }
        $anex=AnyTourMatchAnexEffectiveCoverage::fromPdo($db);$anexBy=[];
        foreach(($anex['by_local']??[]) as $id=>$set)if(isset($active[(int)$id])&&$set)$anexBy[(int)$id]=true;
        $live=[];$cut=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('-30 days');
        foreach($db->query("SELECT hotel_id,MAX(last_seen_at) last_seen_at FROM tour_operator_identity_observations GROUP BY hotel_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){
            $id=(int)$r['hotel_id'];if(!isset($active[$id]))continue;try{$dt=new DateTimeImmutable((string)$r['last_seen_at'],new DateTimeZone('UTC'));}catch(Throwable){continue;}if($dt>=$cut)$live[$id]=true;
        }
        $currentMissing=[];foreach($live as $id=>$_)if(isset($anchors[$id])&&!isset($anexBy[$id]))$currentMissing[$id]=true;
        $expectedControl=[1738,2483,4063,11770,21796,49610,52326,58328,61611,65773,68983,69144,69165,123246,123308];sort($expectedControl);
        $retained=m942_retained_original942();$retainedBy=[];$cohortSource='current_reconstruction';
        if($retained!==null){
            foreach($retained['rows'] as $row)$retainedBy[(int)$row['tv_hotel_id']]=$row;
            $ids=array_keys($retainedBy);sort($ids,SORT_NUMERIC);m942_need(count($ids)===942,'retained_frontier_changed');
            $cohortSource='retained_terminal_tv_plan';
        }else{
            m942_need(count($currentMissing)===927,'current_missing_changed_'.count($currentMissing));
            $control=[];
            foreach($expectedControl as $id){
                m942_need(isset($live[$id])&&isset($anchors[$id])&&isset($anexBy[$id]),'control15_not_current_'.$id);
                $control[$id]=true;
            }
            m942_need(count(array_intersect_key($currentMissing,$control))===0,'control_overlap');
            $front=$currentMissing+$control;m942_need(count($front)===942,'original_frontier_changed_'.count($front));
            $ids=array_keys($front);
        }
        $ctx=m942_rows($db,$ids,true);$fallback=m942_rows($db,array_values(array_diff($ids,array_keys($ctx))),false);
        $depNames=[];foreach($db->query("SELECT id,name FROM catalog_departures WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$did=(int)$r['id'];$name=trim((string)$r['name']);if($did>0&&$name!=='')$depNames[$did]=$name;}
        $deps=[];foreach($db->query("SELECT country_id,departure_id FROM catalog_departure_countries WHERE is_active=1 ORDER BY country_id,(departure_id=1) DESC,departure_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$countryId=(int)$r['country_id'];if(!isset($deps[$countryId]))$deps[$countryId]=(int)$r['departure_id'];}
        $rows=[];$missing=0;$past=0;$departureNameMissing=0;
        foreach($ids as $id){
            $ret=$retainedBy[$id]??null;$f=$facts[$id]??[];
            $countryId=(int)($ret['country_id']??$f['country_id']??0);m942_need($countryId>0,'cohort_country_missing_'.$id);
            $c=$ret??($ctx[$id]??$fallback[$id]??null);$source=$ret!==null?'retained_original942_context':'future_observation';
            if($c===null){$missing++;$source='catalog_fallback';$date=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');$c=['departure_id'=>$deps[$countryId]??1,'country_id'=>$countryId,'departure_date'=>$date,'nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''];}
            elseif($ret===null&&!isset($ctx[$id])){$past++;$source='latest_observation_fallback';$date=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');$c['departure_date']=$date;}
            $departureId=(int)($c['departure_id']??0);m942_need($departureId>0,'departure_id_missing_'.$id);
            $departureName=trim((string)($c['departure_name']??($depNames[$departureId]??'')));if($departureName==='')$departureNameMissing++;
            $retainedAnchors=array_values(array_unique(array_map('intval',$ret['samo_hotel_ids']??[])));sort($retainedAnchors,SORT_NUMERIC);
            $currentAnchors=array_map('intval',array_keys($anchors[$id]??[]));sort($currentAnchors,SORT_NUMERIC);
            $rowAnchors=$retainedAnchors!==[]?$retainedAnchors:$currentAnchors;m942_need($rowAnchors!==[],'cohort_anchor_missing_'.$id);
            $rows[]=[
                'tv_hotel_id'=>$id,'hotel_name'=>(string)($f['name']??$ret['hotel_name']??''),'country_id'=>$countryId,'country'=>(string)($f['country_name']??$ret['country']??''),'region'=>(string)($f['region_name']??$ret['region']??''),'subregion'=>(string)($f['subregion_name']??$ret['subregion']??''),
                'samo_hotel_ids'=>$rowAnchors,'context_source'=>$source,
                'departure_id'=>$departureId,'departure_name'=>$departureName,'departure_date'=>(string)($c['departure_date']??''),'nights'=>max(1,min(28,(int)($c['nights']??7))),'adults'=>max(1,min(6,(int)($c['adults']??2))),
                'children_count'=>max(0,min(3,(int)($c['children_count']??0))),'child_ages_signature'=>(string)($c['child_ages_signature']??'')
            ];
        }
        usort($rows,static fn($a,$b)=>[$a['country_id'],$a['departure_id'],$a['departure_date'],$a['nights'],$a['tv_hotel_id']]<=>[$b['country_id'],$b['departure_id'],$b['departure_date'],$b['nights'],$b['tv_hotel_id']]);
        $db->rollBack();
        $currentControl=0;foreach($expectedControl as $id)if(isset($live[$id])&&isset($anchors[$id])&&isset($anexBy[$id]))$currentControl++;
        return ['state'=>'original_live942_ready','generated_at_utc'=>gmdate('c'),'frontier_count'=>count($rows),'current_missing_count'=>927,'control_written_count'=>15,'control_written_ids'=>$expectedControl,'current_missing_now_count'=>count($currentMissing),'control_current_now_count'=>$currentControl,'cohort_source'=>$cohortSource,'departure_name_missing_count'=>$departureNameMissing,'missing_context_fallback'=>$missing,'past_context_shifted'=>$past,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')==='--self-test'){m942_need(m942_excluded('Россия')&&!m942_excluded('Turkey'),'exclude');echo "MATCH_LIVE942_PLAN_V1_SELFTEST_OK\n";exit;}
    m942_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');m942_need(is_dir($root),'root');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    echo json_encode(m942_plan(v2_data_db()),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}
