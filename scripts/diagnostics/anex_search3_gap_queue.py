#!/usr/bin/env python3
"""One resumable, read-only pass over the fixed initial-search gaps (at most 30 IDs)."""
import ast
from collections import Counter
import csv
import hashlib
import io
import json
import os
from pathlib import Path
import shlex
import subprocess
import tempfile
import time

QUEUE_SHA = 'c5d623f234ecc0e3c0495d5464203e0fdcb59a53ae6a0b1c7ba959032b6a4ce9'
BOOTSTRAP_ARTIFACT = 10059642403
CHECKPOINT = 'anex-initial-search-checkpoint.json'
QUEUE_PATH = Path(__file__).resolve().parents[2] / 'docs/integrations/reports/anex-initial-search-gaps-20260908.json'
CLASSES = {'strong_candidate', 'review', 'unmatched', 'source_error', 'protected'}


def digest(value):
    return hashlib.sha256(json.dumps(value, ensure_ascii=False, sort_keys=True,
                                    separators=(',', ':'), allow_nan=False).encode()).hexdigest()


def load_queue(path=QUEUE_PATH):
    raw = Path(path).read_bytes()
    if hashlib.sha256(raw).hexdigest() != QUEUE_SHA:
        raise ValueError('fixed initial-search queue changed')
    queue = json.loads(raw)
    ids = [r['anex_hotel_id'] for r in queue['rows']]
    if len(ids) != 112 or len(set(ids)) != 112 or any(type(i) is not int or i < 1 for i in ids):
        raise ValueError('invalid initial-search queue')
    return queue


def validate_checkpoint(cp, queue):
    allowed = {r['anex_hotel_id'] for r in queue['rows']}
    if (cp.get('schema_version') != 1 or cp.get('queue_sha256') != QUEUE_SHA
            or cp.get('scope') != 'preview' or not isinstance(cp.get('rows'), list)
            or not isinstance(cp.get('in_flight'), list)):
        raise ValueError('invalid gap checkpoint')
    ids = [r.get('external_id') for r in cp['rows']]
    pending = cp['in_flight']
    if (len(set(ids)) != len(ids) or len(set(pending)) != len(pending)
            or not set(ids + pending) <= allowed or set(ids) & set(pending)
            or len(pending) > 30 or cp.get('completed_total') != len(ids)
            or cp.get('remaining') != 112 - len(ids)
            or any(r.get('status') not in CLASSES for r in cp['rows'])):
        raise ValueError('gap checkpoint identity/count mismatch')
    return cp


def restore(directory, queue):
    path = directory / CHECKPOINT
    if path.exists():
        cp = validate_checkpoint(json.loads(path.read_bytes()), queue)
    else:
        source = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
        if source.get('artifact_id') != BOOTSTRAP_ARTIFACT:
            raise ValueError('gap checkpoint missing; refusing to restart queue')
        cp = {'schema_version': 1, 'scope': 'preview', 'queue_sha256': QUEUE_SHA,
              'rows': [], 'in_flight': [], 'completed_total': 0, 'remaining': 112}
    return cp


def save(directory, cp, queue):
    cp['completed_total'] = len(cp['rows'])
    cp['remaining'] = 112 - len(cp['rows'])
    validate_checkpoint(cp, queue)
    target = directory / CHECKPOINT
    temporary = target.with_suffix('.tmp')
    temporary.write_text(json.dumps(cp, ensure_ascii=False, indent=2, sort_keys=True) + '\n')
    temporary.replace(target)
    completed = {r['external_id'] for r in cp['rows']}
    remaining = [r for r in queue['rows'] if r['anex_hotel_id'] not in completed]
    exports = {'anex-initial-search-pending': remaining}
    for status in CLASSES:
        exports['anex-initial-search-' + status] = [r for r in cp['rows'] if r['status'] == status]
    for name, rows in exports.items():
        (directory / (name + '.json')).write_text(json.dumps({'queue_sha256': QUEUE_SHA,
            'count': len(rows), 'rows': rows}, ensure_ascii=False, indent=2) + '\n')
        with (directory / (name + '.csv')).open('w', newline='') as handle:
            writer = csv.writer(handle)
            writer.writerow(['anex_hotel_id', 'status', 'reason', 'candidate_ids'])
            for row in rows:
                writer.writerow([row.get('external_id', row.get('anex_hotel_id')),
                    row.get('status', 'pending'), row.get('reason', row.get('queue_reason', '')),
                    ','.join(str(c.get('id', c.get('catalog_hotel_id'))) for c in row.get('candidates', []))])


