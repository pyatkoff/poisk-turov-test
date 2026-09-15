<?php
declare(strict_types=1);

const MCR_OP = 'hotel-match-live-residual-current-review-1971-20260915-v4';
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

function mcr_geo_latin(string $s): string {
    $s=mcr_fold($s);
    $s=strtr($s,[
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
        'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya',
        'ь'=>'','ъ'=>''
    ]);
    $s=preg_replace('/[^a-z0-9]+/u',' ',strtolower($s))??strtolower($s);
    return trim(preg_replace('/\s+/',' ',$s)??$s);
}
function mcr_geo_suffixes(array $hotel): array {
    $out=[];
    foreach (['region_name','subregion_name'] as $k) {
        $raw=trim((string)($hotel[$k]??''));
        if($raw==='')continue;
        $g=mcr_geo_latin($raw);
        if($g==='')continue;
        $gt=preg_split('/\s+/', $g)?:[];
        $meaningful=['annex'=>1,'annexe'=>1,'beach'=>1,'garden'=>1,'gardens'=>1,'north'=>1,'south'=>1,'east'=>1,'west'=>1,'pool'=>1,'adult'=>1,'adults'=>1,'family'=>1];
        $bad=false;foreach($gt as $t)if(isset($meaningful[$t])){$bad=true;break;}
        if(!$bad)$out[$g]=true;
    }
    return array_keys($out);
}
function mcr_geo_strip_form(string $raw,array $hotel): ?string {
    $n=mcr_norm($raw); if($n==='')return null;
    $latin=mcr_geo_latin($n); if($latin==='')return null;
    foreach(mcr_geo_suffixes($hotel) as $g){
        if($latin===$g)continue;
        $suffix=' '.$g;
        if(str_ends_with($latin,$suffix)){
            $base=trim(substr($latin,0,-strlen($suffix)));
            if($base!=='' && count(preg_split('/\s+/',$base)?:[])>=1)return $base;
        }
    }
    return null;
}
function mcr_geo_exact_index(array $hotels,array $forms): array {
    $idx=[];
    foreach($forms as $id=>$list){
        if(!isset($hotels[$id]))continue;
        $cid=(int)$hotels[$id]['country_id'];
        foreach(array_unique($list) as $raw){
            $base=mcr_geo_strip_form((string)$raw,$hotels[$id]);
            if($base!==null)$idx[$cid][$base][(int)$id]=true;
        }
    }
    return $idx;
}

