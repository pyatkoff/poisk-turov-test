#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[2]
owner = json.loads((root / 'OWNER_PRIORITY.json').read_text(encoding='utf-8'))
state = json.loads((root / 'AUTOPILOT_STATE.json').read_text(encoding='utf-8'))
autopilot = (root / 'AUTOPILOT.md').read_text(encoding='utf-8')
version = (root / 'DESIGN_SYSTEM_VERSION.md').read_text(encoding='utf-8')
errors = []

expected_mode = 'search_ux_ds2'
expected_phase = 'ANYTOUR_DESIGN_SYSTEM_2_SEARCH_UX'
expected_stage = 'SEARCH_RESULTS_FILTERS_AND_VISUAL_CONVERGENCE'
expected_ds = 'ANYTOUR DESIGN SYSTEM 2.0'

if owner.get('active_mode') != expected_mode or owner.get('active_phase') != expected_phase:
    errors.append('owner phase drift')
if owner.get('current_stage') != expected_stage:
    errors.append('owner stage drift')
if owner.get('canonical_design_system') != expected_ds:
    errors.append('owner design-system drift')
if state.get('mode') != expected_mode or state.get('phase') != expected_phase:
    errors.append('state phase drift')
if (state.get('current_stage') or {}).get('id') != expected_stage:
    errors.append('state stage drift')
if '## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 2.0 SEARCH UX + SITE CONVERGENCE' not in autopilot:
    errors.append('autopilot phase drift')
if 'Treat the public site as one product' not in autopilot:
    errors.append('autopilot site-coherence rule missing')
if '# AnyTour Design System 2.0' not in version:
    errors.append('design-system declaration drift')

if errors:
    print('OWNER_PRIORITY_GUARD_FAIL')
    for error in errors:
        print('-', error)
    raise SystemExit(1)

print('OWNER_PRIORITY_GUARD_OK mode=search_ux_ds2 stage=SEARCH_RESULTS_FILTERS_AND_VISUAL_CONVERGENCE design_system=2.0')
