"""One owner-authorized server probe plus public route boundary checks; no login spoofing."""
import hashlib
import json
import os
from pathlib import Path
import urllib.request
import urllib.error
import anex_search3_owner_decisions as owner
from andromeda_hotel_candidates import save

SOURCE = r'''
error_reporting(0);ob_start();$stage='configuration';
try {
    if(PHP_SAPI!=='cli')throw new RuntimeException();
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
    $request=json_decode(file_get_contents('php://stdin'),true,16,JSON_THROW_ON_ERROR);
    $target=$root.'/_preview/search3-anex-candidate';
    $config=require $target.'/.andromeda-private.php';
    if($config['source_sha']!==$request['source_sha'])throw new RuntimeException();
    require_once $target.'/api-andromeda-search3-preview.php';
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $pdo=v2_data_db();$saved=json_decode(file_get_contents($config['catalog_path']),true,32,JSON_THROW_ON_ERROR);
    $saved['excluded_operator_ids']=$config['excluded_operator_ids'];
    $stage='price_and_projection';
    $out=anytour_andromeda_search3_run($request['search'],$pdo,$saved,$config,$request['audit_ref']);
    $budgetBefore=hash_file('sha256',dirname($config['catalog_path']).'/price-budget.json');
    $stage='saved_resume';
    $again=anytour_andromeda_search3_run($request['search'],$pdo,$saved,$config,$request['audit_ref']);
    if($out!==$again||$budgetBefore!==hash_file('sha256',dirname($config['catalog_path']).'/price-budget.json'))throw new RuntimeException();
    $result=['ok'=>true,'data'=>$out,'resume_verified'=>true,'source_sha'=>$config['source_sha']];
}catch(Throwable $ignored){$result=['ok'=>false,'stage'=>$stage];}
while(ob_get_level())ob_end_clean();echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
'''

def main():
    directory=Path(os.environ['RUNNER_TEMP'])/'andromeda-install'
    request={'source_sha':os.environ['SOURCE_SHA'],'audit_ref':'installation-'+os.environ['GITHUB_RUN_ID'],
      'search':{'generation':1,'params':{'departureId':'1','countryId':'1','dateFrom':'2026-09-18','dateTo':'2026-09-18',
       'nightsFrom':8,'nightsTo':8,'adults':2,'childs':[],'meal':'7','currency':'RUB'}}}
    save(directory/'probe-reservation.json',{'state':'inflight','request':request},exclusive=True)
    result=owner.ssh_php(SOURCE,request,maximum_bytes=4000000)
    save(directory/'live-response.json',result,exclusive=True)
    if not result.get('ok'):raise ValueError('live probe stopped at '+result.get('stage','unknown')+'; do not replay')
    data=result['data']
    if data['provider']!='andromeda' or not data['hotels'] or not result['resume_verified']:raise ValueError('live response not accepted')
    base='https://anytoour.ru/_preview/search3-anex-candidate/'
    checks=[]
    for path,body,expected in [('api-andromeda-search3-preview.php',None,405),
      ('api-andromeda-search3-preview.php',json.dumps(request['search']).encode(),403),('.andromeda-private.php',None,403)]:
        req=urllib.request.Request(base+path,data=body,headers={'Content-Type':'application/json','X-Requested-With':'AnyTourSearch3','Origin':'https://anytoour.ru'})
        try:
            with urllib.request.urlopen(req,timeout=30) as response:status=response.status
        except urllib.error.HTTPError as error:status=error.code
        checks.append({'path':path,'method':'POST' if body else 'GET','status':status})
        if status!=expected:raise ValueError('public boundary check failed')
    summary={'source_sha':request['source_sha'],'received_offers':data['received_offers'],'mapped_offers':data['mapped_offers'],
      'displayed_hotels':len(data['hotels']),'displayed_offers':sum(len(h['tours']) for h in data['hotels']),
      'operators':sorted({t['operator'] for h in data['hotels'] for t in h['tours']}),
      'pages_count':data['pages_count'],'resume_verified':True,'owner_auth_required':True,'public_checks':checks,
      'response_sha256':hashlib.sha256((directory/'live-response.json').read_bytes()).hexdigest()}
    save(directory/'live-summary.json',summary,exclusive=True)
    save(directory/'probe-reservation.json',{'state':'completed','request':request})
    print(json.dumps(summary,ensure_ascii=False))

if __name__=='__main__':main()
