"""Publish saved offer detail view and verify historical snapshots without supplier calls."""
import base64
import json
import os
from pathlib import Path
import anex_search3_owner_decisions as owner
from andromeda_hotel_candidates import save

SOURCE=r'''
error_reporting(0);ob_start();$written=[];$backups=[];$lock=null;
try {
    if(PHP_SAPI!=='cli')throw new RuntimeException();
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
    $target=$root.'/_preview/search3-anex-candidate';$private=dirname($root,2).'/.anytoour-andromeda';
    $request=json_decode(file_get_contents('php://stdin'),true,16,JSON_THROW_ON_ERROR);
    if(!preg_match('/^[a-f0-9]{40}$/D',$request['source_sha']))throw new RuntimeException();
    $lock=fopen($private.'/hotel-filter-update.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();
    $release=$private.'/hotel-filter-'.$request['source_sha'];if(file_exists($release)||!mkdir($release,0700))throw new RuntimeException();
    $expected=['api-andromeda-search3-preview.php'=>'0566e1e674450394c19417b5a58f270d31bd5f673fc317f4b3e9ec42acb7ebbb','anex-search3-preview-v1.js'=>'29731e3ac19f7d5e56070ab075ab29aac8a08fd10a2572b47ae82c5434860de7'];
    if(array_keys($request['files'])!==array_keys($expected))throw new RuntimeException();
    foreach($expected as $path=>$hash)if(is_link($target.'/'.$path)||hash_file('sha256',$target.'/'.$path)!==$hash)throw new RuntimeException();
    $files=[];foreach($request['files'] as $path=>$encoded){$data=base64_decode($encoded,true);if($data===false)throw new RuntimeException();$files[$path]=$data;}
    foreach($files as $path=>$data){
        $backups[$path]=file_get_contents($target.'/'.$path);
        if(file_put_contents($release.'/'.str_replace('/','__',$path),$backups[$path])!==strlen($backups[$path]))throw new RuntimeException();
        $staged=$release.'/next-'.str_replace('/','__',$path);
        if(file_put_contents($staged,$data)!==strlen($data)||!chmod($staged,0644)||!rename($staged,$target.'/'.$path))throw new RuntimeException();
        $written[]=$path;if(hash_file('sha256',$target.'/'.$path)!==hash('sha256',$data))throw new RuntimeException();
    }
    $result=['status'=>'deployed','source_sha'=>$request['source_sha'],'owner_login_required'=>false,
        'sha256'=>array_map(static function($data){return hash('sha256',$data);},$files),
        'private_credentials_unchanged'=>true,'database_changed'=>false,'production_changed'=>false];
    file_put_contents($release.'/manifest.json',json_encode($result));

    require_once $target.'/api-andromeda-search3-preview.php';
    $budgetPath=$private.'/monthly-requests.json';$budget=hash_file('sha256',$budgetPath);
    $checked=0;$expired=0;
    foreach(glob($private.'/searches/*.json')?:[] as $path){
        if(!preg_match('/-[1-9][0-9]*\\.json$/D',$path)||filesize($path)>2200000)continue;
        $state=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
        if(!in_array($state['status']??null,['complete','partial'],true)||empty($state['store']['snapshot']['offers']))continue;
        $offer=$state['store']['snapshot']['offers'][0];
        $context=['provider'=>'andromeda','search_ref'=>$state['search_ref'],'generation'=>$state['generation'],
            'page'=>$state['store']['snapshot']['page'],'offer_ref'=>$offer['offer_ref']];
        // Historic snapshot contract check, not a fresh price or current availability claim.
        $detail=anytour_andromeda_search3_detail_state($state,$context,$state['store']['created_at']);
        if($detail['hotel']!==$offer['hotel']||$detail['price']!==$offer['price']||$detail['booking_enabled']!==false
            ||isset($detail['supplier_offer_id'],$detail['criteria']))throw new RuntimeException();
        $rejected=false;try{anytour_andromeda_search3_detail_state($state,$context,$state['store']['expires_at']);}
        catch(Throwable $expectedError){$rejected=true;}
        if(!$rejected)throw new RuntimeException();++$expired;++$checked;if($checked>=3)break;
    }
    if(!$checked||hash_file('sha256',$budgetPath)!==$budget)throw new RuntimeException();
    $result['verification']=['status'=>'checked','historic_saved_pages'=>$checked,'expired_rejected'=>$expired,
        'supplier_calls'=>0,'budget_unchanged'=>true,'browser_acceptance'=>false];
    file_put_contents($release.'/manifest.json',json_encode($result));
}catch(Throwable $ignored){
    foreach(array_reverse($written) as $path)@file_put_contents($target.'/'.$path,$backups[$path]);
    $result=['status'=>'failed','rollback_attempted'=>count($written)>0];
}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result);
'''

def main():
    directory=Path(os.environ['RUNNER_TEMP'])/'andromeda-hotel-filter';directory.mkdir(exist_ok=True)
    root=Path(__file__).resolve().parents[2]
    request={'source_sha':os.environ['SOURCE_SHA'],'files':{}}
    for name in ['v2/api-andromeda-search3-preview.php','v2/anex-search3-preview-v1.js']:
        request['files'][name.removeprefix('v2/')]=base64.b64encode((root/name).read_bytes()).decode()
    save(directory/'reservation.json',{'state':'inflight','source_sha':request['source_sha']},exclusive=True)
    result=owner.ssh_php(SOURCE,request)
    save(directory/'result.json',result,exclusive=True)
    if result.get('status')!='deployed':raise ValueError('patch not confirmed; inspect before continuing')
    print(json.dumps(result))
    if result.get('verification',{}).get('status')!='checked':raise ValueError('live verification incomplete; do not replay')

if __name__=='__main__':main()
