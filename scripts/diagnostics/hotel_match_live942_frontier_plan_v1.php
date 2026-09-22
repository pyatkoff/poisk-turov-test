<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_anex_effective_coverage.php';

function m942_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function m942_excluded(string $country):bool{return preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim($country))===1;}
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
        m942_need(count($currentMissing)===927,'current_missing_changed_'.count($currentMissing));
        $writerDigest='a46e6c7ecc7e4d57eb5820bdb3f89e5489a874a75597ed0e42b84549b10ad7b1';
        $control=[];$st=$db->prepare("SELECT DISTINCT catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1 AND mapping_digest=? ORDER BY catalog_hotel_id");$st->execute([$writerDigest]);
        foreach($st->fetchAll(PDO::FETCH_COLUMN)?:[] as $raw){$id=(int)$raw;if(isset($live[$id])&&isset($anchors[$id]))$control[$id]=true;}
        $expectedControl=[1738,2483,4063,11770,21796,49610,52326,58328,61611,65773,68983,69144,69165,123246,123308];sort($expectedControl);
        $gotControl=array_keys($control);sort($gotControl);
        m942_need($gotControl===$expectedControl,'control15_changed');
        m942_need(count(array_intersect_key($currentMissing,$control))===0,'control_overlap');
        $front=$currentMissing+$control;
        m942_need(count($front)===942,'original_frontier_changed_'.count($front));
        $ids=array_keys($front);$ctx=m942_rows($db,$ids,true);$fallback=m942_rows($db,array_values(array_diff($ids,array_keys($ctx))),false);
        $deps=[];foreach($db->query("SELECT country_id,departure_id FROM catalog_departure_countries WHERE is_active=1 ORDER BY country_id,(departure_id=1) DESC,departure_id")->fetchAll(PDO::FETCH_ASSOC)?:[] as $r){$c=(int)$r['country_id'];if(!isset($deps[$c]))$deps[$c]=(int)$r['departure_id'];}
        $rows=[];$missing=0;$past=0;
        foreach($ids as $id){
            $f=$facts[$id];$c=$ctx[$id]??$fallback[$id]??null;$source='future_observation';
            if($c===null){$missing++;$source='catalog_fallback';$date=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');$c=['departure_id'=>$deps[(int)$f['country_id']]??1,'country_id'=>(int)$f['country_id'],'departure_date'=>$date,'nights'=>7,'adults'=>2,'children_count'=>0,'child_ages_signature'=>''];}
            elseif(!isset($ctx[$id])){$past++;$source='latest_observation_fallback';$date=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+14 days')->format('Y-m-d');$c['departure_date']=$date;}
            $rows[]=[
                'tv_hotel_id'=>$id,'hotel_name'=>(string)$f['name'],'country_id'=>(int)$f['country_id'],'country'=>(string)$f['country_name'],'region'=>(string)$f['region_name'],'subregion'=>(string)$f['subregion_name'],
                'samo_hotel_ids'=>array_map('intval',array_keys($anchors[$id])),'context_source'=>$source,
                'departure_id'=>(int)$c['departure_id'],'departure_date'=>(string)$c['departure_date'],'nights'=>max(1,min(28,(int)$c['nights'])),'adults'=>max(1,min(6,(int)$c['adults'])),
                'children_count'=>max(0,min(3,(int)$c['children_count'])),'child_ages_signature'=>(string)$c['child_ages_signature']
            ];
        }
        usort($rows,static fn($a,$b)=>[$a['country_id'],$a['departure_id'],$a['departure_date'],$a['nights'],$a['tv_hotel_id']]<=>[$b['country_id'],$b['departure_id'],$b['departure_date'],$b['nights'],$b['tv_hotel_id']]);
        $db->rollBack();
        return ['state'=>'original_live942_ready','generated_at_utc'=>gmdate('c'),'frontier_count'=>count($rows),'current_missing_count'=>count($currentMissing),'control_written_count'=>count($control),'writer_mapping_digest'=>$writerDigest,'missing_context_fallback'=>$missing,'past_context_shifted'=>$past,'rows'=>$rows,'database_writes'=>0,'mapping_writes'=>0];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
if(($argv[1]??'')==='--self-test'){m942_need(m942_excluded('Россия')&&!m942_excluded('Turkey'),'exclude');echo "MATCH_LIVE942_PLAN_V1_SELFTEST_OK\n";exit;}
m942_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');m942_need(is_dir($root),'root');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
echo json_encode(m942_plan(v2_data_db()),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
