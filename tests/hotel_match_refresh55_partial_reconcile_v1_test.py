#!/usr/bin/env python3
import argparse, importlib.util

ap=argparse.ArgumentParser()
ap.add_argument('--source',required=True)
ap.add_argument('--failed-zip',required=True)
ap.add_argument('--router-zip',required=True)
a=ap.parse_args()
spec=importlib.util.spec_from_file_location('m',a.source)
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
assert m.start_hash(56,[80333,69325])==m.TOUCHED_START_HASH
r=m.reconcile(a.failed_zip,a.router_zip)
assert r['completed_context_count']==46 and r['completed_not_returned_count']==5
assert r['touched_incomplete_count']==2 and r['unissued_count']==2
assert r['tourvisor_calls_accounted']==120
assert sorted(x['tv_hotel_id'] for x in r['touched_incomplete'])==[69325,80333]
assert sorted(x['tv_hotel_id'] for x in r['unissued'])==[113978,130752]
assert sum(r['operator_context_counts'].values())==46
assert r['operator_context_counts']=={'12:Pegas Touristik':1,'13:Anex':32,'89:Kazunion':2,'11:Coral':8,'25:Fun&Sun (RU)':2,'197:Xpress Travel':1}
all_rows=[]
for k in ['completed_contexts','completed_not_returned','touched_incomplete','unissued']:
    all_rows+=r[k]
assert len(all_rows)==55 and len({x['tv_hotel_id'] for x in all_rows})==55
assert all(x['safe_to_write_now'] is False for x in all_rows)
print('REFRESH55_PARTIAL_RECONCILE_REAL_INPUT_OK')
