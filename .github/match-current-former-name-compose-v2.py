#!/usr/bin/env python3
import hashlib
import os
from pathlib import Path


def body(path: str) -> str:
    text = Path(path).read_text(encoding="utf-8")
    if text.startswith("<?php"):
        text = text[5:]
    return text.replace("declare(strict_types=1);", "", 1)


writer = body("scripts/diagnostics/hotel_match_current_missing_third_strict_accept.php")
replacements = [
    ("const M3A_OPERATION='hotel-match-current-missing-third-strict-accept-1971-20260911-v1';", f"const M3A_OPERATION='{os.environ['OPERATION_ID']}';"),
    ("const M3A_REVIEW_OPERATION='hotel-match-current-missing-third-strict-review-1971-20260911-v1';", f"const M3A_REVIEW_OPERATION='{os.environ['REVIEW_OPERATION']}';"),
    ("const M3A_REVIEW_RUN='34622237191';", f"const M3A_REVIEW_RUN='{os.environ['REVIEW_RUN']}';"),
    ("const M3A_REVIEW_ARTIFACT='10272453259';", f"const M3A_REVIEW_ARTIFACT='{os.environ['REVIEW_ARTIFACT']}';"),
    ("if(count($r)!==34)throw new RuntimeException('manifest_shape_changed');", "if(count($r)!==2)throw new RuntimeException('manifest_shape_changed');"),
    ("if($writes>34)throw new RuntimeException('write_scope_limit');", "if($writes>2)throw new RuntimeException('write_scope_limit');"),
]
for old, new in replacements:
    if writer.count(old) != 1:
        raise SystemExit("accept_shape_changed")
    writer = writer.replace(old, new, 1)

start = "$txt=<<<'DATA'\n"
end = "\nDATA;"
left = writer.find(start)
right = writer.find(end, left + len(start))
if left < 0 or right < 0:
    raise SystemExit("manifest_block_missing")
manifest = "\n".join([
    "anex|797|1|501|empire beach aqua park|EMPIRE BEACH AQUA PARK|33a87539e24b2e1a8cf88076d17fc8f50ff9358bf92dcbd68640c33bc6a47b57",
    "andromeda|106028|2|711|muang samui|MUANG SAMUI SPA RESORT|513d6bfb3751fb046e8329cc08680d3a5f6251e46ec09147cfa7da27900e4553",
])
writer = writer[: left + len(start)] + manifest + writer[right:]

key_fn = "function m3a_key($v):string{return implode(' ',m3a_tokens($v));}"
alias_fn = r"""function m3a_name_variants($v):array{$raw=trim((string)$v);if($raw==='')return[];$parts=[$raw];if(preg_match_all('/\(\s*(?:ex|ех|former(?:ly)?)\s*\.?\s*[:\-]?\s*([^()]+)\)/iu',$raw,$mm)){foreach($mm[1] as $old)if(trim((string)$old)!=='')$parts[]=trim((string)$old);$parts[]=preg_replace('/\s*\(\s*(?:ex|ех|former(?:ly)?)\s*\.?\s*[:\-]?\s*[^()]+\)\s*/iu',' ',$raw)??$raw;}$out=[];foreach($parts as $part){$part=trim((string)$part);if($part!=='')$out[$part]=true;}return array_keys($out);}"""
if writer.count(key_fn) != 1:
    raise SystemExit("key_function_shape_changed")
writer = writer.replace(key_fn, key_fn + alias_fn, 1)

old_raw = "$raw=null;foreach(array_unique($names) as $n)if(m3a_key($n)===$m['key']){$raw=(string)$n;break;}if($raw===null){$inc($skip,'strict_identity_changed');continue;}"
new_raw = "$raw=null;foreach(array_unique($names) as $n){foreach(m3a_name_variants($n) as $variant){if(m3a_key($variant)===$m['key']){$raw=(string)$n;break 2;}}}if($raw===null){$inc($skip,'strict_identity_changed');continue;}$targetCurrent=false;foreach(m3a_name_variants((string)($h['name']??'')) as $variant){if(m3a_key($variant)===$m['key']){$targetCurrent=true;break;}}if(!$targetCurrent){$inc($skip,'target_identity_changed');continue;}"
if writer.count(old_raw) != 1:
    raise SystemExit("raw_match_shape_changed")
writer = writer.replace(old_raw, new_raw, 1)

reconcile = body("scripts/diagnostics/hotel_full_catalog_reconcile.php")
review = body("scripts/diagnostics/hotel_match_current_bulk_review.php").replace(
    "if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);\nrequire_once __DIR__ . '/hotel_full_catalog_reconcile.php';\n",
    "",
    1,
)
bulk_accept = body("scripts/diagnostics/hotel_match_current_bulk_accept.php").replace(
    "if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);\nrequire_once __DIR__ . '/hotel_full_catalog_reconcile.php';\nrequire_once __DIR__ . '/hotel_match_current_bulk_review.php';\n",
    "",
    1,
)
writer = writer.replace(
    "if(!defined('FC_LIBRARY_ONLY'))define('FC_LIBRARY_ONLY',true);\nrequire_once __DIR__.'/hotel_match_current_bulk_accept.php';\n",
    "",
    1,
)
code = "<?php\ndefine('FC_LIBRARY_ONLY',true);\n" + reconcile + "\n" + review + "\n" + bulk_accept + "\n" + writer
state = Path(os.environ["STATE"])
(state / "live.php").write_text(code, encoding="utf-8")
(state / "composed_sha256.txt").write_text(hashlib.sha256(code.encode()).hexdigest() + "\n")
