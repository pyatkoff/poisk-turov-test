#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / 'TECHNICAL_REFACTOR_LOCK.json').read_text(encoding='utf-8'))
owner = json.loads((root / 'OWNER_PRIORITY.json').read_text(encoding='utf-8'))
state = json.loads((root / 'AUTOPILOT_STATE.json').read_text(encoding='utf-8'))
errors = []

required_lock_fields = {
    'schema_version', 'active', 'mode', 'phase', 'status', 'authority', 'scope',
    'canonical_design_system', 'protected_contracts', 'rule',
}

expected_owner_mode = 'search3_stabilization'
expected_owner_phase = 'ANYTOUR_SEARCH3_STABILIZATION'
expected_owner_stage = 'PROJECT_DEFINITION_AND_RELEASE_BASELINE'
expected_ds = 'ANYTOUR DESIGN SYSTEM 2.0'

missing_lock_fields = sorted(required_lock_fields.difference(lock))
if missing_lock_fields:
    errors.append('technical lock schema missing: ' + ', '.join(missing_lock_fields))
if lock.get('schema_version') != 14:
    errors.append('technical lock schema_version must be 14')

if lock.get('active') is not False or lock.get('status') != 'PAUSED_BY_OWNER_DIRECTION':
    errors.append('technical lock is not paused')
if lock.get('mode') != 'technical_refactor' or lock.get('phase') != 'DEFERRED_BY_OWNER':
    errors.append('technical lock deferred phase drift')
if lock.get('canonical_design_system') != expected_ds:
    errors.append('lock design-system drift')
if lock.get('scope') != 'pyatkoff/poisk-turov-test':
    errors.append('technical lock scope drift')
if not isinstance(lock.get('authority'), str) or not lock.get('authority').strip():
    errors.append('technical lock authority must be non-empty')
required_contracts = {
    'yandex_metrika_configuration_and_goals', 'external_lead_contract',
    'tourvisor_contract', 'neighboring_projects',
}
protected_contracts = lock.get('protected_contracts')
if not isinstance(protected_contracts, list) or not required_contracts.issubset(protected_contracts):
    errors.append('technical lock protected contracts drift')
if not isinstance(lock.get('rule'), str) or 'Broad technical refactor remains deferred.' not in lock.get('rule', ''):
    errors.append('technical lock rule drift')
if owner.get('active_mode') != expected_owner_mode or owner.get('active_phase') != expected_owner_phase or owner.get('current_stage') != expected_owner_stage:
    errors.append('owner active stage drift')
if state.get('mode') != expected_owner_mode or state.get('phase') != expected_owner_phase or (state.get('current_stage') or {}).get('id') != expected_owner_stage:
    errors.append('state active stage drift')

if errors:
    print('TECHNICAL_REFACTOR_LOCK_FAIL')
    for error in errors:
        print('-', error)
    raise SystemExit(1)

print('TECHNICAL_REFACTOR_LOCK_OK status=paused owner_mode=search3_stabilization stage=PROJECT_DEFINITION_AND_RELEASE_BASELINE design_system=2.0')
