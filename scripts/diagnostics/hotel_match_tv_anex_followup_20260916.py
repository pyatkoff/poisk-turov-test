#!/usr/bin/env python3
"""Build one NEW bounded MATCH read, preserving the checked Tourvisor contract."""
from __future__ import annotations
import argparse
import hashlib
import re
from pathlib import Path

BASE_BLOB = 'f421ccbc9817cbf878e0c3d6a3b437f8227cc54b'
OP = 'hotel-match-tv-anex-live-1971-20260916-v2'
PAIRS = {23894:1600,28882:43528,32880:17443,44573:59085,35275:53523,
         16605:53527,44939:157227,32692:70291,44413:101819,32572:17483,
         37885:111046,32686:17390,32355:60358,33110:17403,39389:1557,
         43077:1558,20470:59441,34858:60766,34961:81120}
PRIOR_GUARD = r'''
  // Read completed evidence, never execute the completed operation again.
  $priorOp='hotel-match-tv-anex-live-1971-20260916-v1';
  $priorHash='6d5b18584b31ac3b63774e547e5db2553d09aa8a2f31a7db18897a3ba4a4c76c';
  $priorPath=$base.'/'.$priorOp.'/result.json';
  tal_require(hash_file('sha256',$priorPath)===$priorHash,'previous_live_hash');
  $prior=tal_read($priorPath);$pr=tal_read($base.'/'.$priorOp.'/receipt.json');
  tal_require(($prior['operation_id']??'')===$priorOp&&($pr['operation_id']??'')===$priorOp&&($pr['result_sha256']??'')===$priorHash&&($pr['readback_verified']??false)===true&&($prior['state']??'')==='completed_read_only','previous_live_receipt');
  tal_require(($prior['source_sha']??'')==='ed3873cebc813f3c6ecb4ad1004b62bc40702407'&&($prior['tourvisor_http_attempts']??null)===21,'previous_live_budget');
  foreach($prior['rows']as$r){$native=$r['native_link']['anex_hotel_id']??null;$local=$r['local_hotel_id']??null;if(tal_id($native)&&tal_id($local))$proved[$native.':'.$local]=true;}
  // Conservative rolling window; do not invent provider billing/reset metadata.
  $legacy=0;$legacyOps=[];
  foreach($pf['retained_account_telemetry']['operations']as$old){
   if(!preg_match('/^hotel-match-old-tv-hotellist-mass-1971-20260916-v(?:19|20|21|22|23|24|25)-(?:turkey|egypt)-provider$/D',$old['operation_id']))continue;
   $path=$base.'/'.$old['operation_id'].'/result.json';
   tal_require(($old['verified_terminal_receipt']??false)===true&&hash_file('sha256',$path)===($old['sha256']['result.json']??null),'historical_budget_receipt');
   $saved=tal_read($path);$n=$saved['tourvisor_calls']??null;
   tal_require(is_int($n)&&$n>=0&&$n<=39&&!isset($legacyOps[$old['operation_id']]),'historical_budget_counter');
   $legacy+=$n;$legacyOps[$old['operation_id']]=true;
  }
  tal_require(count($legacyOps)===7&&$legacy===63&&$legacy*4+21+TAL_CAP<=300,'conservative_budget');
  $cutoff=strtotime($pf['read_at_utc']);tal_require($cutoff!==false,'preflight_time');
  $dirs=glob($base.'/*',GLOB_ONLYDIR);tal_require(is_array($dirs)&&count($dirs)<20000,'operation_inventory_bound');
  foreach($dirs as$other){$name=basename($other);
   if($name===TAL_OP||$name===TAL_PREFLIGHT||$name===$priorOp||!preg_match('/(?:tv|tourvisor)/i',$name))continue;
   $path=$other.'/result.json';$reservation=$other.'/reservation.json';
   $modified=max(is_file($path)?(int)filemtime($path):0,is_file($reservation)?(int)filemtime($reservation):0);
   if($modified<=$cutoff)continue;
   tal_require(is_file($path)&&is_file($other.'/receipt.json'),'intervening_tv_operation_unknown');
   $otherResult=tal_read($path);$otherReceipt=tal_read($other.'/receipt.json');
   tal_require(($otherReceipt['result_sha256']??'')===hash_file('sha256',$path)&&($otherReceipt['readback_verified']??false)===true,'intervening_tv_receipt_unknown');
   $n=$otherResult['tourvisor_http_attempts']??$otherResult['tourvisor_calls']??null;
   tal_require(is_int($n)&&$n===0,'intervening_tv_budget_requires_review');
  }
  $out['budget_basis']=['known_prior_client_invocations'=>63,'legacy_max_attempts_per_call'=>4,'previous_exact_http_attempts'=>21,'this_http_attempt_cap'=>TAL_CAP,'known_window_upper_bound'=>63*4+21+TAL_CAP,'provider_daily_remaining'=>null,'not_provider_billing_counter'=>true];
'''


