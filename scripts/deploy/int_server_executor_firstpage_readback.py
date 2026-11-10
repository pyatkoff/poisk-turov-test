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
RIA3_MODE = 'anex-ria-apd-access-v3'
RIA3_OPERATION = 'int-anex-ria-apd-access-20261028-v3'
RIA3_SOURCE = '09cedd5c0a8f34521820a7a58586bfb37ad7b553'
V4_MODE = 'anex-v4-owned-session-readback'
V4_OPERATION = 'int-anex-v4-owned-session-20261101-v1'
V4_SOURCE = '6dcfb5053793caba4f353facfa7a899a30df2d17'
V4_MAP_MODE = 'anex-v4-vhost-session-readback'
V4_MAP_OPERATION = 'int-anex-v4-vhost-session-20261101-v1'
V4_CGI_MODE = 'anex-v4-cgi-retained-readback'
V4_CGI_OPERATION = 'int-anex-v4-cgi-retained-20261110-v1'
V4_CGI_RESTORE_MODE = 'anex-v4-cgi-restore-only'
V4_CGI_RESTORE_OPERATION = 'int-anex-v4-cgi-restore-20261110-v1'
V4_CGI_CONT_MODE = 'anex-v4-cgi-retained-continuation'
V4_CGI_CONT_OPERATION = 'int-anex-v4-cgi-retained-continuation-20261110-v1'
V4_CGI_LOG_MODE = 'anex-v4-cgi-errorlog-incident'
V4_CGI_LOG_OPERATION = 'int-anex-v4-cgi-errorlog-20261110-v1'
MODES = (MODE, FAILURE_MODE, CONTINUATION_MODE, RIA_MODE, RIA2_MODE, RIA3_MODE, V4_MODE, V4_MAP_MODE,
         V4_CGI_MODE, V4_CGI_RESTORE_MODE, V4_CGI_CONT_MODE, V4_CGI_LOG_MODE)


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
            RIA3_MODE: (RIA3_SOURCE, RIA3_OPERATION),
            V4_MODE: (V4_SOURCE, V4_OPERATION),
            V4_MAP_MODE: (V4_SOURCE, V4_MAP_OPERATION),
            V4_CGI_MODE: (V4_SOURCE, V4_CGI_OPERATION),
            V4_CGI_RESTORE_MODE: (V4_SOURCE, V4_CGI_RESTORE_OPERATION),
            V4_CGI_CONT_MODE: (V4_SOURCE, V4_CGI_CONT_OPERATION),
            V4_CGI_LOG_MODE: (V4_SOURCE, V4_CGI_LOG_OPERATION),
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
    if mode == V4_CGI_LOG_MODE: return remote_with_v4_cgi_errorlog(core)
    if mode == V4_CGI_CONT_MODE: return remote_with_v4_cgi_continuation(core)
    if mode in (V4_CGI_MODE, V4_CGI_RESTORE_MODE): return remote_with_v4_cgi_readback(core, mode)
    if mode == V4_MAP_MODE: return remote_with_v4_map_readback(core)
    if mode == V4_MODE: return remote_with_v4_readback(core)
    need(mode in MODES, 'firstpage_registration_mode')
    if mode == RIA3_MODE: return remote_with_ria3_readback(core)
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

REMOTE_RIA3_READER = r'''RIA3_EXEC_FACTS={}

def ria3_failure(stderr,returncode):
    # LC_ALL=C; closed whole-line patterns only. Never return text or its hash.
    if not isinstance(stderr,bytes) or len(stderr)>8192:
        return 'principal_stderr_limit','stream_limit'
    text=stderr.decode('utf-8','replace').strip()
    lines=text.splitlines();first=lines[0] if lines else ''
    python=re.search(r'(?m)^Traceback \(most recent call last\):$',text) or re.search(r'(?m)^  File "<(?:string|pinned-ria-sudo-reader|pinned-ria-fpm-reader)>", line [0-9]+(?:, in [A-Za-z0-9_]+)?$',text)
    if returncode!=0 and python:
        last=lines[-1] if lines else ''
        match=re.fullmatch(r'(NameError|SyntaxError|IndentationError|TabError|ImportError|ModuleNotFoundError|TypeError|ValueError|RuntimeError|PermissionError|FileNotFoundError|OSError|TimeoutError|MemoryError|RecursionError|AttributeError|KeyError|IndexError|UnicodeDecodeError)(?::.*)?',last)
        if match:return 'child_python_'+match[1].lower(),'child_python_error'
        return 'principal_failure_unclassified','unclassified'
    categories=set()
    if not python:
        for line in lines:
            if returncode==1:
                if line=='sudo: a password is required':categories.add(('sudo_password_required','password_required'))
                if line in ('sudo: sorry, you must have a tty to run sudo',
                    'sudo: a terminal is required to read the password; either use the -S option to read from standard input or configure an askpass helper'):
                    categories.add(('sudo_tty_required','tty_required'))
                if line in ('sudo: unknown user www-data','sudo: unknown user: www-data'):
                    categories.add(('sudo_principal_unknown','principal_unknown'))
                if re.fullmatch(r'(?:sudo: )?[^\n]{1,256} is not in the sudoers file\.(?:  This incident will be reported\.)?',line) or re.fullmatch(r'Sorry, user [^\n]{1,256} is not allowed to execute [^\n]{1,4096} as www-data on [^\n]{1,256}\.',line) or re.fullmatch(r'sudo: [^\n]{1,256} may not run sudo on [^\n]{1,256}\.',line):
                    categories.add(('sudo_policy_denied','policy_denied'))
            if returncode in (1,126,127) and line in ('sudo: /usr/bin/python3: command not found',
                'sudo: unable to execute /usr/bin/python3: No such file or directory',
                'sudo: unable to execute /usr/bin/python3: Permission denied',
                'sudo: unable to execute /usr/bin/python3: Exec format error'):
                categories.add(('principal_exec_unavailable','exec_unavailable'))
    if len(categories)==1:return next(iter(categories))
    if len(categories)>1:return 'principal_failure_unclassified','unclassified'
    if returncode==124:return 'child_timeout','timeout'
    return 'principal_failure_unclassified','unclassified'

def ria3_run_child(payload,deadline):
    import queue,threading
    global RIA3_EXEC_FACTS
    if hashlib.sha256(RIA2_CHILD_SOURCE.encode()).hexdigest()!=RIA2_CHILD_SHA:
        raise Ria2Failure('source_pin','child_source_drift','unsupported_shape')
    argv=['/usr/bin/sudo','-n','-u','www-data','--','/usr/bin/python3','-I','-S','-B','-c',RIA2_CHILD_SOURCE]
    try:
        child=subprocess.Popen(argv,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,
            cwd='/',env={'PATH':'/usr/bin:/bin','LANG':'C','LC_ALL':'C','HOME':'/nonexistent'})
    except OSError:
        raise Ria2Failure('principal_execution','principal_exec_unavailable','exec_unavailable') from None
    output=queue.Queue(maxsize=2);streams={};workers=[];complete=False
    limits={'stdout':32768,'stderr':8192}
    def consume(name,pipe):
        try:
            raw=pipe.read(limits[name]+1)
            if isinstance(raw,bytes):RIA3_EXEC_FACTS[name+'_bytes_observed']=len(raw)
            output.put((name,raw))
        except Exception:output.put((name,None))
    for name in ('stdout','stderr'):
        worker=threading.Thread(target=consume,args=(name,getattr(child,name)),daemon=True)
        workers.append(worker);worker.start()
    try:
        try:child.stdin.write(payload);child.stdin.close()
        except BrokenPipeError:pass
        while len(streams)<2:
            try:name,raw=output.get(timeout=max(.1,deadline-time.monotonic()+3))
            except queue.Empty:raise Ria2Failure('principal_execution','child_timeout','timeout') from None
            if not isinstance(raw,bytes):raise Ria2Failure('principal_execution','principal_stream_unavailable','unclassified')
            streams[name]=raw
            if len(raw)>limits[name]:
                raise Ria2Failure('principal_execution','principal_'+name+'_limit','stream_limit')
        try:code=child.wait(timeout=max(.1,deadline-time.monotonic()+3))
        except subprocess.TimeoutExpired:raise Ria2Failure('principal_execution','child_timeout','timeout') from None
        if type(code) is not int:raise Ria2Failure('principal_execution','principal_returncode_unavailable','unclassified')
        RIA3_EXEC_FACTS['returncode']=code
        try:
            # Receipt validation is pure and invokes no content reader.
            ria2_validate_child(ria2_module(),streams['stdout'])
        except Ria2Failure:
            reason,category=ria3_failure(streams['stderr'],code)
            RIA3_EXEC_FACTS['classification']=reason
            raise Ria2Failure('principal_execution',reason,category) from None
        if code!=0:
            RIA3_EXEC_FACTS['classification']='child_receipt_process_nonzero'
            raise Ria2Failure('principal_execution','child_receipt_process_nonzero','process_nonzero')
        complete=True
        RIA3_EXEC_FACTS['classification']='validated_child_receipt'
        return streams['stdout']
    finally:
        if not complete:
            if child.poll() is None:
                child.terminate()
                try:child.wait(timeout=1)
                except subprocess.TimeoutExpired:child.kill();child.wait(timeout=2)
            while time.monotonic()<deadline+.25:time.sleep(.01)
        for worker in workers:worker.join(timeout=1)
        child.stdout.close();child.stderr.close()
        if not child.stdin.closed:child.stdin.close()
        streams.clear()

def ria3_readback():
    global ria2_run_child,RIA3_EXEC_FACTS
    original=ria2_run_child;RIA3_EXEC_FACTS={}
    ria2_run_child=ria3_run_child
    try:receipt=ria2_readback()
    finally:ria2_run_child=original
    if RIA3_EXEC_FACTS:receipt['principal_diagnostics']=dict(RIA3_EXEC_FACTS)
    return receipt
'''
REMOTE_RIA3_DISPATCH = r'''    if mode=='anex-ria-apd-access-v3':
        if operation!='int-anex-ria-apd-access-20261028-v3' or source!='09cedd5c0a8f34521820a7a58586bfb37ad7b553':
            fail('ria3_sealed_scope')
        result.update(supplier_calls=0,database_reads=0,database_writes=0,runtime_writes=0,session_writes=0,cache_writes=0)
        result['anex_ria_apd_access_readback']=ria3_readback()
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

def remote_with_ria3_readback(core):
    remote=core.REMOTE
    definition='def local_read(scopes):\n'
    dispatch="    if mode=='local-readback':\n"
    collectors="    if mode not in ('reconcile',"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collectors)==2,'ria3_registration_source_drift')
    remote=remote.replace(definition,REMOTE_RIA_READER+REMOTE_RIA2_READER+REMOTE_RIA3_READER+definition,1)
    remote=remote.replace(dispatch,REMOTE_RIA3_DISPATCH+dispatch,1)
    remote=remote.replace(collectors,"    if mode not in ('"+RIA3_MODE+"','reconcile',")
    ast.parse(remote)
    return remote


# Same permanent SSH carrier; no public endpoint, sudo, permissions or supplier call.
REMOTE_V4_READER = r"""
V4_PARSER_SOURCE = @@V4_PARSER@@
V4_START = 1793535300  # 2026-11-01T12:15:00Z; excludes the earlier v3 Find.
V4_END = 1793535780    # 2026-11-01T12:23:00Z; original v4 completed12:22:39Z.

def v4_values(value):
    if isinstance(value,list):return value
    if isinstance(value,dict) and list(value)==list(range(len(value))):return list(value.values())
    return None

def v4_config_value(text):
    values=[]
    for line in text.splitlines():
        line=line.strip()
        if not line or line.startswith((';','#')):continue
        if line.startswith('['):raise ValueError('config_section_unsupported')
        if not re.match(r'(?i)^session\.save_path\s*=',line):continue
        match=re.fullmatch(r"(?i)session\.save_path\s*=\s*(?:\"([^\"]+)\"|'([^']+)'|([^;\s]+))\s*(?:;.*)?",line)
        if not match:raise ValueError('config_value_unsupported')
        value=next(v for v in match.groups() if v is not None)
        parts=value.split(';')
        if len(parts)==1:value=parts[0]
        elif len(parts) in (2,3) and parts[0]=='0' and (len(parts)==2 or re.fullmatch(r'[0-7]{3,4}',parts[1])):value=parts[-1]
        else:raise ValueError('config_sharded_path_unsupported')
        if not value.startswith('/') or '$' in value or '\x00' in value:raise ValueError('config_path_unsupported')
        values.append(value)
    if len(values)>1:raise ValueError('config_value_ambiguous')
    return values[0] if values else None

def v4_target_cases(session,ns):
    contexts=session.get('anex_offer_contexts')
    if contexts is None:
        context=session.get('offer_context')
        contexts={'legacy':context} if isinstance(context,dict) else {}
    if not isinstance(contexts,dict) or len(contexts)>4:raise ValueError('context_shape')
    matches=[]
    for context_ref,context in contexts.items():
        if not isinstance(context,dict):raise ValueError('context_reference_or_shape')
        saved=context.get('gateway',{}).get('saved_offers',{})
        if not isinstance(saved,dict):raise ValueError('saved_shape')
        created=saved.get('created_at')
        if type(created) is not int or not V4_START<=created<=V4_END:continue
        search=saved.get('search',{})
        if not isinstance(search,dict):raise ValueError('search_shape')
        ages=v4_values(search.get('child_ages'))
        if ages!=[1] or any(type(age) is not int for age in ages):continue
        if not (search.get('checkin_begin')=='2026-12-20' and search.get('checkin_end')=='2026-12-26'
                and search.get('nights_from')==7 and search.get('nights_till')==7
                and type(search.get('adults')) is int and search['adults']==2
                and type(search.get('children')) is int and search['children']==1
                and type(search.get('nights_from')) is int and type(search.get('nights_till')) is int):continue
        generation=context.get('generation');saved_ref=saved.get('search_ref')
        if (type(generation) is not int or generation<1 or session.get('anex_context_generation')!=generation
                or type(session.get('anex_context_generation')) is not int
                or not isinstance(saved_ref,str) or not re.fullmatch(r'[a-f0-9]{32}',saved_ref)
                or context_ref not in (saved_ref,'legacy')):raise ValueError('target_context_binding')
        snapshot=context.get('gateway',{}).get('search',{})
        snapshot_context=snapshot.get('context') if isinstance(snapshot,dict) else None
        fields=('checkin_begin','checkin_end','nights_from','nights_till','adults','children','child_ages')
        if not isinstance(snapshot_context,dict) or ns['has_reference'](snapshot_context):raise ValueError('snapshot_binding')
        if any(type(snapshot_context.get(field)) is not type(search.get(field)) or snapshot_context.get(field)!=search.get(field) for field in fields):raise ValueError('snapshot_binding')
        known_rows=v4_values(snapshot.get('offers'))
        if known_rows is None or len(known_rows)>5000:raise ValueError('known_offer_binding')
        known={}
        for row in known_rows:
            if not isinstance(row,dict) or not isinstance(row.get('offer_key'),str) or row['offer_key'] in known:raise ValueError('known_offer_binding')
            known[row['offer_key']]=row
        entries=saved.get('offers')
        if not isinstance(entries,dict) or len(entries)>5000:raise ValueError('offers_shape')
        for key,entry in entries.items():
            if isinstance(entry,ns['Ref']):raise ValueError('target_reference')
            offer=entry.get('offer') if isinstance(entry,dict) else None
            if isinstance(offer,ns['Ref']):raise ValueError('target_reference')
            if not isinstance(offer,dict):continue
            hotel=offer.get('hotel',{})
            if not isinstance(hotel,dict) or ns['label'](hotel.get('name'))!='BEACH SAFARI NUBIAN RESORT':continue
            if ns['has_reference'](offer):raise ValueError('target_reference')
            if not isinstance(key,str) or not re.fullmatch(r'anex_online:[a-f0-9]{64}',key) or offer.get('offer_key')!=key:raise ValueError('target_key_binding')
            row=known.get(key)
            if not isinstance(row,dict) or row.get('kind')!=offer.get('kind') or type(row.get('hotel_external_id')) is not type(hotel.get('external_id')) or row.get('hotel_external_id')!=hotel.get('external_id') or hotel.get('external_id') is None:raise ValueError('known_offer_binding')
            money=offer.get('converted_price') or offer.get('price')
            if not (offer.get('kind')=='concrete' and ns['label'](offer.get('room'))=='STANDARD ROOM'
                    and ns['label'](offer.get('meal'))=='AI' and offer.get('checkin')=='2026-12-24'
                    and offer.get('checkout')=='2026-12-31' and type(offer.get('nights')) is int and offer['nights']==7
                    and type(offer.get('adults')) is int and offer['adults']==2
                    and type(offer.get('children')) is int and offer['children']==1
                    and type(offer.get('infants')) is int and offer['infants']==1
                    and isinstance(money,dict) and money.get('currency')=='RUB' and type(money.get('amount')) in (str,int)
                    and re.fullmatch(r'107581(?:\.0{1,2})?',str(money.get('amount')))):continue
            attempts=context.get('package_quotes',{})
            if not isinstance(attempts,dict):raise ValueError('quote_reference_or_shape')
            if key not in attempts:
                matches.append({'status':'quote_not_started','created_at':created,'supplier_stages':[]})
                continue
            attempt=attempts[key]
            if not isinstance(attempt,dict):raise ValueError('quote_reference_or_shape')
            public=attempt.get('public',{})
            if not isinstance(public,dict):raise ValueError('public_shape')
            status=public.get('status')
            if status not in ('quote_choices','quote_verified','quote_failed','quote_unknown','quote_expired'):status='quote_unknown'
            stages=[]
            for stage,state in attempt.get('stages',{}).items():
                if stage in ('start','transports','SetTransport','calcfull') and state in ('complete','unknown'):stages.append({'stage':stage,'state':state})
            pairs=attempt.get('pairs',{})
            if not isinstance(pairs,dict) or len(pairs)>3:raise ValueError('pairs_shape')
            for pair in pairs.values():
                if not isinstance(pair,dict):raise ValueError('pair_reference_or_shape')
                for stage,state in pair.get('stages',{}).items():
                    if stage in ('SetTransport','calcfull') and state in ('complete','unknown'):stages.append({'stage':stage,'state':state})
            # A stored public result is not canonical replay authority (e.g. sealed v2).
            matches.append({'status':'quote_attempt_retained','stored_public_status':status,
                            'sealed':attempt.get('sealed') if type(attempt.get('sealed')) is bool else None,
                            'created_at':created,'supplier_stages':stages})
    return matches

def v4_owned_read(path,limit,actor,ns,window=False,config=False,require_owner=True,expected_facts=None):
    import stat
    ns['no_links'](path)
    before=os.lstat(path)
    fd=os.open(path,os.O_RDONLY|getattr(os,'O_NOFOLLOW',0)|getattr(os,'O_BINARY',0))
    try:
        opened=os.fstat(fd)
        facts=lambda st:(st.st_dev,st.st_ino,st.st_uid,st.st_mode,st.st_size,st.st_mtime_ns,st.st_ctime_ns)
        if facts(before)!=facts(opened) or not stat.S_ISREG(opened.st_mode) or require_owner and opened.st_uid!=actor:raise ValueError('session_ownership')
        if config and opened.st_mode&0o022:raise ValueError('config_ownership_or_mode')
        if expected_facts is not None and facts(opened)!=expected_facts:raise ValueError('config_changed')
        if not 0<opened.st_size<=limit:raise ValueError('session_total_limit')
        if window and not V4_START<=opened.st_mtime<=V4_END:raise ValueError('session_window_changed')
        chunks=[];remaining=opened.st_size
        while remaining:
            part=os.read(fd,min(65536,remaining))
            if not part:raise ValueError('retained_file_changed')
            chunks.append(part);remaining-=len(part)
        if facts(opened)!=facts(os.fstat(fd)):raise ValueError('retained_file_changed')
        return b''.join(chunks)
    finally:os.close(fd)

def v4_owned_session_readback():
    import stat
    report={'status':'hold','supplier_requests':0,'database_operations':0,'runtime_session_cache_writes':0,
            'actor_escalation':False,'path_scope':'project_owned_ini_candidate_only','vhost_pool_authority':False}
    try:
        if hashlib.sha256(V4_PARSER_SOURCE.encode()).hexdigest()!='e75beb5c112fae08592a6c2308db38ee1f2d8d5aecffb49d118f856b303e1aad':raise ValueError('parser_pin')
        ns={'__name__':'fixed_v4_readonly_decoder'}
        exec(compile(V4_PARSER_SOURCE,'<fixed-v4-decoder>','exec'),ns)
        ns.update(START=V4_START,END=V4_END)
        actor=os.geteuid()
        report['stage']='runtime_api_pin'
        # This is pinned public source, not session/config contents; ordinary read ACLs suffice.
        api=v4_owned_read(runtime/'api-anex-search3-preview.php',262144,actor,ns,require_owner=False)
        if hashlib.sha256(api).hexdigest()!='0beda5c137c3153ec7c80da382f63ca3f989634130c508af69f6c6f0ce2162f4':raise ValueError('runtime_api_pin')
        candidates=[]
        report['stage']='project_configuration'
        for config in (project/'.user.ini',project/'_preview/.user.ini',runtime/'.user.ini'):
            if not config.exists():continue
            facts=os.lstat(config)
            if not stat.S_ISREG(facts.st_mode) or facts.st_uid!=actor or facts.st_mode&0o022:raise ValueError('config_ownership_or_mode')
            raw=v4_owned_read(config,65536,actor,ns,config=True)
            value=v4_config_value(raw.decode('utf-8'))
            if value is None:continue
            candidate=pathlib.Path(value)
            ns['no_links'](candidate)
            candidate=candidate.resolve(strict=True)
            candidate.relative_to(home.resolve(strict=True))
            if candidate==home or candidate==project or project in candidate.parents:raise ValueError('candidate_scope')
            facts=os.lstat(candidate)
            if not stat.S_ISDIR(facts.st_mode) or facts.st_uid!=actor:raise ValueError('candidate_ownership')
            candidates.append(candidate)
        candidates=list(dict.fromkeys(candidates))
        report['configured_candidates']=len(candidates)
        if not candidates:raise ValueError('project_owned_session_override_absent')
        if len(candidates)!=1:raise ValueError('project_owned_session_override_ambiguous')
        report['stage']='session_candidate_metadata'
        paths=ns['session_candidates'](candidates[0])
        report['candidate_files']=len(paths)
        total=0
        for path in paths:
            facts=os.lstat(path)
            if facts.st_uid!=actor:raise ValueError('session_ownership')
            total+=facts.st_size
        if total>ns['MAX_TOTAL']:raise ValueError('session_total_limit')
        matches=[];read_bytes=0
        for path in paths:
            report['stage']='retained_session_content'
            raw=v4_owned_read(path,min(ns['MAX_FILE'],ns['MAX_TOTAL']-read_bytes),actor,ns,window=True)
            read_bytes+=len(raw)
            serializer='php_serialize' if raw.startswith(b'a:') else 'php'
            session=ns['decode_session'](raw,serializer)
            report['stage']='exact_case_binding'
            for case in v4_target_cases(session,ns):matches.append((case,hashlib.sha256(raw).hexdigest()))
        if len(matches)!=1:raise ValueError('exact_v4_case_missing' if not matches else 'exact_v4_case_ambiguous')
        case,digest=matches[0]
        report.update(status='retained_case_matched',case='v4-20261101-2A-age1',session_sha256=digest,
                      retained_outcome=case,session_bytes_read=read_bytes,full_price_verified_current=False)
    except Exception as error:
        allowed={'config_section_unsupported','config_value_unsupported','config_sharded_path_unsupported',
            'config_path_unsupported','config_value_ambiguous','config_ownership_or_mode','candidate_scope',
            'candidate_ownership','project_owned_session_override_absent','project_owned_session_override_ambiguous',
            'session_ownership','session_total_limit','exact_v4_case_missing','exact_v4_case_ambiguous',
            'context_shape','context_reference_or_shape','saved_shape','search_shape','offers_shape','target_reference','target_key_binding',
            'quote_reference_or_shape','public_shape','pairs_shape','pair_reference_or_shape','parser_pin','runtime_api_pin',
            'session_window_changed','retained_file_changed','target_context_binding','snapshot_binding','known_offer_binding',
            'retained_file_missing','symlink_rejected','reparse_point_rejected','session_root_invalid',
            'session_directory_entry_limit','session_candidate_count_limit','session_candidate_size_limit',
            'session_candidate_total_size_limit','session_candidate_type_rejected','session_serialization_invalid',
            'session_serialization_limit','session_object_unsupported','session_serializer_unsupported'}
        reason=str(error)
        if isinstance(error,PermissionError):
            report.update(reason='retained_permission_denied',failure_class='permission_denied')
        else:report['reason']=reason if reason in allowed else 'retained_shape_unavailable'
    return report
"""

REMOTE_V4_DISPATCH = r'''    if mode=='anex-v4-owned-session-readback':
        if operation!='int-anex-v4-owned-session-20261101-v1' or source!='6dcfb5053793caba4f353facfa7a899a30df2d17':
            fail('v4_sealed_scope')
        result.update(supplier_calls=0,database_reads=0,database_writes=0,runtime_writes=0,session_writes=0,cache_writes=0)
        result['anex_v4_readback']=v4_owned_session_readback()
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


