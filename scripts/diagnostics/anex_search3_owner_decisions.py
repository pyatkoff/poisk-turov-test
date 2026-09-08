#!/usr/bin/env python3
"""Apply only the nine explicitly accepted preview pairs; never infer owner approval."""
import csv
import hashlib
import json
import os
from pathlib import Path
import shlex
import tempfile

import anex_search3_gap_queue as gaps

APPROVAL_ID = 'owner_nine_capped_pairs_20260908'
MANIFEST_SHA256 = '19fc0e6b0250298a6edb095a267bb28da43f750342b22c8a451339ebcab939a1'
PAIRS = {28892: 17449, 28941: 17428, 28950: 28738, 29292: 17325,
         30305: 17444, 30474: 67478, 31788: 71458, 32652: 28652, 32739: 28697}
REPORT = 'anex-owner-hotel-decisions.json'
RUN_REPORT = 'anex-owner-hotel-decision-run.json'
MANIFEST = Path(__file__).resolve().parents[2] / 'docs/integrations/reports/anex-candidate-ceiling-priority-20260908.json'


def build_request(directory):
    from anex_search3_observed_queue import restore, evidence_history
    raw = MANIFEST.read_bytes()
    if hashlib.sha256(raw).hexdigest() != MANIFEST_SHA256:
        raise ValueError('owner-approved pair manifest changed')
    manifest = json.loads(raw)
    if {r['anex_hotel_id']: r['catalog_hotel_id'] for r in manifest['rows']} != PAIRS or len(manifest['rows']) != 9:
        raise ValueError('owner approval is limited to nine exact pairs')
    cp = restore(directory)
    if cp['in_flight']:
        raise ValueError('unfinished live batch')
    history = evidence_history(directory, cp)
    rows = []
    for item in manifest['rows']:
        identifier = item['anex_hotel_id']
        evidence = history[identifier][0]
        if (gaps.digest(evidence) != item['evidence_row_sha256']
                or evidence['status'] != 'review' or evidence['reason'] != 'candidate_limit_reached'
                or evidence.get('api', {}).get('id') != identifier
                or evidence['candidates'][0]['id'] != PAIRS[identifier]):
            raise ValueError('owner-approved source evidence changed')
        rows.append({'anex_hotel_id': identifier, 'catalog_hotel_id': PAIRS[identifier],
                     'country': item['country'], 'evidence_row_sha256': item['evidence_row_sha256']})
    return {'scope': 'preview', 'approval_id': APPROVAL_ID, 'manifest_sha256': MANIFEST_SHA256,
            'owner_instruction': 'Да, соединяй их и дальше продолжай', 'rows': rows}


def ssh_php(source, request, maximum_bytes=65536):
    if maximum_bytes not in (65536, 4000000):
        raise ValueError('unsupported diagnostic response limit')
    names = ('ANYTOOUR_DEPLOY_SSH_KEY', 'ANYTOOUR_DEPLOY_HOST', 'ANYTOOUR_DEPLOY_USER')
    if any(not os.environ.get(name, '').strip() for name in names):
        raise ValueError('missing SSH configuration')
    host, user = (os.environ[name].strip() for name in names[1:])
    if host.startswith('-') or user.startswith('-') or any(c.isspace() for c in host + user):
        raise ValueError('invalid SSH target')
    with tempfile.TemporaryDirectory(prefix='anex-owner-', dir=os.environ.get('RUNNER_TEMP')) as temp:
        key = Path(temp) / 'ssh_key'
        key.write_text(os.environ[names[0]].rstrip() + '\n')
        key.chmod(0o600)
        control = Path(os.environ.get('RUNNER_TEMP') or temp) / 'anex-observed-ssh'
        control.mkdir(mode=0o700, exist_ok=True)
        command = ['ssh', '-T', '-i', str(key), '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes',
                   '-o', 'ControlMaster=auto', '-o', 'ControlPersist=45', '-o', 'ControlPath=' + str(control / '%C'),
                   '-o', 'StrictHostKeyChecking=accept-new', '-o', 'UserKnownHostsFile=' + str(Path(temp) / 'known_hosts'),
                   '-o', 'ConnectTimeout=15', '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=2',
                   '-o', 'LogLevel=DEBUG1', '-l', user, host,
                   'cd "$HOME/www/anytoour.ru" && php -r ' + shlex.quote(source)]
        result, _ = gaps.run_ssh(command, json.dumps(request, ensure_ascii=False),
                                {k: v for k, v in os.environ.items() if k not in names and not k.startswith('ANEX_')})
    if len(result.stdout.encode('utf-8')) > maximum_bytes:
        raise ValueError('diagnostic response too large')
    return json.loads(result.stdout)


def ssh_apply(request):
    root = Path(__file__).resolve().parents[2]
    source = (root / 'app/integrations/anex-search-mapping-registry.php').read_text().removeprefix('<?php')
    source += '\n' + Path(__file__).with_suffix('.php').read_text().removeprefix('<?php')
    return ssh_php(source, request)


def save(path, value):
    temporary = path.with_suffix('.tmp')
    temporary.write_text(json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + '\n')
    temporary.replace(path)
    if json.loads(path.read_bytes()) != value:
        raise ValueError('owner decision checkpoint readback failed')


def apply(directory):
    request = build_request(directory)
    path = directory / REPORT
    prior = json.loads(path.read_bytes()) if path.exists() else None
    if prior is not None and (prior.get('request') != request or prior.get('request_sha256') != gaps.digest(request)):
        raise ValueError('owner decision checkpoint changed')
    if prior is not None and prior.get('state') == 'applied':
        if prior.get('result', {}).get('readback_verified') is not True:
            raise ValueError('applied owner decisions lack verified readback')
        result = {'status': 'already_finalized', 'inserted': 0, 'approved_count': 9}
        save(directory / RUN_REPORT, result)
        return result
    prepared = {'state': 'prepared', 'request': request, 'request_sha256': gaps.digest(request)}
    save(path, prepared)
    result = ssh_apply(request)
    if (result.get('status') not in ('imported', 'already_imported') or result.get('approval_id') != APPROVAL_ID
            or type(result.get('inserted')) is not int or not 0 <= result['inserted'] <= 9
            or result.get('readback_verified') is not True
            or {r['anex_hotel_id']: r['catalog_hotel_id'] for r in result.get('rows', [])} != PAIRS
            or len(result.get('rows', [])) != 9 or result.get('preservation_before') != result.get('preservation_after')):
        raise ValueError('owner decisions not confirmed; prepared checkpoint retained')
    save(directory / RUN_REPORT, result)
    csv_path = path.with_suffix('.csv')
    cells = [['anex_hotel_id', 'catalog_hotel_id', 'decision_status', 'approval_id', 'evidence_row_sha256']]
    cells += [[str(r['anex_hotel_id']), str(r['catalog_hotel_id']), 'accepted', APPROVAL_ID,
               r['evidence_row_sha256']] for r in request['rows']]
    with csv_path.open('w', newline='') as handle:
        csv.writer(handle).writerows(cells)
    with csv_path.open(newline='') as handle:
        if list(csv.reader(handle)) != cells:
            raise ValueError('owner decisions CSV readback failed')
    save(path, dict(prepared, state='applied', result=result))
    return result


if __name__ == '__main__':
    try:
        print(json.dumps(apply(Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])), ensure_ascii=False, sort_keys=True))
    except Exception as error:
        print(json.dumps(gaps.failure_report(error, 'owner_decisions')))
        raise SystemExit(1) from None
