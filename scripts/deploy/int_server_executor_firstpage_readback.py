"""Sealed, read-only inspection of captured SAMO pagination incidents.

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
CONTINUATION_MODE = 'andromeda-continuation-failure-readback'
CONTINUATION_OPERATION = 'int-andromeda-continuation-failure-20261004-v1'
CONTINUATION_SOURCE = '3054d34bb587ca684d5ecbc5f3c306851785d624'
RIA_MODE = 'anex-ria-apd-retained-readback'
RIA_OPERATION = 'int-anex-ria-apd-retained-20261028-v1'
RIA_SOURCE = '09cedd5c0a8f34521820a7a58586bfb37ad7b553'
MODES = (MODE, FAILURE_MODE, CONTINUATION_MODE, RIA_MODE)


def need(value: bool, reason: str) -> None:
    if not value:
        raise ValueError(reason)


def register_parser(core) -> None:
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] not in MODES:
            return original(body)
        need(len(parts) == 3, 'firstpage_command_shape')
        source, mode, operation = parts
        expected_source, expected_operation = {
            MODE: (SOURCE, OPERATION), FAILURE_MODE: (FAILURE_SOURCE, FAILURE_OPERATION),
            CONTINUATION_MODE: (CONTINUATION_SOURCE, CONTINUATION_OPERATION),
            RIA_MODE: (RIA_SOURCE, RIA_OPERATION),
        }[mode]
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
CF_START=1791136876  # 2026-10-04T18:01:16Z; ONE explicit Continue click.
CF_END=1791136898    # 2026-10-04T18:01:38Z; observed response rounded up.
CF_FIRST_START=1791136782  # 2026-10-04T17:59:42Z; fresh initial click.
CF_FIRST_END=1791136804    # 2026-10-04T18:00:04Z; actual successful page1 log.
CF_CRITERIA=IF_CRITERIA|{'CHECKIN_BEG':'20261014','CHECKIN_END':'20261020','PAGE':2}

def cf_transport_facts(data):
    # Reconstruct only the closed schema emitted by the installed typed wrapper.
    # Missing/invalid facts never become guessed timeout or retry provenance.
    if 'transport_failure' not in data: return {'status':'missing','facts':None}
    facts=data['transport_failure']
    actions={'login','townfrom','state','all','price','broninit','unknown'}
    categories={'network_transfer','endpoint_guard','action_guard','request_budget',
        'monthly_quota','curl_unavailable','response_size_guard','unclassified'}
    if (data.get('status')!='unavailable' or data.get('error_code')!='ANDROMEDA_TRANSPORT_ERROR'
            or not isinstance(facts,dict) or set(facts)!={'source','action','reason_category','curl_errno'}
            or facts.get('source')!='andromeda_transport_error'
            or not isinstance(facts.get('action'),str) or facts['action'] not in actions
            or not isinstance(facts.get('reason_category'),str) or facts['reason_category'] not in categories
            or (facts.get('curl_errno') is not None and (type(facts['curl_errno']) is not int
                or not 1<=facts['curl_errno']<=999 or facts['reason_category']!='network_transfer'))):
        return {'status':'invalid','facts':None}
    return {'status':'retained','facts':{key:facts[key] for key in
        ('source','action','reason_category','curl_errno')}}

def initial_failure_readback(continuation=False):
    directory=fp_directory()
    start,end=(CF_START,CF_END) if continuation else (IF_START,IF_END)
    expected_criteria=CF_CRITERIA if continuation else IF_CRITERIA
    pattern=r'([a-f0-9]{64})-([1-9][0-9]{0,9})-2\.json' if continuation else r'([a-f0-9]{64})-1\.json'
    candidates=[]
    for index,path in enumerate(directory.iterdir()):
        if index>=FP_MAX_ENTRIES: fail('initial_failure_inventory_bound')
        if not re.fullmatch(pattern,path.name): continue
        if path.is_symlink(): fail('initial_failure_evidence_symlink')
        if path.is_file() and start-2<=int(path.stat().st_mtime)<=end+2:
            candidates.append(path)
    if len(candidates)!=1: fail('initial_failure_evidence_missing' if not candidates
                               else 'initial_failure_evidence_ambiguous')
    path=candidates[0];matched=re.fullmatch(pattern,path.name);ref=matched[1];data=fp_json(path)
    store=data.get('store');criteria=data.get('criteria');status=data.get('status')
    if (type(data.get('version')) is not int or data['version']!=1
            or data.get('search_ref')!=ref or not fp_integer(data.get('generation'),2147483647)
            or data['generation']<1 or status not in ('pending','unavailable')
            or not isinstance(store,dict) or type(store.get('version')) is not int or store['version']!=1
            or store.get('search_ref')!=ref or type(store.get('generation')) is not int
            or store['generation']!=data['generation']
            or not fp_integer(store.get('created_at'),4102444800)
            or not start<=store['created_at']<=end
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
            or set(criteria) not in (set(expected_criteria)|{'TOWNFROMINC','STATEINC'},
                                    set(expected_criteria)|{'TOWNFROMINC','STATEINC','OPERATORS'})
            or any(type(criteria.get(k)) is not type(v) or criteria[k]!=v for k,v in expected_criteria.items())
            or any(not fp_integer(criteria.get(k),2147483647) or criteria[k]<1
                   for k in ('TOWNFROMINC','STATEINC'))
            or ('OPERATORS' in criteria and (not isinstance(criteria['OPERATORS'],str)
                or len(criteria['OPERATORS'])>300
                or not re.fullmatch(r'[1-9][0-9]*(?:,[1-9][0-9]*)*',criteria['OPERATORS'])))):
        fail('initial_failure_criteria_contract')
    if continuation:
        # The endpoint names page2 with the actual retained page1 creation time.
        # Bind both windows, ref, generation, complete criteria and exact snapshot;
        # an unrelated second-page file is not evidence for this failed Continue.
        first=fp_json(directory/(ref+'-1.json'));first_store=first.get('store')
        snapshot=first_store.get('snapshot') if isinstance(first_store,dict) else None
        if (first.get('status') not in ('complete','partial') or first.get('search_ref')!=ref
                or type(first.get('version')) is not int or first['version']!=1
                or type(first.get('generation')) is not int or first['generation']!=data['generation']
                or not isinstance(first.get('criteria'),dict) or set(first['criteria'])!=set(criteria)
                or any(type(first['criteria'][key]) is not type(value) or first['criteria'][key]!=value
                       for key,value in (criteria|{'PAGE':1}).items())
                or not isinstance(first_store,dict) or type(first_store.get('version')) is not int
                or first_store['version']!=1 or first_store.get('search_ref')!=ref
                or type(first_store.get('generation')) is not int or first_store['generation']!=data['generation']
                or type(first_store.get('created_at')) is not int or first_store['created_at']!=int(matched[2])
                or not CF_FIRST_START<=first_store['created_at']<=CF_FIRST_END
                or type(first_store.get('expires_at')) is not int
                or first_store['expires_at']!=first_store['created_at']+900
                or store['created_at']>=first_store['expires_at']
                or not isinstance(snapshot,dict) or snapshot.get('provider')!='andromeda'
                or snapshot.get('search_ref')!=ref or type(snapshot.get('generation')) is not int
                or snapshot['generation']!=data['generation'] or type(snapshot.get('page')) is not int
                or snapshot['page']!=1 or snapshot.get('selection_enabled') is not False
                or not fp_integer(snapshot.get('pages_count'),1000) or snapshot['pages_count']<2
                or not isinstance(snapshot.get('offers'),list) or len(snapshot['offers'])>5000
                or not isinstance(snapshot.get('rejected'),list) or len(snapshot['rejected'])>2000):
            fail('initial_failure_precursor_contract')
    app=(runtime/'app/integrations' if fp_safe(runtime/'app/integrations/andromeda-client.php')
         else runtime.parent/'app/integrations')
    owners={'endpoint':runtime/'api-andromeda-search3-preview.php',
            'search':app/'andromeda-search.php','client':app/'andromeda-client.php',
            'transport':app/'andromeda-transport.php','offer_store':app/'andromeda-offer-store.php'}
    code=data.get('error_code')
    code=code if isinstance(code,str) and code in IF_CODES else 'ANDROMEDA_UNCLASSIFIED_ERROR'
    result={'incident':'samo-initial-failure-20261004-152702Z',
            'window_start':start,'window_end':end,'matched_first_pages':1,
            'page':{'status':status,'error_code':code if status=='unavailable' else None,
                    'created_at':store['created_at'],'expires_at':store['expires_at'],
                    'expired_now':int(time.time())>=store['expires_at'],'snapshot_present':False},
            'correlation':'unique_page_window_and_known_criteria','route_identity_verified':False,
            'runtime_sha256':{name:fp_hash(path) for name,path in owners.items()},
            'supplier_calls':0,'database_reads':0,'database_writes':0,'runtime_writes':0,
            'expired_context_reused':False,'raw_payloads_exposed':False}
    if continuation:
        result['incident']='samo-continuation-failure-20261004-180116Z'
        del result['matched_first_pages'];result['matched_continuations']=1
        result['page']['number']=2
        result['precursor']={'page':1,'created_at':first_store['created_at'],
            'expires_at':first_store['expires_at'],'context_fresh_at_continue':True}
        result['correlation']='unique_page2_window_and_bound_page1_criteria'
        result['transport_failure']=cf_transport_facts(data)
        result['runtime_sha256']['network_marker']=fp_hash(app/'andromeda-network-transport-failure.php')
    return result

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

REMOTE_CONTINUATION_DISPATCH = r'''    if mode=='andromeda-continuation-failure-readback':
        if operation!='int-andromeda-continuation-failure-20261004-v1' or source!='3054d34bb587ca684d5ecbc5f3c306851785d624':
            fail('continuation_failure_sealed_scope')
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['runtime_writes']=0
        try:
            result['continuation_failure_readback']=initial_failure_readback(True)
        except Exception as cf_error:
            reason=str(cf_error)
            for prefix in ('firstpage_','initial_failure_'):
                if reason.startswith(prefix):
                    reason='continuation_failure_'+reason[len(prefix):]
                    break
            fail(reason if reason in {
                'continuation_failure_private_config','continuation_failure_config_read','continuation_failure_catalog_scope',
                'continuation_failure_evidence_file','continuation_failure_evidence_shape','continuation_failure_inventory_bound',
                'continuation_failure_evidence_symlink','continuation_failure_evidence_missing','continuation_failure_evidence_ambiguous',
                'continuation_failure_retained_contract','continuation_failure_criteria_contract','continuation_failure_precursor_contract'}
                else 'continuation_failure_readback_unclassified')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


def remote_with_readback(core, mode: str = MODE) -> str:
    need(mode in MODES, 'firstpage_registration_mode')
    if mode == RIA_MODE: return remote_with_ria_readback(core)
    remote = core.REMOTE
    definition = 'def local_read(scopes):\n'
    dispatch = "    if mode=='local-readback':\n"
    collectors = "    if mode not in ('reconcile',"
    need(remote.count(definition) == 1 and remote.count(dispatch) == 1
         and remote.count(collectors) == 2, 'firstpage_registration_source_drift')
    remote = remote.replace(definition, REMOTE_READER + definition, 1)
    remote = remote.replace(dispatch, REMOTE_DISPATCH + dispatch, 1)
    modes = MODE
    if mode in (FAILURE_MODE, CONTINUATION_MODE):
        remote = remote.replace(definition, REMOTE_FAILURE_READER + definition, 1)
        remote = remote.replace(dispatch, REMOTE_FAILURE_DISPATCH + dispatch, 1)
        modes += "','" + FAILURE_MODE
    if mode == CONTINUATION_MODE:
        remote = remote.replace(dispatch, REMOTE_CONTINUATION_DISPATCH + dispatch, 1)
        modes += "','" + CONTINUATION_MODE
    remote = remote.replace(collectors, "    if mode not in ('" + modes + "','reconcile',")
    ast.parse(remote)
    return remote


def activate(core, command: dict) -> None:
    mode = command.get('mode')
    if mode not in MODES:
        return
    expected = core.parse_command(core.PREFIX + ' '.join([
        str(command.get('source_sha', '')), mode, str(command.get('operation_id', '')),
    ]))
    need(command == expected, 'firstpage_authorized_shape')
    core.REMOTE = remote_with_readback(core, mode)

RIA_READER_SHA256 = 'e75beb5c112fae08592a6c2308db38ee1f2d8d5aecffb49d118f856b303e1aad'
RIA_PINNED_READER_SOURCE = r'''#!/usr/bin/env python3
"""Artifact-only, bounded retained-file reader for the sealed RIA APD case.

