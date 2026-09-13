#!/usr/bin/env python3
"""One-shot exact-CATCLAIM comparison of SearchTour freight refs to FreightMonitor keys."""
import json
from pathlib import Path
import sys

import anex_search3_three_source_price as transport
import anex_concrete_fuel_binding as concrete

EXPERIMENT = 'anex_freight_ref_binding_20260913_v1'
SPEC = {
    'experiment_id': EXPERIMENT,
    'country': 'Turkey',
    'date': '2026-10-19',
    'nights': 7,
    'adults': 2,
    'child_ages': [],
    'meal_family': 'ai',
    'currency': 'RUB',
    'hotel_external_id': '25084',
}

PHP = r'''
const ANEX_FREIGHT_REF_BINDING_EXPERIMENT = 'anex_freight_ref_binding_20260913_v1';

function anex_freight_ref_binding_main(): array
{
    $started = microtime(true);
    $pdo = null; $lock = null; $path = null; $reserved = false; $client = null; $secrets = [];
    $out = [
        'schema_version' => 1,
        'experiment_id' => ANEX_FREIGHT_REF_BINDING_EXPERIMENT,
        'status' => 'blocked',
        'automatic_retry' => false,
        'supplier_replay_allowed' => false,
        'anex_requests' => 0,
        'additional_prices_requests' => 0,
        'tourvisor_requests' => 0,
        'andromeda_requests' => 0,
        'booking_calls' => 0,
        'broninit_calls' => 0,
        'mapping_writes' => 0,
        'production_price_arithmetic_applied' => false,
        'additional_prices_tour_binding_verified' => false,
        'selected_transport_verified' => false,
        'selected_concrete' => null,
        'ref_binding' => null,
    ];
    try {
        $input = json_decode((string) file_get_contents('php://stdin'), true, 8, JSON_THROW_ON_ERROR);
        $expected = [
            'experiment_id' => ANEX_FREIGHT_REF_BINDING_EXPERIMENT,
            'country' => 'Turkey', 'date' => '2026-10-19', 'nights' => 7, 'adults' => 2,
            'child_ages' => [], 'meal_family' => 'ai', 'currency' => 'RUB',
            'hotel_external_id' => '25084',
        ];
        if (!is_array($input) || $input !== $expected) throw new RuntimeException('FREIGHT_REF_BINDING_INVALID_INPUT');

        $home = (string) getenv('HOME');
        $root = realpath($home . '/www/anytoour.ru');
        $preview = realpath($root . '/_preview/search3-anex-candidate');
        if (!$root || !$preview || $preview !== $root . '/_preview/search3-anex-candidate'
            || !in_array(realpath((string) getcwd()), [$root, $preview], true)) {
            throw new RuntimeException('FREIGHT_REF_BINDING_RUNTIME');
        }
        require_once $home . '/.anytoour-anex/search3-preview.php';
        require_once $root . '/config.php';
        if (!defined('ANEX_API_TOKEN') || !is_string(ANEX_API_TOKEN) || trim(ANEX_API_TOKEN) === '') {
            throw new RuntimeException('FREIGHT_REF_BINDING_TOKEN');
        }
        $secrets = [ANEX_API_TOKEN];
        $db = is_file($root . '/data/db-v1.php') ? $root . '/data/db-v1.php' : $root . '/v2/data/db-v1.php';
        require_once $db;
        $pdo = v2_data_db();
        if (!$pdo instanceof PDO || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('FREIGHT_REF_BINDING_DB');
        }
        $registry = AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
        if ($registry->resolve('anex_online', '25084', 'preview') !== 21753) {
            throw new RuntimeException('FREIGHT_REF_BINDING_IDENTITY');
        }
        $lookup = $pdo->query("SELECT d.id departure_id,d.name departure_name,c.id country_id,c.name country_name FROM catalog_departures d CROSS JOIN catalog_countries c WHERE d.is_active=1 AND c.is_active=1 AND d.name IN ('Москва','Moscow') AND c.name IN ('Турция','Turkey') LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
        if (count($lookup) !== 1) throw new RuntimeException('FREIGHT_REF_BINDING_LOCAL');
        $local = $lookup[0];

        $dir = $home . '/.anytoour-anex';
        if (!is_dir($dir) || is_link($dir)) throw new RuntimeException('FREIGHT_REF_BINDING_CHECKPOINT_DIR');
        $path = $dir . '/' . ANEX_FREIGHT_REF_BINDING_EXPERIMENT . '.json';
        $lock = fopen($path . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('FREIGHT_REF_BINDING_LOCK');
        if (is_file($path)) {
            $prior = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
            if (($prior['status'] ?? null) === 'completed' && is_array($prior['result'] ?? null)) {
                return array_replace($prior['result'], ['reused' => true]);
            }
            throw new RuntimeException('FREIGHT_REF_BINDING_NOT_REPLAYABLE');
        }
        anex_concrete_fuel_save($path, [
            'schema_version' => 1,
            'experiment_id' => ANEX_FREIGHT_REF_BINDING_EXPERIMENT,
            'status' => 'reserved',
            'reserved_at' => gmdate('c'),
        ]);
        $reserved = true;

        $client = new AnyTourAnexClient(ANEX_API_TOKEN);
        $departure = anex_concrete_fuel_dictionary_id(
            anex_concrete_fuel_dictionary($client, 'SearchTour_TOWNFROMS', []),
            [$local['departure_name'], 'Москва', 'Moscow']
        );
        usleep(1050000);
        $country = anex_concrete_fuel_dictionary_id(
            anex_concrete_fuel_dictionary($client, 'SearchTour_STATES', ['TOWNFROMINC' => $departure]),
            [$local['country_name'], 'Турция', 'Turkey']
        );
        $dated = [
            'TOWNFROMINC' => $departure, 'STATEINC' => $country,
            'CHECKIN_BEG' => '20261019', 'CHECKIN_END' => '20261019',
            'ADULT' => 2, 'CHILD' => 0,
        ];
        usleep(1050000);
        $currency = anex_concrete_fuel_dictionary_id(
            anex_concrete_fuel_dictionary($client, 'SearchTour_CURRENCIES', $dated),
            ['RUB', 'RUR', 'Рубль', 'Рубли', 'Руб']
        );
        $criteria = [
            'supplier_namespace' => 'anex_online',
            'departure_id' => $departure,
            'destination_id' => $country,
            'currency_id' => $currency,
            'checkin_begin' => '2026-10-19', 'checkin_end' => '2026-10-19',
            'nights_from' => 7, 'nights_till' => 7,
            'adults' => 2, 'children' => 0, 'child_ages' => [],
            'hotel_ids' => ['25084'],
        ];
        $search = new AnyTourAnexSearch($client, $registry->previewResolver(), $secrets);
        usleep(1050000);
        $page = $search->search($criteria);
        $groups = [];
        foreach ($page['offers'] ?? [] as $offer) {
            $summary = anex_concrete_fuel_offer_summary($offer);
            if ($summary !== null && ($summary['kind'] ?? null) === 'group_minimum') {
                $groups[] = ['raw' => $offer, 'summary' => $summary];
            }
        }
        if (!$groups) throw new RuntimeException('FREIGHT_REF_BINDING_SEARCH_EMPTY');
        usort($groups, static fn($a, $b) => (float) $a['summary']['price'] <=> (float) $b['summary']['price']);

        usleep(1050000);
        $expanded = $search->expand($groups[0]['raw']['offer_key']);
        $snapshot = $search->snapshot();
        $snapshotByKey = [];
        foreach ($snapshot['offers'] ?? [] as $saved) {
            if (is_array($saved) && is_string($saved['offer_key'] ?? null)) $snapshotByKey[$saved['offer_key']] = $saved;
        }
        $concrete = [];
        foreach ($expanded['offers'] ?? [] as $offer) {
            $summary = anex_concrete_fuel_offer_summary($offer);
            if ($summary === null || ($summary['kind'] ?? null) !== 'concrete') continue;
            $saved = $snapshotByKey[$offer['offer_key'] ?? ''] ?? null;
            $refs = is_array($saved) && is_array($saved['supplier_freight_refs'] ?? null)
                ? $saved['supplier_freight_refs'] : [];
            if (!isset($refs['outbound'], $refs['return'])) continue;
            $concrete[] = ['raw' => $offer, 'summary' => $summary, 'refs' => $refs];
        }
        if (!$concrete) throw new RuntimeException('FREIGHT_REF_BINDING_REFS');
        usort($concrete, static fn($a, $b) => (float) $a['summary']['price'] <=> (float) $b['summary']['price']);
        $selected = $concrete[0];
        $claim = $selected['raw']['supplier_offer_id'] ?? null;
        if (!is_string($claim) || $claim === '') throw new RuntimeException('FREIGHT_REF_BINDING_CLAIM');

        usleep(1050000);
        $payload = $client->request('FreightMonitor_FREIGHTSBYPACKET', ['CATCLAIM' => $claim]);
        $routes = is_array($payload['routes'] ?? null) ? $payload['routes'] : null;
        if (!is_array($routes) || count($routes) < 1 || count($routes) > 6) {
            throw new RuntimeException('FREIGHT_REF_BINDING_ROUTES');
        }
        $routeKeys = [];
        foreach ($routes as $routeIndex => $route) {
            if (!is_array($route) || !is_array($route['freights'] ?? null)) {
                throw new RuntimeException('FREIGHT_REF_BINDING_ROUTE_SHAPE');
            }
            $keys = [];
            foreach (array_slice($route['freights'], 0, 60) as $freight) {
                if (!is_array($freight)) throw new RuntimeException('FREIGHT_REF_BINDING_ROUTE_SHAPE');
                $key = anytour_anex_normalizer_id($freight['key'] ?? null);
                if ($key !== null) $keys[$key] = $key;
            }
            if (!$keys) throw new RuntimeException('FREIGHT_REF_BINDING_ROUTE_KEYS');
            $routeKeys[] = array_values($keys);
        }
        $outbound = (string) $selected['refs']['outbound'];
        $return = (string) $selected['refs']['return'];
        $outboundMatches = [];
        $returnMatches = [];
        foreach ($routeKeys as $routeIndex => $keys) {
            if (in_array($outbound, $keys, true)) $outboundMatches[] = $routeIndex;
            if (in_array($return, $keys, true)) $returnMatches[] = $routeIndex;
        }
        $ordered = count($routeKeys) >= 2
            && in_array($outbound, $routeKeys[0], true)
            && in_array($return, $routeKeys[1], true);
        $reversed = count($routeKeys) >= 2
            && in_array($return, $routeKeys[0], true)
            && in_array($outbound, $routeKeys[1], true);
        $out['selected_concrete'] = $selected['summary'];
        $out['ref_binding'] = [
            'searchtour_refs' => ['outbound' => $outbound, 'return' => $return],
            'freightmonitor_route_keys' => $routeKeys,
            'outbound_route_indexes' => $outboundMatches,
            'return_route_indexes' => $returnMatches,
            'both_refs_exist_in_freightmonitor' => $outboundMatches !== [] && $returnMatches !== [],
            'ordered_route_key_match' => $ordered,
            'reversed_route_key_match' => $reversed,
            'freight_key_namespace_match_verified' => $ordered || $reversed,
        ];
        $out['anex_requests'] = $client->requestsMade();
        $out['status'] = 'completed';
        $out['supplier_effect'] = 'read_only_search_expand_freight_ref_binding_completed';
        $out['reused'] = false;
        anex_concrete_fuel_save($path, [
            'schema_version' => 1,
            'experiment_id' => ANEX_FREIGHT_REF_BINDING_EXPERIMENT,
            'status' => 'completed',
            'completed_at' => gmdate('c'),
            'result' => $out,
        ]);
        $reserved = false;
    } catch (Throwable $e) {
        $code = $e->getMessage();
        $safe = preg_match('/\AFREIGHT_REF_BINDING_[A-Z0-9_]{1,90}\z/D', $code)
            ? $code : 'FREIGHT_REF_BINDING_UNCONFIRMED';
        $out['status'] = $reserved ? 'unknown' : 'blocked';
        $out['reason'] = $safe;
        $out['supplier_effect'] = $reserved ? 'unknown' : 'none';
        if ($reserved && is_string($path)) {
            try {
                anex_concrete_fuel_save($path, [
                    'schema_version' => 1,
                    'experiment_id' => ANEX_FREIGHT_REF_BINDING_EXPERIMENT,
                    'status' => 'unknown',
                    'reason' => $safe,
                ]);
            } catch (Throwable $ignored) {
            }
        }
    } finally {
        if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
        if ($client instanceof AnyTourAnexClient) $out['anex_requests'] = $client->requestsMade();
        $out['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);
    }
    $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    foreach ($secrets as $secret) {
        if ($secret !== '' && is_string($json) && strpos($json, $secret) !== false) {
            return [
                'schema_version' => 1,
                'experiment_id' => ANEX_FREIGHT_REF_BINDING_EXPERIMENT,
                'status' => 'unknown',
                'reason' => 'FREIGHT_REF_BINDING_OUTPUT_REDACTED',
                'automatic_retry' => false,
                'supplier_replay_allowed' => false,
            ];
        }
    }
    return $out;
}
'''


