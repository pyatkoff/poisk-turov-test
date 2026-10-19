<?php
declare(strict_types=1);

putenv('MATCH_PROVIDER_GEO_TEST_LIBRARY=1');
require_once __DIR__ . '/hotel_match_provider_geo_consensus_review.php';

const MPGL_OP = 'hotel-match-provider-geo-label-consensus-review-1971-20260915-v1';
const MPGL_MIN_ANCHORS = 5;

function mpgl_json(array $v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
}
function mpgl_query(PDO $db, string $sql, array $args = []): array {
    $q = $db->prepare($sql);
    $q->execute($args);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}
function mpgl_write(string $file, array $v): string {
    $raw = mpgl_json($v);
    $f = @fopen($file, 'x+b');
    if (!$f) throw new RuntimeException('exclusive_file');
    try {
        if (fwrite($f, $raw) !== strlen($raw) || !fflush($f)) throw new RuntimeException('write');
        if (function_exists('fsync') && !fsync($f)) throw new RuntimeException('sync');
        rewind($f);
        if (stream_get_contents($f) !== $raw) throw new RuntimeException('readback');
    } finally { fclose($f); }
    return hash('sha256', $raw);
}
function mpgl_semantic(string $field): ?string {
    $f = strtolower(preg_replace('/[^a-z]+/i', '', $field) ?? $field);
    if ($f === '' || str_contains($f, 'hotel') || str_contains($f, 'country') || str_contains($f, 'state')) return null;
    if (preg_match('/(?:key|id|code)$/', $f)) return null;
    foreach (['atoll','resort','locality','district','area','city','town','region'] as $term) {
        if (str_contains($f, $term)) return $term;
    }
    return null;
}
function mpgl_label_norm(string $value): string {
    $n = trim(mcr_geo_latin($value));
    if ($n === '' || strlen($n) > 120 || !preg_match('/[a-z]/', $n)) return '';
    $weak = ['all'=>1,'other'=>1,'others'=>1,'unknown'=>1,'none'=>1,'any'=>1,'n a'=>1,'na'=>1,'vse'=>1,'drugoe'=>1];
    if (isset($weak[$n])) return '';
    return $n;
}
function mpgl_geo_labels(array $e): array {
    $src = mcr_source($e);
    $geo = is_array($e['geography'] ?? null) ? $e['geography'] : [];
    $hotelNames = [];
    foreach (mcr_names($e) as $name) {
        $n = mpgl_label_norm((string)$name);
        if ($n !== '') $hotelNames[$n] = true;
    }
    $out = [];
    foreach ([$src, $geo, $e] as $obj) {
        foreach ($obj as $field => $value) {
            if (!is_string($field) || !is_string($value)) continue;
            $semantic = mpgl_semantic($field);
            if ($semantic === null) continue;
            $norm = mpgl_label_norm($value);
            if ($norm === '' || isset($hotelNames[$norm])) continue;
            $key = $semantic . '=' . $norm;
            $out[$key] = ['semantic'=>$semantic, 'label'=>$norm, 'field'=>$field, 'raw'=>trim($value)];
        }
    }
    ksort($out, SORT_NATURAL);
    return $out;
}
function mpgl_build_consensus(array $accepted): array {
    $stats = [];
    foreach ($accepted as $r) {
        $e = mcr_evidence((string)($r['evidence_json'] ?? ''));
        foreach (mpgl_geo_labels($e) as $key => $label) {
            $s = &$stats[$key];
            $s['semantic'] = $label['semantic'];
            $s['label'] = $label['label'];
            $s['fields'][$label['field']] = ($s['fields'][$label['field']] ?? 0) + 1;
            $s['anchors'] = ($s['anchors'] ?? 0) + 1;
            $cid = (int)($r['local_country_id'] ?? 0);
            $rid = (int)($r['local_region_id'] ?? 0);
            $sid = (int)($r['local_subregion_id'] ?? 0);
            if ($cid > 0) $s['countries'][$cid] = ($s['countries'][$cid] ?? 0) + 1;
            if ($rid > 0) $s['regions'][$rid] = ($s['regions'][$rid] ?? 0) + 1;
            if ($sid > 0) $s['subregions'][$sid] = ($s['subregions'][$sid] ?? 0) + 1;
            unset($s);
        }
    }
    $usable = []; $rejected = [];
    foreach ($stats as $key => $s) {
        $n = (int)($s['anchors'] ?? 0);
        $countries = $s['countries'] ?? [];
        $regions = $s['regions'] ?? [];
        $subs = $s['subregions'] ?? [];
        $base = [
            'semantic'=>$s['semantic'], 'label'=>$s['label'], 'fields'=>$s['fields'] ?? [], 'anchors'=>$n,
            'countries'=>$countries, 'regions'=>$regions, 'subregions'=>$subs,
        ];
        if ($n < MPGL_MIN_ANCHORS) { $rejected[$key] = $base + ['reason'=>'insufficient_anchors']; continue; }
        if (count($countries) !== 1 || array_sum($countries) !== $n) { $rejected[$key] = $base + ['reason'=>'country_not_unanimous']; continue; }
        $cid = (int)array_key_first($countries);
        if (count($subs) === 1 && array_sum($subs) === $n) {
            $usable[$key] = $base + ['country_id'=>$cid, 'scope'=>'subregion', 'scope_id'=>(int)array_key_first($subs)];
            continue;
        }
        if (count($regions) === 1 && array_sum($regions) === $n) {
            $usable[$key] = $base + ['country_id'=>$cid, 'scope'=>'region', 'scope_id'=>(int)array_key_first($regions)];
            continue;
        }
        $rejected[$key] = $base + ['reason'=>'local_geography_not_unanimous'];
    }
    ksort($usable, SORT_NATURAL); ksort($rejected, SORT_NATURAL);
    return ['usable'=>$usable, 'rejected'=>$rejected];
}
function mpgl_allowed_ids(array $e, int $countryId, array $consensus, array $scopeIndex): array {
    $sets = []; $used = [];
    foreach (mpgl_geo_labels($e) as $key => $label) {
        $c = $consensus['usable'][$key] ?? null;
        if (!is_array($c) || (int)$c['country_id'] !== $countryId) continue;
        $ids = $scopeIndex[$countryId][$c['scope']][(int)$c['scope_id']] ?? [];
        $sets[] = array_fill_keys(array_map('intval', array_keys($ids)), true);
        $used[] = $c + ['source_field'=>$label['field'], 'source_raw'=>$label['raw']];
    }
    if (!$sets) return ['ids'=>[], 'anchors'=>[], 'status'=>'no_geo_label_consensus'];
    $allowed = array_shift($sets);
    foreach ($sets as $s) $allowed = array_intersect_key($allowed, $s);
    if (!$allowed) return ['ids'=>[], 'anchors'=>$used, 'status'=>'geo_label_consensus_conflict'];
    return ['ids'=>array_map('intval', array_keys($allowed)), 'anchors'=>$used, 'status'=>'ok'];
}