function mcr_select_candidate(array $names, int $countryId, array $points, array $hotels, array $forms, array $exact, array $tokenIndex, array $geoExact = []): array {
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
    $geoIds=[];
    foreach($sourceForms as $n=>$raw){
        $key=mcr_geo_latin($n);
        foreach(array_keys($geoExact[$countryId][$key]??[]) as $id)$geoIds[(int)$id]=true;
    }
    if($geoIds){
        $safe=[];
        foreach(array_keys($geoIds) as $id){
            if(!isset($hotels[$id]))continue;
            $qok=false;
            foreach($names as $srcName)foreach($forms[$id] as $localForm){
                $base=mcr_geo_strip_form((string)$localForm,$hotels[$id]);
                if($base!==null && $base===mcr_geo_latin(mcr_norm((string)$srcName)) && mcr_qualifier_ok((string)$srcName,(string)$localForm)){$qok=true;break 2;}
            }
            if(!$qok)continue;
            $dists=[];foreach($points as $p){$d=mcr_distance($p,$hotels[$id]);if($d!==null)$dists[]=$d;}
            if($dists&&max($dists)>5.0)continue;
            $safe[$id]=['distance_km'=>$dists?min($dists):null];
        }
        if(count($safe)===1){
            $id=(int)array_key_first($safe);
            return ['route'=>'auto_accept_candidate','reason'=>'unique_exact_name_plus_own_geography','target'=>$id,'score'=>1.0]+$safe[$id];
        }
        if(count($safe)>1)return ['route'=>'needs_extra_evidence','reason'=>'ambiguous_geo_suffix_exact','candidate_count'=>count($safe),'candidate_ids'=>array_slice(array_map('intval',array_keys($safe)),0,20)];
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
    if ($best>=0.88 && $margin>=0.18 || ($best>=0.82 && $margin>=0.12 && $near!==null && $near<=1.0)) return ['route'=>'auto_accept_candidate','reason'=>'strong_fuzzy_winner','target'=>$id,'score'=>$best,'margin'=>$margin,'distance_km'=>$near];
    return ['route'=>'needs_extra_evidence','reason'=>'fuzzy_margin_or_score_insufficient','top_target'=>$id,'score'=>$best,'margin'=>$margin,'distance_km'=>$near];
}

if(getenv('MATCH_TEST_LIBRARY')==='1')return;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$operation=(string)getenv('MATCH_OPERATION_ID');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');
if($operation!==MCR_OP||!preg_match('/^[0-9a-f]{40}$/D',$sourceSha))throw new RuntimeException('operation_or_source_guard');
$home=(string)getenv('HOME');if($home==='')throw new RuntimeException('home_missing');$dir=$home.'/.anytoour-match/operations/'.MCR_OP;$reservation=mcr_evidence((string)@file_get_contents($dir.'/reservation.json'));if(($reservation['operation_id']??'')!==MCR_OP||($reservation['source_sha']??'')!==$sourceSha||($reservation['state']??'')!=='reserved_before_db_access')throw new RuntimeException('reservation_contract');
$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
try{
    $coreRows=mcr_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id');$coreIds=[];foreach($coreRows as $c)if(mcr_is_core8_name((string)$c['name']))$coreIds[(int)$c['id']]=(string)$c['name'];if(count($coreIds)<6)throw new RuntimeException('core8_incomplete');
    $catalogRows=mcr_query($db,'SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ('.implode(',',array_fill(0,count($coreIds),'?')).') ORDER BY country_id,id',array_keys($coreIds));$hotels=[];$forms=[];$exact=[];$tokenIndex=[];foreach($catalogRows as $h){$id=(int)$h['id'];$hotels[$id]=$h;$forms[$id]=[(string)$h['name']];}
    $aliases=mcr_query($db,'SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ('.implode(',',array_fill(0,count($coreIds),'?')).') ORDER BY a.hotel_id,a.id',array_keys($coreIds));foreach($aliases as $a){$id=(int)$a['hotel_id'];if(isset($forms[$id]))$forms[$id][]=(string)$a['alias'];}
    foreach($forms as $id=>$list){$cid=(int)$hotels[$id]['country_id'];foreach(array_values(array_unique($list)) as $raw){$n=mcr_norm($raw);if($n==='')continue;$exact[$cid][$n][]=$id;foreach(mcr_tokens($raw) as $t)$tokenIndex[$cid][$t][$id]=true;}}
    $geoExact=mcr_geo_exact_index($hotels,$forms);
    $stateCountry=[];$counts=[];$accepted=mcr_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL ORDER BY external_hotel_id");foreach($accepted as $r){$local=(int)$r['local_hotel_id'];if(!isset($hotels[$local]))continue;$e=mcr_evidence((string)$r['evidence_json']);$sk=mcr_state_key($e);if($sk!==null)$counts[$sk][(int)$hotels[$local]['country_id']]=($counts[$sk][(int)$hotels[$local]['country_id']]??0)+1;}foreach($counts as $sk=>$c){arsort($c,SORT_NUMERIC);$cid=(int)array_key_first($c);$total=array_sum($c);if(isset($coreIds[$cid])&&(int)$c[$cid]>=3&&(int)$c[$cid]/$total>=0.98)$stateCountry[$sk]=['country_id'=>$cid,'winner'=>(int)$c[$cid],'total'=>$total,'share'=>(int)$c[$cid]/$total];}
    $identityRows=mcr_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' OR supplier_namespace LIKE 'operator\\_%' ORDER BY supplier_namespace,external_hotel_id");
    $catalogById=[];$acceptedTypedByAnd=[];$pending=[];$operatorRows=[];foreach($identityRows as $r){$ns=(string)$r['supplier_namespace'];$id=(string)$r['external_hotel_id'];$e=mcr_evidence((string)$r['evidence_json']);if($ns==='andromeda_catalog')$catalogById[$id]=$r;if($r['decision_status']==='accepted'&&$r['local_hotel_id']!==null&&$ns!=='andromeda_catalog')foreach(mcr_provider_bridges($e) as $aid)$acceptedTypedByAnd[$aid][]=[$ns,$id,(int)$r['local_hotel_id'],$r['evidence_sha256']];if($r['decision_status']==='pending'&&$r['local_hotel_id']===null){$pending[]=$r;if($ns!=='andromeda_catalog')$operatorRows[]=$r;}}
    $anexAuthority=[];foreach(mcr_query($db,"SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL ORDER BY anex_hotel_id") as $r){$aid=(string)$r['anex_hotel_id'];$target=(int)$r['catalog_hotel_id'];$anexAuthority[$aid][$target]=true;}foreach(mcr_query($db,"SELECT m.anex_hotel_id,m.catalog_hotel_id FROM anex_hotel_search_mappings m WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy=? AND NOT EXISTS(SELECT 1 FROM anex_hotel_decisions d WHERE d.anex_hotel_id=m.anex_hotel_id) AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id) ORDER BY m.anex_hotel_id,m.catalog_hotel_id",[MCR_POLICY]) as $r){$aid=(string)$r['anex_hotel_id'];$target=(int)$r['catalog_hotel_id'];$anexAuthority[$aid][$target]=true;}
    $routes=[];$reason=[];$routeFrequency=[];$auto=[];
    foreach($pending as $r){$ns=(string)$r['supplier_namespace'];$id=(string)$r['external_hotel_id'];$e=mcr_evidence((string)$r['evidence_json']);$names=mcr_names($e);$freq=mcr_frequency($e);$item=['kind'=>$ns==='andromeda_catalog'?'andromeda_catalog':'operator_native','supplier_namespace'=>$ns,'external_hotel_id'=>$id,'frequency'=>$freq,'names'=>$names];
        if($ns==='andromeda_catalog'){$sk=mcr_state_key($e);$country=mcr_country_name($e);$cid=$sk!==null?($stateCountry[$sk]['country_id']??null):null;if($cid===null&&$country!==null)foreach($coreIds as $ccid=>$cname)if(mcr_country_key($cname)===mcr_country_key($country)){$cid=$ccid;break;}$item['state_key']=$sk;$item['country_id']=$cid;if($cid===null){$sel=['route'=>'needs_extra_evidence','reason'=>'country_semantics_unresolved'];}
        else{$bridges=$acceptedTypedByAnd[$id]??[];$unique=[];foreach($bridges as $b)$unique[(int)$b[2]]=true;if(count($unique)===1){$target=(int)array_key_first($unique);$g=mcr_direct_target_guard($names,mcr_points($e),$hotels[$target]??[]);$sel=($g['ok']??false)?['route'=>'auto_accept_candidate','reason'=>'accepted_operator_native_bridge','target'=>$target,'score'=>1.0,'distance_km'=>$g['distance_km']]:['route'=>'hard_conflict','reason'=>$g['reason']??'bridge_guard'];}elseif(count($unique)>1)$sel=['route'=>'hard_conflict','reason'=>'accepted_operator_bridge_target_conflict','candidate_ids'=>array_map('intval',array_keys($unique))];else $sel=mcr_select_candidate($names,$cid,mcr_points($e),$hotels,$forms,$exact,$tokenIndex,$geoExact);}
        }else{$refs=mcr_provider_bridges($e);$targets=[];foreach($refs as $aid){$c=$catalogById[$aid]??null;if($c&&$c['decision_status']==='accepted'&&$c['local_hotel_id']!==null)$targets[(int)$c['local_hotel_id']]=true;}$item['andromeda_ids']=$refs;if(count($targets)===1){$target=(int)array_key_first($targets);$g=mcr_direct_target_guard($names,mcr_points($e),$hotels[$target]??[]);$sel=($g['ok']??false)?['route'=>'auto_accept_candidate','reason'=>'operator_native_accepted_andromeda_bridge','target'=>$target,'score'=>1.0,'distance_km'=>$g['distance_km']]:['route'=>'hard_conflict','reason'=>$g['reason']??'direct_guard'];}elseif(count($targets)>1)$sel=['route'=>'hard_conflict','reason'=>'operator_native_multi_local_conflict'];elseif($ns==='operator_5'){$auth=$anexAuthority[$id]??[];if(count($auth)===1){$target=(int)array_key_first($auth);$g=mcr_direct_target_guard($names,mcr_points($e),$hotels[$target]??[]);$sel=($g['ok']??false)?['route'=>'auto_accept_candidate','reason'=>'operator_native_current_authority','target'=>$target,'score'=>1.0,'distance_km'=>$g['distance_km']]:['route'=>'hard_conflict','reason'=>$g['reason']??'anex_authority_guard'];}elseif(count($auth)>1)$sel=['route'=>'hard_conflict','reason'=>'anex_current_authority_conflict'];else $sel=['route'=>'needs_anex_detail','reason'=>'anex_no_current_authority'];}else $sel=['route'=>'needs_operator_authority','reason'=>'operator_native_no_current_local_authority'];}
        $item=array_merge($item,$sel);$route=(string)$item['route'];$why=(string)$item['reason'];$routes[$route][]=$item;$reason[$why]=($reason[$why]??0)+1;$routeFrequency[$route]=($routeFrequency[$route]??0)+$freq;if($route==='auto_accept_candidate')$auto[]=$item;
    }
    foreach($routes as &$list)usort($list,fn($a,$b)=>[$b['frequency'],$a['supplier_namespace'],$a['external_hotel_id']]<=>[$a['frequency'],$b['supplier_namespace'],$b['external_hotel_id']]);unset($list);ksort($routes);ksort($reason);ksort($routeFrequency);
    $population=['andromeda_catalog_pending'=>count(array_filter($pending,fn($r)=>$r['supplier_namespace']==='andromeda_catalog')),'operator_native_pending'=>count($operatorRows),'total'=>count($pending),'catalog_hotels'=>count($hotels)];
    $result=['schema'=>'hotel-match-live-residual-current-review/1','operation_id'=>MCR_OP,'source_sha'=>$sourceSha,'state'=>'completed_read_only','server_current'=>true,'transaction'=>'REPEATABLE READ READ ONLY','supplier_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'mapping_writes'=>0,'core8_countries'=>$coreIds,'state_country_semantics'=>$stateCountry,'population'=>$population,'route_counts'=>array_map('count',$routes),'route_frequency'=>$routeFrequency,'reason_counts'=>$reason,'auto_accept_candidate_count'=>count($auto),'auto_accept_candidates'=>$auto,'routes'=>$routes,'no_replay'=>true,'created_at'=>gmdate('c')];
    $hash=mcr_write_exclusive($dir.'/result.json',$result);$raw=(string)file_get_contents($dir.'/result.json');$ok=hash('sha256',$raw)===$hash&&($x=mcr_evidence($raw))&&($x['operation_id']??'')===MCR_OP&&($x['db_writes']??-1)===0&&($x['mapping_writes']??-1)===0&&count($x['routes']??[])>0;$receipt=['operation_id'=>MCR_OP,'source_sha'=>$sourceSha,'state'=>'completed_read_only','result_sha256'=>$hash,'readback_verified'=>$ok,'population_total'=>$population['total'],'auto_accept_candidate_count'=>count($auto),'route_counts'=>array_map('count',$routes),'db_writes'=>0,'supplier_calls'=>0,'external_calls'=>0,'no_replay'=>true,'completed_at'=>gmdate('c')];mcr_write_exclusive($dir.'/receipt.json',$receipt);if(!$ok)throw new RuntimeException('result_readback');echo mcr_json(['operation_id'=>MCR_OP,'state'=>'completed_read_only','population'=>$population,'route_counts'=>array_map('count',$routes),'route_frequency'=>$routeFrequency,'auto_accept_candidate_count'=>count($auto),'reason_counts'=>$reason,'result_sha256'=>$hash]);
    $db->commit();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if(!is_file($dir.'/failure.json'))mcr_write_exclusive($dir.'/failure.json',['operation_id'=>MCR_OP,'source_sha'=>$sourceSha,'state'=>'read_only_failed','error_class'=>get_class($e),'error'=>substr($e->getMessage(),0,300),'supplier_calls'=>0,'external_calls'=>0,'db_writes'=>0,'no_replay'=>true,'failed_at'=>gmdate('c')]);throw $e;}
