<?php
declare(strict_types=1);

const MCR_OP = 'hotel-match-live-residual-current-review-1971-20260915-v1';
const MCR_POLICY = 'owner_exact_and_strong_20260908';

function mcr_json(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
}
function mcr_query(PDO $db, string $sql, array $args = []): array {
    $q = $db->prepare($sql);
    $q->execute($args);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function mcr_write_exclusive(string $file, array $value): string {
    $raw = mcr_json($value);
    $f = @fopen($file, 'x+b');
    if (!$f) throw new RuntimeException('exclusive_file');
    try {
        if (fwrite($f, $raw) !== strlen($raw) || !fflush($f)) throw new RuntimeException('file_write');
        if (function_exists('fsync') && !fsync($f)) throw new RuntimeException('file_sync');
        rewind($f);
        if (stream_get_contents($f) !== $raw) throw new RuntimeException('file_readback');
    } finally { fclose($f); }
    return hash('sha256', $raw);
}
function mcr_fold(string $s): string {
    $s = function_exists('mb_strtolower') ? mb_strtolower(trim($s), 'UTF-8') : strtolower(trim($s));
    $s = strtr($s, ['А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й','К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф','Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я','İ'=>'i']);
    return strtr($s, [
        'ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a',
        'ö'=>'o','ô'=>'o','ó'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','İ'=>'i',
        'ñ'=>'n','ý'=>'y','í'=>'i','ï'=>'i','ž'=>'z','š'=>'s','č'=>'c'
    ]);
}
function mcr_tokens(string $s): array {
    $s = mcr_fold($s);
    $s = preg_replace('/\s*[\(\[]\s*(?:ex|ех|former|бывш)\.?\s+[^\)\]]*[\)\]]\s*$/u', '', $s) ?? $s;
    preg_match_all('/[\p{L}\p{N}]+/u', $s, $m);
    $drop = ['hotel'=>1,'hotels'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'спа'=>1,'the'=>1,'a'=>1,'an'=>1,'by'=>1,'and'=>1,'&'=>1];
    $out = [];
    foreach ($m[0] ?? [] as $t) {
        if ($t === '' || isset($drop[$t])) continue;
        $out[$t] = true;
    }
    return array_keys($out);
}
function mcr_norm(string $s): string { return implode(' ', mcr_tokens($s)); }
function mcr_qualifiers(string $s): array {
    $meaningful = ['annex'=>1,'annexe'=>1,'beach'=>1,'garden'=>1,'gardens'=>1,'north'=>1,'south'=>1,'east'=>1,'west'=>1,'pool'=>1,'adult'=>1,'adults'=>1,'family'=>1];
    $q = [];
    foreach (mcr_tokens($s) as $t) if (isset($meaningful[$t])) $q[$t] = true;
    $out = array_keys($q); sort($out); return $out;
}
function mcr_qualifier_ok(string $a, string $b): bool { return mcr_qualifiers($a) === mcr_qualifiers($b); }
function mcr_score(string $a, string $b): float {
    $aa = array_fill_keys(mcr_tokens($a), true); $bb = array_fill_keys(mcr_tokens($b), true);
    if (!$aa || !$bb) return 0.0;
    $i = count(array_intersect_key($aa, $bb));
    $u = count($aa + $bb);
    return $u ? $i / $u : 0.0;
}
function mcr_distance(array $point, array $hotel): ?float {
    $a = $point['latitude'] ?? $point['lat'] ?? null;
    $b = $point['longitude'] ?? $point['lng'] ?? $point['lon'] ?? null;
    $c = $hotel['latitude'] ?? null; $d = $hotel['longitude'] ?? null;
    foreach ([$a,$b,$c,$d] as $v) if (!is_numeric($v)) return null;
    $a=(float)$a; $b=(float)$b; $c=(float)$c; $d=(float)$d;
    if (abs($a)>90 || abs($c)>90 || abs($b)>180 || abs($d)>180 || ($a==0.0&&$b==0.0) || ($c==0.0&&$d==0.0)) return null;
    [$a,$b,$c,$d] = array_map('deg2rad', [$a,$b,$c,$d]);
    return 6371.0 * 2.0 * asin(min(1.0, sqrt(sin(($c-$a)/2)**2 + cos($a)*cos($c)*sin(($d-$b)/2)**2)));
}
function mcr_evidence(string $raw): array {
    if ($raw === '') return [];
    try { $x = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); return is_array($x) ? $x : []; }
    catch (Throwable) { return []; }
}
function mcr_source(array $e): array { return is_array($e['source'] ?? null) ? $e['source'] : $e; }
function mcr_names(array $e): array {
    $src = mcr_source($e); $out = [];
    foreach (['name','lName','hotel_name','hotelName','original_name','originalName'] as $k) {
        foreach ([$src[$k] ?? null, $e[$k] ?? null] as $v) if (is_string($v) && trim($v) !== '') $out[trim($v)] = true;
    }
    foreach ($e['provider_bridges'] ?? [] as $b) if (is_array($b)) {
        foreach (['hotel_name','original_name'] as $k) if (is_string($b[$k] ?? null) && trim((string)$b[$k]) !== '') $out[trim((string)$b[$k])] = true;
    }
    return array_keys($out);
}
function mcr_state_key(array $e): ?string {
    $src = mcr_source($e);
    foreach ([$src['stateKey'] ?? null, $src['state_key'] ?? null, $e['stateKey'] ?? null, $e['state_key'] ?? null] as $v) {
        if ((is_int($v) || is_string($v)) && preg_match('/^[0-9]{1,12}$/D', (string)$v)) return (string)$v;
    }
    return null;
}
function mcr_country_name(array $e): ?string {
    $src = mcr_source($e);
    foreach (['countryName','country_name','stateName','state_name'] as $k) {
        foreach ([$src[$k] ?? null, $e[$k] ?? null] as $v) if (is_string($v) && trim($v) !== '') return trim($v);
    }
    return null;
}
function mcr_points(array $e): array {
    $src = mcr_source($e); $geo = is_array($e['geography'] ?? null) ? $e['geography'] : [];
    $out=[]; foreach ([$src,$geo,$e] as $x) {
        $lat=$x['latitude']??$x['lat']??null; $lon=$x['longitude']??$x['lng']??$x['lon']??null;
        if (is_numeric($lat)&&is_numeric($lon)) $out[]=['latitude'=>(float)$lat,'longitude'=>(float)$lon];
    }
    return $out;
}
function mcr_frequency(array $e): int {
    $best=0; $stack=[$e]; $keys=['frequency'=>1,'live_frequency'=>1,'seen_count'=>1,'observation_count'=>1,'count'=>1];
    while ($stack) { $x=array_pop($stack); if (!is_array($x)) continue; foreach ($x as $k=>$v) {
        if (isset($keys[$k]) && is_numeric($v)) $best=max($best,(int)$v);
        elseif (is_array($v)) $stack[]=$v;
    }} return $best;
}
function mcr_country_key(string $s): string { return preg_replace('/[^\p{L}\p{N}]+/u', ' ', mcr_fold($s)) ?? mcr_fold($s); }
function mcr_is_core8_name(string $name): bool {
    $n = trim(mcr_country_key($name));
    $set = ['египет','egypt','турция','turkey','turkiye','türkiye','таиланд','thailand','оаэ','uae','united arab emirates','объединенные арабские эмираты','вьетнам','vietnam','шри ланка','sri lanka','мальдивы','maldives','куба','cuba'];
    return in_array($n, $set, true);
}
function mcr_provider_bridges(array $e): array {
    $out=[]; foreach ($e['provider_bridges'] ?? [] as $b) if (is_array($b)) {
        $id=(string)($b['andromeda_hotel_id']??'');
        if (preg_match('/^[1-9][0-9]{0,19}$/D',$id)) $out[$id]=true;
    } return array_map('strval', array_keys($out));
}
function mcr_direct_target_guard(array $names, array $points, array $hotel): array {
    $usable = array_values(array_filter($names, fn($n) => mcr_norm((string)$n) !== ''));
    if ($usable) {
        $best = 0.0; $qual = false;
        foreach ($usable as $n) { $best = max($best, mcr_score((string)$n, (string)$hotel['name'])); if (mcr_qualifier_ok((string)$n, (string)$hotel['name'])) $qual = true; }
        if (!$qual) return ['ok'=>false,'reason'=>'direct_target_qualifier_conflict'];
        if ($best < 0.34) return ['ok'=>false,'reason'=>'direct_target_name_conflict','score'=>$best];
    }
    $dists=[]; foreach ($points as $p) { $d=mcr_distance($p,$hotel); if ($d!==null)$dists[]=$d; }
    if ($dists && max($dists)>5.0) return ['ok'=>false,'reason'=>'direct_target_coordinate_conflict_gt5km','distance_km'=>min($dists)];
    return ['ok'=>true,'distance_km'=>$dists?min($dists):null];
}
function mcr_select_candidate(array $names, int $countryId, array $points, array $hotels, array $forms, array $exact, array $tokenIndex): array {
    $sourceForms=[]; foreach ($names as $n) { $nn=mcr_norm($n); if ($nn!=='') $sourceForms[$nn]=$n; }
    if (!$sourceForms) return ['route'=>'needs_extra_evidence','reason'=>'missing_substantive_name'];
    $exactIds=[];
    foreach ($sourceForms as $n=>$raw) foreach ($exact[$countryId][$n]??[] as $id) $exactIds[(int)$id]=true;
    if ($exactIds) {
        $ids=array_keys($exactIds); $safe=[]; $blocked=[];
        foreach ($ids as $id) {
            $h=$hotels[$id]; $bestRaw=(string)$h['name'];
            $qok=false; foreach ($names as $src) foreach ($forms[$id] as $localForm) if (mcr_norm($src)===mcr_norm($localForm) && mcr_qualifier_ok($src,$localForm)) {$qok=true;$bestRaw=$localForm;break 2;}
            if (!$qok) {$blocked[$id]='qualifier_conflict'; continue;}
            $dists=[]; foreach ($points as $p) { $d=mcr_distance($p,$h); if ($d!==null)$dists[]=$d; }
            if ($dists && max($dists)>5.0) {$blocked[$id]='coordinate_conflict_gt5km';continue;}
            $safe[$id]=['distance_km'=>$dists?min($dists):null,'match_form'=>$bestRaw];
        }
        if (count($safe)===1) { $id=(int)array_key_first($safe); return ['route'=>'auto_accept_candidate','reason'=>'unique_exact_name_or_alias','target'=>$id,'score'=>1.0]+$safe[$id]; }
        if (count($safe)>1) {
            $near=[]; foreach ($safe as $id=>$v) if ($v['distance_km']!==null && $v['distance_km']<=1.0) $near[$id]=$v;
            if (count($near)===1) { $id=(int)array_key_first($near); return ['route'=>'auto_accept_candidate','reason'=>'coordinate_resolved_exact','target'=>$id,'score'=>1.0]+$near[$id]; }
            return ['route'=>'needs_extra_evidence','reason'=>'ambiguous_exact_name','candidate_count'=>count($safe),'candidate_ids'=>array_slice(array_map('intval',array_keys($safe)),0,20)];
        }
        return ['route'=>'hard_conflict','reason'=>in_array('coordinate_conflict_gt5km',$blocked,true)?'exact_name_coordinate_conflict_gt5km':'exact_name_qualifier_conflict','candidate_ids'=>array_slice(array_map('intval',array_keys($blocked)),0,20)];
    }
    $candidateIds=[]; $maxSourceTokens=0;
    foreach ($sourceForms as $n=>$raw) { $tt=mcr_tokens($n); $maxSourceTokens=max($maxSourceTokens,count($tt)); foreach ($tt as $t) foreach (array_keys($tokenIndex[$countryId][$t]??[]) as $id) $candidateIds[(int)$id]=true; }
    if ($maxSourceTokens<2 || !$candidateIds) return ['route'=>'needs_extra_evidence','reason'=>'no_strong_name_candidate'];
    $rank=[];
    foreach (array_keys($candidateIds) as $id) {
        $best=0.0; $qual=false;
        foreach ($names as $src) foreach ($forms[$id] as $localForm) { $best=max($best,mcr_score($src,$localForm)); if (mcr_qualifier_ok($src,$localForm)) $qual=true; }
        if (!$qual) continue;
        $rank[$id]=$best;
    }
    arsort($rank,SORT_NUMERIC); $ids=array_keys($rank);
    if (!$ids) return ['route'=>'needs_extra_evidence','reason'=>'qualifier_filtered_fuzzy'];
    $id=(int)$ids[0]; $best=(float)$rank[$id]; $second=isset($ids[1])?(float)$rank[$ids[1]]:0.0; $margin=$best-$second;
    $dists=[]; foreach ($points as $p) { $d=mcr_distance($p,$hotels[$id]); if ($d!==null)$dists[]=$d; }
    if ($dists && max($dists)>5.0) return ['route'=>'hard_conflict','reason'=>'fuzzy_coordinate_conflict_gt5km','target'=>$id,'score'=>$best,'margin'=>$margin,'distance_km'=>min($dists)];
    $near=$dists?min($dists):null;
    if ($best>=0.88 && $margin>=0.18 || ($best>=0.82 && $margin>=0.18 && $near!==null && $near<=1.5)) return ['route'=>'auto_accept_candidate','reason'=>'strong_fuzzy_winner','target'=>$id,'score'=>$best,'margin'=>$margin,'distance_km'=>$near];
    return ['route'=>'needs_extra_evidence','reason'=>'fuzzy_margin_or_score_insufficient','top_target'=>$id,'score'=>$best,'margin'=>$margin,'distance_km'=>$near];
}

