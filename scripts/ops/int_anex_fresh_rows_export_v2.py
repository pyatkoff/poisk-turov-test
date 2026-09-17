#!/usr/bin/env python3
from __future__ import annotations
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT))
from scripts.ops import int_anex_fresh_rows_export_v1 as base

EXPERIMENT = 'int_anex_fresh_rows_export_20260917_v2'
SPEC = dict(base.SPEC, experiment_id=EXPERIMENT)
PHP = base.PHP
replacements = [
    ("'int_anex_fresh_rows_export_20260917_v1'", "'int_anex_fresh_rows_export_20260917_v2'"),
    ("[$local['departure_name'],'Москва','Moscow']", "[$local['departure_name']]"),
    ("[$local['country_name'],'Египет','Egypt']", "[$local['country_name']]"),
    ("['RUB','RUR','Рубль','Рубли','Руб']", "['RUB']"),
]
for old, new in replacements:
    if PHP.count(old) != 1:
        raise RuntimeError('fresh_rows_v2_patch_contract')
    PHP = PHP.replace(old, new)


def php_source() -> str:
    client = base.concrete.php_body(ROOT / 'app' / 'integrations' / 'anex-client.php')
    normalizer = base.concrete.php_body(ROOT / 'app' / 'integrations' / 'anex-normalizer.php')
    registry = base.concrete.php_body(ROOT / 'app' / 'integrations' / 'anex-search-mapping-registry.php')
    return "declare(strict_types=1);\n" + client + "\n" + normalizer + "\n" + registry + "\n" + PHP + "\n$report=fresh_main();echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),\"\\n\";exit(($report['status']??null)==='completed'?0:1);"


def main() -> int:
    if len(sys.argv) != 2:
        raise SystemExit('usage: int_anex_fresh_rows_export_v2.py OUTPUT_DIR')
    out = Path(sys.argv[1])
    try:
        value = base.transport.ssh_php_no_mux(php_source(), SPEC, maximum_bytes=4000000)
        base.save(out / 'anex-fresh-full.json', value)
        rows = value.get('concrete_rows') if isinstance(value, dict) and isinstance(value.get('concrete_rows'), list) else []
        flights = value.get('flight_details') if isinstance(value, dict) and isinstance(value.get('flight_details'), list) else []
        compact = {
            'schema_version': 1,
            'experiment_id': EXPERIMENT,
            'status': value.get('status') if isinstance(value, dict) else 'unconfirmed',
            'observed_at': value.get('observed_at') if isinstance(value, dict) else None,
            'scope': value.get('scope') if isinstance(value, dict) else None,
            'supplier_ids': value.get('supplier_ids') if isinstance(value, dict) else None,
            'chosen_group': value.get('chosen_group') if isinstance(value, dict) else None,
            'initial_price_rows': value.get('initial_price_rows') if isinstance(value, dict) else None,
            'mapped_group_candidates': value.get('mapped_group_candidates') if isinstance(value, dict) else None,
            'concrete_count': len(rows),
            'concrete_rows': rows,
            'flight_details': flights,
            'anex_requests': value.get('anex_requests') if isinstance(value, dict) else None,
        }
        base.save(out / 'anex-fresh-concrete-rows.json', compact)
        summary = {'status':compact['status'],'concrete_count':len(rows),'flight_detail_count':len(flights),'anex_requests':compact['anex_requests']}
        base.save(out / 'summary.json', summary)
        print(json.dumps(summary, ensure_ascii=False, sort_keys=True))
        return 0 if compact['status'] == 'completed' else 1
    except Exception as exc:
        failure = {'status':'unconfirmed','error_kind':type(exc).__name__,'automatic_retry':False,'supplier_replay_requested':False}
        try: base.save(out / 'failure.json', failure)
        except Exception: pass
        print(json.dumps(failure, sort_keys=True))
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
