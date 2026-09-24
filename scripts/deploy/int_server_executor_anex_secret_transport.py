#!/usr/bin/env python3
from __future__ import annotations

import argparse
import base64
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shlex
import subprocess
import zlib

SCRIPT = Path(__file__).resolve().with_name('int_server_executor.py')
spec = importlib.util.spec_from_file_location('int_server_executor_core', SCRIPT)
if spec is None or spec.loader is None:
    raise RuntimeError('executor_import')
core = importlib.util.module_from_spec(spec)
spec.loader.exec_module(core)

DIRECT_ANEX_MODES = frozenset({'anex-demand', 'anex-range'})
SUPPLIER_SLOT_MODES = frozenset({
    'anex-range', 'match-tv942', 'match-samo942', 'match-tv234-secondary',
    'match-common4-acquire', 'match-common4-continuation-acquire',
    'match-common4-continuation-resume-day', 'match-common4-continuation-remainder',
    'program-fuel-probe',
})
_REMOTE_ENV_LINE = "env={k:v for k,v in os.environ.items() if k not in ('ANEX_API_TOKEN','ANEX_B2B_TOKEN')}"
_REMOTE_SECRET_BLOCK = r"""env={k:v for k,v in os.environ.items() if k not in ('ANEX_API_TOKEN','ANEX_B2B_TOKEN')}
        if mode in ('anex-demand','anex-range'):
            api_encoded=payload.pop('_anex_api_token_b64',None)
            b2b_encoded=payload.pop('_anex_b2b_token_b64',None)
            try:
                api_token=base64.b64decode(api_encoded,validate=True).decode('utf-8')
                b2b_token=base64.b64decode(b2b_encoded,validate=True).decode('utf-8')
            except Exception:
                fail('anex_secret_transport_decode')
            if (not api_token.strip() or len(api_token)>16384 or '\x00' in api_token):
                fail('anex_api_secret_transport')
            if (not b2b_token or len(b2b_token)>16384 or b2b_token.lower().startswith('bearer ')
                    or re.search(r'[\x00-\x20\x7f]',b2b_token)):
                fail('anex_b2b_secret_transport')
            env['ANEX_API_TOKEN']=api_token
            env['ANEX_B2B_TOKEN']=b2b_token"""


def need(value: bool, reason: str) -> None:
    if not value:
        raise ValueError(reason)


def validated_anex_secret_payload() -> dict[str, str]:
    api = os.environ.get('ANEX_API_TOKEN', '')
    b2b = os.environ.get('ANEX_B2B_TOKEN', '')
    need(bool(api.strip()) and len(api) <= 16384 and '\x00' not in api,
         'anex_api_secret_missing_or_invalid')
    need(bool(b2b) and len(b2b) <= 16384 and not b2b.lower().startswith('bearer ')
         and re.search(r'[\x00-\x20\x7f]', b2b) is None,
         'anex_b2b_secret_missing_or_invalid')
    return {
        '_anex_api_token_b64': base64.b64encode(api.encode()).decode(),
        '_anex_b2b_token_b64': base64.b64encode(b2b.encode()).decode(),
    }


def patched_remote() -> str:
    need(core.REMOTE.count(_REMOTE_ENV_LINE) == 1, 'remote_secret_transport_anchor')
    value = core.REMOTE.replace(_REMOTE_ENV_LINE, _REMOTE_SECRET_BLOCK, 1)
    need(value.count("payload.pop('_anex_api_token_b64',None)") == 1,
         'remote_api_secret_transport')
    need(value.count("payload.pop('_anex_b2b_token_b64',None)") == 1,
         'remote_b2b_secret_transport')
    return value


