#!/usr/bin/env python3
"""Inspect preserved ANEX fields. Content collection is stopped per supplier clarification."""
import ast
import hashlib
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import tempfile

import anex_search3_gap_queue as gaps


def batch_plan(path=None):
    path = Path(path) if path is not None else Path(__file__).with_name('anex_search3_catalog_content_plan.json')
    plan = json.loads(path.read_bytes())
    if (plan.get('schema_version') != 1
            or not isinstance(plan.get('batch_key'), str)
            or not re.fullmatch(r'owner_content_review_[0-9]{8}_[0-9]{2}', plan['batch_key'])
            or plan.get('photos_batch_key') != plan['batch_key'].replace('owner_content_review_', 'owner_content_review_photos_', 1)
            or type(plan.get('limit')) is not int or not 1 <= plan['limit'] <= 25
            or plan.get('priority') != 'unresolved_verified_frequency'):
        raise ValueError('invalid explicit content batch plan')
    return plan


def verified_ids(directory):
    ids = set()
    for filename in ('anex-hotel-geo-enrichment.json', 'anex-initial-search-checkpoint.json',
                     'anex-observed-hotel-checkpoint.json'):
        document = json.loads((directory / filename).read_bytes())
        for row in document.get('rows', []):
            api = row.get('api')
            if (isinstance(api, dict) and type(api.get('id')) is int
                    and 0 < api['id'] <= 2147483647 and api['id'] == row.get('external_id')
                    and row.get('api_xml_relation') == 'same_record' and api.get('name')):
                ids.add(api['id'])
    if not ids or len(ids) > 10000:
        raise ValueError('verified content source unavailable')
    return sorted(ids)


def php_body(name):
    return Path(__file__).with_name(name).read_text().removeprefix('<?php').replace('declare(strict_types=1);', '', 1)


def remote_run(payload):
    def php(source, value, timeout=45):
        result = subprocess.run(['php', '-d', 'display_errors=0', '-d', 'log_errors=0', '-r', source],
                                input=json.dumps(value), text=True, capture_output=True, timeout=timeout)
        if result.returncode or len(result.stdout) > 10000000:
            raise ValueError('remote PHP failed')
        return json.loads(result.stdout)
    report = php(INSPECT_PHP, payload)
    if report.get('status') != 'ok' or report.get('supplier_requests') != 0 or report.get('read_only') is not True:
        raise ValueError('read-only content inspection failed')
    return dict(report, source_sha=payload['source_sha'])



def execute(payload):
    names = ('ANYTOOUR_DEPLOY_SSH_KEY', 'ANYTOOUR_DEPLOY_HOST', 'ANYTOOUR_DEPLOY_USER')
    if any(not os.environ.get(name, '').strip() for name in names):
        raise ValueError('missing SSH configuration')
    host, user = (os.environ[name].strip() for name in names[1:])
    if host.startswith('-') or user.startswith('-') or any(c.isspace() for c in host + user):
        raise ValueError('invalid SSH target')
    own = Path(__file__).read_text()
    node = next(n for n in ast.parse(own).body if isinstance(n, ast.FunctionDef) and n.name == 'remote_run')
    source = 'import json, subprocess, sys\n'
    body = php_body('anex_search3_catalog_content_plan.php') + '\n' + php_body('anex_search3_catalog_content_inspect.php')
    source += 'INSPECT_PHP = ' + repr('declare(strict_types=1);\n' + body) + '\n'
    source += ast.get_source_segment(own, node) + '\n'
    source += "try:\n    print(json.dumps(remote_run(json.load(sys.stdin)), ensure_ascii=False))\n"
    source += "except Exception as error:\n    print(json.dumps({'error': type(error).__name__}))\n"
    with tempfile.TemporaryDirectory(prefix='anex-content-', dir=os.environ.get('RUNNER_TEMP')) as temp:
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
            'cd "$HOME/www/anytoour.ru" && python3 -c ' + shlex.quote(source)]
        result, attempts = gaps.run_ssh(command, json.dumps(payload),
            {k: v for k, v in os.environ.items() if k not in names and not k.startswith('ANEX_')})
    if len(result.stdout) > 10000000:
        raise ValueError('remote output too large')
    report = json.loads(result.stdout)
    if 'error' in report:
        raise ValueError('remote content operation failed')
    report['ssh_attempts'] = attempts
    return report


def main():
    directory = Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR'])
    source_sha = os.environ.get('GITHUB_SHA', '')
    if not re.fullmatch('[0-9a-f]{40}', source_sha):
        raise ValueError('source SHA required')
    report = execute({'source_sha': source_sha})
    path = directory / 'anex-catalog-content-inspection.json'
    path.write_text(json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True) + '\n')
    print(json.dumps({'inspection_status': report['status'], 'source_sha': source_sha,
        'report_sha256': hashlib.sha256(path.read_bytes()).hexdigest(),
        'read_only': report['read_only'], 'supplier_requests': report['supplier_requests'],
        'rows': report['count'], 'collection_disabled': report['collection_disabled']}, ensure_ascii=False))
    for row in report['rows']:
        print('ANEX_SAVED_CONTENT ' + json.dumps(row, ensure_ascii=False, sort_keys=True))



if __name__ == '__main__':
    try:
        main()
    except (ValueError, KeyError, OSError, subprocess.SubprocessError):
        raise SystemExit('CATALOG_CONTENT_FAILED: preserved data and reservations were not reset')
