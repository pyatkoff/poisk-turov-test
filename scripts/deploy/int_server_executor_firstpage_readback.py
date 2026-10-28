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
RIA2_MODE = 'anex-ria-apd-fpm-readback'
RIA2_OPERATION = 'int-anex-ria-apd-fpm-readback-20261028-v1'
RIA2_SOURCE = '09cedd5c0a8f34521820a7a58586bfb37ad7b553'
MODES = (MODE, FAILURE_MODE, CONTINUATION_MODE, RIA_MODE, RIA2_MODE)


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
            RIA2_MODE: (RIA2_SOURCE, RIA2_OPERATION),
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
    if mode == RIA2_MODE: return remote_with_ria2_readback(core)
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

RIA2_READER_SHA256 = 'bdd0da18abdd60447a742cd7658aebfcf520f56fe73e60b48e902dc64c8f0112'
RIA2_CHILD_SHA256 = '88a9dffd2af04062308466e43d731548e04f646d5f9afe42bbd5199b6539a481'
REMOTE_RIA2_READER = r'''RIA2_READER_SOURCE = '#!/usr/bin/env python3\n"""V2 artifact-only, bounded retained-file reader for the sealed RIA APD case.\n\nNo application imports, PHP execution, network, DB, session APIs, or file writes.\nOnly the parent/admitted server channel may supply an existing session root.\nFixed safe error classes/stages describe future reads only. They do not explain\nthe already consumed V1 generic HOLD, and this module never replays that operation.\n"""\nfrom __future__ import annotations\n\nimport argparse\nimport datetime as dt\nimport hashlib\nimport json\nimport math\nimport os\nfrom pathlib import Path\nimport re\nimport stat\nimport sys\n\nCASE = "ria-apd-20261028-000902"\nOBSERVED_AT = "2026-10-28T00:09:02Z"\nDAY = "2026-10-28"\nSTART = int(dt.datetime(2026, 10, 28, 0, 8, tzinfo=dt.timezone.utc).timestamp())\nEND = int(dt.datetime(2026, 10, 28, 0, 25, tzinfo=dt.timezone.utc).timestamp())\nMAX_FILES = 32\nMAX_ENTRIES = 4096\nMAX_FILE = 4 * 1024 * 1024\nMAX_TOTAL = 16 * 1024 * 1024\nMAX_CACHE = 1024 * 1024\nMAX_NODES = 120000\nMAX_DEPTH = 64\nNAME = re.compile(r"sess_[A-Za-z0-9,-]{16,128}\\Z")\nIDENT = re.compile(r"[1-9][0-9]{0,17}\\Z")\n\n\nclass Hold(Exception):\n    pass\n\n\nSTAGES = frozenset(("arguments", "session_directory", "session_file", "session_serialization",\n                    "target_match", "cache_root", "cache_file", "cache_shape", "readback"))\nHOLD_REASON_CLASSES = {\n    "absolute_path_required": "argument_invalid",\n    "cache_binding_mismatch": "binding_mismatch",\n    "cache_json_invalid": "serialization_shape",\n    "cache_root_invalid": "argument_invalid",\n    "cache_shape_invalid": "evidence_shape",\n    "exact_case_ambiguous": "case_ambiguous",\n    "no_exact_case_in_supplied_window": "case_missing",\n    "reparse_point_rejected": "symlink_rejected",\n    "retained_file_changed": "file_changed",\n    "retained_file_missing": "not_found",\n    "retained_file_not_regular": "file_type",\n    "retained_path_not_directory": "file_type",\n    "retained_file_empty": "file_empty",\n    "retained_file_size_limit": "file_size_limit",\n    "session_candidate_count_limit": "scope_limit",\n    "session_candidate_size_limit": "file_size_limit",\n    "session_candidate_total_size_limit": "scope_limit",\n    "session_candidate_type_rejected": "file_type",\n    "session_candidate_window_changed": "scope_limit",\n    "session_context_shape_or_limit": "evidence_shape",\n    "session_directory_entry_limit": "scope_limit",\n    "session_filename_invalid": "argument_invalid",\n    "session_object_unsupported": "serialization_unsupported",\n    "session_offer_shape_or_limit": "evidence_shape",\n    "session_root_invalid": "file_type",\n    "session_serialization_invalid": "serialization_shape",\n    "session_serialization_limit": "scope_limit",\n    "session_serializer_unsupported": "serialization_unsupported",\n    "symlink_rejected": "symlink_rejected",\n    "target_apd_evidence_missing_or_unsupported": "evidence_shape",\n    "target_binding_unconfirmed": "binding_unconfirmed",\n    "target_evidence_differs_from_original": "evidence_shape",\n    "target_reference_or_shape_unsupported": "serialization_unsupported",\n    "target_reference_unsupported": "serialization_unsupported",\n}\nEXCEPTION_REASONS = {\n    "retained_permission_denied": "permission_denied",\n    "retained_path_not_found": "not_found",\n    "retained_path_type_error": "file_type",\n    "retained_os_error": "os_error",\n    "retained_value_error": "value_error",\n    "retained_type_error": "type_error",\n    "retained_recursion_error": "recursion_error",\n    "readback_arguments_invalid": "argument_invalid",\n    "unclassified_guard_hold": "unclassified_guard",\n    "unexpected_readback_failure": "unexpected_error",\n}\nREASONS = frozenset(HOLD_REASON_CLASSES) | frozenset(EXCEPTION_REASONS)\nFAILURE_CLASSES = frozenset(HOLD_REASON_CLASSES.values()) | frozenset(EXCEPTION_REASONS.values())\n\n\nclass DiagnosticHold(Hold):\n    def __init__(self, reason: str, failure_class: str, stage: str):\n        # Never trust an arbitrary exception\'s message or caller-provided label.\n        self.reason = reason if reason in REASONS else "unclassified_guard_hold"\n        self.failure_class = failure_class if failure_class in FAILURE_CLASSES else "unclassified_guard"\n        self.stage = stage if stage in STAGES else "readback"\n        super().__init__(self.reason)\n\n\ndef failure_report(error: Exception, stage: str = "readback") -> dict:\n    if isinstance(error, DiagnosticHold):\n        return {"reason": error.reason if error.reason in REASONS else "unclassified_guard_hold",\n                "failure_class": error.failure_class if error.failure_class in FAILURE_CLASSES else "unclassified_guard",\n                "stage": error.stage if error.stage in STAGES else "readback"}\n    if isinstance(error, Hold):\n        reason = str(error) if str(error) in HOLD_REASON_CLASSES else "unclassified_guard_hold"\n        category = HOLD_REASON_CLASSES.get(reason, "unclassified_guard")\n    else:\n        category, reason = "unexpected_error", "unexpected_readback_failure"\n        for kind, fixed_reason in ((PermissionError, "retained_permission_denied"),\n                                   (FileNotFoundError, "retained_path_not_found"),\n                                   (NotADirectoryError, "retained_path_type_error"),\n                                   (IsADirectoryError, "retained_path_type_error"),\n                                   (OSError, "retained_os_error"),\n                                   (TypeError, "retained_type_error"),\n                                   (ValueError, "retained_value_error"),\n                                   (RecursionError, "retained_recursion_error")):\n            if isinstance(error, kind):\n                reason, category = fixed_reason, EXCEPTION_REASONS[fixed_reason]\n                break\n    return {"reason": reason, "failure_class": category,\n            "stage": stage if stage in STAGES else "readback"}\n\n\ndef at_stage(stage: str, operation, *args, **kwargs):\n    try:\n        return operation(*args, **kwargs)\n    except DiagnosticHold:\n        raise\n    except Exception as error:\n        raise DiagnosticHold(**failure_report(error, stage)) from None\n\n\nclass Ref:\n    """An unresolved PHP serialization alias; never dereferenced or exported."""\n\n\nclass PhpScalars:\n    """Parse bounded PHP scalar/array serialization, without object construction."""\n    def __init__(self, data: bytes):\n        self.data, self.pos, self.nodes, self.refs = data, 0, 0, 0\n\n    def take(self, length: int) -> bytes:\n        if length < 0 or self.pos + length > len(self.data):\n            raise Hold("session_serialization_invalid")\n        part = self.data[self.pos:self.pos + length]\n        self.pos += length\n        return part\n\n    def literal(self, value: bytes) -> None:\n        if self.take(len(value)) != value:\n            raise Hold("session_serialization_invalid")\n\n    def number(self, delimiter: bytes = b":", maximum: int = MAX_FILE) -> int:\n        end = self.data.find(delimiter, self.pos, self.pos + 24)\n        if end < 0:\n            raise Hold("session_serialization_invalid")\n        text = self.data[self.pos:end]\n        if not re.fullmatch(rb"(?:0|[1-9][0-9]*)", text):\n            raise Hold("session_serialization_invalid")\n        self.pos = end + len(delimiter)\n        value = int(text)\n        if value > maximum:\n            raise Hold("session_serialization_limit")\n        return value\n\n    def value(self, depth: int = 0):\n        self.nodes += 1\n        if depth > MAX_DEPTH or self.nodes > MAX_NODES:\n            raise Hold("session_serialization_limit")\n        tag = self.take(1)\n        if tag == b"N":\n            self.literal(b";")\n            return None\n        self.literal(b":")\n        if tag == b"s":\n            length = self.number(maximum=1024 * 1024)\n            self.literal(b\'"\')\n            value = self.take(length).decode("utf-8", errors="surrogateescape")\n            self.literal(b\'";\')\n            return value\n        if tag == b"a":\n            size = self.number(maximum=MAX_NODES // 2)\n            self.literal(b"{")\n            result = {}\n            for _ in range(size):\n                key = self.value(depth + 1)\n                if type(key) not in (int, str) or key in result:\n                    raise Hold("session_serialization_invalid")\n                result[key] = self.value(depth + 1)\n            self.literal(b"}")\n            return result\n        if tag in (b"R", b"r"):\n            self.number(b";", MAX_NODES)\n            self.refs += 1\n            return Ref()\n        if tag in (b"O", b"C", b"E"):\n            raise Hold("session_object_unsupported")\n        end = self.data.find(b";", self.pos, self.pos + 100)\n        if end < 0:\n            raise Hold("session_serialization_invalid")\n        text = self.data[self.pos:end]\n        self.pos = end + 1\n        if tag == b"b" and text in (b"0", b"1"):\n            return text == b"1"\n        if tag == b"i" and re.fullmatch(rb"-?(?:0|[1-9][0-9]{0,18})", text):\n            return int(text)\n        if tag == b"d" and re.fullmatch(rb"-?(?:[0-9]+(?:\\.[0-9]*)?|\\.[0-9]+)(?:[Ee][+-]?[0-9]+)?", text):\n            value = float(text)\n            if math.isfinite(value):\n                return value\n        raise Hold("session_serialization_invalid")\n\n\ndef _decode_session(data: bytes, serializer: str) -> dict:\n    parser = PhpScalars(data)\n    if serializer == "php_serialize":\n        result = parser.value()\n    elif serializer == "php":\n        result = {}\n        while parser.pos < len(data):\n            end = data.find(b"|", parser.pos, parser.pos + 130)\n            if end < 0:\n                raise Hold("session_serialization_invalid")\n            key = data[parser.pos:end]\n            if not re.fullmatch(rb"[A-Za-z_][A-Za-z0-9_]{0,127}", key):\n                raise Hold("session_serialization_invalid")\n            key = key.decode("ascii")\n            if key in result:\n                raise Hold("session_serialization_invalid")\n            parser.pos = end + 1\n            result[key] = parser.value()\n    else:\n        raise Hold("session_serializer_unsupported")\n    if parser.pos != len(data) or not isinstance(result, dict):\n        raise Hold("session_serialization_invalid")\n    return result\n\n\ndef decode_session(data: bytes, serializer: str) -> dict:\n    return at_stage("session_serialization", _decode_session, data, serializer)\n\n\ndef no_links(path: Path) -> None:\n    if not path.is_absolute():\n        raise Hold("absolute_path_required")\n    current = Path(path.anchor)\n    for part in path.parts[1:]:\n        current /= part\n        try:\n            entry = os.lstat(current)\n        except FileNotFoundError:\n            raise Hold("retained_file_missing") from None\n        if stat.S_ISLNK(entry.st_mode):\n            raise Hold("symlink_rejected")\n        if getattr(entry, "st_file_attributes", 0) & 0x400:\n            raise Hold("reparse_point_rejected")\n\n\ndef _read_file(path: Path, limit: int, *, session_window: bool = False) -> bytes:\n    no_links(path)\n    before = os.lstat(path)\n    if not stat.S_ISREG(before.st_mode):\n        raise Hold("retained_file_not_regular")\n    if before.st_size < 1:\n        raise Hold("retained_file_empty")\n    if before.st_size > limit:\n        raise Hold("retained_file_size_limit")\n    flags = os.O_RDONLY | getattr(os, "O_NOFOLLOW", 0) | getattr(os, "O_BINARY", 0)\n    fd = os.open(path, flags)\n    try:\n        opened = os.fstat(fd)\n        if session_window and not START <= opened.st_mtime <= END:\n            raise Hold("session_candidate_window_changed")\n        if (opened.st_dev, opened.st_ino, opened.st_size, opened.st_mtime_ns) != (before.st_dev, before.st_ino, before.st_size, before.st_mtime_ns):\n            raise Hold("retained_file_changed")\n        chunks, size = [], 0\n        while size < opened.st_size:\n            chunk = os.read(fd, min(65536, opened.st_size - size))\n            if not chunk:\n                break\n            chunks.append(chunk)\n            size += len(chunk)\n            if size > limit:\n                raise Hold("retained_file_size_limit")\n        after = os.fstat(fd)\n        if (after.st_size, after.st_mtime_ns, after.st_ctime_ns) != (opened.st_size, opened.st_mtime_ns, opened.st_ctime_ns) or size != opened.st_size:\n            raise Hold("retained_file_changed")\n        return b"".join(chunks)\n    finally:\n        os.close(fd)\n\n\ndef read_file(path: Path, limit: int, *, session_window: bool = False) -> bytes:\n    return at_stage("session_file" if session_window else "cache_file", _read_file,\n                    path, limit, session_window=session_window)\n\n\ndef select_session_candidates(entries: list[dict]) -> list[dict]:\n    """Pure selector: never truncate an oversized window or guess a likely file."""\n    if len(entries) > MAX_ENTRIES:\n        raise Hold("session_directory_entry_limit")\n    candidates = []\n    for entry in entries:\n        if not NAME.fullmatch(entry["name"]) or not START <= entry["mtime"] <= END:\n            continue\n        if entry["symlink"] or not entry["regular"]:\n            raise Hold("session_candidate_type_rejected")\n        if not 1 <= entry["size"] <= MAX_FILE:\n            raise Hold("session_candidate_size_limit")\n        candidates.append(entry)\n    if len(candidates) > MAX_FILES:\n        raise Hold("session_candidate_count_limit")\n    if sum(entry["size"] for entry in candidates) > MAX_TOTAL:\n        raise Hold("session_candidate_total_size_limit")\n    return sorted(candidates, key=lambda entry: entry["name"])\n\n\ndef _session_candidates(root: Path) -> list[Path]:\n    no_links(root)\n    if not stat.S_ISDIR(os.lstat(root).st_mode):\n        raise Hold("session_root_invalid")\n    entries = []\n    with os.scandir(root) as scan:\n        for entry in scan:\n            if len(entries) >= MAX_ENTRIES:\n                raise Hold("session_directory_entry_limit")\n            facts = entry.stat(follow_symlinks=False)\n            entries.append({"name": entry.name, "mtime": facts.st_mtime, "size": facts.st_size,\n                            "symlink": entry.is_symlink() or bool(getattr(facts, "st_file_attributes", 0) & 0x400),\n                            "regular": stat.S_ISREG(facts.st_mode)})\n    return [root / entry["name"] for entry in select_session_candidates(entries)]\n\n\ndef session_candidates(root: Path) -> list[Path]:\n    return at_stage("session_directory", _session_candidates, root)\n\n\ndef label(value) -> str:\n    return " ".join(value.split()).upper() if isinstance(value, str) else ""\n\n\ndef fixed_price(value) -> bool:\n    return type(value) in (int, str) and re.fullmatch(r"73690(?:\\.0{1,4})?", str(value)) is not None\n\n\ndef has_reference(value) -> bool:\n    return isinstance(value, Ref) or isinstance(value, dict) and any(has_reference(item) for item in value.values())\n\n\ndef _matching_cases(session: dict) -> list[dict]:\n    contexts = session.get("anex_offer_contexts")\n    if contexts is None:\n        context = session.get("offer_context")\n        if isinstance(context, Ref):\n            raise Hold("target_reference_unsupported")\n        contexts = {"legacy": context} if isinstance(context, dict) else {}\n    if isinstance(contexts, Ref):\n        raise Hold("target_reference_unsupported")\n    if not isinstance(contexts, dict) or len(contexts) > 4:\n        raise Hold("session_context_shape_or_limit")\n    matches = []\n    for context in contexts.values():\n        if isinstance(context, Ref):\n            raise Hold("target_reference_unsupported")\n        if not isinstance(context, dict):\n            raise Hold("session_context_shape_or_limit")\n        gateway = context.get("gateway", {})\n        if not isinstance(gateway, dict):\n            raise Hold("target_reference_or_shape_unsupported")\n        saved = gateway.get("saved_offers", {})\n        entries = saved.get("offers", {}) if isinstance(saved, dict) else None\n        if not isinstance(entries, dict) or len(entries) > 5000:\n            raise Hold("session_offer_shape_or_limit")\n        for entry in entries.values():\n            if isinstance(entry, Ref):\n                raise Hold("target_reference_unsupported")\n            offer = entry.get("offer") if isinstance(entry, dict) else None\n            if isinstance(offer, Ref):\n                raise Hold("target_reference_unsupported")\n            if not isinstance(offer, dict):\n                continue\n            hotel = offer.get("hotel")\n            if isinstance(hotel, Ref) or isinstance(hotel, dict) and isinstance(hotel.get("name"), Ref):\n                raise Hold("target_reference_unsupported")\n            if not isinstance(hotel, dict) or label(hotel.get("name")) != "RIA SUITES HOTEL":\n                continue\n            # A target name is present: ambiguous/unresolved identity must HOLD, not disappear.\n            if has_reference(offer):\n                raise Hold("target_reference_unsupported")\n            if not (offer.get("kind") == "concrete" and label(offer.get("room")) == "STANDARD ROOM"\n                    and label(offer.get("meal")) == "BB" and offer.get("checkin") == "2026-11-16"\n                    and offer.get("checkout") == "2026-11-23" and offer.get("nights") == 7\n                    and all(type(offer.get(k)) is int for k in ("nights", "adults", "children"))\n                    and offer.get("adults") == 2 and offer.get("children") == 0\n                    and (offer.get("infants") is None or type(offer.get("infants")) is int and offer.get("infants") == 0)):\n                continue\n            money = offer.get("price")\n            converted = offer.get("converted_price")\n            if not any(isinstance(m, dict) and m.get("currency") == "RUB" and fixed_price(m.get("amount")) for m in (money, converted)):\n                continue\n            tour, currency = entry.get("supplier_tour_program_id"), entry.get("supplier_currency_id")\n            if not isinstance(tour, str) or not IDENT.fullmatch(tour) or not isinstance(currency, str) or not IDENT.fullmatch(currency):\n                raise Hold("target_binding_unconfirmed")\n            digest = hashlib.sha256("\\0".join((tour, currency, "2026-11-16", "7")).encode("ascii")).hexdigest()\n            attempts = context.get("additional_prices")\n            if not isinstance(attempts, dict):\n                raise Hold("target_apd_evidence_missing_or_unsupported")\n            attempt = attempts.get(digest)\n            if has_reference(attempt):\n                raise Hold("target_reference_unsupported")\n            evidence = attempt.get("evidence") if isinstance(attempt, dict) else None\n            if not isinstance(evidence, dict) or evidence.get("observed_at") != OBSERVED_AT:\n                continue\n            if attempt.get("status") != "complete" or evidence.get("rows") not in ({}, []) or type(evidence.get("total_count")) is not int or evidence.get("total_count") != 0 or evidence.get("truncated") is not False:\n                raise Hold("target_evidence_differs_from_original")\n            matches.append({"digest": digest,\n                            "tour_digest": hashlib.sha256(tour.encode("ascii")).hexdigest(),\n                            "currency_digest": hashlib.sha256(currency.encode("ascii")).hexdigest(),\n                            "flight_type": offer.get("flight_type") if offer.get("flight_type") in ("regular", "charter", "unknown") else "unconfirmed"})\n    return matches\n\n\ndef matching_cases(session: dict) -> list[dict]:\n    return at_stage("target_match", _matching_cases, session)\n\n\ndef json_object(raw: bytes) -> dict:\n    def object_pairs(pairs):\n        result = {}\n        for key, value in pairs:\n            if key in result:\n                raise Hold("cache_json_invalid")\n            result[key] = value\n        return result\n    try:\n        result = json.loads(raw, object_pairs_hook=object_pairs, parse_constant=lambda _: (_ for _ in ()).throw(Hold("cache_json_invalid")))\n    except (ValueError, UnicodeError, RecursionError):\n        raise Hold("cache_json_invalid") from None\n    if not isinstance(result, dict):\n        raise Hold("cache_json_invalid")\n    return result\n\n\ndef _cache_target(root: Path, digest: str) -> Path:\n    no_links(root)\n    if not stat.S_ISDIR(os.lstat(root).st_mode):\n        raise Hold("retained_path_not_directory")\n    if root.name != "anytour-anex-apd-v1":\n        raise Hold("cache_root_invalid")\n    day = root / DAY\n    try:\n        facts = os.lstat(day)\n    except FileNotFoundError:\n        pass\n    else:\n        no_links(day)\n        if not stat.S_ISDIR(facts.st_mode):\n            raise Hold("retained_path_not_directory")\n    return day / (digest + ".json")\n\n\ndef _cache_report(raw: bytes, digest: str) -> dict:\n    entry = json_object(raw)\n    if type(entry.get("version")) is not int or entry.get("version") != 1 or entry.get("day") != DAY or entry.get("context_digest") != digest:\n        raise Hold("cache_binding_mismatch")\n    state = entry.get("status")\n    if state == "unknown":\n        return {"status": "unknown_reservation", "sha256": hashlib.sha256(raw).hexdigest()}\n    if state != "complete" or not isinstance(entry.get("payload"), dict):\n        raise Hold("cache_shape_invalid")\n    payload = entry["payload"]\n    rows, total = payload.get("data"), payload.get("totalCount")\n    if not isinstance(rows, list) or type(total) is not int or total < len(rows):\n        raise Hold("cache_shape_invalid")\n    return {"status": "complete", "row_count": len(rows), "total_count": total,\n            "matches_original_empty": rows == [] and total == 0,\n            "sha256": hashlib.sha256(raw).hexdigest()}\n\n\ndef inspect_cache(root: Path, digest: str) -> dict:\n    target = at_stage("cache_root", _cache_target, root, digest)\n    try:\n        at_stage("cache_file", os.lstat, target)\n    except DiagnosticHold as error:\n        if error.failure_class == "not_found":\n            return {"status": "missing_in_supplied_root"}\n        raise\n    raw = read_file(target, MAX_CACHE)\n    return at_stage("cache_shape", _cache_report, raw, digest)\n\n\ndef _session_file_facts(path: Path):\n    no_links(path)\n    facts = os.lstat(path)\n    if not stat.S_ISREG(facts.st_mode):\n        raise Hold("retained_file_not_regular")\n    if facts.st_size < 1:\n        raise Hold("retained_file_empty")\n    if facts.st_size > MAX_FILE:\n        raise Hold("retained_file_size_limit")\n    if not START <= facts.st_mtime <= END:\n        raise Hold("session_candidate_window_changed")\n    return facts\n\n\ndef _inspect(paths: list[Path], serializer: str, cache_root: Path) -> dict:\n    if len(paths) > MAX_FILES:\n        raise Hold("session_candidate_count_limit")\n    # Recheck the complete selected set before any content read. The opened-file\n    # guard below still applies if any candidate changes after this preflight.\n    selected_bytes = 0\n    for path in paths:\n        facts = at_stage("session_file", _session_file_facts, path)\n        selected_bytes += facts.st_size\n        if selected_bytes > MAX_TOTAL:\n            raise Hold("session_candidate_total_size_limit")\n    matches, read_bytes = [], 0\n    for path in paths:\n        remaining = MAX_TOTAL - read_bytes\n        if remaining < 1:\n            raise Hold("session_candidate_total_size_limit")\n        raw = read_file(path, min(MAX_FILE, remaining), session_window=True)\n        read_bytes += len(raw)\n        if read_bytes > MAX_TOTAL:\n            raise Hold("session_candidate_total_size_limit")\n        for case in matching_cases(decode_session(raw, serializer)):\n            matches.append((case, hashlib.sha256(raw).hexdigest()))\n    if not matches:\n        raise Hold("no_exact_case_in_supplied_window")\n    if len(matches) != 1:\n        raise Hold("exact_case_ambiguous")\n    case, source_digest = matches[0]\n    cache = inspect_cache(cache_root, case["digest"])\n    return {"schema_version": 1, "case": CASE, "status": "retained_case_matched",\n            "observed_at": OBSERVED_AT, "selected_files": len(paths), "session_bytes_read": read_bytes,\n            "session_sha256": source_digest, "context_digest": case["digest"],\n            "tour_id_sha256": case["tour_digest"], "currency_id_sha256": case["currency_digest"],\n            "flight_type": case["flight_type"], "original_daily_rows": 0, "cache": cache,\n            "fresh_read_vs_cache_hit": "not_retained", "upstream_empty_reason": "not_established",\n            "supplier_requests": 0, "database_operations": 0, "runtime_session_cache_writes": 0}\n\n\ndef inspect(paths: list[Path], serializer: str, cache_root: Path) -> dict:\n    return at_stage("readback", _inspect, paths, serializer, cache_root)\n\n\nclass SafeArgumentParser(argparse.ArgumentParser):\n    def error(self, message):\n        raise DiagnosticHold("readback_arguments_invalid", "argument_invalid", "arguments")\n\n\ndef main() -> int:\n    parser = SafeArgumentParser(description=__doc__)\n    group = parser.add_mutually_exclusive_group(required=True)\n    group.add_argument("--session-file", type=Path)\n    group.add_argument("--session-root", type=Path)\n    parser.add_argument("--serializer", choices=("php", "php_serialize"), required=True)\n    parser.add_argument("--cache-root", required=True, type=Path)\n    try:\n        args = parser.parse_args()\n        if args.session_file is not None:\n            if not NAME.fullmatch(args.session_file.name):\n                raise DiagnosticHold("session_filename_invalid", "argument_invalid", "arguments")\n            paths = [args.session_file]\n        else:\n            paths = session_candidates(args.session_root)\n        result = inspect(paths, args.serializer, args.cache_root)\n    except Exception as error:\n        result = {"schema_version": 1, "case": CASE, "status": "hold", **failure_report(error),\n                  "supplier_requests": 0, "database_operations": 0, "runtime_session_cache_writes": 0}\n        print(json.dumps(result, sort_keys=True, separators=(",", ":")))\n        return 2\n    print(json.dumps(result, sort_keys=True, separators=(",", ":")))\n    return 0\n\n\nif __name__ == "__main__":\n    raise SystemExit(main())\n'
RIA2_READER_SHA = 'bdd0da18abdd60447a742cd7658aebfcf520f56fe73e60b48e902dc64c8f0112'
RIA2_CHILD_TEMPLATE = "import hashlib,json,os,sys,time,signal\nSOURCE=@@CHILD_READER@@\nEXPECTED='bdd0da18abdd60447a742cd7658aebfcf520f56fe73e60b48e902dc64c8f0112'\nACTOR_VERIFIED=False\n\ndef child_hold(reason,failure_class,stage):\n    return {'status':'hold','reason':reason,'failure_class':failure_class,'stage':stage,\n        'supplier_requests':0,'database_operations':0,'runtime_session_cache_writes':0}\n\ndef child_actor():\n    import pwd\n    user=pwd.getpwnam('www-data')\n    return user.pw_name=='www-data' and user.pw_uid>0 and os.geteuid()==user.pw_uid\n\ndef child_deadline(deadline):\n    remaining=deadline-time.monotonic()\n    if remaining<=0:os._exit(124)\n    if remaining>13:raise ValueError()\n    def expired(signum,frame):os._exit(124)\n    signal.signal(signal.SIGALRM,expired)\n    signal.setitimer(signal.ITIMER_REAL,remaining)\n\ndef child_main():\n    global ACTOR_VERIFIED\n    namespace=None\n    try:\n        raw=sys.stdin.buffer.read(4097)\n        if len(raw)>4096:raise ValueError()\n        def pairs(items):\n            value={}\n            for key,item in items:\n                if key in value:raise ValueError()\n                value[key]=item\n            return value\n        request=json.loads(raw,object_pairs_hook=pairs,parse_constant=lambda value:(_ for _ in ()).throw(ValueError()))\n        if not isinstance(request,dict) or set(request)!={'session_root','cache_root','serializer','deadline_ms'}:raise ValueError()\n        if type(request['deadline_ms']) is not int or request['serializer'] not in ('php','php_serialize'):raise ValueError()\n        for key in ('session_root','cache_root'):\n            if not isinstance(request[key],str) or len(request[key])>1024 or '\\0' in request[key]:raise ValueError()\n        # Absolute monotonic deadline precedes UID resolution and all content I/O.\n        child_deadline(request['deadline_ms']/1000)\n        if not child_actor():return child_hold('child_actor_mismatch','permission_denied','principal_entry')\n        ACTOR_VERIFIED=True\n        if hashlib.sha256(SOURCE.encode()).hexdigest()!=EXPECTED:raise ValueError()\n        namespace={'__name__':'anex_ria_sudo_pinned_reader'}\n        exec(compile(SOURCE,'<pinned-ria-sudo-reader>','exec'),namespace)\n        try:\n            paths=namespace['session_candidates'](namespace['Path'](request['session_root']))\n            return namespace['inspect'](paths,request['serializer'],namespace['Path'](request['cache_root']))\n        except namespace['Hold'] as error:\n            return dict(namespace['failure_report'](error),status='hold',supplier_requests=0,\n                database_operations=0,runtime_session_cache_writes=0)\n    except Exception as error:\n        if namespace is not None:\n            return dict(namespace['failure_report'](error),status='hold',supplier_requests=0,\n                database_operations=0,runtime_session_cache_writes=0)\n        return child_hold('child_protocol_invalid','unsupported_shape','child_protocol')\n\nif __name__=='__main__':\n    result=child_main()\n    result['child_actor_verified']=ACTOR_VERIFIED\n    result['child_principal']='www-data'\n    sys.stdout.write(json.dumps(result,sort_keys=True,separators=(',',':')))\n"
RIA2_CHILD_SOURCE = RIA2_CHILD_TEMPLATE.replace('@@CHILD_READER@@',repr(RIA2_READER_SOURCE))
RIA2_CHILD_SHA = '88a9dffd2af04062308466e43d731548e04f646d5f9afe42bbd5199b6539a481'
RIA2_MODE_PIN = 'anex-ria-apd-fpm-readback'
RIA2_OPERATION_PIN = 'int-anex-ria-apd-fpm-readback-20261028-v1'

class Ria2Failure(Exception):
    def __init__(self,stage,reason,failure_class):
        self.stage,self.reason,self.failure_class=stage,reason,failure_class

def ria2_module():
    if hashlib.sha256(RIA2_READER_SOURCE.encode()).hexdigest()!=RIA2_READER_SHA:
        raise Ria2Failure('source_pin','reader_source_drift','unsupported_shape')
    namespace={'__name__':'anex_ria_fpm_fixed_reader'}
    exec(compile(RIA2_READER_SOURCE,'<pinned-ria-fpm-reader>','exec'),namespace)
    return namespace

def ria2_hold(stage,reason,failure_class):
    return {'status':'hold','reason':reason,'failure_class':failure_class,'stage':stage,
        'supplier_requests':0,'database_operations':0,'runtime_session_cache_writes':0}

def ria2_effective_access(path,directory=False,traverse_only=False):
    import os
    if os.access not in os.supports_effective_ids:
        raise Ria2Failure('actor_selection','effective_access_unsupported','unsupported_shape')
    flags=os.X_OK if traverse_only else os.R_OK|(os.X_OK if directory else 0)
    return os.access(path,flags,effective_ids=True)

def ria2_select_actor(namespace,sessions,cache):
    # Only metadata/access before this decision. No current-reader then sudo retry.
    import os,stat
    for index,root in enumerate((sessions,cache,cache/'2026-10-28')):
        try:
            entry=os.lstat(root)
            if not stat.S_ISDIR(entry.st_mode):
                raise Ria2Failure('actor_selection','candidate_directory_shape','unsupported_shape')
            if not ria2_effective_access(root,True,traverse_only=index>0):return 'default_fpm_www_data'
        except PermissionError:return 'default_fpm_www_data'
        except FileNotFoundError:
            raise Ria2Failure('actor_selection','candidate_directory_missing','not_found') from None
    try:
        paths=namespace['session_candidates'](sessions)
    except namespace['Hold'] as error:
        report=namespace['failure_report'](error)
        if report['failure_class']=='permission_denied':return 'default_fpm_www_data'
        raise Ria2Failure(report['stage'],report['reason'],report['failure_class']) from None
    for path in paths:
        if not ria2_effective_access(path):return 'default_fpm_www_data'
    return 'current'

def ria2_root_owned_path(path,python_link=False):
    import os,stat
    current=pathlib.Path(path)
    for _ in range(5):
        entry=os.lstat(current)
        if entry.st_uid!=0:raise Ria2Failure('actor_selection','system_path_untrusted','unsupported_shape')
        if stat.S_ISLNK(entry.st_mode):
            if not python_link:raise Ria2Failure('actor_selection','system_path_untrusted','unsupported_shape')
            target=os.readlink(current)
            current=pathlib.Path(target) if target.startswith('/') else current.parent/target
            continue
        if not stat.S_ISREG(entry.st_mode) or entry.st_mode&0o022:
            raise Ria2Failure('actor_selection','system_path_untrusted','unsupported_shape')
        if python_link and not re.fullmatch(r'/usr/bin/python3\.[0-9]{1,2}',str(current)):
            raise Ria2Failure('actor_selection','python_target_untrusted','unsupported_shape')
        for parent in current.parents:
            ancestor=os.lstat(parent)
            if not stat.S_ISDIR(ancestor.st_mode) or ancestor.st_uid!=0 or ancestor.st_mode&0o022:
                raise Ria2Failure('actor_selection','system_path_untrusted','unsupported_shape')
        return current
    raise Ria2Failure('actor_selection','system_path_untrusted','unsupported_shape')

def ria2_fpm_actor(namespace):
    # Read this one existing pool file only; never modify sudoers, users or policies.
    import pwd
    pool=pathlib.Path('/etc/php/8.3/fpm/pool.d/www.conf')
    ria2_root_owned_path(str(pool))
    raw=namespace['read_file'](pool,65536)
    try:text=raw.decode('utf-8')
    except UnicodeError:raise Ria2Failure('actor_selection','pool_config_unconfirmed','unsupported_shape') from None
    section=None;users=[];sections=[]
    for original in text.splitlines():
        line=original.strip()
        if not line or line.startswith((';','#')):continue
        match=re.fullmatch(r'\[([A-Za-z0-9_-]+)\]',line)
        if match:section=match[1];sections.append(section);continue
        if re.match(r'include\s*=',line,re.I):
            raise Ria2Failure('actor_selection','pool_config_unconfirmed','unsupported_shape')
        if re.match(r'user\s*=',line,re.I):
            if section!='www':raise Ria2Failure('actor_selection','pool_config_unconfirmed','unsupported_shape')
            users.append(line.split('=',1)[1].split(';',1)[0].strip())
    if sections!=['www'] or users!=['www-data']:
        raise Ria2Failure('actor_selection','pool_config_unconfirmed','unsupported_shape')
    identity=pwd.getpwnam('www-data')
    if identity.pw_name!='www-data' or identity.pw_uid<=0:
        raise Ria2Failure('actor_selection','fpm_identity_unconfirmed','permission_denied')
    ria2_root_owned_path('/usr/bin/sudo')
    ria2_root_owned_path('/usr/bin/python3',True)
    return {'actor_basis':'fixed_root_owned_default_www_pool','pool_config_sha256':hashlib.sha256(raw).hexdigest(),
        'sudo_rights_provisioned':False,'root_fallback':False}

def ria2_current_read(namespace,sessions,serializer,cache):
    try:
        paths=namespace['session_candidates'](sessions)
        return namespace['inspect'](paths,serializer,cache)
    except namespace['Hold'] as error:
        return dict(namespace['failure_report'](error),status='hold',supplier_requests=0,
            database_operations=0,runtime_session_cache_writes=0)

def ria2_child_payload(sessions,serializer,cache,deadline):
    payload=json.dumps({'session_root':str(sessions),'serializer':serializer,'cache_root':str(cache),
        'deadline_ms':int(deadline*1000)},sort_keys=True,separators=(',',':')).encode()
    if len(payload)>4096:raise Ria2Failure('principal_execution','child_protocol_invalid','unsupported_shape')
    return payload

def ria2_run_child(payload,deadline):
    import queue,threading,os
    if hashlib.sha256(RIA2_CHILD_SOURCE.encode()).hexdigest()!=RIA2_CHILD_SHA:
        raise Ria2Failure('source_pin','child_source_drift','unsupported_shape')
    argv=['/usr/bin/sudo','-n','-u','www-data','--','/usr/bin/python3','-I','-S','-B','-c',RIA2_CHILD_SOURCE]
    child=subprocess.Popen(argv,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.DEVNULL,
        cwd='/',env={'PATH':'/usr/bin:/bin','LANG':'C','LC_ALL':'C','HOME':'/nonexistent'})
    output=queue.Queue(maxsize=1)
    def consume():
        try:output.put(child.stdout.read(32769))
        except Exception:output.put(None)
    worker=threading.Thread(target=consume,daemon=True);worker.start()
    completed=False
    try:
        try:child.stdin.write(payload);child.stdin.close()
        except BrokenPipeError:pass
        try:raw=output.get(timeout=max(.1,deadline-time.monotonic()+3))
        except queue.Empty:raise Ria2Failure('principal_execution','child_timeout','timeout') from None
        try:code=child.wait(timeout=max(.1,deadline-time.monotonic()+3))
        except subprocess.TimeoutExpired:raise Ria2Failure('principal_execution','child_timeout','timeout') from None
        if code==124:raise Ria2Failure('principal_execution','child_timeout','timeout')
        if code:raise Ria2Failure('principal_execution','existing_sudo_denied_or_child_unavailable','execution_denied')
        if not isinstance(raw,bytes) or len(raw)>32768:
            raise Ria2Failure('principal_execution','child_output_invalid','unsupported_shape')
        completed=True
        return raw
    finally:
        if not completed:
            if child.poll() is None:
                child.terminate()
                try:child.wait(timeout=1)
                except subprocess.TimeoutExpired:child.kill();child.wait(timeout=2)
            # Child has its own absolute deadline before content reads. Do not
            # publish a timeout receipt while a surviving sudo child may read.
            while time.monotonic()<deadline+.25:time.sleep(.01)
        worker.join(timeout=1)
        child.stdout.close()
        if not child.stdin.closed:child.stdin.close()

def ria2_validate_child(namespace,raw):
    def pairs(items):
        value={}
        for key,item in items:
            if key in value:raise ValueError()
            value[key]=item
        return value
    try:
        data=json.loads(raw,object_pairs_hook=pairs,parse_constant=lambda value:(_ for _ in ()).throw(ValueError()))
        if not isinstance(data,dict):raise ValueError()
        allowed={'schema_version','case','status','observed_at','selected_files','session_bytes_read','session_sha256',
            'context_digest','tour_id_sha256','currency_id_sha256','flight_type','original_daily_rows','cache',
            'fresh_read_vs_cache_hit','upstream_empty_reason','supplier_requests','database_operations',
            'runtime_session_cache_writes','reason','failure_class','stage','child_actor_verified','child_principal'}
        if set(data)-allowed or type(data.get('child_actor_verified')) is not bool or data.get('child_principal')!='www-data':raise ValueError()
        for key in ('supplier_requests','database_operations','runtime_session_cache_writes'):
            if type(data.get(key)) is not int or data[key]!=0:raise ValueError()
        if data.get('status')=='hold':
            if set(data)!={'status','reason','failure_class','stage','supplier_requests','database_operations',
                'runtime_session_cache_writes','child_actor_verified','child_principal'}:raise ValueError()
            if not data['child_actor_verified'] and data.get('reason') not in ('child_actor_mismatch','child_protocol_invalid'):raise ValueError()
            if data.get('reason')=='child_actor_mismatch' and (data['child_actor_verified'] is not False or data.get('stage')!='principal_entry'):raise ValueError()
            if data.get('reason')=='child_protocol_invalid' and data.get('stage')!='child_protocol':raise ValueError()
            if data.get('reason') not in namespace['REASONS']|{'child_protocol_invalid','child_actor_mismatch'}:raise ValueError()
            if data.get('failure_class') not in namespace['FAILURE_CLASSES']|{'permission_denied','unsupported_shape'}:raise ValueError()
            if data.get('stage') not in namespace['STAGES']|{'child_protocol','principal_entry'}:raise ValueError()
            classes=dict(namespace['HOLD_REASON_CLASSES'],**namespace['EXCEPTION_REASONS'])
            classes.update(child_protocol_invalid='unsupported_shape',child_actor_mismatch='permission_denied')
            if data['failure_class']!=classes[data['reason']]:raise ValueError()
        elif data.get('status')=='retained_case_matched':
            if data['child_actor_verified'] is not True:raise ValueError()
            if type(data.get('schema_version')) is not int or data['schema_version']!=1:raise ValueError()
            if set(data)!={'schema_version','case','status','observed_at','selected_files','session_bytes_read','session_sha256',
                'context_digest','tour_id_sha256','currency_id_sha256','flight_type','original_daily_rows','cache',
                'fresh_read_vs_cache_hit','upstream_empty_reason','supplier_requests','database_operations',
                'runtime_session_cache_writes','child_actor_verified','child_principal'}:raise ValueError()
            if data.get('case')!=namespace['CASE'] or data.get('observed_at')!=namespace['OBSERVED_AT']:raise ValueError()
            for key in ('session_sha256','context_digest','tour_id_sha256','currency_id_sha256'):
                if not isinstance(data.get(key),str) or not re.fullmatch('[a-f0-9]{64}',data[key]):raise ValueError()
            if type(data.get('selected_files')) is not int or not 1<=data['selected_files']<=32:raise ValueError()
            if type(data.get('session_bytes_read')) is not int or not 1<=data['session_bytes_read']<=16777216:raise ValueError()
            if data.get('flight_type') not in ('regular','charter','unknown','unconfirmed'):raise ValueError()
            if type(data.get('original_daily_rows')) is not int or data['original_daily_rows']!=0:raise ValueError()
            if data.get('fresh_read_vs_cache_hit')!='not_retained' or data.get('upstream_empty_reason')!='not_established':raise ValueError()
            cache=data.get('cache')
            if not isinstance(cache,dict):raise ValueError()
            if cache.get('status') not in ('complete','unknown_reservation','missing_in_supplied_root'):raise ValueError()
            required={'complete':{'status','row_count','total_count','matches_original_empty','sha256'},
                'unknown_reservation':{'status','sha256'},'missing_in_supplied_root':{'status'}}[cache['status']]
            if set(cache)!=required:raise ValueError()
            if 'sha256' in cache and (not isinstance(cache['sha256'],str) or not re.fullmatch('[a-f0-9]{64}',cache['sha256'])):raise ValueError()
            for key in ('row_count','total_count'):
                if key in cache and (type(cache[key]) is not int or not 0<=cache[key]<=100000000):raise ValueError()
            if 'matches_original_empty' in cache and type(cache['matches_original_empty']) is not bool:raise ValueError()
            if cache['status']=='complete':
                if cache['total_count']<cache['row_count']:raise ValueError()
                if cache['matches_original_empty']!=(cache['row_count']==0 and cache['total_count']==0):raise ValueError()
        else:raise ValueError()
        data.pop('child_principal')
        return data
    except (ValueError,TypeError,UnicodeError,RecursionError):
        raise Ria2Failure('principal_execution','child_output_invalid','unsupported_shape') from None

def ria2_readback():
    actor='not_selected';scope={'path_scope':'candidate_discovery_incomplete','vhost_pool_authority':False,'other_roots_examined':0}
    metadata={};stage='metadata_discovery';namespace=None
    try:
        namespace=ria2_module()
        sessions,serializer,cache,scope=ria_runtime_scope()
        stage='actor_selection'
        actor=ria2_select_actor(namespace,sessions,cache)
        if actor=='current':
            stage='readback'
            receipt=ria2_current_read(namespace,sessions,serializer,cache)
        else:
            metadata=ria2_fpm_actor(namespace)
            stage='principal_execution'
            deadline=time.monotonic()+12
            payload=ria2_child_payload(sessions,serializer,cache,deadline)
            receipt=ria2_validate_child(namespace,ria2_run_child(payload,deadline))
        public_actor='configured_fpm_principal' if actor=='default_fpm_www_data' else 'current_principal'
        return dict(receipt,scope=scope,actor=public_actor,**metadata)
    except Ria2Failure as error:
        public_actor={'current':'current_principal','default_fpm_www_data':'configured_fpm_principal','not_selected':'not_selected'}[actor]
        return dict(ria2_hold(error.stage,error.reason,error.failure_class),scope=scope,actor=public_actor,**metadata)
    except RuntimeError as error:
        code=str(error)
        if code not in RIA_REASON_CODES:code='unexpected_readback_failure'
        category='timeout' if code=='metadata_timeout' else 'unsupported_shape'
        public_actor={'current':'current_principal','default_fpm_www_data':'configured_fpm_principal','not_selected':'not_selected'}[actor]
        return dict(ria2_hold(stage,code,category),scope=scope,actor=public_actor,**metadata)
    except Exception as error:
        if namespace is not None:
            report=namespace['failure_report'](error)
            public_actor={'current':'current_principal','default_fpm_www_data':'configured_fpm_principal','not_selected':'not_selected'}[actor]
            return dict(ria2_hold(stage,report['reason'],report['failure_class']),scope=scope,actor=public_actor,**metadata)
        return dict(ria2_hold(stage,'unexpected_readback_failure','unexpected_error'),scope=scope,actor='not_selected',**metadata)
'''
REMOTE_RIA2_DISPATCH = r'''    if mode=='anex-ria-apd-fpm-readback':
        if operation!='int-anex-ria-apd-fpm-readback-20261028-v1' or source!='09cedd5c0a8f34521820a7a58586bfb37ad7b553':
            fail('ria2_sealed_scope')
        result.update(supplier_calls=0,database_reads=0,database_writes=0,runtime_writes=0,session_writes=0,cache_writes=0)
        result['anex_ria_apd_fpm_readback']=ria2_readback()
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

def remote_with_ria2_readback(core):
    remote=core.REMOTE
    definition='def local_read(scopes):\n'
    dispatch="    if mode=='local-readback':\n"
    collectors="    if mode not in ('reconcile',"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collectors)==2,'ria2_registration_source_drift')
    remote=remote.replace(definition,REMOTE_RIA_READER+REMOTE_RIA2_READER+definition,1)
    remote=remote.replace(dispatch,REMOTE_RIA2_DISPATCH+dispatch,1)
    remote=remote.replace(collectors,"    if mode not in ('"+RIA2_MODE+"','reconcile',")
    ast.parse(remote)
    return remote
