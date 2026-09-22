#!/usr/bin/env python3
"""Capture missing BG geography once; classify retained hotel evidence without writes."""
from __future__ import annotations
import base64
import collections
import datetime
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import signal
import subprocess
import sys
import urllib.request
import zipfile

OP = 'hotel-match-bg-geography-1971-20260922-v1'
CLAIM = 5774488492
REPO = 'pyatkoff/poisk-turov-test'
BASE = '740bc609d110ac37ad32233b4ad205244ab5e39e'
LEGACY_REF = '56b1bf4aea12bc04e23b4c31f1bdaeae287a64b2'
LEGACY_PATH = 'scripts/diagnostics/hotel_match_bg_dictionary_v5.py'
LEGACY_HASH = '6d9d51b95380e6a99376498be62c09617eac0c789a4598e89e596d33a91156d5'
BG_PIN = (10684769853, '0f3f82ba6716155bbfc74a44c7b90df7bca88a944864450b73b1b8893b73a3bf',
          'b52da9feaa87caea3800b6434fbcaf75d7fc21ff8742824074cef02632758964')
URLS = {'countries': 'https://export.bgoperator.ru/yandex?action=countries',
        'cities': 'https://export.bgoperator.ru/auto/jsonResorts.json'}
GEO_FIELDS = ('id', 'title_ru', 'title_en', 'country', 'code', 'alpha3')


def sha(data):
    return hashlib.sha256(data).hexdigest()


def check(ok, message):
    if not ok:
        raise ValueError(message)


def now():
    return datetime.datetime.now(datetime.timezone.utc).isoformat()


