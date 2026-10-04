"""Sealed, read-only inspection of captured SAMO first-page incidents.

Registers in the permanent executor; retains its actor/ref/reservation/SSH guards.
Never resumes a search, consumes a snapshot, loads supplier clients or opens DB.
The incident, source and operation are fixed; this is not a generic log reader.
"""
from __future__ import annotations

import ast

MODE = 'andromeda-firstpage-readback'
OPERATION = 'int-andromeda-firstpage-receipt-20261004-v1'
SOURCE = '598092cd292b66b7b94e4a7913a3e2d1f5b550ae'
FAILURE_MODE = 'andromeda-initial-failure-readback'
FAILURE_OPERATION = 'int-andromeda-initial-failure-20261004-v1'
FAILURE_SOURCE = 'd1ad064fbc6fda65929cc57395581b1ba819d952'


def need(value: bool, reason: str) -> None:
    if not value:
        raise ValueError(reason)


def register_parser(core) -> None:
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] not in (MODE, FAILURE_MODE):
            return original(body)
        need(len(parts) == 3, 'firstpage_command_shape')
        source, mode, operation = parts
        expected_source, expected_operation = ((SOURCE, OPERATION) if mode == MODE
                                               else (FAILURE_SOURCE, FAILURE_OPERATION))
        need(source == expected_source, 'firstpage_evidence_source')
        need(operation == expected_operation, 'firstpage_sealed_operation')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation}

    core.parse_command = parse