def replace_once(source: str, old: str, new: str) -> str:
    if source.count(old) != 1:
        raise ValueError('source_dependency_changed: ' + old[:70])
    return source.replace(old, new, 1)


def build(raw: bytes) -> bytes:
    blob = hashlib.sha1(b'blob ' + str(len(raw)).encode() + b'\0' + raw).hexdigest()
    if blob != BASE_BLOB:
        raise ValueError('source_blob_mismatch')
    source = raw.decode('utf-8')
    protected_start = source.index('function tal_call(')
    protected_end = source.index('function tal_main(')
    protected_http = source[protected_start:protected_end]
    source = replace_once(source, "const TAL_OP='hotel-match-tv-anex-live-1971-20260916-v1';", f"const TAL_OP='{OP}';")
    source = replace_once(source, "const TAL_DATE='2026-10-16';", "const TAL_DATE='2026-10-23';")
    source = replace_once(source, 'const TAL_CAP=40;', 'const TAL_CAP=27;')
    old = re.search(r'^const TAL_PAIRS=\[.*\];$', source, re.M)
    if old is None or len(PAIRS)!=19 or len(set(PAIRS.values()))!=19:
        raise ValueError('pair_contract')
    source = replace_once(source, old.group(), 'const TAL_PAIRS=[' + ','.join(f'{a}=>{b}' for a,b in PAIRS.items()) + '];')
    source = replace_once(source, '$ok(count(TAL_PAIRS)===25&&count(array_unique(TAL_PAIRS))===25);', '$ok(count(TAL_PAIRS)===19&&count(array_unique(TAL_PAIRS))===19);')
    marker = "  $cfg=is_file($root.'/v2/config.php')?"
    source = replace_once(source, marker, PRIOR_GUARD + '\n' + marker)
    # The paid batch stays narrow even if this generator is accidentally broadened.
    source = replace_once(source, "  $db->exec('ROLLBACK');$out['current_read_at_utc']", "  tal_require((!$batches||array_keys($batches)===[4])&&(!isset($batches[4])||count($batches[4])<=19),'selected_country_scope');\n  $db->exec('ROLLBACK');$out['current_read_at_utc']")
    marker = "   $out['batches'][]=['country_id'=>$cid,'search_id'=>$sid,'target_count'=>count($ids),'flattened_rows'"
    ordered = "   $priority=[];foreach($targets as$t)$priority[$t['local_hotel_id']]=$t['search_count'];usort($selected,static fn($a,$b)=>($priority[$b['local_hotel_id']]??0)<=>($priority[$a['local_hotel_id']]??0));\n"
    source = replace_once(source, marker, ordered + marker)
    if source[source.index('function tal_call('):source.index('function tal_main(')] != protected_http:
        raise ValueError('http_contract_changed')
    if re.search(r'\b(?:INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|TRUNCATE)\s',source,re.I):
        raise ValueError('not_read_only')
    for text in ['START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY', 'CURLOPT_FOLLOWLOCATION=>false', "if($status===429)throw new RuntimeException('rate_limited')", "getenv('MATCH_OPERATION_ID')===TAL_OP"]:
        if text not in source:
            raise ValueError('guard_missing')
    if source.count('curl_exec(')!=1 or source.count('tal_main();')!=1:
        raise ValueError('execution_contract')
    return source.encode('utf-8')


def main() -> None:
    ap=argparse.ArgumentParser(description=__doc__)
    ap.add_argument('source',type=Path)
    ap.add_argument('output',type=Path)
    args=ap.parse_args()
    raw=build(args.source.read_bytes())
    with args.output.open('xb') as f:
        if f.write(raw)!=len(raw):
            raise OSError('short_write')
    if args.output.read_bytes()!=raw:
        raise OSError('output_readback')
    print(hashlib.sha256(raw).hexdigest())

if __name__=='__main__':
    main()