def save(path, value):
    data = (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()
    with Path(path).open('xb') as handle:
        handle.write(data)
        handle.flush()
        os.fsync(handle.fileno())
    return sha(data)


def read(path):
    return json.loads(Path(path).read_bytes())


def api(path):
    return json.loads(subprocess.check_output(['gh', 'api', 'repos/' + REPO + '/' + path], timeout=45))


def helper(download=False):
    path = Path('input/bg-helper.py')
    if download:
        raw = base64.b64decode(api('contents/' + LEGACY_PATH + '?ref=' + LEGACY_REF)['content'])
        check(sha(raw) == LEGACY_HASH, 'helper_sha')
        with path.open('xb') as handle:
            handle.write(raw)
    check(sha(path.read_bytes()) == LEGACY_HASH, 'helper_sha')
    spec = importlib.util.spec_from_file_location('bg_pinned_helper', path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def norm(text):
    # Punctuation/case only. No removal of GRAND/FAMILY/BEACH/buildings or numerals.
    return ' '.join(re.findall(r'[^\W_]+', str(text or '').casefold().replace('ё', 'е'), re.UNICODE))


def star(text):
    match = re.fullmatch(r'\s*([1-5])\s*\*?\s*', str(text or ''))
    return int(match[1]) if match else None


def geo_index(data):
    check(isinstance(data, list), 'geography_not_array')
    result = {}
    for row in data:
        check(isinstance(row, dict), 'geography_row_not_object')
        key = str(row.get('id', ''))
        check(re.fullmatch(r'[1-9][0-9]{3,17}', key) is not None, 'geography_missing_id')
        check(key not in result, 'duplicate_geography_id_' + key)
        check(bool(row.get('title_ru') or row.get('title_en')), 'geography_missing_title')
        result[key] = row
    return result


def geo_labels(row):
    return {norm(row.get(k)) for k in ('title_ru', 'title_en') if row.get(k)}


def classify(bg, dossier, country_data, city_data):
    countries, cities = geo_index(country_data), geo_index(city_data)
    old_by_tv = {x['tv_hotel_id']: x for x in dossier['rows']}
    check(len(old_by_tv) == len(bg['rows']), 'dossier_membership_length')
    rows = []
    counts = collections.Counter()
    for original in bg['rows']:
        tv = original['tv_hotel_id']
        old = old_by_tv[tv]
        check(original['f4_candidates'] == old['f4_candidates'], 'f4_membership')
        check(original['operator_link_sha256'] == old['operator_link_sha256'], 'link_provenance')
        local = old['catalog_hotel']
        local_geos = {norm(local.get(k)) for k in ('region_name', 'subregion_name') if local.get(k)}
        per_key = []
        for hotel in original['dictionary_rows']:
            country = countries.get(str(hotel.get('countryKey')))
            city = cities.get(str(hotel.get('cityKey')))
            country_match = bool(country and norm(local.get('country_name')) in geo_labels(country))
            parent_match = bool(country and city and str(city.get('country')) == str(hotel.get('countryKey')))
            city_match = bool(city and local_geos.intersection(geo_labels(city)))
            name_match = bool(norm(hotel.get('name')) and norm(hotel.get('name')) == norm(local.get('name')))
            star_match = star(hotel.get('stars')) is not None and star(hotel.get('stars')) == star(local.get('category'))
            reasons = []
            if not country: reasons.append('bg_country_id_missing')
            elif not country_match: reasons.append('local_country_label_requires_review')
            if not city: reasons.append('bg_city_id_missing')
            elif not parent_match: reasons.append('bg_city_country_conflict')
            if city and not city_match: reasons.append('specific_city_or_subregion_not_exact')
            if not name_match: reasons.append('name_or_qualifier_not_exact')
            if not star_match: reasons.append('category_not_exact')
            geo_ok = country_match and parent_match and city_match
            per_key.append({'native_namespace': 'bgoperator', 'native_id': str(hotel['key']),
                            'hotel': hotel, 'official_country': country, 'official_city': city,
                            'country_matches_local': country_match, 'city_country_consistent': parent_match,
                            'specific_geo_exact': city_match, 'geography_supported': geo_ok,
                            'name_exact': name_match, 'category_exact': star_match, 'reasons': reasons})
        structural = len(old['f4_candidates']) == 1 and len(old['accepted_catalog_ids']) == 1 and not old['manual']
        exact = bool(structural and len(per_key) == 1 and per_key[0]['geography_supported']
                     and per_key[0]['name_exact'] and per_key[0]['category_exact'] and local['is_active'] == 1)
        counts['hotels_examined'] += 1
        counts['official_keys_examined'] += len(per_key)
        counts['hotels_with_all_geo_ids_found'] += int(all(x['official_country'] and x['official_city'] for x in per_key))
        counts['hotels_with_consistent_city_country'] += int(all(x['city_country_consistent'] for x in per_key))
        counts['hotels_with_exact_specific_geography'] += int(all(x['geography_supported'] for x in per_key))
        counts['single_code_single_anchor_no_manual'] += int(structural)
        counts['strict_evidence_candidates'] += int(exact)
        holds = []
        if len(old['f4_candidates']) != 1: holds.append('dual_raw_f4_preserved')
        if len(old['accepted_catalog_ids']) != 1: holds.append(old['anchor_class'])
        if old['manual']: holds.append('manual_preserved')
        if not exact: holds.append('additional_identity_evidence_needed')
        # No write path exists here. Official BG native keys are not SAMO original keys.
        holds.extend(['bgoperator_to_operator115_namespace_not_proven', 'current_writer_checks_not_performed'])
        rows.append({'tv_hotel_id': tv, 'f4_candidates': original['f4_candidates'],
                     'operator_link': original['operator_link'], 'operator_link_sha256': original['operator_link_sha256'],
                     'search_id': original['search_id'], 'tour_id': original['tour_id'],
                     'catalog_hotel': local, 'accepted_catalog_ids': old['accepted_catalog_ids'],
                     'canonical_evidence': old['identities'], 'manual': old['manual'], 'official_evidence': per_key,
                     'strict_evidence_candidate': exact, 'holds': holds, 'safe_to_write_now': False})
    return {'schema': 'bg-official-geography-evidence/1', 'operation': OP, 'counts': dict(counts), 'rows': rows,
            'hotel_dictionary_refetched': False, 'database_writes': 0, 'mapping_writes': 0,
            'geographic_rule': 'exact official ru/en city label matches local region or subregion with same country',
            'supplier_namespace_promoted': False, 'safe_to_write_now': False,
            'source_dossier_at': dossier.get('snapshot_at_utc'), 'no_replay': True}


def prepare():
    for directory in ('input', 'reservation', 'terminal'):
        Path(directory).mkdir()
    claim = api('issues/comments/' + str(CLAIM))
    check(claim['user']['id'] == 226193297 and claim['author_association'] == 'OWNER'
          and claim['issue_url'].endswith('/issues/2530'), 'claim_owner')
    for text in (OP, 'максимум2', 'SSH0/DB0/mappingwrites0', 'Не возвращаюсь к cleanup'):
        check(text in claim['body'], 'claim_scope')
    lib = helper(download=True)
    lib.artifact('dossier')
    lib.artifact('queue')
    aid, archive_hash, result_hash = BG_PIN
    raw = subprocess.check_output(['gh', 'api', f'repos/{REPO}/actions/artifacts/{aid}/zip'], timeout=60)
    check(sha(raw) == archive_hash, 'dictionary_archive_sha')
    z = zipfile.ZipFile(io.BytesIO(raw))
    data = z.read('result.json')
    check(sha(data) == result_hash and json.loads(z.read('receipt.json'))['result_sha256'] == result_hash, 'dictionary_result_sha')
    bg = json.loads(data)
    check(bg['state'] == 'completed_dictionary_read' and bg['matched_f4'] == 820 and len(bg['rows']) == 816, 'dictionary_input')
    check(bg['source_sha'] == LEGACY_REF and bg['missing_f4'] and len(bg['missing_f4']) == 18, 'dictionary_provenance')
    save('input/bg.json', bg)
    save('reservation/reservation.json', {'operation': OP, 'source_sha': os.environ['HEAD'], 'base_sha': BASE,
         'at_utc': now(), 'claim_sha256': sha(claim['body'].encode()), 'urls': URLS, 'supplier_http_budget': 2,
         'bg_artifact': BG_PIN, 'helper_sha256': LEGACY_HASH, 'no_hotels_refetch': True,
         'database_writes': 0, 'mapping_writes': 0, 'no_replay': True})


def acquire(kind):
    check(kind in URLS, 'unknown_geography_kind')
    lib = helper()
    lib.security()
    proof = lib.capability()
    # Also catch new MATCH-BG names that the pinned legacy selector predates.
    for status in ('queued', 'in_progress'):
        listing = lib.api('actions/runs?status=' + status + '&per_page=100')
        check(listing['total_count'] <= 100, 'additional_queue_pagination')
        for run in listing['workflow_runs']:
            if run['id'] == int(os.environ['GITHUB_RUN_ID']): continue
            label = ' '.join(str(run.get(k, '')) for k in ('name', 'path', 'head_branch')).lower()
            if any(x in label for x in ('match-bg', 'match bg', 'biblio', 'bgoperator')):
                raise ValueError('concurrent_bg_operation_' + str(run['id']))
    save('terminal/' + kind + '-capability.json', proof)
    save('terminal/' + kind + '-http-reservation.json', {'operation': OP, 'kind': kind, 'url': URLS[kind],
         'at_utc': now(), 'source_sha': os.environ['HEAD'], 'reserved_http_requests': 1, 'no_replay': True})
    result = {'operation': OP, 'kind': kind, 'url': URLS[kind], 'at_utc': now(), 'supplier_http_requests': 1,
              'database_writes': 0, 'mapping_writes': 0, 'no_replay': True}
    def deadline(signum, frame):
        raise TimeoutError('geography_request_deadline')
    signal.signal(signal.SIGALRM, deadline)
    signal.alarm(30)
    try:
        request = urllib.request.Request(URLS[kind], headers={'Accept-Encoding': 'gzip', 'Accept': 'application/json',
                                                            'User-Agent': 'AnyTour-MATCH-geography/1'})
        with urllib.request.build_opener(lib.NoRedirect()).open(request, timeout=30) as response:
            result['http_status'] = response.status
            check(response.status == 200, 'geography_http_status')
            wire = response.read(lib.CAP + 1)
            body = lib.inflate(wire, response.headers.get('Content-Encoding', '').strip().lower())
        signal.alarm(0)
        result.update(wire_bytes=len(wire), wire_sha256=sha(wire), decoded_bytes=len(body), response_sha256=sha(body))
        data = json.loads(body.decode('utf-8-sig'))
        # Preserve completed public geographic data before semantic validation; no refetch on a parser failure.
        result['data_sha256'] = save('terminal/' + kind + '-data.json', data)
        index = geo_index(data)
        result.update(state='completed_geography_read', dictionary_rows=len(index))
    except Exception as error:
        signal.alarm(0)
        result.update(state='failed_no_replay', error_type=type(error).__name__, error=str(error)[:200])
    save('terminal/' + kind + '-result.json', result)
    print(json.dumps(result, ensure_ascii=False))
    check(result['state'] == 'completed_geography_read', 'geography_operation_failed')


def analyze():
    output = classify(read('input/bg.json'), read('input/dossier.json'),
                      read('terminal/countries-data.json'), read('terminal/cities-data.json'))
    output['source_sha'] = os.environ.get('HEAD')
    output['at_utc'] = now()
    output['input_sha256'] = {p: sha(Path(p).read_bytes()) for p in
                            ('input/bg.json', 'input/dossier.json', 'terminal/countries-data.json', 'terminal/cities-data.json')}
    save('terminal/geography-candidates.json', output)
    print(json.dumps(output['counts'], ensure_ascii=False))


def finish():
    Path('terminal').mkdir(exist_ok=True)
    attempts, unknown = 0, []
    for kind in URLS:
        reservation = Path('terminal/' + kind + '-http-reservation.json')
        result = Path('terminal/' + kind + '-result.json')
        if result.exists(): attempts += read(result)['supplier_http_requests']
        elif reservation.exists(): unknown.append(kind)
    state = 'completed_geography_evidence' if Path('terminal/geography-candidates.json').exists() else ('access_unknown' if unknown else 'incomplete_no_replay')
    files = {p.name: sha(p.read_bytes()) for p in Path('terminal').iterdir() if p.is_file()}
    save('terminal/receipt.json', {'operation': OP, 'source_sha': os.environ['HEAD'], 'state': state,
         'supplier_http_requests': None if unknown else attempts, 'known_attempted_requests': attempts,
         'unknown_requests': unknown, 'files_sha256': files, 'database_writes': 0, 'mapping_writes': 0, 'no_replay': True})


def tests():
    c = [{'id': '100410000047', 'title_ru': 'Египет', 'title_en': 'Egypt'}]
    town = [{'id': '100500000001', 'title_ru': 'Хургада', 'title_en': 'Hurghada', 'country': '100410000047'}]
    old = {'tv_hotel_id': 10, 'f4_candidates': ['102610000001'], 'operator_link': 'link', 'operator_link_sha256': 'digest',
           'search_id': '1', 'tour_id': '2', 'accepted_catalog_ids': ['2000000001'], 'manual': [], 'identities': [],
           'anchor_class': 'single_accepted_catalog', 'catalog_hotel': {'name': 'Hotel Family', 'country_name': 'Египет',
           'region_name': 'Хургада', 'subregion_name': 'Макади', 'category': 3, 'is_active': 1}}
    b = dict(old, dictionary_rows=[{'key': '102610000001', 'name': 'HOTEL FAMILY', 'stars': '3*', 'countryKey': c[0]['id'], 'cityKey': town[0]['id']}])
    def run(): return classify({'rows': [b]}, {'rows': [old]}, c, town)['rows'][0]
    check(run()['strict_evidence_candidate'] is True, 'positive_geo_test')
    check(run()['safe_to_write_now'] is False, 'no_write_test')
    for value in ('Hotel Grand Family', 'Hotel Family Annex', 'Hotel Family 2'):
        b['dictionary_rows'][0]['name'] = value
        check(not run()['strict_evidence_candidate'], 'qualifier_test')
    b['dictionary_rows'][0]['name'] = 'HOTEL FAMILY'
    town[0]['country'] = '100499999999'
    check(not run()['strict_evidence_candidate'], 'geo_country_conflict_test')
    town[0]['country'] = c[0]['id']
    town[0]['title_ru'] = 'Као-Лак'; town[0]['title_en'] = 'Khao Lak'
    check(not run()['strict_evidence_candidate'], 'specific_geo_mismatch_test')
    town[0]['title_ru'] = 'Хургада'
    old['manual'] = [{'decision': 'excluded'}]
    check(not run()['strict_evidence_candidate'], 'manual_test')
    old['manual'] = []
    old['accepted_catalog_ids'].append('2000000002')
    check(not run()['strict_evidence_candidate'], 'multi_anchor_test')
    check(star('4*') == 4 and star('4/5*') is None and norm('GRAND GOSIA') != norm('GOSIA'), 'normalization_tests')
    for bad in ({}, [{'id': 'bad', 'title_ru': 'X'}], c + c):
        try: geo_index(bad)
        except ValueError: pass
        else: raise AssertionError('invalid_geo_accepted')
    print('geography focused tests PASS')


if __name__ == '__main__':
    action = sys.argv[1]
    if action in URLS: acquire(action)
    else: {'prepare': prepare, 'analyze': analyze, 'finish': finish, 'test': tests}[action]()