REMOTE_READER = r'''
import datetime as fp_dt
from zoneinfo import ZoneInfo as FPZoneInfo

FP_START=1791053925  # 2026-10-03T18:58:45Z; original UI click, never widened.
FP_END=1791053946    # 2026-10-03T18:59:06Z; successful existing page receipt.
FP_MAX_ENTRIES=20000
FP_REASONS={'stored','already_published','local_ingest_unavailable','partial_ingest_unavailable',
    'runtime_dependency_unavailable','context_invalid','country_invalid','autosave_failed',
    'not_authoritative_search','cohort_incomplete','received_page_missing','cohort_invalid',
    'cohort_rejected_rows','conflicting_offer_identity','too_many_offers',
    'no_received_owned_offers','no_current_mapped_offers','offer_contract_incomplete',
    'no_final_price_ready_resolved_offers'}
FP_FAILURES={'ANDROMEDA_ANYTOUR_COHORT_INVALID','ANDROMEDA_ANYTOUR_CHECKPOINT_INVALID',
    'ANDROMEDA_ANYTOUR_MAPPING_RECEIPT','ANDROMEDA_ANYTOUR_CANONICAL_RECEIPT',
    'ANDROMEDA_ANYTOUR_CHECKPOINT_COUNT','ANDROMEDA_ANYTOUR_CHECKPOINT_WRITE',
    'ANDROMEDA_ANYTOUR_CHECKPOINT_READBACK','ANDROMEDA_ANYTOUR_MONEY_DIGEST',
    'ANDROMEDA_ANYTOUR_CHILD_AGES'}

def fp_safe(path,limit=3*1024*1024):
    return path.resolve()==path and safe_file(path,limit)

def fp_json(path):
    if not fp_safe(path): fail('firstpage_evidence_file')
    data=json.loads(path.read_text())
    if not isinstance(data,dict): fail('firstpage_evidence_shape')
    return data

def fp_hash(path):
    return hashlib.sha256(path.read_bytes()).hexdigest() if fp_safe(path,2*1024*1024) else None

def fp_integer(value,maximum=10000000):
    return type(value) is int and 0<=value<=maximum

def fp_log_receipts():
    # Only the project's default error_log or an explicit in-project .user.ini
    # error_log. Never inspect other projects or account-wide/system logs.
    targets={project/'error_log'};configured=None
    ini=project/'.user.ini'
    if fp_safe(ini,65536):
        for line in ini.read_text(errors='replace').splitlines():
            match=re.fullmatch(r'\s*error_log\s*=\s*([^;]+?)\s*',line)
            if match:
                value=match[1].strip().strip('"\'')
                path=pathlib.Path(value)
                if not path.is_absolute(): path=project/path
                if project in path.resolve().parents and path.resolve()==path:
                    configured=path
    if configured is not None: targets.add(configured)
    receipts=[];failures=[];files_read=0
    for path in sorted(targets):
        if path.resolve()!=path or not safe_file(path,1024*1024*1024): continue
        with path.open('rb') as stream:
            stream.seek(max(0,path.stat().st_size-256*1024))
            text=stream.read(256*1024).decode('utf-8',errors='replace')
        files_read+=1
        for line in text.splitlines():
            match=re.match(r'\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}) ([A-Za-z0-9_+/:.-]+)\] (.*)',line)
            if not match: continue
            try:
                at=fp_dt.datetime.strptime(match[1],'%d-%b-%Y %H:%M:%S').replace(tzinfo=FPZoneInfo(match[2]))
            except (ValueError,KeyError): continue
            ts=int(at.timestamp())
            if not FP_START<=ts<=FP_END: continue
            body=match[3]
            receipt=re.fullmatch(r'ANDROMEDA_ANYTOUR_AUTOSAVE_RESULT published=([01]) mode=(partial_additive|complete_replace) reason=([a-z_]{1,80}) received=(\d+) owned=(\d+) ready=(\d+) confirmation=(\d+)',body)
            if receipt:
                counts=[int(receipt[i]) for i in (4,5,6,7)]
                if any(n>10000000 for n in counts): continue
                receipts.append({'at':ts,'published':receipt[1]=='1','mode':receipt[2],
                    'reason':receipt[3] if receipt[3] in FP_REASONS else 'unclassified',
                    **dict(zip(('received','owned','ready','confirmation'),counts))})
            elif body.startswith('ANDROMEDA_ANYTOUR_AUTOSAVE_FAILED '):
                code=body[len('ANDROMEDA_ANYTOUR_AUTOSAVE_FAILED '):]
                failures.append({'at':ts,'code':code if code in FP_FAILURES else 'unclassified',
                    'message_sha256':hashlib.sha256(code.encode()).hexdigest()})
            if len(receipts)+len(failures)>20: fail('firstpage_log_window_ambiguous')
    return {'files_read':files_read,'receipts':receipts,'failures':failures,
            'correlation':'time_window_only','tail_bytes_per_file':256*1024}

def fp_checkpoint(directory,ref,created,generation):
    path=directory/(ref+'-'+str(created)+'-page-1-anytour-offer-autosave-v1.json')
    if not path.exists() and not path.is_symlink(): return {'status':'missing'}
    data=fp_json(path)
    if (type(data.get('version')) is not int or data['version']!=1 or data.get('provider')!='andromeda'
            or data.get('search_ref')!=ref or data.get('generation')!=generation
            or type(data.get('received_page')) is not int or data['received_page']!=1
            or data.get('snapshot_mode')!='partial_additive'
            or not isinstance(data.get('cohort_digest'),str)
            or not re.fullmatch(r'[a-f0-9]{64}',data['cohort_digest'])
            or not fp_integer(data.get('published_at'),4102444800)
            or not fp_integer(data.get('ready_offer_count'))
            or not fp_integer(data.get('confirmation_required_offer_count'))):
        return {'status':'invalid'}
    return {'status':'published','published_at':data['published_at'],
            'ready_offers':data['ready_offer_count'],
            'confirmation_offers':data['confirmation_required_offer_count']}

def fp_directory():
    config=runtime/'.andromeda-private.php'
    if not fp_safe(config,65536): fail('firstpage_private_config')
    # Loading the existing return-only private config supplies only its catalog
    # directory. Output buffering discards unexpected output; no clients/modules
    # are loaded, no credential or config payload reaches the receipt.
    php=r"""error_reporting(0);ini_set('display_errors','0');ini_set('log_errors','0');ob_start();
try{$c=require $argv[1];$p=$c['catalog_path']??null;
if(!is_array($c)||($c['enabled']??null)!==true||!is_string($p)||$p===''||$p[0]!=='/')throw new RuntimeException();
$r=['directory'=>dirname($p)];}catch(Throwable $e){$r=['blocked'=>true];}
while(ob_get_level())ob_end_clean();echo json_encode($r);"""
    call=subprocess.run(['php','-d','allow_url_fopen=0','-r',php,str(config)],
        capture_output=True,text=True,timeout=20)
    if call.returncode or call.stderr.strip(): fail('firstpage_config_read')
    base=json.loads(call.stdout)
    if not isinstance(base,dict) or set(base)!={'directory'} or not isinstance(base['directory'],str):
        fail('firstpage_config_read')
    directory=pathlib.Path(base['directory'])/'searches'
    www=home/'www'
    if (not directory.is_absolute() or directory.resolve()!=directory
            or not directory.is_dir() or directory.is_symlink()
            or home not in directory.parents
            or (www in directory.parents and project not in directory.parents)):
        fail('firstpage_catalog_scope')
    return directory

def firstpage_readback():
    directory=fp_directory()
    app=(runtime/'app/integrations' if fp_safe(runtime/'app/integrations/andromeda-client.php')
         else runtime.parent/'app/integrations')
    ingest=project/'_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php'
    owners={'endpoint':runtime/'api-andromeda-search3-preview.php',
            'autosave':app/'andromeda-anytour-offer-autosave.php',
            'pricing_reader':app/'andromeda-saved-package-runtime.php',
            'producer':app/'anytour-offer-snapshot-producer.php','local_ingest':ingest}
    hashes={name:fp_hash(path) for name,path in owners.items()}
    partial=bool(fp_safe(ingest) and re.search(rb'function\s+mergePartialSnapshot\s*\(',ingest.read_bytes()))
    candidates=[]
    for index,path in enumerate(directory.iterdir()):
        if index>=FP_MAX_ENTRIES: fail('firstpage_inventory_bound')
        if not re.fullmatch(r'[a-f0-9]{64}-1\.json',path.name): continue
        if path.is_symlink(): fail('firstpage_evidence_symlink')
        if path.is_file() and FP_START-2<=int(path.stat().st_mtime)<=FP_END+2:
            candidates.append(path)
    if len(candidates)>1: fail('firstpage_cohort_ambiguous')
    page=None
    if candidates:
        path=candidates[0];data=fp_json(path);ref=path.name[:-7]
        store=data.get('store');snapshot=store.get('snapshot') if isinstance(store,dict) else None
        if (data.get('search_ref')!=ref or data.get('status') not in ('complete','partial')
                or not isinstance(store,dict) or not isinstance(snapshot,dict)
                or type(store.get('version')) is not int or store['version']!=1 or store.get('search_ref')!=ref
                or snapshot.get('provider')!='andromeda' or snapshot.get('search_ref')!=ref
                or type(snapshot.get('page')) is not int or snapshot['page']!=1
                or snapshot.get('selection_enabled') is not False
                or not fp_integer(data.get('generation'),2147483647) or data['generation']<1
                or store.get('generation')!=data['generation'] or snapshot.get('generation')!=data['generation']
                or not fp_integer(store.get('created_at'),4102444800) or store['created_at']<1
                or not fp_integer(store.get('expires_at'),4102444800)
                or store['expires_at']!=store['created_at']+900
                or not isinstance(snapshot.get('offers'),list) or len(snapshot['offers'])>5000
                or not isinstance(snapshot.get('rejected'),list) or len(snapshot['rejected'])>2000
                or not fp_integer(snapshot.get('pages_count'),1000)):
            fail('firstpage_retained_contract')
        classes={}
        for row in snapshot['rejected']:
            reason=row.get('reason') if isinstance(row,dict) else None
            reason=reason if isinstance(reason,str) and reason in {'MISSING_FIELD','THREE_PROVIDER_ROOM_LABEL'} else 'unclassified'
            classes[reason]=classes.get(reason,0)+1
        page={'status':data['status'],'page':1,'advertised_pages':snapshot['pages_count'],
              'normalized_offers':len(snapshot['offers']),'rejected_rows':len(snapshot['rejected']),
              'rejection_classes':classes,'created_at':store['created_at'],'expires_at':store['expires_at'],
              'expired_now':int(time.time())>=store['expires_at'],
              'checkpoint':fp_checkpoint(directory,ref,store['created_at'],data['generation'])}
    return {'incident':'samo-firstpage-20261003-185845Z','window_start':FP_START,'window_end':FP_END,
            'matched_first_pages':len(candidates),'page':page,'runtime_sha256':hashes,
            'ingest_source_declares_partial_method':partial,'logs':fp_log_receipts(),
            'supplier_calls':0,'database_reads':0,'database_writes':0,'runtime_writes':0,
            'expired_context_reused':False,'raw_payloads_exposed':False}

'''