def remote_with_v4_readback(core):
    remote=core.REMOTE
    definition='def local_read(scopes):\n'
    dispatch="    if mode=='local-readback':\n"
    collectors="    if mode not in ('reconcile',"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collectors)==2,'v4_registration_source_drift')
    reader=REMOTE_V4_READER.replace('@@V4_PARSER@@',repr(RIA_PINNED_READER_SOURCE))
    remote=remote.replace(definition,reader+definition,1)
    remote=remote.replace(dispatch,REMOTE_V4_DISPATCH+dispatch,1)
    remote=remote.replace(collectors,"    if mode not in ('"+V4_MODE+"','reconcile',")
    ast.parse(remote)
    return remote


REMOTE_V4_MAP_READER = r"""
def v4_nginx_parse(text):
    tokens=[];i=0
    while i<len(text):
        char=text[i]
        if char.isspace():i+=1;continue
        if char=='#':
            end=text.find('\n',i);i=len(text) if end<0 else end+1;continue
        if char in '{};':tokens.append(char);i+=1
        else:
            word=[]
            while i<len(text) and not text[i].isspace() and text[i] not in '{};#':
                char=text[i]
                if char in ('\"',"'"):
                    quote=char;i+=1
                    while i<len(text) and text[i]!=quote:
                        if text[i]=='\\':i+=1
                        if i>=len(text):raise ValueError('nginx_syntax')
                        word.append(text[i]);i+=1
                    if i>=len(text):raise ValueError('nginx_syntax')
                    i+=1
                elif char=='\\':
                    i+=1
                    if i>=len(text):raise ValueError('nginx_syntax')
                    word.append(text[i] if text[i].isspace() or text[i] in '\"\'\\;#{}' else '\\'+text[i]);i+=1
                else:word.append(char);i+=1
            tokens.append(''.join(word))
        if len(tokens)>50000:raise ValueError('config_scope_limit')
    at=0;count=0
    def block(depth):
        nonlocal at,count
        if depth>16:raise ValueError('config_scope_limit')
        result=[]
        while at<len(tokens) and tokens[at]!='}':
            words=[]
            while at<len(tokens) and tokens[at] not in ('{','}',';'):words.append(tokens[at]);at+=1
            if not words or at>=len(tokens) or tokens[at]=='}':raise ValueError('nginx_syntax')
            ending=tokens[at];at+=1
            children=None
            if ending=='{':
                children=block(depth+1)
                if at>=len(tokens) or tokens[at]!='}':raise ValueError('nginx_syntax')
                at+=1
            result.append((words[0],words[1:],children));count+=1
            if count>5000:raise ValueError('config_scope_limit')
        return result
    result=block(0)
    if at!=len(tokens):raise ValueError('nginx_syntax')
    return result

def v4_config_trusted(path,roots,actor,ns,budget):
    import stat
    if not path.is_absolute() or '..' in path.parts or not any(path==root or root in path.parents for root in roots):raise ValueError('config_path_scope')
    resolved=path.resolve(strict=True)
    accepted=next((root for root in roots if resolved==root or root in resolved.parents),None)
    if accepted is None:raise ValueError('config_path_scope')
    for parent in (accepted,*list(resolved.relative_to(accepted).parents)[:-1]):
        check=parent if parent.is_absolute() else accepted/parent
        facts=os.lstat(check)
        if facts.st_uid not in (0,actor) or facts.st_mode&0o022:raise ValueError('config_trust')
    facts=os.lstat(resolved)
    if not stat.S_ISREG(facts.st_mode) or facts.st_uid not in (0,actor) or facts.st_mode&0o022:raise ValueError('config_trust')
    fingerprint=(facts.st_dev,facts.st_ino,facts.st_uid,facts.st_mode,facts.st_size,facts.st_mtime_ns,facts.st_ctime_ns)
    cache=budget.setdefault('cache',{})
    if str(resolved) in cache:
        data,previous=cache[str(resolved)]
        if fingerprint!=previous:raise ValueError('config_changed')
        return resolved,data
    remaining=2097152-budget['bytes']
    if len(budget['files'])>=32 or not 0<facts.st_size<=min(262144,remaining):raise ValueError('config_scope_limit')
    data=v4_owned_read(resolved,min(262144,remaining),facts.st_uid,ns,config=True,expected_facts=fingerprint)
    budget['files'].add(str(resolved));budget['bytes']+=len(data)
    cache[str(resolved)]=(data,fingerprint)
    return resolved,data

def v4_config_matches(pattern,roots,actor):
    import fnmatch
    if any(char in str(pattern.parent) for char in '*?[') or '$' in str(pattern) or '..' in pattern.parts:raise ValueError('config_include_shape')
    if not pattern.is_absolute() or not any(pattern.parent==root or root in pattern.parent.parents for root in roots):raise ValueError('config_path_scope')
    parent=pattern.parent.resolve(strict=True)
    if not any(parent==root or root in parent.parents for root in roots):raise ValueError('config_path_scope')
    facts=os.lstat(parent)
    if facts.st_uid not in (0,actor) or facts.st_mode&0o022:raise ValueError('config_trust')
    if not any(char in pattern.name for char in '*?['):return [pattern]
    matches=[];entries=0
    with os.scandir(parent) as scan:
        for entry in scan:
            entries+=1
            if entries>4096:raise ValueError('config_scope_limit')
            if fnmatch.fnmatchcase(entry.name,pattern.name):matches.append(pathlib.Path(entry.path))
            if len(matches)>32:raise ValueError('config_scope_limit')
    return sorted(matches)

def v4_nginx_load(actor,ns,budget):
    roots=(pathlib.Path('/etc/nginx'),pathlib.Path('/usr/share/nginx/modules-available'))
    expanded=0
    def load(path,chain):
        nonlocal expanded
        resolved,data=v4_config_trusted(path,roots,actor,ns,budget)
        if resolved in chain or len(chain)>=16:raise ValueError('config_include_cycle')
        result=[]
        def expand(nodes):
            nonlocal expanded
            output=[]
            for name,args,children in nodes:
                expanded+=1
                if expanded>10000:raise ValueError('config_scope_limit')
                if name=='include':
                    if children is not None or len(args)!=1 or '$' in args[0] or '..' in pathlib.Path(args[0]).parts:raise ValueError('config_include_shape')
                    pattern=pathlib.Path(args[0]);pattern=pattern if pattern.is_absolute() else roots[0]/pattern
                    for match in v4_config_matches(pattern,roots,actor):output.extend(load(match,chain+(resolved,)))
                else:output.append((name,args,expand(children) if children is not None else None))
            return output
        result.extend(expand(v4_nginx_parse(data.decode('utf-8'))))
        return result
    return load(roots[0]/'nginx.conf',())

def v4_nginx_endpoint(children):
    endpoint='/_preview/search3-anex-candidate/api-anex-search3-preview.php'
    routing={'proxy_pass','grpc_pass','uwsgi_pass','scgi_pass','rewrite','return','error_page','internal'}
    if any(name in routing or name=='if' for name,args,sub in children):raise ValueError('vhost_route_unsupported')
    locations=[(args,sub) for name,args,sub in children if name=='location']
    for args,sub in locations:
        if sub is None or any(name=='location' for name,values,nested in sub):raise ValueError('vhost_route_unsupported')
    exact=[sub for args,sub in locations if args==['=',endpoint]]
    if len(exact)>1:raise ValueError('vhost_route_ambiguous')
    selected=exact[0] if exact else None
    if selected is None:
        prefixes=[]
        for args,sub in locations:
            if len(args)==1 and args[0].startswith('/'):
                if endpoint.startswith(args[0]):prefixes.append((args[0],False,sub))
            elif len(args)==2 and args[0]=='^~' and args[1].startswith('/'):
                if endpoint.startswith(args[1]):prefixes.append((args[1],True,sub))
            elif args and args[0] not in ('=','~','~*','^~') and (len(args)!=1 or not args[0].startswith('@')):raise ValueError('vhost_route_unsupported')
        longest=max((len(path) for path,stop,sub in prefixes),default=-1)
        prefixes=[item for item in prefixes if len(item[0])==longest]
        if len(prefixes)>1:raise ValueError('vhost_route_ambiguous')
        if prefixes and prefixes[0][1]:selected=prefixes[0][2]
        else:
            for args,sub in locations:
                if not args or args[0] not in ('~','~*'):continue
                if len(args)!=2:raise ValueError('vhost_route_unsupported')
                pattern=args[1]
                matched=None
                if pattern in ('\\.php$','.php$','\\.php(/|$)','\\.php(?:/|$)','\\.php(?:$|/)'):matched=True
                elif (pattern.startswith('/\\.') or pattern.startswith('^/\\.')) and '|' not in pattern:
                    suffix=pattern[4:] if pattern.startswith('^') else pattern[3:]
                    if not suffix.startswith(('*','?','{')):matched=False
                else:
                    extensions=re.fullmatch(r'\\\.\(([A-Za-z0-9|?]+)\)\$',pattern)
                    if extensions:
                        variants=[]
                        for part in extensions[1].split('|'):
                            if not re.fullmatch(r'[A-Za-z0-9]+\??',part):raise ValueError('vhost_route_unsupported')
                            variants.extend((part[:-1],part[:-2]) if part.endswith('?') else (part,))
                        matched='php' in ([value.lower() for value in variants] if args[0]=='~*' else variants)
                    else:
                        prefix=re.match(r'^\^(/[A-Za-z0-9_/-]+)',pattern)
                        if prefix and '|' not in pattern and pattern[prefix.end():] in ('','.*','.*$','/.*','/.*$','$'):
                            target=endpoint.lower() if args[0]=='~*' else endpoint
                            expected=prefix[1].lower() if args[0]=='~*' else prefix[1]
                            if not target.startswith(expected):matched=False
                if matched is None:raise ValueError('vhost_route_unsupported')
                if matched:selected=sub;break
            if selected is None and prefixes:selected=prefixes[0][2]
    if selected is None:raise ValueError('vhost_route_missing')
    if any(name in routing or name=='if' for name,args,sub in selected):raise ValueError('vhost_route_unsupported')
    if any(name=='try_files' and args not in (['$uri','=404'],['$fastcgi_script_name','=404']) for name,args,sub in selected):raise ValueError('vhost_route_unsupported')
    if any(name=='fastcgi_split_path_info' and args!=['^(.+?\\.php)(/.*)$'] for name,args,sub in selected):raise ValueError('vhost_route_unsupported')
    params=[args for name,args,sub in selected if name=='fastcgi_param']
    if not params:params=[args for name,args,sub in children if name=='fastcgi_param']
    script=[args[1] for args in params if len(args)==2 and args[0]=='SCRIPT_FILENAME']
    if not script or any(value not in ('$document_root$fastcgi_script_name','$realpath_root$fastcgi_script_name','$request_filename') for value in script):raise ValueError('vhost_route_unsupported')
    passes={args[0] for name,args,sub in selected if name=='fastcgi_pass' and len(args)==1}
    if not passes:passes={args[0] for name,args,sub in children if name=='fastcgi_pass' and len(args)==1}
    if len(passes)!=1:raise ValueError('vhost_php_mapping_ambiguous')
    return next(iter(passes))

def v4_nginx_target(nodes,project_path):
    def walk(values):
        for node in values:
            yield node
            if node[2] is not None:yield from walk(node[2])
    servers=[]
    for name,args,children in walk(nodes):
        if name!='server' or children is None:continue
        hostnames=[arg for key,values,sub in children if key=='server_name' for arg in values]
        if not set(hostnames)&{'anytoour.ru','www.anytoour.ru'}:continue
        directives=list(walk(children))
        roots={values[0] for key,values,sub in directives if key=='root' and len(values)==1}
        if not roots:continue  # A redirect-only www/server block is not a PHP mapping.
        if len(roots)!=1 or any('$' in value for value in roots):raise ValueError('vhost_root_ambiguous')
        if pathlib.Path(next(iter(roots))).resolve()!=project_path.resolve():continue
        if any(key=='alias' for key,values,sub in directives):raise ValueError('vhost_alias_unsupported')
        if any(key=='fastcgi_param' and values and values[0] in ('PHP_VALUE','PHP_ADMIN_VALUE') for key,values,sub in directives):raise ValueError('vhost_php_override_unsupported')
        returns=[values for key,values,sub in children if key=='return']
        if len(returns)==1 and returns[0] and returns[0][0] in ('301','302','307','308'):continue
        address=v4_nginx_endpoint(children)
        if not address.startswith('unix:'):
            upstreams=[sub for key,values,sub in walk(nodes) if key=='upstream' and values==[address] and sub is not None]
            if len(upstreams)!=1:raise ValueError('vhost_upstream_unsupported')
            endpoints=[values[0] for key,values,sub in upstreams[0] if key=='server' and len(values)==1]
            if len(endpoints)!=1:raise ValueError('vhost_upstream_unsupported')
            address=endpoints[0]
        if not address.startswith('unix:/') or '$' in address:raise ValueError('vhost_socket_unsupported')
        servers.append(pathlib.Path(address[5:]).resolve(strict=True))
    servers=set(servers)
    if len(servers)!=1:raise ValueError('vhost_mapping_missing_or_ambiguous')
    return next(iter(servers))

def v4_pool_sections(text):
    pools={};current=None
    for line in text.splitlines():
        line=line.strip()
        if not line or line.startswith((';','#')):continue
        if line.startswith('['):
            match=re.fullmatch(r'\[([A-Za-z0-9_.-]{1,80})\]',line)
            if not match or match[1] in pools:raise ValueError('pool_shape')
            current={};pools[match[1]]=current;continue
        if current is None:continue
        if '=' not in line:raise ValueError('pool_shape')
        key,value=line.split('=',1);key=key.strip();value=value.strip()
        if value.startswith(('\"',"'")):
            quote=value[0];end=value.find(quote,1)
            if end<0 or value[end+1:].strip() and not value[end+1:].strip().startswith(';'):raise ValueError('pool_shape')
            value=value[1:end]
        else:value=value.split(';',1)[0].strip()
        if key=='include':raise ValueError('pool_include_unsupported')
        if key in ('listen','user','chroot','php_admin_value[session.save_path]','php_value[session.save_path]',
                   'php_admin_value[user_ini.filename]','php_value[user_ini.filename]'):
            if key in current:raise ValueError('pool_shape')
            current[key]=value
    return list(pools.values())

def v4_fpm_config_load(pool_root,actor,ns,budget):
    included=set()
    def load(path,chain):
        resolved,data=v4_config_trusted(path,(pool_root,),actor,ns,budget)
        if resolved in chain or len(chain)>=16:raise ValueError('config_include_cycle')
        if resolved in included:raise ValueError('config_include_repeated')
        included.add(resolved)
        output=[];section=None
        for line in data.decode('utf-8').splitlines():
            clean=line.strip()
            if clean.startswith('['):
                match=re.fullmatch(r'\[([A-Za-z0-9_.-]{1,80})\]',clean)
                if not match:raise ValueError('pool_shape')
                section=match[1]
            include=re.match(r'^include\s*=(.*)$',clean)
            if include:
                if section not in (None,'global'):raise ValueError('pool_include_unsupported')
                value=include[1].strip()
                if value.startswith(('\"',"'")):
                    quote=value[0];end=value.find(quote,1)
                    if end<0 or value[end+1:].strip() and not value[end+1:].strip().startswith(';'):raise ValueError('pool_shape')
                    value=value[1:end]
                else:value=value.split(';',1)[0].strip()
                if not value:raise ValueError('config_include_shape')
                pattern=pathlib.Path(value);pattern=pattern if pattern.is_absolute() else pool_root/pattern
                for match in v4_config_matches(pattern,(pool_root,),actor):output.append(load(match,chain+(resolved,)))
            else:output.append(line)
        return '\n'.join(output)
    return v4_pool_sections(load(pool_root/'php-fpm.conf',()))

def v4_mapped_session_readback():
    import pwd,stat
    report={'status':'hold','actor_escalation':False,'supplier_requests':0,'database_operations':0,
            'runtime_session_cache_writes':0,'path_scope':'single_configured_vhost_pool_candidate',
            'vhost_pool_authority':'static_configuration_only','stage':'runtime_api_pin'}
    try:
        ns={'__name__':'fixed_v4_mapped_decoder'}
        if hashlib.sha256(V4_PARSER_SOURCE.encode()).hexdigest()!='e75beb5c112fae08592a6c2308db38ee1f2d8d5aecffb49d118f856b303e1aad':raise ValueError('parser_pin')
        exec(compile(V4_PARSER_SOURCE,'<fixed-v4-mapped-decoder>','exec'),ns);ns.update(START=V4_START,END=V4_END)
        actor=os.geteuid()
        api=v4_owned_read(runtime/'api-anex-search3-preview.php',262144,actor,ns,require_owner=False)
        if hashlib.sha256(api).hexdigest()!='0beda5c137c3153ec7c80da382f63ca3f989634130c508af69f6c6f0ce2162f4':raise ValueError('runtime_api_pin')
        report['stage']='configured_vhost';budget={'files':set(),'bytes':0}
        socket=v4_nginx_target(v4_nginx_load(actor,ns,budget),project)
        if not stat.S_ISSOCK(os.stat(socket).st_mode):raise ValueError('fpm_socket_not_live')
        report['stage']='configured_fpm_pool';pool_root=pathlib.Path('/etc/php/8.3/fpm')
        matches=[]
        for pool in v4_fpm_config_load(pool_root,actor,ns,budget):
            listen=pool.get('listen','')
            if listen.startswith('/') and pathlib.Path(listen).resolve()==socket:matches.append(pool)
        if len(matches)!=1:raise ValueError('fpm_pool_missing_or_ambiguous')
        pool=matches[0];username=pool.get('user','')
        if pool.get('chroot') or not re.fullmatch(r'[a-z_][a-z0-9_-]{0,31}',username):raise ValueError('fpm_principal_unsupported')
        uid=pwd.getpwnam(username).pw_uid
        if uid<1:raise ValueError('fpm_principal_unsupported')
        binary=ria_system_binary('/usr/sbin/php-fpm8.3')
        info=ria_capture([binary,'-i'],1048576);values=ria_fpm_values(info)
        user_ini=[line.split('=>') for line in info.splitlines() if line.split('=>')[0].strip()=='user_ini.filename']
        if len(user_ini)!=1 or len(user_ini[0])!=3 or user_ini[0][1].strip()!=user_ini[0][2].strip():raise ValueError('user_ini_metadata_unavailable')
        user_ini=pool.get('php_admin_value[user_ini.filename]',pool.get('php_value[user_ini.filename]',user_ini[0][1].strip()))
        if user_ini not in ('.user.ini','','no value'):raise ValueError('user_ini_filename_unsupported')
        raw_path=pool.get('php_admin_value[session.save_path]',pool.get('php_value[session.save_path]',values['session.save_path']))
        session_path=v4_config_value('session.save_path="'+raw_path+'"')
        if not session_path:raise ValueError('fpm_session_path_unavailable')
        candidate=pathlib.Path(session_path);ns['no_links'](candidate);candidate=candidate.resolve(strict=True)
        if candidate==project or project in candidate.parents:raise ValueError('candidate_scope')
        for config in ((project/'.user.ini',project/'_preview/.user.ini',runtime/'.user.ini') if user_ini=='.user.ini' else ()):
            if config.exists() and v4_config_value(v4_owned_read(config,65536,actor,ns,config=True).decode('utf-8')) is not None:raise ValueError('app_override_changed')
        report.update(pool_uid_same_as_carrier=uid==actor,configured_candidates=1,
                      session_root_sha256=hashlib.sha256(str(candidate).encode()).hexdigest())
        report['stage']='session_candidate_metadata';paths=[];total=0;entries=0
        for config,(_,previous) in budget.get('cache',{}).items():
            facts=os.lstat(config)
            if (facts.st_dev,facts.st_ino,facts.st_uid,facts.st_mode,facts.st_size,facts.st_mtime_ns,facts.st_ctime_ns)!=previous:raise ValueError('config_changed')
        with os.scandir(candidate) as scan:
            for entry in scan:
                entries+=1
                if entries>4096:raise ValueError('session_directory_entry_limit')
                facts=entry.stat(follow_symlinks=False)
                if not ns['NAME'].fullmatch(entry.name) or not V4_START<=facts.st_mtime<=V4_END or facts.st_uid!=uid:continue
                if not stat.S_ISREG(facts.st_mode) or entry.is_symlink() or not 0<facts.st_size<=ns['MAX_FILE']:raise ValueError('session_candidate_type_or_size')
                paths.append(pathlib.Path(entry.path));total+=facts.st_size
                if len(paths)>32 or total>ns['MAX_TOTAL']:raise ValueError('session_candidate_total_size_limit')
        report['candidate_files']=len(paths);cases=[];read_bytes=0
        for path in sorted(paths):
            report['stage']='retained_session_content'
            # Expected file owner only; os.open still runs as the unchanged carrier actor.
            raw=v4_owned_read(path,min(ns['MAX_FILE'],ns['MAX_TOTAL']-read_bytes),uid,ns,window=True);read_bytes+=len(raw)
            session=ns['decode_session'](raw,'php_serialize' if raw.startswith(b'a:') else 'php')
            report['stage']='exact_case_binding'
            for case in v4_target_cases(session,ns):cases.append((case,hashlib.sha256(raw).hexdigest()))
        if len(cases)!=1:raise ValueError('exact_v4_case_missing' if not cases else 'exact_v4_case_ambiguous')
        case,digest=cases[0];report.update(status='retained_case_matched',case='v4-20261101-2A-age1',
            session_sha256=digest,retained_outcome=case,session_bytes_read=read_bytes,full_price_verified_current=False)
    except PermissionError:report.update(reason='retained_permission_denied',failure_class='permission_denied')
    except FileNotFoundError:report.update(reason='configured_path_unavailable',failure_class='not_found')
    except Exception as error:
        reasons={'nginx_syntax','config_scope_limit','config_path_scope','config_trust','config_changed','config_include_cycle','config_include_shape',
            'vhost_root_ambiguous','vhost_alias_unsupported','vhost_php_override_unsupported','vhost_php_mapping_ambiguous',
            'vhost_route_unsupported','vhost_route_missing','vhost_route_ambiguous',
            'vhost_upstream_unsupported','vhost_socket_unsupported','vhost_mapping_missing_or_ambiguous','pool_shape','pool_include_unsupported',
            'fpm_socket_not_live','fpm_pool_missing_or_ambiguous','fpm_principal_unsupported','fpm_session_path_unavailable',
            'candidate_scope','app_override_changed','user_ini_metadata_unavailable','user_ini_filename_unsupported','session_directory_entry_limit','session_candidate_type_or_size',
            'session_candidate_total_size_limit','exact_v4_case_missing','exact_v4_case_ambiguous','runtime_api_pin','parser_pin',
            'config_value_unsupported','config_path_unsupported','config_sharded_path_unsupported','config_value_ambiguous',
            'metadata_binary_unavailable','metadata_output_limit','metadata_timeout','metadata_unavailable','metadata_format'}
        reason=str(error);report['reason']=reason if reason in reasons else 'mapping_or_retained_shape_unavailable'
    return report
"""

