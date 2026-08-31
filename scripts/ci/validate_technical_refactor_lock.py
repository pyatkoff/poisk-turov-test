#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / 'TECHNICAL_REFACTOR_LOCK.json').read_text(encoding='utf-8'))
owner = json.loads((root / 'OWNER_PRIORITY.json').read_text(encoding='utf-8'))
state = json.loads((root / 'AUTOPILOT_STATE.json').read_text(encoding='utf-8'))
errors = []

expected_mode = 'technical_refactor'
expected_phase = 'ARCHITECTURE_INVENTORY_AND_CI_CONSOLIDATION'
expected_stage = 'ARCHITECTURE_SOURCE_OF_TRUTH'
expected_ds = 'ANYTOUR DESIGN SYSTEM 2.0'

if lock.get('active') is not True or lock.get('status') != 'ACTIVE_OWNER_DIRECTION':
    errors.append('technical lock is not active')
if lock.get('mode') != expected_mode or lock.get('phase') != expected_phase:
    errors.append('technical lock phase drift')
if lock.get('canonical_design_system') != expected_ds:
    errors.append('lock design-system drift')
if owner.get('active_mode') != expected_mode or owner.get('active_phase') != expected_phase or owner.get('current_stage') != expected_stage:
    errors.append('owner technical stage drift')
if state.get('mode') != expected_mode or state.get('phase') != expected_phase or (state.get('current_stage') or {}).get('id') != expected_stage:
    errors.append('state technical stage drift')

if errors:
    print('TECHNICAL_REFACTOR_LOCK_FAIL')
    for error in errors:
        print('-', error)
    raise SystemExit(1)

print('TECHNICAL_REFACTOR_LOCK_OK status=active owner_mode=technical_refactor stage=ARCHITECTURE_SOURCE_OF_TRUTH design_system=2.0')
