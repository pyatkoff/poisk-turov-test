"""One-shot install, publication and saved-page backfill for unresolved Andromeda hotels."""
import base64
import hashlib
import json
import os
from pathlib import Path

from andromeda_hotel_candidates import save
import anex_search3_owner_decisions as owner

EXPECTED_ENDPOINT_SHA256 = "fa968565ffe3831beba0aaefacf2f2c7085a6c6934bcb329eb81dbf4cf15409e"

def main():
    root=Path(__file__).resolve().parents[2]
    output=Path(os.environ['RUNNER_TEMP'])/'andromeda-observations';output.mkdir(exist_ok=True)
    paths=['app/integrations/andromeda-hotel-observations.php','v2/api-andromeda-search3-preview.php']
    files={path.removeprefix('v2/'):base64.b64encode((root/path).read_bytes()).decode() for path in paths}
    request={'source_sha':os.environ['SOURCE_SHA'],'expected_endpoint_sha256':EXPECTED_ENDPOINT_SHA256,'files':files,
        'module_sha256':hashlib.sha256((root/paths[0]).read_bytes()).hexdigest(),
        'endpoint_sha256':hashlib.sha256((root/paths[1]).read_bytes()).hexdigest()}
    save(output/'reservation.json',{'state':'inflight','source_sha':request['source_sha'],'request_sha256':hashlib.sha256(json.dumps(request,sort_keys=True).encode()).hexdigest()},exclusive=True)
    php=(root/'scripts/diagnostics/andromeda-observations-install.php').read_text().removeprefix('<?php')
    result=owner.ssh_php(php,request,maximum_bytes=200000)
    save(output/'result.json',result,exclusive=True);print(json.dumps(result,ensure_ascii=False))
    if result.get('status')!='installed' or result.get('readback_verified') is not True or result.get('supplier_calls')!=0:
        raise ValueError('observation install not confirmed; do not replay')

if __name__=='__main__':main()
