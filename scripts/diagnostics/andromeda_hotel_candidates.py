#!/usr/bin/env python3
"""One-shot read-only candidate capture from pinned Andromeda hotel evidence."""
import argparse
import hashlib
import json
from pathlib import Path

SOURCE_SHA256 = '9b3ec26136ed20bed9366bf3741667ba48a58013fd48e860670c8289ae97c5ce'


def save(path, value, exclusive=False):
    raw = (json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + '\n').encode()
    if exclusive:
        with path.open('xb') as stream:
            stream.write(raw)
    else:
        temporary = path.with_suffix('.tmp')
        temporary.write_bytes(raw)
        temporary.replace(path)
    if path.read_bytes() != raw:
        raise ValueError('checkpoint readback failed')


def plan(evidence):
    if (evidence.get('state') != 'completed' or evidence.get('provider') != 'andromeda'
            or evidence.get('country_id') != '3' or evidence.get('accepted_mappings') != 0
            or evidence.get('selection_enabled') is not False):
        raise ValueError('unexpected evidence scope')
    rows = [r for r in evidence['rows'] if r['status'] == 'catalog_found']
    if len(rows) != 14 or len(evidence['rows']) != 16:
        raise ValueError('unexpected hotel count')
    identities = set()
    queries = []
    for key, row in enumerate(rows, 1):
        identity = (row['supplier_namespace'], row['external_hotel_id'])
        if (identity in identities or identity[0] != 'andromeda_catalog'
                or row['local_hotel_id'] is not None or row['decision_status'] != 'unreviewed'
                or row['catalog']['state_key'] != '3'):
            raise ValueError('invalid unresolved identity')
        identities.add(identity)
        names = []
        for name in [row['catalog'].get('name'), row['catalog'].get('latin_name'), *row['observed_names']]:
            if isinstance(name, str) and name.strip() and name.strip() not in names:
                names.append(name.strip())
        if not names:
            raise ValueError('missing hotel names')
        queries.append({'key': key, 'country_id': 1, 'names': names[:3]})
    return {'schema_version': 1, 'state': 'reserved', 'source_sha256': SOURCE_SHA256,
            'country_scope': {'andromeda': '3', 'anytour': 1}, 'source_rows': rows,
            'accepted_mappings': 0, 'selection_enabled': False, 'supplier_calls': 0,
            'batches': [{'state': 'reserved', 'request': {'mode': 'alias_review', 'queries': queries[i:i+2]}}
                        for i in range(0, len(queries), 2)]}


def validate_response(request, response):
    if not isinstance(response, dict) or response.get('status') != 'ok':
        raise ValueError('candidate reader failed')
    items = response.get('items')
    expected = [q['key'] for q in request['queries']]
    if not isinstance(items, list) or len(items) != len(expected) or sorted(i['key'] for i in items) != expected:
        raise ValueError('candidate response keys mismatch')
    for item in items:
        if (type(item.get('candidate_set_complete')) is not bool
                or type(item.get('alias_set_complete')) is not bool
                or not isinstance(item.get('candidates'), list) or len(item['candidates']) > 4097):
            raise ValueError('candidate response malformed')
        ids = set()
        for candidate in item['candidates']:
            local_id = candidate.get('id')
            if type(local_id) is not int or local_id <= 0 or local_id in ids:
                raise ValueError('invalid candidate identity')
            ids.add(local_id)
    return response


def capture(path, reader):
    checkpoint = json.loads(path.read_bytes())
    if (checkpoint.get('state') != 'reserved' or checkpoint.get('source_sha256') != SOURCE_SHA256
            or len(checkpoint.get('batches', [])) != 7
            or any(b.get('state') != 'reserved' for b in checkpoint['batches'])):
        raise ValueError('capture already started; inspect saved checkpoint, do not replay')
    checkpoint['state'] = 'in_progress'
    for batch in checkpoint['batches']:
        batch['state'] = 'inflight'
        save(path, checkpoint)
        try:
            response = reader(batch['request'])
            batch['response'] = response
            validate_response(batch['request'], response)
        except Exception:
            batch['state'] = 'unknown'
            checkpoint['state'] = 'needs_inspection'
            save(path, checkpoint)
            raise ValueError('candidate read unconfirmed; checkpoint retained, no retry') from None
        batch['state'] = 'completed'
        save(path, checkpoint)
    checkpoint['state'] = 'completed'
    checkpoint['review_rows'] = []
    for batch in checkpoint['batches']:
        for item in batch['response']['items']:
            source = checkpoint['source_rows'][item['key'] - 1]
            checkpoint['review_rows'].append({
                'external_hotel_id': source['external_hotel_id'], 'name': source['catalog']['name'],
                'candidates': item['candidates'], 'decision_status': 'needs_review',
                'candidate_set_complete': item['candidate_set_complete'],
                'alias_set_complete': item['alias_set_complete']})
    save(path, checkpoint)
    return checkpoint


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('operation', choices=['prepare', 'capture'])
    parser.add_argument('--source', type=Path)
    parser.add_argument('--checkpoint', type=Path, required=True)
    args = parser.parse_args()
    if args.operation == 'prepare':
        raw = args.source.read_bytes()
        if hashlib.sha256(raw).hexdigest() != SOURCE_SHA256:
            raise ValueError('source digest mismatch')
        save(args.checkpoint, plan(json.loads(raw)), exclusive=True)
    else:
        import anex_search3_owner_decisions as owner
        source = Path(__file__).with_name('anex_alias_catalog_reader.php').read_text().removeprefix('<?php')
        result = capture(args.checkpoint, lambda request: owner.ssh_php(source, request, maximum_bytes=4000000))
        print(json.dumps({'state': result['state'], 'accepted_mappings': 0,
                          'rows': result['review_rows']}, ensure_ascii=False))
    print('CHECKPOINT_SHA256=' + hashlib.sha256(args.checkpoint.read_bytes()).hexdigest())


if __name__ == '__main__':
    main()