REMOTE_V4_MAP_DISPATCH = r'''    if mode=='anex-v4-vhost-session-readback':
        if operation!='int-anex-v4-vhost-session-20261101-v1' or source!='6dcfb5053793caba4f353facfa7a899a30df2d17':fail('v4_map_sealed_scope')
        result.update(supplier_calls=0,database_reads=0,database_writes=0,runtime_writes=0,session_writes=0,cache_writes=0)
        result['anex_v4_vhost_readback']=v4_mapped_session_readback()
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''


def remote_with_v4_map_readback(core):
    remote=core.REMOTE;definition='def local_read(scopes):\n';dispatch="    if mode=='local-readback':\n";collectors="    if mode not in ('reconcile',"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collectors)==2,'v4_map_registration_source_drift')
    reader=REMOTE_V4_READER.replace('@@V4_PARSER@@',repr(RIA_PINNED_READER_SOURCE))
    remote=remote.replace(definition,REMOTE_RIA_READER+reader+REMOTE_V4_MAP_READER+definition,1)
    remote=remote.replace(dispatch,REMOTE_V4_MAP_DISPATCH+dispatch,1)
    remote=remote.replace(collectors,"    if mode not in ('"+V4_MAP_MODE+"','reconcile',")
    ast.parse(remote);return remote


# One new runtime-bound read; prior sealed readers and programs remain unchanged.
REMOTE_V4_CGI_READER = r'''
V4_CGI_PARENT = 'int-anex-v4-cgi-retained-20261110-v1'
V4_CGI_RESTORE = 'int-anex-v4-cgi-restore-20261110-v1'
V4_CGI_API_SHA = '0beda5c137c3153ec7c80da382f63ca3f989634130c508af69f6c6f0ce2162f4'
V4_CGI_HEADER = b'<?php\ndeclare(strict_types=1);\n'
V4_CGI_DEPS = (
    'anex-package-quote-runtime.php','anex-package-quote-client.php','anex-package-quote-handoff.php',
    'anex-normalizer.php','search3-messenger-handoff.php','three-provider-meal-family.php',
    'anex-additional-prices-batch.php','anex-program-observation-runtime.php',
    'tourvisor-selected-tour-handoff.php','anex-anytour-offer-autosave.php',
    'anex-apd-cache-runtime.php','anex-program-apd-store.php','anex-apd-prewarm.php',
)