def php_source():
    root = Path(__file__).resolve().parents[2]
    diag = root / 'scripts' / 'diagnostics'
    paired = concrete.php_body(diag / 'anex_search3_paired_runner.php', False)
    client = concrete.php_body(root / 'app' / 'integrations' / 'anex-client.php')
    normalizer = concrete.php_body(root / 'app' / 'integrations' / 'anex-normalizer.php')
    search = concrete.php_body(root / 'app' / 'integrations' / 'anex-search.php')
    search = '\n'.join(line for line in search.splitlines() if not line.startswith('require_once __DIR__')) + '\n'
    registry = concrete.php_body(root / 'app' / 'integrations' / 'anex-search-mapping-registry.php')
    binding = concrete.php_body(diag / 'anex_concrete_fuel_binding.php')
    return (
        "declare(strict_types=1);\n"
        "define('ANYTOUR_ANEX_PAIRED_LIBRARY_ONLY', true);\n"
        "define('ANYTOUR_ANEX_CONCRETE_FUEL_LIBRARY_ONLY', true);\n"
        + paired + '\n' + client + '\n' + normalizer + '\n' + search + '\n' + registry + '\n' + binding + '\n' + PHP
        + '\n$report=anex_freight_ref_binding_main();'
          'echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\\n";'
          'exit(($report["status"]??null)==="completed"?0:1);'
    )