def matching_source():
    """Reuse existing strict pure matching functions, never either old queue runner."""
    selections = {
        'anex_access_probe.py': {'positive_id'},
        'anex_hotel_match_probe.py': {'norm', 'hotel_text', 'coordinate', 'distance_m', 'name_score',
            'country_match', 'supplier_record', 'xml_relation', 'candidate_rank', 'read_catalog'},
        'anex_geo_enrichment.py': {'geo_decision'},
    }
    pieces = ['import difflib, html, math, unicodedata, re, json, subprocess\nSENSITIVE_VALUES = ()\n']
    for name, names in selections.items():
        source = Path(__file__).with_name(name).read_text()
        nodes = [node for node in ast.parse(source).body if isinstance(node, ast.FunctionDef) and node.name in names]
        if {node.name for node in nodes} != names:
            raise ValueError('matching implementation changed')
        pieces.extend(ast.get_source_segment(source, node) for node in nodes)
    return '\n\n'.join(pieces)


def remote_batch(selected, catalog_rows, country_id):
    """Executed on the existing AnyTour server; never reads secrets into Python."""
    def snapshot():
        result = subprocess.run(['php', '-d', 'display_errors=0', '-d', 'log_errors=0', '-r', DETAILS_PHP],
            input='{"mode":"snapshot"}', text=True, capture_output=True, timeout=30)
        value = json.loads(result.stdout)
        if result.returncode or value.get('status') != 'ok' or value.get('staging_total') != 8362:
            raise ValueError('snapshot:' + str(value.get('phase', 'count')) + ':' + str(value.get('staging_total', -1)))
        return value
    before = snapshot()
    rows = []
    deadline = time.monotonic() + 240
    stop_reason = None
    for item in selected:
        identifier = item['anex_hotel_id']
        original = catalog_rows[str(identifier)]
        xml = {'id': identifier, 'name': original['name'], 'alternate_name': original['alternate_name'],
               'town_id': original.get('town_id')}
        row = {'external_id': identifier, 'original_status': original['status'], 'xml': xml,
               'status': 'source_error', 'reason': stop_reason or 'details_unavailable', 'candidates': []}
        rows.append(row)
        if stop_reason or time.monotonic() >= deadline:
            row['reason'] = stop_reason or 'batch_deadline'
            continue
        try:
            result = subprocess.run(['php', '-d', 'display_errors=0', '-d', 'log_errors=0', '-r', DETAILS_PHP],
                input=json.dumps({'id': identifier}), text=True, capture_output=True, timeout=35)
            if result.returncode or len(result.stdout) > 20000:
                continue
            response = json.loads(result.stdout)
            if response.get('status') == 'protected':
                row.update(status='protected', reason='existing_mapping_or_manual_decision')
                continue
            if response.get('status') != 'ok':
                reason = response.get('reason', '')
                if re.fullmatch(r'details_unavailable|rate_limited|supplier_error_[0-9]{1,5}', reason):
                    row['reason'] = reason
                if reason == 'rate_limited':
                    stop_reason = 'rate_limited'
                continue
            api = supplier_record(response['details'])
            row['api'] = api
            if not api['id'] or not api['name']:
                continue
            relation = xml_relation(xml, api)
            if country_match(original['country'], api['country']) is False:
                relation = 'country_conflict'
            row['api_xml_relation'] = relation
            if relation != 'same_record':
                row.update(status='review', reason='supplier_identity_unverified')
                continue
            local = read_catalog([{'key': identifier, 'names': [api['name'], xml['name'], xml['alternate_name']],
                'country_id': country_id, 'latitude': api['latitude'], 'longitude': api['longitude']}], candidate_limit=256)
            if local['status'] != 'ok':
                row['reason'] = 'catalog_unavailable'
                continue
            candidates = [candidate_rank(api, xml, c) for item in local['items'] if item['key'] == identifier
                          for c in item['candidates']]
            candidates.sort(key=lambda c: (-c['score'], c['id']))
            # Resort names have different geographic granularity across catalogues.
            # Preserve evidence for review; never override coordinate/conflict rules.
            supplier_places = {norm(v) for v in [api.get('town'), api.get('region'), original.get('town')] if norm(v)}
            for candidate in candidates:
                local_places = {norm(v) for v in [candidate.get('town'), candidate.get('region')] if norm(v)}
                candidate['resort_evidence'] = {
                    'supplier_places': sorted(supplier_places), 'local_places': sorted(local_places),
                    'shared_names': sorted(supplier_places & local_places),
                    'interpretation': 'shared_name' if supplier_places & local_places else 'unresolved'}
            row['status'], row['reason'] = geo_decision(api, candidates, relation)
            row['candidates'] = candidates
        except (ValueError, KeyError, OSError, subprocess.SubprocessError):
            continue
    after = snapshot()
    if before != after:
        raise ValueError('database preservation mismatch')
    return {'rows': rows, 'preservation': after}