# This branch executes in the exact installed handler, before every original require.
# No cookie/session opening, imports, supplier transport or settings mutation.
V4_CGI_PHP = r"""
if (array_key_exists('HTTP_X_ANYTOUR_V4_METADATA', $_SERVER)) {
    $v4level = ob_get_level();
    set_error_handler(static function() { return true; });
    $v4buffered = ob_start(); $v4ok = false;
    $v4c = json_decode(base64_decode('@@SPEC@@', true), true);
    $v4g = null; $v4m = null; $v4oldMask = null;
    function v4cneed($ok) { if (!$ok) throw new RuntimeException('v4_cgi_guard'); }
    function v4ctoken($ts, &$i, $text) { v4cneed(($ts[$i++][1] ?? null) === $text); }
    function v4cname($ts, &$i) {
        $t = $ts[$i++] ?? null;
        v4cneed(is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING);
        v4cneed(preg_match('/\A[\'"][A-Z][A-Z0-9_]{0,127}[\'"]\z/D', $t[1]) === 1);
        return substr($t[1], 1, -1);
    }
    function v4cdefine($ts, &$i) {
        v4ctoken($ts, $i, 'define'); v4ctoken($ts, $i, '(');
        $name = v4cname($ts, $i); v4ctoken($ts, $i, ',');
        $v = $ts[$i++] ?? null;
        v4cneed(is_array($v) && (in_array($v[0], [T_CONSTANT_ENCAPSED_STRING,T_LNUMBER,T_DNUMBER], true)
            || ($v[0] === T_STRING && in_array(strtolower($v[1]), ['true','false','null'], true))));
        v4ctoken($ts, $i, ')'); v4ctoken($ts, $i, ';'); return $name;
    }
    function v4cconfig($path, $uid, $bootstrap, &$hashes, $ordinal) {
        clearstatcache(true,$path); v4cneed(realpath($path) === $path);
        $s = @lstat($path);
        v4cneed(is_array($s) && ($s['mode'] & 0170000) === 0100000 && $s['uid'] === $uid
            && ($s['mode'] & 0022) === 0 && $s['nlink'] === 1 && $s['size'] > 0 && $s['size'] <= 65536);
        $raw = @file_get_contents($path, false, null, 0, 65537);
        v4cneed(is_string($raw) && strlen($raw) === $s['size']);
        clearstatcache(true,$path); $after = @lstat($path);
        v4cneed(is_array($after));
        foreach (['dev','ino','mode','uid','gid','size','mtime','ctime','nlink'] as $field) v4cneed($s[$field] === $after[$field]);
        $ts = [];
        foreach (token_get_all($raw, TOKEN_PARSE) as $t) {
            $id = is_array($t) ? $t[0] : 0; $text = is_array($t) ? $t[1] : $t;
            if ($id === T_INLINE_HTML) v4cneed(trim($text) === '');
            elseif (!in_array($id, [T_OPEN_TAG,T_CLOSE_TAG,T_WHITESPACE,T_COMMENT,T_DOC_COMMENT], true))
                $ts[] = [$id,$text];
        }
        $i = 0; $count = 0; $follow = false;
        while ($i < count($ts)) {
            $id = $ts[$i][0];
            if ($id === T_DECLARE) {
                v4cneed($count === 0); ++$i; v4ctoken($ts,$i,'('); v4ctoken($ts,$i,'strict_types');
                v4ctoken($ts,$i,'='); v4cneed(in_array($ts[$i++][1] ?? null,['0','1'],true));
                v4ctoken($ts,$i,')'); v4ctoken($ts,$i,';');
            } elseif ($id === T_REQUIRE_ONCE) {
                v4cneed($ordinal === 0 && !$follow); ++$i;
                foreach (['dirname','(','__DIR__',',','4',')','.'] as $word) v4ctoken($ts,$i,$word);
                v4cneed(($ts[$i++][1] ?? null) === "'/.anytoour-anex/search3-preview.php'");
                v4ctoken($ts,$i,';'); $follow = true;
            } elseif ($id === T_IF) {
                ++$i; foreach (['(','!','defined','('] as $word) v4ctoken($ts,$i,$word);
                $name = v4cname($ts,$i); v4ctoken($ts,$i,')'); v4ctoken($ts,$i,')');
                $brace = ($ts[$i][1] ?? null) === '{'; if ($brace) ++$i;
                v4cneed(v4cdefine($ts,$i) === $name); if ($brace) v4ctoken($ts,$i,'}');
            } else v4cdefine($ts,$i);
            ++$count; v4cneed($count <= 128);
        }
        v4cneed($count > 0);
        $hashes[$ordinal === 0 ? 'outer' : 'bootstrap'] = hash('sha256',$raw);
        if ($follow) v4cconfig($bootstrap,$uid,$bootstrap,$hashes,1);
    }
    try {
        v4cneed($v4buffered === true && is_array($v4c) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && ($_SERVER['HTTP_HOST'] ?? '') === 'anytoour.ru'
            && ($_SERVER['QUERY_STRING'] ?? '') === ''
            && is_string($_SERVER['HTTP_X_ANYTOUR_V4_METADATA'])
            && hash_equals($v4c['nonce_hash'], hash('sha256',$_SERVER['HTTP_X_ANYTOUR_V4_METADATA']))
            && time() <= $v4c['deadline']);
        v4cneed(function_exists('posix_geteuid') && posix_geteuid() === $v4c['uid']);
        v4cneed(__FILE__ === $v4c['script'] && ($_SERVER['SCRIPT_FILENAME'] ?? '') === $v4c['script']);
        $v4g = @fopen($v4c['gate'],'rb'); v4cneed(is_resource($v4g));
        $v4s = fstat($v4g);
        v4cneed(($v4s['mode'] & 0170000) === 0100000 && ($v4s['mode'] & 0777) === 0600
            && $v4s['uid'] === $v4c['uid'] && $v4s['nlink'] === 1
            && [$v4s['dev'],$v4s['ino'],$v4s['uid'],$v4s['gid'],$v4s['mode']] === $v4c['gate_identity']
            && flock($v4g,LOCK_SH|LOCK_NB));
        v4cneed(stream_get_contents($v4g,128) === "OPEN\n".$v4c['nonce_hash']."\n" && time() <= $v4c['deadline']);
        $v4startup = ['session_status'=>session_status(),'auto_start'=>(string)ini_get('session.auto_start'),
            'prepend_empty'=>ini_get('auto_prepend_file') === '', 'append_empty'=>ini_get('auto_append_file') === ''];
        $v4safe = $v4startup['session_status'] === PHP_SESSION_NONE
            && in_array($v4startup['auto_start'],['','0'],true)
            && $v4startup['prepend_empty'] && $v4startup['append_empty'];
        $v4hashes = []; $v4config = false;
        if ($v4safe) {
            try { v4cconfig($v4c['config'],$v4c['uid'],$v4c['bootstrap'],$v4hashes,0); $v4config = true; }
            catch (Throwable $v4ignored) { $v4hashes = []; }
        }
        $v4root = null; $v4candidate = null; $v4basis = 'unavailable';
        $v4path = (string)ini_get('session.save_path');
        if ((string)ini_get('session.save_handler') === 'files'
            && in_array((string)ini_get('session.serialize_handler'),['php','php_serialize'],true)) {
            $v4basis = $v4path === '' ? 'cgi_files_empty_path_runtime_tempdir' : 'cgi_files_configured';
            $v4effective = $v4path === '' ? sys_get_temp_dir() : $v4path;
            $v4parts = explode(';',$v4effective);
            if (count($v4parts) === 1) $v4candidate = $v4effective;
            elseif (count($v4parts) === 3 && $v4parts[0] === '0'
                && preg_match('/\A[0-7]{3,4}\z/D',$v4parts[1]) === 1) $v4candidate = $v4parts[2];
            if (is_string($v4candidate) && strlen($v4candidate) > 1 && strlen($v4candidate) <= 1024
                && $v4candidate[0] === '/' && preg_match('/[\\\\\x00-\x20\x7f]/',$v4candidate) === 0
                && !in_array('..',explode('/',$v4candidate),true) && realpath($v4candidate) === $v4candidate) {
                clearstatcache(true,$v4candidate); $v4rs = @lstat($v4candidate);
                if (is_array($v4rs) && ($v4rs['mode'] & 0170000) === 0040000)
                    $v4root = ['path'=>$v4candidate,'identity'=>[$v4rs['dev'],$v4rs['ino'],$v4rs['uid'],$v4rs['gid'],$v4rs['mode']]];
            }
        }
        $v4data = ['schema_version'=>1,'operation_id'=>$v4c['operation_id'],'nonce_hash'=>$v4c['nonce_hash'],
            'at'=>time(),'uid'=>posix_geteuid(),'sapi'=>php_sapi_name(),'script'=>__FILE__,
            'script_sha256'=>hash_file('sha256',__FILE__),'startup'=>$v4startup,
            'config_contract'=>$v4config,'config_hashes'=>$v4hashes,
            'save_handler'=>(string)ini_get('session.save_handler'),
            'save_path'=>(string)ini_get('session.save_path'),
            'serializer'=>(string)ini_get('session.serialize_handler'),'session_root'=>$v4root,
            'effective_path'=>$v4candidate,'path_basis'=>$v4basis,'php_version_id'=>PHP_VERSION_ID];
        v4cneed(time() <= $v4c['deadline']);
        $v4raw = json_encode($v4data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        v4cneed(strlen($v4raw) <= 16384);
        $v4oldMask = umask(0077);
        $v4m = @fopen($v4c['metadata'],'xb');
        umask($v4oldMask); $v4oldMask = null;
        v4cneed(is_resource($v4m)); $v4s = fstat($v4m);
        v4cneed(($v4s['mode'] & 0777) === 0600 && $v4s['uid'] === $v4c['uid'] && $v4s['nlink'] === 1);
        $v4offset = 0;
        while ($v4offset < strlen($v4raw)) {
            $v4written = @fwrite($v4m,substr($v4raw,$v4offset));
            v4cneed(is_int($v4written) && $v4written > 0); $v4offset += $v4written;
        }
        v4cneed(fflush($v4m)); fclose($v4m); $v4m = null;
        flock($v4g,LOCK_UN); fclose($v4g); $v4g = null;
        $v4ok = true;
    } catch (Throwable $v4ignored) {
        if ($v4oldMask !== null) umask($v4oldMask);
        if (is_resource($v4m)) fclose($v4m);
        if (is_resource($v4g)) { flock($v4g,LOCK_UN); fclose($v4g); }
    }
    while (ob_get_level() > $v4level) ob_end_clean();
    restore_error_handler();
    header('Content-Type: application/json'); header('Cache-Control: private, no-store');
    http_response_code($v4ok ? 200 : 403); echo $v4ok ? '{"ok":true}' : '{"ok":false}';
    exit;
}
"""

