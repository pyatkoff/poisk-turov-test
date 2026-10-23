"""Fixed passive-fuel scopes inside the permanent INT carrier.

Sealed inventories remain read-only. The separate activation transaction replaces
exactly two reviewed files only after its complete dependency preflight is ready.
No application/config execution, database connection or supplier request applies.
"""
from __future__ import annotations

import ast
import hashlib
import io
import json
from pathlib import Path
import re
import tarfile

MODE = 'passive-fuel-readiness'
SOURCE = '670245c83b8fd47429a51a5d079e5106125476f9'
OPERATION = 'int-anex-passive-fuel-readiness-20261020-v1'
# A distinct, previously uninspected file closure. The ROOT operation above stays sealed.
PRIVATE_MODE = 'passive-fuel-private-readiness'
PRIVATE_SOURCE = '266848221cc78b1299c675171d7fe53fbb7e0cce'
PRIVATE_OPERATION = 'int-anex-passive-fuel-private-readiness-20261020-v1'
PLAN_MODE = 'passive-fuel-activation-plan'
PLAN_SOURCE = '199a570adbaa8de4957bdd84b30648bfaaee5fb5'
PLAN_OPERATION = 'int-anex-passive-fuel-activation-plan-20261020-v1'
INSTALL_MODE = 'passive-fuel-activate'
INSTALL_SOURCE = '0415fe57046a8929d1b857e13cd0d0d8824e904d'
INSTALL_OPERATION = 'int-anex-passive-fuel-activate-20261020-v1'
STOCK_MODE = 'passive-fuel-stock-activate'
STOCK_SOURCE = '0415fe57046a8929d1b857e13cd0d0d8824e904d'
STOCK_OPERATION = 'int-anex-passive-fuel-stock-activate-20261020-v1'
CURRENT_MODE = 'passive-fuel-current-activate'
CURRENT_SOURCE = '57ff5ced11f3912b6b39dd9d271c110029459f07'
CURRENT_OPERATION = 'int-anex-passive-fuel-current-activate-20261020-v1'
# A distinct metadata inspection, never an activation retry or lock repair.
LOCK_MODE = 'publication-lock-metadata'
LOCK_SOURCE = '61415e679957acac285bc0aa2f9c870f677618ea'
LOCK_OPERATION = 'int-anex-publication-lock-metadata-20261020-v1'
# SOURCE-ONLY proposal: this exact permission change still needs owner approval.
LOCK_REPAIR_MODE = 'publication-lock-mode-repair'
LOCK_REPAIR_SOURCE = LOCK_SOURCE
LOCK_REPAIR_OPERATION = 'int-anex-publication-lock-mode-repair-20261020-v1'
REPAIRED_MODE = 'passive-fuel-repaired-activate'
REPAIRED_SOURCE = '61415e679957acac285bc0aa2f9c870f677618ea'
REPAIRED_OPERATION = 'int-anex-passive-fuel-repaired-activate-20261021-v1'
# The stock server persists compact JSON; the runner artifact is pretty JSON.
REPAIR_RECEIPT_SHA256 = 'a1e608a8ad12c866169bbc4609365e1f6fbf901c86f59dd8e1e050973b6d7635'
REPAIR_RECEIPT_BYTES = 2056
PARTY_MODE = 'tourvisor-child-party-activate'
PARTY_SOURCE = 'd6f5afd6ed3dcf090afb9a7463c0bbc675c86780'
PARTY_OPERATION = 'int-anex-tourvisor-child-party-20261021-v1'
PARTY_CHECK_COMMIT = '28441a96a537e168e2bdc43d9c94e7371c14092e'
PARTY_CHECK_IDS = {119329030098,119329027030,119329026567,119329026268,119329025959}
PARTY_API_SHA256 = '90ddd43020aff509430b6ff85887a267ea4f0b30acc2213c82d7dcce0eb862f2'
PARTY_CONTINUE_MODE = 'tourvisor-child-party-continue'
PARTY_CONTINUE_SOURCE = PARTY_SOURCE
PARTY_CONTINUE_OPERATION = 'int-anex-tourvisor-child-party-continue-20261021-v1'
PARTY_ROOT_DB_SHA256 = 'ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'
PARTY_HOLD_SHA256 = '7e1b4cf6b92f52b2645b50ef5ae5ea56507fbcba955eceb98f3d1179683a081f'
PARTY_HOLD_BYTES = 7767
RELEASE_MODES = (MODE, PRIVATE_MODE, PLAN_MODE, INSTALL_MODE, STOCK_MODE, CURRENT_MODE, LOCK_MODE, LOCK_REPAIR_MODE, REPAIRED_MODE, PARTY_MODE, PARTY_CONTINUE_MODE)
DIAGNOSTIC_MODE = 'passive-fuel-diagnostic-readback'
DIAGNOSTIC_SOURCE = 'f3c1328b1af5b38d4ffd4757f25b89e3ddf7c2bd'
DIAGNOSTIC_OPERATION = 'int-anex-passive-fuel-diagnostic-readback-20261022-v1'
DIAGNOSTIC_HELPER_SHA256 = 'b980c347d2644dfa86f383d4652fd1b1d0727afb174fe6972efd0e2b18fbab42'
DIAGNOSTIC_CHECK_COMMIT = '80aa23987c8541a4c850436194f7c6ebec4d36a4'
DIAGNOSTIC_CHECK_IDS = {119688289004,119688288755}
DIAGNOSTIC_INSTALLED_AT = 1792654334
# New ordinary case6343328785 after receiver+sender installation. Old4814 stays
# consumed with its original source, operation, helper and time bounds.
CONTEXT_DIAGNOSTIC_MODE = 'passive-fuel-context-diagnostic'
CONTEXT_DIAGNOSTIC_SOURCE = 'd6599dc44e7fb1066272b6eab09a528498c3c55a'
CONTEXT_DIAGNOSTIC_OPERATION = 'int-anex-passive-context-diagnostic-20261022-v1'
CONTEXT_DIAGNOSTIC_IDS = ('25295685786553','25296808705983')
CONTEXT_DIAGNOSTIC_HELPER_SHA256 = '7ae4c385f49bf9e994a073d3405564054e9e503da1d1e5f8c1ad4ee7cd9219ef'
CONTEXT_DIAGNOSTIC_CHECK_COMMIT = '5d95ba40c32b7f6322a979cc8c9c74e2f53fb59e'
CONTEXT_DIAGNOSTIC_CHECK_IDS = {119739791997,119739790960}
CONTEXT_DIAGNOSTIC_MIN_TIME = 1792679137
# The new completed case6346100352 uses the installed supplier-date helpers.
# Old diagnostic reservations keep their original source, IDs and helper pins.
DATE_DIAGNOSTIC_MODE = 'passive-fuel-date-diagnostic'
DATE_DIAGNOSTIC_SOURCE = '9b55e8e8e2e5f095156da8ca89d7b24a8209b988'
DATE_DIAGNOSTIC_OPERATION = 'int-anex-passive-date-diagnostic-20261022-v1'
DATE_DIAGNOSTIC_IDS = ('18296733726725',)
DATE_DIAGNOSTIC_HELPER_SHA256 = '82575d37e45cdb6ff7976ea3e779c06571d98e381d80b89b57ae5d1a6b1dc088'
DATE_DIAGNOSTIC_CHECK_COMMIT = '6811734492a4983bf1ac8e31477f5245659831b1'
DATE_DIAGNOSTIC_CHECK_IDS = {119936837769,119936838595,119936837359}
DATE_DIAGNOSTIC_MIN_TIME = 1792686698
CAPTURE_INSTALL_MODE = 'install-tv-fuel-capture'
CAPTURE_INSTALL_OPERATION = 'int-anex-tv-fuel-capture-install-20261024-v1'
CAPTURE_INSTALL_PATH = 'app/integrations/tourvisor-operator-fuel-passive-intake.php'
CAPTURE_INSTALL_SHA256 = '5c0c0ba5a15517995b9241dc709e3d31ae663d0848e295e7e9bc6c8942462350'
CAPTURE_INSTALL_PREDECESSOR = '686878efe2b13b212b5d6a01c3d7649221e9d9768960398d19d4fa01c9c84a75'
CAPTURE_INSTALL_DEPENDENCIES = {
    'app/integrations/tourvisor-operator-fuel-retained-intake.php': '6c233c9fc0fe64c1647941ccb22756e1cad4c17b9692998dbd1eb6b7eb04a3d0',
    'app/integrations/operator-fuel-rule-store.php': 'a84468efc7953f50ee6fc25aaa77a40b97bd527ad9fbbe923d547dbe9db60fb9',
    'app/integrations/operator-fuel-rule-evidence.php': '5205c275da1f71aa756aab6852664902a8ea0453a4a457673f0896737f11a6a4',
}
CAPTURE_INSTALL_CHECK_COMMIT = '5d61cc5a31defbb85fec480c58b9c2fa06ba368c'
CAPTURE_INSTALL_CHECK_IDS = {120679633370, 120679633352, 120679632456}
CAPTURE_RECONCILE_MODE = 'reconcile-tv-fuel-capture-install'
CAPTURE_RECONCILE_OPERATION = 'int-anex-tv-fuel-capture-reconcile-20261024-v1'
CAPTURE_RECONCILE_TARGET_SOURCE = '04c0647ac30e1fd195aaf78d3d538178dcd59ebb'
CAPTURE_RECONCILE_TARGET_RESULT = '7b2c198b8f831605e0ee1c4c384b984095f6188e245367f46f3ec70855b4b3d5'
CAPTURE_COMPLETE_MODE = 'complete-tv-fuel-capture'
CAPTURE_COMPLETE_OPERATION = 'int-anex-tv-fuel-capture-complete-20261024-v1'
CAPTURE_COMPLETE_ORIGINAL_RESERVATION = '7c23f85aacc4e7c2c708cdc1d8549b96f99c23ea3eb4d1395049d0375ca5e4b1'
CAPTURE_COMPLETE_READ_RESULT = 'ff4e2670026497ef5ee8bfa595b1022bb6ebb209df848385105a3c4145044416'
CAPTURE_COMPLETE_PUBLIC = {'index.php':{'state':'regular','sha256':'80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f'},
    'v2/index.php':{'state':'absent'},'v2/api-v2.php':{'state':'absent'},'v2/lead-adapter-v2.php':{'state':'absent'}}
CAPTURE_COMPLETE_RUNS = (
    (40034325979,'1fddefb6ce28981ae44d6e57ea06a777a56d5c74','failure',12536421402,'7fc554c40b4bd1e38ee831e75a328b63ab98fa90a7ccae8448e2527104f65aee'),
    (40037220184,'25f1eb0f68c22b955dd177b864715707619ca86d','success',12537746864,'bcdd4281e7d2a216dc1e13e16f40e31dadaf920497b1d30c019858c7b5994ba8'))
MODES = (*RELEASE_MODES, DIAGNOSTIC_MODE, CONTEXT_DIAGNOSTIC_MODE, DATE_DIAGNOSTIC_MODE, CAPTURE_INSTALL_MODE, CAPTURE_RECONCILE_MODE, CAPTURE_COMPLETE_MODE)
SOURCE_CHECK_COMMIT = '990eb5859e1c8355337eb90351c655b604df8b39'
CHECK_IDS = {118651685241, 118651684803, 118651684153, 118651683778, 118651682993}
PRIVATE_SOURCE_CHECK_COMMIT = '65a802303afc3bedd6a8b674b9f5a9579ab4a38a'
PRIVATE_CHECK_IDS = {118696895774, 118696894874, 118696893621, 118696893334, 118696892718}
PROTECTED = ('api-v2.php','data/db-v1.php','data/collect-hotel-details-v1.php',
    'data/hotel-details-v1.php','data/local-tv-catalog-enabled.json','data/local-tv-catalog-v1.php',
    'data/price-observer-v1.php','data/tourvisor-client-v1.php','index.php','v2/index.php',
    'v2/api-v2.php','config.php','_preview/search3-next-candidate/visual-search/app.js',
    '_preview/search3-design-v2-candidate/visual-search/app.js')
SOURCE_HASHES = {
    'v2/api-v2.php': 'b87733d4f604f404c25f8d127c6b5949f6e0d42dcb3120c0e3c02df1793aa468',
    'app/integrations/tourvisor-operator-fuel-passive-intake.php': 'da89e7e5dc7816419140b716cb965462542ebc2ff3f42b4ec96dc623d823fa8d',
    'app/integrations/tourvisor-operator-fuel-retained-intake.php': 'a086f4d109b8d5e521311fd23a4047b5bf548cc41469e9b64370d740518adc0b',
    'app/integrations/operator-fuel-rule-evidence.php': 'cecd23217cc5e74b81f6c2ecd22db5813770edde3e861be888026393693ae1f2',
    'app/integrations/operator-fuel-rule-store.php': '774c5bc01e213c614644b269b111593967bd82dc2ea120a15175df846bcd386d',
}
PRIVATE_SOURCE_HASHES = {**SOURCE_HASHES,
    'v2/api-v2.php': '04cddd8c112bcdd70438a2e58e4935e1eae115057b218780f2f845bbfd83fd15'}
PARTY_SOURCE_HASHES = {**PRIVATE_SOURCE_HASHES, 'v2/api-v2.php': PARTY_API_SHA256}


def need(value, reason):
    if not value:
        raise ValueError(reason)


def parse(parts):
    if parts[1:2] == [CAPTURE_COMPLETE_MODE]:
        need(len(parts) == 3 and isinstance(parts[0],str) and re.fullmatch(r'[a-f0-9]{40}',parts[0])
             and parts[2] == CAPTURE_COMPLETE_OPERATION,'capture_complete_fixed_scope')
        return dict(source_sha=parts[0],mode=parts[1],operation_id=parts[2])
    if parts[1:2] == [CAPTURE_RECONCILE_MODE]:
        need(len(parts) == 3 and isinstance(parts[0], str) and re.fullmatch(r'[a-f0-9]{40}', parts[0])
             and parts[2] == CAPTURE_RECONCILE_OPERATION, 'capture_reconcile_fixed_scope')
        return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2])
    if parts[1:2] == [CAPTURE_INSTALL_MODE]:
        need(len(parts) == 3 and isinstance(parts[0], str) and re.fullmatch(r'[a-f0-9]{40}', parts[0])
             and parts[2] == CAPTURE_INSTALL_OPERATION, 'capture_install_fixed_scope')
        return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2])
    if parts[1:2] == [DATE_DIAGNOSTIC_MODE]:
        need(parts == [DATE_DIAGNOSTIC_SOURCE,DATE_DIAGNOSTIC_MODE,
                       DATE_DIAGNOSTIC_OPERATION,*DATE_DIAGNOSTIC_IDS],
             'date_diagnostic_fixed_case')
        return dict(source_sha=parts[0],mode=parts[1],operation_id=parts[2],tour_ids=parts[3:])
    if parts[1:2] == [CONTEXT_DIAGNOSTIC_MODE]:
        need(parts == [CONTEXT_DIAGNOSTIC_SOURCE,CONTEXT_DIAGNOSTIC_MODE,
                       CONTEXT_DIAGNOSTIC_OPERATION,*CONTEXT_DIAGNOSTIC_IDS],
             'context_diagnostic_fixed_case')
        return dict(source_sha=parts[0],mode=parts[1],operation_id=parts[2],tour_ids=parts[3:])
    if parts[1:2] == [DIAGNOSTIC_MODE]:
        need(len(parts) == 5 and parts[:3] == [DIAGNOSTIC_SOURCE,DIAGNOSTIC_MODE,DIAGNOSTIC_OPERATION],
             'diagnostic_fixed_scope')
        ids = parts[3:]
        need(all(isinstance(value,str) and re.fullmatch(r'[1-9][0-9]{5,19}',value) for value in ids)
             and len(set(ids)) == 2, 'diagnostic_two_offers')
        return dict(source_sha=parts[0],mode=parts[1],operation_id=parts[2],tour_ids=ids)
    need(parts in ([SOURCE, MODE, OPERATION], [PRIVATE_SOURCE, PRIVATE_MODE, PRIVATE_OPERATION],
                  [PLAN_SOURCE, PLAN_MODE, PLAN_OPERATION],
                  [INSTALL_SOURCE, INSTALL_MODE, INSTALL_OPERATION],
                  [STOCK_SOURCE, STOCK_MODE, STOCK_OPERATION],
                  [CURRENT_SOURCE, CURRENT_MODE, CURRENT_OPERATION],
                  [LOCK_SOURCE, LOCK_MODE, LOCK_OPERATION],
                  [LOCK_REPAIR_SOURCE, LOCK_REPAIR_MODE, LOCK_REPAIR_OPERATION],
                  [REPAIRED_SOURCE, REPAIRED_MODE, REPAIRED_OPERATION],
                  [PARTY_SOURCE, PARTY_MODE, PARTY_OPERATION],
                  [PARTY_CONTINUE_SOURCE, PARTY_CONTINUE_MODE, PARTY_CONTINUE_OPERATION]),
         'passive_readiness_fixed_scope')
    return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2])


def policy(command=None):
    command = parse([SOURCE, MODE, OPERATION]) if command is None else command
    if command.get('mode') in (DIAGNOSTIC_MODE,CONTEXT_DIAGNOSTIC_MODE,DATE_DIAGNOSTIC_MODE):
        ids = command.get('tour_ids')
        need(isinstance(ids,list) and command == parse(
            [command.get('source_sha'),command.get('mode'),command.get('operation_id'),*ids]),
            'diagnostic_fixed_scope')
        return command
    need(command == parse([command.get('source_sha'), command.get('mode'), command.get('operation_id')]),
         'passive_readiness_fixed_scope')
    return command