def _positive_id(value):
    return isinstance(value, str) and value.isdigit() and value == str(int(value)) and int(value) > 0


def validate(value):
    if not isinstance(value, dict) or value.get('schema_version') != 1 or value.get('experiment_id') != EXPERIMENT:
        raise ValueError('freight_ref_binding_result')
    if value.get('supplier_replay_allowed') is not False or value.get('automatic_retry') is not False:
        raise ValueError('freight_ref_binding_replay')
    for key in ('additional_prices_requests', 'tourvisor_requests', 'andromeda_requests', 'booking_calls', 'broninit_calls', 'mapping_writes'):
        if value.get(key) != 0:
            raise ValueError('freight_ref_binding_effect')
    if value.get('production_price_arithmetic_applied') is not False or value.get('additional_prices_tour_binding_verified') is not False:
        raise ValueError('freight_ref_binding_money_boundary')
    if value.get('selected_transport_verified') is not False:
        raise ValueError('freight_ref_binding_transport_boundary')
    if value.get('status') == 'completed':
        if value.get('supplier_effect') != 'read_only_search_expand_freight_ref_binding_completed':
            raise ValueError('freight_ref_binding_effect_name')
        if not (1 <= int(value.get('anex_requests', 0)) <= 6):
            raise ValueError('freight_ref_binding_budget')
        selected = value.get('selected_concrete') or {}
        if selected.get('kind') != 'concrete' or 'supplier_offer_id' in selected or 'offer_key' in selected:
            raise ValueError('freight_ref_binding_selected')
        binding = value.get('ref_binding')
        if not isinstance(binding, dict):
            raise ValueError('freight_ref_binding_missing')
        refs = binding.get('searchtour_refs')
        if not isinstance(refs, dict) or set(refs) != {'outbound', 'return'} or not all(_positive_id(x) for x in refs.values()):
            raise ValueError('freight_ref_binding_refs')
        route_keys = binding.get('freightmonitor_route_keys')
        if not isinstance(route_keys, list) or not (1 <= len(route_keys) <= 6):
            raise ValueError('freight_ref_binding_routes')
        for keys in route_keys:
            if not isinstance(keys, list) or not keys or len(keys) > 60 or not all(_positive_id(x) for x in keys):
                raise ValueError('freight_ref_binding_route_keys')
        for field in ('outbound_route_indexes', 'return_route_indexes'):
            indexes = binding.get(field)
            if not isinstance(indexes, list) or any(not isinstance(x, int) or x < 0 or x >= len(route_keys) for x in indexes):
                raise ValueError('freight_ref_binding_indexes')
        encoded = json.dumps(value, ensure_ascii=False).lower()
        for forbidden in ('catclaim', 'oauth_token', 'anex_api_token', 'https://parser.anextour.ru'):
            if forbidden in encoded:
                raise ValueError('freight_ref_binding_sensitive')
    return value


