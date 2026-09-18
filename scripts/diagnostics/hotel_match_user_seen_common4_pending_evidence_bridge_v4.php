<?php
declare(strict_types=1);

const HMP4_OPERATION = 'hotel-match-user-seen-common4-pending-evidence-bridge-1971-20260918-v4';
const HMP4_LIMIT = 100000;
const HMP4_EXCLUDED = [46 => true, 47 => true];
const HMP4_GENERIC = ['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'resort'=>1,'resorts'=>1,'spa'=>1];
const HMP4_QUAL = ['annex'=>1,'beach'=>1,'garden'=>1,'gardens'=>1,'north'=>1,'south'=>1,'east'=>1,'west'=>1,'adult'=>1,'adults'=>1,'only'=>1,'family'=>1,'pool'=>1,'sea'=>1,'view'=>1,'deluxe'=>1,'suite'=>1,'suites'=>1,'villa'=>1,'villas'=>1,'palace'=>1,'park'=>1,'club'=>1,'city'=>1,'premium'=>1,'select'=>1];

function hmp4_rows(PDO $pdo, string $sql, array $params = []): array {
    $s = $pdo->prepare($sql);
    $s->execute(array_values($params));
    $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($rows) > HMP4_LIMIT) throw new RuntimeException('row_budget');
    return $rows;
}
function hmp4_norm(string $v): string {
    $v = mb_strtolower(trim($v), 'UTF-8');
    $v = strtr($v, ['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);
    $v = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $v) ?? $v;
    return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
}
function hmp4_generic(string $v): string {
    $t = preg_split('/\s+/u', hmp4_norm($v), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return implode(' ', array_values(array_filter($t, static fn($x) => !isset(HMP4_GENERIC[$x]))));
}
function hmp4_valid(string $v): bool {
    $t = preg_split('/\s+/u', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $sub = array_values(array_filter($t, static fn($x) => !isset(HMP4_GENERIC[$x])));
    return mb_strlen($v, 'UTF-8') >= 4 && (count($sub) >= 2 || (count($sub) === 1 && mb_strlen($sub[0], 'UTF-8') >= 7));
}
function hmp4_forms(string $name): array {
    $base = [];
    $add = static function(string $x) use (&$base): void {
        $n = hmp4_norm($x);
        if ($n !== '') $base[$n] = true;
    };
    $add($name);
    if (preg_match('/^(.+?)\s*\((?:EX\.?|FORMERLY|БЫВШ\.?)[^)]*\)/iu', $name, $m)) $add($m[1]);
    if (preg_match_all('/\((?:EX\.?|FORMERLY|БЫВШ\.?)\s*([^)]*)\)/iu', $name, $m)) {
        foreach ($m[1] as $x) foreach (preg_split('/[;|\/]+/u', $x) ?: [] as $y) $add($y);
    }
    $out = [];
    foreach (array_keys($base) as $n) {
        if (hmp4_valid($n)) $out[$n] = true;
        $g = hmp4_generic($n);
        if ($g !== $n && hmp4_valid($g)) $out[$g] = true;
    }
    return array_keys($out);
}
function hmp4_tokens(string $v): array { return preg_split('/\s+/u', hmp4_norm($v), -1, PREG_SPLIT_NO_EMPTY) ?: []; }
function hmp4_qual(string $v): array {
    $out = [];
    foreach (hmp4_tokens($v) as $x) if (isset(HMP4_QUAL[$x])) $out[$x] = true;
    $out = array_keys($out); sort($out, SORT_STRING); return $out;
}
function hmp4_nums(string $v): array {
    $out = [];
    foreach (hmp4_tokens($v) as $x) if (ctype_digit($x)) $out[$x] = true;
    $out = array_keys($out); sort($out, SORT_STRING); return $out;
}
function hmp4_product(string $v): bool { return (bool)preg_match('/^(?:fortuna|фортуна|roulette|рулетка|рулет)(?:\s|$)/u', hmp4_norm($v)); }
function hmp4_scalar(array $a, array $keys): ?string {
    foreach ($keys as $k) {
        if (!array_key_exists($k, $a) || !(is_string($a[$k]) || is_int($a[$k]))) continue;
        $v = trim((string)$a[$k]); if ($v !== '') return $v;
    }
    return null;
}
function hmp4_idstr(mixed $v): ?string {
    if (!(is_string($v) || is_int($v))) return null;
    $s = trim((string)$v);
    return preg_match('/^[1-9][0-9]{0,31}$/D', $s) ? $s : null;
}
function hmp4_sources(array $v, array &$out): void {
    foreach ($v as $k => $x) {
        if (($k === 'source' || $k === 'source_candidate') && is_array($x)) $out[] = $x;
        if (is_array($x)) hmp4_sources($x, $out);
    }
}
function hmp4_identity_route(array $source, mixed $external): ?string {
    $ext = hmp4_idstr($external); if ($ext === null) return null;
    foreach (['hotelKey','external_hotel_id'] as $k) {
        if (!array_key_exists($k, $source)) continue;
        $seen = hmp4_idstr($source[$k]);
        return $seen !== null && hash_equals($ext, $seen) ? $k : null;
    }
    if (array_key_exists('id', $source)) {
        $seen = hmp4_idstr($source['id']);
        return $seen !== null && hash_equals($ext, $seen) ? 'id' : null;
    }
    return null;
}
function hmp4_bound_source(array $evidence, mixed $external): array {
    $sources = []; hmp4_sources($evidence, $sources);
    $matched = [];
    foreach ($sources as $source) {
        $route = hmp4_identity_route($source, $external);
        if ($route === null) continue;
        $priority = $route === 'hotelKey' ? 30 : ($route === 'external_hotel_id' ? 20 : 10);
        if (hmp4_scalar($source, ['name','lName','hotel_name']) !== null) $priority += 4;
        if (hmp4_scalar($source, ['stateKey','state','country']) !== null) $priority += 2;
        if (hmp4_scalar($source, ['townKey','town']) !== null) $priority += 1;
        $matched[] = ['source'=>$source,'route'=>$route,'priority'=>$priority];
    }
    if (!$matched) return ['source'=>[], 'route'=>null];
    usort($matched, static fn($a,$b) => $b['priority'] <=> $a['priority']);
    return ['source'=>$matched[0]['source'], 'route'=>$matched[0]['route']];
}
function hmp4_best_source(array $evidence): array {
    $sources = []; hmp4_sources($evidence, $sources); $best = []; $score = -1;
    foreach ($sources as $source) {
        $s = 0;
        if (hmp4_scalar($source, ['hotelKey','external_hotel_id','id']) !== null) $s += 4;
        if (hmp4_scalar($source, ['name','lName','hotel_name']) !== null) $s += 3;
        if (hmp4_scalar($source, ['stateKey','state','country']) !== null) $s += 2;
        if (hmp4_scalar($source, ['townKey','town']) !== null) $s += 1;
        if ($s > $score) { $score = $s; $best = $source; }
    }
    return $best;
}
function hmp4_consensus(array $set): ?string { $x = array_keys($set); return count($x) === 1 ? (string)$x[0] : null; }
function hmp4_save(string $path, array $value): string {
    $raw = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    $f = @fopen($path, 'x+b'); if (!$f) throw new RuntimeException('durable_exists');
    try {
        if (fwrite($f, $raw) !== strlen($raw) || !fflush($f)) throw new RuntimeException('durable_write');
        if (function_exists('fsync') && !fsync($f)) throw new RuntimeException('durable_sync');
        rewind($f); if (stream_get_contents($f) !== $raw) throw new RuntimeException('durable_readback');
    } finally { fclose($f); }
    return hash('sha256', $raw);
}

if (in_array('--self-test', $argv ?? [], true)) {
    if (hmp4_identity_route(['id'=>'OFFER','hotelKey'=>123], 123) !== 'hotelKey') throw new RuntimeException('numeric_key');
    if (hmp4_identity_route(['id'=>123], '123') !== 'id') throw new RuntimeException('catalog_id');
    if (hmp4_identity_route(['id'=>123,'hotelKey'=>999], 123) !== null) throw new RuntimeException('price_mismatch_fallback');
    $e = ['a'=>['source'=>['id'=>77,'name'=>'Catalog Hotel']], 'b'=>['source_candidate'=>['id'=>'OFFER','hotelKey'=>88,'name'=>'Price Hotel']]];
    $b = hmp4_bound_source($e, 88); if (($b['route']??null) !== 'hotelKey' || ($b['source']['name']??'') !== 'Price Hotel') throw new RuntimeException('bound_source');
    if (!in_array('sun maris park', hmp4_forms('SUN BAY HOTEL (EX. SUN MARIS PARK)'), true)) throw new RuntimeException('former');
    echo "MATCH_PENDING_EVIDENCE_BRIDGE_V4_SELFTEST_OK\n"; exit(0);
}
if (PHP_SAPI !== 'cli') exit(2);

$root = realpath((string)getenv('ANYTOUR_ROOT'));
$op = (string)getenv('MATCH_OPERATION_DIR');
$registryPath = realpath((string)getenv('MATCH_MAPPING_REGISTRY_PATH'));
$sourceSha = (string)getenv('MATCH_SOURCE_SHA');
if (!$root || $op === '' || !is_string($registryPath) || !is_file($registryPath) || !preg_match('/^[a-f0-9]{40}$/D', $sourceSha) || is_dir($op) || !mkdir($op, 0700, true)) throw new RuntimeException('runtime_guard');
hmp4_save($op.'/reservation.json', ['operation'=>HMP4_OPERATION,'state'=>'reserved_before_db_read','source_sha'=>$sourceSha,'provider_access'=>false,'no_replay'=>true]);

require_once $registryPath;
$dbFile = is_file($root.'/data/db-v1.php') ? $root.'/data/db-v1.php' : $root.'/v2/data/db-v1.php';
require_once $dbFile;
$pdo = v2_data_db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION READ ONLY');

try {
    $registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $anex = [];
    foreach (hmp4_rows($pdo, 'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions') as $r) {
        $local = $registry->resolve('anex_online', (string)$r['anex_hotel_id'], 'preview');
        if (is_int($local) && $local > 0) $anex[$local] = true;
    }

    $identity = hmp4_rows($pdo, "SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id");
    $and = []; $pending = [];
    foreach ($identity as $r) {
        if (($r['decision_status']??'') === 'accepted' && $r['local_hotel_id'] !== null) $and[(int)$r['local_hotel_id']] = true;
        if (($r['decision_status']??'') === 'pending' && $r['local_hotel_id'] === null) $pending[(string)$r['external_hotel_id']] = $r;
    }

    $seen = [];
    foreach (hmp4_rows($pdo, "SELECT hotel_id,COUNT(*) n,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id") as $r) {
        $seen[(int)$r['hotel_id']] = ['n'=>(int)$r['n'],'last'=>(string)$r['last_seen']];
    }
    $ids = array_keys($seen); if (!$ids) throw new RuntimeException('no_seen');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $front = [];
    foreach (hmp4_rows($pdo, "SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,is_active FROM catalog_hotels WHERE id IN ($ph)", $ids) as $h) {
        $id = (int)$h['id']; $cid = (int)$h['country_id'];
        if ((int)$h['is_active'] !== 1 || isset(HMP4_EXCLUDED[$cid]) || isset($anex[$id]) || isset($and[$id]) || hmp4_product((string)$h['name'])) continue;
        $front[$id] = $h + ['observation_rows'=>$seen[$id]['n'],'last_observed_at'=>$seen[$id]['last']];
    }
    $fids = array_keys($front); if (!$fids) throw new RuntimeException('frontier_empty');
    $fh = implode(',', array_fill(0, count($fids), '?'));
    $common = [];
    foreach (hmp4_rows($pdo, "SELECT DISTINCT hotel_id FROM tour_price_observations WHERE source='user_search' AND hotel_id IN ($fh) AND departure_date>=CURRENT_DATE AND operator_id IN(13,18,25,43)", $fids) as $r) $common[(int)$r['hotel_id']] = true;
    $targets = array_intersect_key($front, $common); if (!$targets) throw new RuntimeException('no_targets');

    $countries = []; foreach ($targets as $h) $countries[(int)$h['country_id']] = true;
    $cids = array_keys($countries); $cph = implode(',', array_fill(0, count($cids), '?'));
    $all = []; $index = []; $countryNames = [];
    foreach (hmp4_rows($pdo, "SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,is_active FROM catalog_hotels WHERE is_active=1 AND country_id IN ($cph)", $cids) as $h) {
        $id=(int)$h['id']; $cid=(int)$h['country_id']; $all[$id]=$h; $countryNames[$cid][hmp4_norm((string)$h['country_name'])]=true;
        foreach (hmp4_forms((string)$h['name']) as $key) $index[$cid][$key][$id][(string)$h['name']] = true;
    }
    foreach (hmp4_rows($pdo, "SELECT a.hotel_id,a.alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($cph)", $cids) as $a) {
        $id=(int)$a['hotel_id']; $raw=trim((string)$a['alias']); if ($raw==='') continue;
        foreach (hmp4_forms($raw) as $key) $index[(int)$a['country_id']][$key][$id][$raw]=true;
    }

    $stateSets=[]; $townSets=[]; $acceptedEvidence=0;
    foreach ($identity as $r) {
        if (($r['decision_status']??'')!=='accepted' || $r['local_hotel_id']===null) continue;
        $local=(int)$r['local_hotel_id']; $h=$all[$local]??null; if (!$h) continue;
        try { $e=json_decode((string)$r['evidence_json'], true, 128, JSON_THROW_ON_ERROR); } catch (Throwable $x) { continue; }
        $source=hmp4_best_source($e); if (!$source) continue;
        $stateKey=hmp4_scalar($source,['stateKey','provider_state_id','supplier_state_id']);
        $townKey=hmp4_scalar($source,['townKey','town_key','supplier_town_id']);
        if ($stateKey!==null) $stateSets[$stateKey][(string)$h['country_id']]=true;
        if ($townKey!==null) {
            $k=$h['country_id'].'|'.$townKey;
            $townSets[$k]['region'][(string)($h['region_id']??'')]=true;
            $townSets[$k]['sub'][(string)($h['subregion_id']??'')]=true;
            $townSets[$k]['rows']=($townSets[$k]['rows']??0)+1;
        }
        $acceptedEvidence++;
    }
    $state=[]; $stateConf=0;
    foreach ($stateSets as $k=>$set) { $v=hmp4_consensus($set); if ($v===null) $stateConf++; else $state[$k]=(int)$v; }
    $town=[]; $townConf=0;
    foreach ($townSets as $k=>$x) {
        $regions=$x['region']; unset($regions['']); $subs=$x['sub']; unset($subs['']);
        $region=hmp4_consensus($regions); $sub=hmp4_consensus($subs);
        if ($region===null && $sub===null) { $townConf++; continue; }
        $town[$k]=['region_id'=>$region===null?null:(int)$region,'subregion_id'=>$sub===null?null:(int)$sub,'accepted_rows'=>(int)$x['rows']];
    }

    $raw=[]; $reasons=[]; $routes=[];
    foreach ($pending as $externalKey=>$r) {
        $external=hmp4_idstr($externalKey);
        if ($external===null) { $reasons['invalid_external_id']=($reasons['invalid_external_id']??0)+1; continue; }
        try { $e=json_decode((string)$r['evidence_json'], true, 128, JSON_THROW_ON_ERROR); } catch (Throwable $x) { $reasons['invalid_evidence']=($reasons['invalid_evidence']??0)+1; continue; }
        $bound=hmp4_bound_source($e, $external);
        $source=$bound['source']; $route=$bound['route'];
        if (!$source || $route===null) { $reasons['source_hotel_identity_unproven']=($reasons['source_hotel_identity_unproven']??0)+1; continue; }
        $routes[$route]=($routes[$route]??0)+1;

        $stateKey=hmp4_scalar($source,['stateKey','provider_state_id','supplier_state_id']);
        $cid=$stateKey!==null ? ($state[$stateKey]??0) : 0;
        if ($cid===0) {
            $label=hmp4_norm((string)(hmp4_scalar($source,['state','country','country_name'])??'')); $hits=[];
            if ($label!=='') foreach ($countryNames as $cc=>$names) if (isset($names[$label])) $hits[$cc]=true;
            if (count($hits)===1) $cid=(int)array_key_first($hits);
        }
        if ($cid===0 || !isset($countries[$cid])) { $reasons['country_unresolved_or_outside']=($reasons['country_unresolved_or_outside']??0)+1; continue; }

        $sourceNames=[];
        foreach (['name','lName','hotel','hotel_name'] as $k) { $v=trim((string)($source[$k]??'')); if ($v!=='') $sourceNames[$v]=true; }
        if (!$sourceNames) { $reasons['no_source_name']=($reasons['no_source_name']??0)+1; continue; }
        $candidates=[]; $matches=[];
        foreach (array_keys($sourceNames) as $sourceName) foreach (hmp4_forms($sourceName) as $key) foreach ($index[$cid][$key]??[] as $local=>$rawForms) {
            $candidates[(int)$local]=true;
            foreach (array_keys($rawForms) as $targetForm) $matches[(int)$local][]=['key'=>$key,'source_name'=>$sourceName,'target_form'=>$targetForm];
        }
        if (count($candidates)!==1) { $reason=count($candidates)>1?'ambiguous_country_name':'no_exact_country_name'; $reasons[$reason]=($reasons[$reason]??0)+1; continue; }
        $local=(int)array_key_first($candidates);
        if (!isset($targets[$local])) { $reasons['unique_match_not_current_target']=($reasons['unique_match_not_current_target']??0)+1; continue; }
        $h=$targets[$local]; $good=null;
        foreach ($matches[$local]??[] as $m) {
            if (hmp4_qual($m['source_name'])===hmp4_qual($m['target_form']) && hmp4_nums($m['source_name'])===hmp4_nums($m['target_form'])) { $good=$m; break; }
        }
        if ($good===null) { $reasons['qualifier_or_number_conflict']=($reasons['qualifier_or_number_conflict']??0)+1; continue; }
        $star=hmp4_scalar($source,['star','category']);
        if ($star!==null && preg_match('/^[1-5]$/D',$star) && $h['category']!==null && (int)$h['category']>=1 && (int)$h['category']<=5 && (int)$star!==(int)$h['category']) { $reasons['star_conflict']=($reasons['star_conflict']??0)+1; continue; }

        $townKey=hmp4_scalar($source,['townKey','town_key','supplier_town_id']);
        $townName=hmp4_norm((string)(hmp4_scalar($source,['town','town_name','region_name'])??''));
        $direct=$townName!=='' && in_array($townName, array_filter([hmp4_norm((string)$h['region_name']),hmp4_norm((string)$h['subregion_name'])]), true);
        $cross=$townKey===null ? null : ($town[$cid.'|'.$townKey]??null);
        $crossOk=$cross!==null && (($cross['region_id']!==null && (int)($h['region_id']??0)===$cross['region_id']) || ($cross['subregion_id']!==null && (int)($h['subregion_id']??0)===$cross['subregion_id']));
        if (!$direct && !$crossOk) { $reason=$cross!==null?'town_crosswalk_conflict':'geography_unproven'; $reasons[$reason]=($reasons[$reason]??0)+1; continue; }

        $raw[]=[
            'external_hotel_id'=>$external,'source_identity_route'=>$route,'local_hotel_id'=>$local,'local_name'=>(string)$h['name'],
            'country_id'=>$cid,'country_name'=>(string)$h['country_name'],'region_id'=>$h['region_id']===null?null:(int)$h['region_id'],'region_name'=>(string)$h['region_name'],
            'subregion_id'=>$h['subregion_id']===null?null:(int)$h['subregion_id'],'subregion_name'=>(string)$h['subregion_name'],'category'=>$h['category']===null?null:(int)$h['category'],
            'source_names'=>array_keys($sourceNames),'state_key'=>$stateKey,'town_key'=>$townKey,'source_town'=>$townName?:null,
            'geography_route'=>$direct?'direct_town_label':'accepted_town_crosswalk','match'=>$good,'observation_rows'=>(int)$h['observation_rows'],
            'pending_evidence_sha256'=>(string)($r['evidence_sha256']??'')
        ];
    }

    $extLoc=[]; $locExt=[];
    foreach ($raw as $x) { $extLoc[$x['external_hotel_id']][$x['local_hotel_id']]=true; $locExt[$x['local_hotel_id']][$x['external_hotel_id']]=true; }
    $safe=[]; $holds=[];
    foreach ($raw as $x) {
        if (count($extLoc[$x['external_hotel_id']])!==1 || count($locExt[$x['local_hotel_id']])!==1) { $holds[]=$x; continue; }
        $safe[]=$x;
    }
    usort($safe, static fn($a,$b) => $b['observation_rows'] <=> $a['observation_rows'] ?: $a['local_hotel_id'] <=> $b['local_hotel_id']);
    ksort($routes); ksort($reasons); $pdo->rollBack();
    $result=[
        'operation'=>HMP4_OPERATION,'status'=>'read_only_complete','source_sha'=>$sourceSha,
        'current_unresolved_real'=>count($front),'current_common4_residual'=>count($targets),'pending_examined'=>count($pending),'accepted_evidence_rows_examined'=>$acceptedEvidence,
        'source_identity_route_counts'=>$routes,'state_crosswalk_keys'=>count($state),'state_crosswalk_conflicts'=>$stateConf,'town_crosswalk_keys'=>count($town),'town_crosswalk_conflicts'=>$townConf,
        'raw_candidate_count'=>count($raw),'safe_one_to_one_count'=>count($safe),'safe_candidates'=>$safe,'collision_holds'=>array_slice($holds,0,200),'reason_counts'=>$reasons,
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0
    ];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $result=['operation'=>HMP4_OPERATION,'status'=>'failed_read_only','source_sha'=>$sourceSha,'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',mb_substr($e->getMessage(),0,120)),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0];
}
$digest=hmp4_save($op.'/result.json',$result);
hmp4_save($op.'/receipt.json',['operation'=>HMP4_OPERATION,'status'=>$result['status'],'result_sha256'=>$digest,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit(($result['status']??'')==='read_only_complete'?0:2);
