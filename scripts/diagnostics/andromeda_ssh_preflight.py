#!/usr/bin/env python3
"""Supplier-free SSH transport preflight for AnyTour diagnostics.

Runs only a fixed printf in the project directory. It never transfers PHP, JSON requests,
credentials, supplier identifiers, or database commands to the remote host.
"""
import json
import os
from pathlib import Path
import subprocess
import tempfile

EXPECTED = '{"status":"ok","scope":"three-source-ssh-preflight"}\n'
SECRET_NAMES = ('ANYTOOUR_DEPLOY_SSH_KEY','ANYTOOUR_DEPLOY_HOST','ANYTOOUR_DEPLOY_USER')


def progress(stderr: str):
    text = str(stderr)
    return {
        'tcp_connected': 'debug1: Connection established.' in text,
        'authenticated': 'Authenticated to ' in text or 'debug1: Authentication succeeded' in text,
        'multiplexing_seen': 'mux_client' in text or 'multiplexing control connection' in text,
        'command_sent': 'debug1: Sending command:' in text,
        'remote_exit_seen': 'debug1: Exit status ' in text,
    }


def run_mode(mode: str):
    if mode not in ('no_mux','isolated_mux'):
        raise ValueError('invalid_mode')
    missing = [name for name in SECRET_NAMES if not os.environ.get(name, '').strip()]
    if missing:
        raise ValueError('missing_ssh_configuration')
    key_text = os.environ[SECRET_NAMES[0]].rstrip() + '\n'
    host = os.environ[SECRET_NAMES[1]].strip()
    user = os.environ[SECRET_NAMES[2]].strip()
    if host.startswith('-') or user.startswith('-') or any(c.isspace() for c in host + user):
        raise ValueError('invalid_ssh_target')
    with tempfile.TemporaryDirectory(prefix='anytour-ssh-preflight-', dir=os.environ.get('RUNNER_TEMP')) as temp:
        root = Path(temp)
        key = root / 'ssh_key'
        key.write_text(key_text)
        key.chmod(0o600)
        command = [
            'ssh','-vv','-T','-i',str(key),'-o','IdentitiesOnly=yes','-o','BatchMode=yes',
            '-o','StrictHostKeyChecking=accept-new','-o','UserKnownHostsFile=' + str(root/'known_hosts'),
            '-o','ConnectTimeout=15','-o','ServerAliveInterval=15','-o','ServerAliveCountMax=2',
        ]
        if mode == 'no_mux':
            command += ['-o','ControlMaster=no','-o','ControlPath=none']
        else:
            control = root / 'control'
            control.mkdir(mode=0o700)
            command += ['-o','ControlMaster=auto','-o','ControlPersist=10','-o','ControlPath=' + str(control/'%C')]
        command += ['-l',user,host,"cd \"$HOME/www/anytoour.ru\" && printf '%s\\n' '{\"status\":\"ok\",\"scope\":\"three-source-ssh-preflight\"}'"]
        env = {k:v for k,v in os.environ.items() if k not in SECRET_NAMES and not k.startswith('ANEX_') and not k.startswith('ANDROMEDA_')}
        result = subprocess.run(command, text=True, capture_output=True, timeout=35, env=env)
    state = progress(result.stderr)
    return {
        'mode': mode,
        'ok': result.returncode == 0 and result.stdout == EXPECTED,
        'exit_code': result.returncode if -255 <= result.returncode <= 255 else None,
        'stdout_exact': result.stdout == EXPECTED,
        'ssh_progress': state,
        'supplier_calls': 0,
        'database_commands': 0,
        'php_commands': 0,
    }


def main():
    output = {'schema_version':1,'scope':'three-source-ssh-preflight','results':[],
              'supplier_calls':0,'database_commands':0,'php_commands':0}
    try:
        for mode in ('no_mux','isolated_mux'):
            output['results'].append(run_mode(mode))
    except Exception as exc:
        output['status'] = 'unconfirmed'
        output['error_kind'] = type(exc).__name__ if type(exc).__name__ in {'ValueError','TimeoutExpired','OSError'} else 'other'
    else:
        output['status'] = 'ok' if all(item['ok'] for item in output['results']) else 'transport_problem'
    print(json.dumps(output, sort_keys=True, separators=(',',':')))
    raise SystemExit(0 if output['status'] == 'ok' else 1)


if __name__ == '__main__':
    main()