def ssh_batch(selected, catalog_rows, country_id):
    names = ('ANYTOOUR_DEPLOY_SSH_KEY', 'ANYTOOUR_DEPLOY_HOST', 'ANYTOOUR_DEPLOY_USER')
    if any(not os.environ.get(name, '').strip() for name in names):
        raise ValueError('missing SSH configuration')
    host, user = (os.environ[name].strip() for name in names[1:])
    if host.startswith('-') or user.startswith('-') or any(c.isspace() for c in host + user):
        raise ValueError('invalid SSH target')
    own_source = Path(__file__).read_text()
    function = next(n for n in ast.parse(own_source).body if isinstance(n, ast.FunctionDef) and n.name == 'remote_batch')
    source = 'import time, sys\n' + matching_source() + '\n'
    for variable, file in [('DETAILS_PHP', 'anex_search3_gap_details.php'), ('CATALOG_PHP', 'anex_catalog_reader.php')]:
        source += variable + ' = ' + repr(Path(__file__).with_name(file).read_text().removeprefix('<?php')) + '\n'
    source += ast.get_source_segment(own_source, function) + '\n'
    source += "try:\n    print(json.dumps(remote_batch(**json.load(sys.stdin)), ensure_ascii=False))\n"
    source += "except Exception as error:\n    print(json.dumps({'remote_error': type(error).__name__, 'phase': str(error) if str(error).startswith('snapshot:') else 'batch'}))\n"
    with tempfile.TemporaryDirectory(prefix='anex-search-gaps-', dir=os.environ.get('RUNNER_TEMP')) as temp:
        key = Path(temp) / 'ssh_key'
        key.write_text(os.environ[names[0]].rstrip() + '\n')
        key.chmod(0o600)
        command = ['ssh', '-T', '-i', str(key), '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new', '-o', 'UserKnownHostsFile=' + str(Path(temp) / 'known_hosts'),
            '-o', 'ConnectTimeout=15', '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=2',
            '-o', 'LogLevel=ERROR', '-l', user, host,
            'cd "$HOME/www/anytoour.ru" && python3 -c ' + shlex.quote(source)]
        result = subprocess.run(command, input=json.dumps({'selected': selected, 'catalog_rows': catalog_rows,
            'country_id': country_id}), text=True, capture_output=True, timeout=310,
            env={k: v for k, v in os.environ.items() if k not in names and not k.startswith('ANEX_')})
    if result.returncode or len(result.stdout) > 4000000:
        raise ValueError('remote_batch_exit_' + str(result.returncode))
    payload = json.loads(result.stdout)
    if 'remote_error' in payload:
        raise ValueError(str(payload['remote_error']) + ':' + str(payload['phase']))
    return payload


