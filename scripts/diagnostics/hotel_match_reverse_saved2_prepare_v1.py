from pathlib import Path
import argparse
import json,zipfile,hashlib
parser=argparse.ArgumentParser()
parser.add_argument('inputs',type=Path)
parser.add_argument('output',type=Path)
args=parser.parse_args()
P=args.output; P.mkdir(parents=True,exist_ok=True)
sha=lambda b:hashlib.sha256(b).hexdigest()
z=zipfile.ZipFile(args.inputs/'detail180.zip')
assert sha((args.inputs/'detail180.zip').read_bytes())=='a274fa78bca8c2f798596ebeb8655dfdadeb669843e1c79316fdabf6171e344a'
assert sha(z.read('server/result.json'))=='63f64b9d8c6eb2ac28fc7a1102432ea76eda97bb40e4efcf3d8d2bbc2651a5e4'
assert sha((args.inputs/'frontier.zip').read_bytes())=='7d64c6d2e21180bd47f6d574df78f6d640dc4f67ab24ce131f1241ebf042f9d5'
with zipfile.ZipFile(args.inputs/'frontier.zip') as fz:
    rb=fz.read('server/result.json')
assert sha(rb)=='8644e09a8301b3a98aced075365c0ce89488d2e09adab3d43796f3b4ced0c968'
rev=json.loads(rb); frontier={x['local_hotel_id']:x for x in rev['frontier']}
pairs=[]
for ix,hid,aid in [(74,1478,8419),(85,28460,18685)]:
 edge=json.loads(z.read(f'server/edge-{ix:03}.json')); response=z.read(f'server/response-{ix:03}.json'); raw=json.loads(response); d=raw['data']; h=d['hotel']
 assert edge['state']=='detail_identity_verified' and edge['http_status']==200 and raw['http_status']==200
 assert raw['raw_body_sha256']==edge['response_sha256']
 assert d['operator']['id']==edge['operator_id']==13 and h['id']==edge['tv_hotel_id']==hid
 assert d['id']==edge['tour_id']==edge['returned_tour_id'] and d['operatorLink']==edge['operator_link']
 assert edge['positive_hotellist_ids']==[aid] and edge['raw_hotellist_tokens']==[str(aid)]
 assert frontier[hid]['route']=='saved_tour_detail_ready'
 pairs.append({'native_anex_id':aid,'hotel_id':hid,'country_id':h['country']['id'],'hotel_name':h['name'],'operator_id':13,'tour_id':d['id'],'operator_link':d['operatorLink'],'raw_signed_tokens':edge['raw_hotellist_tokens'],'tv_hotel':{'id':h['id'],'name':h['name'],'country':h['country'],'region':h['region'],'subRegion':h['subRegion'],'category':h['category'],**h['common']},'detail_response_sha256':sha(response),'recorded_http_body_sha256':edge['response_sha256'],'source_edge_sha256':sha(z.read(f'server/edge-{ix:03}.json')),'source_request_sha256':sha(z.read(f'server/request-{ix:03}.json')),'canonical_samo_ids':list(map(str,frontier[hid]['samo_external_ids'])),'safe_to_write_now':False})
packet={'schema':'match_reverse_saved2_v1','source_artifact_id':10577689146,'source_result_sha256':'63f64b9d8c6eb2ac28fc7a1102432ea76eda97bb40e4efcf3d8d2bbc2651a5e4','frontier_artifact_id':10603032763,'frontier_result_sha256':'8644e09a8301b3a98aced075365c0ce89488d2e09adab3d43796f3b4ced0c968','pairs':pairs}
b=(json.dumps(packet,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode();(P/'input.json').write_bytes(b)
assert sha((args.inputs/'guards.zip').read_bytes())=='adc61bca5a62746adfe13abd6ecb0f51220f17bf4f06fa9a9b4c7dc6b99f8cff'
with zipfile.ZipFile(args.inputs/'guards.zip') as gz:
    full=gz.read('remote/payload/hotel_match_rolling38_final_v2.php')
assert sha(full)=='c23219e93f98a7f75060de1cbe5d849b3e9eff0e0cbde8d5f9e582184e9e84f9'
prefix=full.split(b"if(($argv[1]??'')==='--self-test')",1)[0];(P/'guards.php').write_bytes(prefix)
assert sha(b)=='68fe7d79d9bad888a621a7b9df833551f7d58ba34413257fa24e70b5a8aaa492'
assert sha(prefix)=='5e3875e9835a8de388317e0bd6ef41d76b56531596ed7fab99ea286b66a96878'
print('INPUT',sha(b),len(b),'GUARD',sha(prefix),len(prefix))