No application imports, PHP execution, network, DB, session APIs, or file writes.
Only the parent/admitted server channel may supply an existing session root.
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import math
import os
from pathlib import Path
import re
import stat
import sys

CASE = "ria-apd-20261028-000902"
OBSERVED_AT = "2026-10-28T00:09:02Z"
DAY = "2026-10-28"
START = int(dt.datetime(2026, 10, 28, 0, 8, tzinfo=dt.timezone.utc).timestamp())
END = int(dt.datetime(2026, 10, 28, 0, 25, tzinfo=dt.timezone.utc).timestamp())
MAX_FILES = 32
MAX_ENTRIES = 4096
MAX_FILE = 4 * 1024 * 1024
MAX_TOTAL = 16 * 1024 * 1024
MAX_CACHE = 1024 * 1024
MAX_NODES = 120000
MAX_DEPTH = 64
NAME = re.compile(r"sess_[A-Za-z0-9,-]{16,128}\Z")
IDENT = re.compile(r"[1-9][0-9]{0,17}\Z")


class Hold(Exception):
    pass


class Ref:
    """An unresolved PHP serialization alias; never dereferenced or exported."""


class PhpScalars:
    """Parse bounded PHP scalar/array serialization, without object construction."""
    def __init__(self, data: bytes):
        self.data, self.pos, self.nodes, self.refs = data, 0, 0, 0

    def take(self, length: int) -> bytes:
        if length < 0 or self.pos + length > len(self.data):
            raise Hold("session_serialization_invalid")
        part = self.data[self.pos:self.pos + length]
        self.pos += length
        return part

    def literal(self, value: bytes) -> None:
        if self.take(len(value)) != value:
            raise Hold("session_serialization_invalid")

    def number(self, delimiter: bytes = b":", maximum: int = MAX_FILE) -> int:
        end = self.data.find(delimiter, self.pos, self.pos + 24)
        if end < 0:
            raise Hold("session_serialization_invalid")
        text = self.data[self.pos:end]
        if not re.fullmatch(rb"(?:0|[1-9][0-9]*)", text):
            raise Hold("session_serialization_invalid")
        self.pos = end + len(delimiter)
        value = int(text)
        if value > maximum:
            raise Hold("session_serialization_limit")
        return value

    def value(self, depth: int = 0):
        self.nodes += 1
        if depth > MAX_DEPTH or self.nodes > MAX_NODES:
            raise Hold("session_serialization_limit")
        tag = self.take(1)
        if tag == b"N":
            self.literal(b";")
            return None
        self.literal(b":")
        if tag == b"s":
            length = self.number(maximum=1024 * 1024)
            self.literal(b'"')
            value = self.take(length).decode("utf-8", errors="surrogateescape")
            self.literal(b'";')
            return value
        if tag == b"a":
            size = self.number(maximum=MAX_NODES // 2)
            self.literal(b"{")
            result = {}
            for _ in range(size):
                key = self.value(depth + 1)
                if type(key) not in (int, str) or key in result:
                    raise Hold("session_serialization_invalid")
                result[key] = self.value(depth + 1)
            self.literal(b"}")
            return result
        if tag in (b"R", b"r"):
            self.number(b";", MAX_NODES)
            self.refs += 1
            return Ref()
        if tag in (b"O", b"C", b"E"):
            raise Hold("session_object_unsupported")
        end = self.data.find(b";", self.pos, self.pos + 100)
        if end < 0:
            raise Hold("session_serialization_invalid")
        text = self.data[self.pos:end]
        self.pos = end + 1
        if tag == b"b" and text in (b"0", b"1"):
            return text == b"1"
        if tag == b"i" and re.fullmatch(rb"-?(?:0|[1-9][0-9]{0,18})", text):
            return int(text)
        if tag == b"d" and re.fullmatch(rb"-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[Ee][+-]?[0-9]+)?", text):
            value = float(text)
            if math.isfinite(value):
                return value
        raise Hold("session_serialization_invalid")


def decode_session(data: bytes, serializer: str) -> dict:
    parser = PhpScalars(data)
    if serializer == "php_serialize":
        result = parser.value()
    elif serializer == "php":
        result = {}
        while parser.pos < len(data):
            end = data.find(b"|", parser.pos, parser.pos + 130)
            if end < 0:
                raise Hold("session_serialization_invalid")
            key = data[parser.pos:end]
            if not re.fullmatch(rb"[A-Za-z_][A-Za-z0-9_]{0,127}", key):
                raise Hold("session_serialization_invalid")
            key = key.decode("ascii")
            if key in result:
                raise Hold("session_serialization_invalid")
            parser.pos = end + 1
            result[key] = parser.value()
    else:
        raise Hold("session_serializer_unsupported")
    if parser.pos != len(data) or not isinstance(result, dict):
        raise Hold("session_serialization_invalid")
    return result


def no_links(path: Path) -> None:
    if not path.is_absolute():
        raise Hold("absolute_path_required")
    current = Path(path.anchor)
    for part in path.parts[1:]:
        current /= part
        try:
            entry = os.lstat(current)
        except FileNotFoundError:
            raise Hold("retained_file_missing") from None
        if stat.S_ISLNK(entry.st_mode):
            raise Hold("symlink_rejected")
        if getattr(entry, "st_file_attributes", 0) & 0x400:
            raise Hold("reparse_point_rejected")


def read_file(path: Path, limit: int, *, session_window: bool = False) -> bytes:
    no_links(path)
    before = os.lstat(path)
    if not stat.S_ISREG(before.st_mode) or before.st_size < 1 or before.st_size > limit:
        raise Hold("retained_file_type_or_size")
    flags = os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0) | getattr(os, "O_BINARY", 0)
    fd = os.open(path, flags)
    try:
        opened = os.fstat(fd)
        if session_window and not START <= opened.st_mtime <= END:
            raise Hold("session_candidate_window_changed")
        if (opened.st_dev, opened.st_ino, opened.st_size, opened.st_mtime_ns) != (before.st_dev, before.st_ino, before.st_size, before.st_mtime_ns):
            raise Hold("retained_file_changed")
        chunks, size = [], 0
        while size < opened.st_size:
            chunk = os.read(fd, min(65536, opened.st_size - size))
            if not chunk:
                break
            chunks.append(chunk)
            size += len(chunk)
            if size > limit:
                raise Hold("retained_file_type_or_size")
        after = os.fstat(fd)
        if (after.st_size, after.st_mtime_ns, after.st_ctime_ns) != (opened.st_size, opened.st_mtime_ns, opened.st_ctime_ns) or size != opened.st_size:
            raise Hold("retained_file_changed")
        return b"".join(chunks)
    finally:
        os.close(fd)


