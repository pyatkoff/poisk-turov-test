"""Standalone fixed-target update transaction for search3-local-candidate.

The predecessor must be the exact publisher-owned, published target named in the
request. It is retained before activation and restored byte-for-byte on rollback.
No arbitrary route or production path is accepted.
"""
from __future__ import annotations
import hashlib, json, os, re, shutil, sys, tarfile, tempfile
from contextlib import contextmanager
from pathlib import Path, PurePosixPath

ROUTE='/_preview/search3-local-candidate/'
NAME='search3-local-candidate'
REQUIRED=('index.php','search-page-v2.php','poisk-turov/index.php','assets.php','api-v2.php','lead-adapter-v2.php','lead-bridge-v1.php','lead-receiver-v1.php','lead-price-v1.php','lead-idempotency-v1.php','analytics-config.php','bundle-manifest-v1.php','config.php')
OPTIONAL=('robots.txt','sitemap.xml','.htaccess','seo-config.php','seo-launch-slice-v1.php','_preview/search3-anex-candidate/api-andromeda-search3-preview.php','_preview/search3-anex-candidate/app/integrations/andromeda-selected-offer.php')
INVARIANTS={'production_lead_delivery':False,'preview_metrika_counter':0,'production_document_root_bootstrap':False,'external_consultant_widget':False,'production_metrika_changes':False,'production_api_path':'/api-v2.php','preview_lead_path':ROUTE+'preview-lead-disabled.php'}

def need(v,r):
    if not v: raise ValueError(r)
def digest(b): return hashlib.sha256(b).hexdigest()
def json_bytes(v): return (json.dumps(v,ensure_ascii=False,sort_keys=True,separators=(',',':'))+'\n').encode()
def inventory(root):
    need(root.is_dir() and not root.is_symlink(),'invalid_inventory_root'); out={}
    for p in sorted(root.rglob('*')):
        need(not p.is_symlink() and (p.is_dir() or p.is_file()),'linked_or_special_file')
        if p.is_file(): out[p.relative_to(root).as_posix()]=digest(p.read_bytes())
    return out
def safe_name(name):
    p=PurePosixPath(name); need(bool(name) and not p.is_absolute() and '..' not in p.parts and '\\' not in name and str(p)==name and '\x00' not in name,'unsafe_archive_path'); return p
def safe_extract(archive,target):
    need(not target.exists() and not target.is_symlink(),'extraction_exists')
    with tarfile.open(archive,'r:gz') as src:
        members=src.getmembers(); seen=set(); total=0; need(len(members)<=12000,'archive_member_limit')
        for m in members:
            name=m.name.rstrip('/'); p=safe_name(name); need(p.parts[0] in ('payload','control') and name not in seen,'archive_inventory'); need(m.isdir() or m.isreg(),'archive_link_or_special'); need(m.size<=8*1024*1024,'archive_file_limit'); seen.add(name); total+=m.size
        need(total<=64*1024*1024,'archive_total_limit'); target.mkdir(mode=0o700)
        for m in members:
            dest=target.joinpath(*safe_name(m.name.rstrip('/')).parts)
            if m.isdir(): dest.mkdir(mode=0o755,parents=True,exist_ok=True)
            else:
                dest.parent.mkdir(mode=0o755,parents=True,exist_ok=True)
                with src.extractfile(m) as inp, dest.open('xb') as out: shutil.copyfileobj(inp,out)
                dest.chmod(0o644)
def validate(q):
    for k in ('source_sha','source_tree','release_sha','previous_source_sha'): need(isinstance(q.get(k),str) and re.fullmatch('[0-9a-f]{40}',q[k]),'invalid_'+k)
    for k in ('artifact_id','build_run','deploy_run'): need(type(q.get(k)) is int and 0<q[k]<10**15,'invalid_'+k)
    for k in ('archive_sha256','manifest_sha256','payload_sha256','source_ZIP_sha256'): need(isinstance(q.get(k),str) and re.fullmatch('[0-9a-f]{64}',q[k]),'invalid_'+k)
    need(q.get('attempt')==1,'no_replay'); need(q.get('operation')=='update','update_operation_required'); need(q['source_sha']!=q['previous_source_sha'],'same_source_no_update'); need(type(q.get('file_count')) is int and 0<q['file_count']<10000,'invalid_file_count')