REMOTE_DISPATCH = r'''    if mode=='andromeda-firstpage-readback':
        if operation!='int-andromeda-firstpage-receipt-20261004-v1' or source!='598092cd292b66b7b94e4a7913a3e2d1f5b550ae':
            fail('firstpage_sealed_scope')
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['runtime_writes']=0
        try:
            result['firstpage_readback']=firstpage_readback()
        except Exception as fp_error:
            # The stock exception receipt includes str(error). Never let a path,
            # invalid JSON fragment or subprocess stderr escape this diagnostic.
            fp_reason=str(fp_error)
            fail(fp_reason if re.fullmatch(r'firstpage_[a-z_]{1,80}',fp_reason)
                 else 'firstpage_readback_unclassified')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

REMOTE_FAILURE_READER = r'''
# A separate incident. The Oct3 reader/window/source/operation above stay sealed.
IF_START=1791127622  # 2026-10-04T15:27:02Z; new UI initial click.
IF_END=1791127646    # 2026-10-04T15:27:26Z; observed HTTP502 response rounded up.
IF_CODES={'ANDROMEDA_CREDENTIALS_REQUIRED','ANDROMEDA_DISABLED','ANDROMEDA_REQUEST_BUDGET',
    'ANDROMEDA_TRANSPORT_ERROR','ANDROMEDA_INVALID_RESPONSE','ANDROMEDA_HTTP_ERROR',
    'ANDROMEDA_RESPONSE_TOO_LARGE','ANDROMEDA_SUPPLIER_ERROR','ANDROMEDA_LOGIN_REQUIRED',
    'ANDROMEDA_INVALID_PARAMS','ANDROMEDA_PRICE_REPLAY_REFUSED','ANDROMEDA_INVALID_PRICE_RESPONSE',
    'ANDROMEDA_PRICE_ROW_BUDGET','ANDROMEDA_SECRET_ECHO','ANDROMEDA_UNCLASSIFIED_ERROR'}