def select_session_candidates(entries: list[dict]) -> list[dict]:
    """Pure selector: never truncate an oversized window or guess a likely file."""
    if len(entries) > MAX_ENTRIES:
        raise Hold("session_directory_entry_limit")
    candidates = []
    for entry in entries:
        if not NAME.fullmatch(entry["name"]) or not START <= entry["mtime"] <= END:
            continue
        if entry["symlink"] or not entry["regular"]:
            raise Hold("session_candidate_type_rejected")
        if not 1 <= entry["size"] <= MAX_FILE:
            raise Hold("session_candidate_size_limit")
        candidates.append(entry)
    if len(candidates) > MAX_FILES:
        raise Hold("session_candidate_count_limit")
    if sum(entry["size"] for entry in candidates) > MAX_TOTAL:
        raise Hold("session_candidate_total_size_limit")
    return sorted(candidates, key=lambda entry: entry["name"])


def session_candidates(root: Path) -> list[Path]:
    no_links(root)
    if not root.is_dir():
        raise Hold("session_root_invalid")
    entries = []
    with os.scandir(root) as scan:
        for entry in scan:
            if len(entries) >= MAX_ENTRIES:
                raise Hold("session_directory_entry_limit")
            facts = entry.stat(follow_symlinks=False)
            entries.append({"name": entry.name, "mtime": facts.st_mtime, "size": facts.st_size,
                            "symlink": entry.is_symlink() or bool(getattr(facts, "st_file_attributes", 0) & 0x400),
                            "regular": stat.S_ISREG(facts.st_mode)})
    return [root / entry["name"] for entry in select_session_candidates(entries)]