def v4_cgi_ns():
    if hashlib.sha256(V4_PARSER_SOURCE.encode()).hexdigest() != 'e75beb5c112fae08592a6c2308db38ee1f2d8d5aecffb49d118f856b303e1aad':
        raise ValueError('v4_cgi_parser_pin')
    ns = {'__name__':'fixed_v4_cgi_decoder'}
    exec(compile(V4_PARSER_SOURCE,'<fixed-v4-cgi-decoder>','exec'),ns)
    ns.update(START=V4_START,END=V4_END)
    return ns

def v4_cgi_identity(path,actor,ns,maximum=262144,permissions=None):
    import stat
    ns['no_links'](path)
    s = os.lstat(path)
    if (not stat.S_ISREG(s.st_mode) or s.st_uid != actor or s.st_nlink != 1
            or s.st_mode & 0o022 or not 0 < s.st_size <= maximum
            or (permissions is not None and s.st_mode & 0o777 != permissions)):
        raise ValueError('v4_cgi_file_contract')
    return [s.st_dev,s.st_ino,s.st_uid,s.st_gid,s.st_mode,s.st_size,s.st_mtime_ns,s.st_ctime_ns,s.st_nlink]

def v4_cgi_read(path,actor,ns,maximum=262144,permissions=None):
    before = v4_cgi_identity(path,actor,ns,maximum,permissions)
    raw = v4_owned_read(path,maximum,actor,ns,config=True,expected_facts=tuple([before[0],before[1],before[2],before[4],before[5],before[6],before[7]]))
    if v4_cgi_identity(path,actor,ns,maximum,permissions) != before: raise ValueError('v4_cgi_file_changed')
    return raw

def v4_cgi_write(path,raw):
    fd = os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|getattr(os,'O_NOFOLLOW',0),0o600)
    try:
        offset = 0
        while offset < len(raw):
            written = os.write(fd,raw[offset:])
            if written < 1: raise ValueError('v4_cgi_private_write')
            offset += written
        os.fsync(fd)
    finally: os.close(fd)
    directory = os.open(path.parent,os.O_RDONLY|getattr(os,'O_DIRECTORY',0))
    try: os.fsync(directory)
    finally: os.close(directory)


def v4_cgi_sync_file(path,identity,actor,ns):
    if v4_cgi_identity(path,actor,ns) != identity: raise ValueError('v4_cgi_sync_file_changed')
    fd = os.open(path,os.O_RDONLY|getattr(os,'O_NOFOLLOW',0))
    try:
        st = os.fstat(fd)
        actual = [st.st_dev,st.st_ino,st.st_uid,st.st_gid,st.st_mode,st.st_size,st.st_mtime_ns,st.st_ctime_ns,st.st_nlink]
        if actual != identity: raise ValueError('v4_cgi_sync_file_changed')
        os.fsync(fd)
    finally: os.close(fd)
    if v4_cgi_identity(path,actor,ns) != identity: raise ValueError('v4_cgi_sync_file_changed')

def v4_cgi_sync_dirs(*paths):
    import stat
    for path in dict.fromkeys(paths):
        fd = os.open(path,os.O_RDONLY|getattr(os,'O_DIRECTORY',0)|getattr(os,'O_NOFOLLOW',0))
        try:
            if not stat.S_ISDIR(os.fstat(fd).st_mode): raise ValueError('v4_cgi_sync_directory')
            os.fsync(fd)
        finally: os.close(fd)

def v4_cgi_cleanup_temp(path,identity,actor,ns):
    try:
        ns['no_links'](path.parent)
        try: os.lstat(path)
        except FileNotFoundError: return
        current = v4_cgi_identity(path,actor,ns)
    except Exception: raise ValueError('v4_cgi_temp_cleanup_unproven') from None
    if identity is None or current != identity: raise ValueError('v4_cgi_temp_cleanup_foreign')
    try:
        path.unlink()
        v4_cgi_sync_dirs(path.parent)
    except Exception: raise ValueError('v4_cgi_temp_cleanup_failed') from None

def v4_cgi_root_trust(root,actor,ns):
    import stat
    ns['no_links'](root); shared = False
    for path in (root,*root.parents):
        st = os.lstat(path)
        if not stat.S_ISDIR(st.st_mode) or st.st_uid not in (0,actor):
            raise ValueError('v4_cgi_root_trust')
        if st.st_mode & 0o022:
            if st.st_uid != 0 or not st.st_mode & stat.S_ISVTX: raise ValueError('v4_cgi_root_writable')
            shared = True
    return shared

def v4_cgi_close_gate(parent,intent,actor,ns):
    import fcntl
    path = parent/'cgi-gate'
    identity = v4_cgi_identity(path,actor,ns,128,0o600)
    fd = os.open(path,os.O_RDWR|getattr(os,'O_NOFOLLOW',0))
    try:
        s = os.fstat(fd)
        if [s.st_dev,s.st_ino] != identity[:2]: raise ValueError('v4_cgi_gate_changed')
        deadline = time.monotonic()+10
        while True:
            try: fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB); break
            except BlockingIOError:
                if time.monotonic() >= deadline: raise ValueError('v4_cgi_gate_close_timeout')
                time.sleep(.025)
        value = os.read(fd,129)
        if value not in (('OPEN\n'+intent['nonce_hash']+'\n').encode(),b'CLOSED\n'):
            raise ValueError('v4_cgi_gate_shape')
        os.lseek(fd,0,os.SEEK_SET); os.write(fd,b'CLOSED\n'); os.ftruncate(fd,7); os.fsync(fd)
        if v4_cgi_identity(path,actor,ns,128,0o600)[:2] != identity[:2]: raise ValueError('v4_cgi_gate_changed')
    finally: os.close(fd)

def v4_cgi_load_intent(parent,actor,ns):
    import stat
    ns['no_links'](parent)
    s = os.lstat(parent)
    if not stat.S_ISDIR(s.st_mode) or s.st_uid != actor or s.st_mode & 0o777 != 0o700:
        raise ValueError('v4_cgi_private_scope')
    reservation = json.loads(v4_cgi_read(parent/'reservation.json',actor,ns,16384,0o600))
    if (not isinstance(reservation,dict) or reservation.get('operation_id') != V4_CGI_PARENT
            or reservation.get('source_sha') != '6dcfb5053793caba4f353facfa7a899a30df2d17'
            or reservation.get('mode') != 'anex-v4-cgi-retained-readback'
            or type(reservation.get('reserved_at')) is not int):
        raise ValueError('v4_cgi_parent_reservation')
    value = json.loads(v4_cgi_read(parent/'cgi-intent.json',actor,ns,16384,0o600))
    keys = {'schema_version','parent_operation','source_sha','original_sha256','wrapper_sha256',
        'wrapper_identity','original_identity','nonce_hash','deadline'}
    if (not isinstance(value,dict) or set(value) != keys or value['schema_version'] != 1
            or value['parent_operation'] != V4_CGI_PARENT or value['source_sha'] != '6dcfb5053793caba4f353facfa7a899a30df2d17'
            or value['original_sha256'] != V4_CGI_API_SHA
            or any(not isinstance(value[k],str) or re.fullmatch('[a-f0-9]{64}',value[k]) is None
                   for k in ('wrapper_sha256','nonce_hash'))
            or type(value['deadline']) is not int or not reservation['reserved_at'] <= value['deadline'] <= reservation['reserved_at']+180
            or any(not isinstance(value[k],list) or len(value[k]) != 9
                   or any(type(n) is not int for n in value[k]) for k in ('wrapper_identity','original_identity'))
            or value['original_identity'][2] != actor or value['wrapper_identity'][2] != actor
            or value['original_identity'][3:5] != value['wrapper_identity'][3:5]):
        raise ValueError('v4_cgi_intent_shape')
    return value

def v4_cgi_same_staged(actual,expected):
    # rename may update ctime; stable inode/owner/group/mode/size/mtime and digest are pinned.
    return actual[:7]+actual[8:] == expected[:7]+expected[8:]

def v4_cgi_restore(parent,actor,ns):
    intent = v4_cgi_load_intent(parent,actor,ns)
    v4_cgi_close_gate(parent,intent,actor,ns)
    api = runtime/'api-anex-search3-preview.php'
    current = v4_cgi_identity(api,actor,ns)
    raw = v4_cgi_read(api,actor,ns)
    original = v4_cgi_read(parent/'cgi-api-original.php',actor,ns,262144,0o600)
    if hashlib.sha256(original).hexdigest() != V4_CGI_API_SHA: raise ValueError('v4_cgi_backup_pin')
    expected = intent['original_identity']
    if current[2:5] != expected[2:5]: raise ValueError('v4_cgi_restore_owner')
    digest = hashlib.sha256(raw).hexdigest()
    if digest == V4_CGI_API_SHA:
        v4_cgi_sync_file(api,current,actor,ns); v4_cgi_sync_dirs(parent,api.parent)
        return {'status':'already_original','gate_closed':True,'api_restored':True}
    if digest != intent['wrapper_sha256'] or not v4_cgi_same_staged(current,intent['wrapper_identity']):
        raise ValueError('v4_cgi_restore_foreign_writer')
    temp = stage_target_bytes(parent/'cgi-restored.php',original,expected[4]&0o777)
    staged = None
    try:
        staged = v4_cgi_identity(temp,actor,ns)
        if staged[0] != current[0] or staged[2:5] != expected[2:5]: raise ValueError('v4_cgi_restore_group')
        v4_cgi_sync_file(temp,staged,actor,ns)
        if v4_cgi_identity(api,actor,ns) != current: raise ValueError('v4_cgi_restore_foreign_writer')
        os.replace(temp,api)
        v4_cgi_sync_dirs(parent,api.parent)
        restored = v4_cgi_identity(api,actor,ns)
        if restored[2:5] != expected[2:5] or hashlib.sha256(v4_cgi_read(api,actor,ns)).hexdigest() != V4_CGI_API_SHA:
            raise ValueError('v4_cgi_restore_verification')
    finally:
        v4_cgi_cleanup_temp(temp,staged,actor,ns)
    return {'status':'restored','gate_closed':True,'api_restored':True}

def v4_cgi_http(nonce):
    import urllib.request
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self,*args,**kwargs): return None
    opener = urllib.request.build_opener(NoRedirect)
    request = urllib.request.Request(
        'https://anytoour.ru/_preview/search3-anex-candidate/api-anex-search3-preview.php',
        data=b'',method='POST',headers={'X-AnyTour-V4-Metadata':nonce,'Content-Type':'text/plain','Accept':'application/json'})
    with opener.open(request,timeout=15) as response:
        raw = response.read(129)
        if (response.status != 200 or len(raw) > 128 or response.headers.get_all('Set-Cookie',[])
                or 'no-store' not in response.headers.get('Cache-Control','')
                or response.headers.get_content_type() != 'application/json'
                or json.loads(raw) != {'ok':True}):
            raise ValueError('v4_cgi_http_shape')

def v4_cgi_dependencies(actor,ns):
    # These accepted, pinned top-level imports contain definitions only. Never execute them.
    paths = [('v2/data/hotel-details-v1.php',runtime/'data/hotel-details-v1.php')]
    paths += [('app/integrations/'+name,runtime/'app/integrations'/name) for name in V4_CGI_DEPS]
    for relative,path in paths:
        expected = files.get(relative)
        raw = v4_cgi_read(path,actor,ns,2097152)
        if not isinstance(expected,str) or hashlib.sha256(raw).hexdigest() != expected:
            raise ValueError('v4_cgi_dependency_pin')
        if any(word in raw for word in (b'session.save_path',b'session.save_handler',b'session.serialize_handler',
                b'session_set_save_handler',b'session_save_path(',b'session_module_name(')):
            raise ValueError('v4_cgi_dependency_storage_override')

def v4_cgi_metadata(parent,intent,actor,ns):
    metadata = json.loads(v4_cgi_read(parent/'cgi-metadata.json',actor,ns,16384,0o600))
    keys = {'schema_version','operation_id','nonce_hash','at','uid','sapi','script','script_sha256',
            'startup','config_contract','config_hashes','save_handler','save_path','serializer','session_root',
            'effective_path','path_basis','php_version_id'}
    startup = metadata.get('startup') if isinstance(metadata,dict) else None
    if (not isinstance(metadata,dict) or set(metadata) != keys or metadata['schema_version'] != 1
            or metadata['operation_id'] != V4_CGI_PARENT or metadata['nonce_hash'] != intent['nonce_hash']
            or metadata['uid'] != actor or type(metadata['uid']) is not int
            or type(metadata['at']) is not int or not intent['deadline']-60 <= metadata['at'] <= intent['deadline']
            or metadata['sapi'] != 'cgi-fcgi' or metadata['script'] != str(runtime/'api-anex-search3-preview.php')
            or metadata['script_sha256'] != intent['wrapper_sha256']):
        raise ValueError('v4_cgi_metadata_binding')
    if (not isinstance(startup,dict) or set(startup) != {'session_status','auto_start','prepend_empty','append_empty'}
            or startup['session_status'] != 1 or type(startup['session_status']) is not int
            or startup['auto_start'] not in ('','0') or startup['prepend_empty'] is not True or startup['append_empty'] is not True):
        raise ValueError('v4_cgi_startup_unproven')
    if metadata['config_contract'] is not True: raise ValueError('v4_cgi_private_config_unproven')
    hashes = metadata['config_hashes']
    if not isinstance(hashes,dict) or set(hashes) not in ({'outer'},{'outer','bootstrap'}): raise ValueError('v4_cgi_private_config_unproven')
    paths = {'outer':runtime/'.anex-private.php','bootstrap':home/'.anytoour-anex/search3-preview.php'}
    for index,digest in hashes.items():
        if not isinstance(digest,str) or hashlib.sha256(v4_cgi_read(paths[index],actor,ns,65536)).hexdigest() != digest:
            raise ValueError('v4_cgi_private_config_changed')
    if metadata['save_handler'] != 'files': raise ValueError('v4_cgi_session_handler_unsupported')
    serializer = metadata['serializer']
    if serializer not in ('php','php_serialize'): raise ValueError('v4_cgi_session_serializer_unsupported')
    value = metadata['save_path']; effective = metadata['effective_path']
    if not isinstance(value,str) or len(value) > 1024 or type(metadata['php_version_id']) is not int:
        raise ValueError('v4_cgi_session_path_unavailable')
    if value == '':
        # PHP 8.3 mod_files and sys_get_temp_dir use the same CGI php_get_temporary_directory().
        if (metadata['path_basis'] != 'cgi_files_empty_path_runtime_tempdir'
                or not 80300 <= metadata['php_version_id'] < 80400
                or not isinstance(effective,str) or not effective):
            raise ValueError('v4_cgi_default_path_unproven')
        candidate = v4_config_value('session.save_path='+json.dumps(effective))
    else:
        if metadata['path_basis'] != 'cgi_files_configured': raise ValueError('v4_cgi_session_path_unavailable')
        candidate = v4_config_value('session.save_path='+json.dumps(value))
        if candidate != effective: raise ValueError('v4_cgi_session_path_unavailable')
    if not candidate: raise ValueError('v4_cgi_session_path_unavailable')
    root = pathlib.Path(candidate); ns['no_links'](root)
    if root.resolve(strict=True) != root or root in (pathlib.Path(root.anchor),home,project,runtime) or project in root.parents:
        raise ValueError('v4_cgi_session_path_scope')
    root_fact = metadata['session_root']
    import stat
    st = os.lstat(root)
    identity = [st.st_dev,st.st_ino,st.st_uid,st.st_gid,st.st_mode]
    if (not stat.S_ISDIR(st.st_mode) or not isinstance(root_fact,dict) or set(root_fact) != {'path','identity'}
            or root_fact['path'] != str(root) or root_fact['identity'] != identity
            or any(type(n) is not int for n in root_fact['identity'])):
        raise ValueError('v4_cgi_root_namespace_unproven')
    v4_cgi_root_trust(root,actor,ns)
    return root,serializer,identity


