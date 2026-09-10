#!/usr/bin/env python3
import importlib.util
import os
from pathlib import Path
from types import SimpleNamespace

path = Path(__file__).resolve().parents[1] / 'scripts/diagnostics/andromeda_ssh_preflight.py'
spec = importlib.util.spec_from_file_location('ssh_preflight', path)
mod = importlib.util.module_from_spec(spec); spec.loader.exec_module(mod)

os.environ['ANYTOOUR_DEPLOY_SSH_KEY'] = 'PRIVATE-KEY-FIXTURE'
os.environ['ANYTOOUR_DEPLOY_HOST'] = 'example.invalid'
os.environ['ANYTOOUR_DEPLOY_USER'] = 'fixture-user'

calls=[]
def fake_run(command, **kwargs):
    calls.append(command)
    stderr='debug1: Connection established.\nAuthenticated to example.invalid\ndebug1: Sending command: fixed\ndebug1: Exit status 0\n'
    if any('ControlMaster=auto' in item for item in command): stderr += 'mux_client: master session id: 1\n'
    return SimpleNamespace(returncode=0, stdout=mod.EXPECTED, stderr=stderr)
mod.subprocess.run=fake_run

one=mod.run_mode('no_mux'); two=mod.run_mode('isolated_mux')
assert one['ok'] and two['ok'] and one['supplier_calls']==two['supplier_calls']==0
assert one['php_commands']==two['php_commands']==0 and one['database_commands']==two['database_commands']==0
assert one['ssh_progress']['multiplexing_seen'] is False and two['ssh_progress']['multiplexing_seen'] is True
assert len(calls)==2
for command in calls:
    joined=' '.join(command).lower()
    assert 'printf' in joined
    assert 'php ' not in joined and 'curl ' not in joined and 'mysql' not in joined
    assert 'anextour' not in joined and 'samo.ru' not in joined and 'tourvisor' not in joined
assert any('ControlMaster=no' in item for item in calls[0])
assert any('ControlMaster=auto' in item for item in calls[1])
assert 'PRIVATE-KEY-FIXTURE' not in str(one) + str(two)
print('SSH preflight guards: PASS; supplier/php/db/network=0')