def execute_direct_anex(command: dict, source_root: Path) -> dict:
    host, user, raw_key = (
        os.environ.get(name, '').strip()
        for name in ('INT_SSH_HOST', 'INT_SSH_USER', 'INT_SSH_KEY')
    )
    core.need(bool(host and user and raw_key), 'ssh_config')
    core.need(not host.startswith('-') and not user.startswith('-')
              and not any(c.isspace() for c in host + user), 'ssh_identity')
    core.need(command.get('mode') in DIRECT_ANEX_MODES, 'direct_anex_mode')

    bundle, manifest = core.bundle_source(source_root)
    output = Path(os.environ['RUNNER_TEMP']) / 'int-server-executor'
    output.mkdir(mode=0o700, exist_ok=True)
    key, known, archive = output / 'key', output / 'known_hosts', output / 'source.tar.gz'
    key.write_text(raw_key.rstrip() + '\n')
    key.chmod(0o600)
    subprocess.run(['ssh-keygen', '-y', '-f', str(key)], stdout=subprocess.DEVNULL,
                   stderr=subprocess.PIPE, check=True, timeout=10)
    scan = subprocess.run(['ssh-keyscan', '-T', '15', '-t', 'ed25519', host],
                          capture_output=True, check=True, timeout=20).stdout
    core.need(bool(scan), 'ssh_hostkey')
    known.write_bytes(scan)
    known.chmod(0o600)
    archive.write_bytes(bundle)
    archive.chmod(0o600)
    options = core.ssh_options(key, known)
    remote_archive = (
        '/tmp/' + command['operation_id'] + '-' + hashlib.sha256(bundle).hexdigest()[:16] + '.tar.gz'
    )
    subprocess.run(['scp', *options, str(archive), user + '@' + host + ':' + remote_archive],
                   check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, timeout=60)

    payload = dict(command)
    payload['archive'] = remote_archive
    payload['manifest_sha256'] = hashlib.sha256(
        json.dumps(manifest, sort_keys=True, separators=(',', ':')).encode()
    ).hexdigest()
    payload.update(validated_anex_secret_payload())

    encoded = base64.b64encode(zlib.compress(patched_remote().encode(), 9)).decode()
    remote_command = (
        "python3 -c 'import base64,zlib;exec(zlib.decompress(base64.b64decode(\"" + encoded + "\")))'"
    )
    core.need(len(remote_command.encode()) <= 65536, 'remote_command_size')
    try:
        run = subprocess.run(
            ['ssh', *options, '-l', user, host, remote_command],
            input=json.dumps(payload, separators=(',', ':')), text=True,
            capture_output=True, timeout=1000,
        )
        core.need(run.returncode == 0, 'ssh_remote_exit')
        result = json.loads(run.stdout.strip())
        core.need(isinstance(result, dict)
                  and result.get('operation_id') == command['operation_id']
                  and result.get('source_sha') == command['source_sha'],
                  'remote_receipt')
        (output / 'result.json').write_text(json.dumps(result, sort_keys=True, indent=2) + '\n')
        return result
    finally:
        subprocess.run(
            ['ssh', *options, '-l', user, host, 'rm -f -- ' + shlex.quote(remote_archive)],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=30,
        )
        key.unlink(missing_ok=True)
        known.unlink(missing_ok=True)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--parse-only', action='store_true')
    parser.add_argument('--source-root', default='source')
    args = parser.parse_args()
    event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    token = os.environ.get('GH_TOKEN', '')
    core.need(bool(token), 'gh_token')
    command = core.checked_event(token, event, os.environ['GITHUB_SHA'])
    if args.parse_only:
        for key, value in command.items():
            print(f'{key}={value}')
        return
    if command['mode'] in SUPPLIER_SLOT_MODES:
        core.ensure_supplier_slot(token)
    if command['mode'] in DIRECT_ANEX_MODES:
        result = execute_direct_anex(command, Path(args.source_root))
    else:
        result = core.execute(command, Path(args.source_root))
    print(json.dumps(result, sort_keys=True))
    if result.get('status') not in ('complete', 'reconciled_read_only', 'installed'):
        raise SystemExit(1)


if __name__ == '__main__':
    main()