V4_CGI_MONEY_ENCODER = r"""
error_reporting(0);
function v4munpack($x,&$nodes,$depth=0) {
    if (++$nodes > 120000 || $depth > 64 || !is_array($x) || array_keys($x)!==['t','v'])
        throw new RuntimeException('shape');
    $t=$x['t'];$v=$x['v'];
    if ($t==='n' && $v===null) return null;
    if ($t==='b' && is_bool($v)) return $v;
    if ($t==='s' && is_string($v)) return $v;
    if ($t==='i' && is_string($v) && preg_match('/\A-?(?:0|[1-9][0-9]*)\z/D',$v)===1
        && (string)(int)$v===$v) return (int)$v;
    if ($t==='d' && is_string($v) && is_numeric($v) && is_finite((float)$v)) return (float)$v;
    if ($t==='a' && is_array($v) && array_is_list($v)) {
        $out=[];
        foreach($v as $pair) {
            if(!is_array($pair)||array_keys($pair)!==[0,1]) throw new RuntimeException('shape');
            $key=v4munpack($pair[0],$nodes,$depth+1);
            if((!is_string($key)&&!is_int($key))||array_key_exists($key,$out)) throw new RuntimeException('key');
            $out[$key]=v4munpack($pair[1],$nodes,$depth+1);
        }
        return $out;
    }
    throw new RuntimeException('type');
}
try {
    $raw=stream_get_contents(STDIN,1048577);
    if(!is_string($raw)||strlen($raw)>1048576) throw new RuntimeException('bound');
    $data=json_decode($raw,true,256,JSON_THROW_ON_ERROR);
    if(!is_array($data)||array_keys($data)!==['binding','maps','id']||!is_string($data['id'])
        ||!is_array($data['maps'])||count($data['maps'])>3) throw new RuntimeException('shape');
    $nodes=0;$binding=v4munpack($data['binding'],$nodes);$refs=[];
    foreach($data['maps'] as $wire) {
        $map=v4munpack($wire,$nodes);
        if(!is_array($map)||count($map)!==2) throw new RuntimeException('map');
        $sorted=$map;ksort($sorted,SORT_NUMERIC);
        if($sorted!==$map) throw new RuntimeException('map');
        $refs[]='anex_quote:'.hash('sha256',$data['id']."\0".json_encode($map,JSON_THROW_ON_ERROR));
    }
    echo json_encode(['context_digest'=>hash('sha256',json_encode($binding,JSON_THROW_ON_ERROR)),
        'pair_refs'=>$refs],JSON_THROW_ON_ERROR);
} catch(Throwable $ignored) { echo '{"ok":false}';exit(1); }
"""

def v4_cgi_wire(value,depth=0,counter=None):
    import math
    counter = [0] if counter is None else counter; counter[0] += 1
    if counter[0] > 120000 or depth > 64: raise ValueError('v4_cgi_history_encoding_bound')
    if value is None: return {'t':'n','v':None}
    if type(value) is bool: return {'t':'b','v':value}
    if type(value) is int: return {'t':'i','v':str(value)}
    if type(value) is float and math.isfinite(value): return {'t':'d','v':repr(value)}
    if isinstance(value,str): return {'t':'s','v':value}
    if isinstance(value,dict):
        pairs=[]
        for key,item in value.items():
            if type(key) not in (str,int): raise ValueError('v4_cgi_history_encoding_type')
            pairs.append([v4_cgi_wire(key,depth+1,counter),v4_cgi_wire(item,depth+1,counter)])
        return {'t':'a','v':pairs}
    raise ValueError('v4_cgi_history_encoding_type')

def v4_cgi_php_hashes(binding,maps,calculation_id):
    payload={'binding':v4_cgi_wire(binding),'maps':[v4_cgi_wire(m) for m in maps],'id':calculation_id}
    raw=json.dumps(payload,separators=(',',':'),allow_nan=False).encode()
    if len(raw)>1048576: raise ValueError('v4_cgi_history_encoding_bound')
    child=subprocess.run(['php','-n','-d','display_errors=0','-d','allow_url_fopen=0',
        '-d','auto_prepend_file=','-d','auto_append_file=','-d','opcache.enable_cli=0',
        '-r',V4_CGI_MONEY_ENCODER],input=raw,capture_output=True,timeout=5)
    if child.returncode or child.stderr or len(child.stdout)>1024: raise ValueError('v4_cgi_history_php_encoding')
    result=json.loads(child.stdout)
    if (not isinstance(result,dict) or set(result)!={'context_digest','pair_refs'}
            or not isinstance(result['context_digest'],str) or re.fullmatch('[a-f0-9]{64}',result['context_digest']) is None
            or not isinstance(result['pair_refs'],list) or len(result['pair_refs'])!=len(maps)
            or any(not isinstance(ref,str) or re.fullmatch('anex_quote:[a-f0-9]{64}',ref) is None for ref in result['pair_refs'])):
        raise ValueError('v4_cgi_history_php_encoding')
    return result

def v4_cgi_php_same(a,b):
    if type(a) is not type(b): return False
    if isinstance(a,dict):
        return (list(a.keys())==list(b.keys()) and all(type(x) is type(y) for x,y in zip(a,b))
            and all(v4_cgi_php_same(a[k],b[k]) for k in a))
    return a==b

def v4_cgi_history_diagnostic(value,stage):
    return (isinstance(value,dict) and value.get('action')==stage
        and type(value.get('http_status')) is int and value['http_status']==200
        and type(value.get('curl_errno')) is int and value['curl_errno']==0
        and type(value.get('response_bytes')) is int and 0<value['response_bytes']<=2097152
        and type(value.get('elapsed_ms')) is int and 0<=value['elapsed_ms']<=120000)

def v4_cgi_historical_gross(session,ns):
    unknown={'status':'unknown','amount':None,'historical_only':True,'current_validity':False,
        'raw_participant_documents_retained':False,'reason':'v4_cgi_history_unverified'}
    try:
        if len(v4_target_cases(session,ns))!=1: raise ValueError('v4_cgi_history_identity')
        contexts=session.get('anex_offer_contexts')
        if contexts is None: contexts={'legacy':session.get('offer_context')}
        located=[]
        for context_ref,context in contexts.items():
            gateway=context['gateway'];saved=gateway['saved_offers'];attempts=context.get('package_quotes',{})
            if not isinstance(attempts,dict) or len(attempts)>40: raise ValueError('v4_cgi_history_attempt_bound')
            for key in attempts:
                if key not in saved['offers']: continue
                candidate=dict(context);cg=dict(gateway);cs=dict(saved);cs['offers']={key:saved['offers'][key]}
                cg['saved_offers']=cs;candidate['gateway']=cg
                view={'anex_context_generation':session.get('anex_context_generation'),'anex_offer_contexts':{context_ref:candidate}}
                if len(v4_target_cases(view,ns))==1: located.append((context,key,saved['offers'][key]))
        if len(located)!=1: raise ValueError('v4_cgi_history_attempt_missing')
        context,key,entry=located[0];offer=entry['offer'];saved=context['gateway']['saved_offers']
        snapshot=context['gateway']['search'];search=snapshot['context'];attempt=context['package_quotes'][key]
        public_failure=attempt.get('public') if isinstance(attempt,dict) else None
        if isinstance(public_failure,dict) and public_failure.get('status') in ('quote_failed','quote_unknown','quote_expired'):
            reasons={'ANEX_QUOTE_IDENTITY_UNCONFIRMED','ANEX_QUOTE_TRANSPORT_UNCONFIRMED','ANEX_QUOTE_PRICE_UNCONFIRMED',
                'ANEX_QUOTE_SUPPLIER_REJECTED','ANEX_QUOTE_HTTP_ERROR','ANEX_QUOTE_TRANSPORT_ERROR','ANEX_QUOTE_INVALID_RESPONSE',
                'ANEX_QUOTE_CLIENT_UNAVAILABLE','ANEX_QUOTE_RATE_LIMIT','ANEX_QUOTE_UNKNOWN','ANEX_QUOTE_EXPIRED'}
            if public_failure.get('reason') in reasons: unknown['quote_reason']=public_failure['reason']
            if public_failure.get('failure_stage') in ('start','transports','SetTransport','calcfull'):
                unknown['failure_stage']=public_failure['failure_stage']
        if ns['has_reference'](attempt): raise ValueError('v4_cgi_history_reference')
        known=[row for row in v4_values(snapshot['offers']) if isinstance(row,dict) and row.get('offer_key')==key]
        currency=search.get('currency_id')
        if (len(known)!=1 or not isinstance(known[0].get('supplier_offer_id'),str) or not known[0]['supplier_offer_id']
                or type(currency) not in (str,int) or str(currency)==''
                or v4_values(search.get('child_ages'))!=[1]
                or type(saved.get('expires_at')) is not int or saved['expires_at']<=saved['created_at']):
            raise ValueError('v4_cgi_history_binding')
        expiry=attempt.get('expires_at');pairs=attempt.get('pairs');choices=attempt.get('choices');stages=attempt.get('stages')
        if (type(attempt.get('version')) is not int or attempt['version']!=2 or attempt.get('sealed') is not False
                or 'active_pair' not in attempt or attempt['active_pair'] is not None
                or type(expiry) is not int or not saved['created_at']<expiry<=saved['expires_at']
                or not isinstance(pairs,dict) or not 1<=len(pairs)<=3 or not isinstance(choices,dict) or not 1<=len(choices)<=40
                or type(attempt.get('reserved_calls')) is not int or attempt['reserved_calls']!=2+2*len(pairs)
                or not isinstance(stages,dict) or set(stages)!={'start','transports','SetTransport','calcfull'}
                or any(state!='complete' for state in stages.values())
                or not isinstance(attempt.get('id'),str) or re.fullmatch('[1-9][0-9]{12}',attempt['id']) is None):
            raise ValueError('v4_cgi_history_attempt_unready')
        diagnostics=attempt.get('diagnostics',{})
        if not all(v4_cgi_history_diagnostic(diagnostics.get(stage),stage) for stage in stages):
            raise ValueError('v4_cgi_history_diagnostics')
        maps=[];refs=[]
        for ref,pair in pairs.items():
            choice=choices.get(ref)
            if (not isinstance(ref,str) or re.fullmatch('anex_quote:[a-f0-9]{64}',ref) is None
                    or not isinstance(pair,dict) or set(pair)!={'stages','diagnostics','public'}
                    or pair['stages']!={'SetTransport':'complete','calcfull':'complete'}
                    or not isinstance(choice,dict) or set(choice)!={'selection','public'}):
                raise ValueError('v4_cgi_history_pair')
            if not all(v4_cgi_history_diagnostic(pair['diagnostics'].get(stage),stage) for stage in ('SetTransport','calcfull')):
                raise ValueError('v4_cgi_history_diagnostics')
            selection=choice['selection'];display=choice['public'];public=pair['public']
            if (not isinstance(selection,dict) or len(selection)!=2
                    or any(type(route) not in (int,str) or re.fullmatch('[0-9]{1,3}',str(route)) is None
                        or not isinstance(uid,str) or not 0<len(uid.encode())<=2048 or re.search(r'[\x00-\x20\x7f]',uid)
                        for route,uid in selection.items())
                    or not isinstance(display,dict) or list(display)!=['choice_ref','legs','current']
                    or display['choice_ref']!=ref or type(display['current']) is not bool
                    or not isinstance(display['legs'],dict) or list(display['legs'])!=[0,1]
                    or any(type(k) is not int for k in display['legs'])):
                raise ValueError('v4_cgi_history_choice')
            for leg in display['legs'].values():
                if (not isinstance(leg,dict) or list(leg)!=['label'] or not isinstance(leg['label'],str)
                        or not 0<len(leg['label'].encode())<=4096 or re.search(r'[\x00-\x1f\x7f]',leg['label'])):
                    raise ValueError('v4_cgi_history_leg')
            price=public.get('price') if isinstance(public,dict) else None
            verified=public.get('verified_at') if isinstance(public,dict) else None
            if (not isinstance(price,dict) or list(price)!=['amount','currency','basis']
                    or not isinstance(price['amount'],str) or re.fullmatch(r'[0-9]{1,10}(?:\.[0-9]{1,2})?',price['amount']) is None
                    or int(price['amount'].replace('.',''))<=0 or price['currency']!='RUB'
                    or price['basis']!='supplier_gross_package' or type(verified) is not int
                    or not saved['created_at']<=verified<expiry
                    or not v4_cgi_php_same(public,{'status':'quote_verified','final_price_verified':True,
                        'selection_state':'preview_only','price':price,'choice':display,'verified_at':verified,'expires_at':expiry})):
                raise ValueError('v4_cgi_history_price')
            refs.append(ref);maps.append(selection)
        last=attempt.get('public')
        selected=last.get('choice',{}).get('choice_ref') if isinstance(last,dict) else None
        if (selected not in pairs or not v4_cgi_php_same(last,pairs[selected]['public'])
                or not all(v4_cgi_php_same(diagnostics[stage],pairs[selected]['diagnostics'][stage])
                    for stage in ('SetTransport','calcfull'))):
            raise ValueError('v4_cgi_history_public_conflict')
        binding={'generation':context['generation'],'search_ref':saved['search_ref'],'offer_ref':key,
            'offer':offer,'supplier_offer_id':known[0]['supplier_offer_id'],'currency_id':str(currency),
            'child_ages':search['child_ages']}
        hashed=v4_cgi_php_hashes(binding,maps,attempt['id'])
        if hashed['context_digest']!=attempt.get('context_digest') or hashed['pair_refs']!=refs:
            raise ValueError('v4_cgi_history_digest')
        return {'status':'verified_historical_runtime_result','amount':last['price']['amount'],'currency':'RUB',
            'basis':'supplier_gross_package','verified_at':last['verified_at'],'expires_at':expiry,
            'legs':[{'label':leg['label']} for leg in last['choice']['legs'].values()],
            'party':{'adults':2,'children':0,'infants':1},'child_ages':[1],
            'historical_only':True,'current_validity':False,'proof_basis':'accepted_runtime_retained_result',
            'raw_participant_documents_retained':False}
    except Exception as error:
        reason=str(error)
        if re.fullmatch('v4_cgi_history_[a-z_]{1,64}',reason): unknown['reason']=reason
        return unknown