if (getenv('MATCH_PROVIDER_GEO_LABEL_TEST_LIBRARY') === '1') return;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$op = (string)getenv('MATCH_OPERATION_ID');
$sha = (string)getenv('MATCH_SOURCE_SHA');
if ($op !== MPGL_OP || !preg_match('/^[0-9a-f]{40}$/D', $sha)) throw new RuntimeException('operation_or_source_guard');
$home = (string)getenv('HOME');
if ($home === '') throw new RuntimeException('home');
$dir = $home . '/.anytoour-match/operations/' . MPGL_OP;
$res = mcr_evidence((string)@file_get_contents($dir . '/reservation.json'));
if (($res['operation_id'] ?? '') !== MPGL_OP || ($res['source_sha'] ?? '') !== $sha || ($res['state'] ?? '') !== 'reserved_before_db_access') throw new RuntimeException('reservation');
$root = realpath(getcwd());
if (!$root || basename($root) !== 'anytoour.ru') throw new RuntimeException('root');
require_once $root . (is_file($root . '/data/db-v1.php') ? '/data/db-v1.php' : '/v2/data/db-v1.php');
$db = v2_data_db();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$db->exec('START TRANSACTION READ ONLY');
try {
    $core = [];
    foreach (mpgl_query($db, 'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c) {
        if (mcr_is_core8_name((string)$c['name'])) $core[(int)$c['id']] = (string)$c['name'];
    }
    if (count($core) < 6) throw new RuntimeException('core8');
    $marks = implode(',', array_fill(0, count($core), '?'));

    $hotels = []; $forms = []; $scope = [];
    foreach (mpgl_query($db, "SELECT id,country_id,country_name,region_id,region_name,subregion_id,subregion_name,name,category,latitude,longitude FROM catalog_hotels WHERE is_active=1 AND country_id IN ($marks) ORDER BY country_id,id", array_keys($core)) as $h) {
        $id = (int)$h['id']; $hotels[$id] = $h; $forms[$id] = [(string)$h['name']];
        $cid=(int)$h['country_id']; $rid=(int)$h['region_id']; $sid=(int)$h['subregion_id'];
        if ($rid > 0) $scope[$cid]['region'][$rid][$id] = true;
        if ($sid > 0) $scope[$cid]['subregion'][$sid][$id] = true;
    }
    foreach (mpgl_query($db, "SELECT a.hotel_id,a.alias FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1 AND h.country_id IN ($marks) ORDER BY a.hotel_id,a.id", array_keys($core)) as $a) {
        $id=(int)$a['hotel_id']; if (isset($forms[$id])) $forms[$id][]=(string)$a['alias'];
    }

    $all = mpgl_query($db, "SELECT external_hotel_id,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id");
    $accepted = mpgl_query($db, "SELECT i.external_hotel_id,i.local_hotel_id,i.evidence_json,h.country_id AS local_country_id,h.region_id AS local_region_id,h.subregion_id AS local_subregion_id FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id AND h.is_active=1 WHERE i.supplier_namespace='andromeda_catalog' AND i.decision_status='accepted' AND i.local_hotel_id IS NOT NULL ORDER BY i.external_hotel_id");
    $country = msac_accepted_country_consensus($accepted, $all, $core);
    $labels = mpgl_build_consensus($accepted);

    $pending = mpgl_query($db, "SELECT supplier_namespace,external_hotel_id,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");
    $routes=[]; $candidates=[]; $reasons=[]; $withLabel=0; $freq=0; $fieldCounts=[];
    foreach ($pending as $r) {
        $e=mcr_evidence((string)$r['evidence_json']);
        foreach (mpgl_geo_labels($e) as $lab) $fieldCounts[$lab['field']] = ($fieldCounts[$lab['field']] ?? 0) + 1;
        $sk=mcr_state_key($e);
        if ($sk===null || !isset($country['inferred'][$sk])) continue;
        $cid=(int)$country['inferred'][$sk]['country_id'];
        $allow=mpgl_allowed_ids($e,$cid,$labels,$scope);
        if ($allow['status']!=='no_geo_label_consensus') $withLabel++;
        if ($allow['status']==='geo_label_consensus_conflict') $sel=['route'=>'hard_conflict','reason'=>'provider_geo_label_consensus_conflict'];
        elseif ($allow['status']!=='ok') $sel=['route'=>'needs_extra_evidence','reason'=>'no_provider_geo_label_consensus'];
        else $sel=mpg_select(mcr_names($e),mcr_points($e),$allow['ids'],$hotels,$forms);
        $item=array_merge([
            'supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>(string)$r['external_hotel_id'],
            'evidence_sha256'=>(string)$r['evidence_sha256'],'state_key'=>$sk,'country_id'=>$cid,
            'frequency'=>mcr_frequency($e),'geo_label_anchors'=>$allow['anchors'],
        ],$sel);
        $routes[$item['route']][]=$item;
        $reasons[$item['reason']]=($reasons[$item['reason']]??0)+1;
        if($item['route']==='auto_accept_candidate'){$candidates[]=$item;$freq+=(int)$item['frequency'];}
    }
    foreach($routes as &$ls) usort($ls,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id'])); unset($ls);
    usort($candidates,fn($a,$b)=>(($b['frequency']??0)<=>($a['frequency']??0))?:strcmp((string)$a['external_hotel_id'],(string)$b['external_hotel_id']));
    ksort($routes); ksort($reasons); ksort($fieldCounts,SORT_NATURAL);
    $result=[
        'schema'=>'hotel-match-provider-geo-label-consensus-review/1','operation_id'=>MPGL_OP,'source_sha'=>$sha,
        'state'=>'completed_read_only','server_current'=>true,'transaction'=>'REPEATABLE READ READ ONLY',
        'accepted_andromeda_rows'=>count($accepted),'pending_andromeda_rows'=>count($pending),
        'usable_geo_labels'=>count($labels['usable']),'rejected_geo_labels'=>count($labels['rejected']),
        'pending_with_usable_geo_label'=>$withLabel,'pending_geo_label_field_counts'=>$fieldCounts,
        'candidate_count'=>count($candidates),'candidate_live_frequency_sum'=>$freq,'candidates'=>$candidates,
        'route_counts'=>array_map('count',$routes),'reason_counts'=>$reasons,'geo_label_consensus'=>$labels,
        'supplier_calls'=>0,'tourvisor_calls'=>0,'external_calls'=>0,'booking_calls'=>0,'db_writes'=>0,'mapping_writes'=>0,'operator_5_writes'=>0,
        'no_replay'=>true,'created_at'=>gmdate('c')
    ];
    $hash=mpgl_write($dir.'/result.json',$result);
    $raw=(string)file_get_contents($dir.'/result.json'); $x=mcr_evidence($raw);
    if(hash('sha256',$raw)!==$hash||($x['state']??'')!=='completed_read_only'||(int)($x['db_writes']??-1)!==0) throw new RuntimeException('result_readback');
    mpgl_write($dir.'/receipt.json',['operation_id'=>MPGL_OP,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$hash,'readback_verified'=>true,'no_replay'=>true,'created_at'=>gmdate('c')]);
    $db->rollBack();
    echo 'MATCH_PROVIDER_GEO_LABEL_CONSENSUS_OK '.count($candidates)."\n";
} catch(Throwable $e) {
    if($db->inTransaction()) $db->rollBack();
    throw $e;
}