def merge(cp, rows, queue):
    if (not isinstance(rows, list) or {r.get('external_id') for r in rows} != set(cp['in_flight'])
            or len(rows) != len(cp['in_flight']) or any(r.get('status') not in CLASSES for r in rows)):
        raise ValueError('batch identities disagree with reservation')
    before = {r['external_id']: digest(r) for r in cp['rows']}
    cp = dict(cp, rows=cp['rows'] + rows, in_flight=[], completed_total=len(cp['rows']) + len(rows),
              remaining=112 - len(cp['rows']) - len(rows))
    validate_checkpoint(cp, queue)
    assert all(digest(r) == before[r['external_id']] for r in cp['rows'] if r['external_id'] in before)
    return cp


def approved_delta(checkpoint_path):
    """Recompute strict independent evidence before the append-only DB protocol."""
    directory = Path(checkpoint_path).parent
    queue = load_queue()
    raw = Path(checkpoint_path).read_bytes()
    cp = validate_checkpoint(json.loads(raw), queue)
    if cp['in_flight']:
        raise ValueError('unfinished batch cannot be accepted')
    sources = {}
    documents = {}
    for key, name in [('catalog_sha256', 'anex-hotel-catalog-match.json'), ('geo_sha256', 'anex-hotel-geo-enrichment.json')]:
        content = (directory / name).read_bytes()
        sources[key] = hashlib.sha256(content).hexdigest()
        if sources[key] != queue['sources'][key]:
            raise ValueError('approved baseline changed')
        documents[key] = json.loads(content)
    originals = {r['external_id']: r for r in documents['catalog_sha256']['matches']}
    baseline_accepted = {r['external_id'] for r in documents['catalog_sha256']['matches'] if r['status'] == 'verified_auto'}
    baseline_accepted |= {r['external_id'] for r in documents['geo_sha256']['rows'] if r['status'] == 'strong_candidate'}
    ns = {}
    exec(matching_source(), ns)
    rows = []
    for row in cp['rows']:
        if row['status'] != 'strong_candidate':
            continue
        identifier = row['external_id']
        if identifier in baseline_accepted:
            raise ValueError('gap delta overlaps previously accepted identities')
        original = originals[identifier]
        xml = {'id': identifier, 'name': original['name'], 'alternate_name': original['alternate_name'], 'town_id': original.get('town_id')}
        api = row['api']
        if row['xml'] != xml or api.get('id') != identifier or ns['xml_relation'](xml, api) != 'same_record':
            raise ValueError('gap identity evidence disagrees')
        if ns['country_match'](original['country'], api['country']) is False:
            raise ValueError('gap country conflict')
        candidates = []
        for c in row['candidates']:
            local = dict(c, country_name=c['country'], region_name=c['region'], subregion_name=c['town'])
            ranked = ns['candidate_rank'](api, xml, local)
            if any(c[k] != value for k, value in ranked.items()):
                raise ValueError('candidate evidence was not reproduced')
            candidates.append(ranked)
        candidates.sort(key=lambda c: (-c['score'], c['id']))
        status, reason = ns['geo_decision'](api, candidates, 'same_record')
        if status != 'strong_candidate' or reason != row['reason']:
            raise ValueError('gap does not meet unchanged strong criteria')
        rows.append({'anex_hotel_id': identifier, 'catalog_hotel_id': candidates[0]['id'],
            'match_class': status, 'reason': 'initial_search_gap:' + reason, 'source_row_digest': digest(row)})
    sources['gap_sha256'] = hashlib.sha256(raw).hexdigest()
    rows.sort(key=lambda r: r['anex_hotel_id'])
    return {'schema_version': 1, 'scope': 'preview', 'approval_policy': 'owner_exact_and_strong_20260908',
        'append_only': True, 'sources': sources, 'rows': rows,
        'counts': {'exact': 0, 'strong': len(rows), 'total': len(rows),
                   'unique_catalog_hotels': len({r['catalog_hotel_id'] for r in rows})}}