def label(value) -> str:
    return " ".join(value.split()).upper() if isinstance(value, str) else ""


def fixed_price(value) -> bool:
    return type(value) in (int, str) and re.fullmatch(r"73690(?:\.0{1,4})?", str(value)) is not None


def has_reference(value) -> bool:
    return isinstance(value, Ref) or isinstance(value, dict) and any(has_reference(item) for item in value.values())


def matching_cases(session: dict) -> list[dict]:
    contexts = session.get("anex_offer_contexts")
    if contexts is None:
        context = session.get("offer_context")
        if isinstance(context, Ref):
            raise Hold("target_reference_unsupported")
        contexts = {"legacy": context} if isinstance(context, dict) else {}
    if isinstance(contexts, Ref):
        raise Hold("target_reference_unsupported")
    if not isinstance(contexts, dict) or len(contexts) > 4:
        raise Hold("session_context_shape_or_limit")
    matches = []
    for context in contexts.values():
        if isinstance(context, Ref):
            raise Hold("target_reference_unsupported")
        if not isinstance(context, dict):
            raise Hold("session_context_shape_or_limit")
        gateway = context.get("gateway", {})
        if not isinstance(gateway, dict):
            raise Hold("target_reference_or_shape_unsupported")
        saved = gateway.get("saved_offers", {})
        entries = saved.get("offers", {}) if isinstance(saved, dict) else None
        if not isinstance(entries, dict) or len(entries) > 5000:
            raise Hold("session_offer_shape_or_limit")
        for entry in entries.values():
            if isinstance(entry, Ref):
                raise Hold("target_reference_unsupported")
            offer = entry.get("offer") if isinstance(entry, dict) else None
            if isinstance(offer, Ref):
                raise Hold("target_reference_unsupported")
            if not isinstance(offer, dict):
                continue
            hotel = offer.get("hotel")
            if isinstance(hotel, Ref) or isinstance(hotel, dict) and isinstance(hotel.get("name"), Ref):
                raise Hold("target_reference_unsupported")
            if not isinstance(hotel, dict) or label(hotel.get("name")) != "RIA SUITES HOTEL":
                continue
            # A target name is present: ambiguous/unresolved identity must HOLD, not disappear.
            if has_reference(offer):
                raise Hold("target_reference_unsupported")
            if not (offer.get("kind") == "concrete" and label(offer.get("room")) == "STANDARD ROOM"
                    and label(offer.get("meal")) == "BB" and offer.get("checkin") == "2026-11-16"
                    and offer.get("checkout") == "2026-11-23" and offer.get("nights") == 7
                    and all(type(offer.get(k)) is int for k in ("nights", "adults", "children"))
                    and offer.get("adults") == 2 and offer.get("children") == 0
                    and (offer.get("infants") is None or type(offer.get("infants")) is int and offer.get("infants") == 0)):
                continue
            money = offer.get("price")
            converted = offer.get("converted_price")
            if not any(isinstance(m, dict) and m.get("currency") == "RUB" and fixed_price(m.get("amount")) for m in (money, converted)):
                continue
            tour, currency = entry.get("supplier_tour_program_id"), entry.get("supplier_currency_id")
            if not isinstance(tour, str) or not IDENT.fullmatch(tour) or not isinstance(currency, str) or not IDENT.fullmatch(currency):
                raise Hold("target_binding_unconfirmed")
            digest = hashlib.sha256("\0".join((tour, currency, "2026-11-16", "7")).encode("ascii")).hexdigest()
            attempts = context.get("additional_prices")
            if not isinstance(attempts, dict):
                raise Hold("target_apd_evidence_missing_or_unsupported")
            attempt = attempts.get(digest)
            if has_reference(attempt):
                raise Hold("target_reference_unsupported")
            evidence = attempt.get("evidence") if isinstance(attempt, dict) else None
            if not isinstance(evidence, dict) or evidence.get("observed_at") != OBSERVED_AT:
                continue
            if attempt.get("status") != "complete" or evidence.get("rows") not in ({}, []) or type(evidence.get("total_count")) is not int or evidence.get("total_count") != 0 or evidence.get("truncated") is not False:
                raise Hold("target_evidence_differs_from_original")
            matches.append({"digest": digest,
                            "tour_digest": hashlib.sha256(tour.encode("ascii")).hexdigest(),
                            "currency_digest": hashlib.sha256(currency.encode("ascii")).hexdigest(),
                            "flight_type": offer.get("flight_type") if offer.get("flight_type") in ("regular", "charter", "unknown") else "unconfirmed"})
    return matches


