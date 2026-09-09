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
CHECKPOINT = 'anex-review-storage-checkpoint.json'
SAVED_SOURCES = {
    'anex-paired-search-v2-checkpoint.json': ('013a231497da38399159167c25ebaa0aeb01079d776d7370d163a61bdc90603a', ('tv_day', 'tv_week')),
    'anex-segment-search-checkpoint.json': ('479bc48067e6147ade99f92e85462ebc73794044d5962f24cd4ff3a11f894cf8', ('tv_alanya', 'tv_5star', 'tv_alanya_5star'))}


def digest(raw):
    return hashlib.sha256(raw).hexdigest()


def write_json(path, value):
    raw = (json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode()
    # A partial write must not look like a missing checkpoint on the next invocation.
    pending = path.with_suffix('.pending')
    pending.write_bytes(raw)
    if pending.read_bytes() != raw:
        raise ValueError('storage checkpoint readback')
    pending.replace(path)
    if path.read_bytes() != raw:
        raise ValueError('storage checkpoint readback')


def execution_identity(source_sha):
    run, attempt = os.environ.get('GITHUB_RUN_ID', ''), os.environ.get('GITHUB_RUN_ATTEMPT', '')
    if not re.fullmatch('[1-9][0-9]*', run) or not re.fullmatch('[1-9][0-9]*', attempt):
        raise ValueError('storage execution identity required')
    return {'run_id': run, 'attempt': attempt, 'source_sha': source_sha}


def apply_checkpoint(directory, plan):
    path = directory / CHECKPOINT
    if path.with_suffix('.pending').exists():
        raise ValueError('storage outcome unknown')
    if not path.exists():
        return None
    cp = json.loads(path.read_bytes())
    if (cp.get('schema_version') != 1 or cp.get('plan_sha256') != gaps.digest(plan)
            or cp.get('state') not in ('reserved', 'executing', 'completed')
            or not isinstance(cp.get('reservation'), dict)
            or cp.get('reservation_sha256') != gaps.digest(cp['reservation'])):
        raise ValueError('storage checkpoint mismatch')
    expected = {'action': 'apply', 'schema_sha256': plan['schema_sha256'],
                'triage_sha256': plan['triage_sha256'], 'source_artifact_id': plan['bootstrap_artifact_id'],
                'rows': plan['expected_rows'], 'supplier_requests': 0}
    if any(cp['reservation'].get(k) != v for k, v in expected.items()):
        raise ValueError('storage checkpoint mismatch')
    if cp['state'] == 'completed':
        if not isinstance(cp.get('report'), dict) or cp.get('report_sha256') != gaps.digest(cp['report']):
            raise ValueError('storage completion report mismatch')
        validate_completion(cp['report'], cp['reservation'])
    return cp


def validate_completion(report, reservation):
    schema, imported = report.get('schema', {}), report.get('import', {})
    if (report.get('status') != 'ok' or report.get('supplier_requests') != 0
            or report.get('source_sha') != reservation['source_sha']
            or report.get('reservation') != reservation or report.get('panel_published') is not False
            or schema.get('schema_sha256') != reservation['schema_sha256']
            or schema.get('status') != 'ready' or schema.get('after_schema', {}).get('ready') is not True
            or schema.get('after_schema', {}).get('missing') != []
            or schema.get('preserved') is not True or schema.get('read_only') is not False
            or imported.get('status') not in ('stored', 'already_stored')
            or imported.get('artifact_id') != reservation['source_artifact_id']
            or imported.get('rows') != reservation['rows'] or imported.get('verified_ids') != reservation['rows']
            or type(imported.get('inserted')) is not int or not 0 <= imported['inserted'] <= reservation['rows']):
        raise ValueError('storage completion not verified')
    before, after = imported.get('before', {}), imported.get('after', {})
    required = {'catalog_hotels', 'anex_hotels', 'anex_hotel_auto_matches', 'anex_hotel_candidates',
                'anex_hotel_search_mappings', 'anex_hotel_decisions', 'anex_review_state',
                'anex_review_pair_exclusions', 'anex_review_audit'}
    if not required.issubset(before) or set(before) != set(after) or any(before[k] != after[k] for k in required):
        raise ValueError('storage preservation not verified')


def run_operation(directory, source, plan=None):
    original = json.loads((directory / 'anex-review-storage-reservation.json').read_bytes())
    reservation, envelope = prepare(directory, source, plan)
    if reservation != original:
        raise ValueError('reserved source changed')
    if envelope is None:
        return {'status': 'already_finalized', 'new_database_operations': 0, 'supplier_requests': 0}
    cp = None
    if reservation['action'] == 'apply':
        cp = json.loads((directory / CHECKPOINT).read_bytes())
        cp['state'] = 'executing'
        write_json(directory / CHECKPOINT, cp)
    report = execute({'action': reservation['action'], 'source_sha': source, 'envelope': envelope})
    report['reservation'] = reservation
    write_json(directory / 'anex-review-storage-report.json', report)
    if cp is not None:
        validate_completion(report, reservation)
        cp.update(state='completed', report=report, report_sha256=gaps.digest(report))
        write_json(directory / CHECKPOINT, cp)
    if report.get('status') != 'ok' or report.get('supplier_requests') != 0:
        raise ValueError('storage failed')
    return report


def packer():
    spec = importlib.util.spec_from_file_location('dossier_pack', Path(__file__).with_name('anex-review-dossier-pack.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def schema_files():
    files = {name: (ROOT / 'app/admin/anex-review' / name).read_text() for name in ('schema.sql', 'dossier-schema.sql')}
    return files, digest((files['schema.sql'] + '\n' + files['dossier-schema.sql']).encode())


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
    ledger = None
    if plan['action'] == 'apply':
        if (type(plan.get('bootstrap_artifact_id')) is not int or plan['bootstrap_artifact_id'] <= 0
                or type(plan.get('expected_rows')) is not int or not 0 <= plan['expected_rows'] <= 1000
                or not re.fullmatch('[0-9a-f]{64}', plan.get('triage_sha256', ''))
                or plan.get('readiness_schema_sha256') != schema_sha):
            raise ValueError('apply requires pinned inspected evidence')
        ledger = apply_checkpoint(directory, plan)
        if ledger is not None:
            if ledger['state'] == 'completed':
                # Do not re-read a mutable live checkpoint or repackage historic evidence.
                write_json(directory / 'anex-review-storage-reservation.json', ledger['reservation'])
                write_json(directory / 'anex-review-storage-report.json', ledger['report'])
                return ledger['reservation'], None
            if ledger['state'] != 'reserved' or ledger.get('execution') != execution_identity(source_sha):
                raise ValueError('storage outcome unknown')
    restored = json.loads((directory / 'anex-checkpoint-source.json').read_bytes())
    if plan['action'] == 'apply' and restored['artifact_id'] != plan['bootstrap_artifact_id']:
        raise ValueError('storage bootstrap lineage mismatch')
    cp = json.loads((directory / 'anex-observed-hotel-checkpoint.json').read_bytes())
    history = cp.get('inherited', []) + cp.get('rows', [])
    identifiers = [row.get('external_id') for row in history]
    if (cp.get('in_flight') or cp.get('batch_needs_finalization')
            or cp.get('completed_total') != len(history) or len(history) < 292
            or any(type(i) is not int or i <= 0 for i in identifiers)
            or len(set(identifiers)) != len(identifiers)):
        raise ValueError('completed live checkpoint required')
    with (directory / 'anex-observed-hotel-triage.json').open('rb') as stream:
        raw = stream.read(32_000_001)
    sha = digest(raw)
    envelope = packer().pack(raw, sha, restored['artifact_id'])
    if envelope['checkpoint_digest'] != gaps.digest(cp):
        raise ValueError('triage checkpoint mismatch')
    if plan['action'] == 'apply' and (plan.get('triage_sha256') != sha or plan.get('readiness_schema_sha256') != schema_sha):
        raise ValueError('apply requires pinned inspected evidence')
    if plan['action'] == 'apply' and plan['expected_rows'] != len(envelope['rows']):
        raise ValueError('storage row count mismatch')
    reservation = {'schema_version': 1, 'source_sha': source_sha, 'action': plan['action'],
                   'schema_sha256': schema_sha, 'triage_sha256': sha,
                   'source_artifact_id': restored['artifact_id'], 'rows': len(envelope['rows']),
                   'formatted_bytes':len(raw),'compact_bytes':len(packer().canonical(json.loads(raw)).encode()),
                   'supplier_requests': 0}
    if plan['action'] == 'apply':
        next_ledger = {'schema_version': 1, 'plan_sha256': gaps.digest(plan), 'state': 'reserved',
                       'execution': execution_identity(source_sha), 'reservation': reservation,
                       'reservation_sha256': gaps.digest(reservation), 'envelope_sha256': gaps.digest(envelope)}
        if ledger is not None and ledger != next_ledger:
            raise ValueError('reserved source changed')
        write_json(directory / CHECKPOINT, next_ledger)
    write_json(directory / 'anex-review-storage-reservation.json', reservation)
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


def execute(payload, inventory=False):
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
            'cd "$HOME/www/anytoour.ru" && php -d display_errors=0 -d log_errors=0 -r ' + shlex.quote(
                Path(__file__).with_name('anex_review_auth_inventory.php').read_text().removeprefix('<?php')
                if inventory else php_source())]
        result, attempts = gaps.run_ssh(command, json.dumps(payload),
            {k: v for k, v in os.environ.items() if k not in names and not k.startswith('ANEX_')})
    if len(result.stdout) > 200000:
        raise ValueError('storage report bound')
    report = json.loads(result.stdout)
    report['ssh_attempts'] = attempts
    return report


def main():
    import argparse
    parser = argparse.ArgumentParser()
    mode = parser.add_mutually_exclusive_group()
    mode.add_argument('--prepare', action='store_true'); mode.add_argument('--auth-inventory', action='store_true')
    args = parser.parse_args()
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    source = os.environ.get('GITHUB_SHA', '')
    if args.auth_inventory:
        if not re.fullmatch('[0-9a-f]{40}', source):
            raise ValueError('source SHA required')
        path = directory / 'anex-review-auth-inventory.json'
        inventory_sha = digest(Path(__file__).with_name('anex_review_auth_inventory.php').read_bytes())
        if path.exists():
            saved = json.loads(path.read_bytes())
            if saved.get('inventory_sha256') == inventory_sha and saved.get('status') == 'ok':
                print(json.dumps({'status': 'already_inspected', 'new_ssh_calls': 0})); return
        report = execute({}, inventory=True)
        if (report.get('status') != 'ok' or report.get('scope') != 'cli_presence_only'
                or any(report.get(k) != 0 for k in ('database_calls', 'supplier_requests', 'data_writes'))
                or report.get('application_code_executed') is not False or report.get('session_started') is not False):
            raise ValueError('auth inventory failed')
        report['source_sha'] = source
        report['inventory_sha256'] = inventory_sha
        write_json(path, report)
        print(json.dumps({'report_sha256': digest(path.read_bytes()), **report})); return
    if args.prepare:
        reservation, envelope = prepare(directory, source)
        # The historical saved-search audit is already finished. Storage is not a new search experiment.
        print(json.dumps(reservation)); return
    report = run_operation(directory, source)
    print(json.dumps({'report_sha256': gaps.digest(report), **report}, ensure_ascii=False))


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        allowed = {'completed live checkpoint required', 'triage checkpoint mismatch', 'review plan/schema mismatch',
                   'apply requires pinned inspected evidence', 'reserved source changed', 'source_digest_or_bound',
                   'storage checkpoint readback', 'storage execution identity required', 'storage outcome unknown',
                   'storage checkpoint mismatch', 'storage completion report mismatch', 'storage completion not verified',
                   'storage preservation not verified', 'storage bootstrap lineage mismatch', 'storage row count mismatch',
                   'source_contract', 'row_contract', 'evidence_digest', 'source_count','source_compact_bound'}
        reason = str(error) if isinstance(error, ValueError) and str(error) in allowed else 'see_failure_kind'
        print(json.dumps({'status': 'failed', **gaps.failure_report(error, 'review_storage'), 'reason': reason, 'reset_performed': False}))
        raise SystemExit(1)
