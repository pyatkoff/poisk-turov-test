"""Publish only the checked Andromeda overlay in the existing integration preview."""
import base64
import hashlib
import json
import os
from pathlib import Path
import anex_search3_owner_decisions as owner
from andromeda_hotel_candidates import save

def main():
    root=Path(__file__).resolve().parents[2];directory=Path(os.environ['RUNNER_TEMP'])/'andromeda-install'
    directory.mkdir(exist_ok=True)
    request={'source_sha':os.environ['GITHUB_SHA'],'files':{}}
    files=['v2/api-andromeda-search3-preview.php','v2/anex-search3-preview-v1.js',
           'app/integrations/andromeda-client.php','app/integrations/andromeda-transport.php',
           'app/integrations/andromeda-normalizer.php','app/integrations/andromeda-hotel-resolver.php']
    for path in files:request['files'][path.removeprefix('v2/')]=base64.b64encode((root/path).read_bytes()).decode()
    request['previous_addon_sha256']=os.environ['PREVIOUS_ADDON_SHA256']
    saved=Path(os.environ['RUNNER_TEMP'])/'saved-catalog'
    request['catalog']={key:json.loads((saved/(key+'.json')).read_bytes()) for key in ['all','townfrom']}
    if hashlib.sha256((saved/'all.json').read_bytes()).hexdigest()!='01030bb9e23e0c87f8bed7c50628c8f56243b89f3e55a24151430766c1576641':raise ValueError('catalog changed')
    request['username']=os.environ.pop('ANDROMEDA_USERNAME');request['password']=os.environ.pop('ANDROMEDA_PASSWORD')
    state=directory/'install-state.json';save(state,{'state':'inflight','source_sha':request['source_sha']},exclusive=True)
    source=Path(__file__).with_suffix('.php').read_text().removeprefix('<?php')
    result=owner.ssh_php(source,request)
    save(directory/'install-result.json',result,exclusive=True)
    if result.get('status')!='deployed':raise ValueError('preview install unconfirmed; inspect, do not replay')
    save(state,{'state':'completed','source_sha':request['source_sha']})
    print(json.dumps(result))

if __name__=='__main__':main()
