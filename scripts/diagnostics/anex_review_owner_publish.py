#!/usr/bin/env python3
"""One-shot isolated owner publication. Hash-only plan, reservation before SSH."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import anex_review_storage as storage

ROOT = Path(__file__).resolve().parents[2]
PLAN = Path(__file__).with_name('anex_review_owner_publish_plan.json')
CHECKPOINT = 'anex-owner-publish-checkpoint.json'
LINK_PATHS = ('app/admin/anex-review/view.php','v2/anex-hotel-review.php')
FILES = ('app/admin/anex-review/access.php','app/admin/anex-review/service.php',
         'app/admin/anex-review/view.php','app/admin/anex-review/dossier-store.php',
         'app/admin/anex-review/owner-auth.php','app/admin/anex-review/owner-login.php',
         'app/admin/anex-review/public-cards.json','v2/anex-hotel-review.php','v2/anex-owner-login.php')

def canonical(value):
    return hashlib.sha256(json.dumps(value,sort_keys=True,separators=(',',':')).encode()).hexdigest()

def checkpoint_name(action):
    return {'repair':'anex-owner-repair-checkpoint.json','panel_links':'anex-owner-links-checkpoint.json'}.get(action,CHECKPOINT)

def prepare(directory, source, plan):
    if not re.fullmatch('[0-9a-f]{40}',source) or plan.get('action') not in ('inspect','apply','repair','panel_links'):
        raise ValueError('owner_plan_invalid')
    payload={'action':plan['action'],'source_sha':source,'files':{}}
    for name in FILES:
        p=ROOT/name
        if p.is_symlink() or not p.is_file() or p.stat().st_size>1000000:raise ValueError('owner_source_invalid')
        content=p.read_text();payload['files'][name]={'content':content,'sha256':hashlib.sha256(content.encode()).hexdigest()}
    cp_path=directory/checkpoint_name(plan['action'])
    if cp_path.with_suffix('.pending').exists():raise ValueError('owner_outcome_unknown')
    cp=json.loads(cp_path.read_bytes()) if cp_path.exists() else None
    identity=storage.execution_identity(source)
    if cp:
        if cp.get('plan_sha256')!=canonical(plan):raise ValueError('owner_checkpoint_plan_changed')
        if cp.get('state')=='completed':
            if canonical(cp.get('report'))!=cp.get('report_sha256'):raise ValueError('owner_report_digest')
            return cp,None
        if cp.get('state')!='reserved' or cp.get('execution')!=identity:raise ValueError('owner_outcome_unknown')
    restored=json.loads((directory/'anex-checkpoint-source.json').read_bytes())
    if plan['action'] in ('repair','panel_links'):
        prior=CHECKPOINT if plan['action']=='repair' else 'anex-owner-repair-checkpoint.json'
        published=json.loads((directory/prior).read_bytes())
        if published.get('state')!='completed' or canonical(published.get('report'))!=plan.get('published_report_sha256'):
            raise ValueError('owner_repair_lineage')
        payload['expected_source']=published['report']['source_sha']
        payload['expected_runtime']=published['report']['runtime_files']
        if plan['action']=='panel_links':
            delta=plan.get('allowed_delta')
            if not isinstance(delta,dict) or set(delta)!=set(LINK_PATHS):raise ValueError('owner_links_delta_paths')
            for name,file in payload['files'].items():
                if name in delta:
                    if not re.fullmatch('[0-9a-f]{64}',str(delta[name])) or file['sha256']!=delta[name] or file['sha256']==payload['expected_runtime'].get(name):raise ValueError('owner_links_delta_digest')
                elif file['sha256']!=payload['expected_runtime'].get(name):raise ValueError('owner_links_delta_not_allowed')
            payload['allowed_delta']=delta
    if plan['action']=='apply':
        if not re.fullmatch('[0-9a-f]{64}',plan.get('setup_hash','')) or restored['artifact_id']!=plan.get('bootstrap_artifact_id'):
            raise ValueError('owner_bootstrap_lineage')
        readiness=json.loads((directory/'anex-owner-publish-readiness.json').read_bytes())
        if canonical(readiness)!=plan.get('readiness_sha256') or readiness.get('status')!='ready':raise ValueError('owner_readiness_digest')
        payload.update(setup_hash=plan['setup_hash'],expected_before=readiness['before'])
    next_cp={'state':'reserved','execution':identity,'plan_sha256':canonical(plan),'payload_sha256':canonical(payload),
        'source_artifact_id':restored['artifact_id'],'manifest':{n:f['sha256'] for n,f in payload['files'].items()}}
    if cp and cp!=next_cp:raise ValueError('owner_reservation_changed')
    if plan['action'] in ('apply','repair','panel_links'):storage.write_json(cp_path,next_cp)
    storage.write_json(directory/'anex-owner-publish-reservation.json',next_cp)
    return next_cp,payload

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--prepare',action='store_true');args=parser.parse_args()
    directory=Path(os.environ['ANEX_CATALOG_ARTIFACT_DIR']);source=os.environ['GITHUB_SHA'];plan=json.loads(PLAN.read_bytes())
    if not args.prepare:
        original=json.loads((directory/'anex-owner-publish-reservation.json').read_bytes())
    cp,payload=prepare(directory,source,plan)
    if payload is None:print(json.dumps({'status':'already_published','new_ssh_calls':0}));return
    if args.prepare:print(json.dumps({'status':'reserved','action':plan['action'],'source_sha':source,'files':len(FILES)}));return
    if original!=cp:raise ValueError('owner_reservation_changed')
    cp_name=checkpoint_name(plan['action'])
    if plan['action'] in ('apply','repair','panel_links'):storage.write_json(directory/cp_name,dict(cp,state='executing'))
    report=storage.execute(payload,publish_owner=True)
    expected='published' if plan['action'] in ('apply','repair','panel_links') else 'ready'
    if report.get('status')!=expected or report.get('database_calls')!=0 or report.get('supplier_requests')!=0:
        storage.write_json(directory/'anex-owner-publish-failure.json',report);raise ValueError(report.get('reason','owner_publish_unconfirmed'))
    if plan['action'] in ('apply','repair','panel_links'):
        if report.get('source_sha')!=source or report.get('write_enabled') is not False or report.get('runtime_files')!=cp['manifest']:
            raise ValueError('owner_publish_readback')
        storage.write_json(directory/cp_name,dict(cp,state='completed',report=report,report_sha256=canonical(report)))
    report_name={'repair':'anex-owner-repair-report.json','panel_links':'anex-owner-links-report.json','apply':'anex-owner-publish-report.json'}.get(plan['action'],'anex-owner-publish-readiness.json')
    storage.write_json(directory/report_name,report)
    print(json.dumps({'report_sha256':canonical(report),**report}))

if __name__=='__main__':
    try:main()
    except Exception as error:
        reason=str(error) if isinstance(error,ValueError) and re.fullmatch('[a-z_]+',str(error)) else type(error).__name__
        print(json.dumps({'status':'failed','reason':reason,'reset_performed':False}));raise SystemExit(1)
