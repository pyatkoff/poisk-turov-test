#!/usr/bin/env python3
"""Build a pinned, supplier-free CURRENT residual review; no acceptance authority."""
import hashlib,json,re,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
OP='hotel-match-core8-residual-current-1971-20260915-v1'
PINS={'hotel_match_live_residual_current_review.php': '39092786a76de75be1746975b9d113352b0960bf2a3816f093250ec1610ee5a3', 'hotel_match_state_country_consensus.php': '2e1988ee3a5e44207fc3a253977d5149e65a76734860acf1b0f24fb45ba666da', 'hotel_match_state_accepted_country_consensus.php': '0f904b79a271051e4f28bdd0e05c2b79c16fed2832dd619ed2221c74feea1a84', 'hotel_match_provider_geo_consensus_review.php': '6bb9a2faaac6af92ffebcc3af63bd6cff622f738d449803e575459a8697649d8'}
REPORT='reports/hotel-match-received-union-current-20260915.json'
REPORT_SHA='d9a9a9cbbb8b4d3f77600c309fac8aa4334ebcb0812888725504c580bbdba8c6'
def once(s,a,b):
    if s.count(a)!=1: raise ValueError('source fragment count: '+a[:80])
    return s.replace(a,b,1)
def build():
    raw=(ROOT/REPORT).read_bytes()
    assert hashlib.sha256(raw).hexdigest()==REPORT_SHA
    report=json.loads(raw)
    excluded=sorted({str(r['fact']['andromeda_hotel_id']) for k in ['candidates','unresolved_anchors'] for r in report[k]})
    assert excluded and all(re.fullmatch(r'[0-9]+',v) for v in excluded)
    parts=[]
    for i,(name,digest) in enumerate(PINS.items()):
        raw=(ROOT/'scripts/diagnostics'/name).read_bytes()
        assert hashlib.sha256(raw).hexdigest()==digest
        s=raw.decode();s=once(s,'<?php\ndeclare(strict_types=1);','')
        if i<3:
            s=s.split("if(getenv(",1)[0] if i!=2 else s.split("if (getenv(",1)[0]
        s=re.sub(r"^putenv\([^\n]+\);\n",'',s,flags=re.M)
        s=re.sub(r"^require_once __DIR__[^\n]+\n",'',s,flags=re.M)
        if i==3:
            s=once(s,"const MPG_OP = 'hotel-match-provider-geo-consensus-review-1971-20260915-v1';","const MPG_OP = '"+OP+"';")
            s=once(s,"if(getenv('MATCH_PROVIDER_GEO_TEST_LIBRARY')==='1')return;",'')
            start="$routes=[];$candidates=[];$reasons=[];$withGeo=0;$freq=0;"
            inject="""$totalPending=count($pending);$excluded=array_fill_keys(__EXCLUDED__,true);$excludedCurrent=[];
 $pending=array_values(array_filter($pending,function($r)use($excluded,&$excludedCurrent){$id=(string)$r['external_hotel_id'];if(isset($excluded[$id])){$excludedCurrent[]=$id;return false;}return true;}));
 $live=[];foreach(mpg_query($db,"SELECT external_hotel_id,COUNT(*) AS n FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' GROUP BY external_hotel_id") as $o)$live[(string)$o['external_hotel_id']]=(int)$o['n'];
 $routes=[];$candidates=[];$reasons=[];$withGeo=0;$freq=0;""".replace('__EXCLUDED__','['+','.join("'"+v+"'" for v in excluded)+']')
            s=once(s,start,inject)
            s=once(s,"if($sk===null||!isset($country['inferred'][$sk]))continue;$cid=(int)$country['inferred'][$sk]['country_id'];","$cid=($sk!==null&&isset($country['inferred'][$sk]))?(int)$country['inferred'][$sk]['country_id']:0;")
            s=once(s,"'frequency'=>mcr_frequency($e),'geo_anchors'","'frequency'=>$live[(string)$r['external_hotel_id']]??0,'names'=>mcr_names($e),'points'=>mcr_points($e),'geo_keys'=>mpg_geo_keys($e),'geo_anchors'")
            s=once(s,"'schema'=>'hotel-match-provider-geo-consensus-review/1'","'schema'=>'hotel-match-core8-residual-current/1'")
            s=once(s,"'candidate_count'=>count($candidates)","'total_pending_before_exclusion'=>$totalPending,'excluded_current_ids'=>$excludedCurrent,'excluded_claim_ids'=>array_keys($excluded),'routes'=>$routes,'local_hotels'=>$hotels,'local_alias_forms'=>$forms,'country_consensus'=>$country,'prepared_only'=>true,'accepted_mappings'=>0,'candidate_count'=>count($candidates)")
        parts.append(s)
    bundle='<?php\ndeclare(strict_types=1);\n'+'\n'.join(parts)
    assert 'START TRANSACTION READ ONLY' in bundle
    assert not re.search(r'(?<![$\w])(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\s',bundle,re.I)
    assert '__DIR__' not in bundle
    assert bundle.count('START TRANSACTION READ ONLY')==1
    return bundle,excluded
if __name__=='__main__':
    bundle,excluded=build()
    Path(sys.argv[1]).write_text(bundle)
    print(json.dumps({'operation_id':OP,'excluded_unique_ids':len(excluded),'bundle_sha256':hashlib.sha256(bundle.encode()).hexdigest(),'prepared_only':True}))
