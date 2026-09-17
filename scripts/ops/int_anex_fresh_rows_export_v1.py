#!/usr/bin/env python3
from __future__ import annotations
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts' / 'diagnostics'))
import anex_search3_three_source_price as transport
import anex_concrete_fuel_binding as concrete

EXPERIMENT = 'int_anex_fresh_rows_export_20260917_v1'
SPEC = {
    'experiment_id': EXPERIMENT,
    'departure_local_id': 1,
    'country_local_id': 4,
    'date': '2026-10-12',
    'nights': 7,
    'adults': 2,
    'child_ages': [],
    'currency': 'RUB',
}

PHP = r'''
const ANEX_FRESH_ROWS_EXPERIMENT = 'int_anex_fresh_rows_export_20260917_v1';

function fresh_norm($value): string {
    $value = is_string($value) ? trim($value) : '';
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace(['ё','Ё'], 'е', $value)));
}
function fresh_dict_id(array $rows, array $names): int {
    $wanted = array_map('fresh_norm', $names); $found = [];
    foreach ($rows as $row) {
        if (!is_array($row) || !preg_match('/\A[1-9][0-9]{0,7}\z/D', (string)($row['id'] ?? ''))) continue;
        foreach (['name','nameAlt','alias','currencyISO'] as $field) {
            if (is_string($row[$field] ?? null) && in_array(fresh_norm($row[$field]), $wanted, true)) $found[(int)$row['id']] = true;
        }
    }
    if (count($found) !== 1) throw new RuntimeException('FRESH_ROWS_DICTIONARY');
    return (int)array_key_first($found);
}
function fresh_safe($value, int $depth = 0) {
    if ($depth > 10) return '[depth-limit]';
    if (is_array($value)) {
        $out = []; $count = 0;
        foreach ($value as $key => $item) {
            if (++$count > 5000) { $out['__truncated__'] = true; break; }
            $name = is_string($key) ? $key : (string)$key;
            if (preg_match('/token|authorization|password|secret|cookie|session|searchkey/i', $name)) {
                $out[$key] = '[redacted]';
            } else {
                $out[$key] = fresh_safe($item, $depth + 1);
            }
        }
        return $out;
    }
    if (is_string($value)) return strlen($value) > 12000 ? substr($value, 0, 12000) . '[truncated]' : $value;
    if (is_int($value) || is_float($value) || is_bool($value) || $value === null) return $value;
    return (string)$value;
}
function fresh_request(AnyTourAnexClient $client, string $action, array $params, int &$count) {
    if ($count > 0) usleep(1050000);
    ++$count;
    return $client->request($action, $params);
}
function fresh_data($payload): array {
    if (!is_array($payload)) throw new RuntimeException('FRESH_ROWS_RESPONSE');
    $data = array_key_exists('SearchTour_PRICES', $payload) ? $payload['SearchTour_PRICES'] : $payload;
    if (!is_array($data) || !is_array($data['prices'] ?? null)) throw new RuntimeException('FRESH_ROWS_PRICES');
    return $data;
}
function fresh_main(): array {
    $started = microtime(true); $requests = 0; $secrets = [];
    $out = ['schema_version'=>1,'experiment_id'=>ANEX_FRESH_ROWS_EXPERIMENT,'status'=>'blocked',
        'supplier_replay_allowed'=>false,'automatic_retry'=>false,'anex_requests'=>0,'database_writes'=>0,
        'booking_calls'=>0,'lead_calls'=>0,'mapping_writes'=>0,'concrete_count'=>0,'flight_detail_count'=>0];
    try {
        $raw = file_get_contents('php://stdin', false, null, 0, 4097);
        $input = is_string($raw) ? json_decode($raw, true, 16, JSON_THROW_ON_ERROR) : null;
        $expected = ['experiment_id'=>ANEX_FRESH_ROWS_EXPERIMENT,'departure_local_id'=>1,'country_local_id'=>4,
            'date'=>'2026-10-12','nights'=>7,'adults'=>2,'child_ages'=>[],'currency'=>'RUB'];
        if (!is_array($input) || $input !== $expected) throw new RuntimeException('FRESH_ROWS_INPUT');
        $home = (string)getenv('HOME'); $root = realpath($home . '/www/anytoour.ru');
        if (!$root || realpath((string)getcwd()) !== $root) throw new RuntimeException('FRESH_ROWS_RUNTIME');
        $ledger = $home . '/.anytour-ops/' . ANEX_FRESH_ROWS_EXPERIMENT;
        if (file_exists($ledger)) throw new RuntimeException('FRESH_ROWS_NO_REPLAY');
        if (!is_dir($home . '/.anytour-ops') && !mkdir($home . '/.anytour-ops', 0700, true)) throw new RuntimeException('FRESH_ROWS_LEDGER');
        if (!mkdir($ledger, 0700)) throw new RuntimeException('FRESH_ROWS_LEDGER');
        file_put_contents($ledger . '/state', "reserved\n", LOCK_EX);

        require_once $home . '/.anytoour-anex/search3-preview.php'; require_once $root . '/config.php';
        if (!defined('ANEX_API_TOKEN') || !is_string(ANEX_API_TOKEN) || trim(ANEX_API_TOKEN) === '') throw new RuntimeException('FRESH_ROWS_TOKEN');
        $secrets[] = ANEX_API_TOKEN;
        $db = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php'; require_once $db;
        $pdo = v2_data_db(); if (!$pdo instanceof PDO) throw new RuntimeException('FRESH_ROWS_DB');
        $lookup = $pdo->prepare('SELECT d.name departure_name,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.id=? AND c.id=? AND d.is_active=1 AND c.is_active=1 LIMIT 1');
        $lookup->execute([1,4]); $local = $lookup->fetch(PDO::FETCH_ASSOC); if (!$local) throw new RuntimeException('FRESH_ROWS_LOCAL');
        $registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo); $resolver = $registry->previewResolver();
        $client = new AnyTourAnexClient(ANEX_API_TOKEN);

        $towns = fresh_request($client, 'SearchTour_TOWNFROMS', [], $requests);
        $departure = fresh_dict_id($towns, [$local['departure_name'],'Москва','Moscow']);
        $states = fresh_request($client, 'SearchTour_STATES', ['TOWNFROMINC'=>$departure], $requests);
        $country = fresh_dict_id($states, [$local['country_name'],'Египет','Egypt']);
        $dated = ['TOWNFROMINC'=>$departure,'STATEINC'=>$country,'CHECKIN_BEG'=>'20261012','CHECKIN_END'=>'20261012','ADULT'=>2,'CHILD'=>0];
        $currencies = fresh_request($client, 'SearchTour_CURRENCIES', $dated, $requests);
        $currency = fresh_dict_id($currencies, ['RUB','RUR','Рубль','Рубли','Руб']);
        $params = $dated + ['CURRENCY'=>$currency,'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'FREIGHT'=>1,'FILTER'=>1,'PRICEPAGE'=>1,'PARTITION_PRICE'=>32,'SORT'=>'ASC','DYN_SEPARATE'=>1];
        $initialPayload = fresh_request($client, 'SearchTour_PRICES', $params, $requests); $initial = fresh_data($initialPayload);
        $groups = [];
        foreach ($initial['prices'] as $row) {
            if (!is_array($row) || anytour_anex_normalizer_flag($row['grouped'] ?? null) !== true) continue;
            $hotel = anytour_anex_normalizer_id($row['hotelKey'] ?? null); $id = $row['id'] ?? null;
            if ($hotel === null || !(is_string($id) || is_int($id))) continue;
            $localId = $resolver('anex_online', $hotel); if (!is_int($localId) || $localId < 1) continue;
            $amount = anytour_anex_normalizer_decimal($row['price'] ?? null); if ($amount === null) continue;
            $groups[] = ['raw'=>$row,'hotel_external_id'=>$hotel,'local_hotel_id'=>$localId,'amount'=>(float)$amount];
        }
        if (!$groups) throw new RuntimeException('FRESH_ROWS_NO_GROUP');
        usort($groups, static fn($a,$b) => $a['amount'] <=> $b['amount']); $chosen = $groups[0];
        $groupId = (string)$chosen['raw']['id'];
        if (!preg_match('~\A[A-Za-z0-9][A-Za-z0-9_.:,;\~@+/=|\-]{0,2047}\z~D', $groupId)) throw new RuntimeException('FRESH_ROWS_GROUP_ID');
        $expandParams = $params; unset($expandParams['PARTITION_PRICE']); $expandParams['CATCLAIM'] = $groupId; $expandParams['HOTELS'] = $chosen['hotel_external_id'];
        $expandedPayload = fresh_request($client, 'SearchTour_PRICES', $expandParams, $requests); $expanded = fresh_data($expandedPayload);
        if (count($expanded['prices']) > 2000) throw new RuntimeException('FRESH_ROWS_TOO_MANY');
        $context = ['supplier_namespace'=>'anex_online','departure_id'=>(string)$departure,'destination_id'=>(string)$country,'currency_id'=>(string)$currency,
            'checkin_begin'=>'2026-10-12','checkin_end'=>'2026-10-12','nights_from'=>7,'nights_till'=>7,'adults'=>2,'children'=>0,'child_ages'=>[]];
        $rows = [];
        foreach ($expanded['prices'] as $row) {
            if (!is_array($row) || anytour_anex_normalizer_flag($row['grouped'] ?? null) !== false) continue;
            if ((string)($row['hotelKey'] ?? '') !== $chosen['hotel_external_id']) continue;
            $normalized = anytour_anex_normalizer_offer($row, $context, $resolver, $secrets);
            $rows[] = ['raw'=>fresh_safe($row),'normalized'=>$normalized === null ? null : fresh_safe($normalized)];
        }
        if (!$rows) throw new RuntimeException('FRESH_ROWS_NO_CONCRETE');

        $flightDetails = [];
        foreach (array_slice($rows, 0, min(3, count($rows))) as $entry) {
            $catclaim = (string)($entry['raw']['id'] ?? '');
            if ($catclaim === '' || !preg_match('~\A[A-Za-z0-9][A-Za-z0-9_.:,;\~@+/=|\-]{0,2047}\z~D', $catclaim)) continue;
            $payload = fresh_request($client, 'FreightMonitor_FREIGHTSBYPACKET', ['CATCLAIM'=>$catclaim], $requests);
            $flightDetails[] = ['catclaim'=>$catclaim,'payload'=>fresh_safe($payload)];
        }
        $meta = $expanded; unset($meta['prices']); $initialMeta = $initial; unset($initialMeta['prices']);
        $out += [
            'status'=>'completed','observed_at'=>gmdate('c'),'scope'=>['departure'=>$local['departure_name'],'country'=>$local['country_name'],'date'=>'2026-10-12','nights'=>7,'adults'=>2,'children'=>0,'currency'=>'RUB'],
            'supplier_ids'=>['departure'=>(string)$departure,'country'=>(string)$country,'currency'=>(string)$currency],
            'initial_price_rows'=>count($initial['prices']),'mapped_group_candidates'=>count($groups),
            'chosen_group'=>['local_hotel_id'=>$chosen['local_hotel_id'],'hotel_external_id'=>$chosen['hotel_external_id'],'raw'=>fresh_safe($chosen['raw'])],
            'initial_meta'=>fresh_safe($initialMeta),'expanded_meta'=>fresh_safe($meta),'concrete_count'=>count($rows),'concrete_rows'=>$rows,
            'flight_detail_count'=>count($flightDetails),'flight_details'=>$flightDetails,'supplier_effect'=>'read_only_search_expand_flights'];
        file_put_contents($ledger . '/state', "completed\n", LOCK_EX);
    } catch (Throwable $e) {
        $safe = preg_match('/\AFRESH_ROWS_[A-Z0-9_]{1,80}\z/D', $e->getMessage()) ? $e->getMessage() : 'FRESH_ROWS_UNCONFIRMED';
        $out['status'] = 'blocked'; $out['reason'] = $safe;
    }
    $out['anex_requests'] = $requests; $out['elapsed_ms'] = (int)round((microtime(true)-$started)*1000);
    $json = json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    foreach ($secrets as $secret) if ($secret !== '' && strpos($json, $secret) !== false) return ['schema_version'=>1,'experiment_id'=>ANEX_FRESH_ROWS_EXPERIMENT,'status'=>'blocked','reason'=>'FRESH_ROWS_OUTPUT_REDACTED'];
    return $out;
}
'''

