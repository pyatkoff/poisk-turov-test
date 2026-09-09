"""Offline unresolved-hotel evidence; never infer or accept an AnyTour mapping."""
import argparse
import hashlib
import json
from pathlib import Path
import re


class EvidenceError(ValueError):
    pass


def require(value, code):
    if not value:
        raise EvidenceError(code)


def identifier(value):
    require(type(value) in (int, str), 'INVALID_ID')
    value = str(value)
    require(re.fullmatch(r'[A-Za-z0-9_-]{1,128}', value), 'INVALID_ID')
    return value


def label(value):
    require(isinstance(value, str) and 0 < len(value.encode('utf-8')) <= 4096
            and not re.search(r'[\x00-\x1f\x7f]', value), 'INVALID_LABEL')
    return value


def build(page, catalog):
    require(isinstance(page, dict) and page.get('provider') == 'andromeda'
            and page.get('selection_enabled') is False, 'INVALID_PAGE')
    require(type(page.get('generation')) is int and page['generation'] > 0, 'INVALID_GENERATION')
    search = identifier(page.get('search_ref'))
    offers = page.get('offers')
    require(isinstance(offers, list) and len(offers) <= 2000, 'INVALID_OFFERS')
    require(isinstance(page.get('rejected'), list), 'INVALID_REJECTED')
    require(page.get('status') in ('complete', 'partial'), 'INVALID_STATUS')
    require(type(page.get('page')) is int and page['page'] >= 1
            and type(page.get('pages_count')) is int and page['pages_count'] >= 0
            and (page['pages_count'] == 0 or page['page'] <= page['pages_count'])
            and (page['pages_count'] != 0 or not offers), 'INVALID_PAGINATION')
    require(isinstance(catalog, dict) and isinstance(catalog.get('params'), dict)
            and isinstance(catalog.get('payload'), dict), 'INVALID_CATALOG')
    country = identifier(catalog['params'].get('STATEINC'))
    departure = identifier(catalog['params'].get('TOWNFROMINC'))
    hotels = catalog['payload'].get('HOTELS')
    require(isinstance(hotels, list) and len(hotels) <= 50000, 'INVALID_HOTELS')
    index = {}
    for hotel in hotels:
        require(isinstance(hotel, dict), 'INVALID_HOTEL')
        key = identifier(hotel.get('id'))
        require(key not in index, 'DUPLICATE_CATALOG_ID')
        actual_country = identifier(hotel.get('stateKey'))
        safe = {'id': key, 'name': label(hotel.get('name')), 'state_key': actual_country}
        for source, target in [('lName', 'latin_name'), ('state', 'country'),
                               ('stateLName', 'country_latin'), ('town', 'town'),
                               ('townLName', 'town_latin'), ('star', 'star')]:
            if hotel.get(source) not in (None, ''):
                safe[target] = label(str(hotel[source])) if type(hotel[source]) is int else label(hotel[source])
        if hotel.get('townKey') is not None:
            safe['town_key'] = identifier(hotel['townKey'])
        index[key] = safe

    groups = {}
    seen = set()
    for offer in offers:
        require(isinstance(offer, dict) and offer.get('provider') == 'andromeda'
                and offer.get('selection_enabled') is False
                and 'local_hotel_id' in offer and offer['local_hotel_id'] is None, 'INVALID_OFFER')
        require(offer.get('search_ref') == search and type(offer.get('generation')) is int
                and offer['generation'] == page['generation'], 'MIXED_SEARCH')
        operator = identifier(offer.get('operator_ref'))
        namespace = offer.get('supplier_namespace')
        require(namespace in ('andromeda_catalog', 'operator_' + operator), 'INVALID_NAMESPACE')
        external = identifier(offer.get('external_hotel_id'))
        ref = offer.get('offer_ref')
        require(isinstance(ref, str) and re.fullmatch(r'offer_[a-f0-9]{64}', ref)
                and ref not in seen, 'INVALID_OR_DUPLICATE_OFFER_REF')
        seen.add(ref)
        key = (namespace, external)
        if key not in groups:
            # Operator IDs must NEVER be looked up in the Andromeda catalog.
            found = index.get(external) if namespace == 'andromeda_catalog' else None
            status = ('operator_key_excluded' if namespace != 'andromeda_catalog'
                      else 'catalog_missing' if found is None
                      else 'catalog_country_conflict' if found['state_key'] != country
                      else 'catalog_found')
            groups[key] = {'provider': 'andromeda', 'supplier_namespace': namespace,
                           'external_hotel_id': external, 'local_hotel_id': None,
                           'status': status, 'catalog': found, 'observed_names': set(),
                           'operators': set(), 'offer_refs': [], 'decision_status': 'unreviewed'}
        group = groups[key]
        group['observed_names'].add(label(offer.get('hotel')))
        group['operators'].add(operator)
        group['offer_refs'].append(ref)
    rows = []
    for key in sorted(groups):
        row = groups[key]
        row['observed_names'] = sorted(row['observed_names'])
        row['operators'] = sorted(row['operators'])
        row['offer_refs'].sort()
        row['offer_count'] = len(row['offer_refs'])
        rows.append(row)
    statuses = ['catalog_found', 'catalog_missing', 'catalog_country_conflict', 'operator_key_excluded']
    return {'schema_version': 1, 'state': 'completed', 'provider': 'andromeda',
            'search_ref': search, 'generation': page['generation'],
            'source_page': page['page'], 'source_pages_count': page['pages_count'],
            'source_status': page['status'], 'departure_id': departure, 'country_id': country,
            'counts': {'offers': len(offers), 'unique_hotels': len(rows),
                       'catalog_country_conflicts': sum(h['state_key'] != country for h in index.values()),
                       'source_rejected': len(page['rejected']),
                       'hotels_by_status': {s: sum(r['status'] == s for r in rows) for s in statuses},
                       'offers_by_status': {s: sum(r['offer_count'] for r in rows if r['status'] == s) for s in statuses}},
            'rows': rows, 'accepted_mappings': 0, 'selection_enabled': False,
            'supplier_calls': 0, 'database_calls': 0}


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        require(key not in result, 'DUPLICATE_JSON_KEY')
        result[key] = value
    return result


def read_pinned(path, expected):
    require(re.fullmatch(r'[a-f0-9]{64}', expected), 'INVALID_DIGEST')
    with Path(path).open('rb') as stream:
        raw = stream.read(8 * 1024 * 1024 + 1)
    require(len(raw) <= 8 * 1024 * 1024, 'INPUT_TOO_LARGE')
    require(hashlib.sha256(raw).hexdigest() == expected, 'SOURCE_DIGEST_MISMATCH')
    return json.loads(raw, object_pairs_hook=unique_object)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for name in ['normalized', 'normalized-sha256', 'catalog', 'catalog-sha256', 'output']:
        parser.add_argument('--' + name, required=True)
    args = parser.parse_args()
    report = build(read_pinned(args.normalized, args.normalized_sha256),
                   read_pinned(args.catalog, args.catalog_sha256))
    report['input_sha256'] = {'normalized': args.normalized_sha256, 'catalog': args.catalog_sha256}
    data = (json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True) + '\n').encode()
    destination = Path(args.output)
    with destination.open('xb') as stream:
        stream.write(data)
    require(destination.read_bytes() == data, 'OUTPUT_READBACK_MISMATCH')
    print(json.dumps({'counts': report['counts'], 'sha256': hashlib.sha256(data).hexdigest(),
                      'supplier_calls': 0, 'database_calls': 0}, sort_keys=True))


if __name__ == '__main__':
    main()