IF_CRITERIA={'CHECKIN_BEG':'20261013','CHECKIN_END':'20261019','NIGHTS_FROM':7,'NIGHTS_TILL':7,
    'ADULT':2,'CHILD':0,'CURRENCYINC':643,'PACKETTYPE':0,'PAGE':1,'GROUP_BY':32}

def initial_failure_readback():
    directory=fp_directory()
    candidates=[]
    for index,path in enumerate(directory.iterdir()):
        if index>=FP_MAX_ENTRIES: fail('initial_failure_inventory_bound')
        if not re.fullmatch(r'[a-f0-9]{64}-1\.json',path.name): continue
        if path.is_symlink(): fail('initial_failure_evidence_symlink')
        if path.is_file() and IF_START-2<=int(path.stat().st_mtime)<=IF_END+2:
            candidates.append(path)
    if len(candidates)!=1: fail('initial_failure_evidence_missing' if not candidates
                               else 'initial_failure_evidence_ambiguous')
    path=candidates[0];ref=path.name[:-7];data=fp_json(path)
    store=data.get('store');criteria=data.get('criteria');status=data.get('status')
    if (type(data.get('version')) is not int or data['version']!=1
            or data.get('search_ref')!=ref or not fp_integer(data.get('generation'),2147483647)
            or data['generation']<1 or status not in ('pending','unavailable')
            or not isinstance(store,dict) or type(store.get('version')) is not int or store['version']!=1
            or store.get('search_ref')!=ref or type(store.get('generation')) is not int
            or store['generation']!=data['generation']
            or not fp_integer(store.get('created_at'),4102444800)
            or not IF_START<=store['created_at']<=IF_END
            or not fp_integer(store.get('expires_at'),4102444800)
            or store['expires_at']!=store['created_at']+900
            or 'snapshot' not in store or store['snapshot'] is not None
            or store.get('criteria')!=[] or store.get('raw_ids')!=[]
            or (status=='pending' and (data.get('error') is not None or 'error_code' in data))
            or (status=='unavailable' and (data.get('error')!='supplier_result_unavailable'
                                         or 'error_code' not in data))):
        fail('initial_failure_retained_contract')
    # The UI supplies canonical local route IDs; the endpoint maps them through
    # current dictionaries. Do not guess supplier IDs or expose them. Bind all
    # observed stay/party/filter fields, require valid mapped IDs and a unique
    # page in the fixed window. This does not independently prove route identity.
    if (not isinstance(criteria,dict)
            or set(criteria) not in (set(IF_CRITERIA)|{'TOWNFROMINC','STATEINC'},
                                    set(IF_CRITERIA)|{'TOWNFROMINC','STATEINC','OPERATORS'})
            or any(type(criteria.get(k)) is not type(v) or criteria[k]!=v for k,v in IF_CRITERIA.items())
            or any(not fp_integer(criteria.get(k),2147483647) or criteria[k]<1
                   for k in ('TOWNFROMINC','STATEINC'))
            or ('OPERATORS' in criteria and (not isinstance(criteria['OPERATORS'],str)
                or len(criteria['OPERATORS'])>300
                or not re.fullmatch(r'[1-9][0-9]*(?:,[1-9][0-9]*)*',criteria['OPERATORS'])))):
        fail('initial_failure_criteria_contract')
    app=(runtime/'app/integrations' if fp_safe(runtime/'app/integrations/andromeda-client.php')
         else runtime.parent/'app/integrations')
    owners={'endpoint':runtime/'api-andromeda-search3-preview.php',
            'search':app/'andromeda-search.php','client':app/'andromeda-client.php',
            'transport':app/'andromeda-transport.php','offer_store':app/'andromeda-offer-store.php'}
    code=data.get('error_code')
    code=code if isinstance(code,str) and code in IF_CODES else 'ANDROMEDA_UNCLASSIFIED_ERROR'
    return {'incident':'samo-initial-failure-20261004-152702Z',
            'window_start':IF_START,'window_end':IF_END,'matched_first_pages':1,
            'page':{'status':status,'error_code':code if status=='unavailable' else None,
                    'created_at':store['created_at'],'expires_at':store['expires_at'],
                    'expired_now':int(time.time())>=store['expires_at'],'snapshot_present':False},
            'correlation':'unique_page_window_and_known_criteria','route_identity_verified':False,
            'runtime_sha256':{name:fp_hash(path) for name,path in owners.items()},
            'supplier_calls':0,'database_reads':0,'database_writes':0,'runtime_writes':0,
            'expired_context_reused':False,'raw_payloads_exposed':False}