def php_source() -> str:
    client = concrete.php_body(ROOT / 'app' / 'integrations' / 'anex-client.php')
    normalizer = concrete.php_body(ROOT / 'app' / 'integrations' / 'anex-normalizer.php')
    registry = concrete.php_body(ROOT / 'app' / 'integrations' / 'anex-search-mapping-registry.php')
    return "declare(strict_types=1);\n" + client + "\n" + normalizer + "\n" + registry + "\n" + PHP + "\n$report=fresh_main();echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),\"\\n\";exit(($report['status']??null)==='completed'?0:1);"

def save(path: Path, value) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n')

def main() -> int:
    if len(sys.argv) != 2:
        raise SystemExit('usage: int_anex_fresh_rows_export_v1.py OUTPUT_DIR')
    out = Path(sys.argv[1])
    try:
        value = transport.ssh_php_no_mux(php_source(), SPEC, maximum_bytes=4000000)
        save(out / 'anex-fresh-full.json', value)
        rows = value.get('concrete_rows') if isinstance(value, dict) else None
        compact = {
            'schema_version': 1,
            'experiment_id': EXPERIMENT,
            'status': value.get('status') if isinstance(value, dict) else 'unconfirmed',
            'observed_at': value.get('observed_at') if isinstance(value, dict) else None,
            'scope': value.get('scope') if isinstance(value, dict) else None,
            'chosen_group': value.get('chosen_group') if isinstance(value, dict) else None,
            'concrete_count': len(rows) if isinstance(rows, list) else 0,
            'concrete_rows': rows if isinstance(rows, list) else [],
            'flight_details': value.get('flight_details') if isinstance(value, dict) else [],
            'anex_requests': value.get('anex_requests') if isinstance(value, dict) else None,
        }
        save(out / 'anex-fresh-concrete-rows.json', compact)
        print(json.dumps({'status': compact['status'], 'concrete_count': compact['concrete_count'], 'flight_detail_count': len(compact['flight_details']), 'anex_requests': compact['anex_requests']}, ensure_ascii=False, sort_keys=True))
        return 0 if compact['status'] == 'completed' else 1
    except Exception as exc:
        failure = {'status':'unconfirmed','error_kind':type(exc).__name__,'automatic_retry':False,'supplier_replay_requested':False}
        try: save(out / 'failure.json', failure)
        except Exception: pass
        print(json.dumps(failure, sort_keys=True))
        return 1

if __name__ == '__main__':
    raise SystemExit(main())