def json_object(raw: bytes) -> dict:
    def object_pairs(pairs):
        result = {}
        for key, value in pairs:
            if key in result:
                raise Hold("cache_json_invalid")
            result[key] = value
        return result
    try:
        result = json.loads(raw, object_pairs_hook=object_pairs, parse_constant=lambda _: (_ for _ in ()).throw(Hold("cache_json_invalid")))
    except (ValueError, UnicodeError, RecursionError):
        raise Hold("cache_json_invalid") from None
    if not isinstance(result, dict):
        raise Hold("cache_json_invalid")
    return result


def inspect_cache(root: Path, digest: str) -> dict:
    no_links(root)
    if not root.is_dir() or root.name != "anytour-anex-apd-v1":
        raise Hold("cache_root_invalid")
    if os.path.lexists(root / DAY):
        no_links(root / DAY)
    target = root / DAY / (digest + ".json")
    if not os.path.lexists(target):
        return {"status": "missing_in_supplied_root"}
    raw = read_file(target, MAX_CACHE)
    entry = json_object(raw)
    if type(entry.get("version")) is not int or entry.get("version") != 1 or entry.get("day") != DAY or entry.get("context_digest") != digest:
        raise Hold("cache_binding_mismatch")
    state = entry.get("status")
    if state == "unknown":
        return {"status": "unknown_reservation", "sha256": hashlib.sha256(raw).hexdigest()}
    if state != "complete" or not isinstance(entry.get("payload"), dict):
        raise Hold("cache_shape_invalid")
    payload = entry["payload"]
    rows, total = payload.get("data"), payload.get("totalCount")
    if not isinstance(rows, list) or type(total) is not int or total < len(rows):
        raise Hold("cache_shape_invalid")
    return {"status": "complete", "row_count": len(rows), "total_count": total,
            "matches_original_empty": rows == [] and total == 0,
            "sha256": hashlib.sha256(raw).hexdigest()}


