#!/usr/bin/env python3
"""Accept only the 106 strict rows from the retained complete187 ANEX review."""
from __future__ import annotations
import argparse, hashlib, json
from pathlib import Path
from zipfile import ZipFile

OPERATION_ID = 'anex-1759-complete106-20260911-v2'
ARTIFACT_SHA = '1c4ad8fd32f5dfef1b52526a7050fb72c781d76fc1d2e455c72e6d09eff788bd'
REVIEW_SHA = '03a2ee906d503a967669b5e4f12a9aace4af36c1d522aec3182619e0cea32a24'
SEED_SHA = 'ba9c9e068fb9240cef81a87129e4a31ee91f9ecd79d259e5d54f2e7c016a7cd8'
REQUEST_SHA = 'cec6672929ccd3333c757be1dcc9ba1a0b0df3283d8cd529b1093a31d01e4433'
COUNT = 106
UNIQUE_TARGETS = 91
V1_OPERATION = 'anex-1759-complete106-20260910-v1'
V1_REQUEST_SHA = 'a49b81d1d0727fdab79b5f89ef73cdd697f59251be5ad4f91a59b96b8837810e'

def canonical(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'), allow_nan=False).encode()

def digest(value):
    return hashlib.sha256(canonical(value)).hexdigest()

def load(archive_path: Path):
    raw=archive_path.read_bytes()
    if hashlib.sha256(raw).hexdigest()!=ARTIFACT_SHA:
        raise ValueError('unreviewed_complete187_artifact')
    with ZipFile(archive_path) as z:
        result=json.loads(z.read('data/result.json'))
        report=json.loads(z.read('data/complete-review.json'))
        seed=json.loads(z.read('seed.json'))
    if (result.get('status')!='completed_read_only' or result.get('eligible_count')!=COUNT
        or result.get('catalog_reads')!=187 or result.get('supplier_calls')!=0 or result.get('database_writes')!=0
        or result.get('review_sha256')!=REVIEW_SHA or digest(report)!=REVIEW_SHA
        or digest(seed)!=SEED_SHA or report.get('seed_sha256')!=SEED_SHA):
        raise ValueError('complete187_result_not_verified')
    return report, seed

def request(archive_path: Path):
    report, seed=load(archive_path)
    seed_by={r['anex_hotel_id']:r for r in seed['rows']}
    rows=[]
    for row in report['rows']:
        if not row.get('eligible_not_applied'):
            continue
        if row.get('status')!='strong_candidate' or row.get('reason')!='name_country_coordinates' or row.get('candidate_set_complete') is not True:
            raise ValueError('unexpected_eligible_policy')
        best=row.get('best') or {}
        sid=seed_by.get(row['anex_hotel_id'])
        if not sid or sid['country_id']!=row['country_id'] or sid['query']['key']!=row['anex_hotel_id']:
            raise ValueError('seed_identity_mismatch')
        if not isinstance(best.get('id'),int) or best['id']<1 or best.get('latitude') is None or best.get('longitude') is None:
            raise ValueError('eligible_target_incomplete')
        rows.append({
            'anex_hotel_id':row['anex_hotel_id'],
            'catalog_hotel_id':best['id'],
            'country_id':row['country_id'],
            'target_name':best['name'],
            'target_latitude':best['latitude'],
            'target_longitude':best['longitude'],
            'query':sid['query'],
            'expected_raw_candidates_sha256':row['raw_candidates_sha256'],
            'expected_ranked_candidates_sha256':row['ranked_candidates_sha256'],
            'source_row_sha256':row['source_row_sha256'],
            'review_row_sha256':digest(row),
        })
    rows.sort(key=lambda r:r['anex_hotel_id'])
    payload={'operation_id':OPERATION_ID,'source_artifact_sha256':ARTIFACT_SHA,
             'source_review_sha256':REVIEW_SHA,'seed_sha256':SEED_SHA,
             'count':len(rows),'supplier_calls':0,'rows':rows}
    if len(rows)!=COUNT or len({r['anex_hotel_id'] for r in rows})!=COUNT or len({r['catalog_hotel_id'] for r in rows})!=UNIQUE_TARGETS:
        raise ValueError('complete106_scope_changed')
    if digest(payload)!=REQUEST_SHA:
        raise ValueError('complete106_request_changed')
    return payload

def validate_result(result, payload):
    if (result.get('status')!='imported' or result.get('operation_id')!=OPERATION_ID
        or result.get('request_sha256')!=digest(payload) or result.get('input_count')!=COUNT
        or result.get('inserted')!=COUNT or result.get('updated')!=0
        or result.get('readback_verified') is not True or result.get('previous_rows_unchanged') is not True
        or result.get('supplier_calls')!=0 or len(result.get('rows',[]))!=COUNT):
        raise ValueError('complete106_live_result_unconfirmed')
    expected={r['anex_hotel_id']:r['catalog_hotel_id'] for r in payload['rows']}
    actual={int(r['anex_hotel_id']):int(r['catalog_hotel_id']) for r in result['rows']}
    if actual!=expected or any(r.get('match_class')!='strong_candidate' for r in result['rows']):
        raise ValueError('complete106_live_pairs_mismatch')

def save(path:Path,value,exclusive=True):
    mode='x' if exclusive else 'w'
    with path.open(mode,encoding='utf-8') as f:
        json.dump(value,f,ensure_ascii=False,sort_keys=True,separators=(',',':'))
        f.write('\n')

def v2_php_source(root: Path):
    """Pin the already-tested SQL body while giving the retry its own no-replay identity."""
    raw=(root/'scripts/diagnostics/anex_complete_strong_accept.php').read_text()
    old_op=f"const ANEX_COMPLETE_OPERATION = '{V1_OPERATION}';"
    new_op=f"const ANEX_COMPLETE_OPERATION = '{OPERATION_ID}';"
    old_sha=f"const ANEX_COMPLETE_REQUEST_SHA = '{V1_REQUEST_SHA}';"
    new_sha=f"const ANEX_COMPLETE_REQUEST_SHA = '{REQUEST_SHA}';"
    if raw.count(old_op)!=1 or raw.count(old_sha)!=1 or OPERATION_ID in raw or REQUEST_SHA in raw:
        raise ValueError('complete106_php_source_changed')
    updated=raw.replace(old_op,new_op,1).replace(old_sha,new_sha,1)
    if updated.count(new_op)!=1 or updated.count(new_sha)!=1 or old_op in updated or old_sha in updated:
        raise ValueError('complete106_php_v2_substitution_failed')
    return updated.removeprefix('<?php')

def apply(archive_path:Path, receipt:Path, transport=None):
    payload=request(archive_path); h=digest(payload)
    if receipt.is_symlink(): raise ValueError('receipt_symlink')
    if receipt.exists():
        prior=json.loads(receipt.read_bytes())
        if prior.get('state')!='finalized' or prior.get('request_sha256')!=h or prior.get('result_sha256')!=digest(prior.get('result')):
            raise ValueError('reserved_or_unknown_operation_do_not_replay')
        validate_result(prior['result'],payload)
        return {'status':'already_finalized','new_database_writes':0,'saved_result':prior['result']}
    save(receipt,{'state':'reserved','operation_id':OPERATION_ID,'request_sha256':h})
    if transport is None:
        import anex_search3_owner_decisions as owner
        root=Path(__file__).resolve().parents[2]
        registry=(root/'app/integrations/anex-search-mapping-registry.php').read_text().removeprefix('<?php')
        php=v2_php_source(root)
        # The shared SSH helper deliberately permits only 64 KiB or 4 MiB responses.
        # 106 post-COMMIT readback rows require the supported bounded 4 MiB envelope.
        result=owner.ssh_php(registry+'\n'+php,payload,maximum_bytes=4000000)
    else:
        result=transport(payload)
    save(receipt.with_name(receipt.name+'.outcome.json'),result)
    validate_result(result,payload)
    save(receipt,{'state':'finalized','operation_id':OPERATION_ID,'request_sha256':h,
                  'result_sha256':digest(result),'result':result},exclusive=False)
    return result

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--archive',required=True,type=Path);p.add_argument('--output',type=Path)
    p.add_argument('--apply',action='store_true');p.add_argument('--receipt',type=Path)
    args=p.parse_args()
    if args.apply and args.receipt is None:p.error('--apply requires --receipt')
    payload=request(args.archive)
    result=apply(args.archive,args.receipt) if args.apply else {'status':'prepared_not_applied','request':payload}
    if args.output: args.output.write_text(json.dumps(result,ensure_ascii=False,indent=2,sort_keys=True)+'\n')
    print(json.dumps(result if args.apply else {'status':result['status'],'count':len(payload['rows']),
          'unique_targets':len({r['catalog_hotel_id'] for r in payload['rows']}),'request_sha256':digest(payload)},ensure_ascii=False,sort_keys=True))
if __name__=='__main__':main()