def admission(api_get, event, token, env, repo, issue, prefix, command=None):
    command = policy(command)
    need(event.get('action') == 'created' and event.get('repository', {}).get('full_name') == repo
         and env.get('GITHUB_EVENT_NAME') == 'issue_comment'
         and env.get('GITHUB_REPOSITORY') == repo and env.get('GITHUB_REF') == 'refs/heads/main'
         and env.get('GITHUB_WORKFLOW_REF') == repo + '/.github/workflows/int-server-executor.yml@refs/heads/main'
         and env.get('GITHUB_ACTOR') == env.get('GITHUB_TRIGGERING_ACTOR') == 'pyatkoff'
         and env.get('GITHUB_RUN_ATTEMPT') == '1', 'passive_readiness_event')
    private = command['mode'] in (PRIVATE_MODE, PLAN_MODE, INSTALL_MODE, STOCK_MODE, CURRENT_MODE, LOCK_MODE, LOCK_REPAIR_MODE, REPAIRED_MODE)
    check_commit = PRIVATE_SOURCE_CHECK_COMMIT if private else SOURCE_CHECK_COMMIT
    check_ids = PRIVATE_CHECK_IDS if private else CHECK_IDS
    if command['mode'] == DIAGNOSTIC_MODE:
        check_commit,check_ids = DIAGNOSTIC_CHECK_COMMIT,DIAGNOSTIC_CHECK_IDS
    if command['mode'] == CONTEXT_DIAGNOSTIC_MODE:
        check_commit,check_ids = CONTEXT_DIAGNOSTIC_CHECK_COMMIT,CONTEXT_DIAGNOSTIC_CHECK_IDS
    if command['mode'] == DATE_DIAGNOSTIC_MODE:
        check_commit,check_ids = DATE_DIAGNOSTIC_CHECK_COMMIT,DATE_DIAGNOSTIC_CHECK_IDS
    if command['mode'] in (PARTY_MODE, PARTY_CONTINUE_MODE):
        check_commit, check_ids = PARTY_CHECK_COMMIT, PARTY_CHECK_IDS
    if command['mode'] in (CAPTURE_INSTALL_MODE,CAPTURE_RECONCILE_MODE,CAPTURE_COMPLETE_MODE):
        check_commit, check_ids = CAPTURE_INSTALL_CHECK_COMMIT, CAPTURE_INSTALL_CHECK_IDS
    checks = api_get('/commits/' + check_commit + '/check-runs?per_page=100', token)
    need(checks.get('total_count') == len(check_ids)
         and {c.get('id') for c in checks.get('check_runs', [])} == check_ids
         and all(c.get('status') == 'completed' and c.get('conclusion') == 'success'
                 and c.get('app', {}).get('slug') == 'github-actions' for c in checks['check_runs']),
         'passive_readiness_source_ci')
    if command['mode'] in (CONTEXT_DIAGNOSTIC_MODE,DATE_DIAGNOSTIC_MODE,CAPTURE_INSTALL_MODE,CAPTURE_RECONCILE_MODE,CAPTURE_COMPLETE_MODE):
        rows=checks['check_runs']
        need(type(checks.get('total_count')) is int and len(rows)==len(check_ids)
             and all(c.get('head_sha')==check_commit for c in rows),
             'context_diagnostic_source_ci')
    if command['mode'] == CAPTURE_COMPLETE_MODE:
        for run_id,control,conclusion,artifact_id,digest in CAPTURE_COMPLETE_RUNS:
            prior=api_get('/actions/runs/'+str(run_id),token)
            need(prior.get('id')==run_id and prior.get('head_sha')==control
                 and prior.get('event')=='issue_comment' and prior.get('status')=='completed'
                 and prior.get('conclusion')==conclusion and type(prior.get('run_attempt')) is int and prior['run_attempt']==1
                 and prior.get('path')=='.github/workflows/int-server-executor.yml'
                 and prior.get('actor',{}).get('login')==prior.get('triggering_actor',{}).get('login')=='pyatkoff',
                 'capture_complete_prior_control')
            artifacts=api_get('/actions/runs/'+str(run_id)+'/artifacts?per_page=100',token)
            rows=artifacts.get('artifacts')
            need(type(artifacts.get('total_count')) is int and artifacts['total_count']==1
                 and isinstance(rows,list) and len(rows)==1,'capture_complete_prior_artifact')
            item=rows[0]
            need(item.get('id')==artifact_id and item.get('digest')=='sha256:'+digest
                 and item.get('name')=='int-server-executor-'+str(run_id)+'-1'
                 and item.get('workflow_run',{}).get('id')==run_id
                 and item['workflow_run'].get('head_sha')==control,'capture_complete_prior_artifact')
    matched = []
    for page in range(1, 31):
        comments = api_get(f'/issues/{issue}/comments?per_page=100&page={page}', token)
        need(isinstance(comments, list), 'passive_readiness_comments')
        for item in comments:
            body = item.get('body', '')
            if body.startswith(prefix) and command['mode'] in body[len(prefix):].split()[1:2]:
                matched.append(item.get('id'))
        if len(comments) < 100:
            need(matched == [event['comment']['id']], 'passive_readiness_no_replay')
            return
    raise ValueError('passive_readiness_history_incomplete')


