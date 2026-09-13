#!/usr/bin/env python3
"""Validate an immutable saved result from the retired ANEX freight-ref probe.

This diagnostic is intentionally supplier-free. The original one-shot runtime path is
sealed/no-replay after the 2026-09-13 incident and must not be reintroduced here.
"""
from __future__ import annotations

import hashlib
import json
from pathlib import Path
import sys

EXPERIMENT = 'anex_freight_ref_binding_20260913_v1'
SEALED_EVIDENCE = {
    'run_id': 34741215794,
    'artifact_id': 10312404322,
    'artifact_sha256': 'a2851f86f0bc0db2d68f179e403cf4e2aad5958d5789e764496503609782f699',
    'result_sha256': '8f59f55a58ac84bb268a5f94924fad3d1093d4379ab0af84670547aae6b09e0f',
    'disposition': 'sealed_prohibited_replay_not_new_p0_evidence',
}
ZERO_EFFECT_FIELDS = (
    'additional_prices_requests',
    'tourvisor_requests',
    'andromeda_requests',
    'booking_calls',
    'broninit_calls',
    'mapping_writes',
)
FORBIDDEN_SERIALIZED_MARKERS = (
    'catclaim',
    'oauth_token',
    'anex_api_token',
    'https://parser.anextour.ru',
    'authorization',
    'bearer ',
)


def _positive_id(value):
    return isinstance(value, str) and value.isdigit() and value == str(int(value)) and int(value) > 0


def validate(value):
    if not isinstance(value, dict) or value.get('schema_version') != 1 or value.get('experiment_id') != EXPERIMENT:
        raise ValueError('freight_ref_binding_result')
    if value.get('supplier_replay_allowed') is not False or value.get('automatic_retry') is not False:
        raise ValueError('freight_ref_binding_replay')
    for key in ZERO_EFFECT_FIELDS:
        if value.get(key) != 0:
            raise ValueError('freight_ref_binding_effect')
    if value.get('production_price_arithmetic_applied') is not False or value.get('additional_prices_tour_binding_verified') is not False:
        raise ValueError('freight_ref_binding_money_boundary')
    if value.get('selected_transport_verified') is not False:
        raise ValueError('freight_ref_binding_transport_boundary')

    status = value.get('status')
    if status not in {'completed', 'unknown', 'blocked'}:
        raise ValueError('freight_ref_binding_status')
    try:
        requests = int(value.get('anex_requests', -1))
    except (TypeError, ValueError):
        raise ValueError('freight_ref_binding_budget') from None
    if not (0 <= requests <= 6):
        raise ValueError('freight_ref_binding_budget')

    if status == 'completed':
        if value.get('supplier_effect') != 'read_only_search_expand_freight_ref_binding_completed' or requests < 1:
            raise ValueError('freight_ref_binding_effect_name')
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
    else:
        if value.get('supplier_effect') not in {'unknown', 'none', None}:
            raise ValueError('freight_ref_binding_unknown_effect')
        if value.get('selected_concrete') is not None or value.get('ref_binding') is not None:
            raise ValueError('freight_ref_binding_unknown_partial')

    encoded = json.dumps(value, ensure_ascii=False).lower()
    for forbidden in FORBIDDEN_SERIALIZED_MARKERS:
        if forbidden in encoded:
            raise ValueError('freight_ref_binding_sensitive')
    return value


def summarize(value):
    validate(value)
    return {
        'schema_version': 1,
        'experiment_id': EXPERIMENT,
        'status': value.get('status'),
        'reason': value.get('reason'),
        'supplier_replay_allowed': False,
        'production_price_arithmetic_applied': False,
        'additional_prices_tour_binding_verified': False,
        'selected_transport_verified': False,
        'anex_requests': value.get('anex_requests'),
        'selected_concrete': value.get('selected_concrete'),
        'ref_binding': value.get('ref_binding'),
        # Semantic validity alone never proves origin from the sealed artifact.
        'sealed_evidence': None,
        'note': 'Request counts describe the saved result, not this read. Saved-result validation only; no supplier/SSH/DB calls, no replay, no B2B/TV/Andromeda/booking and no price arithmetic.',
    }


def load_saved(path):
    """Return a report attributed only when the single input buffer matches the pin."""
    path = Path(path)
    if not path.is_file():
        raise ValueError('freight_ref_binding_saved_result_missing')
    try:
        raw = path.read_bytes()
        value = json.loads(raw.decode('utf-8'))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise ValueError('freight_ref_binding_saved_result_invalid') from exc
    report = summarize(value)
    report['input_sha256'] = hashlib.sha256(raw).hexdigest()
    if report['input_sha256'] == SEALED_EVIDENCE['result_sha256']:
        report['sealed_evidence'] = dict(SEALED_EVIDENCE)
    return report


def main(argv=None):
    args = list(sys.argv[1:] if argv is None else argv)
    if len(args) != 1:
        raise SystemExit('usage: anex_freight_ref_binding.py SAVED_RESULT_JSON')
    try:
        report = load_saved(args[0])
    except ValueError as exc:
        print(json.dumps({'status': 'invalid_saved_result', 'reason': str(exc)}, sort_keys=True))
        raise SystemExit(1)
    print(json.dumps(report, ensure_ascii=False, sort_keys=True))


if __name__ == '__main__':
    main()