def summarize(value):
    return {
        'schema_version': 1,
        'experiment_id': EXPERIMENT,
        'status': value.get('status'),
        'supplier_replay_allowed': False,
        'production_price_arithmetic_applied': False,
        'additional_prices_tour_binding_verified': False,
        'selected_transport_verified': False,
        'anex_requests': value.get('anex_requests'),
        'selected_concrete': value.get('selected_concrete'),
        'ref_binding': value.get('ref_binding'),
        'note': 'Exact same-CATCLAIM opaque ID comparison only; no B2B/TV/Andromeda/booking and no price arithmetic.',
    }


def save(path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix('.tmp')
    tmp.write_text(json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n')
    tmp.replace(path)


def main():
    if len(sys.argv) != 2:
        raise SystemExit('usage: anex_freight_ref_binding.py OUTPUT_DIR')
    out = Path(sys.argv[1])
    try:
        value = validate(transport.ssh_php_no_mux(php_source(), SPEC, maximum_bytes=4_000_000))
        save(out / 'result.json', value)
        report = summarize(value)
        save(out / 'report.json', report)
        print(json.dumps(report, ensure_ascii=False, sort_keys=True))
        raise SystemExit(0 if value.get('status') == 'completed' else 1)
    except SystemExit:
        raise
    except Exception as exc:
        failure = {
            'status': 'unconfirmed',
            'error_kind': type(exc).__name__,
            'automatic_retry': False,
            'supplier_replay_requested': False,
        }
        try:
            save(out / 'failure.json', failure)
        except Exception:
            pass
        print(json.dumps(failure, sort_keys=True))
        raise SystemExit(1)


if __name__ == '__main__':
    main()