def verify(root,q):
    validate(q); mb=(root/'control/manifest.json').read_bytes(); cb=(root/'control/payload.sha256').read_bytes(); need(digest(mb)==q['manifest_sha256'] and digest(cb)==q['payload_sha256'],'control_hash'); m=json.loads(mb)
    need(m.get('schema_version')==1 and m.get('target')=='search3-local-preview' and m.get('route')==ROUTE and m.get('source_sha')==q['source_sha'] and m.get('source_tree_sha')==q['source_tree'],'manifest_identity'); need(m.get('invariants')==INVARIANTS,'manifest_invariants')
    need(m.get('derived_from')=={'artifact_id':q['artifact_id'],'build_run':q['build_run'],'ZIP_sha256':q['source_ZIP_sha256'],'transform':'local-route-v1'},'derivation_identity')
    expected={}
    for f in m['files']:
        name=str(safe_name(f['path'])); need(name not in expected and re.fullmatch('[0-9a-f]{64}',f['sha256']),'duplicate_or_bad_hash'); need((root/'payload'/name).stat().st_size==f['size'],'file_size'); expected[name]=f['sha256']
    need(len(expected)==m['file_count']==q['file_count'],'file_count'); need(inventory(root/'payload')==expected,'payload_hashes'); need(cb==''.join(f'{expected[n]}  ./{n}\n' for n in sorted(expected)).encode(),'checksums_inventory')
    need(all(p not in expected for p in ('config.php','api.php','api-v2.php','lead-adapter.php','lead-adapter-v2.php','lead-bridge-v1.php','lead-receiver-v1.php')),'protected_payload_endpoint')
    need(all(p in expected for p in ('.htaccess','preview-lead-disabled.php','search-page-v2.php','data/hotel-details-read-v1.php','data/hotel-presentation-read-v1.php','poisk-turov/index.php')),'missing_guard_or_catalog')
    need('metrikaCounter=0' in (root/'payload/search-page-v2.php').read_text(),'counter_not_zero'); need('#^(/_preview/search3-local-candidate)(?:/|$)#' in (root/'payload/site-path-v1.php').read_text(),'wrong_route_helper'); return expected
