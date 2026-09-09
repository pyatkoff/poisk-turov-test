#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
lock = json.loads((root / 'TECHNICAL_REFACTOR_LOCK.json').read_text(encoding='utf-8'))
owner = json.loads((root / 'OWNER_PRIORITY.json').read_text(encoding='utf-8'))
state = json.loads((root / 'AUTOPILOT_STATE.json').read_text(encoding='utf-8'))
errors = []

expected_owner_mode = 'search_ux_ds2'
expected_owner_phase = 'ANYTOUR_DESIGN_SYSTEM_2_SEARCH_UX'
expected_owner_stage = 'SEARCH_RESULTS_FILTERS_AND_VISUAL_CONVERGENCE'
expected_ds = 'ANYTOUR DESIGN SYSTEM 2.0'

if lock.get('active') is not False or lock.get('status') != 'PAUSED_BY_OWNER_DIRECTION':
    errors.append('technical lock is not paused')
if lock.get('mode') != 'technical_refactor' or lock.get('phase') != 'DEFERRED_BY_OWNER':
    errors.append('technical lock deferred phase drift')
if lock.get('canonical_design_system') != expected_ds:
    errors.append('lock design-system drift')
if owner.get('active_mode') != expected_owner_mode or owner.get('active_phase') != expected_owner_phase or owner.get('current_stage') != expected_owner_stage:
    errors.append('owner active stage drift')
if state.get('mode') != expected_owner_mode or state.get('phase') != expected_owner_phase or (state.get('current_stage') or {}).get('id') != expected_owner_stage:
    errors.append('state active stage drift')

if errors:
    print('TECHNICAL_REFACTOR_LOCK_FAIL')
    for error in errors:
        print('-', error)
    raise SystemExit(1)

print('TECHNICAL_REFACTOR_LOCK_OK status=paused owner_mode=search_ux_ds2 stage=SEARCH_RESULTS_FILTERS_AND_VISUAL_CONVERGENCE design_system=2.0')
