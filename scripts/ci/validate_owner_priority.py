#!/usr/bin/env python3
import json
import re
from datetime import datetime
from pathlib import Path

root = Path(__file__).resolve().parents[2]
owner = json.loads((root / 'OWNER_PRIORITY.json').read_text(encoding='utf-8'))
state = json.loads((root / 'AUTOPILOT_STATE.json').read_text(encoding='utf-8'))
autopilot = (root / 'AUTOPILOT.md').read_text(encoding='utf-8')
version = (root / 'DESIGN_SYSTEM_VERSION.md').read_text(encoding='utf-8')
errors = []

required_owner_fields = {
    'schema_version', 'active_mode', 'active_phase', 'current_stage',
    'canonical_design_system', 'priority_after_emergency_overrides', 'source',
    'rule',
}
required_state_fields = {
    'schema_version', 'project', 'updated_at', 'mode', 'phase',
    'canonical_design_system', 'owner_priority_source', 'program_source',
    'current_stage', 'current_task', 'queue', 'last_ci',
    'last_verified_commit', 'verification_context', 'blocked', 'protected',
    'continuation_policy',
}
required_project_paths = [
    'START_HERE.md', 'AUTOPILOT.md', 'AUTOPILOT_STATE_SCHEMA.md',
    'ARCHITECTURE.md', 'TEST_MATRIX.md', 'docs/project/PRODUCT_BRIEF.md',
    'docs/project/MASTER_PLAN.md', 'docs/project/SEARCH3_MIGRATION_MAP.md',
    'docs/project/RELEASE_GATES.md', 'docs/project/ROLLOUT_RUNBOOK.md',
    'docs/project/FUNNEL_SPEC.md', 'v2/bundle-manifest-v1.php',
]

expected_mode = 'search3_stabilization'
expected_phase = 'ANYTOUR_SEARCH3_STABILIZATION'
expected_stage = 'PROJECT_DEFINITION_AND_RELEASE_BASELINE'
expected_ds = 'ANYTOUR DESIGN SYSTEM 2.0'
sha_pattern = re.compile(r'^[0-9a-f]{40}$')


def missing_non_empty_strings(value, fields):
    if not isinstance(value, dict):
        return list(fields)
    return [field for field in fields if not isinstance(value.get(field), str) or not value.get(field).strip()]


missing_owner_fields = sorted(required_owner_fields.difference(owner))
if missing_owner_fields:
    errors.append('owner schema missing: ' + ', '.join(missing_owner_fields))
if owner.get('schema_version') != 15:
    errors.append('owner schema_version must be 15')
if missing_non_empty_strings(owner, ['active_mode', 'active_phase', 'current_stage', 'canonical_design_system', 'source', 'rule']):
    errors.append('owner required strings must be non-empty')
priorities = owner.get('priority_after_emergency_overrides')
if not isinstance(priorities, list) or not priorities or not all(isinstance(item, str) and item.strip() for item in priorities):
    errors.append('owner priorities must be a non-empty string array')

if owner.get('active_mode') != expected_mode or owner.get('active_phase') != expected_phase:
    errors.append('owner phase drift')
if owner.get('current_stage') != expected_stage:
    errors.append('owner stage drift')
if owner.get('canonical_design_system') != expected_ds:
    errors.append('owner design-system drift')
if state.get('schema_version') != 21:
    errors.append('state schema_version must be 21')
if state.get('project') != 'pyatkoff/poisk-turov-test':
    errors.append('state project drift')
if state.get('mode') != expected_mode or state.get('phase') != expected_phase:
    errors.append('state phase drift')
if state.get('canonical_design_system') != expected_ds:
    errors.append('state design-system drift')
if state.get('owner_priority_source') != 'OWNER_PRIORITY.json':
    errors.append('state owner-priority source drift')
current_stage = state.get('current_stage')
if not isinstance(current_stage, dict) or missing_non_empty_strings(current_stage, ['id', 'name', 'status']):
    errors.append('state current_stage shape invalid')
elif current_stage.get('id') != expected_stage:
    errors.append('state stage drift')
missing_state_fields = sorted(required_state_fields.difference(state))
if missing_state_fields:
    errors.append('state schema missing: ' + ', '.join(missing_state_fields))
