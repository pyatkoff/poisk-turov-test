#!/usr/bin/env python3
import json
from pathlib import Path
root=Path(__file__).resolve().parents[2]
lock=json.loads((root/'TECHNICAL_REFACTOR_LOCK.json').read_text(encoding='utf-8'))
owner=json.loads((root/'OWNER_PRIORITY.json').read_text(encoding='utf-8'))
state=json.loads((root/'AUTOPILOT_STATE.json').read_text(encoding='utf-8'))
errors=[]
expected_stage='DS2_REFERENCE_CONVERGENCE'
if lock.get('active') is not False or lock.get('status')!='INACTIVE_SUPERSEDED_BY_OWNER_DIRECTION': errors.append('technical lock reactivated')
if lock.get('canonical_design_system')!='ANYTOUR DESIGN SYSTEM 2.0': errors.append('lock design-system drift')
if owner.get('active_mode')!='ux_visual' or owner.get('active_phase')!='ANYTOUR_DESIGN_SYSTEM_2_SITE_UNIFICATION' or owner.get('current_stage')!=expected_stage: errors.append('owner visual stage drift')
if state.get('mode')!='ux_visual' or state.get('phase')!='ANYTOUR_DESIGN_SYSTEM_2_SITE_UNIFICATION' or (state.get('current_stage') or {}).get('id')!=expected_stage: errors.append('state visual stage drift')
if errors:
 print('TECHNICAL_REFACTOR_LOCK_FAIL'); [print('-',e) for e in errors]; raise SystemExit(1)
print('TECHNICAL_REFACTOR_LOCK_OK status=inactive owner_mode=ux_visual stage=DS2_REFERENCE_CONVERGENCE design_system=2.0')
