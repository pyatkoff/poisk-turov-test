#!/usr/bin/env python3
import json
from pathlib import Path
root=Path(__file__).resolve().parents[2]
owner=json.loads((root/'OWNER_PRIORITY.json').read_text(encoding='utf-8'))
state=json.loads((root/'AUTOPILOT_STATE.json').read_text(encoding='utf-8'))
autopilot=(root/'AUTOPILOT.md').read_text(encoding='utf-8')
errors=[]
if owner.get('active_mode')!='ux_visual' or owner.get('active_phase')!='ANYTOUR_DESIGN_SYSTEM_1_SITE_UNIFICATION': errors.append('owner phase drift')
if owner.get('current_stage')!='CROSS_PAGE_JOURNEY_AUDIT': errors.append('owner stage drift')
if owner.get('canonical_design_system')!='ANYTOUR DESIGN SYSTEM 1.0': errors.append('owner design-system drift')
if state.get('mode')!='ux_visual' or state.get('phase')!='ANYTOUR_DESIGN_SYSTEM_1_SITE_UNIFICATION': errors.append('state phase drift')
if state.get('canonical_design_system')!='ANYTOUR DESIGN SYSTEM 1.0': errors.append('state design-system drift')
if (state.get('current_stage') or {}).get('id')!='CROSS_PAGE_JOURNEY_AUDIT': errors.append('state stage drift')
if '## Current owner-directed phase — ANYTOUR DESIGN SYSTEM 1.0' not in autopilot: errors.append('autopilot phase drift')
if errors:
 print('OWNER_PRIORITY_GUARD_FAIL'); [print('-',e) for e in errors]; raise SystemExit(1)
print('OWNER_PRIORITY_GUARD_OK mode=ux_visual stage=CROSS_PAGE_JOURNEY_AUDIT')
