"""Bind proposed targets to saved review evidence; never emit accepted identities."""
import argparse
import hashlib
import json
from pathlib import Path

REVIEW_SHA256 = '54c6b1cb4a36eb3ab1ca09aac52574f2758ed08308a620c2db0c6d1eb0c864a1'


def build(review, decisions):
    for value in (review, decisions):
        if (value.get('provider') != 'andromeda' or value.get('accepted_mappings') != 0
                or value.get('selection_enabled') is not False):
            raise ValueError('review must remain unresolved and disabled')
    if decisions.get('review_sha256') != REVIEW_SHA256 or decisions.get('status') != 'proposed_not_accepted':
        raise ValueError('unexpected review lineage')
    sources = {}
    for row in review['rows']:
        source = row['source']
        key = (source['supplier_namespace'], source['external_hotel_id'])
        if key in sources or key[0] != 'andromeda_catalog' or source.get('local_hotel_id') is not None:
            raise ValueError('invalid review identity')
        sources[key] = row
    if len(sources) != 14 or len(decisions['rows']) != 14:
        raise ValueError('unexpected dossier count')
    output, seen = [], set()
    for decision in decisions['rows']:
        key = (decision['supplier_namespace'], decision['external_hotel_id'])
        if key not in sources or key in seen or not decision.get('reason'):
            raise ValueError('invalid or duplicate decision identity')
        seen.add(key)
        status, target = decision['decision_status'], decision['proposed_catalog_hotel_id']
        if status not in ('proposed', 'quarantined'):
            raise ValueError('shortlist cannot accept identities')
        candidates = sources[key]['candidates']
        if status == 'proposed':
            if type(target) is not int or target not in [c['id'] for c in candidates]:
                raise ValueError('proposed target absent from saved candidates')
            if key[1] == '2000073714':
                raise ValueError('Empire catalog/offer conflict requires new supplier evidence')
        elif target is not None:
            raise ValueError('quarantined identity cannot have a proposed target')
        raw = json.dumps(sources[key], ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode()
        output.append({**sources[key], 'decision_status': status, 'local_hotel_id': None,
                       'proposal': decision, 'source_row_sha256': hashlib.sha256(raw).hexdigest()})
    return {'schema_version': 1, 'provider': 'andromeda', 'state': 'awaiting_identity_acceptance',
            'review_sha256': REVIEW_SHA256, 'accepted_mappings': 0, 'selection_enabled': False,
            'supplier_calls': 0, 'database_calls': 0, 'resolver_rows': [],
            'counts': {'proposed': sum(r['decision_status'] == 'proposed' for r in output),
                       'quarantined': sum(r['decision_status'] == 'quarantined' for r in output),
                       'proposed_offer_count': sum(r['source']['offer_count'] for r in output
                                                   if r['decision_status'] == 'proposed')},
            'corroboration': decisions['corroboration'], 'rows': output}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ('review', 'decisions', 'output'):
        parser.add_argument('--' + name, required=True, type=Path)
    args = parser.parse_args()
    raw = args.review.read_bytes()
    if hashlib.sha256(raw).hexdigest() != REVIEW_SHA256:
        raise ValueError('saved review digest mismatch')
    result = build(json.loads(raw), json.loads(args.decisions.read_bytes()))
    encoded = (json.dumps(result, ensure_ascii=False, indent=2, sort_keys=True) + '\n').encode()
    with args.output.open('xb') as stream:
        stream.write(encoded)
    if args.output.read_bytes() != encoded:
        raise ValueError('shortlist readback failed')
    print(json.dumps({'counts': result['counts'], 'accepted_mappings': 0, 'resolver_rows': [],
                      'sha256': hashlib.sha256(encoded).hexdigest()}))


if __name__ == '__main__':
    main()