try:
    updated_at = datetime.fromisoformat(state.get('updated_at', ''))
    if updated_at.tzinfo is None:
        errors.append('state updated_at must include timezone')
except (TypeError, ValueError):
    errors.append('state updated_at must be ISO-8601')

current_task = state.get('current_task')
if not isinstance(current_task, dict) or missing_non_empty_strings(current_task, ['id', 'category', 'title', 'status', 'objective', 'next_action']):
    errors.append('state current_task shape invalid')

queue = state.get('queue')
queue_by_id = {}
if not isinstance(queue, list) or not queue:
    errors.append('state queue must be a non-empty array')
else:
    for index, item in enumerate(queue):
        if not isinstance(item, dict) or missing_non_empty_strings(item, ['id', 'status', 'title']):
            errors.append(f'state queue item {index} shape invalid')
            continue
        item_id = item['id']
        if item_id in queue_by_id:
            errors.append(f'state queue duplicate id: {item_id}')
        queue_by_id[item_id] = item
if isinstance(current_task, dict) and current_task.get('id'):
    queued_current = queue_by_id.get(current_task['id'])
    if not queued_current:
        errors.append('state current_task is absent from queue')
    elif queued_current.get('status') != current_task.get('status'):
        errors.append('state current_task status differs from queue')

last_ci = state.get('last_ci')
if not isinstance(last_ci, dict) or missing_non_empty_strings(last_ci, ['workflow', 'head_sha', 'conclusion', 'classification']):
    errors.append('state last_ci shape invalid')
elif not sha_pattern.fullmatch(last_ci['head_sha']):
    errors.append('state last_ci head_sha must be 40 lowercase hex characters')
last_verified_commit = state.get('last_verified_commit')
if not isinstance(last_verified_commit, str) or not sha_pattern.fullmatch(last_verified_commit):
    errors.append('state last_verified_commit must be 40 lowercase hex characters')
if not isinstance(state.get('verification_context'), dict) or not state.get('verification_context'):
    errors.append('state verification_context must be a non-empty object')

blocked = state.get('blocked')
if not isinstance(blocked, list):
    errors.append('state blocked must be an array')
else:
    for index, item in enumerate(blocked):
        if not isinstance(item, dict) or missing_non_empty_strings(item, ['id', 'blocks', 'status']):
            errors.append(f'state blocked item {index} shape invalid')
        elif 'resolution_task' in item and (not isinstance(item['resolution_task'], str) or not item['resolution_task'].strip()):
            errors.append(f'state blocked item {index} resolution_task invalid')

protected = state.get('protected')
if not isinstance(protected, dict) or not protected:
    errors.append('state protected must be a non-empty object')
elif protected.get('search3_production') != 'LOCKED_UNTIL_EXACT_CANDIDATE_GATES_AND_OWNER_APPROVAL':
    errors.append('state Search3 production lock drift')
continuation_policy = state.get('continuation_policy')
if not isinstance(continuation_policy, dict) or not continuation_policy:
    errors.append('state continuation_policy must be a non-empty object')

program_source = state.get('program_source')
if not isinstance(program_source, str) or not program_source.strip() or not (root / program_source).is_file():
    errors.append('state program_source must reference an existing file')
for path in required_project_paths:
    if not (root / path).is_file():
        errors.append('required project source missing: ' + path)
start_here = (root / 'START_HERE.md').read_text(encoding='utf-8')
if isinstance(current_task, dict) and current_task.get('id') and f"`{current_task['id']}`" not in start_here:
    errors.append('current task missing from START_HERE.md')
if isinstance(program_source, str) and (root / program_source).is_file():
    master_plan = (root / program_source).read_text(encoding='utf-8')
    for item_id in queue_by_id:
        if f'`{item_id}`' not in master_plan:
            errors.append('queued task missing from program source: ' + item_id)
if '## Current owner-directed phase — ANYTOUR SEARCH3 STABILIZATION' not in autopilot:
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

print('OWNER_PRIORITY_GUARD_OK mode=search3_stabilization stage=PROJECT_DEFINITION_AND_RELEASE_BASELINE design_system=2.0')