def v4_cgi_retained(root,serializer,actor,ns,root_identity):
    import stat
    def root_unchanged():
        v4_cgi_root_trust(root,actor,ns); st = os.lstat(root)
        if (not stat.S_ISDIR(st.st_mode) or [st.st_dev,st.st_ino,st.st_uid,st.st_gid,st.st_mode] != root_identity):
            raise ValueError('v4_cgi_root_changed')
    root_unchanged()
    strict_file_mode = v4_cgi_root_trust(root,actor,ns)
    admission = int(time.time()); entries = 0; paths = []; total = 0
    # New CGI-bound admission: only file mtime is widened; original context window stays fixed.
    with os.scandir(root) as scan:
        for entry in scan:
            entries += 1
            if entries > 4096: raise ValueError('v4_cgi_directory_bound')
            st = entry.stat(follow_symlinks=False)
            if (not ns['NAME'].fullmatch(entry.name) or st.st_uid != actor
                    or not V4_START <= st.st_mtime <= admission): continue
            if (entry.is_symlink() or not stat.S_ISREG(st.st_mode) or st.st_nlink != 1
                    or st.st_mode & 0o022 or (strict_file_mode and stat.S_IMODE(st.st_mode) != 0o600)
                    or not 0 < st.st_size <= ns['MAX_FILE']): raise ValueError('v4_cgi_candidate_contract')
            fact = (st.st_dev,st.st_ino,st.st_uid,st.st_mode,st.st_size,st.st_mtime_ns,st.st_ctime_ns)
            paths.append((pathlib.Path(entry.path),fact)); total += st.st_size
            if len(paths) > 32 or total > ns['MAX_TOTAL']: raise ValueError('v4_cgi_candidate_bound')
    matches = []; read_bytes = 0
    for path,fact in sorted(paths):
        root_unchanged()
        raw = v4_owned_read(path,min(ns['MAX_FILE'],ns['MAX_TOTAL']-read_bytes),actor,ns,
            window=False,config=True,expected_facts=fact)
        read_bytes += len(raw)
        session = ns['decode_session'](raw,serializer)
        for case in v4_target_cases(session,ns): matches.append((case,hashlib.sha256(raw).hexdigest(),path,fact,session))
    root_unchanged()
    if len(matches) != 1: raise ValueError('v4_cgi_case_missing' if not matches else 'v4_cgi_case_ambiguous')
    case,digest,matched_path,matched_facts,matched_session = matches[0]
    historical = v4_cgi_historical_gross(matched_session,ns)
    binding = {'schema_version':1,'case':'v4-20261101-2A-age1','operation_id':V4_CGI_PARENT,
        'source_sha':'6dcfb5053793caba4f353facfa7a899a30df2d17','session_file':str(matched_path),
        'serializer':serializer,'session_root':str(root),'root_identity':root_identity,
        'file_stat_fields':['dev','ino','uid','mode','size','mtime_ns','ctime_ns'],
        'file_stat':list(matched_facts),'session_sha256':digest,'read_admission_timestamp':admission,
        'original_context_window':[V4_START,V4_END]}
    binding_bytes = json.dumps(binding,sort_keys=True,separators=(',',':')).encode()
    v4_cgi_write(op/'v4-case-binding.json',binding_bytes)
    return {'status':'retained_case_matched','binding_record_saved':True,
        'binding_record_sha256':hashlib.sha256(binding_bytes).hexdigest(),'case':'v4-20261101-2A-age1','retained_outcome':case,
        'session_sha256':digest,'session_bytes_read':read_bytes,'candidate_files':len(paths),
        'read_admission_timestamp':admission,'file_mtime_window':[V4_START,admission],
        'original_context_window':[V4_START,V4_END],'full_price_verified_current':False,'historical_gross':historical}

def v4_cgi_reason(error):
    if isinstance(error,PermissionError): return 'retained_permission_denied'
    if isinstance(error,FileNotFoundError): return 'retained_path_unavailable'
    reason = str(error)
    if re.fullmatch(r'v4_cgi_[a-z_]{1,64}',reason): return reason
    return 'v4_cgi_guard_hold'

def v4_cgi_readback():
    import secrets,stat
    actor = os.geteuid(); ns = v4_cgi_ns()
    report = {'status':'hold','stage':'runtime_pin','actor_escalation':False,'script_supplier_intents':0,
        'script_session_open_intents':0,'cgi_startup_side_effects':'unverified','historical_gross':None,
        'full_price_verified_current':False,'runtime_probe_writes':0,'api_restored':False}
    parent = op; api = runtime/'api-anex-search3-preview.php'; temp = None; staged = None; intent_written = False
    try:
        ns['no_links'](parent)
        facts = os.lstat(parent)
        if not stat.S_ISDIR(facts.st_mode) or facts.st_uid != actor or facts.st_mode&0o777 != 0o700:
            raise ValueError('v4_cgi_private_scope')
        original = v4_cgi_read(api,actor,ns)
        if hashlib.sha256(original).hexdigest() != V4_CGI_API_SHA or not original.startswith(V4_CGI_HEADER):
            raise ValueError('v4_cgi_runtime_api_pin')
        prior = v4_cgi_identity(api,actor,ns); v4_cgi_dependencies(actor,ns)
        nonce = secrets.token_hex(32); nonce_hash = hashlib.sha256(nonce.encode()).hexdigest(); deadline = int(time.time())+60
        v4_cgi_write(parent/'cgi-api-original.php',original)
        v4_cgi_write(parent/'cgi-gate',('OPEN\n'+nonce_hash+'\n').encode())
        gate_stat = os.lstat(parent/'cgi-gate')
        spec = {'operation_id':V4_CGI_PARENT,'nonce_hash':nonce_hash,'uid':actor,'deadline':deadline,'script':str(api),
            'gate':str(parent/'cgi-gate'),'metadata':str(parent/'cgi-metadata.json'),
            'gate_identity':[gate_stat.st_dev,gate_stat.st_ino,gate_stat.st_uid,gate_stat.st_gid,gate_stat.st_mode],
            'config':str(runtime/'.anex-private.php'),'bootstrap':str(home/'.anytoour-anex/search3-preview.php')}
        branch = V4_CGI_PHP.replace('@@SPEC@@',base64.b64encode(json.dumps(spec,separators=(',',':')).encode()).decode()).encode()
        wrapper = V4_CGI_HEADER+branch+original[len(V4_CGI_HEADER):]
        temp = stage_target_bytes(parent/'cgi-wrapper.php',wrapper,prior[4]&0o777)
        staged = v4_cgi_identity(temp,actor,ns)
        if staged[0] != prior[0] or staged[2:5] != prior[2:5]: raise ValueError('v4_cgi_stage_group')
        v4_cgi_sync_file(temp,staged,actor,ns)
        lint = subprocess.run(['php','-l',str(temp)],capture_output=True,timeout=20)
        if lint.returncode: raise ValueError('v4_cgi_probe_lint')
        intent = {'schema_version':1,'parent_operation':V4_CGI_PARENT,'source_sha':source,'original_sha256':V4_CGI_API_SHA,
            'wrapper_sha256':hashlib.sha256(wrapper).hexdigest(),'wrapper_identity':staged,
            'original_identity':prior,'nonce_hash':nonce_hash,'deadline':deadline}
        v4_cgi_write(parent/'cgi-intent.json',json.dumps(intent,sort_keys=True,separators=(',',':')).encode())
        intent_written = True; report['stage'] = 'metadata_probe'
        if v4_cgi_identity(api,actor,ns) != prior: raise ValueError('v4_cgi_activation_drift')
        os.replace(temp,api); temp = None; report['runtime_probe_writes'] = 1
        v4_cgi_sync_dirs(parent,api.parent)
        if not v4_cgi_same_staged(v4_cgi_identity(api,actor,ns),staged): raise ValueError('v4_cgi_activation_drift')
        time.sleep(3)  # One settle before the sole POST; never retry or change OPcache settings.
        if time.time()+15 > deadline: raise ValueError('v4_cgi_probe_deadline')
        v4_cgi_http(nonce)
    except Exception as error: report['reason'] = v4_cgi_reason(error)
    finally:
        if intent_written:
            try:
                restored = v4_cgi_restore(parent,actor,ns); report.update(api_restored=True,gate_closed=True)
                report['runtime_probe_writes'] += restored['status'] == 'restored'
            except Exception as error:
                report.update(reason=v4_cgi_reason(error),api_restored=False,restore_only_required=True)
        if temp is not None:
            try: v4_cgi_cleanup_temp(temp,staged,actor,ns)
            except Exception as error: report.update(reason=v4_cgi_reason(error),temporary_cleanup_failed=True)
    if 'reason' in report or not report['api_restored']: return report
    try:
        report['stage'] = 'runtime_metadata'
        intent = v4_cgi_load_intent(parent,actor,ns)
        root,serializer,root_identity = v4_cgi_metadata(parent,intent,actor,ns)
        report.update(cgi_startup_side_effects='none_observed',runtime_metadata='validated',
            session_root_sha256=hashlib.sha256(str(root).encode()).hexdigest(),serializer=serializer)
        report['stage'] = 'exact_retained_case'
        report.update(v4_cgi_retained(root,serializer,actor,ns,root_identity))
    except Exception as error: report['reason'] = v4_cgi_reason(error)
    return report

def v4_cgi_restore_only():
    report = {'status':'hold','actor_escalation':False,'session_content_reads':0,'script_supplier_intents':0}
    try:
        report.update(v4_cgi_restore(private/V4_CGI_PARENT,os.geteuid(),v4_cgi_ns()))
        report['status'] = 'restored_only'; report['full_price_verified_current'] = False
    except Exception as error: report['reason'] = v4_cgi_reason(error)
    return report
'''

REMOTE_V4_CGI_DISPATCH = r'''    if mode in ('anex-v4-cgi-retained-readback','anex-v4-cgi-restore-only'):
        expected_operation = 'int-anex-v4-cgi-retained-20261110-v1' if mode=='anex-v4-cgi-retained-readback' else 'int-anex-v4-cgi-restore-20261110-v1'
        if operation!=expected_operation or source!='6dcfb5053793caba4f353facfa7a899a30df2d17':fail('v4_cgi_sealed_scope')
        result['anex_v4_cgi_readback']=v4_cgi_readback() if mode=='anex-v4-cgi-retained-readback' else v4_cgi_restore_only()
        safe = mode=='anex-v4-cgi-restore-only' or result['anex_v4_cgi_readback'].get('cgi_startup_side_effects')=='none_observed'
        result.update(supplier_calls=0 if safe else 'unknown',database_reads=0 if safe else 'unknown',database_writes=0 if safe else 'unknown')
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''


def remote_with_v4_cgi_readback(core,mode):
    remote=core.REMOTE;definition='def local_read(scopes):\n';dispatch="    if mode=='local-readback':\n";collectors="    if mode not in ('reconcile',"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collectors)==2,'v4_cgi_registration_source_drift')
    reader=REMOTE_V4_READER.replace('@@V4_PARSER@@',repr(RIA_PINNED_READER_SOURCE))
    remote=remote.replace(definition,reader+REMOTE_V4_CGI_READER+definition,1)
    remote=remote.replace(dispatch,REMOTE_V4_CGI_DISPATCH+dispatch,1)
    remote=remote.replace(collectors,"    if mode not in ('"+mode+"','reconcile',")
    ast.parse(remote);return remote


# Original-attempt continuation: no probe, gate mutation, API mutation or replay.
REMOTE_V4_CGI_CONT_READER = r'''
V4_CGI_CONT_OPERATION = 'int-anex-v4-cgi-retained-continuation-20261110-v1'
V4_CGI_CONT_PARENT_SHA = '281c0165c099af06ab58ab9f498da63fb73bdd46273e11c1ff6a2c1ae2cf5816'

def v4_cgi_continuation_readback():
    import stat
    actor=os.geteuid(); ns=v4_cgi_ns(); parent=private/V4_CGI_PARENT
    report={'status':'hold','stage':'parent_receipt','metadata_parent_operation_id':V4_CGI_PARENT,
        'own_http_requests':0,'own_api_writes':0,'own_supplier_requests':0,'own_database_operations':0,
        'own_session_writes':0,'actor_escalation':False,'retained_read_invoked':False,
        'parent_supplier_calls':'unknown','parent_database_writes':'unknown',
        'parent_cgi_startup_side_effects':'unverified','full_price_verified_current':False,'historical_gross':None}
    real_read=v4_cgi_read; real_write=v4_cgi_write; cache={}; binding_digest=None; binding_writes=0
    limits={'reservation.json':16384,'cgi-intent.json':16384,'result.json':16384,
        'cgi-gate':128,'cgi-api-original.php':262144,'cgi-metadata.json':16384}
    fixed={parent/name:limit for name,limit in limits.items()}
    def read_once(path,owner,parser,maximum=262144,permissions=None):
        if path not in fixed: return real_read(path,owner,parser,maximum,permissions)
        if owner!=actor or parser is not ns or maximum>fixed[path] or permissions!=0o600:
            raise ValueError('v4_cgi_cont_parent_read_scope')
        if path not in cache:
            identity=v4_cgi_identity(path,actor,ns,fixed[path],0o600)
            raw=real_read(path,actor,ns,fixed[path],0o600)
            if v4_cgi_identity(path,actor,ns,fixed[path],0o600)!=identity:
                raise ValueError('v4_cgi_cont_parent_changed')
            cache[path]=(raw,identity)
        elif v4_cgi_identity(path,actor,ns,fixed[path],0o600)!=cache[path][1]:
            raise ValueError('v4_cgi_cont_parent_changed')
        return cache[path][0]
    def parent_unchanged():
        for path,(_,identity) in cache.items():
            if v4_cgi_identity(path,actor,ns,fixed[path],0o600)!=identity:
                raise ValueError('v4_cgi_cont_parent_changed')
    def write_binding(path,raw):
        nonlocal binding_digest,binding_writes
        if path!=op/'v4-case-binding.json' or binding_writes:
            raise ValueError('v4_cgi_cont_write_scope')
        value=json.loads(raw)
        if (not isinstance(value,dict) or value.get('operation_id')!=V4_CGI_PARENT
                or value.get('case')!='v4-20261101-2A-age1'
                or value.get('source_sha')!='6dcfb5053793caba4f353facfa7a899a30df2d17'):
            raise ValueError('v4_cgi_cont_binding_shape')
        value.update(operation_id=V4_CGI_CONT_OPERATION,metadata_parent_operation_id=V4_CGI_PARENT,
            metadata_parent_result_sha256=V4_CGI_CONT_PARENT_SHA,
            metadata_sha256=hashlib.sha256(cache[parent/'cgi-metadata.json'][0]).hexdigest(),
            read_admission_basis='original_cgi_metadata_first_content_read')
        encoded=json.dumps(value,sort_keys=True,separators=(',',':')).encode()
        real_write(path,encoded);binding_writes+=1;binding_digest=hashlib.sha256(encoded).hexdigest()
    globals()['v4_cgi_read']=read_once
    globals()['v4_cgi_write']=write_binding
    try:
        ns['no_links'](private)
        trust=os.lstat(private)
        if not stat.S_ISDIR(trust.st_mode) or trust.st_uid!=actor or trust.st_mode&0o777!=0o700:
            raise ValueError('v4_cgi_cont_private_scope')
        intent=v4_cgi_load_intent(parent,actor,ns)
        result=json.loads(read_once(parent/'result.json',actor,ns,16384,0o600))
        canonical=json.dumps(result,sort_keys=True,separators=(',',':')).encode()
        if hashlib.sha256(canonical).hexdigest()!=V4_CGI_CONT_PARENT_SHA:
            raise ValueError('v4_cgi_cont_parent_receipt_pin')
        inner=result.get('anex_v4_cgi_readback',{})
        if (result.get('operation_id')!=V4_CGI_PARENT or result.get('mode')!='anex-v4-cgi-retained-readback'
                or result.get('source_sha')!='6dcfb5053793caba4f353facfa7a899a30df2d17' or result.get('status')!='complete'
                or inner.get('status')!='hold' or inner.get('stage')!='metadata_probe'
                or inner.get('reason')!='v4_cgi_guard_hold' or inner.get('api_restored') is not True
                or inner.get('gate_closed') is not True or inner.get('runtime_probe_writes')!=2):
            raise ValueError('v4_cgi_cont_parent_state')
        if read_once(parent/'cgi-gate',actor,ns,128,0o600)!=b'CLOSED\n':
            raise ValueError('v4_cgi_cont_gate_not_closed')
        backup=read_once(parent/'cgi-api-original.php',actor,ns,262144,0o600)
        if hashlib.sha256(backup).hexdigest()!=V4_CGI_API_SHA:
            raise ValueError('v4_cgi_cont_backup_pin')
        api=runtime/'api-anex-search3-preview.php'
        api_identity=v4_cgi_identity(api,actor,ns)
        if api_identity[2:5]!=intent['original_identity'][2:5] or hashlib.sha256(real_read(api,actor,ns)).hexdigest()!=V4_CGI_API_SHA:
            raise ValueError('v4_cgi_cont_api_not_original')
        try: os.lstat(parent/'v4-case-binding.json')
        except FileNotFoundError: pass
        else: raise ValueError('v4_cgi_cont_parent_content_already_recorded')
        report.update(parent_receipt_verified=True,metadata_parent_result_sha256=V4_CGI_CONT_PARENT_SHA,
            api_original_verified=True,gate_closed_verified=True)
        report['stage']='retained_metadata'
        try: os.lstat(parent/'cgi-metadata.json')
        except FileNotFoundError:
            report.update(metadata_present=False,reason='v4_cgi_cont_metadata_absent')
            return report
        report['metadata_present']=True
        v4_cgi_dependencies(actor,ns)
        root,serializer,root_identity=v4_cgi_metadata(parent,intent,actor,ns)
        parent_unchanged()
        if v4_cgi_identity(api,actor,ns)!=api_identity:
            raise ValueError('v4_cgi_cont_api_changed')
        report.update(metadata_parent_sha256=hashlib.sha256(cache[parent/'cgi-metadata.json'][0]).hexdigest(),
            runtime_metadata='validated_original_capture',serializer=serializer,
            session_root_sha256=hashlib.sha256(str(root).encode()).hexdigest())
        report['stage']='first_retained_content'
        report['retained_read_invoked']=True
        retained=v4_cgi_retained(root,serializer,actor,ns,root_identity)
        parent_unchanged()
        if not retained.get('binding_record_saved') or binding_writes!=1 or binding_digest is None:
            raise ValueError('v4_cgi_cont_binding_missing')
        retained['binding_record_sha256']=binding_digest
        report.update(retained)
    except Exception as error:
        reason=str(error)
        if isinstance(error,PermissionError): reason='v4_cgi_cont_permission_denied'
        elif isinstance(error,json.JSONDecodeError): reason='v4_cgi_cont_metadata_or_parent_json_invalid'
        elif not re.fullmatch(r'v4_cgi_[a-z_]{1,64}',reason): reason='v4_cgi_cont_guard_hold'
        report['reason']=reason
    finally:
        globals()['v4_cgi_read']=real_read
        globals()['v4_cgi_write']=real_write
    return report
'''

