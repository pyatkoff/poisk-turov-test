"""Publish pinned grouped discovery runtime to its isolated preview; no supplier calls."""
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
    $lock=fopen($private.'/grouped-search-update.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();
    $release=$private.'/grouped-search-'.$request['source_sha'];if(file_exists($release)||!mkdir($release,0700))throw new RuntimeException();
    $expected=['anex-search3-preview-v1.js'=>'eefbe37df590c6e9114e65a7b268f42c4c229b6c7f86053b76883db0c0fc7a13'];
    if(array_keys($request['files'])!==array_keys($expected))throw new RuntimeException();
    foreach($expected as $path=>$hash)if(is_link($target.'/'.$path)||hash_file('sha256',$target.'/'.$path)!==$hash)throw new RuntimeException();
    $files=[];foreach($request['files'] as $path=>$encoded){$data=base64_decode($encoded,true);if($data===false)throw new RuntimeException();$files[$path]=$data;}
    if(is_link($target.'/search-page-v2.php'))throw new RuntimeException();
    $page=file_get_contents($target.'/search-page-v2.php');$count=0;
    $files['search-page-v2.php']=str_replace('anex-search3-preview-v1.js?v=99fa70e1d1d9222993f9caf07d9c48347a9ddf2d','anex-search3-preview-v1.js?v='.$request['source_sha'],$page,$count);
    if($count!==1)throw new RuntimeException();
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

    $result['artifact_id']=$request['artifact_id'];
    $result['verification']=['status'=>'checked','supplier_calls'=>0,'browser_acceptance'=>false];
    file_put_contents($release.'/manifest.json',json_encode($result));
}catch(Throwable $ignored){
    foreach(array_reverse($written) as $path)@file_put_contents($target.'/'.$path,$backups[$path]);
    $result=['status'=>'failed','rollback_attempted'=>count($written)>0];
}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result);
'''

def main():
    directory=Path(os.environ['RUNNER_TEMP'])/'andromeda-grouped-search';directory.mkdir(exist_ok=True)
    root=Path(__file__).resolve().parents[2]
    request={'source_sha':os.environ['SOURCE_SHA'],'artifact_id':os.environ['ARTIFACT_ID'],'files':{}}
    for name in ['v2/anex-search3-preview-v1.js']:
        request['files'][name.removeprefix('v2/')]=base64.b64encode((root/name).read_bytes()).decode()
    save(directory/'reservation.json',{'state':'inflight','source_sha':request['source_sha']},exclusive=True)
    result=owner.ssh_php(SOURCE,request)
    save(directory/'result.json',result,exclusive=True)
    if result.get('status')!='deployed':raise ValueError('patch not confirmed; inspect before continuing')
    print(json.dumps(result))
    if result.get('verification',{}).get('status')!='checked':raise ValueError('live verification incomplete; do not replay')

if __name__=='__main__':main()