'''

REMOTE_FAILURE_DISPATCH = r'''    if mode=='andromeda-initial-failure-readback':
        if operation!='int-andromeda-initial-failure-20261004-v1' or source!='d1ad064fbc6fda65929cc57395581b1ba819d952':
            fail('initial_failure_sealed_scope')
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['runtime_writes']=0
        try:
            result['initial_failure_readback']=initial_failure_readback()
        except Exception as if_error:
            reason=str(if_error)
            # Shared config/file guards also emit fixed firstpage_* categories.
            if re.fullmatch(r'firstpage_[a-z_]{1,80}',reason):
                reason='initial_failure_'+reason[len('firstpage_'):]
            fail(reason if reason in {
                'initial_failure_private_config','initial_failure_config_read','initial_failure_catalog_scope',
                'initial_failure_evidence_file','initial_failure_evidence_shape','initial_failure_inventory_bound',
                'initial_failure_evidence_symlink','initial_failure_evidence_missing','initial_failure_evidence_ambiguous',
                'initial_failure_retained_contract','initial_failure_criteria_contract'}
                else 'initial_failure_readback_unclassified')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


def remote_with_readback(core, mode: str = MODE) -> str:
    need(mode in (MODE, FAILURE_MODE), 'firstpage_registration_mode')
    remote = core.REMOTE
    definition = 'def local_read(scopes):\n'
    dispatch = "    if mode=='local-readback':\n"
    collectors = "    if mode not in ('reconcile',"
    need(remote.count(definition) == 1 and remote.count(dispatch) == 1
         and remote.count(collectors) == 2, 'firstpage_registration_source_drift')
    remote = remote.replace(definition, REMOTE_READER + definition, 1)
    remote = remote.replace(dispatch, REMOTE_DISPATCH + dispatch, 1)
    modes = MODE
    if mode == FAILURE_MODE:
        remote = remote.replace(definition, REMOTE_FAILURE_READER + definition, 1)
        remote = remote.replace(dispatch, REMOTE_FAILURE_DISPATCH + dispatch, 1)
        modes += "','" + FAILURE_MODE
    remote = remote.replace(collectors, "    if mode not in ('" + modes + "','reconcile',")
    ast.parse(remote)
    return remote


def activate(core, command: dict) -> None:
    mode = command.get('mode')
    if mode not in (MODE, FAILURE_MODE):
        return
    expected = core.parse_command(core.PREFIX + ' '.join([
        str(command.get('source_sha', '')), mode, str(command.get('operation_id', '')),
    ]))
    need(command == expected, 'firstpage_authorized_shape')
    core.REMOTE = remote_with_readback(core, mode)