def atomic_bytes(path,data):
    need(not path.is_symlink() and path.parent.is_dir() and not path.parent.is_symlink(),'metadata_link'); fd,name=tempfile.mkstemp(prefix='.'+path.name+'.',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as out: out.write(data); out.flush(); os.fsync(out.fileno())
        os.replace(name,path); d=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try: os.fsync(d)
        finally: os.close(d)
    finally: Path(name).unlink(missing_ok=True)

class Site:
    def __init__(self,root):
        self.root=Path(root); self.parent=self.root/'_preview'; self.target=self.parent/NAME; self.owner=self.parent/'.search3-local-owner'
        for p in (self.root,self.parent): need(p.is_dir() and not p.is_symlink(),'invalid_site_root')
        need(self.root.name=='anytoour.ru','wrong_project'); need(not self.target.is_symlink() and not self.owner.is_symlink(),'target_or_owner_link')
    @contextmanager
    def lock(self):
        p=self.parent/'.search3-local-lock'; need(not (self.parent/'.search3-site-lock').exists(),'existing_preview_busy'); p.mkdir(mode=0o700)
        try: yield
        finally: p.rmdir()
    def protected(self):
        out={}
        for name in REQUIRED+OPTIONAL:
            p=self.root/name; need(all(not self.root.joinpath(*Path(name).parts[:n]).is_symlink() for n in range(1,len(Path(name).parts)+1)),'protected_link')
            if p.exists(): need(p.is_file(),'protected_not_file'); out[name]=digest(p.read_bytes())
            else: need(name not in REQUIRED,'protected_missing'); out[name]=None
        for name in ('search3-site-candidate','search3-candidate'):
            p=self.parent/name; out['preview:'+name]=digest(json_bytes(inventory(p))) if p.exists() else None
        return out
    def snapshot(self):
        return {'protected':self.protected(),'target':digest(json_bytes(inventory(self.target))) if self.target.exists() else None,'owner':json.loads(self.owner.read_text()) if self.owner.exists() else None}
    def binding(self,q,remove=False):
        name=q['name']; nonce=q['nonce']; need(re.fullmatch(r'search3-local-update-bind-[1-9][0-9]*-[0-9a-f]{24}\.txt',name) and re.fullmatch('[0-9a-f]{64}',nonce),'binding_input'); p=self.parent/name
        if remove: need(p.is_file() and not p.is_symlink() and p.read_text()==nonce,'binding_owner'); p.unlink()
        else:
            fd=os.open(p,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o644)
            with os.fdopen(fd,'w') as out: out.write(nonce)
        return {'status':'removed' if remove else 'bound'}
    def receipt(self,q): return self.parent/('.search3-local-update-receipt-'+str(q['deploy_run']))
    def predecessor(self,q,before=None):
        before=before or self.snapshot(); owner=before.get('owner'); need(isinstance(before.get('target'),str) and re.fullmatch('[0-9a-f]{64}',before['target']),'missing_predecessor_target'); need(isinstance(owner,dict) and owner.get('status')=='published' and owner.get('source')==q['previous_source_sha'] and type(owner.get('run')) is int and owner['run']>0 and owner.get('digest')==before['target'],'not_known_published_predecessor'); return before
    def activate_update(self,q):
        validate(q); archive=Path(q['archive']); need(re.fullmatch(r'/tmp/search3-local-update\.[A-Za-z0-9_-]+\.tar\.gz',str(archive)),'upload_path'); need(archive.is_file() and not archive.is_symlink() and digest(archive.read_bytes())==q['archive_sha256'],'upload_digest'); stage=self.parent/('.search3-local-update-stage-'+str(q['deploy_run'])); receipt=self.receipt(q)
        with self.lock():
            before=self.predecessor(q,q['before']); need(self.snapshot()==before,'predecessor_changed'); need(not receipt.exists() and not receipt.is_symlink(),'previous_outcome_unknown'); receipt.mkdir(mode=0o700); atomic_bytes(receipt/'request.json',json_bytes(q)); atomic_bytes(receipt/'previous-owner.json',json_bytes(before['owner']))
            try:
                safe_extract(archive,stage); files=verify(stage,q); expected={'run':q['deploy_run'],'source':q['source_sha'],'digest':digest(json_bytes(files)),'status':'activated'}; atomic_bytes(receipt/'expected-owner.json',json_bytes(expected)); atomic_bytes(receipt/'manifest.json',(stage/'control/manifest.json').read_bytes()); need(self.snapshot()==before,'predecessor_changed')
                self.target.rename(receipt/'previous-payload'); (stage/'payload').rename(self.target); atomic_bytes(self.owner,json_bytes(expected)); need(inventory(self.target)==files and self.protected()==before['protected'],'activation_readback'); return {'status':'activated','files':len(files),'predecessor_retained':True}
            finally:
                if stage.exists() and not stage.is_symlink(): shutil.rmtree(stage)
                archive.unlink(missing_ok=True)
    def finish_update(self,q,rollback=False):
        validate(q); receipt=self.receipt(q)
        with self.lock():
            need(receipt.is_dir() and not receipt.is_symlink(),'missing_update_receipt'); need(json.loads((receipt/'request.json').read_bytes())==q,'request_changed'); before=self.predecessor(q,q['before']); need(self.protected()==before['protected'],'production_or_existing_preview_drift'); prev_owner=(receipt/'previous-owner.json').read_bytes(); need(json.loads(prev_owner)==before['owner'],'predecessor_metadata_changed'); backup=receipt/'previous-payload'; need(backup.is_dir() and not backup.is_symlink() and digest(json_bytes(inventory(backup)))==before['target'],'modified_backup'); expected=json.loads((receipt/'expected-owner.json').read_bytes()); need(expected.get('run')==q['deploy_run'] and expected.get('source')==q['source_sha'] and expected.get('status')=='activated','wrong_activation_identity'); manifest=json.loads((receipt/'manifest.json').read_bytes()); files={f['path']:f['sha256'] for f in manifest['files']}; need(expected.get('digest')==digest(json_bytes(files)),'activation_digest_changed')
            current=self.snapshot()
            if rollback:
                need(not self.target.is_symlink(),'target_link')
                if self.target.exists():
                    need(current.get('target')==expected['digest'] and current.get('owner') in (expected,{**expected,'status':'published'}),'modified_target'); retired=receipt/'rolled-back-payload'; need(not retired.exists(),'rollback_already_started'); self.target.rename(retired)
                backup.rename(self.target); atomic_bytes(self.owner,prev_owner); need(self.snapshot()==before,'rollback_readback'); result={'status':'rolled_back_to_predecessor','source_sha':q['previous_source_sha']}
            else:
                need(current.get('target')==expected['digest'] and current.get('owner')==expected,'modified_target_or_incomplete_activation'); published={**expected,'status':'published'}; atomic_bytes(self.owner,json_bytes(published)); result={'status':'published','source_sha':q['source_sha'],'previous_source_sha':q['previous_source_sha'],'route':ROUTE,'rollback_retained':True,'production_unchanged':True,'existing_preview_unchanged':True}
            need(not (receipt/'result.json').exists(),'already_finished'); atomic_bytes(receipt/'result.json',json_bytes(result)); return result

def main():
    need(len(sys.argv)==3 and len(sys.argv[2])<131072,'usage'); action=sys.argv[1]; q=json.loads(sys.argv[2]); site=Site(Path.home()/'www/anytoour.ru')
    if action=='snapshot': result=site.snapshot()
    elif action in ('bind','unbind'): result=site.binding(q,action=='unbind')
    elif action=='upload': fd,name=tempfile.mkstemp(prefix='search3-local-update.',suffix='.tar.gz',dir='/tmp'); os.close(fd); result={'archive':name}
    elif action=='activate-update': result=site.activate_update(q)
    elif action in ('complete-update','rollback-update'): result=site.finish_update(q,action=='rollback-update')
    else: raise ValueError('unknown_action')
    print(json.dumps(result,sort_keys=True))
if __name__=='__main__': main()