def inspect(paths: list[Path], serializer: str, cache_root: Path) -> dict:
    if len(paths) > MAX_FILES:
        raise Hold("session_candidate_count_limit")
    # Recheck the complete selected set before any content read. The opened-file
    # guard below still applies if any candidate changes after this preflight.
    selected_bytes = 0
    for path in paths:
        no_links(path)
        facts = os.lstat(path)
        if not stat.S_ISREG(facts.st_mode) or not 1 <= facts.st_size <= MAX_FILE:
            raise Hold("session_candidate_type_or_size")
        if not START <= facts.st_mtime <= END:
            raise Hold("session_candidate_window_changed")
        selected_bytes += facts.st_size
        if selected_bytes > MAX_TOTAL:
            raise Hold("session_candidate_total_size_limit")
    matches, read_bytes = [], 0
    for path in paths:
        remaining = MAX_TOTAL - read_bytes
        if remaining < 1:
            raise Hold("session_candidate_total_size_limit")
        raw = read_file(path, min(MAX_FILE, remaining), session_window=True)
        read_bytes += len(raw)
        if read_bytes > MAX_TOTAL:
            raise Hold("session_candidate_total_size_limit")
        for case in matching_cases(decode_session(raw, serializer)):
            matches.append((case, hashlib.sha256(raw).hexdigest()))
    if not matches:
        raise Hold("no_exact_case_in_supplied_window")
    if len(matches) != 1:
        raise Hold("exact_case_ambiguous")
    case, source_digest = matches[0]
    cache = inspect_cache(cache_root, case["digest"])
    return {"schema_version": 1, "case": CASE, "status": "retained_case_matched",
            "observed_at": OBSERVED_AT, "selected_files": len(paths), "session_bytes_read": read_bytes,
            "session_sha256": source_digest, "context_digest": case["digest"],
            "tour_id_sha256": case["tour_digest"], "currency_id_sha256": case["currency_digest"],
            "flight_type": case["flight_type"], "original_daily_rows": 0, "cache": cache,
            "fresh_read_vs_cache_hit": "not_retained", "upstream_empty_reason": "not_established",
            "supplier_requests": 0, "database_operations": 0, "runtime_session_cache_writes": 0}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--session-file", type=Path)
    group.add_argument("--session-root", type=Path)
    parser.add_argument("--serializer", choices=("php", "php_serialize"), required=True)
    parser.add_argument("--cache-root", required=True, type=Path)
    args = parser.parse_args()
    try:
        if args.session_file is not None:
            if not NAME.fullmatch(args.session_file.name):
                raise Hold("session_filename_invalid")
            paths = [args.session_file]
        else:
            paths = session_candidates(args.session_root)
        result = inspect(paths, args.serializer, args.cache_root)
    except Hold as error:
        result = {"schema_version": 1, "case": CASE, "status": "hold", "reason": str(error),
                  "supplier_requests": 0, "database_operations": 0, "runtime_session_cache_writes": 0}
        print(json.dumps(result, sort_keys=True, separators=(",", ":")))
        return 2
    except (OSError, RecursionError):
        print(json.dumps({"schema_version": 1, "case": CASE, "status": "hold", "reason": "retained_file_access_or_shape"}, sort_keys=True, separators=(",", ":")))
        return 2
    print(json.dumps(result, sort_keys=True, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
'''
REMOTE_RIA_READER_TEMPLATE = r'''
RIA_PINNED_READER = @@READER@@
RIA_READER_HASH = 'e75beb5c112fae08592a6c2308db38ee1f2d8d5aecffb49d118f856b303e1aad'
RIA_SOURCE_PIN = '09cedd5c0a8f34521820a7a58586bfb37ad7b553'
RIA_OPERATION_PIN = 'int-anex-ria-apd-retained-20261028-v1'
RIA_REASON_CODES = ['absolute_path_required', 'cache_binding_mismatch', 'cache_json_invalid', 'cache_root_invalid', 'cache_shape_invalid', 'candidate_path_rejected', 'exact_case_ambiguous', 'metadata_binary_unavailable', 'metadata_format', 'metadata_output_limit', 'metadata_timeout', 'metadata_unavailable', 'no_case_in_candidate_root', 'no_exact_case_in_supplied_window', 'reader_source_drift', 'reparse_point_rejected', 'retained_file_access_or_shape', 'retained_file_changed', 'retained_file_missing', 'retained_file_type_or_size', 'retained_reader_unclassified', 'session_candidate_count_limit', 'session_candidate_size_limit', 'session_candidate_total_size_limit', 'session_candidate_type_or_size', 'session_candidate_type_rejected', 'session_candidate_window_changed', 'session_context_shape_or_limit', 'session_directory_entry_limit', 'session_filename_invalid', 'session_object_unsupported', 'session_offer_shape_or_limit', 'session_root_invalid', 'session_save_path_sharded_or_invalid', 'session_serialization_invalid', 'session_serialization_limit', 'session_serializer_unsupported', 'symlink_rejected', 'target_apd_evidence_missing_or_unsupported', 'target_binding_unconfirmed', 'target_evidence_differs_from_original', 'target_reference_or_shape_unsupported', 'target_reference_unsupported']

def ria_capture(argv, maximum, timeout=10):
    # Fixed trusted system executables only. No application bootstrap or caller argv.
    import queue as ria_queue, threading as ria_threads
    started = time.monotonic()
    child = subprocess.Popen(argv, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
        stderr=subprocess.DEVNULL, env={'HOME':str(home),'PATH':'/usr/sbin:/usr/bin:/bin','LANG':'C','LC_ALL':'C'})
    output = ria_queue.Queue(maxsize=1)
    def consume():
        try: output.put(child.stdout.read(maximum + 1))
        except Exception: output.put(None)
    worker = ria_threads.Thread(target=consume, daemon=True)
    worker.start()
    try:
        try: raw = output.get(timeout=timeout)
        except ria_queue.Empty: raise RuntimeError('metadata_timeout') from None
        if not isinstance(raw, bytes): raise RuntimeError('metadata_unavailable')
        if len(raw) > maximum: raise RuntimeError('metadata_output_limit')
        try: code = child.wait(timeout=max(.01,timeout-(time.monotonic()-started)))
        except subprocess.TimeoutExpired: raise RuntimeError('metadata_timeout') from None
        if code: raise RuntimeError('metadata_unavailable')
        try: return raw.decode('utf-8')
        except UnicodeError: raise RuntimeError('metadata_format') from None
    finally:
        if child.poll() is None:
            child.kill()
            child.wait(timeout=2)
        worker.join(timeout=1)
        child.stdout.close()

def ria_system_binary(path):
    import stat as ria_stat
    p = pathlib.Path(path)
    if not p.is_absolute() or p.is_symlink(): raise RuntimeError('metadata_binary_unavailable')
    st = p.lstat()
    if not ria_stat.S_ISREG(st.st_mode) or st.st_uid != 0 or st.st_mode & 0o022:
        raise RuntimeError('metadata_binary_unavailable')
    return str(p)

def ria_fpm_values(text):
    selected = {}
    wanted = {'session.save_path','session.serialize_handler','sys_temp_dir'}
    for line in text.splitlines():
        parts = [piece.strip() for piece in line.split('=>')]
        if not parts or parts[0] not in wanted: continue
        if len(parts) != 3 or parts[0] in selected or parts[1] != parts[2]:
            raise RuntimeError('metadata_format')
        selected[parts[0]] = parts[1]
    if set(selected) != wanted: raise RuntimeError('metadata_format')
    return selected

def ria_candidate_path(value, category):
    # Explicit single-root whitelist. This is a candidate, never proof of vhost ini.
    if not isinstance(value,str) or not value.startswith('/') or len(value)>1024 or re.search(r'[\\\x00-\x20\x7f]',value):
        raise RuntimeError('candidate_path_rejected')
    pure = pathlib.PurePosixPath(value)
    if any(part in ('.','..') for part in value.split('/')): raise RuntimeError('candidate_path_rejected')
    p = pathlib.Path(value)
    account = pathlib.Path(home)
    own = p != account and account in p.parents
    standard = value in ('/tmp','/var/tmp') or category=='session' and value=='/var/lib/php/sessions'
    if not (own or standard): raise RuntimeError('candidate_path_rejected')
    return p

def ria_runtime_scope():
    fpm = ria_system_binary('/usr/sbin/php-fpm8.3')
    values = ria_fpm_values(ria_capture([fpm,'-i'],1024*1024))
    serializer = values['session.serialize_handler']
    if serializer not in ('php','php_serialize'): raise RuntimeError('session_serializer_unsupported')
    saved = values['session.save_path'].split(';')
    if len(saved)==1: session = saved[0]
    elif len(saved) in (2,3) and saved[0]=='0' and (len(saved)==2 or re.fullmatch(r'[0-7]{3,4}',saved[1])): session=saved[-1]
    else: raise RuntimeError('session_save_path_sharded_or_invalid')
    sessions = ria_candidate_path(session,'session')
    temporary = values['sys_temp_dir']
    temp_basis = 'fpm_default_configured_candidate'
    if temporary in ('no value',''):
        php = ria_system_binary('/usr/bin/php8.3')
        temporary = ria_capture([php,'-n','-d','allow_url_fopen=0','-d','auto_prepend_file=',
            '-d','auto_append_file=','-d','opcache.enable_cli=0','-r','echo sys_get_temp_dir();'],4096)
        temp_basis = 'php_system_default_candidate'
    cache = ria_candidate_path(temporary,'temp')/'anytour-anex-apd-v1'
    public = {'path_scope':'single_fpm_default_candidate_only','vhost_pool_authority':False,
        'session_root_sha256':hashlib.sha256(str(sessions).encode()).hexdigest(),
        'cache_root_sha256':hashlib.sha256(str(cache).encode()).hexdigest(),
        'serializer':serializer,'cache_path_basis':temp_basis,'other_roots_examined':0}
    return sessions,serializer,cache,public

def ria_readback():
    scope = {'path_scope':'candidate_discovery_incomplete','vhost_pool_authority':False,'other_roots_examined':0}
    try:
        if hashlib.sha256(RIA_PINNED_READER.encode()).hexdigest()!=RIA_READER_HASH:
            raise RuntimeError('reader_source_drift')
        sessions,serializer,cache,scope = ria_runtime_scope()
        namespace = {'__name__':'anex_ria_fixed_reader'}
        exec(compile(RIA_PINNED_READER,'<pinned-ria-reader>','exec'),namespace)
        try:
            paths = namespace['session_candidates'](sessions)
            receipt = namespace['inspect'](paths,serializer,cache)
        except namespace['Hold'] as error:
            code = str(error)
            if code=='no_exact_case_in_supplied_window': code='no_case_in_candidate_root'
            if code not in RIA_REASON_CODES: code='retained_reader_unclassified'
            return {'status':'hold','reason':code,'scope':scope,'supplier_requests':0,
                'database_operations':0,'runtime_session_cache_writes':0}
        return dict(receipt,scope=scope)
    except (OSError,RecursionError,RuntimeError,ValueError) as error:
        code = str(error) if type(error) is RuntimeError else 'retained_file_access_or_shape'
        if code not in RIA_REASON_CODES: code='retained_reader_unclassified'
        return {'status':'hold','reason':code,'scope':scope,'supplier_requests':0,
            'database_operations':0,'runtime_session_cache_writes':0}

'''
REMOTE_RIA_READER = REMOTE_RIA_READER_TEMPLATE.replace("@@READER@@",repr(RIA_PINNED_READER_SOURCE))
REMOTE_RIA_DISPATCH = r'''    if mode=='anex-ria-apd-retained-readback':
        if operation!='int-anex-ria-apd-retained-20261028-v1' or source!='09cedd5c0a8f34521820a7a58586bfb37ad7b553':
            fail('ria_sealed_scope')
        result.update(supplier_calls=0,database_reads=0,database_writes=0,runtime_writes=0,
            session_writes=0,cache_writes=0)
        result['anex_ria_apd_readback']=ria_readback()
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

def remote_with_ria_readback(core):
    remote=core.REMOTE
    definition='def local_read(scopes):\n'
    dispatch="    if mode=='local-readback':\n"
    collectors="    if mode not in ('reconcile',"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collectors)==2,'ria_registration_source_drift')
    remote=remote.replace(definition,REMOTE_RIA_READER+definition,1)
    remote=remote.replace(dispatch,REMOTE_RIA_DISPATCH+dispatch,1)
    remote=remote.replace(collectors,"    if mode not in ('"+RIA_MODE+"','reconcile',")
    ast.parse(remote)
    return remote