def main():
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    if '--preflight' in __import__('sys').argv:
        try:
            result = ssh_batch([], {}, 1)
            report = {'status': 'ok', 'supplier_requests': 0, 'preservation': result['preservation']}
        except Exception as error:
            import re
            reason = str(error)
            report = {'status': 'preflight_failed', 'supplier_requests': 0,
                      'reason': reason if re.fullmatch(r'[A-Za-z0-9_:.-]{1,160}', reason) else type(error).__name__}
        (directory / 'anex-initial-search-preflight.json').write_text(json.dumps(report, indent=2) + '\n')
        print(json.dumps(report))
        if report['status'] != 'ok': raise SystemExit(1)
        return
    queue = load_queue()
    cp = restore(directory, queue)
    owner = os.environ['GITHUB_RUN_ID'] + ':' + os.environ['GITHUB_RUN_ATTEMPT']
    prepare = '--prepare' in __import__('sys').argv
    previous_total = cp['completed_total']
    recovered = False
    if prepare and cp['in_flight']:
        # Never repeat uncertain requests after a killed job or lost SSH reply.
        rows = [{'external_id': i, 'status': 'source_error', 'reason': 'interrupted_result_unknown',
                 'candidates': []} for i in cp['in_flight']]
        cp = merge(cp, rows, queue)
        save(directory, cp, queue)
        recovered = True
    if prepare:
        completed = {r['external_id'] for r in cp['rows']}
        selected = [] if recovered else [r for r in queue['rows'] if r['anex_hotel_id'] not in completed][:30]
        cp['in_flight'] = [r['anex_hotel_id'] for r in selected]
        cp['reservation_owner'] = owner
        cp['previous_completed'] = previous_total
        save(directory, cp, queue)
        print(json.dumps({'checkpoint_prepared': True, 'reserved': len(selected), 'completed': cp['completed_total']}))
        return
    if cp.get('reservation_owner') != owner:
        raise ValueError('current run reservation must be uploaded before API requests')
    previous_total = cp['previous_completed']
    selected = [r for r in queue['rows'] if r['anex_hotel_id'] in cp['in_flight']]
    preservation = None
    if selected:
        catalog_raw = (directory / 'anex-hotel-catalog-match.json').read_bytes()
        if hashlib.sha256(catalog_raw).hexdigest() != queue['sources']['catalog_sha256']:
            raise ValueError('original catalogue changed')
        wanted = {r['anex_hotel_id'] for r in selected}
        originals = {str(r['external_id']): r for r in json.loads(catalog_raw)['matches'] if r['external_id'] in wanted}
        if len(originals) != len(selected):
            raise ValueError('queue identities absent from original catalogue')
        try:
            result = ssh_batch(selected, originals, queue['evidence']['criteria']['countryId'])
            cp = merge(cp, result['rows'], queue)
            preservation = result['preservation']
        except Exception:
            # Keep reservation in artifact: no blind replay on the next run.
            raise RuntimeError('GAP_BATCH_UNCONFIRMED: reserved IDs preserved') from None
    save(directory, cp, queue)
    report = {'schema_version': 1, 'scope': 'preview', 'queue_sha256': QUEUE_SHA,
        'source_sha': os.environ.get('GITHUB_SHA'), 'previous_completed': previous_total,
        'new_completed': cp['completed_total'] - previous_total, 'completed_total': cp['completed_total'],
        'remaining': cp['remaining'], 'repeated_ids': 0, 'counts': dict(Counter(r['status'] for r in cp['rows'])),
        'new_counts': dict(Counter(r['status'] for r in cp['rows'][previous_total:])),
        'links_applied': 0, 'preservation': preservation}
    (directory / 'anex-initial-search-report.json').write_text(json.dumps(report, indent=2, sort_keys=True) + '\n')
    print(json.dumps(report, sort_keys=True))


if __name__ == '__main__':
    main()