REMOTE_V4_CGI_CONT_DISPATCH = r'''    if mode=='anex-v4-cgi-retained-continuation':
        if operation!='int-anex-v4-cgi-retained-continuation-20261110-v1' or source!='6dcfb5053793caba4f353facfa7a899a30df2d17':
            fail('v4_cgi_cont_sealed_scope')
        result.update(supplier_calls=0,database_reads=0,database_writes=0,runtime_writes=0,session_writes=0,cache_writes=0)
        result['anex_v4_cgi_continuation']=v4_cgi_continuation_readback()
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''


def remote_with_v4_cgi_continuation(core):
    remote=core.REMOTE;definition='def local_read(scopes):\n';dispatch="    if mode=='local-readback':\n";collectors="    if mode not in ('reconcile',"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collectors)==2,'v4_cgi_cont_registration_source_drift')
    reader=REMOTE_V4_READER.replace('@@V4_PARSER@@',repr(RIA_PINNED_READER_SOURCE))
    remote=remote.replace(definition,reader+REMOTE_V4_CGI_READER+REMOTE_V4_CGI_CONT_READER+definition,1)
    remote=remote.replace(dispatch,REMOTE_V4_CGI_CONT_DISPATCH+dispatch,1)
    remote=remote.replace(collectors,"    if mode not in ('"+V4_CGI_CONT_MODE+"','reconcile',")
    ast.parse(remote);return remote



# One fixed ANEX metadata-probe incident candidate; never discover log locations.
REMOTE_V4_CGI_LOG_READER = r'''
import datetime as v4log_dt
import stat as v4log_stat

V4_CGI_LOG_CHILD = 'int-anex-v4-cgi-retained-continuation-20261110-v1'
V4_CGI_LOG_CHILD_SHA = 'b67f629f2e6813baa8298d8553edb1a4d580824888b800878a388d37a80371bc'
V4_CGI_LOG_START = 1794325959
V4_CGI_LOG_END = 1794325975
V4_CGI_LOG_MAX_BYTES = 262144
V4_CGI_LOG_MAX_LINES = 2048

def v4_log_no_links(path,actor):
    for parent in reversed(path.parents):
        st=os.lstat(parent)
        sticky_root=st.st_uid==0 and st.st_mode&0o1000
        if (not v4log_stat.S_ISDIR(st.st_mode) or st.st_uid not in (0,actor)
                or st.st_mode&0o022 and not sticky_root):
            raise ValueError('v4_log_parent_contract')

def v4_log_facts(st):
    return (st.st_dev,st.st_ino,st.st_uid,st.st_gid,st.st_mode,st.st_nlink,
        st.st_size,st.st_mtime_ns,st.st_ctime_ns)

def v4_log_read(path,actor,maximum,tail=False,permissions=None):
    v4_log_no_links(path,actor)
    before=os.lstat(path)
    if (not v4log_stat.S_ISREG(before.st_mode) or before.st_uid!=actor
            or before.st_nlink!=1 or before.st_mode&0o022
            or permissions is not None and before.st_mode&0o777!=permissions
            or not tail and not 0<before.st_size<=maximum):
        raise ValueError('v4_log_file_contract')
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    try:
        with os.fdopen(fd,'rb',closefd=False) as stream:
            if v4_log_facts(os.fstat(fd))!=v4_log_facts(before):
                raise ValueError('v4_log_file_changed')
            offset=max(0,before.st_size-maximum) if tail else 0
            stream.seek(offset);raw=stream.read(min(maximum,before.st_size))
            if (len(raw)!=before.st_size-offset
                    or v4_log_facts(os.fstat(fd))!=v4_log_facts(before)
                    or v4_log_facts(os.lstat(path))!=v4_log_facts(before)):
                raise ValueError('v4_log_file_changed')
    finally: os.close(fd)
    return raw,offset>0

def v4_log_timestamp(value,zone):
    if zone in ('UTC','GMT'): tz=v4log_dt.timezone.utc
    elif zone in ('Europe/Moscow','Europe/Kaliningrad'):
        try:
            from zoneinfo import ZoneInfo
            tz=ZoneInfo(zone)
        except (ImportError,KeyError): return None
    else:
        match=re.fullmatch(r'([+-])(\d{2}):?(\d{2})',zone)
        if not match: return None
        hours,minutes=int(match[2]),int(match[3])
        if hours>23 or minutes>59: return None
        offset=v4log_dt.timedelta(hours=hours,minutes=minutes)
        tz=v4log_dt.timezone(offset if match[1]=='+' else -offset)
    try:
        return int(v4log_dt.datetime.strptime(value,'%d-%b-%Y %H:%M:%S').replace(tzinfo=tz).timestamp())
    except ValueError: return None

def v4_log_category(severity,message):
    if 'Permission denied' in message: return 'permission_denied'
    if 'open_basedir restriction in effect' in message: return 'open_basedir'
    if re.search(r'Allowed memory size of \d+ bytes exhausted',message): return 'memory_exhausted'
    if re.search(r'Maximum execution time of \d+ seconds exceeded',message): return 'execution_timeout'
    if 'failed to open stream' in message: return 'open_stream_failed'
    if 'Call to undefined function ' in message: return 'undefined_function'
    if severity=='Parse error': return 'parse_error'
    if re.match(r'Uncaught (?:Error|Exception|TypeError|RuntimeException)\b',message): return 'uncaught_error'
    return 'other_php_diagnostic'

def v4_cgi_errorlog_incident():
    actor=os.geteuid()
    report={'status':'hold','stage':'child_receipt','candidate':'project_error_log',
        'own_http_requests':0,'own_api_writes':0,'own_supplier_requests':0,'own_database_operations':0,
        'own_session_content_reads':0,'own_session_writes':0,'actor_escalation':False,
        'original_http_status':'unknown','unique_request_attribution':False,
        'original_supplier_calls':'unknown','original_cgi_startup_side_effects':'unverified',
        'full_price_verified_current':False,'candidate_read_invoked':False,
        'record_utc_window':[V4_CGI_LOG_START,V4_CGI_LOG_END]}
    try:
        child=private/V4_CGI_LOG_CHILD
        v4_log_no_links(child/'result.json',actor)
        for parent in (private,child):
            st=os.lstat(parent)
            if not v4log_stat.S_ISDIR(st.st_mode) or st.st_uid!=actor or st.st_mode&0o777!=0o700:
                raise ValueError('v4_log_private_scope')
        raw,_=v4_log_read(child/'result.json',actor,16384,permissions=0o600)
        saved=json.loads(raw)
        canonical=json.dumps(saved,sort_keys=True,separators=(',',':')).encode()
        if hashlib.sha256(canonical).hexdigest()!=V4_CGI_LOG_CHILD_SHA:
            raise ValueError('v4_log_child_receipt_pin')
        inner=saved.get('anex_v4_cgi_continuation',{})
        if (saved.get('source_sha')!='6dcfb5053793caba4f353facfa7a899a30df2d17'
                or saved.get('operation_id')!=V4_CGI_LOG_CHILD
                or saved.get('mode')!='anex-v4-cgi-retained-continuation' or saved.get('status')!='complete'
                or inner.get('reason')!='v4_cgi_cont_metadata_absent' or inner.get('metadata_present') is not False
                or inner.get('retained_read_invoked') is not False or inner.get('api_original_verified') is not True
                or inner.get('gate_closed_verified') is not True):
            raise ValueError('v4_log_child_state')
        report.update(child_receipt_verified=True,child_result_sha256=V4_CGI_LOG_CHILD_SHA)
        report['stage']='candidate_tail';candidate=project/'error_log'
        try: os.lstat(candidate)
        except FileNotFoundError:
            report.update(candidate_present=False,reason='v4_log_candidate_absent')
            return report
        report['candidate_present']=True
        report['candidate_read_invoked']=True
        raw,partial=v4_log_read(candidate,actor,V4_CGI_LOG_MAX_BYTES,tail=True)
        tail_read_bytes=len(raw)
        if partial: raw=raw.split(b'\n',1)[1] if b'\n' in raw else b''
        lines=raw.splitlines()
        if len(lines)>V4_CGI_LOG_MAX_LINES: raise ValueError('v4_log_line_bound')
        api=str(runtime/'api-anex-search3-preview.php')
        pattern=re.compile(r'^\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}) ([^\]\s]{1,64})\] PHP '
            r'(Warning|Fatal error|Parse error|Notice|Deprecated|Strict Standards|Recoverable fatal error): '
            r'(.*) in '+re.escape(api)+r'(?: on line ([1-9]\d{0,8})|:([1-9]\d{0,8}))$')
        categories={};severities={};diagnostics={};matches=0;unproven=0;undecodable=0;line_bound=0
        for rawline in lines:
            try: line=rawline.decode('utf-8')
            except UnicodeDecodeError: undecodable+=1;continue
            match=pattern.fullmatch(line)
            if not match: continue
            timestamp=v4_log_timestamp(match[1],match[2])
            if timestamp is None: unproven+=1;continue
            if not V4_CGI_LOG_START<=timestamp<V4_CGI_LOG_END: continue
            matches+=1;category=v4_log_category(match[3],match[4])
            categories[category]=categories.get(category,0)+1
            severities[match[3]]=severities.get(match[3],0)+1
            api_line=int(match[5] or match[6])
            if api_line>10000: line_bound+=1;continue
            key=(api_line,match[3],category);diagnostics[key]=diagnostics.get(key,0)+1
        report.update(status='scoped_candidate_checked',tail_bytes_read=tail_read_bytes,
            parsed_tail_bytes=len(raw),tail_lines=len(lines),undecodable_lines=undecodable,
            partial_first_line_discarded=partial,exact_api_records=matches,category_counts=categories,
            severity_counts=severities,unproven_timezone_records=unproven,
            diagnostic_counts=[{'api_line':line,'severity':severity,'category':category,'count':count}
                for (line,severity,category),count in sorted(diagnostics.items())],
            out_of_bound_api_line_records=line_bound,api_line_basis='recorded_runtime_api_line',
            coverage='bounded_candidate_tail_only',record_absence_proves_http_absence=False)
    except Exception as error:
        reason=str(error)
        if isinstance(error,PermissionError): reason='v4_log_permission_denied'
        elif isinstance(error,FileNotFoundError): reason='v4_log_required_path_absent'
        elif not re.fullmatch(r'v4_log_[a-z_]{1,64}',reason): reason='v4_log_guard_hold'
        report['reason']=reason
    return report
'''

REMOTE_V4_CGI_LOG_DISPATCH = r'''    if mode=='anex-v4-cgi-errorlog-incident':
        if operation!='int-anex-v4-cgi-errorlog-20261110-v1' or source!='6dcfb5053793caba4f353facfa7a899a30df2d17':
            fail('v4_log_sealed_scope')
        result.update(supplier_calls=0,database_reads=0,database_writes=0,runtime_writes=0,session_writes=0,cache_writes=0)
        result['anex_v4_cgi_errorlog_incident']=v4_cgi_errorlog_incident()
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''


def remote_with_v4_cgi_errorlog(core):
    remote=core.REMOTE;definition='def local_read(scopes):\n';dispatch="    if mode=='local-readback':\n";collectors="    if mode not in ('reconcile',"
    need(remote.count(definition)==1 and remote.count(dispatch)==1 and remote.count(collectors)==2,'v4_log_registration_source_drift')
    remote=remote.replace(definition,REMOTE_V4_CGI_LOG_READER+definition,1)
    remote=remote.replace(dispatch,REMOTE_V4_CGI_LOG_DISPATCH+dispatch,1)
    remote=remote.replace(collectors,"    if mode not in ('"+V4_CGI_LOG_MODE+"','reconcile',")
    ast.parse(remote);return remote
