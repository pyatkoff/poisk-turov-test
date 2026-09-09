#!/usr/bin/env python3
"""Restore-only P2 schema/dossier integration. Never loads a supplier client."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shlex
import tempfile

import anex_search3_gap_queue as gaps

ROOT = Path(__file__).resolve().parents[2]
PLAN = Path(__file__).with_name('anex_review_storage_plan.json')
MAX_INSPECT_SOURCE_BYTES = 64_000_000
SAVED_SOURCES = {
    'anex-paired-search-v2-checkpoint.json': ('013a231497da38399159167c25ebaa0aeb01079d776d7370d163a61bdc90603a', ('tv_day', 'tv_week')),
    'anex-segment-search-checkpoint.json': ('479bc48067e6147ade99f92e85462ebc73794044d5962f24cd4ff3a11f894cf8', ('tv_alanya', 'tv_5star', 'tv_alanya_5star'))}


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def packer():
    spec = importlib.util.spec_from_file_location('dossier_pack', Path(__file__).with_name('anex-review-dossier-pack.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def schema_files():
    files = {name: (ROOT / 'app/admin/anex-review' / name).read_text() for name in ('schema.sql', 'dossier-schema.sql')}
    return files, digest((files['schema.sql'] + '\n' + files['dossier-schema.sql']).encode())


def read_triage(path, import_limit):
    """Fingerprint bounded saved bytes; never parse an oversized import source."""
    size, chunks, fingerprint = 0, [], hashlib.sha256()
    with path.open('rb') as stream:
        while True:
            chunk = stream.read(min(1_000_000, MAX_INSPECT_SOURCE_BYTES + 1 - size))
            if not chunk:
                break
            size += len(chunk)
            if size > MAX_INSPECT_SOURCE_BYTES:
                raise ValueError('inspection_source_bound')
            fingerprint.update(chunk)
            if size <= import_limit:
                chunks.append(chunk)
            else:
                chunks.clear()
    return (b''.join(chunks) if size <= import_limit else None), fingerprint.hexdigest(), size


def saved_search_audit(directory, envelope):
    """Intersect saved candidate IDs with completed TV searches; never accept a pair."""
    tv_ids = set()
    for name, (expected, cases) in SAVED_SOURCES.items():
        with (directory/name).open('rb') as stream: raw = stream.read(8000001)
        if len(raw)>8000000 or digest(raw)!=expected: raise ValueError('saved TV checkpoint changed')
        cp=json.loads(raw)
        for case in cases:
            row=cp['cases'][case]; result=row['result']
            if row['state']!='completed' or result['status']!='ok' or row['result_sha256']!=gaps.digest(result):
                raise ValueError('saved TV result incomplete')
            for offer in result['offers']:
                identifier=offer.get('hotel_id')
                if str(identifier).isdigit() and int(identifier)>0: tv_ids.add(int(identifier))
    hits=[]; hint_only=[]; with_candidates=0
    for item in envelope['rows']:
        row=json.loads(item['row_json']); candidates=row['evidence'].get('candidates', [])
        ids={int(c.get('id',c.get('catalog_hotel_id'))) for c in candidates
             if str(c.get('id',c.get('catalog_hotel_id'))).isdigit()}
        if ids: with_candidates+=1
        shared=sorted(ids & tv_ids)
        prior={int(c.get('id',c.get('catalog_hotel_id'))) for c in row.get('prior_fixed_queue_hints', [])
               if str(c.get('id',c.get('catalog_hotel_id'))).isdigit()}
        record={'anex_hotel_id':item['id'],'hotel_name':row['observation'].get('hotel_name'),
                'status':row['status'],'reason':row.get('reason'),'candidate_local_ids_seen_in_saved_tv':shared,
                'automatic_acceptance':False}
        if shared: hits.append(record)
        elif prior & tv_ids: hint_only.append(dict(record, historical_hint_ids=sorted(prior & tv_ids)))
    result={'scope':'saved_triage_only','new_supplier_requests':0,'new_bindings':0,
            'triage_sha256':envelope['source_digest'],'source_artifact_id':envelope['artifact_id'],
            'saved_tv_unique_hotels':len(tv_ids),'dossiers':len(envelope['rows']),
            'with_saved_candidates':with_candidates,'with_candidate_seen_in_saved_tv':len(hits),
            'with_historical_hint_only_seen_in_saved_tv':len(hint_only),
            'candidate_hits':hits,'historical_hints':hint_only,
            'meaning':'Existing Tourvisor availability can prioritize candidate review; no match or mismatch is established by this intersection.'}
    path=directory/'anex-review-saved-search-audit.json'
    path.write_text(json.dumps(result,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    if json.loads(path.read_bytes())!=result: raise ValueError('saved TV audit readback')
    print(json.dumps({k:v for k,v in result.items() if k not in ('candidate_hits','historical_hints')},ensure_ascii=False))
    return result


def prepare(directory, source_sha, plan=None):
    if not re.fullmatch('[0-9a-f]{40}', source_sha):
        raise ValueError('source SHA required')
    plan = json.loads(PLAN.read_bytes()) if plan is None else plan
    files, schema_sha = schema_files()
    if plan.get('action') not in ('inspect', 'apply') or plan.get('schema_sha256') != schema_sha:
        raise ValueError('review plan/schema mismatch')
    restored = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
    cp = json.loads((directory / 'anex-observed-hotel-checkpoint.json').read_bytes())
    history = cp.get('inherited', []) + cp.get('rows', [])
    identifiers = [row.get('external_id') for row in history]
    if (cp.get('in_flight') or cp.get('batch_needs_finalization')
            or cp.get('completed_total') != len(history) or len(history) < 292
            or any(type(i) is not int or i <= 0 for i in identifiers)
            or len(set(identifiers)) != len(identifiers)):
        raise ValueError('completed live checkpoint required')
    module = packer()
    raw, sha, source_bytes = read_triage(directory / 'anex-observed-hotel-triage.json', module.MAX_BYTES)
    if raw is None and plan['action'] != 'inspect':
        raise ValueError('source_digest_or_bound')
    envelope = module.pack(raw, sha, restored['artifact_id']) if raw is not None else None
    if envelope is not None and envelope['checkpoint_digest'] != gaps.digest(cp):
        raise ValueError('triage checkpoint mismatch')
    if plan['action'] == 'apply' and (plan.get('triage_sha256') != sha or plan.get('readiness_schema_sha256') != schema_sha):
        raise ValueError('apply requires pinned inspected evidence')
    reservation = {'schema_version': 1, 'source_sha': source_sha, 'action': plan['action'],
                   'schema_sha256': schema_sha, 'triage_sha256': sha,
                   'source_artifact_id': restored['artifact_id'], 'rows': len(envelope['rows']) if envelope else None,
                   'triage_bytes': source_bytes,
                   'dossier_status': 'validated' if envelope is not None else 'deferred_source_bound',
                   'supplier_requests': 0}
    path = directory / 'anex-review-storage-reservation.json'
    path.write_text(json.dumps(reservation, sort_keys=True, indent=2) + '\n')
    if json.loads(path.read_bytes()) != reservation:
        raise ValueError('reservation readback failed')
    return reservation, envelope


def php_source():
    files, schema_sha = schema_files()
    source = 'declare(strict_types=1);\n' + 'define("REVIEW_SCHEMA_FILES",json_decode(' + php_string(json.dumps(files)) + ',true,64,JSON_THROW_ON_ERROR));\n'
    source += 'define("REVIEW_SCHEMA_DIGEST",' + php_string(schema_sha) + ');\n'
    for name in ('app/admin/anex-review/schema-manager.php', 'app/admin/anex-review/dossier-store.php', 'scripts/diagnostics/anex_review_storage_runner.php'):
        source += (ROOT / name).read_text().removeprefix('<?php').replace('declare(strict_types=1);', '', 1) + '\n'
    return source


def php_string(value):
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"


def execute(payload):
    names = ('ANYTOOUR_DEPLOY_SSH_KEY', 'ANYTOOUR_DEPLOY_HOST', 'ANYTOOUR_DEPLOY_USER')
    if any(not os.environ.get(n, '').strip() for n in names):
        raise ValueError('missing SSH configuration')
    host, user = (os.environ[n].strip() for n in names[1:])
    if host.startswith('-') or user.startswith('-') or any(c.isspace() for c in host + user):
        raise ValueError('invalid SSH target')
    with tempfile.TemporaryDirectory(prefix='anex-review-', dir=os.environ.get('RUNNER_TEMP')) as temp:
        key = Path(temp) / 'ssh_key'
        key.write_text(os.environ[names[0]].rstrip() + '\n'); key.chmod(0o600)
        control = Path(os.environ.get('RUNNER_TEMP') or temp) / 'anex-observed-ssh'
        control.mkdir(mode=0o700, exist_ok=True)
        command = ['ssh', '-T', '-i', str(key), '-o', 'IdentitiesOnly=yes', '-o', 'BatchMode=yes',
            '-o', 'ControlMaster=auto', '-o', 'ControlPersist=45', '-o', 'ControlPath=' + str(control / '%C'),
            '-o', 'StrictHostKeyChecking=accept-new', '-o', 'UserKnownHostsFile=' + str(Path(temp) / 'known_hosts'),
            '-o', 'ConnectTimeout=15', '-o', 'ServerAliveInterval=15', '-o', 'ServerAliveCountMax=2',
            '-o', 'LogLevel=DEBUG1', '-l', user, host,
            'cd "$HOME/www/anytoour.ru" && php -d display_errors=0 -d log_errors=0 -r ' + shlex.quote(php_source())]
        result, attempts = gaps.run_ssh(command, json.dumps(payload),
            {k: v for k, v in os.environ.items() if k not in names and not k.startswith('ANEX_')})
    if len(result.stdout) > 200000:
        raise ValueError('storage report bound')
    report = json.loads(result.stdout)
    report['ssh_attempts'] = attempts
    return report


def main():
    import argparse
    parser = argparse.ArgumentParser(); parser.add_argument('--prepare', action='store_true'); args = parser.parse_args()
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    source = os.environ.get('GITHUB_SHA', '')
    if args.prepare:
        reservation, envelope = prepare(directory, source)
        try:
            if envelope is None:
                raise ValueError('dossiers deferred; schema inspection only')
            saved_search_audit(directory, envelope)
        except (ValueError, KeyError, OSError):
            # Independent research cannot authorize writes or destroy storage progress.
            failure={'status':'unavailable','reason':'saved_search_evidence_not_verified','new_supplier_requests':0,'new_bindings':0}
            (directory/'anex-review-saved-search-audit.json').write_text(json.dumps(failure)+'\n')
            print(json.dumps(failure))
        print(json.dumps(reservation)); return
    original = json.loads((directory / 'anex-review-storage-reservation.json').read_bytes())
    reservation, envelope = prepare(directory, source)
    if reservation != original:
        raise ValueError('reserved source changed')
    report = execute({'action': reservation['action'], 'source_sha': source, 'envelope': envelope})
    report['reservation'] = reservation
    path = directory / 'anex-review-storage-report.json'
    path.write_text(json.dumps(report, ensure_ascii=False, sort_keys=True, indent=2) + '\n')
    if json.loads(path.read_bytes()) != report:
        raise ValueError('storage report readback')
    print(json.dumps({'report_sha256': digest(path.read_bytes()), **report}, ensure_ascii=False))
    if report.get('status') != 'ok' or report.get('supplier_requests') != 0:
        raise ValueError('storage failed')


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        allowed = {'completed live checkpoint required', 'triage checkpoint mismatch', 'review plan/schema mismatch',
                   'apply requires pinned inspected evidence', 'reserved source changed', 'source_digest_or_bound',
                   'source_contract', 'row_contract', 'evidence_digest', 'source_count', 'inspection_source_bound'}
        reason = str(error) if isinstance(error, ValueError) and str(error) in allowed else 'see_failure_kind'
        print(json.dumps({'status': 'failed', **gaps.failure_report(error, 'review_storage'), 'reason': reason, 'reset_performed': False}))
        raise SystemExit(1)
