"""Keep the stopped LOCAL recovery lane inaccessible pending a separate safety decision."""
from __future__ import annotations

MODE = 'local-profile-plan-4191'
OPERATION = 'int-andromeda-local-profile-mass-recovery-plan-4191-20261008-v1'
BATCH = 'local4191-mass-recovery-20261008'
RUNNER = 'scripts/diagnostics/local_profile_mass_recovery_plan_4191.php'
BLOCKED_REASON = 'local_recovery_blocked_safety_stop_6047119931'


def need(value: bool, reason: str) -> None:
    if not value:
        raise ValueError(reason)


def register_parser(core) -> None:
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 3 or parts[1:3] != [MODE, OPERATION]:
            return original(body)
        # #4464 is DORMANT/BLOCKED by #4217/comment6047119931.
        need(False, BLOCKED_REASON)

    core.parse_command = parse


REMOTE_HANDLER = r'''
def run_local_profile_mass_recovery_plan_4191(stage):
    if (operation!='int-andromeda-local-profile-mass-recovery-plan-4191-20261008-v1'
            or payload.get('batch')!='local4191-mass-recovery-20261008'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0
            or not isinstance(payload.get('local_profile_control_sha'),str)
            or not re.fullmatch(r'[a-f0-9]{40}',payload['local_profile_control_sha'])):
        fail('local_recovery_scope')
    runner=stage/'scripts/diagnostics/local_profile_mass_recovery_plan_4191.php'
    if not safe_file(runner,2*1024*1024): fail('local_recovery_runner')
    env={k:os.environ[k] for k in ('PATH','HOME','LANG','LC_ALL') if k in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'LOCAL_PROFILE_PLAN_DIR':str(op),
                'LOCAL_PROFILE_SOURCE_SHA':source,'LOCAL_PROFILE_CONTROL_SHA':payload['local_profile_control_sha']})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,exec,shell_exec,system,passthru,popen,proc_open'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','disable_functions='+disabled,str(runner),'--plan-only'],
                       cwd=project,env=env,capture_output=True,text=True,timeout=480)
    public_path=op/'local-mass-recovery-receipt.json'
    private_path=op/'local-mass-recovery-plan.json'
    if not safe_file(public_path,65536): fail('local_recovery_terminal_missing_no_replay')
    data=safe_json(public_path,65536)
    expected={'schema_version','state','operation_id','source_sha','control_source_sha','batch','demand_through',
              'recovery_evidence_state','inspection_operation_id','inspection_input_sha256',
              'failed_operation_id','failed_operation_replay_allowed',
              'predecessor_private_plan_sha256','predecessor2_private_plan_sha256',
              'active_profiles','census_complete','core_fields_present','missing_field_counts','eligible_profiles',
              'source_plans_prepared','profiles_with_delta','planned_fields','classification_counts','safe_to_apply',
              'predecessor_exclusion_state','predecessor_excluded_profiles',
              'predecessor2_exclusion_state','predecessor2_excluded_profiles',
              'd1_exclusion_state','history_exclusion_state','ready_batches','private_plan_sha256',
              'provider_http_calls','database_writes','profile_writes','mapping_writes','schema_writes'}
    if (set(data)!=expected or data.get('schema_version')!=1 or data.get('state')!='completed_read_only'
            or data.get('operation_id')!=operation or data.get('source_sha')!=source
            or data.get('control_source_sha')!=payload['local_profile_control_sha']
            or data.get('batch')!='local4191-mass-recovery-20261008'
            or data.get('recovery_evidence_state')!='verified_pre_main_no_recorded_scope'
            or data.get('inspection_operation_id')!='int-andromeda-local-phase3-inspection-4191-20261007-v1'
            or data.get('inspection_input_sha256')!='3caa94dc41a2eaec57de720c2655ec5ec6c042ae2f4552668e68190e37a40d27'
            or data.get('failed_operation_id')!='int-andromeda-local-profile-mass-plan3-4191-20261002-v1'
            or data.get('failed_operation_replay_allowed') is not False
            or data.get('predecessor_private_plan_sha256')!='0b0a8807bf4b7b07172561537eafe1bc865265ef926c91fa117eed1c9f618426'
            or data.get('predecessor2_private_plan_sha256')!='aafbc0aa015d485817ae9d851a6200f677488ea5538ab73447f2ee1dc67c84e1'
            or data.get('predecessor_exclusion_state')!='verified_terminal_predecessor'
            or data.get('predecessor_excluded_profiles')!=2000
            or data.get('predecessor2_exclusion_state')!='verified_terminal_predecessor2'
            or data.get('predecessor2_excluded_profiles')!=2000
            or data.get('census_complete') is not True or data.get('safe_to_apply') is not False
            or any(type(data.get(k)) is not int or data[k]!=0 for k in
                   ('provider_http_calls','database_writes','profile_writes','mapping_writes','schema_writes'))
            or not safe_file(private_path,32*1024*1024)
            or hashlib.sha256(private_path.read_bytes()).hexdigest()!=data.get('private_plan_sha256')):
        fail('local_recovery_receipt_contract')
    bounds={'active_profiles':30000,'core_fields_present':30000,'eligible_profiles':30000,
            'source_plans_prepared':2000,'profiles_with_delta':2000,'planned_fields':24000,'ready_batches':8}
    for key,maximum in bounds.items():
        if type(data.get(key)) is not int or not 0<=data[key]<=maximum: fail('local_recovery_count')
    allowed={'ALIAS_HELD','PROFILE_INTEGRITY_HELD','PRIOR_OR_EDITORIAL_HELD','SCREENED_FIELDS_PRESENT',
             'HISTORICAL_366_HELD','D1_MANIFEST_UNKNOWN_HELD','D1_OVERLAP_HELD','HISTORY_UNKNOWN_HELD',
             'PREDECESSOR_2000_HELD','PREDECESSOR_UNKNOWN_HELD','PREDECESSOR2_2000_HELD','PREDECESSOR2_UNKNOWN_HELD',
             'PLAN_BOUND_DEFERRED','OWNER_PLAN_HELD','RETAINED_DELTA_PREPARED','NO_DELTA_NOT_PROVEN_COMPLETE',
             'SOURCE_MISSING','SOURCE_PROVENANCE_HELD'}
    counts=data.get('classification_counts');missing=data.get('missing_field_counts')
    fields={'description','primaryImage','images','address','place','build','repair','square',
            'hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes'}
    if (not isinstance(counts,dict) or set(counts)-allowed
            or any(type(v) is not int or v<0 for v in counts.values())
            or sum(counts.values())!=data['active_profiles']
            or counts.get('RETAINED_DELTA_PREPARED',0)!=data['profiles_with_delta']
            or counts.get('PREDECESSOR_2000_HELD',0)!=2000
            or counts.get('PREDECESSOR2_2000_HELD',0)!=2000
            or not isinstance(missing,dict) or set(missing)-fields
            or any(type(v) is not int or not 0<=v<=data['active_profiles'] for v in missing.values())
            or not 0<=data['profiles_with_delta']<=data['source_plans_prepared']<=data['eligible_profiles']<=data['active_profiles']
            or data.get('d1_exclusion_state')!='verified_terminal_manifest'
            or data.get('history_exclusion_state')!='verified'):
        fail('local_recovery_classification')
    private=safe_json(private_path,32*1024*1024)
    for key in ('operation_id','source_sha','control_source_sha','batch','demand_through',
                'recovery_evidence_state','inspection_operation_id','inspection_input_sha256',
                'failed_operation_id','failed_operation_replay_allowed','active_profiles',
                'classification_counts','profiles_with_delta','planned_fields','safe_to_apply',
                'predecessor_private_plan_sha256','predecessor2_private_plan_sha256'):
        if private.get(key)!=data[key]: fail('local_recovery_private_contract')
    evidence=private.get('recovery_evidence')
    if (not isinstance(evidence,dict)
            or evidence.get('state')!='verified_pre_main_no_recorded_scope'
            or evidence.get('inspectionOperationId')!=data['inspection_operation_id']
            or evidence.get('inspectionInputSha256')!=data['inspection_input_sha256']
            or evidence.get('failedOperationId')!=data['failed_operation_id']
            or evidence.get('failedOperationReplayAllowed') is not False):
        fail('local_recovery_evidence_contract')
    batches=private.get('batches')
    if not isinstance(batches,list) or len(batches)!=data['ready_batches']: fail('local_recovery_batch_count')
    profiles=0
    for index,batch in enumerate(batches,1):
        if (not isinstance(batch,dict) or set(batch)!={'file','sha256','profiles','scope_profiles','plan_sha256'}
                or batch.get('file')!='recovery-batch-%03d.json'%index
                or type(batch.get('profiles')) is not int or not 1<=batch['profiles']<=250
                or type(batch.get('scope_profiles')) is not int or not batch['profiles']<=batch['scope_profiles']<=250
                or not isinstance(batch.get('sha256'),str) or not re.fullmatch(r'[a-f0-9]{64}',batch['sha256'])
                or not isinstance(batch.get('plan_sha256'),str) or not re.fullmatch(r'[a-f0-9]{64}',batch['plan_sha256'])):
            fail('local_recovery_batch_contract')
        path=op/batch['file']
        if not safe_file(path,32*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=batch['sha256']:
            fail('local_recovery_batch_digest')
        profiles+=batch['profiles']
    if profiles!=data['profiles_with_delta']: fail('local_recovery_delta_count')
    if run.returncode!=0 or run.stderr.strip(): fail('local_recovery_runner_failed')
    try:
        stdout=json.loads(run.stdout)
    except Exception:
        fail('local_recovery_stdout')
    if stdout!=data: fail('local_recovery_stdout_receipt')
    result['supplier_calls']=0
    result['database_writes']=0
    result['profile_writes']=0
    result['mapping_writes']=0
    result['schema_writes']=0
    return data
'''


def activate(core, command: dict, plan) -> None:
    # Also reject saved or synthetic payloads that bypass command parsing.
    need(False, BLOCKED_REASON)
