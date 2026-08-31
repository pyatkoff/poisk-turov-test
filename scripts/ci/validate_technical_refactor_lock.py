#!/usr/bin/env python3
import json
from pathlib import Path
root=Path(__file__).resolve().parents[2]
lock=json.loads((root/'TECHNICAL_REFACTOR_LOCK.json').read_text(encoding='utf-8'))
owner=json.loads((root/'OWNER_PRIORITY.json').read_text(encoding='utf-8'))
state=json.loads((root/'AUTOPILOT_STATE.json').read_text(encoding='utf-8'))
errors=[]
expected_phase='CI_COST_AUDIT_AND_CONSOLIDATION'
expected_stage='ARCHITECTURE_INVENTORY_AND_CI_CONSOLIDATION'
if lock.get('active') is not True or lock.get('status')!='ACTIVE_OWNER_DIRECTION': errors.append('technical lock inactive')
if lock.get('canonical_design_system')!='ANYTOUR DESIGN SYSTEM 2.0': errors.append('lock design-system drift')
if owner.get('active_mode')!='technical_refactor' or owner.get('active_phase')!=expected_phase or owner.get('current_stage')!=expected_stage: errors.append('owner technical stage drift')
if state.get('mode')!='technical_refactor' or state.get('phase')!=expected_phase or (state.get('current_stage') or {}).get('id')!=expected_stage: errors.append('state technical stage drift')
if errors:
 print('TECHNICAL_REFACTOR_LOCK_FAIL'); [print('-',e) for e in errors]; raise SystemExit(1)
print('TECHNICAL_REFACTOR_LOCK_OK status=active owner_mode=technical_refactor design_system=2.0')