if (getenv('MATCH_TEST_LIBRARY') === '1') return;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$operation=(string)getenv('MATCH_OPERATION_ID'); $sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if ($operation!==MCR_OP || !preg_match('/^[0-9a-f]{40}$/D',$sourceSha)) throw new RuntimeException('operation_or_source_guard');
$home=(string)getenv('HOME'); if ($home==='') throw new RuntimeException('home_missing');
$dir=$home.'/.anytoour-match/operations/'.MCR_OP;
if (!is_file($dir.'/reservation.json')) throw new RuntimeException('reservation_missing');
$res=mcr_evidence((string)file_get_contents($dir.'/reservation.json'));
if (($res['operation_id']??'')!==MCR_OP || ($res['source_sha']??'')!==$sourceSha || ($res['state']??'')!=='reserved_before_db_access') throw new RuntimeException('reservation_contract');
$root=realpath(getcwd()); if (!$root || basename($root)!=='anytoour.ru') throw new RuntimeException('root_guard');
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db(); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
try {
    $required=['andromeda_hotel_identities','catalog_hotels','catalog_countries','hotel_aliases','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions'];
    $present=mcr_query($db,'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($required),'?')).')',$required);
    $have=array_fill_keys(array_column($present,'TABLE_NAME'),true); foreach($required as $t) if(!isset($have[$t])) throw new RuntimeException('required_table_missing_'.$t);
    $hasReview=(bool)mcr_query($db,"SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='anex_review_state' AND COLUMN_NAME='anex_hotel_id' LIMIT 1");

    $countries=mcr_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id'); $coreIds=[]; $countryNameToId=[];
    foreach($countries as $c){$cid=(int)$c['id'];$key=trim(mcr_country_key((string)$c['name']));$countryNameToId[$key]=$cid;if(mcr_is_core8_name((string)$c['name']))$coreIds[$cid]=(string)$c['name'];}
    if(count($coreIds)<6) throw new RuntimeException('core8_country_dictionary_incomplete');

    $catalogRows=mcr_query($db,'SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ('.implode(',',array_fill(0,count($coreIds),'?')).') ORDER BY country_id,id',array_keys($coreIds));
    $hotels=[];$forms=[];$exact=[];$tokenIndex=[];
    foreach($catalogRows as $h){$id=(int)$h['id'];$cid=(int)$h['country_id'];$hotels[$id]=$h;$forms[$id]=[(string)$h['name']];}
    $aliases=mcr_query($db,'SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ('.implode(',',array_fill(0,count($coreIds),'?')).') ORDER BY a.hotel_id,a.id',array_keys($coreIds));
    foreach($aliases as $a){$id=(int)$a['hotel_id'];if(isset($forms[$id]))$forms[$id][]=(string)$a['alias'];}
    foreach($forms as $id=>$list){$cid=(int)$hotels[$id]['country_id'];foreach(array_values(array_unique($list)) as $raw){$n=mcr_norm($raw);if($n==='')continue;$exact[$cid][$n][]=$id;foreach(mcr_tokens($raw) as $t)$tokenIndex[$cid][$t][$id]=true;}}

    $allAnd=mcr_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' OR LEFT(supplier_namespace,9)='operator_' ORDER BY supplier_namespace,external_hotel_id");
    $acceptedAnd=[];$acceptedTypedByAnd=[];$stateCounts=[];$pendingCatalog=[];$pendingTyped=[];
    foreach($allAnd as $r){$ns=(string)$r['supplier_namespace'];$e=mcr_evidence((string)$r['evidence_json']);$local=$r['local_hotel_id']!==null?(int)$r['local_hotel_id']:null;
        if($ns==='andromeda_catalog'){
            $aid=(string)$r['external_hotel_id'];
            if($r['decision_status']==='accepted'&&$local!==null&&isset($hotels[$local])){$acceptedAnd[$aid]=$local;$sk=mcr_state_key($e);if($sk!==null)$stateCounts[$sk][(int)$hotels[$local]['country_id']] = ($stateCounts[$sk][(int)$hotels[$local]['country_id']]??0)+1;}
            elseif($r['decision_status']==='pending'&&$local===null)$pendingCatalog[]=$r;
        } elseif(str_starts_with($ns,'operator_')) {
            if($r['decision_status']==='accepted'&&$local!==null){foreach(mcr_provider_bridges($e) as $aid)$acceptedTypedByAnd[$aid][$local]=true;}
            elseif($r['decision_status']==='pending'&&$local===null)$pendingTyped[]=$r;
        }
    }
    $stateCountry=[];$stateEvidence=[];foreach($stateCounts as $sk=>$counts){arsort($counts,SORT_NUMERIC);$cid=(int)array_key_first($counts);$total=array_sum($counts);$top=(int)$counts[$cid];if(isset($coreIds[$cid])&&$top>=3&&$top/$total>=0.98)$stateCountry[$sk]=$cid;$stateEvidence[$sk]=['winner'=>$cid,'top'=>$top,'total'=>$total,'share'=>$total?$top/$total:0.0,'accepted'=>$stateCountry[$sk]??null];}

    $decisions=mcr_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL");
    $excluded=[];foreach(mcr_query($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions') as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
    $review=[];if($hasReview)foreach(mcr_query($db,'SELECT anex_hotel_id FROM anex_review_state') as $x)$review[(int)$x['anex_hotel_id']]=true;
    $anexTargets=[];foreach($decisions as $d){$a=(int)$d['anex_hotel_id'];$t=(int)$d['catalog_hotel_id'];if(!isset($excluded[$a][$t]))$anexTargets[$a][$t]=true;}
    foreach(mcr_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE enabled=1 AND scope='preview' AND approval_policy=?",[MCR_POLICY]) as $m){$a=(int)$m['anex_hotel_id'];$t=(int)$m['catalog_hotel_id'];if(!isset($excluded[$a][$t]))$anexTargets[$a][$t]=true;}
    $anexAuthority=[];foreach($anexTargets as $a=>$targets)if(count($targets)===1&&!isset($review[$a]))$anexAuthority[$a]=(int)array_key_first($targets);

    $routes=[];$reasons=[];$auto=[];$frequencyByRoute=[];
    $countryForEvidence=function(array $e)use($stateCountry,$countryNameToId,$coreIds):?int{$sk=mcr_state_key($e);if($sk!==null&&isset($stateCountry[$sk]))return $stateCountry[$sk];$cn=mcr_country_name($e);if($cn!==null){$key=trim(mcr_country_key($cn));$cid=$countryNameToId[$key]??null;if($cid!==null&&isset($coreIds[$cid]))return $cid;}return null;};
    foreach($pendingCatalog as $r){$e=mcr_evidence((string)$r['evidence_json']);$aid=(string)$r['external_hotel_id'];$freq=mcr_frequency($e);$names=mcr_names($e);$cid=$countryForEvidence($e);$row=['kind'=>'andromeda_catalog','external_hotel_id'=>$aid,'frequency'=>$freq,'names'=>array_slice($names,0,6),'state_key'=>mcr_state_key($e),'country_id'=>$cid];
        $typed=array_keys($acceptedTypedByAnd[$aid]??[]);if(count($typed)===1){$target=(int)$typed[0];if(isset($hotels[$target])&&($cid===null||(int)$hotels[$target]['country_id']===$cid)){ $guard=mcr_direct_target_guard($names,mcr_points($e),$hotels[$target]);$sel=$guard['ok']?['route'=>'auto_accept_candidate','reason'=>'accepted_operator_bridge','target'=>$target,'score'=>1.0,'distance_km'=>$guard['distance_km']]:['route'=>'hard_conflict','reason'=>$guard['reason'],'target'=>$target]+$guard; } else $sel=['route'=>'hard_conflict','reason'=>'accepted_operator_bridge_country_conflict','target'=>$target];}
        elseif(count($typed)>1)$sel=['route'=>'hard_conflict','reason'=>'accepted_operator_bridge_target_conflict','candidate_ids'=>array_map('intval',$typed)];
        elseif($cid===null)$sel=['route'=>'needs_extra_evidence','reason'=>'country_semantics_unresolved'];
        else $sel=mcr_select_candidate($names,$cid,mcr_points($e),$hotels,$forms,$exact,$tokenIndex);
        $row+=$sel;$routes[$sel['route']][]=$row;$reasons[$sel['reason']]=($reasons[$sel['reason']]??0)+1;$frequencyByRoute[$sel['route']]=($frequencyByRoute[$sel['route']]??0)+$freq;if($sel['route']==='auto_accept_candidate')$auto[]=$row;
    }
    foreach($pendingTyped as $r){$e=mcr_evidence((string)$r['evidence_json']);$ns=(string)$r['supplier_namespace'];$native=(string)$r['external_hotel_id'];$freq=mcr_frequency($e);$refs=mcr_provider_bridges($e);$accepted=[];foreach($refs as $aid)if(isset($acceptedAnd[$aid]))$accepted[$acceptedAnd[$aid]]=true;$row=['kind'=>'operator_native','supplier_namespace'=>$ns,'external_hotel_id'=>$native,'frequency'=>$freq,'andromeda_ids'=>$refs,'names'=>array_slice(mcr_names($e),0,6)];
        if($ns==='operator_5'&&ctype_digit($native)&&isset($anexAuthority[(int)$native]))$accepted[$anexAuthority[(int)$native]]=true;
        if(count($accepted)===1){$target=(int)array_key_first($accepted);$guard=isset($hotels[$target])?mcr_direct_target_guard(mcr_names($e),mcr_points($e),$hotels[$target]):['ok'=>false,'reason'=>'operator_target_outside_core8'];if($guard['ok'])$row+=['route'=>'auto_accept_candidate','reason'=>$ns==='operator_5'?'operator_native_current_authority':'operator_native_accepted_andromeda_bridge','target'=>$target,'score'=>1.0,'distance_km'=>$guard['distance_km']];else$row+=['route'=>'hard_conflict','reason'=>$guard['reason'],'target'=>$target]+$guard;}
        elseif(count($accepted)>1)$row+=['route'=>'hard_conflict','reason'=>'operator_native_target_conflict','candidate_ids'=>array_map('intval',array_keys($accepted))];
        elseif($ns==='operator_5')$row+=['route'=>'needs_anex_detail','reason'=>'anex_no_current_authority'];
        else $row+=['route'=>'needs_operator_authority','reason'=>'operator_native_no_current_local_authority'];
        $route=$row['route'];$reason=$row['reason'];$routes[$route][]=$row;$reasons[$reason]=($reasons[$reason]??0)+1;$frequencyByRoute[$route]=($frequencyByRoute[$route]??0)+$freq;if($route==='auto_accept_candidate')$auto[]=$row;
    }
    foreach($routes as &$rows)usort($rows,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0)) ?: strcmp((string)($a['external_hotel_id']??''),(string)($b['external_hotel_id']??'')));unset($rows);
    usort($auto,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0)) ?: strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
    $routeCounts=[];foreach($routes as $k=>$v)$routeCounts[$k]=count($v);ksort($routeCounts);ksort($reasons);ksort($frequencyByRoute);
    $result=['schema'=>'hotel-match-live-residual-current-review/1','operation_id'=>MCR_OP,'source_sha'=>$sourceSha,'state'=>'completed_read_only','server_current'=>true,'transaction'=>'repeatable_read_read_only','supplier_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'mapping_writes'=>0,'core8_countries'=>$coreIds,'state_country_semantics'=>$stateEvidence,'population'=>['andromeda_catalog_pending'=>count($pendingCatalog),'operator_native_pending'=>count($pendingTyped),'total'=>count($pendingCatalog)+count($pendingTyped),'catalog_hotels_considered'=>count($hotels)],'route_counts'=>$routeCounts,'route_frequency'=>$frequencyByRoute,'reason_counts'=>$reasons,'auto_accept_candidate_count'=>count($auto),'auto_accept_candidates'=>$auto,'routes'=>$routes,'no_replay'=>true,'created_at'=>gmdate('c')];
    $db->commit();
    $resultSha=mcr_write_exclusive($dir.'/result.json',$result);$readRaw=(string)file_get_contents($dir.'/result.json');$read=mcr_evidence($readRaw);$readOk=hash('sha256',$readRaw)===$resultSha&&($read['operation_id']??'')===MCR_OP&&($read['source_sha']??'')===$sourceSha&&($read['state']??'')==='completed_read_only'&&($read['db_writes']??null)===0;
    $receipt=['operation_id'=>MCR_OP,'source_sha'=>$sourceSha,'state'=>'completed_read_only','result_sha256'=>$resultSha,'readback_verified'=>$readOk,'population_total'=>$result['population']['total'],'auto_accept_candidate_count'=>count($auto),'route_counts'=>$routeCounts,'db_writes'=>0,'supplier_calls'=>0,'external_calls'=>0,'no_replay'=>true,'completed_at'=>gmdate('c')];
    mcr_write_exclusive($dir.'/receipt.json',$receipt); if(!$readOk)throw new RuntimeException('result_readback'); echo mcr_json(['operation_id'=>MCR_OP,'state'=>'completed_read_only','population'=>$result['population'],'route_counts'=>$routeCounts,'auto_accept_candidate_count'=>count($auto),'result_sha256'=>$resultSha]);
} catch(Throwable $e) {
    if($db->inTransaction())$db->rollBack();
    if(!is_file($dir.'/failure.json'))mcr_write_exclusive($dir.'/failure.json',['operation_id'=>MCR_OP,'source_sha'=>$sourceSha,'state'=>'failed_before_result','error_class'=>get_class($e),'error'=>substr($e->getMessage(),0,300),'db_writes'=>0,'supplier_calls'=>0,'external_calls'=>0,'no_replay'=>true,'failed_at'=>gmdate('c')]);
    throw $e;
}