def bundle_source(source_root, command=None):
    command = policy(command)
    hashes = PRIVATE_SOURCE_HASHES if command['mode'] in (PRIVATE_MODE, PLAN_MODE, INSTALL_MODE, STOCK_MODE, CURRENT_MODE, REPAIRED_MODE) else SOURCE_HASHES
    if command['mode'] in (LOCK_MODE, LOCK_REPAIR_MODE): hashes = {}
    if command['mode'] in (PARTY_MODE, PARTY_CONTINUE_MODE): hashes = PARTY_SOURCE_HASHES
    if command['mode'] in (DIAGNOSTIC_MODE,CONTEXT_DIAGNOSTIC_MODE,DATE_DIAGNOSTIC_MODE):
        hashes = {'app/integrations/tourvisor-operator-fuel-passive-intake.php':_diagnostic_profile(command)['helper_sha256']}
    if command['mode'] in (CAPTURE_INSTALL_MODE,CAPTURE_COMPLETE_MODE): hashes = {CAPTURE_INSTALL_PATH: CAPTURE_INSTALL_SHA256}
    if command['mode'] == CAPTURE_RECONCILE_MODE: hashes = {}
    root = Path(source_root).resolve()
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative, expected in hashes.items():
            path = root / relative
            need(path.resolve() == path and path.is_file() and not path.is_symlink(), 'passive_source_path')
            data = path.read_bytes()
            need(0 < len(data) <= 65536 and hashlib.sha256(data).hexdigest() == expected,
                 'passive_source_hash')
            info = tarfile.TarInfo(relative)
            info.size = len(data); info.mode = 0o600; info.mtime = 0
            archive.addfile(info, io.BytesIO(data))
        data = json.dumps({'schema_version': 1, 'files': hashes}, sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size = len(data); info.mode = 0o600; info.mtime = 0
        archive.addfile(info, io.BytesIO(data))
    return output.getvalue(), dict(hashes)


REMOTE_READER = r'''
import stat as pf_stat
PF_EXPECTED=__PF_EXPECTED__
PF_PREDECESSOR='01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61'
PF_DB='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4'
PF_PROTECTED=__PF_PROTECTED__

def pf_identity(s):
    return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)

def pf_read(path,optional=True,limit=2*1024*1024):
    # Walk fixed paths from the account's www directory using no-follow descriptors.
    anchor=home/'www'
    try: parts=path.relative_to(anchor).parts
    except ValueError: fail('passive_path_scope')
    if not parts or '..' in parts: fail('passive_path_scope')
    descriptor=os.open(anchor,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
    try:
        for part in parts[:-1]:
            try: child=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=descriptor)
            except FileNotFoundError:
                if optional:return None,None
                raise
            os.close(descriptor);descriptor=child
        try: leaf=os.open(parts[-1],os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=descriptor)
        except FileNotFoundError:
            if optional:return None,None
            raise
        try:
            before=os.fstat(leaf)
            if not pf_stat.S_ISREG(before.st_mode) or before.st_nlink!=1 or not 0<before.st_size<=limit:
                fail('passive_file_shape')
            data=b''
            while len(data)<=limit:
                chunk=os.read(leaf,min(65536,limit+1-len(data)))
                if not chunk:break
                data+=chunk
            after=os.fstat(leaf)
            entry=os.stat(parts[-1],dir_fd=descriptor,follow_symlinks=False)
            if len(data)!=before.st_size or pf_identity(before)!=pf_identity(after) or pf_identity(after)!=pf_identity(entry):
                fail('passive_file_changed')
            if path.resolve()!=path or pf_identity(path.lstat())!=pf_identity(after):fail('passive_file_changed')
            return data,{'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),'mode':pf_stat.S_IMODE(after.st_mode)}
        finally:os.close(leaf)
    finally:os.close(descriptor)

def pf_snapshot():
    records={};bodies={}
    for relative in PF_PROTECTED:
        bodies[relative],records[relative]=pf_read(project/relative)
    api=bodies['api-v2.php'] or b''
    parent_bound=b"require_once dirname(__DIR__) . '/app/integrations/tourvisor-anytour-offer-autosave.php';" in api
    for layout,base in (('root',project),('parent',project.parent)):
        for relative in PF_EXPECTED:
            if not relative.startswith('app/integrations/'):continue
            key=layout+':'+relative
            if layout=='parent' and not parent_bound:bodies[key],records[key]=None,None
            else:bodies[key],records[key]=pf_read(base/relative,limit=65536)
    bodies['private_config'],records['private_config']=pf_read(project/'.andromeda-private.php',limit=65536)
    return bodies,records

def pf_catalog_literal(text):
    literal=re.fullmatch(r'\s*<\?php\s*(?:declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*)?return\s*(\[.*\])\s*;\s*',text,re.S)
    if literal is None:raise ValueError('literal')
    pattern=re.compile(r"""\s*(=>|\[|\]|,|'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?|true|false|null)""")
    tokens=[];position=0;raw=literal[1]
    while position<len(raw):
        match=pattern.match(raw,position)
        if match is None or len(tokens)>=4096:raise ValueError('tokens')
        tokens.append(match[1]);position=match.end()
    cursor=0
    def value(depth=0):
        nonlocal cursor
        if depth>8 or cursor>=len(tokens):raise ValueError('depth')
        token=tokens[cursor];cursor+=1
        if token=='[':
            entries=[]
            while cursor<len(tokens) and tokens[cursor]!=']':
                first=value(depth+1);key=None
                if cursor<len(tokens) and tokens[cursor]=='=>':
                    cursor+=1;key=first;first=value(depth+1)
                    if not isinstance(key,str):raise ValueError('key')
                entries.append((key,first))
                if cursor<len(tokens) and tokens[cursor]==',':cursor+=1
                elif cursor>=len(tokens) or tokens[cursor]!=']':raise ValueError('separator')
            if cursor>=len(tokens):raise ValueError('array')
            cursor+=1;return entries
        if token[:1] in ("'",'"'):
            result=token[1:-1]
            if '\\' in result or (token[0]=='"' and '$' in result):raise ValueError('string')
            return result
        if token in ('true','false','null'):return {'true':True,'false':False,'null':None}[token]
        if re.fullmatch(r'-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?',token):return float(token)
        raise ValueError('value')
    entries=value()
    if cursor!=len(tokens) or not isinstance(entries,list):raise ValueError('trailing')
    candidates=[v for k,v in entries if k=='catalog_path']
    if len(candidates)!=1 or not isinstance(candidates[0],str):raise ValueError('catalog')
    return candidates[0]

def pf_binding(body):
    if body is None:return False,'missing',None
    # Never execute a PHP config. Only an unambiguous literal binding is understood.
    try:text=body.decode('utf-8')
    except UnicodeDecodeError:return False,'unrecognized',None
    try:catalog=pathlib.Path(pf_catalog_literal(text))
    except ValueError:return False,'unrecognized',None
    if not catalog.is_absolute() or '..' in catalog.parts or project not in catalog.parents:
        return False,'outside_scope',None
    directory=catalog.parent/'searches'
    if directory.resolve()!=directory or not directory.is_dir() or directory.is_symlink():
        return False,'store_unavailable',None
    # Directory identity only: never list/read observations or return private paths.
    first=directory.stat()
    second=directory.stat()
    if pf_identity(first)!=pf_identity(second):fail('passive_store_changed')
    return True,'verified_literal',pf_identity(second)

def passive_fuel_readiness():
    bodies,before=pf_snapshot()
    if before['api-v2.php'] is None:fail('passive_gateway_missing')
    api=bodies['api-v2.php']
    parent_bound=b"require_once dirname(__DIR__) . '/app/integrations/tourvisor-anytour-offer-autosave.php';" in api
    # The parent app is an existing explicit gateway dependency, never a guessed fallback.
    if not parent_bound and any(before['parent:'+p] is not None for p in PF_EXPECTED if p.startswith('app/')):
        fail('passive_parent_binding_unknown')
    binding=pf_binding(bodies['private_config'])
    binding_ok,binding_state,_=binding
    helpers=[p for p in PF_EXPECTED if p.startswith('app/')]
    layouts={}
    for layout in ('root','parent'):
        layouts[layout]={p:before[layout+':'+p] for p in helpers}
    selected=next((layout for layout in ('root','parent') if all(layouts[layout][p] is not None for p in helpers)),None)
    exact=selected is not None and all(layouts[selected][p]['sha256']==PF_EXPECTED[p] for p in helpers)
    db=bodies['data/db-v1.php']
    db_ok=before['data/db-v1.php'] is not None and before['data/db-v1.php']['sha256']==PF_DB
    declarations=0 if db is None else sum(bool(re.search(rb'function\s+'+name+rb'\s*\(',db))
        for name in (b'v2_data_db_config',b'v2_data_db',b'v2_data_normalize_text',b'v2_data_slug'))
    _,after=pf_snapshot()
    if before!=after:fail('passive_inventory_changed')
    if binding!=pf_binding(bodies['private_config']):fail('passive_store_changed')
    predecessor=before['api-v2.php']['sha256']==PF_PREDECESSOR
    return {'status':'ready' if predecessor and binding_ok and exact and db_ok and declarations==4 else 'not_ready',
        'gateway_predecessor_matches':predecessor,'helper_layouts':layouts,'selected_layout':selected,
        'helpers_exact':exact,'config_binding':binding_state,'store_directory_verified':binding_ok,
        'db_helper_integrity':db_ok,'db_helper_declarations':declarations,'loaded_db_state':'not_executed',
        'protected_before':{p:before[p] for p in PF_PROTECTED},
        'protected_after':{p:after[p] for p in PF_PROTECTED},
        'private_config_sha256':None if before['private_config'] is None else before['private_config']['sha256'],
        'supplier_calls':0,'database_reads':0,'database_writes':0,'runtime_writes':0,
        'booking_calls':0,'lead_calls':0,'protected_unchanged':True}
'''.replace('__PF_EXPECTED__', repr(SOURCE_HASHES)).replace('__PF_PROTECTED__', repr(PROTECTED))


# Reuse the already reviewed no-follow file reader and literal-only parser, not
# the consumed ROOT/parent inventory. Only the fixed private INT closure is new.
PRIVATE_REMOTE_READER = (REMOTE_READER[:REMOTE_READER.index('def pf_snapshot():')]
    + REMOTE_READER[REMOTE_READER.index('def pf_catalog_literal('):REMOTE_READER.index('def passive_fuel_readiness():')]
    + r'''
def pf_identity(s):
    return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns,s.st_uid,s.st_gid)

def pf_private_binding(body):
    ok,state,identity=pf_binding(body)
    if not ok:return False,state,identity,None,False
    directory=pathlib.Path(pf_catalog_literal(body.decode('utf-8'))).parent/'searches'
    current=directory.stat(follow_symlinks=False)
    if pf_identity(current)!=identity:fail('passive_store_changed')
    mode=pf_stat.S_IMODE(current.st_mode)
    owner_matches=current.st_uid==os.getuid()
    if not owner_matches or mode&0o002 or mode&0o300!=0o300:
        return False,'store_unavailable',identity,mode,owner_matches
    return True,state,identity,mode,owner_matches

def pf_snapshot():
    records={};bodies={}
    for relative in PF_PROTECTED:
        bodies[relative],records[relative]=pf_read(project/relative)
    runtime=project/'_preview/search3-anex-candidate'
    for relative in PF_EXPECTED:
        if not relative.startswith('app/integrations/'):continue
        key='private_int:'+relative
        bodies[key],records[key]=pf_read(runtime/relative,limit=65536)
    bodies['private_config'],records['private_config']=pf_read(runtime/'.andromeda-private.php',limit=65536)
    return bodies,records

def passive_fuel_readiness():
    bodies,before=pf_snapshot()
    if before['api-v2.php'] is None:fail('passive_gateway_missing')
    binding=pf_private_binding(bodies['private_config'])
    binding_ok,binding_state,_,store_mode,owner_matches=binding
    helpers=[p for p in PF_EXPECTED if p.startswith('app/')]
    rows={p:before['private_int:'+p] for p in helpers}
    selected='private_int' if all(rows[p] is not None for p in helpers) else None
    exact=selected is not None and all(rows[p]['sha256']==PF_EXPECTED[p] for p in helpers)
    db=bodies['data/db-v1.php']
    db_ok=before['data/db-v1.php'] is not None and before['data/db-v1.php']['sha256']==PF_DB
    declarations=0 if db is None else sum(bool(re.search(rb'function\s+'+name+rb'\s*\(',db))
        for name in (b'v2_data_db_config',b'v2_data_db',b'v2_data_normalize_text',b'v2_data_slug'))
    _,after=pf_snapshot()
    if before!=after:fail('passive_inventory_changed')
    if binding!=pf_private_binding(bodies['private_config']):fail('passive_store_changed')
    predecessor=before['api-v2.php']['sha256']==PF_PREDECESSOR
    return {'status':'ready' if predecessor and binding_ok and exact and db_ok and declarations==4 else 'not_ready',
        'gateway_predecessor_matches':predecessor,'helper_layouts':{'private_int':rows},'selected_layout':selected,
        'helpers_exact':exact,'config_binding':binding_state,'store_directory_verified':binding_ok,
        'db_helper_integrity':db_ok,'db_helper_declarations':declarations,'loaded_db_state':'not_executed',
        'protected_before':{p:before[p] for p in PF_PROTECTED},
        'protected_after':{p:after[p] for p in PF_PROTECTED},
        'private_config_sha256':None if before['private_config'] is None else before['private_config']['sha256'],
        'private_config_record':before['private_config'],'store_directory_mode':store_mode,
        'store_directory_owner_matches':owner_matches,
        'supplier_calls':0,'database_reads':0,'database_writes':0,'runtime_writes':0,
        'booking_calls':0,'lead_calls':0,'protected_unchanged':True}
''').replace('PF_EXPECTED='+repr(SOURCE_HASHES), 'PF_EXPECTED='+repr(PRIVATE_SOURCE_HASHES), 1)

REMOTE_DISPATCH = r'''
    if mode=='passive-fuel-readiness':
        try:result['passive_fuel_readiness']=passive_fuel_readiness()
        except OSError:fail('passive_file_unsafe')
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result.update(status='complete',supplier_calls=0,database_reads=0,database_writes=0,
                      runtime_changed=False,production_unchanged=True)
'''

# Installer planning only. Unlike the sealed metadata operations, this fixed
# closure validates the stock proxy target and private gateway caller before
# producing a two-file plan. It cannot execute an include or install any file.
PLAN_REMOTE_READER = (REMOTE_READER[:REMOTE_READER.index('def pf_snapshot():')]
    .replace("anchor=home/'www'", 'anchor=home', 1) + r'''
PP_PROXY='52e05b625b63a611f34cb87b58bd0e554c024df1e277bede469416330812d5aa'
PP_DB='c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13'
PP_CONFIG='121839ce1956ec7a48f0a94758e2c2359293ee01d601c69f720a294c4bda7def'
PP_OLD_PASSIVE='be4380b64caf4c98fc572c8db526f079a89bd04c301db5eccea3c2f2f232cc22'
PP_CALLER='2dedef982d7036c3f8520e8b8ca1175d83eb89fe14be865198441e33ff2c2ff4'
PP_LEGACY_CALLER='9935a1cd571ee58146e5fdec906833f3f678c38d3723c59c62d7796659c03dda'
PP_TARGETS={'api-v2.php':('01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61',
    '04cddd8c112bcdd70438a2e58e4935e1eae115057b218780f2f845bbfd83fd15'),
    '_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php':
    (PP_OLD_PASSIVE,'da89e7e5dc7816419140b716cb965462542ebc2ff3f42b4ec96dc623d823fa8d')}

def pf_identity(s):
    return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns,s.st_uid,s.st_gid)

def pp_literal_config(body):
    # Parse bounded PHP literals, including var_export's array(...), never PHP.
    if body is None:return None
    try:text=body.decode('utf-8')
    except UnicodeDecodeError:return None
    literal=re.fullmatch(r'\s*<\?php\s*(?:declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*)?return\s*(.*?)\s*;\s*',text,re.S)
    if literal is None:return None
    pattern=re.compile(r"""\s*(=>|array\b|\(|\)|\[|\]|,|'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*"|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?|true\b|false\b|null\b)""")
    tokens=[];position=0;raw=literal[1]
    while position<len(raw):
        match=pattern.match(raw,position)
        if match is None or len(tokens)>=4096:return None
        tokens.append(match[1]);position=match.end()
    cursor=0
    def value(depth=0):
        nonlocal cursor
        if depth>8 or cursor>=len(tokens):raise ValueError('shape')
        token=tokens[cursor];cursor+=1
        if token in ('[','array'):
            closing=']'
            if token=='array':
                if cursor>=len(tokens) or tokens[cursor]!='(':raise ValueError('array')
                cursor+=1;closing=')'
            entries=[]
            while cursor<len(tokens) and tokens[cursor]!=closing:
                first=value(depth+1);key=None
                if cursor<len(tokens) and tokens[cursor]=='=>':
                    cursor+=1;key=first;first=value(depth+1)
                    if not isinstance(key,(str,int)) or isinstance(key,bool):raise ValueError('key')
                entries.append((key,first))
                if cursor<len(tokens) and tokens[cursor]==',':cursor+=1
                elif cursor>=len(tokens) or tokens[cursor]!=closing:raise ValueError('separator')
            if cursor>=len(tokens):raise ValueError('array')
            cursor+=1;return entries
        if token[:1] in ("'",'"'):
            text=token[1:-1]
            if token[0]=='"' and '$' in text:raise ValueError('interpolation')
            # Only unambiguous escapes emitted by literal configuration writers.
            if re.search(r"\\(?![\\'\"])" ,text):raise ValueError('escape')
            return re.sub(r"\\([\\'\"])",r'\1',text)
        if token in ('true','false','null'):return {'true':True,'false':False,'null':None}[token]
        if re.fullmatch(r'-?(?:0|[1-9][0-9]*)',token):return int(token)
        if re.fullmatch(r'-?(?:0|[1-9][0-9]*)\.[0-9]+',token):return float(token)
        raise ValueError('value')
    try:entries=value()
    except (ValueError,RecursionError):return None
    if cursor!=len(tokens) or not isinstance(entries,list):return None
    enabled=[v for k,v in entries if k=='enabled']
    catalogs=[v for k,v in entries if k=='catalog_path']
    if len(enabled)!=1 or enabled[0] is not True or len(catalogs)!=1 or not isinstance(catalogs[0],str):return None
    return catalogs[0]

def pp_directory(path):
    # Metadata only. No directory listing, observations, or catalog contents.
    try:parts=path.relative_to(home).parts
    except ValueError:fail('passive_plan_directory_scope')
    if not parts or '..' in parts:fail('passive_plan_directory_scope')
    descriptor=os.open(home,os.O_PATH|os.O_DIRECTORY|os.O_NOFOLLOW)
    try:
        for part in parts:
            try:child=os.open(part,os.O_PATH|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=descriptor)
            except FileNotFoundError:return None
            os.close(descriptor);descriptor=child
        first=os.fstat(descriptor);second=path.lstat()
        if path.resolve()!=path or not pf_stat.S_ISDIR(first.st_mode) or pf_identity(first)!=pf_identity(second):
            fail('passive_plan_directory_changed')
        mode=pf_stat.S_IMODE(first.st_mode)
        return (pf_identity(first),mode,first.st_uid==os.getuid(),
                first.st_uid==os.getuid() and mode&0o300==0o300 and not mode&0o002)
    finally:os.close(descriptor)

def pp_snapshot():
    runtime=project/'_preview/search3-anex-candidate'
    paths={p:project/p for p in PF_PROTECTED}
    for p in PF_EXPECTED:
        if p.startswith('app/integrations/'):paths['helper:'+p]=runtime/p
    paths.update(proxy=runtime/'.andromeda-private.php',private_gateway=runtime/'api-v2.php',
        private_db=runtime/'data/db-v1.php',
        backing_config=home/'.anytoour-andromeda/search3-preview.php')
    bodies={};records={};identities={}
    for key,path in paths.items():
        bodies[key],records[key]=pf_read(path,limit=2*1024*1024 if key in PF_PROTECTED else 65536)
        identities[key]=None if records[key] is None else pf_identity(path.lstat())
    directories=(pp_directory(home/'.anytoour-andromeda'),pp_directory(home/'.anytoour-andromeda/searches'))
    return bodies,records,identities,directories

def passive_fuel_activation_plan(snapshot=None):
    bodies,before,identities,directories=pp_snapshot() if snapshot is None else snapshot
    helpers=[p for p in PF_EXPECTED if p.startswith('app/')]
    passive='app/integrations/tourvisor-operator-fuel-passive-intake.php'
    digest=lambda name:None if before[name] is None else before[name]['sha256']
    proxy_ok=digest('proxy')==PP_PROXY and before['proxy']['mode']==0o600
    db_ok=digest('data/db-v1.php')==PP_DB
    private_db_ok=digest('private_db')==PP_DB
    config_ok=digest('config.php')==PP_CONFIG
    api_ok=digest('api-v2.php')==PP_TARGETS['api-v2.php'][0]
    passive_ok=digest('helper:'+passive)==PP_OLD_PASSIVE
    dependencies_ok=all(digest('helper:'+p)==PF_EXPECTED[p] for p in helpers if p!=passive)
    caller=digest('private_gateway')
    caller_state=('compatible_pr4732' if caller==PP_CALLER else 'legacy_eager_dependency'
        if caller==PP_LEGACY_CALLER else 'missing' if caller is None else 'unrecognized')
    catalog=pp_literal_config(bodies['backing_config'])
    expected=home/'.anytoour-andromeda/catalog.json'
    config_owned=(identities['backing_config'] is not None and identities['backing_config'][-2]==os.getuid()
        and before['backing_config']['mode']==0o600)
    binding=(proxy_ok and config_owned and catalog==str(expected))
    store_ok=binding and all(d is not None and d[3] for d in directories)
    store_state=('verified_stock_binding' if store_ok else 'config_unrecognized'
        if catalog is None else 'config_binding_mismatch' if not binding else 'store_unavailable')
    _,after,after_identities,after_directories=pp_snapshot()
    if before!=after or identities!=after_identities or directories!=after_directories:fail('passive_plan_drift')
    ready=all((api_ok,passive_ok,dependencies_ok,db_ok,private_db_ok,config_ok,proxy_ok,store_ok,caller_state=='compatible_pr4732'))
    return {'status':'ready' if ready else 'hold','gateway_predecessor_matches':api_ok,
        'passive_predecessor_matches':passive_ok,'dependencies_exact':dependencies_ok,
        'db_predecessor_matches':db_ok,'private_db_dependency_matches':private_db_ok,'root_config_predecessor_matches':config_ok,
        'proxy_predecessor_matches':proxy_ok,'private_caller_state':caller_state,
        'store_state':store_state,'store_directory_mode':None if directories[1] is None else directories[1][1],
        'store_directory_owner_matches':False if directories[1] is None else directories[1][2],
        'backing_config_record':before['backing_config'],'private_gateway_record':before['private_gateway'],
        'private_db_record':before['private_db'],
        'proxy_record':before['proxy'],'loaded_db_state':'not_executed','binding_identity_stable':True,
        'plan_files':{p:{'predecessor_sha256':v[0],'candidate_sha256':v[1]} for p,v in PP_TARGETS.items()},
        'protected_before':{p:before[p] for p in PF_PROTECTED},
        'protected_after':{p:after[p] for p in PF_PROTECTED},'protected_unchanged':True,
        'supplier_calls':0,'database_reads':0,'database_writes':0,'runtime_writes':0,'booking_calls':0,'lead_calls':0}
''').replace('PF_EXPECTED='+repr(SOURCE_HASHES), 'PF_EXPECTED='+repr(PRIVATE_SOURCE_HASHES), 1)


# This is a new installation transaction, not a replacement metadata operation.
# The old three operation/source tuples above remain immutable and no-replay.
INSTALL_REMOTE = PLAN_REMOTE_READER + r'''
import fcntl as pi_fcntl

def pi_parent(path):
    parts=path.relative_to(home).parts
    if not parts or '..' in parts:fail('activation_path')
    descriptor=os.open(home,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
    try:
        for part in parts[:-1]:
            child=os.open(part,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=descriptor)
            os.close(descriptor);descriptor=child
        if path.parent.resolve()!=path.parent:fail('activation_parent')
        return descriptor
    except Exception:
        os.close(descriptor);raise

def pi_parent_stable(descriptor,path):
    a=os.fstat(descriptor);b=path.parent.lstat()
    fields=lambda s:(s.st_dev,s.st_ino,s.st_mode,s.st_uid,s.st_gid)
    if (fields(a)!=fields(b) or path.parent.resolve()!=path.parent or a.st_uid!=os.getuid()
            or pf_stat.S_IMODE(a.st_mode)&0o022):fail('activation_parent')

def pi_write(descriptor,name,data,mode):
    fd=os.open(name,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600,dir_fd=descriptor)
    with os.fdopen(fd,'wb') as stream:
        stream.write(data);stream.flush();os.fchmod(stream.fileno(),mode);os.fsync(stream.fileno())
        return pf_identity(os.fstat(stream.fileno()))

def pi_private(path,data):
    fd=pi_parent(path)
    try:
        pi_parent_stable(fd,path);pi_write(fd,path.name,data,0o600);os.fsync(fd)
    finally:os.close(fd)

def pi_lint(path):
    # Syntax only: -n disables PHP configuration; no include or application run.
    call=subprocess.run(['php','-n','-l',str(path)],stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,timeout=15,check=False,env={'PATH':'/usr/bin:/bin','HOME':str(home)})
    if call.returncode:fail('activation_syntax')

def passive_fuel_activate(stage,op,production_before):
    out={'status':'blocked_no_replay','reason':'preflight_failed','preflight':None,
        'publication_lock':'not_acquired','changed_files':[],'rollback_files':[],
        'before_files':{},'after_files':{},'protected_after':{},'protected_unchanged_except_target':False,
        'runtime_writes':0,'staging_file_writes':0,'backup_retained':False,
        'supplier_calls':0,'database_reads':0,'database_writes':0,'booking_calls':0,'lead_calls':0}
    lock=None;lock_parent=None;parents={};temps={};applied=[];initial=None;expected=None;expected_ids=None
    root='api-v2.php'
    passive='_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php'
    order=(passive,root)  # Dependency first, ROOT gateway last; rollback reverses this order.
    keys={root:root,passive:'helper:app/integrations/tourvisor-operator-fuel-passive-intake.php'}
    source_paths={root:'v2/api-v2.php',passive:'app/integrations/tourvisor-operator-fuel-passive-intake.php'}
    old={};candidates={};installed={}
    def snapshot_matches():
        current=pp_snapshot()
        if (current[1]!=expected or current[2]!=expected_ids or current[3]!=initial[3]
                or fingerprints()!=production_before):fail('activation_drift')
        for rel,fd in parents.items():pi_parent_stable(fd,project/rel)
        if lock is not None and pf_identity(os.fstat(lock))!=lock_identity:fail('activation_lock_changed')
        if lock is not None and pf_identity(lock_path.lstat())!=lock_identity:fail('activation_lock_changed')
        return current
    def checkpoint(name):
        pi_private(op/name,json.dumps({k:out[k] for k in ('status','runtime_writes','changed_files','rollback_files')},
            sort_keys=True,separators=(',',':')).encode())
    try:
        out['preflight']=passive_fuel_activation_plan()
        out.update(protected_after=out['preflight']['protected_after'],protected_unchanged_except_target=True)
        if out['preflight']['status']!='ready':
            out.update(status='hold',reason='preflight_hold',protected_after=out['preflight']['protected_after'],
                protected_unchanged_except_target=True)
            return out
        lock_path=home/'.anytoour-andromeda/grouped-search-update.lock'
        lock_parent=pi_parent(lock_path)
        pi_parent_stable(lock_parent,lock_path)
        try:lock=os.open(lock_path.name,os.O_RDWR|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=lock_parent)
        except FileNotFoundError:
            out.update(status='hold',reason='publication_lock_missing');return out
        info=os.fstat(lock);lock_identity=pf_identity(info)
        if (not pf_stat.S_ISREG(info.st_mode) or info.st_nlink!=1 or info.st_uid!=os.getuid()
                or pf_stat.S_IMODE(info.st_mode)&0o022 or info.st_size>65536
                or pf_identity(lock_path.lstat())!=lock_identity):fail('activation_lock_unsafe')
        try:pi_fcntl.flock(lock,pi_fcntl.LOCK_EX|pi_fcntl.LOCK_NB)
        except BlockingIOError:
            out.update(status='hold',reason='publication_busy');return out
        out['publication_lock']='exclusive_existing'
        # Revalidate everything under the publisher's existing lock.
        initial=pp_snapshot()
        out['preflight']=passive_fuel_activation_plan(initial)
        out.update(protected_after=out['preflight']['protected_after'],protected_unchanged_except_target=True)
        if out['preflight']['status']!='ready':
            out.update(status='hold',reason='preflight_hold');return out
        expected=dict(initial[1]);expected_ids=dict(initial[2])
        for rel in order:
            target=project/rel;key=keys[rel];record=initial[1][key];identity=initial[2][key]
            if (record is None or record['sha256']!=PP_TARGETS[rel][0] or record['mode']!=0o644
                    or identity[-2]!=os.getuid()):fail('activation_predecessor')
            parents[rel]=pi_parent(target);pi_parent_stable(parents[rel],target)
            if os.fstat(parents[rel]).st_dev!=op.stat().st_dev:fail('activation_filesystem')
            old[rel]=initial[0][key]
            data,record=pf_read(stage/source_paths[rel],optional=False,limit=65536)
            if record['sha256']!=PP_TARGETS[rel][1]:fail('activation_candidate')
            candidates[rel]=data;out['before_files'][rel]=initial[1][key]
        snapshot_matches()
        for index,rel in enumerate(order):
            pi_private(op/('activation-backup-'+str(index)+'.php'),old[rel])
        out['backup_retained']=True
        for index,rel in enumerate(order):
            name='.int-passive-fuel-'+operation+'-'+str(index)+'.tmp'
            identity=pi_write(parents[rel],name,candidates[rel],0o644)
            temps[rel]=(name,identity);out['staging_file_writes']+=1
            pi_lint(project/rel);pi_lint((project/rel).parent/name)
        snapshot_matches();out['status']='prepared';checkpoint('activation-prepared.json')
        for index,rel in enumerate(order):
            snapshot_matches();name,identity=temps[rel];fd=parents[rel]
            if pf_identity(os.stat(name,dir_fd=fd,follow_symlinks=False))!=identity:fail('activation_candidate')
            os.replace(name,(project/rel).name,src_dir_fd=fd,dst_dir_fd=fd)
            # Record ownership immediately after atomic replacement, before fsync/readback/lint.
            applied.append(rel);installed[rel]=identity
            out['runtime_writes']+=1;out['changed_files'].append(rel);temps.pop(rel)
            os.fsync(fd)
            data,record=pf_read(project/rel,optional=False,limit=65536)
            actual_id=pf_identity((project/rel).lstat())
            # rename changes ctime, so bind the new inode plus the exact installed bytes/mode.
            if (actual_id[:5]!=identity[:5] or record['sha256']!=PP_TARGETS[rel][1]
                    or record['mode']!=0o644):fail('activation_readback')
            installed[rel]=actual_id;expected[keys[rel]]=record;expected_ids[keys[rel]]=actual_id
            pi_lint(project/rel);snapshot_matches();checkpoint('activation-applied-'+str(index)+'.json')
        final=snapshot_matches()
        out.update(status='installed_verified',reason=None,after_files={p:final[1][keys[p]] for p in order},
            protected_after={p:final[1][p] for p in PF_PROTECTED},protected_unchanged_except_target=True)
        checkpoint('activation-verified.json')
    except Exception as exc:
        allowed={'activation_path','activation_parent','activation_lock_changed','activation_lock_unsafe',
            'activation_drift','activation_predecessor','activation_filesystem','activation_candidate',
            'activation_syntax','activation_readback'}
        out['reason']=str(exc) if isinstance(exc,RuntimeError) and str(exc) in allowed else 'activation_failed'
        out['status']='blocked_no_replay' if not applied else 'unknown_no_replay'
        if applied:
            try:
                for index,rel in enumerate(reversed(applied)):
                    fd=parents[rel];target=project/rel;pi_parent_stable(fd,target)
                    data,record=pf_read(target,optional=False,limit=65536)
                    current=pf_identity(target.lstat());ours=installed[rel]
                    # Never overwrite a subsequent writer's inode or changed bytes.
                    if (current[:5]!=ours[:5] or current[5]!=ours[5]
                            or current[7:]!=ours[7:] or record['sha256']!=PP_TARGETS[rel][1]):
                        fail('activation_rollback_ownership')
                    name='.int-passive-fuel-'+operation+'-rollback-'+str(index)+'.tmp'
                    identity=pi_write(fd,name,old[rel],0o644);temps[rel]=(name,identity)
                    if pf_identity(target.lstat())!=current:fail('activation_rollback_ownership')
                    os.replace(name,target.name,src_dir_fd=fd,dst_dir_fd=fd);temps.pop(rel)
                    out['runtime_writes']+=1;out['rollback_files'].append(rel);os.fsync(fd)
                    _,restored=pf_read(target,optional=False,limit=65536)
                    if restored!=out['before_files'][rel]:fail('activation_rollback_readback')
                final=pp_snapshot()
                if (final[1]!=initial[1] or final[3]!=initial[3] or fingerprints()!=production_before
                        or any(final[2][k]!=initial[2][k] for k in initial[2] if k not in keys.values())):
                    fail('activation_rollback_drift')
                out.update(status='rolled_back_no_replay',after_files={p:final[1][keys[p]] for p in order},
                    protected_after={p:final[1][p] for p in PF_PROTECTED},protected_unchanged_except_target=True)
            except Exception:out['status']='rollback_failed_no_replay'
    finally:
        for rel,(name,identity) in temps.items():
            try:
                fd=parents[rel]
                if pf_identity(os.stat(name,dir_fd=fd,follow_symlinks=False))==identity:os.unlink(name,dir_fd=fd);os.fsync(fd)
                else:out['status']='unknown_no_replay'
            except Exception:out['status']='unknown_no_replay'
        for fd in parents.values():os.close(fd)
        if lock is not None:os.close(lock)
        if lock_parent is not None:os.close(lock_parent)
    return out
'''


# Stock publisher c03dbecd at this deployed source excludes api-v2.php at
# every path component. Its safe wrapper does not change build_payload; the
# isolated UI keeps ROOT /api-v2.php. These seven rows were independently
# reproduced by that exact publisher, without PHP/config/provider execution.
STOCK_PUBLISHER_SOURCE = '7d21538b56a463ced185dd893361469e92911c77'
STOCK_REQUIRED = {
    'poisk-turov/index.php': (1272, 'a6d151903685c4aaf68163c339a57a52f2979e7587d6635b2a64e0d6e6548c67'),
    'api-anex-search3-preview.php': (52903, 'd69aaedf759d2d7cdfd3d8a0cbedf400bb1cc92a285b79ad9746fa825338f8db'),
    'api-andromeda-search3-preview.php': (43929, 'fc338f37496290871793c6b1fe0f5614b4aea4db2a2f0503277117a676a912e4'),
    'anex-search3-preview-v1.js': (47839, 'a5343ea7cc716e1319ba0bb93548a5f08162672e406c4718f909252537a6aa7e'),
    'preview-lead-disabled.php': (378, '15d1c61b6fde806caa52820748050b8bd55c82345499390d5f2a8e04577c2f5d'),
    'app/integrations/anex-search-mapping-registry.php': (8865, 'ba06d082ac66d671efc01569a7d11e9d8b5abd73cabde8ea72ecc154b3e67814'),
    'app/integrations/anex-additional-prices-client.php': (21737, '82811acb7908d95315287d5e552b4d8ccf1f30103f6714ee5424aec8788fd34a'),
}

STOCK_REMOTE = INSTALL_REMOTE + '\nPS_SOURCE=' + repr(STOCK_PUBLISHER_SOURCE) + '\nPS_REQUIRED=' + repr(STOCK_REQUIRED) + r'''
ps_base_snapshot=pp_snapshot
ps_base_plan=passive_fuel_activation_plan

def pp_snapshot():
    bodies,records,identities,directories=ps_base_snapshot()
    for key,path in (('stock_manifest',runtime/'anex-preview-manifest.json'),
                     ('nested_gateway',runtime/'v2/api-v2.php')):
        bodies[key],records[key]=pf_read(path,limit=1024*1024)
        identities[key]=None if records[key] is None else pf_identity(path.lstat())
    return bodies,records,identities,directories

def ps_manifest(body):
    if body is None:return False
    def unique(pairs):
        result={}
        for key,value in pairs:
            if key in result:raise ValueError('duplicate')
            result[key]=value
        return result
    try:
        value=json.loads(body,object_pairs_hook=unique,parse_constant=lambda _:None)
        if (not isinstance(value,dict) or type(value.get('schema_version')) is not int or value['schema_version']!=1
                or value.get('source_sha')!=PS_SOURCE or value.get('route')!='/_preview/search3-anex-candidate/'
                or value.get('production_entry_changes') is not False or value.get('production_lead_delivery') is not False
                or type(value.get('preview_metrika_counter')) is not int or value['preview_metrika_counter']!=0
                or value.get('mapping_scope')!='preview' or not isinstance(value.get('files'),list)
                or type(value.get('file_count')) is not int or not len(PS_REQUIRED)<=value['file_count']<=3000
                or value['file_count']!=len(value['files'])):return False
        rows={}
        for row in value['files']:
            if not isinstance(row,dict) or set(row)!={'path','sha256','bytes'}:return False
            path=row['path']
            if (not isinstance(path,str) or not 1<=len(path)<=240 or '\\' in path
                    or pathlib.PurePosixPath(path).is_absolute() or str(pathlib.PurePosixPath(path))!=path
                    or any(part in ('.','..','api-v2.php') for part in path.split('/')) or path in rows
                    or not isinstance(row['sha256'],str) or re.fullmatch('[a-f0-9]{64}',row['sha256']) is None
                    or type(row['bytes']) is not int or not 0<row['bytes']<=8*1024*1024):return False
            rows[path]=(row['bytes'],row['sha256'])
        return all(rows.get(path)==record for path,record in PS_REQUIRED.items())
    except (ValueError,UnicodeError,RecursionError):return False

def passive_fuel_activation_plan(snapshot=None):
    initial=pp_snapshot() if snapshot is None else snapshot
    value=ps_base_plan(initial)  # Full extended snapshot also checked after validation.
    records=initial[1]
    absent=records['private_gateway'] is None and records['nested_gateway'] is None
    verified=(ps_manifest(initial[0]['stock_manifest']) and records['stock_manifest']['mode']==0o644
        and initial[2]['stock_manifest'][-2]==os.getuid())
    topology=verified and absent
    value['stock_topology']={'status':'verified_stock_exclusion' if topology else 'gateway_present'
        if not absent else 'unrecognized_manifest','publisher_source_sha':PS_SOURCE,
        'required_rows_verified':verified,'manifest_record':records['stock_manifest'],
        'nested_gateway_record':records['nested_gateway']}
    if topology:value['private_caller_state']='excluded_by_stock_publisher'
    flags=('gateway_predecessor_matches','passive_predecessor_matches','dependencies_exact',
        'db_predecessor_matches','private_db_dependency_matches','root_config_predecessor_matches',
        'proxy_predecessor_matches','store_directory_owner_matches','binding_identity_stable','protected_unchanged')
    value['status']='ready' if topology and all(value[k] for k in flags) and value['store_state']=='verified_stock_binding' else 'hold'
    return value
'''


# Exact observed current publication. Both the original seven anchors and the
# newer publisher's quote-route requirement were reproduced from this source.
# This is a separate fixed closure; the completed historical stock HOLD stays sealed.
CURRENT_PUBLISHER_SOURCE = '786514ccbb4047868a89f00bb2d06dfe5157ffb8'
CURRENT_MANIFEST_SHA256 = 'b16a2b0fa7939b4f73f7c09a37fea2b15d22058f4bbe7559901011019a530223'
CURRENT_MANIFEST_BYTES = 117364
CURRENT_EMPTY_PATHS = ('search3-entry-v1.js','search3-results-cards-v2.js')
CURRENT_REQUIRED = {**STOCK_REQUIRED,
    'api-anex-search3-preview.php': (54751, '75a4168920ba4ea4ca043516d5979a05acf2be2a2757096121ccb6180c49e74e'),
    'api-andromeda-search3-preview.php': (50795, '47d41972b275913a48b8ac95101f928deb9d504cf1354ea86019f3463f076c6b'),
    'app/integrations/anex-additional-prices-client.php': (25315, 'b13657f206961c2a3e88ef5515ba2bb5739230483d35102496b479656bb4db8e'),
    'api-andromeda-quote-preview.php': (20781, '54bbab700a6507cd116744140b3bf8f4d1e05b9d549c4479ab98687d86eb6118'),
}
CURRENT_REMOTE = (STOCK_REMOTE
    .replace('PS_SOURCE='+repr(STOCK_PUBLISHER_SOURCE), 'PS_SOURCE='+repr(CURRENT_PUBLISHER_SOURCE), 1)
    .replace('PS_REQUIRED='+repr(STOCK_REQUIRED), 'PS_REQUIRED='+repr(CURRENT_REQUIRED), 1)
    .replace("or value['file_count']!=len(value['files'])", "or value['file_count']!=818 or value['file_count']!=len(value['files'])", 1)
    .replace("or not 0<row['bytes']<=8*1024*1024", "or not (0<row['bytes']<=8*1024*1024 or "
        "(row['bytes']==0 and path in PS_EMPTY_PATHS and row['sha256']==PS_EMPTY_SHA))", 1)
    .replace('verified=(ps_manifest(', 'verified=(records[\'stock_manifest\'] is not None '
        "and records['stock_manifest']['sha256']==PS_MANIFEST_PIN and records['stock_manifest']['bytes']==PS_MANIFEST_BYTES and ps_manifest(", 1)
    + '\nPS_MANIFEST_PIN='+repr(CURRENT_MANIFEST_SHA256)+'\nPS_MANIFEST_BYTES='+repr(CURRENT_MANIFEST_BYTES)
    + '\nPS_EMPTY_PATHS='+repr(CURRENT_EMPTY_PATHS)+'\nPS_EMPTY_SHA='+repr(hashlib.sha256(b'').hexdigest())+'\n')

# CURRENT-only: never expose candidate or rollback PHP as a readable .tmp in
# the web root. Retain the sealed remote programs and their old receipts.
for _old, _new, _count in (
    ('old={};candidates={};installed={}', 'old={};candidates={};installed={};stage_parent=None', 1),
    ("        snapshot_matches()\n        for index,rel in enumerate(order):\n            pi_private(",
     "        stage_parent=pi_parent(op/'activation-private-stage.php')\n"
     "        pi_current_stage_check(stage_parent,op)\n"
     "        snapshot_matches()\n        for index,rel in enumerate(order):\n            pi_private(", 1),
    ("        if lock is not None and pf_identity(os.fstat(lock))",
     "        if stage_parent is not None:\n"
     "            pi_current_stage_check(stage_parent,op)\n"
     "        if lock is not None and pf_identity(os.fstat(lock))", 1),
    ('identity=pi_write(parents[rel],name,candidates[rel],0o644)',
     'pi_current_stage_check(stage_parent,op);identity=pi_write(stage_parent,name,candidates[rel],0o644)', 1),
    ('pi_lint((project/rel).parent/name)', 'pi_lint(op/name)', 1),
    ('os.stat(name,dir_fd=fd,follow_symlinks=False)', 'os.stat(name,dir_fd=stage_parent,follow_symlinks=False)', 2),
    ('src_dir_fd=fd,dst_dir_fd=fd', 'src_dir_fd=stage_parent,dst_dir_fd=fd', 2),
    ('identity=pi_write(fd,name,old[rel],0o644)',
     'pi_current_stage_check(stage_parent,op);identity=pi_write(stage_parent,name,old[rel],0o644)', 1),
    ("                fd=parents[rel]\n", "                pi_current_stage_check(stage_parent,op);fd=stage_parent\n", 1),
    ('        for fd in parents.values():os.close(fd)',
     '        for fd in parents.values():os.close(fd)\n        if stage_parent is not None:os.close(stage_parent)', 1),
    ('            os.fsync(fd)\n            data,record=pf_read',
     '            os.fsync(fd);os.fsync(stage_parent)\n            data,record=pf_read', 1),
    ("out['rollback_files'].append(rel);os.fsync(fd)",
     "out['rollback_files'].append(rel);os.fsync(fd);os.fsync(stage_parent)", 1),
    ("        snapshot_matches();out['status']='prepared';checkpoint(",
     "        os.fsync(stage_parent);snapshot_matches();out['status']='prepared';checkpoint(", 1),
):
    need(CURRENT_REMOTE.count(_old)==_count,'current_private_staging_registration_drift')
    CURRENT_REMOTE=CURRENT_REMOTE.replace(_old,_new,_count)
del _old, _new, _count
CURRENT_REMOTE += r'''
def pi_current_stage_check(descriptor,op):
    pi_parent_stable(descriptor,op/'activation-private-stage.php')
    info=os.fstat(descriptor)
    if pf_stat.S_IMODE(info.st_mode)!=0o700 or info.st_dev!=project.lstat().st_dev:fail('activation_parent')
'''


REPAIR_PROOF_REMOTE = r'''
PR_REPAIR_OPERATION=__PR_REPAIR_OPERATION__
PR_REPAIR_SHA=__PR_REPAIR_SHA__
PR_REPAIR_BYTES=__PR_REPAIR_BYTES__
PR_PROOF_IDENTITY=None

def pr_repair_proof():
    # Read only the sealed stock correction receipt, never an arbitrary private
    # path, config, observations or log. Keep its identity bound across writes.
    global PR_PROOF_IDENTITY
    descriptors=[]
    directory_id=lambda s:(s.st_dev,s.st_ino,s.st_mode,s.st_uid,s.st_gid)
    file_id=lambda s:directory_id(s)+(s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)
    try:
        root=os.open(home,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW);descriptors.append(root)
        parent=root;directory_ids=[directory_id(os.fstat(root))]
        for name in ('.anytoour-int-executor',PR_REPAIR_OPERATION):
            child=os.open(name,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=parent)
            descriptors.append(child);info=os.fstat(child)
            if info.st_uid!=os.getuid() or pf_stat.S_IMODE(info.st_mode)!=0o700:fail('activation_repair_proof')
            directory_ids.append(directory_id(info));parent=child
        leaf=os.open('result.json',os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=parent)
        descriptors.append(leaf);before=os.fstat(leaf)
        if (not pf_stat.S_ISREG(before.st_mode) or before.st_nlink!=1 or before.st_uid!=os.getuid()
                or before.st_size!=PR_REPAIR_BYTES or pf_stat.S_IMODE(before.st_mode)!=0o600):fail('activation_repair_proof')
        data=b''
        while len(data)<=PR_REPAIR_BYTES:
            part=os.read(leaf,PR_REPAIR_BYTES+1-len(data))
            if not part:break
            data+=part
        after=os.fstat(leaf)
        if (len(data)!=PR_REPAIR_BYTES or hashlib.sha256(data).hexdigest()!=PR_REPAIR_SHA
                or file_id(before)!=file_id(after)
                or file_id(after)!=file_id(os.stat('result.json',dir_fd=parent,follow_symlinks=False))
                or directory_ids!=[directory_id(os.fstat(fd)) for fd in descriptors[:-1]]
                or directory_ids[0]!=directory_id(home.lstat()) or home.resolve()!=home):fail('activation_repair_proof')
        for index,name in enumerate(('.anytoour-int-executor',PR_REPAIR_OPERATION)):
            if directory_ids[index+1]!=directory_id(os.stat(name,dir_fd=descriptors[index],follow_symlinks=False)):
                fail('activation_repair_proof')
        identity=(tuple(directory_ids),file_id(after))
        if PR_PROOF_IDENTITY is not None and PR_PROOF_IDENTITY!=identity:fail('activation_repair_proof')
        PR_PROOF_IDENTITY=identity
        return {'status':'verified','operation_id':PR_REPAIR_OPERATION,'sha256':PR_REPAIR_SHA,
                'bytes':PR_REPAIR_BYTES,'mode':0o600,'identity_stable':True}
    except OSError:
        fail('activation_repair_proof')
    finally:
        for descriptor in reversed(descriptors):os.close(descriptor)
'''
REPAIR_PROOF_REMOTE = REPAIR_PROOF_REMOTE.replace('__PR_REPAIR_OPERATION__',repr(LOCK_REPAIR_OPERATION)).replace(
    '__PR_REPAIR_SHA__',repr(REPAIR_RECEIPT_SHA256)).replace('__PR_REPAIR_BYTES__',str(REPAIR_RECEIPT_BYTES))

# Material post-repair profile: the old CURRENT program remains byte-identical.
# This transaction additionally requires the accepted correction proof and the
# exact corrected lock shape; the same existing two-file transaction is reused.
REPAIRED_REMOTE = CURRENT_REMOTE + REPAIR_PROOF_REMOTE
for _old,_new in (
    ("'backup_retained':False,", "'backup_retained':False,'repair_proof':None,"),
    ('    def snapshot_matches():\n', '    def snapshot_matches():\n        pr_repair_proof()\n'),
    ("        out['preflight']=passive_fuel_activation_plan()\n",
     "        out['repair_proof']=pr_repair_proof()\n        out['preflight']=passive_fuel_activation_plan()\n"),
    ("'activation_syntax','activation_readback'", "'activation_syntax','activation_readback','activation_repair_proof'"),
    ('or pf_stat.S_IMODE(info.st_mode)&0o022 or info.st_size>65536',
     'or pf_stat.S_IMODE(info.st_mode)!=0o644 or info.st_size!=0'),
):
    need(REPAIRED_REMOTE.count(_old)==1,'repaired_profile_registration_drift')
    REPAIRED_REMOTE=REPAIRED_REMOTE.replace(_old,_new,1)


# New confirmed party correction: reuse the reviewed transaction and corrected
# lock proof, but activate only ROOT API. Already installed helpers are read-only.
need(REPAIRED_REMOTE.count('order=(passive,root)') == 1, 'party_registration_drift')
PARTY_REMOTE = REPAIRED_REMOTE.replace('order=(passive,root)', 'order=(root,)', 1)
PARTY_REMOTE += '\nPP_OLD_PASSIVE=' + repr(PRIVATE_SOURCE_HASHES[
    'app/integrations/tourvisor-operator-fuel-passive-intake.php']) + '\nPP_TARGETS=' + repr({
        'api-v2.php': (PRIVATE_SOURCE_HASHES['v2/api-v2.php'], PARTY_API_SHA256)}) + '\n'

# Resume only the never-started activation after its exact immutable zero-write
# HOLD. ROOT uses the release helper; the private helper retains its own pin.
PARTY_PARENT_PROOF_REMOTE = (REPAIR_PROOF_REMOTE
    .replace(repr(LOCK_REPAIR_OPERATION), repr(PARTY_OPERATION))
    .replace(repr(REPAIR_RECEIPT_SHA256), repr(PARTY_HOLD_SHA256))
    .replace('PR_REPAIR_BYTES='+str(REPAIR_RECEIPT_BYTES), 'PR_REPAIR_BYTES='+str(PARTY_HOLD_BYTES))
    .replace('PR_', 'PC_').replace('pr_repair_proof', 'pc_parent_hold_proof')
    .replace('activation_repair_proof', 'activation_parent_hold_proof'))
PARTY_CONTINUE_REMOTE = PARTY_REMOTE + PARTY_PARENT_PROOF_REMOTE + '\nPC_ROOT_DB='+repr(PARTY_ROOT_DB_SHA256)+'\n'
for _old, _new in (
    ("db_ok=digest('data/db-v1.php')==PP_DB", "db_ok=digest('data/db-v1.php')==PC_ROOT_DB"),
    ("'backup_retained':False,'repair_proof':None,", "'backup_retained':False,'repair_proof':None,'parent_hold_proof':None,"),
    ('    def snapshot_matches():\n        pr_repair_proof()',
     '    def snapshot_matches():\n        pc_parent_hold_proof()\n        pr_repair_proof()'),
    ("        out['repair_proof']=pr_repair_proof()\n",
     "        out['parent_hold_proof']=pc_parent_hold_proof()\n        out['repair_proof']=pr_repair_proof()\n"),
    ("'activation_syntax','activation_readback','activation_repair_proof'",
     "'activation_syntax','activation_readback','activation_repair_proof','activation_parent_hold_proof'"),
):
    need(PARTY_CONTINUE_REMOTE.count(_old)==1, 'party_continuation_registration_drift')
    PARTY_CONTINUE_REMOTE=PARTY_CONTINUE_REMOTE.replace(_old,_new,1)
del _old, _new


LOCK_METADATA_REMOTE = r'''
import stat as lm_stat

def publication_lock_metadata():
    # O_PATH observes the existing inode without opening its data or taking flock.
    # No caller-controlled path, content read, lock acquisition or repair exists.
    out={'status':'not_measured','reason':None,'parent':None,'lock':None,
         'home_identity_matches':None,'parent_identity_matches':None,
         'lock_identity_stable':None,'unsafe_predicates':[],
         'supplier_calls':0,'database_reads':0,'database_writes':0,
         'runtime_writes':0,'booking_calls':0,'lead_calls':0}
    descriptors=[]
    def identity(s):
        return (s.st_dev,s.st_ino,s.st_mode,s.st_uid,s.st_gid)
    def lock_identity(s):
        return identity(s)+(s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)
    def kind(s):
        for name,check in (('regular',lm_stat.S_ISREG),('directory',lm_stat.S_ISDIR),
                           ('symlink',lm_stat.S_ISLNK),('fifo',lm_stat.S_ISFIFO),
                           ('socket',lm_stat.S_ISSOCK)):
            if check(s.st_mode):return name
        return 'other'
    def record(s):
        return {'kind':kind(s),'nlink':s.st_nlink,'bytes':s.st_size,
                'mode':lm_stat.S_IMODE(s.st_mode),'owner_matches':s.st_uid==os.getuid()}
    try:
        if not hasattr(os,'O_PATH'):
            out['reason']='metadata_handle_unavailable';return out
        flags=os.O_PATH|os.O_NOFOLLOW|os.O_NONBLOCK
        root=os.open(home,flags|os.O_DIRECTORY);descriptors.append(root)
        root_before=os.fstat(root)
        try:parent=os.open('.anytoour-andromeda',flags,dir_fd=root)
        except FileNotFoundError:
            out['reason']='parent_missing';return out
        descriptors.append(parent);parent_before=os.fstat(parent)
        out['parent']=record(parent_before)
        if not lm_stat.S_ISDIR(parent_before.st_mode):
            out['reason']='parent_not_directory';return out
        try:leaf=os.open('grouped-search-update.lock',flags,dir_fd=parent)
        except FileNotFoundError:
            out['reason']='lock_missing';return out
        descriptors.append(leaf);first=os.fstat(leaf)
        entry=os.stat('grouped-search-update.lock',dir_fd=parent,follow_symlinks=False)
        last=os.fstat(leaf)
        parent_entry=os.stat('.anytoour-andromeda',dir_fd=root,follow_symlinks=False)
        out['home_identity_matches']=(identity(root_before)==identity(os.fstat(root))
                                      ==identity(home.lstat()) and home.resolve()==home)
        out['parent_identity_matches']=(identity(parent_before)==identity(os.fstat(parent))
                                        ==identity(parent_entry))
        out['lock_identity_stable']=lock_identity(first)==lock_identity(last)
        out['lock']={**record(first),'entry_identity_matches':lock_identity(last)==lock_identity(entry)}
        checks=(('home_identity',out['home_identity_matches']),
                ('parent_identity',out['parent_identity_matches']),
                ('parent_owner',out['parent']['owner_matches']),
                ('parent_writable',not out['parent']['mode']&0o022),
                ('regular_type',out['lock']['kind']=='regular'),
                ('link_count',out['lock']['nlink']==1),
                ('owner',out['lock']['owner_matches']),
                ('writable',not out['lock']['mode']&0o022),
                ('byte_size',out['lock']['bytes']<=65536),
                ('entry_identity',out['lock']['entry_identity_matches']),
                ('lock_stable',out['lock_identity_stable']))
        out['unsafe_predicates']=[name for name,valid in checks if not valid]
        out['status']='measured'
        if not (out['home_identity_matches'] and out['parent_identity_matches']
                and out['lock_identity_stable'] and out['lock']['entry_identity_matches']):
            out.update(status='not_measured',reason='metadata_changed')
        return out
    except OSError:
        out.update(status='not_measured',reason='metadata_unavailable');return out
    finally:
        for descriptor in reversed(descriptors):os.close(descriptor)
'''


LOCK_REPAIR_REMOTE = LOCK_METADATA_REMOTE + r'''
import fcntl as lm_fcntl

def publication_lock_mode_repair():
    before=publication_lock_metadata()
    out={'status':'hold','reason':'precondition_changed','before':before,'after':None,
         'mode_before':None,'mode_after':None,'permission_writes':0,
         'permission_write_attempted':False,
         'same_inode':False,'exclusive_existing_lock':False,
         'supplier_calls':0,'database_reads':0,'database_writes':0,
         'runtime_writes':0,'booking_calls':0,'lead_calls':0}
    if (before['status']!='measured' or before['unsafe_predicates']!=['writable']
            or before['lock']['mode']!=0o664 or before['lock']['bytes']!=0
            or before['parent']['mode']!=0o700):return out
    descriptors=[]
    def identity(s):return (s.st_dev,s.st_ino,s.st_mode,s.st_uid,s.st_gid)
    def full(s):return identity(s)+(s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)
    try:
        root=os.open(home,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW);descriptors.append(root)
        root_initial=os.fstat(root)
        parent=os.open('.anytoour-andromeda',os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=root)
        descriptors.append(parent);parent_initial=os.fstat(parent)
        if parent_initial.st_uid!=os.getuid() or lm_stat.S_IMODE(parent_initial.st_mode)!=0o700:
            return out
        leaf=os.open('grouped-search-update.lock',os.O_RDWR|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=parent)
        descriptors.append(leaf);initial=os.fstat(leaf)
        def stable():
            return (identity(root_initial)==identity(os.fstat(root))==identity(home.lstat())
                and home.resolve()==home
                and identity(parent_initial)==identity(os.fstat(parent))
                    ==identity(os.stat('.anytoour-andromeda',dir_fd=root,follow_symlinks=False))
                and full(initial)==full(os.fstat(leaf))
                    ==full(os.stat('grouped-search-update.lock',dir_fd=parent,follow_symlinks=False)))
        if (not lm_stat.S_ISREG(initial.st_mode) or initial.st_nlink!=1 or initial.st_size!=0
                or initial.st_uid!=os.getuid() or lm_stat.S_IMODE(initial.st_mode)!=0o664 or not stable()):return out
        out['mode_before']=0o664
        try:lm_fcntl.flock(leaf,lm_fcntl.LOCK_EX|lm_fcntl.LOCK_NB)
        except BlockingIOError:
            out['reason']='publication_busy';return out
        out['exclusive_existing_lock']=True
        if not stable():
            out['reason']='state_changed';return out
        # Remove ONLY group-write from the held existing inode. Never replace,
        # truncate, chown or release another publisher's lock.
        # A syscall may take effect before an interruption is reported. Seal the
        # uncertainty boundary first; never publish HOLD/0 after a write attempt.
        out.update(permission_write_attempted=True,permission_writes='unknown',
                   status='unknown_no_replay',reason='repair_unavailable')
        os.fchmod(leaf,0o644);out['permission_writes']=1
        changed=os.fstat(leaf)
        out['mode_after']=lm_stat.S_IMODE(changed.st_mode)
        out['same_inode']=(initial.st_dev,initial.st_ino)==(changed.st_dev,changed.st_ino)
        unchanged=lambda s:(s.st_dev,s.st_ino,lm_stat.S_IFMT(s.st_mode),s.st_uid,s.st_gid,s.st_nlink,s.st_size,s.st_mtime_ns)
        if (not out['same_inode'] or unchanged(initial)!=unchanged(changed) or out['mode_after']!=0o644):
            out.update(status='unknown_no_replay',reason='readback_failed');return out
        os.fsync(leaf)
        out['after']=publication_lock_metadata()
        if (out['after']['status']!='measured' or out['after']['unsafe_predicates']!=[]
                or out['after']['lock']['mode']!=0o644 or out['after']['lock']['bytes']!=0
                or out['after']['parent']['mode']!=0o700
                or full(changed)!=full(os.fstat(leaf))
                or full(changed)!=full(os.stat('grouped-search-update.lock',dir_fd=parent,follow_symlinks=False))
                or identity(root_initial)!=identity(os.fstat(root))
                or identity(root_initial)!=identity(home.lstat()) or home.resolve()!=home
                or identity(parent_initial)!=identity(os.fstat(parent))
                or identity(parent_initial)!=identity(os.stat('.anytoour-andromeda',dir_fd=root,follow_symlinks=False))):
            out.update(status='unknown_no_replay',reason='readback_failed');return out
        out.update(status='repaired_verified',reason=None);return out
    except OSError:
        out.update(status='unknown_no_replay' if out['permission_write_attempted'] else 'hold',reason='repair_unavailable')
        return out
    finally:
        for descriptor in reversed(descriptors):os.close(descriptor)
'''


CAPTURE_PUBLIC_PATHS = ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php')

CAPTURE_PUBLIC_REMOTE = r'''
def capture_install_public_state():
    import stat
    def identity(s):return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink)
    def version(s):return identity(s)+(s.st_size,s.st_mtime_ns,s.st_ctime_ns)
    states={}
    for relative in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'):
        descriptors=[];directories=[]
        try:
            parent=os.open(project,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)
            descriptors.append(parent);directories.append((project,parent,os.fstat(parent)))
            parts=relative.split('/');absolute=project
            for position,name in enumerate(parts):
                absolute=absolute/name
                try:initial=os.stat(name,dir_fd=parent,follow_symlinks=False)
                except FileNotFoundError:
                    states[relative]={'state':'absent'};break
                if position<len(parts)-1:
                    if not stat.S_ISDIR(initial.st_mode):fail('capture_install_public_unsafe')
                    child=os.open(name,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=parent)
                    descriptors.append(child)
                    if identity(os.fstat(child))!=identity(initial):fail('capture_install_public_unsafe')
                    directories.append((absolute,child,initial));parent=child
                else:
                    if not stat.S_ISREG(initial.st_mode) or initial.st_nlink!=1 or initial.st_size>4*1024*1024:
                        fail('capture_install_public_unsafe')
                    leaf=os.open(name,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=parent)
                    descriptors.append(leaf)
                    if version(os.fstat(leaf))!=version(initial):fail('capture_install_public_unsafe')
                    chunks=[];size=0
                    while True:
                        chunk=os.read(leaf,min(65536,4*1024*1024+1-size))
                        if not chunk:break
                        chunks.append(chunk);size+=len(chunk)
                        if size>4*1024*1024:fail('capture_install_public_unsafe')
                    if (version(os.fstat(leaf))!=version(initial)
                            or version(os.stat(name,dir_fd=parent,follow_symlinks=False))!=version(initial)):
                        fail('capture_install_public_unsafe')
                    states[relative]={'state':'regular','sha256':hashlib.sha256(b''.join(chunks)).hexdigest()}
            for path,descriptor,initial in directories:
                if (identity(os.fstat(descriptor))!=identity(initial)
                        or identity(path.lstat())!=identity(initial) or path.resolve()!=path):
                    fail('capture_install_public_unsafe')
        except OSError:fail('capture_install_public_unavailable')
        finally:
            for descriptor in reversed(descriptors):os.close(descriptor)
    if states['index.php']['state']!='regular':fail('capture_install_public_index_required')
    return states
'''


def capture_public_fingerprints(states):
    """Strict typed receipt: None is never accepted as evidence of absence."""
    if not isinstance(states,dict) or set(states)!=set(CAPTURE_PUBLIC_PATHS):return None
    values={}
    for relative,state in states.items():
        if not isinstance(state,dict):return None
        if state=={'state':'absent'} and relative!='index.php':values[relative]=None
        elif (set(state)=={'state','sha256'} and state['state']=='regular'
                and isinstance(state['sha256'],str) and re.fullmatch(r'[a-f0-9]{64}',state['sha256'])):
            values[relative]=state['sha256']
        else:return None
    return values


CAPTURE_RECONCILE_REMOTE = r'''
def capture_reconcile_file(path,limit=65536):
    import stat
    def version(s):return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)
    if path.resolve()!=path:fail('capture_reconcile_file_unsafe')
    initial=path.lstat()
    if not stat.S_ISREG(initial.st_mode) or initial.st_nlink!=1 or initial.st_size>limit:
        fail('capture_reconcile_file_unsafe')
    descriptor=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        if version(os.fstat(descriptor))!=version(initial):fail('capture_reconcile_file_drift')
        chunks=[];size=0
        while True:
            chunk=os.read(descriptor,min(65536,limit+1-size))
            if not chunk:break
            chunks.append(chunk);size+=len(chunk)
            if size>limit:fail('capture_reconcile_file_unsafe')
        if version(os.fstat(descriptor))!=version(initial) or version(path.lstat())!=version(initial):
            fail('capture_reconcile_file_drift')
        return b''.join(chunks)
    finally:os.close(descriptor)

def capture_reconcile_original():
    import stat
    target=private/CAPTURE_TARGET_OPERATION
    initial=target.lstat()
    if not stat.S_ISDIR(initial.st_mode) or target.resolve()!=target:
        fail('capture_reconcile_original_unsafe')
    reservation_raw=capture_reconcile_file(target/'reservation.json')
    result_raw=capture_reconcile_file(target/'result.json')
    if hashlib.sha256(result_raw).hexdigest()!=CAPTURE_TARGET_RESULT:
        fail('capture_reconcile_original_result')
    saved=json.loads(reservation_raw)
    if (not isinstance(saved,dict) or set(saved)!={'operation_id','source_sha','mode','reserved_at'}
            or saved['operation_id']!=CAPTURE_TARGET_OPERATION or saved['source_sha']!=CAPTURE_TARGET_SOURCE
            or saved['mode']!='install-tv-fuel-capture' or type(saved['reserved_at']) is not int or saved['reserved_at']<1):
        fail('capture_reconcile_original_reservation')
    for name in ('install-plan.json','backup'):
        path=target/name
        if path.exists() or path.is_symlink():fail('capture_reconcile_install_evidence')
    final=target.lstat()
    if (initial.st_dev,initial.st_ino,initial.st_mode)!=(final.st_dev,final.st_ino,final.st_mode):
        fail('capture_reconcile_original_drift')
    return {'result_sha256':hashlib.sha256(result_raw).hexdigest(),
            'reservation_sha256':hashlib.sha256(reservation_raw).hexdigest(),
            'no_install_plan_or_backup':True}

def capture_reconcile_public():
    try:return {'status':'measured','paths':capture_install_public_state()}
    except (OSError,RuntimeError) as error:
        code=str(error)
        if code not in ('capture_install_public_unsafe','capture_install_public_unavailable','capture_install_public_index_required'):
            code='capture_install_public_unavailable'
        path=result.pop('_capture_public_cursor',None)
        if code=='capture_install_public_index_required':path='index.php'
        return {'status':'unavailable','code':code,'path':path}
    finally:result.pop('_capture_public_cursor',None)

def capture_reconcile_runtime():
    out={}
    for relative in CAPTURE_RUNTIME_PATHS:
        try:
            data=capture_reconcile_file(runtime/relative,2*1024*1024)
            out[relative]={'status':'measured','sha256':hashlib.sha256(data).hexdigest()}
        except (OSError,RuntimeError):out[relative]={'status':'unavailable'}
    return out
'''


def capture_reconcile_remote_program(remote,command):
    policy(command)
    dispatch="    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview'):\n"
    manifest="if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    exclusion="    if mode not in ('reconcile',"
    gate="    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):"
    reservation='    op=private/operation\n'
    definitions='def db_summary(provider):\n'
    for marker in (dispatch,manifest,gate,reservation,definitions):
        need(remote.count(marker)==1,'capture_reconcile_stock_drift')
    need(remote.count(exclusion)==2,'capture_reconcile_stock_drift')
    cursor="    for relative in ('index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'):\n"
    need(CAPTURE_PUBLIC_REMOTE.count(cursor)==1,'capture_reconcile_public_drift')
    public=CAPTURE_PUBLIC_REMOTE.replace(cursor,cursor+"        result['_capture_public_cursor']=relative\n",1)
    constants=('CAPTURE_TARGET_OPERATION='+repr(CAPTURE_INSTALL_OPERATION)+'\n'
        +'CAPTURE_TARGET_RESULT='+repr(CAPTURE_RECONCILE_TARGET_RESULT)+'\n'
        +'CAPTURE_TARGET_SOURCE='+repr(CAPTURE_RECONCILE_TARGET_SOURCE)+'\n'
        +'CAPTURE_RUNTIME_PATHS='+repr((CAPTURE_INSTALL_PATH,*CAPTURE_INSTALL_DEPENDENCIES))+'\n')
    registration="    if mode=="+repr(CAPTURE_RECONCILE_MODE)+":\n"+(
        "        original=capture_reconcile_original()\n"
        "        public_before=capture_reconcile_public()\n"
        "        runtime_before=capture_reconcile_runtime()\n"
        "        runtime_after=capture_reconcile_runtime()\n"
        "        public_after=capture_reconcile_public()\n"
        "        if capture_reconcile_original()!=original:fail('capture_reconcile_original_drift')\n"
        "        result['production_after']=fingerprints()\n"
        "        if (public_after!=public_before or runtime_after!=runtime_before\n"
        "                or result['production_after']!=before):fail('capture_reconcile_read_drift')\n"
        "        public_measured=public_before['status']=='measured'\n"
        "        if public_measured and before!={k:v.get('sha256') for k,v in public_before['paths'].items()}:fail('production_drift')\n"
        "        result['capture_install_reconciliation']={\n"
        "            'target_operation':CAPTURE_TARGET_OPERATION,'target_source':CAPTURE_TARGET_SOURCE,\n"
        "            'original':original,'target_status':'unknown_no_replay',\n"
        "            'target_supplier_calls':'unknown','target_database_writes':'unknown',\n"
        "            'public_state':public_before,'runtime_files':runtime_before,\n"
        "            'original_operation_unchanged':True,'installation_attempted':False,\n"
        "            'historical_path_types_established':False,'installation_authorized':False}\n"
        "        result.update(status='reconciled_read_only',supplier_calls=0,database_writes=0,\n"
        "            runtime_changed=False,production_unchanged=public_measured)\n"
    )
    fixed="    if mode!="+repr(CAPTURE_RECONCILE_MODE)+" or operation!="+repr(CAPTURE_RECONCILE_OPERATION)+" or source!="+repr(command['source_sha'])+":fail('capture_reconcile_fixed_scope')\n"
    remote=remote.replace(definitions,constants+public+'\n'+CAPTURE_RECONCILE_REMOTE+'\n'+definitions,1)
    remote=remote.replace(dispatch,registration+dispatch,1)
    remote=remote.replace(manifest,"if not isinstance(files,dict) or files!={}:fail('capture_reconcile_manifest')",1)
    remote=remote.replace(exclusion,"    if mode not in ("+repr(CAPTURE_RECONCILE_MODE)+",'reconcile',")
    remote=remote.replace(gate,fixed+gate,1)
    remote=remote.replace(reservation,
        "    if (private/operation).exists() or (private/operation).is_symlink():\n"
        "        result.update(status='blocked',reason='operation_exists_no_replay',supplier_calls=0,database_writes=0)\n"
        "        print(json.dumps(result,separators=(',',':')));raise SystemExit(0)\n"+reservation,1)
    ast.parse(remote)
    return remote


def capture_reconcile_completed(command,result):
    record=result.get('capture_install_reconciliation')
    keys={'target_operation','target_source','original','target_status','target_supplier_calls',
          'target_database_writes','public_state','runtime_files','original_operation_unchanged',
          'installation_attempted','historical_path_types_established','installation_authorized'}
    if not isinstance(record,dict) or set(record)!=keys:return False
    original=record['original'];public=record['public_state'];runtime_files=record['runtime_files']
    if (not isinstance(original,dict) or set(original)!={'result_sha256','reservation_sha256','no_install_plan_or_backup'}
            or original['result_sha256']!=CAPTURE_RECONCILE_TARGET_RESULT
            or not isinstance(original['reservation_sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',original['reservation_sha256'])
            or original['no_install_plan_or_backup'] is not True):return False
    if not isinstance(public,dict):return False
    measured=public.get('status')=='measured'
    if measured:
        if set(public)!={'status','paths'} or capture_public_fingerprints(public['paths']) is None:return False
        if result.get('production_before')!=capture_public_fingerprints(public['paths']):return False
    elif (set(public)!={'status','code','path'} or public.get('status')!='unavailable'
            or public['path'] not in CAPTURE_PUBLIC_PATHS
            or public['code'] not in ('capture_install_public_unsafe','capture_install_public_unavailable','capture_install_public_index_required')):return False
    if not isinstance(runtime_files,dict) or set(runtime_files)!={CAPTURE_INSTALL_PATH,*CAPTURE_INSTALL_DEPENDENCIES}:return False
    for state in runtime_files.values():
        if state=={'status':'unavailable'}:continue
        if (not isinstance(state,dict) or set(state)!={'status','sha256'} or state['status']!='measured'
                or not isinstance(state['sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',state['sha256'])):return False
    return (type(result.get('schema_version')) is int and result['schema_version']==1
        and result.get('operation_id')==CAPTURE_RECONCILE_OPERATION and result.get('mode')==CAPTURE_RECONCILE_MODE
        and result.get('source_sha')==command['source_sha'] and result.get('status')=='reconciled_read_only'
        and record['target_operation']==CAPTURE_INSTALL_OPERATION and record['target_source']==CAPTURE_RECONCILE_TARGET_SOURCE
        and record['target_status']=='unknown_no_replay' and record['target_supplier_calls']==record['target_database_writes']=='unknown'
        and record['original_operation_unchanged'] is True and record['installation_attempted'] is False
        and record['historical_path_types_established'] is False and record['installation_authorized'] is False
        and result.get('runtime_changed') is False and result.get('production_unchanged') is measured
        and isinstance(result.get('production_before'),dict) and set(result['production_before'])==set(CAPTURE_PUBLIC_PATHS)
        and all(v is None or isinstance(v,str) and re.fullmatch(r'[a-f0-9]{64}',v) for v in result['production_before'].values())
        and result['production_before']==result.get('production_after')
        and all(type(result.get(k)) is int and result[k]==0 for k in ('supplier_calls','database_writes','booking_calls','lead_calls')))


def capture_install_remote_program(remote, command):
    """Restrict the existing stock installer to one reviewed replacement; no new I/O lane."""
    policy(command)
    dispatch = "    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview','install-andromeda-quote-preview'):\n"
    inventory = "    if len(selected)<20 or not any(x=='app/integrations/three-provider-fuel-evidence.php' for x in selected):\n"
    manifest = "if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    exclusion = "    if mode not in ('reconcile',"
    rollback = "    if mode in ('install-runtime','install-anex-preview','install-andromeda-preview') and install_started:\n"
    gate = "    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):"
    reservation = '    op=private/operation\n'
    snapshot = "    before=fingerprints(); result['production_before']=before\n"
    definitions = 'def db_summary(provider):\n'
    for marker in (dispatch, inventory, manifest, rollback, gate, reservation, snapshot, definitions):
        need(remote.count(marker) == 1, 'capture_install_stock_drift')
    need(remote.count(exclusion) == 2, 'capture_install_stock_drift')
    pins = {CAPTURE_INSTALL_PATH: CAPTURE_INSTALL_SHA256}
    preflight = {**CAPTURE_INSTALL_DEPENDENCIES, CAPTURE_INSTALL_PATH: CAPTURE_INSTALL_PREDECESSOR}
    registration = "    if mode==" + repr(CAPTURE_INSTALL_MODE) + ":\n" + (
        "        for relative,expected in " + repr(preflight) + ".items():\n"
        "            target=runtime/relative\n"
        "            if (not safe_file(target,2*1024*1024) or target.resolve()!=target\n"
        "                    or target.stat().st_nlink!=1 or hashlib.sha256(target.read_bytes()).hexdigest()!=expected):\n"
        "                fail('capture_install_predecessor_or_dependency_drift')\n"
        "        result['install']=install_runtime(stage,files,op)\n"
        "        result['passive_capture_public_after']=capture_install_public_state()\n"
        "        result['production_after']=fingerprints()\n"
        "        if (result['production_after']!=before\n"
        "                or result['passive_capture_public_after']!=result['passive_capture_public_before']): fail('production_drift')\n"
        "        result.update(status='installed',supplier_calls=0,database_writes=0,\n"
        "            runtime_changed=True,public_ui_entrypoints_unchanged=True)\n"
        "        result['passive_capture_install']=" + repr({'target':CAPTURE_INSTALL_PATH,
            'sha256':CAPTURE_INSTALL_SHA256, 'previous_sha256':CAPTURE_INSTALL_PREDECESSOR,
            'dependencies':CAPTURE_INSTALL_DEPENDENCIES}) + "\n"
    )
    fixed = "    if mode!=" + repr(CAPTURE_INSTALL_MODE) + " or operation!=" + repr(CAPTURE_INSTALL_OPERATION) + " or source!=" + repr(command['source_sha']) + ":fail('capture_install_fixed_scope')\n"
    remote = remote.replace(dispatch, registration + dispatch, 1)
    remote = remote.replace(definitions, CAPTURE_PUBLIC_REMOTE + '\n' + definitions, 1)
    remote = remote.replace(snapshot, snapshot + "    result['passive_capture_public_before']=capture_install_public_state()\n"
        "    if before!={k:v.get('sha256') for k,v in result['passive_capture_public_before'].items()}:fail('production_drift')\n", 1)
    remote = remote.replace(inventory, "    if mode!=" + repr(CAPTURE_INSTALL_MODE)
        + " and (len(selected)<20 or not any(x=='app/integrations/three-provider-fuel-evidence.php' for x in selected)):\n", 1)
    remote = remote.replace(manifest, "if not isinstance(files,dict) or files!=" + repr(pins) + ": fail('capture_install_manifest')", 1)
    remote = remote.replace(exclusion, "    if mode not in (" + repr(CAPTURE_INSTALL_MODE) + ",'reconcile',")
    remote = remote.replace(rollback, rollback.replace("('install-runtime',", "(" + repr(CAPTURE_INSTALL_MODE) + ",'install-runtime',"), 1)
    remote = remote.replace(gate, fixed + gate, 1)
    # Do not let the stock finally block overwrite an earlier immutable receipt.
    remote = remote.replace(reservation,
        "    if (private/operation).exists() or (private/operation).is_symlink():\n"
        "        result.update(status='blocked',reason='operation_exists_no_replay',supplier_calls=0,database_writes=0)\n"
        "        print(json.dumps(result,separators=(',',':')));raise SystemExit(0)\n" + reservation, 1)
    ast.parse(remote)
    return remote


def capture_install_completed(command, result):
    pins = {CAPTURE_INSTALL_PATH: CAPTURE_INSTALL_SHA256}
    manifest = hashlib.sha256(json.dumps(pins, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
    expected = {'target':CAPTURE_INSTALL_PATH,'sha256':CAPTURE_INSTALL_SHA256,
                'previous_sha256':CAPTURE_INSTALL_PREDECESSOR,'dependencies':CAPTURE_INSTALL_DEPENDENCIES}
    install = result.get('install')
    public_before=result.get('passive_capture_public_before')
    public_fingerprints=capture_public_fingerprints(public_before)
    return (type(result.get('schema_version')) is int and result['schema_version'] == 1
        and result.get('operation_id') == CAPTURE_INSTALL_OPERATION
        and result.get('mode') == CAPTURE_INSTALL_MODE and result.get('source_sha') == command['source_sha']
        and result.get('status') == 'installed' and result.get('passive_capture_install') == expected
        and isinstance(install,dict) and all(type(install.get(k)) is int for k in ('files','changed_files','created_files'))
        and install == {'status':'installed','source_sha':command['source_sha'],
            'files':1,'changed_files':1,'created_files':0,'manifest_sha256':manifest}
        and result.get('runtime_changed') is True and result.get('public_ui_entrypoints_unchanged') is True
        and public_fingerprints is not None
        and public_before == result.get('passive_capture_public_after')
        and result.get('production_before') == public_fingerprints
        and result['production_before'] == result.get('production_after')
        and all(type(result.get(k)) is int and result[k] == 0
                for k in ('supplier_calls','database_writes','booking_calls','lead_calls')))


CAPTURE_COMPLETE_REMOTE = r'''
def capture_complete_lineage():
    original=capture_reconcile_original()
    if original['reservation_sha256']!=CAPTURE_ORIGINAL_RESERVATION:
        fail('capture_complete_original_reservation')
    directory=private/CAPTURE_READ_OPERATION
    if not directory.is_dir() or directory.is_symlink() or directory.resolve()!=directory:
        fail('capture_complete_read_directory')
    reservation_raw=capture_reconcile_file(directory/'reservation.json')
    read_raw=capture_reconcile_file(directory/'result.json')
    if hashlib.sha256(read_raw).hexdigest()!=CAPTURE_READ_RESULT:fail('capture_complete_read_result')
    saved=json.loads(reservation_raw)
    if (not isinstance(saved,dict) or set(saved)!={'operation_id','source_sha','mode','reserved_at'}
            or saved['operation_id']!=CAPTURE_READ_OPERATION or saved['source_sha']!=CAPTURE_TARGET_SOURCE
            or saved['mode']!='reconcile-tv-fuel-capture-install'
            or type(saved['reserved_at']) is not int or saved['reserved_at']<1):
        fail('capture_complete_read_reservation')
    for name in ('install-plan.json','backup'):
        path=directory/name
        if path.exists() or path.is_symlink():fail('capture_complete_read_install_evidence')
    return {'original_result_sha256':CAPTURE_TARGET_RESULT,'original_reservation_sha256':CAPTURE_ORIGINAL_RESERVATION,
        'reconciliation_result_sha256':CAPTURE_READ_RESULT,'reconciliation_reservation_sha256':hashlib.sha256(reservation_raw).hexdigest(),
        'original_control':'1fddefb6ce28981ae44d6e57ea06a777a56d5c74',
        'reconciliation_control':'25f1eb0f68c22b955dd177b864715707619ca86d',
        'original_status':'unknown_no_replay','original_write_started':False,
        'original_operation_unchanged':True,'reconciliation_operation_unchanged':True}

def capture_complete_prewrite():
    if capture_complete_lineage()!=result['capture_install_completion']:
        fail('capture_complete_closed_receipt_drift')
    if capture_install_public_state()!=CAPTURE_EXPECTED_PUBLIC:fail('capture_complete_public_drift')
    for relative,expected in CAPTURE_EXPECTED_RUNTIME.items():
        if hashlib.sha256(capture_reconcile_file(runtime/relative,2*1024*1024)).hexdigest()!=expected:
            fail('capture_complete_runtime_drift')
'''


def capture_complete_remote_program(remote,command):
    """First previously unstarted write, using the sealed install implementation and two receipts."""
    policy(command)
    original_command=dict(command,mode=CAPTURE_INSTALL_MODE,operation_id=CAPTURE_INSTALL_OPERATION)
    remote=capture_install_remote_program(remote,original_command)
    need(remote.count(repr(CAPTURE_INSTALL_MODE))==6 and remote.count(repr(CAPTURE_INSTALL_OPERATION))==1,
         'capture_complete_profile_drift')
    remote=remote.replace(repr(CAPTURE_INSTALL_MODE),repr(CAPTURE_COMPLETE_MODE))
    remote=remote.replace(repr(CAPTURE_INSTALL_OPERATION),repr(CAPTURE_COMPLETE_OPERATION))
    definitions='def db_summary(provider):\n'
    install="        result['install']=install_runtime(stage,files,op)\n"
    prewrite='    install_started=True\n'
    need(remote.count(definitions)==1 and remote.count(install)==2 and remote.count(prewrite)==2,
         'capture_complete_stock_drift')
    split='def capture_reconcile_public():\n'
    need(CAPTURE_RECONCILE_REMOTE.count(split)==1,'capture_complete_reader_drift')
    reader=CAPTURE_RECONCILE_REMOTE.split(split)[0]
    constants=('CAPTURE_TARGET_OPERATION='+repr(CAPTURE_INSTALL_OPERATION)+'\n'
        +'CAPTURE_TARGET_RESULT='+repr(CAPTURE_RECONCILE_TARGET_RESULT)+'\n'
        +'CAPTURE_TARGET_SOURCE='+repr(CAPTURE_RECONCILE_TARGET_SOURCE)+'\n'
        +'CAPTURE_ORIGINAL_RESERVATION='+repr(CAPTURE_COMPLETE_ORIGINAL_RESERVATION)+'\n'
        +'CAPTURE_READ_OPERATION='+repr(CAPTURE_RECONCILE_OPERATION)+'\n'
        +'CAPTURE_READ_RESULT='+repr(CAPTURE_COMPLETE_READ_RESULT)+'\n'
        +'CAPTURE_EXPECTED_PUBLIC='+repr(CAPTURE_COMPLETE_PUBLIC)+'\n'
        +'CAPTURE_EXPECTED_RUNTIME='+repr({**CAPTURE_INSTALL_DEPENDENCIES,CAPTURE_INSTALL_PATH:CAPTURE_INSTALL_PREDECESSOR})+'\n')
    remote=remote.replace(definitions,constants+reader+'\n'+CAPTURE_COMPLETE_REMOTE+'\n'+definitions,1)
    remote=remote.replace(install,
        "        result['capture_install_completion']=capture_complete_lineage()\n"
        "        if result['passive_capture_public_before']!=CAPTURE_EXPECTED_PUBLIC:fail('capture_complete_public_drift')\n"
        "        capture_complete_prewrite()\n"+install+
        "        if capture_complete_lineage()!=result['capture_install_completion']:fail('capture_complete_closed_receipt_drift')\n",1)
    # Existing stock installer has prepared its backup/plan, but has not replaced a file yet.
    remote=remote.replace(prewrite,'    capture_complete_prewrite()\n'+prewrite,1)
    ast.parse(remote)
    return remote


def capture_complete_completed(command,result):
    if result.get('operation_id')!=CAPTURE_COMPLETE_OPERATION or result.get('mode')!=CAPTURE_COMPLETE_MODE:return False
    proof=result.get('capture_install_completion')
    if not isinstance(proof,dict):return False
    expected={'original_result_sha256':CAPTURE_RECONCILE_TARGET_RESULT,
        'original_reservation_sha256':CAPTURE_COMPLETE_ORIGINAL_RESERVATION,
        'reconciliation_result_sha256':CAPTURE_COMPLETE_READ_RESULT,
        'reconciliation_reservation_sha256':proof.get('reconciliation_reservation_sha256'),
        'original_control':CAPTURE_COMPLETE_RUNS[0][1],'reconciliation_control':CAPTURE_COMPLETE_RUNS[1][1],
        'original_status':'unknown_no_replay','original_write_started':False,
        'original_operation_unchanged':True,'reconciliation_operation_unchanged':True}
    if (proof!=expected or not isinstance(proof.get('reconciliation_reservation_sha256'),str)
            or not re.fullmatch(r'[a-f0-9]{64}',proof['reconciliation_reservation_sha256'])
            or proof['original_write_started'] is not False or proof['original_operation_unchanged'] is not True
            or proof['reconciliation_operation_unchanged'] is not True
            or result.get('passive_capture_public_before')!=CAPTURE_COMPLETE_PUBLIC):return False
    original_command=dict(command,mode=CAPTURE_INSTALL_MODE,operation_id=CAPTURE_INSTALL_OPERATION)
    projected=dict(result,mode=CAPTURE_INSTALL_MODE,operation_id=CAPTURE_INSTALL_OPERATION)
    return capture_install_completed(original_command,projected)


def remote_program(remote, command=None):
    command = policy(command)
    if command['mode'] == CAPTURE_COMPLETE_MODE:return capture_complete_remote_program(remote,command)
    if command['mode'] == CAPTURE_RECONCILE_MODE:return capture_reconcile_remote_program(remote,command)
    if command['mode'] == CAPTURE_INSTALL_MODE: return capture_install_remote_program(remote, command)
    if command['mode'] in (DIAGNOSTIC_MODE,CONTEXT_DIAGNOSTIC_MODE,DATE_DIAGNOSTIC_MODE):return diagnostic_remote_program(remote,command)
    mode = command['mode']
    marker = 'def local_read(scopes):\n'
    dispatch = "    if mode=='local-readback':\n"
    exclusion = "    if mode not in ('reconcile',"
    size = "if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    need(remote.count(marker) == remote.count(dispatch) == remote.count(size) == 1
         and remote.count(exclusion) == 2, 'passive_registration_drift')
    reader = CURRENT_REMOTE if mode == CURRENT_MODE else STOCK_REMOTE if mode == STOCK_MODE else INSTALL_REMOTE if mode == INSTALL_MODE else PLAN_REMOTE_READER if mode == PLAN_MODE else PRIVATE_REMOTE_READER if mode == PRIVATE_MODE else REMOTE_READER
    hashes = PRIVATE_SOURCE_HASHES if mode in (PRIVATE_MODE, PLAN_MODE, INSTALL_MODE, STOCK_MODE, CURRENT_MODE) else SOURCE_HASHES
    if mode == LOCK_MODE: reader,hashes = LOCK_METADATA_REMOTE,{}
    if mode == LOCK_REPAIR_MODE: reader,hashes = LOCK_REPAIR_REMOTE,{}
    if mode == REPAIRED_MODE: reader,hashes = REPAIRED_REMOTE,PRIVATE_SOURCE_HASHES
    if mode == PARTY_MODE: reader,hashes = PARTY_REMOTE,PARTY_SOURCE_HASHES
    if mode == PARTY_CONTINUE_MODE: reader,hashes = PARTY_CONTINUE_REMOTE,PARTY_SOURCE_HASHES
    remote = remote.replace(marker, reader + '\n' + marker, 1)
    gate = "    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):"
    need(remote.count(gate) == 1, 'passive_remote_gate')
    fixed = "    if mode==" + repr(mode) + " and (source!=" + repr(command['source_sha']) + " or operation!=" + repr(command['operation_id']) + "):fail('passive_fixed_scope')\n"
    remote = remote.replace(gate, fixed + gate, 1)
    registration = REMOTE_DISPATCH.replace(repr(MODE), repr(mode), 1)
    if mode == LOCK_MODE:
        registration = "    if mode==" + repr(mode) + ":\n" + """        result['publication_lock_metadata']=publication_lock_metadata()
        result['production_after']=fingerprints()
        result.update(status='complete',supplier_calls=0,database_reads=0,database_writes=0,
            runtime_writes=0,booking_calls=0,lead_calls=0,runtime_changed=False,
            production_unchanged=result['production_after']==before)
"""
    if mode == LOCK_REPAIR_MODE:
        registration = "    if mode==" + repr(mode) + ":\n" + """        result['publication_lock_mode_repair']=publication_lock_mode_repair()
        repair=result['publication_lock_mode_repair']
        result['production_after']=fingerprints()
        result.update(status='complete' if repair['status'] in ('hold','repaired_verified') else repair['status'],
            supplier_calls=0,database_reads=0,database_writes=0,runtime_writes=0,booking_calls=0,lead_calls=0,
            permission_writes=repair['permission_writes'],runtime_changed=False,
            permission_write_attempted=repair['permission_write_attempted'],
            publication_lock_mode_changed=None if repair['permission_writes']=='unknown' else repair['permission_writes']==1,
            production_unchanged=result['production_after']==before)
"""
    if mode == PLAN_MODE:
        registration = registration.replace("result['passive_fuel_readiness']=passive_fuel_readiness()",
            "result['passive_fuel_activation_plan']=passive_fuel_activation_plan()", 1)
    if mode in (INSTALL_MODE, STOCK_MODE, CURRENT_MODE, REPAIRED_MODE, PARTY_MODE, PARTY_CONTINUE_MODE):
        registration = "    if mode==" + repr(mode) + ":\n" + """        result['passive_fuel_activation']=passive_fuel_activate(stage,op,before)
        activation=result['passive_fuel_activation']
        result['production_after']=fingerprints()
        result.update(status='complete' if activation['status'] in ('hold','installed_verified') else activation['status'],
            supplier_calls=0,database_reads=0,database_writes=0,
            runtime_changed=activation['status']=='installed_verified' or activation['status'] in ('unknown_no_replay','rollback_failed_no_replay'),
            production_unchanged=result['production_after']==before)
"""
    remote = remote.replace(dispatch, registration + dispatch, 1)
    remote = remote.replace(exclusion, "    if mode not in (" + repr(mode) + ",'reconcile',")
    remote = remote.replace(size, "if not isinstance(files,dict) or files!=" + repr(hashes) + ": fail('passive_manifest')", 1)
    ast.parse(remote)
    return remote


def completed(command, result):
    command = policy(command)
    if command['mode'] == CAPTURE_COMPLETE_MODE:return capture_complete_completed(command,result)
    if command['mode'] == CAPTURE_RECONCILE_MODE:return capture_reconcile_completed(command,result)
    if command['mode'] == CAPTURE_INSTALL_MODE: return capture_install_completed(command, result)
    if command['mode'] in (DIAGNOSTIC_MODE,CONTEXT_DIAGNOSTIC_MODE,DATE_DIAGNOSTIC_MODE):return diagnostic_completed(command,result)
    if command['mode'] == LOCK_MODE:return lock_metadata_completed(command,result)
    if command['mode'] == LOCK_REPAIR_MODE:return lock_mode_repair_completed(command,result)
    if command['mode'] == REPAIRED_MODE:return repaired_completed(command,result)
    if command['mode'] == PARTY_MODE:return party_completed(command,result)
    if command['mode'] == PARTY_CONTINUE_MODE:return party_continue_completed(command,result)
    if command['mode'] == CURRENT_MODE:return current_completed(command,result)
    if command['mode'] == STOCK_MODE:return stock_completed(command,result)
    if command['mode'] == INSTALL_MODE:return install_completed(command,result)
    if command['mode'] == PLAN_MODE:return plan_completed(command, result)
    private = command['mode'] == PRIVATE_MODE
    value = result.get('passive_fuel_readiness')
    counts = ('supplier_calls','database_reads','database_writes','runtime_writes','booking_calls','lead_calls')
    fields = {'status','gateway_predecessor_matches','helper_layouts','selected_layout','helpers_exact',
              'config_binding','store_directory_verified','db_helper_integrity','db_helper_declarations',
              'loaded_db_state','protected_before','protected_after','private_config_sha256',
              'protected_unchanged',*counts}
    if private:fields.update(('private_config_record','store_directory_mode','store_directory_owner_matches'))
    def digest(value):
        return isinstance(value,str) and re.fullmatch('[a-f0-9]{64}',value) is not None
    def record(value):
        return value is None or (isinstance(value,dict) and set(value)=={'sha256','bytes','mode'}
            and digest(value['sha256']) and type(value['bytes']) is int and 0<value['bytes']<=2*1024*1024
            and type(value['mode']) is int and 0<=value['mode']<=0o7777)
    if not isinstance(value,dict) or set(value)!=fields:return False
    if private and (not record(value['private_config_record'])
            or value['store_directory_mode'] is not None and (type(value['store_directory_mode']) is not int
                or not 0<=value['store_directory_mode']<=0o7777)
            or type(value['store_directory_owner_matches']) is not bool):return False
    before=value['protected_before'];layouts=value['helper_layouts']
    if (not isinstance(before,dict) or set(before)!=set(PROTECTED) or not all(map(record,before.values()))
            or not isinstance(layouts,dict) or set(layouts)!=({'private_int'} if private else {'root','parent'})):return False
    helpers={p for p in SOURCE_HASHES if p.startswith('app/')}
    if any(not isinstance(rows,dict) or set(rows)!=helpers or not all(map(record,rows.values()))
           for rows in layouts.values()):return False
    if (value['config_binding'] not in ('missing','unrecognized','outside_scope','store_unavailable','verified_literal')
            or value['selected_layout'] not in ((None,'private_int') if private else (None,'root','parent'))
            or value['loaded_db_state']!='not_executed'
            or type(value['db_helper_declarations']) is not int or not 0<=value['db_helper_declarations']<=4
            or value['private_config_sha256'] is not None and not digest(value['private_config_sha256'])
            or any(type(value[key]) is not bool for key in ('gateway_predecessor_matches','helpers_exact',
                   'store_directory_verified','db_helper_integrity','protected_unchanged'))):return False
    return (all(result.get(k) == command[k] for k in command)
            and result.get('status') == 'complete' and result.get('production_unchanged') is True
            and result.get('runtime_changed') is False
            and isinstance(value, dict) and value.get('status') in ('ready', 'not_ready')
            and value.get('protected_unchanged') is True
            and isinstance(value.get('protected_before'), dict)
            and value.get('protected_before') == value.get('protected_after')
            and all(type(result.get(k)) is int and result[k] == 0
                    for k in ('supplier_calls', 'database_reads', 'database_writes', 'booking_calls', 'lead_calls'))
            and all(type(value.get(k)) is int and value[k] == 0 for k in counts))


def lock_metadata_completed(command,result):
    value=result.get('publication_lock_metadata')
    counts={'supplier_calls','database_reads','database_writes','runtime_writes','booking_calls','lead_calls'}
    fields={'status','reason','parent','lock','home_identity_matches','parent_identity_matches',
            'lock_identity_stable','unsafe_predicates',*counts}
    if not isinstance(value,dict) or set(value)!=fields:return False
    if (value['status'] not in ('measured','not_measured')
            or value['reason'] not in (None,'metadata_handle_unavailable','parent_missing',
                'parent_not_directory','lock_missing','metadata_changed','metadata_unavailable')
            or value['status']=='measured' and value['reason'] is not None
            or value['status']=='not_measured' and value['reason'] is None):return False
    def record(row,leaf=False):
        keys={'kind','nlink','bytes','mode','owner_matches'}
        if leaf:keys.add('entry_identity_matches')
        return (isinstance(row,dict) and set(row)==keys
            and row['kind'] in ('regular','directory','symlink','fifo','socket','other')
            and all(type(row[k]) is int and 0<=row[k]<2**63 for k in ('nlink','bytes'))
            and type(row['mode']) is int and 0<=row['mode']<=0o7777
            and type(row['owner_matches']) is bool
            and (not leaf or type(row['entry_identity_matches']) is bool))
    if any(value[k] is not None and type(value[k]) is not bool for k in
            ('home_identity_matches','parent_identity_matches','lock_identity_stable')):return False
    if (value['parent'] is not None and not record(value['parent'])
            or value['lock'] is not None and not record(value['lock'],True)
            or not isinstance(value['unsafe_predicates'],list)
            or any(type(k) is not str for k in value['unsafe_predicates'])
            or len(value['unsafe_predicates'])!=len(set(value['unsafe_predicates']))):return False
    allowed={'home_identity','parent_identity','parent_owner','parent_writable','regular_type',
             'link_count','owner','writable','byte_size','entry_identity','lock_stable'}
    if any(type(k) is not str or k not in allowed for k in value['unsafe_predicates']):return False
    if value['status']=='measured':
        if (value['parent'] is None or value['parent']['kind']!='directory' or value['lock'] is None
                or any(value[k] is not True for k in ('home_identity_matches','parent_identity_matches','lock_identity_stable'))
                or value['lock']['entry_identity_matches'] is not True):return False
        parent,leaf=value['parent'],value['lock']
        expected=[name for name,valid in (('parent_owner',parent['owner_matches']),
            ('parent_writable',not parent['mode']&0o022),('regular_type',leaf['kind']=='regular'),
            ('link_count',leaf['nlink']==1),('owner',leaf['owner_matches']),
            ('writable',not leaf['mode']&0o022),('byte_size',leaf['bytes']<=65536)) if not valid]
        if value['unsafe_predicates']!=expected:return False
    before=result.get('production_before')
    return (all(result.get(k)==command[k] for k in command)
        and result.get('status')=='complete' and result.get('runtime_changed') is False
        and result.get('production_unchanged') is True
        and isinstance(before,dict) and set(before)=={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
        and before==result.get('production_after')
        and all(type(value[k]) is int and value[k]==0 and type(result.get(k)) is int and result[k]==0 for k in counts))


def lock_mode_repair_completed(command,result):
    value=result.get('publication_lock_mode_repair')
    counts={'supplier_calls','database_reads','database_writes','runtime_writes','booking_calls','lead_calls'}
    fields={'status','reason','before','after','mode_before','mode_after','permission_writes',
            'permission_write_attempted','same_inode','exclusive_existing_lock',*counts}
    if not isinstance(value,dict) or set(value)!=fields:return False
    if (type(value['permission_writes']) is not int or value['permission_writes'] not in (0,1)
            or type(value['same_inode']) is not bool or type(value['exclusive_existing_lock']) is not bool
            or type(value['permission_write_attempted']) is not bool
            or result.get('permission_write_attempted') is not value['permission_write_attempted']
            or any(type(value[k]) is not int or value[k]!=0 for k in counts)
            or type(result.get('permission_writes')) is not int
            or result['permission_writes']!=value['permission_writes']
            or result.get('publication_lock_mode_changed') is not (value['permission_writes']==1)
            or any(result.get(k)!=command[k] for k in command)):return False
    inspection=parse([LOCK_SOURCE,LOCK_MODE,LOCK_OPERATION])
    def metadata_valid(row):
        return lock_metadata_completed(inspection,{**result,**inspection,'publication_lock_metadata':row})
    if not metadata_valid(value['before']):return False
    if value['status']=='hold':
        return (value['reason'] in ('precondition_changed','publication_busy','state_changed','repair_unavailable')
            and value['permission_writes']==0 and value['after'] is None and value['mode_after'] is None
            and value['permission_write_attempted'] is False
            and (value['mode_before'] is None or type(value['mode_before']) is int and value['mode_before']==0o664)
            and value['same_inode'] is False)
    if value['status']!='repaired_verified' or value['reason'] is not None or not metadata_valid(value['after']):return False
    before,after=value['before'],value['after']
    return (value['permission_writes']==1 and value['exclusive_existing_lock'] is True and value['same_inode'] is True
        and value['permission_write_attempted'] is True
        and type(value['mode_before']) is int and value['mode_before']==0o664
        and type(value['mode_after']) is int and value['mode_after']==0o644
        and before['status']==after['status']=='measured'
        and before['unsafe_predicates']==['writable'] and after['unsafe_predicates']==[]
        and before['lock']['mode']==0o664 and after['lock']['mode']==0o644
        and before['lock']['bytes']==after['lock']['bytes']==0
        and before['parent']['mode']==after['parent']['mode']==0o700)


def repaired_completed(command,result):
    if (command!=parse([REPAIRED_SOURCE,REPAIRED_MODE,REPAIRED_OPERATION])
            or any(result.get(k)!=command[k] for k in command)):return False
    activation=result.get('passive_fuel_activation')
    if not isinstance(activation,dict):return False
    normalized=dict(activation);proof=normalized.pop('repair_proof',None)
    if (proof!={'status':'verified','operation_id':LOCK_REPAIR_OPERATION,
            'sha256':REPAIR_RECEIPT_SHA256,'bytes':REPAIR_RECEIPT_BYTES,'mode':0o600,'identity_stable':True}
            or not isinstance(proof,dict) or type(proof.get('bytes')) is not int
            or type(proof.get('mode')) is not int or type(proof.get('identity_stable')) is not bool):return False
    original=parse([CURRENT_SOURCE,CURRENT_MODE,CURRENT_OPERATION])
    return current_completed(original,{**result,**original,'passive_fuel_activation':normalized})


def party_continue_completed(command, result):
    if (command != parse([PARTY_CONTINUE_SOURCE,PARTY_CONTINUE_MODE,PARTY_CONTINUE_OPERATION])
            or any(result.get(k) != command[k] for k in command)):return False
    value=result.get('passive_fuel_activation')
    if not isinstance(value,dict):return False
    normalized=dict(value);proof=normalized.pop('parent_hold_proof',None)
    if (proof != {'status':'verified','operation_id':PARTY_OPERATION,'sha256':PARTY_HOLD_SHA256,
            'bytes':PARTY_HOLD_BYTES,'mode':0o600,'identity_stable':True}
            or not isinstance(proof,dict) or type(proof.get('bytes')) is not int
            or type(proof.get('mode')) is not int or type(proof.get('identity_stable')) is not bool):return False
    pre=normalized.get('preflight')
    if (not isinstance(pre,dict) or not isinstance(pre.get('protected_before'),dict)
            or pre['protected_before'].get('data/db-v1.php') != {
                'sha256':PARTY_ROOT_DB_SHA256,'bytes':3861,'mode':0o644}
            or pre.get('private_db_record') != {
                'sha256':'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13',
                'bytes':3282,'mode':0o644}):return False
    original=parse([PARTY_SOURCE,PARTY_MODE,PARTY_OPERATION])
    return party_completed(original,{**result,**original,'passive_fuel_activation':normalized})


def party_completed(command, result):
    """Accept only one API replacement, retaining the complete existing proof."""
    if (command != parse([PARTY_SOURCE, PARTY_MODE, PARTY_OPERATION])
            or any(result.get(k) != command[k] for k in command)
            or result.get('status') != 'complete' or result.get('production_unchanged') is not True
            or result.get('production_before') != result.get('production_after')
            or not isinstance(result.get('production_before'), dict)
            or set(result['production_before']) != {'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}):
        return False
    zero = ('supplier_calls','database_reads','database_writes','booking_calls','lead_calls')
    value = result.get('passive_fuel_activation')
    fields = {'status','reason','preflight','publication_lock','changed_files','rollback_files',
        'before_files','after_files','protected_after','protected_unchanged_except_target',
        'runtime_writes','staging_file_writes','backup_retained','repair_proof',*zero}
    if (not isinstance(value, dict) or set(value) != fields
            or any(type(result.get(k)) is not int or result[k] != 0
                   or type(value[k]) is not int or value[k] != 0 for k in zero)
            or value['protected_unchanged_except_target'] is not True
            or value['repair_proof'] != {'status':'verified','operation_id':LOCK_REPAIR_OPERATION,
                'sha256':REPAIR_RECEIPT_SHA256,'bytes':REPAIR_RECEIPT_BYTES,'mode':0o600,'identity_stable':True}):
        return False
    if (type(value['repair_proof']['bytes']) is not int or type(value['repair_proof']['mode']) is not int
            or type(value['repair_proof']['identity_stable']) is not bool):return False
    pre = value['preflight']
    flags = {'gateway_predecessor_matches','passive_predecessor_matches','dependencies_exact',
        'db_predecessor_matches','private_db_dependency_matches','root_config_predecessor_matches',
        'proxy_predecessor_matches','store_directory_owner_matches','binding_identity_stable','protected_unchanged'}
    pre_fields = {'status','private_caller_state','store_state','store_directory_mode','backing_config_record',
        'private_gateway_record','private_db_record','proxy_record','loaded_db_state','plan_files',
        'protected_before','protected_after','stock_topology','runtime_writes',*flags,*zero}
    def record(row):
        return row is None or (isinstance(row,dict) and set(row) == {'sha256','bytes','mode'}
            and isinstance(row['sha256'],str) and re.fullmatch('[a-f0-9]{64}',row['sha256']) is not None
            and type(row['bytes']) is int and 0 < row['bytes'] <= 2*1024*1024
            and type(row['mode']) is int and 0 <= row['mode'] <= 0o7777)
    if (not isinstance(pre,dict) or set(pre) != pre_fields
            or pre['status'] not in ('ready','hold') or any(type(pre[k]) is not bool for k in flags)
            or pre['loaded_db_state'] != 'not_executed'
            or pre['binding_identity_stable'] is not True or pre['protected_unchanged'] is not True
            or any(type(pre[k]) is not int or pre[k] != 0 for k in (*zero,'runtime_writes'))
            or not all(record(pre[k]) for k in ('backing_config_record','private_gateway_record','private_db_record','proxy_record'))
            or pre['store_directory_mode'] is not None and (type(pre['store_directory_mode']) is not int
                or not 0 <= pre['store_directory_mode'] <= 0o7777)
            or pre['private_caller_state'] not in ('excluded_by_stock_publisher','missing','unrecognized','compatible_pr4732','legacy_eager_dependency')
            or pre['store_state'] not in ('verified_stock_binding','config_unrecognized','config_binding_mismatch','store_unavailable')
            or pre['plan_files'] != {'api-v2.php':{'predecessor_sha256':PRIVATE_SOURCE_HASHES['v2/api-v2.php'],
                                                'candidate_sha256':PARTY_API_SHA256}}):
        return False
    before = pre['protected_before']
    if (not isinstance(before,dict) or set(before) != set(PROTECTED)
            or not all(map(record,before.values())) or pre['protected_after'] != before):return False
    topology = pre['stock_topology']
    if (not isinstance(topology,dict) or set(topology) != {'status','publisher_source_sha','required_rows_verified',
            'manifest_record','nested_gateway_record'} or topology['publisher_source_sha'] != CURRENT_PUBLISHER_SOURCE
            or topology['status'] not in ('verified_stock_exclusion','gateway_present','unrecognized_manifest')
            or type(topology['required_rows_verified']) is not bool
            or not record(topology['manifest_record']) or not record(topology['nested_gateway_record'])):return False
    if value['status'] == 'hold':
        return (value['reason'] in ('preflight_hold','publication_lock_missing','publication_busy')
            and (value['reason'] != 'preflight_hold' or pre['status'] == 'hold')
            and value['publication_lock'] in ('not_acquired','exclusive_existing')
            and value['changed_files'] == value['rollback_files'] == []
            and value['before_files'] == value['after_files'] == {}
            and type(value['runtime_writes']) is int and value['runtime_writes'] == 0
            and type(value['staging_file_writes']) is int and value['staging_file_writes'] == 0
            and value['backup_retained'] is False and result.get('runtime_changed') is False
            and value['protected_after'] == before)
    old = {'sha256':PRIVATE_SOURCE_HASHES['v2/api-v2.php'],'bytes':20233,'mode':0o644}
    new = {'sha256':PARTY_API_SHA256,'bytes':20362,'mode':0o644}
    return (value['status'] == 'installed_verified' and value['reason'] is None
        and pre['status'] == 'ready' and all(pre[k] for k in flags)
        and pre['private_caller_state'] == 'excluded_by_stock_publisher'
        and pre['store_state'] == 'verified_stock_binding' and pre['private_gateway_record'] is None
        and topology == {'status':'verified_stock_exclusion','publisher_source_sha':CURRENT_PUBLISHER_SOURCE,
            'required_rows_verified':True,'manifest_record':{'sha256':CURRENT_MANIFEST_SHA256,
                'bytes':CURRENT_MANIFEST_BYTES,'mode':0o644},'nested_gateway_record':None}
        and value['publication_lock'] == 'exclusive_existing' and value['backup_retained'] is True
        and value['changed_files'] == ['api-v2.php'] and value['rollback_files'] == []
        and value['before_files'] == {'api-v2.php':old} and value['after_files'] == {'api-v2.php':new}
        and before['api-v2.php'] == old and value['protected_after'] == {**before,'api-v2.php':new}
        and type(value['runtime_writes']) is int and value['runtime_writes'] == 1
        and type(value['staging_file_writes']) is int and value['staging_file_writes'] == 1
        and result.get('runtime_changed') is True)


def plan_completed(command, result):
    value=result.get('passive_fuel_activation_plan')
    counts={'supplier_calls','database_reads','database_writes','runtime_writes','booking_calls','lead_calls'}
    flags={'gateway_predecessor_matches','passive_predecessor_matches','dependencies_exact',
           'db_predecessor_matches','private_db_dependency_matches','root_config_predecessor_matches','proxy_predecessor_matches',
           'store_directory_owner_matches','binding_identity_stable','protected_unchanged'}
    fields={'status','private_caller_state','store_state','store_directory_mode','backing_config_record',
            'private_gateway_record','private_db_record','proxy_record','loaded_db_state','plan_files','protected_before','protected_after',
            *flags,*counts}
    def record(row):
        return row is None or (isinstance(row,dict) and set(row)=={'sha256','bytes','mode'}
            and isinstance(row['sha256'],str) and re.fullmatch('[a-f0-9]{64}',row['sha256']) is not None
            and type(row['bytes']) is int and 0<row['bytes']<=2*1024*1024
            and type(row['mode']) is int and 0<=row['mode']<=0o7777)
    if not isinstance(value,dict) or set(value)!=fields:return False
    if (value['status'] not in ('ready','hold') or any(type(value[k]) is not bool for k in flags)
            or value['private_caller_state'] not in ('compatible_pr4732','legacy_eager_dependency','missing','unrecognized')
            or value['store_state'] not in ('verified_stock_binding','config_unrecognized','config_binding_mismatch','store_unavailable')
            or value['store_directory_mode'] is not None and (type(value['store_directory_mode']) is not int
                or not 0<=value['store_directory_mode']<=0o7777)
            or value['loaded_db_state']!='not_executed'
            or any(not record(value[k]) for k in ('backing_config_record','private_gateway_record','private_db_record','proxy_record'))):return False
    before=value['protected_before']
    if (not isinstance(before,dict) or set(before)!=set(PROTECTED) or not all(map(record,before.values()))
            or before!=value['protected_after'] or value['protected_unchanged'] is not True
            or value['binding_identity_stable'] is not True):return False
    expected={'api-v2.php':{'predecessor_sha256':'01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61',
                          'candidate_sha256':PRIVATE_SOURCE_HASHES['v2/api-v2.php']},
        '_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php':{
            'predecessor_sha256':'be4380b64caf4c98fc572c8db526f079a89bd04c301db5eccea3c2f2f232cc22',
            'candidate_sha256':PRIVATE_SOURCE_HASHES['app/integrations/tourvisor-operator-fuel-passive-intake.php']}}
    if value['plan_files']!=expected:return False
    if value['status']=='ready' and (not all(value[k] for k in flags)
            or value['private_caller_state']!='compatible_pr4732' or value['store_state']!='verified_stock_binding'):return False
    return (command==parse([PLAN_SOURCE,PLAN_MODE,PLAN_OPERATION])
        and all(result.get(k)==command[k] for k in command) and result.get('status')=='complete'
        and result.get('production_unchanged') is True and result.get('runtime_changed') is False
        and all(type(value[k]) is int and value[k]==0 for k in counts)
        and all(type(result.get(k)) is int and result[k]==0 for k in counts-{'runtime_writes'}))


def install_completed(command,result):
    value=result.get('passive_fuel_activation')
    fields={'status','reason','preflight','publication_lock','changed_files','rollback_files',
        'before_files','after_files','protected_after','protected_unchanged_except_target',
        'runtime_writes','staging_file_writes','backup_retained',
        'supplier_calls','database_reads','database_writes','booking_calls','lead_calls'}
    if not isinstance(value,dict) or set(value)!=fields:return False
    counts=('supplier_calls','database_reads','database_writes','booking_calls','lead_calls')
    if (any(type(value[k]) is not int or value[k]!=0 or type(result.get(k)) is not int or result[k]!=0 for k in counts)
            or result.get('status')!='complete' or result.get('production_unchanged') is not True
            or result.get('production_before')!=result.get('production_after')
            or not isinstance(result.get('production_before'),dict)
            or set(result['production_before'])!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or command!=parse([INSTALL_SOURCE,INSTALL_MODE,INSTALL_OPERATION])
            or any(result.get(k)!=command[k] for k in command)
            or value['protected_unchanged_except_target'] is not True):return False
    plan_command=parse([PLAN_SOURCE,PLAN_MODE,PLAN_OPERATION])
    if not plan_completed(plan_command,{**plan_command,'status':'complete','runtime_changed':False,
            'production_unchanged':True,**dict.fromkeys(counts,0),'passive_fuel_activation_plan':value['preflight']}):return False
    preflight=value['preflight']
    if value['status']=='hold':
        return (value['reason'] in ('preflight_hold','publication_lock_missing','publication_busy')
            and (value['reason']!='preflight_hold' or preflight['status']=='hold')
            and value['publication_lock'] in ('not_acquired','exclusive_existing')
            and value['changed_files']==value['rollback_files']==[]
            and value['before_files']==value['after_files']=={}
            and type(value['runtime_writes']) is int and value['runtime_writes']==0
            and type(value['staging_file_writes']) is int and value['staging_file_writes']==0
            and value['backup_retained'] is False and result.get('runtime_changed') is False
            and value['protected_after']==preflight['protected_before'])
    root='api-v2.php'
    passive='_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php'
    before={root:{'sha256':'01ac5ed43961f73879cd36c2c1d387ac79fadaf53ad66d3c4aaebcc617464c61','bytes':16366,'mode':0o644},
        passive:{'sha256':'be4380b64caf4c98fc572c8db526f079a89bd04c301db5eccea3c2f2f232cc22','bytes':13823,'mode':0o644}}
    after={root:{'sha256':PRIVATE_SOURCE_HASHES['v2/api-v2.php'],'bytes':20233,'mode':0o644},
        passive:{'sha256':PRIVATE_SOURCE_HASHES['app/integrations/tourvisor-operator-fuel-passive-intake.php'],'bytes':13766,'mode':0o644}}
    expected_protected={**preflight['protected_before'],root:after[root]}
    return (value['status']=='installed_verified' and value['reason'] is None and preflight['status']=='ready'
        and value['publication_lock']=='exclusive_existing' and value['backup_retained'] is True
        and value['changed_files']==[passive,root] and value['rollback_files']==[]
        and value['before_files']==before and value['after_files']==after
        and preflight['protected_before'][root]==before[root] and value['protected_after']==expected_protected
        and type(value['runtime_writes']) is int and value['runtime_writes']==2
        and type(value['staging_file_writes']) is int and value['staging_file_writes']==2
        and result.get('runtime_changed') is True)


def stock_completed(command,result):
    if (command!=parse([STOCK_SOURCE,STOCK_MODE,STOCK_OPERATION])
            or any(result.get(k)!=command[k] for k in command)):return False
    value=result.get('passive_fuel_activation')
    if not isinstance(value,dict) or not isinstance(value.get('preflight'),dict):return False
    preflight=dict(value['preflight']);topology=preflight.pop('stock_topology',None)
    if (not isinstance(topology,dict) or set(topology)!={'status','publisher_source_sha','required_rows_verified',
            'manifest_record','nested_gateway_record'} or topology['publisher_source_sha']!=STOCK_PUBLISHER_SOURCE
            or type(topology['required_rows_verified']) is not bool
            or topology['status'] not in ('verified_stock_exclusion','gateway_present','unrecognized_manifest')):return False
    def record(row):
        return row is None or (isinstance(row,dict) and set(row)=={'sha256','bytes','mode'}
            and isinstance(row['sha256'],str) and re.fullmatch('[a-f0-9]{64}',row['sha256']) is not None
            and type(row['bytes']) is int and 0<row['bytes']<=1024*1024
            and type(row['mode']) is int and 0<=row['mode']<=0o7777)
    if not all(record(topology[k]) for k in ('manifest_record','nested_gateway_record')):return False
    if topology['status']=='verified_stock_exclusion':
        if (topology['required_rows_verified'] is not True or topology['manifest_record'] is None
                or topology['manifest_record']['mode']!=0o644
                or topology['nested_gateway_record'] is not None or preflight.get('private_gateway_record') is not None
                or preflight.get('private_caller_state')!='excluded_by_stock_publisher'):return False
        # Reuse the complete original two-file receipt validator only after this
        # separate positive publisher-topology proof, never for the sealed mode.
        preflight['private_caller_state']='compatible_pr4732'
    elif preflight.get('status')!='hold' or preflight.get('private_caller_state')=='excluded_by_stock_publisher':return False
    original=parse([INSTALL_SOURCE,INSTALL_MODE,INSTALL_OPERATION])
    normalized={**result,**original,'passive_fuel_activation':{**value,'preflight':preflight}}
    return install_completed(original,normalized)


def current_completed(command,result):
    if (command!=parse([CURRENT_SOURCE,CURRENT_MODE,CURRENT_OPERATION])
            or any(result.get(k)!=command[k] for k in command)):return False
    value=result.get('passive_fuel_activation')
    if not isinstance(value,dict) or not isinstance(value.get('preflight'),dict):return False
    preflight=dict(value['preflight']);topology=preflight.get('stock_topology')
    if not isinstance(topology,dict) or topology.get('publisher_source_sha')!=CURRENT_PUBLISHER_SOURCE:return False
    topology=dict(topology)
    if topology.get('status')=='verified_stock_exclusion' and topology.get('manifest_record')!={
            'sha256':CURRENT_MANIFEST_SHA256,'bytes':CURRENT_MANIFEST_BYTES,'mode':0o644}:return False
    # Pure receipt-shape reuse after exact CURRENT proof; no old admission or
    # operation is executed and the old source/mode validators remain unchanged.
    topology['publisher_source_sha']=STOCK_PUBLISHER_SOURCE;preflight['stock_topology']=topology
    original=parse([STOCK_SOURCE,STOCK_MODE,STOCK_OPERATION])
    return stock_completed(original,{**result,**original,'passive_fuel_activation':{**value,'preflight':preflight}})


# The new scope exports already-safe receipts, never raw/pending/rule/config files.
DIAGNOSTIC_ERROR_CODES = frozenset(["TV_PASSIVE_TOUR_ID","TV_PASSIVE_OPERATOR","TV_PASSIVE_OPERATOR_CONFLICT","TV_PASSIVE_DATE","TV_PASSIVE_DATE_CONFLICT","TV_PASSIVE_PARTY_CONFLICT","TV_PASSIVE_LOCK","TV_PASSIVE_PAYLOAD_INTEGRITY","TV_PASSIVE_CONTEXT_INTEGRITY","TV_PASSIVE_PARTY","TV_PASSIVE_CONTEXT_CONFLICT","TV_PASSIVE_PENDING_INVALID","TV_PASSIVE_FLIGHTS","TV_PASSIVE_MARKET","TV_PASSIVE_DIRECTORY","TV_PASSIVE_WRITE","TV_FUEL_TOUR","TV_FUEL_FLIGHTS","TV_FUEL_TOUR_ID","TV_FUEL_OPERATOR","TV_FUEL_PARTY","TV_FUEL_RESPONSE_ERROR","TV_FUEL_COMBINATIONS","TV_FUEL_DEFAULT","TV_FUEL_FORWARD","TV_FUEL_BACKWARD","TV_FUEL_DATE","TV_FUEL_AMOUNT","TV_FUEL_AMOUNT_CONFLICT","TV_FUEL_CURRENCY_CONFLICT","TV_FUEL_INFO","TV_FUEL_FLAGS","TV_FUEL_REQUIRED_CHARGES","TV_FUEL_SOURCE_DIGEST","TV_FUEL_TIME","TV_FUEL_MARKET","TV_FUEL_PERIOD","TV_FUEL_LEG","TV_FUEL_PORT","TV_FUEL_CARRIER","TV_FUEL_FLIGHT","TV_FUEL_CURRENCY","OPERATOR_FUEL_STORE_CONFLICT","OPERATOR_FUEL_STORE_SIZE","OPERATOR_FUEL_STORE_WRITE","OPERATOR_FUEL_STORE_READBACK","OPERATOR_FUEL_STORE_INVALID","OPERATOR_FUEL_STORE_ROOT","OPERATOR_FUEL_STORE_DIGEST"])
DIAGNOSTIC_VALIDATOR = r'''
def pd_validate(value,tour_id,stage,read_at):
    fields={'schema_version','source','stage','outcome','code','observed_at','expires_at',
            'elapsed_ms','offer_ref_digest','normalized_response_sha256','observation_count','rule_confirmed'}
    if not isinstance(value,dict) or set(value)!=fields:raise ValueError('diagnostic_schema')
    if (type(value['schema_version']) is not int or value['schema_version']!=1
        or value['source']!='tourvisor-operator-fuel-passive-intake-v1'
        or value['stage']!=stage or value['offer_ref_digest']!=hashlib.sha256(('tourvisor:tour:'+tour_id).encode()).hexdigest()
        or not isinstance(value['normalized_response_sha256'],str)
        or re.fullmatch(r'[a-f0-9]{64}',value['normalized_response_sha256']) is None):
        raise ValueError('diagnostic_schema')
    observed=value['observed_at'];expiry=value['expires_at'];elapsed=value['elapsed_ms']
    if (type(observed) is not int or not PD_MIN_TIME<=observed<=read_at+60
        or type(expiry) is not int or expiry!=observed+86400
        or type(elapsed) is not int or not 0<=elapsed<=2147483647):
        raise ValueError('diagnostic_schema')
    outcome=value['outcome'];code=value['code']
    if not isinstance(code,str):raise ValueError('diagnostic_schema')
    if outcome=='retained':
        allowed={'tour_retained'} if stage=='tour' else {'created','appended','unchanged'}
    elif outcome=='skipped':
        allowed={'operator_not_target','offer_context_missing','tour_pair_missing','tour_pair_expired'}
    elif outcome=='rejected':
        allowed=PD_ERROR_CODES|{'unclassified_error'}
    else:raise ValueError('diagnostic_schema')
    if code not in allowed:raise ValueError('diagnostic_schema')
    count=value['observation_count'];confirmed=value['rule_confirmed']
    if outcome=='retained' and stage=='flights':
        if type(count) is not int or not 1<=count<=1000000 or type(confirmed) is not bool:
            raise ValueError('diagnostic_schema')
    elif count is not None or confirmed is not None:raise ValueError('diagnostic_schema')
    return value
'''

DIAGNOSTIC_READER = r'''
def pf_identity(s):
    return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns,s.st_uid,s.st_gid)

def pd_read(path,limit):
    try:
        try:before=pf_identity(path.lstat())
        except FileNotFoundError:before=None
        data,record=pf_read(path,limit=limit)
        identity=None if data is None else pf_identity(path.lstat())
        if identity!=before:fail('diagnostic_snapshot_changed')
        if data is not None and path.lstat().st_uid!=os.getuid():fail('diagnostic_file_owner')
        return data,record,identity
    except Exception:fail('diagnostic_file_unreadable')

def pd_object(pairs):
    value={}
    for key,item in pairs:
        if key in value:raise ValueError('diagnostic_schema')
        value[key]=item
    return value

def passive_fuel_diagnostic_readback():
    helper=project/'_preview/search3-anex-candidate/app/integrations/tourvisor-operator-fuel-passive-intake.php'
    data,record,identity=pd_read(helper,65536)
    if record is None or record['sha256']!=PD_HELPER_SHA:fail('diagnostic_helper_pin')
    read_at=int(time.time());rows=[];identities={}
    for tour_id in PD_IDS:
        offer=hashlib.sha256(('tourvisor:tour:'+tour_id).encode()).hexdigest()
        for stage in ('tour','flights'):
            filename='tourvisor-fuel-intake-receipt-v1-'+stage+'-'+hashlib.sha256(tour_id.encode()).hexdigest()+'.json'
            path=home/'.anytoour-andromeda/searches'/filename
            data,record,file_identity=pd_read(path,2048)
            identities[path]=file_identity
            row={'offer_ref_digest':offer,'stage':stage,'status':'missing','snapshot':None,
                 'record_sha256':None,'record_bytes':None}
            if data is not None:
                if record['mode']!=0o600:fail('diagnostic_file_mode')
                try:
                    value=json.loads(data.decode('utf-8'),object_pairs_hook=pd_object,
                        parse_constant=lambda _:fail('diagnostic_schema'))
                    pd_validate(value,tour_id,stage,read_at)
                except Exception:fail('diagnostic_schema')
                row.update(status='available' if value['expires_at']>read_at else 'expired',snapshot=value,
                    record_sha256=record['sha256'],record_bytes=record['bytes'])
            rows.append(row)
    try:
        if pf_identity(helper.lstat())!=identity:fail('diagnostic_snapshot_changed')
        for path,expected in identities.items():
            if expected is None:
                if path.exists() or path.is_symlink():fail('diagnostic_snapshot_changed')
            elif pf_identity(path.lstat())!=expected:fail('diagnostic_snapshot_changed')
    except Exception:fail('diagnostic_snapshot_changed')
    return {'schema_version':1,'source':'tourvisor-fuel-intake-diagnostic-readback-v1','status':'complete',
            'installed_helper_sha256':PD_HELPER_SHA,'read_at':read_at,'snapshots':rows,
            'observed_files':sum(row['status']!='missing' for row in rows),
            'fresh_files':sum(row['status']=='available' for row in rows),'final_price_verified':False}
'''


def _diagnostic_profile(command):
    command=policy(command)
    if command['mode']==DATE_DIAGNOSTIC_MODE:
        return dict(mode=DATE_DIAGNOSTIC_MODE,source=DATE_DIAGNOSTIC_SOURCE,
                    operation=DATE_DIAGNOSTIC_OPERATION,helper_sha256=DATE_DIAGNOSTIC_HELPER_SHA256,
                    min_time=DATE_DIAGNOSTIC_MIN_TIME)
    if command['mode']==DIAGNOSTIC_MODE:
        return dict(mode=DIAGNOSTIC_MODE,source=DIAGNOSTIC_SOURCE,operation=DIAGNOSTIC_OPERATION,
                    helper_sha256=DIAGNOSTIC_HELPER_SHA256,min_time=DIAGNOSTIC_INSTALLED_AT)
    need(command['mode']==CONTEXT_DIAGNOSTIC_MODE,'diagnostic_profile')
    return dict(mode=CONTEXT_DIAGNOSTIC_MODE,source=CONTEXT_DIAGNOSTIC_SOURCE,
                operation=CONTEXT_DIAGNOSTIC_OPERATION,helper_sha256=CONTEXT_DIAGNOSTIC_HELPER_SHA256,
                min_time=CONTEXT_DIAGNOSTIC_MIN_TIME)


def diagnostic_reader(command):
    profile=_diagnostic_profile(command)
    # Reuse only the descriptor-safe reader function; no legacy inventory/config reader runs.
    functions=[node for node in ast.parse(REMOTE_READER).body if isinstance(node,ast.FunctionDef) and node.name=='pf_read']
    need(len(functions)==1,'diagnostic_reader_registration')
    common=ast.get_source_segment(REMOTE_READER,functions[0]).replace("anchor=home/'www'",'anchor=home',1)
    reader=('import hashlib,json,os,pathlib,re,time\nimport stat as pf_stat\nPD_HELPER_SHA='+repr(profile['helper_sha256'])
            +'\nPD_MIN_TIME='+repr(profile['min_time'])+'\nPD_IDS='+repr(command['tour_ids'])
            +'\nPD_ERROR_CODES=frozenset('+repr(sorted(DIAGNOSTIC_ERROR_CODES))+')\n'+common+'\n'
            +DIAGNOSTIC_VALIDATOR+'\n'+DIAGNOSTIC_READER)
    return reader


def diagnostic_remote_program(remote,command):
    profile=_diagnostic_profile(command)
    reader=diagnostic_reader(command)
    marker='def local_read(scopes):\n';dispatch="    if mode=='local-readback':\n"
    exclusion="    if mode not in ('reconcile',";size="if not isinstance(files,dict) or len(files)<20: fail('manifest')"
    gate="    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):"
    need(remote.count(marker)==remote.count(dispatch)==remote.count(size)==remote.count(gate)==1
         and remote.count(exclusion)==2,'diagnostic_registration_drift')
    fixed="    if mode=="+repr(profile['mode'])+" and (source!="+repr(profile['source'])+" or operation!="+repr(profile['operation'])+" or payload.get('tour_ids')!="+repr(command['tour_ids'])+"):fail('diagnostic_fixed_scope')\n"
    registration="    if mode=="+repr(profile['mode'])+":\n"+"""        result['passive_fuel_diagnostics']=passive_fuel_diagnostic_readback()
        result['production_after']=fingerprints()
        result.update(status='complete',supplier_calls=0,database_reads=0,database_writes=0,
            runtime_writes=0,booking_calls=0,lead_calls=0,runtime_changed=False,
            production_unchanged=result['production_after']==before)
"""
    remote=remote.replace(marker,reader+'\n'+marker,1).replace(gate,fixed+gate,1)
    remote=remote.replace(dispatch,registration+dispatch,1)
    remote=remote.replace(exclusion,"    if mode not in ("+repr(profile['mode'])+",'reconcile',")
    hashes={'app/integrations/tourvisor-operator-fuel-passive-intake.php':profile['helper_sha256']}
    remote=remote.replace(size,"if not isinstance(files,dict) or files!="+repr(hashes)+": fail('diagnostic_manifest')",1)
    ast.parse(remote)
    return remote


def diagnostic_completed(command,result):
    profile=_diagnostic_profile(command)
    expected_rows=2 if command['mode']==DATE_DIAGNOSTIC_MODE else 4
    counters=('supplier_calls','database_reads','database_writes','runtime_writes','booking_calls','lead_calls')
    value=result.get('passive_fuel_diagnostics')
    fields={'schema_version','source','status','installed_helper_sha256','read_at','snapshots','observed_files',
            'fresh_files','final_price_verified'}
    outer={'schema_version','source_sha','mode','operation_id','status','runtime_changed',
           'production_before','production_after','production_unchanged','passive_fuel_diagnostics',*counters}
    if (set(result)!=outer or type(result.get('schema_version')) is not int or result['schema_version']!=1
        or not all(result.get(k)==command[k] for k in ('source_sha','mode','operation_id'))
        or result.get('status')!='complete' or result.get('runtime_changed') is not False
        or result.get('production_unchanged') is not True
        or not isinstance(result.get('production_before'),dict)
        or result['production_before']!=result.get('production_after')
        or any(type(result.get(k)) is not int or result[k]!=0 for k in counters)
        or not isinstance(value,dict) or set(value)!=fields
        or type(value['schema_version']) is not int or value['schema_version']!=1
        or value['source']!='tourvisor-fuel-intake-diagnostic-readback-v1' or value['status']!='complete'
        or value['installed_helper_sha256']!=profile['helper_sha256'] or value['final_price_verified'] is not False
        or type(value['read_at']) is not int or not profile['min_time']<=value['read_at']<=4102444800
        or not isinstance(value['snapshots'],list) or len(value['snapshots'])!=expected_rows):return False
    namespace=dict(hashlib=hashlib,re=re,PD_MIN_TIME=profile['min_time'],PD_ERROR_CODES=DIAGNOSTIC_ERROR_CODES)
    exec(DIAGNOSTIC_VALIDATOR,namespace)
    observed=fresh=0
    try:
        for row,(tour_id,stage) in zip(value['snapshots'],[(i,s) for i in command['tour_ids'] for s in ('tour','flights')]):
            if (not isinstance(row,dict) or set(row)!={'offer_ref_digest','stage','status','snapshot','record_sha256','record_bytes'}
                or row['offer_ref_digest']!=hashlib.sha256(('tourvisor:tour:'+tour_id).encode()).hexdigest()
                or row['stage']!=stage):return False
            if row['status']=='missing':
                if row['snapshot'] is not None or row['record_sha256'] is not None or row['record_bytes'] is not None:return False
                continue
            snapshot=namespace['pd_validate'](row['snapshot'],tour_id,stage,value['read_at'])
            if (row['status']!=('available' if snapshot['expires_at']>value['read_at'] else 'expired')
                or not isinstance(row['record_sha256'],str) or re.fullmatch('[a-f0-9]{64}',row['record_sha256']) is None
                or type(row['record_bytes']) is not int or not 0<row['record_bytes']<=2048):return False
            observed+=1;fresh+=row['status']=='available'
    except (KeyError,TypeError,ValueError):return False
    return (type(value['observed_files']) is int and value['observed_files']==observed
            and type(value['fresh_files']) is int and value['fresh_files']==fresh)
