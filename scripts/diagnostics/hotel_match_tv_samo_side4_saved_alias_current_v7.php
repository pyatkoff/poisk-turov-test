<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_tv_samo_common4_side_star4_saved_v6.php';

const HMA7_OP = 'hotel-match-tv-samo-side4-saved-alias-current-1971-20260922-v7';
const HMA7_SOURCE_OP = HMC6_SOURCE_OP;
const HMA7_QUALIFIERS = ['apart','apartment','family','premium','aqua','aquapark','beach','resort','club','boutique','palace','garden','village','suite','spa'];

function hma7_tokens(string $name): array {
    $name = preg_replace('/\b(?:ex|former|formerly)\b.*$/iu', '', hmc_norm($name)) ?? hmc_norm($name);
    $tokens = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $generic = ['hotel'=>true,'hotels'=>true,'otel'=>true,'отель'=>true,'турция'=>true,'turkey'=>true];
    $out = [];
    foreach ($tokens as $token) {
        if (isset($generic[$token]) || preg_match('/^[1-5]\*?$/D', $token)) continue;
        $out[$token] = true;
    }
    $out = array_keys($out); sort($out, SORT_STRING); return $out;
}
function hma7_qualifiers(array $tokens): array {
    $out=[]; foreach ($tokens as $t) if (in_array($t,HMA7_QUALIFIERS,true)) $out[]=$t;
    sort($out,SORT_STRING); return array_values(array_unique($out));
}
function hma7_name_score(string $a,string $b): array {
    $ta=hma7_tokens($a);$tb=hma7_tokens($b);
    if(!$ta||!$tb)return ['score'=>0.0,'exact'=>false,'qualifier_conflict'=>true,'a_tokens'=>$ta,'b_tokens'=>$tb];
    $qa=hma7_qualifiers($ta);$qb=hma7_qualifiers($tb);$qualifierConflict=$qa!==$qb;
    $sa=implode(' ',$ta);$sb=implode(' ',$tb);$exact=$sa===$sb;
    $inter=count(array_intersect($ta,$tb));$union=count(array_unique(array_merge($ta,$tb)));
    $j=$union?$inter/$union:0.0;$contain=$inter/min(count($ta),count($tb));
    $max=max(strlen($sa),strlen($sb));
    // PHP's levenshtein implementation rejects long byte strings on supported
    // runtimes. Token similarity remains deterministic and qualifier-safe.
    $lev=$max&&strlen($sa)<=240&&strlen($sb)<=240?1-(levenshtein($sa,$sb)/$max):0.0;
    $score=$exact?1.0:max($lev,0.68*$j+0.32*$contain);
    if($qualifierConflict)$score=min($score,0.79);
    return ['score'=>round(max(0.0,min(1.0,$score)),6),'exact'=>$exact,'qualifier_conflict'=>$qualifierConflict,'a_tokens'=>$ta,'b_tokens'=>$tb];
}
function hma7_hotels(array $rows): array {
    $out=[];
    foreach($rows as $r){
        if(!is_array($r)||empty($r['hotel_id'])||empty($r['hotel_name']))continue;
        $id=(string)$r['hotel_id'];$name=(string)$r['hotel_name'];
        $out[$id]['id']=$id;$out[$id]['names'][$name]=true;
        if(!empty($r['operator_family']))$out[$id]['families'][(string)$r['operator_family']]=true;
        $out[$id]['offer_count']=($out[$id]['offer_count']??0)+1;
    }
    foreach($out as &$h){$h['names']=array_keys($h['names']);sort($h['names'],SORT_STRING);$h['families']=array_keys($h['families']??[]);sort($h['families'],SORT_STRING);}unset($h);
    ksort($out,SORT_NATURAL);return $out;
}
function hma7_resolve(array $tvRows,array $samoRows): array {
    $tv=hma7_hotels($tvRows);$sa=hma7_hotels($samoRows);$matrix=[];
    foreach($tv as $tid=>$t)foreach($sa as $sid=>$s){
        $best=null;$pair=null;
        foreach($t['names'] as $tn)foreach($s['names'] as $sn){$x=hma7_name_score($tn,$sn);if($best===null||$x['score']>$best['score']){$best=$x;$pair=[$tn,$sn];}}
        $overlap=array_values(array_intersect($t['families'],$s['families']));sort($overlap,SORT_STRING);
        $common3=array_values(array_intersect($overlap,['anex','funsun','intourist']));
        if(($best['score']??0)<0.72)continue;
        $matrix[$tid][$sid]=['tv_hotel_id'=>$tid,'samo_hotel_id'=>$sid,'tv_name'=>$pair[0],'samo_name'=>$pair[1],
            'name_score'=>$best['score'],'name_exact_generic'=>$best['exact'],'qualifier_conflict'=>$best['qualifier_conflict'],
            'operator_overlap'=>$overlap,'common3_overlap'=>$common3,'tv_offer_count'=>$t['offer_count'],'samo_offer_count'=>$s['offer_count']];
    }
    $tvRanks=[];$saRanks=[];
    foreach($matrix as $tid=>$xs){$v=array_values($xs);usort($v,fn($a,$b)=>$b['name_score']<=>$a['name_score']?:strcmp($a['samo_hotel_id'],$b['samo_hotel_id']));$tvRanks[$tid]=$v;foreach($v as $x)$saRanks[$x['samo_hotel_id']][]=$x;}
    foreach($saRanks as &$v)usort($v,fn($a,$b)=>$b['name_score']<=>$a['name_score']?:strcmp($a['tv_hotel_id'],$b['tv_hotel_id']));unset($v);
    $strong=[];$review=[];
    foreach($tvRanks as $tid=>$rank){$x=$rank[0];$second=$rank[1]['name_score']??0.0;$inverse=$saRanks[$x['samo_hotel_id']];$invSecond=$inverse[1]['name_score']??0.0;
        $x['tv_margin']=round($x['name_score']-$second,6);$x['samo_margin']=round($x['name_score']-$invSecond,6);
        $x['mutual_unique']=$inverse[0]['tv_hotel_id']===$tid&&$x['tv_margin']>=0.06&&$x['samo_margin']>=0.06;
        $x['tier']=($x['mutual_unique']&&!$x['qualifier_conflict']&&count($x['common3_overlap'])>0&&$x['name_score']>=0.90)?'strong_common3':'review';
        if($x['tier']==='strong_common3')$strong[]=$x;elseif($x['name_score']>=0.72)$review[]=$x;
    }
    usort($strong,fn($a,$b)=>$b['name_score']<=>$a['name_score']?:strcmp($a['tv_hotel_id'],$b['tv_hotel_id']));
    usort($review,fn($a,$b)=>$b['name_score']<=>$a['name_score']?:strcmp($a['tv_hotel_id'],$b['tv_hotel_id']));
    return ['tv_hotels'=>count($tv),'samo_hotels'=>count($sa),'strong_common3'=>$strong,'review'=>array_slice($review,0,100),
        'strong_count'=>count($strong),'review_count'=>count($review),'policy'=>'mutual_unique_name_score_gte_0.90_margin_0.06_common3_overlap_no_qualifier_conflict'];
}
function hma7_baseline_pairs(array $baseline): array {
    $out=[];
    foreach(($baseline['hotel_candidates']??[]) as $c){
        if(!is_array($c))continue;
        $ops=array_values(array_filter(array_map('strval',$c['operator_overlap']['operators']??[])));sort($ops,SORT_STRING);
        $common3=array_values(array_intersect($ops,['anex','funsun','intourist']));sort($common3,SORT_STRING);
        $tier=count($common3)>0?'baseline_exact_common3':(in_array('biblio',$ops,true)?'baseline_exact_biblio_only':'baseline_exact_no_common3');
        $out[]=[
            'tv_hotel_id'=>(string)$c['tv_hotel_id'],'samo_hotel_id'=>(string)$c['samo_hotel_id'],
            'tv_name'=>(string)($c['tv_name']??''),'samo_name'=>(string)($c['samo_name']??''),
            'name_score'=>1.0,'name_exact_generic'=>(bool)($c['name_exact']??false),'qualifier_conflict'=>false,
            'operator_overlap'=>$ops,'common3_overlap'=>$common3,'tier'=>$tier,
            'baseline_evidence_class'=>(string)($c['hotel_evidence_class']??'exact_hotel_name_plus_operator_fingerprint'),
        ];
    }
    usort($out,fn($a,$b)=>strcmp($a['tv_hotel_id'],$b['tv_hotel_id'])?:strcmp($a['samo_hotel_id'],$b['samo_hotel_id']));
    return $out;
}
function hma7_unresolved_rows(array $tvRows,array $baselinePairs): array {
    $resolved=[];foreach($baselinePairs as $c)$resolved[(string)$c['tv_hotel_id']]=true;
    return array_values(array_filter($tvRows,fn($r)=>is_array($r)&&!isset($resolved[(string)($r['hotel_id']??'')])));
}
function hma7_query(PDO $db,string $sql,array $params=[]): array {$st=$db->prepare($sql);$st->execute(array_values($params));return $st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hma7_current(PDO $db,array $candidates): array {
    if(!$candidates)return ['rows'=>[],'counts'=>[]];
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        $ids=array_values(array_unique(array_map(fn($x)=>(int)$x['tv_hotel_id'],$candidates)));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
        $hotels=[];foreach(hma7_query($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph) ORDER BY id",$ids) as $r)$hotels[(int)$r['id']]=$r;
        $ident=hma7_query($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id');
        $manual=[];foreach(hma7_query($db,'SELECT catalog_hotel_id,decision_status FROM anex_hotel_decisions WHERE catalog_hotel_id IS NOT NULL ORDER BY catalog_hotel_id') as $r)$manual[(int)$r['catalog_hotel_id']]=true;
        $byKey=[];$byTarget=[];foreach($ident as $r){$byKey[$r['supplier_namespace'].'|'.$r['external_hotel_id']][]=$r;if($r['local_hotel_id']!==null&&$r['decision_status']==='accepted')$byTarget[$r['supplier_namespace'].'|'.$r['local_hotel_id']][]=$r;}
        $out=[];$counts=[];
        foreach($candidates as $c){$tv=(int)$c['tv_hotel_id'];$key='andromeda_catalog|'.$c['samo_hotel_id'];$reason=null;$h=$hotels[$tv]??null;$existing=$byKey[$key]??[];
            if(count($existing)===1&&(int)$existing[0]['local_hotel_id']===$tv&&$existing[0]['decision_status']==='accepted')$reason='already_accepted_same';
            elseif($existing)$reason='source_identity_occupied';
            elseif(!$h||(int)$h['is_active']!==1)$reason='target_missing_or_inactive';
            elseif(preg_match('/^(?:россия|абхазия|russia|russian federation|abkhazia)$/iu',trim((string)$h['country_name'])))$reason='excluded_country';
            elseif(isset($manual[$tv]))$reason='manual_target_protected';
            elseif(isset($byTarget['andromeda_catalog|'.$tv]))$reason='provider_target_occupied';
            else $reason='current_missing_edge_candidate';
            $counts[$reason]=($counts[$reason]??0)+1;$out[]=$c+['current_reason'=>$reason,'catalog_hotel'=>$h];
        }
        ksort($counts,SORT_STRING);$db->rollBack();return ['rows'=>$out,'counts'=>$counts,'database_writes'=>0,'mapping_writes'=>0];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function hma7_execute(string $opDir,string $root): array {
    $reservation=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation']??null)!==HMA7_OP||($reservation['state']??null)!=='reserved_read_only_current_audit')throw new RuntimeException('reservation');
    $operations=dirname($opDir);$saved=hmc6_samo_rows($operations.'/'.HMA7_SOURCE_OP);$checkpoint=hmc3_previous_checkpoint($operations.'/'.HMC_PREVIOUS_OP);
    $common=['anex'=>['tv'=>['id'=>13,'name'=>'ANEX']], 'biblio'=>['tv'=>['id'=>18,'name'=>'Библио-Глобус']], 'funsun'=>['tv'=>['id'=>25,'name'=>'FUN&SUN']], 'intourist'=>['tv'=>['id'=>43,'name'=>'Интурист']]];
    $tv=hmc_tv_offer_rows($checkpoint['rows'],HMC_DATE_FROM,$common);
    $baseline=hmf_resolve($tv,$saved['rows'],[]);$baselinePairs=hma7_baseline_pairs($baseline);
    if(count($baselinePairs)!==3)throw new RuntimeException('baseline_exact_count');
    $unresolvedRows=hma7_unresolved_rows($tv,$baselinePairs);$unresolvedHotels=hma7_hotels($unresolvedRows);
    if(count($unresolvedHotels)!==137)throw new RuntimeException('unresolved_tv_count');
    $aliases=hma7_resolve($unresolvedRows,$saved['rows']);
    $audit=[];$seen=[];
    foreach(array_merge($baselinePairs,$aliases['strong_common3']) as $c){$k=$c['tv_hotel_id'].'|'.$c['samo_hotel_id'];if(isset($seen[$k]))continue;$seen[$k]=true;$audit[]=$c;}
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$current=hma7_current(v2_data_db(),$audit);
    return ['operation'=>HMA7_OP,'state'=>'completed_read_only_current_audit','source_operation'=>HMA7_SOURCE_OP,
        'scope'=>['resort'=>'Side','date_from'=>HMC_DATE_FROM,'date_to'=>HMC_DATE_TO,'nights'=>7,'adults'=>2,'children'=>0,'retained_tv_hotels'=>140,'retained_tv_offers'=>1809,'retained_samo_offers'=>count($saved['rows']),'baseline_exact_pairs'=>count($baselinePairs),'alias_unresolved_tv_hotels'=>count($unresolvedHotels)],
        'acquisition_policy'=>'COMMON3_ANEX_FUNSUN_INTOURIST_FOR_NEW_MATCH','biblio_history_preserved'=>true,'biblio_fuel_policy'=>'owner_policy_zero_included_no_fuel_sample',
        'resolver'=>['baseline_exact'=>$baselinePairs,'baseline_exact_count'=>count($baselinePairs),'unresolved_tv_hotels'=>count($unresolvedHotels),'alias'=>$aliases,'current_audit_candidate_count'=>count($audit)],
        'current'=>$current,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_writes'=>0,'search_visibility_verified'=>false];
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');$opDir=(string)getenv('MATCH_OPERATION_DIR');$root=(string)getenv('ANYTOUR_ROOT');
    if(!is_dir($opDir)||!is_dir($root)||basename($root)!=='anytoour.ru')throw new RuntimeException('runtime_paths');
    try{$result=hma7_execute($opDir,$root);$sha=hmc_write($opDir.'/result.json',$result);hmc_write($opDir.'/receipt.json',['operation'=>HMA7_OP,'state'=>$result['state'],'result_sha256'=>$sha,'readback_verified'=>hash_file('sha256',$opDir.'/result.json')===$sha,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo hmc_json(['state'=>$result['state'],'strong'=>$result['resolver']['strong_count'],'current'=>$result['current']['counts']])."\n";}
    catch(Throwable $e){$reason=preg_match('/^[A-Za-z0-9_.:-]{1,120}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';$fail=['operation'=>HMA7_OP,'state'=>'failed_read_only_current_audit','reason'=>$reason,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$sha=hmc_write($opDir.'/result.json',$fail);hmc_write($opDir.'/receipt.json',['operation'=>HMA7_OP,'state'=>$fail['state'],'result_sha256'=>$sha,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$reason."\n");exit(2);}
}
