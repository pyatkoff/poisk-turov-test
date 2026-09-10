"""Publish the latest supplier page-count continuation fix in the scoped preview."""
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
    $lock=fopen($private.'/pagination-followup.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();
    $release=$private.'/pagination-followup-'.$request['source_sha'];if(file_exists($release)||!mkdir($release,0700))throw new RuntimeException();
    $expected=['api-andromeda-search3-preview.php'=>'2db7b41a65718342900c17f46b1cf6293b22f89c1bbaab17ac8c5e8c97333f0e',
        'anex-search3-preview-v1.js'=>'5c85970f189882bde5a6ed321007f02baa1c9ab5c28a75dbd2efefa9256f8b32'];
    if(array_keys($request['files'])!==array_keys($expected))throw new RuntimeException();
    foreach($expected as $path=>$hash)if(is_link($target.'/'.$path)||hash_file('sha256',$target.'/'.$path)!==$hash)throw new RuntimeException();
    $files=[];foreach($request['files'] as $path=>$encoded){$data=base64_decode($encoded,true);if($data===false)throw new RuntimeException();$files[$path]=$data;}
    $page=file_get_contents($target.'/search-page-v2.php');$count=0;
    $files['search-page-v2.php']=str_replace('anex-search3-preview-v1.js?v=ff33b5212d7949a50fac2784ac4e11386684d576','anex-search3-preview-v1.js?v='.$request['source_sha'],$page,$count);
    if($count!==1)throw new RuntimeException();
    foreach($files as $path=>$data){
        $backups[$path]=file_get_contents($target.'/'.$path);
        if(file_put_contents($release.'/'.$path,$backups[$path])!==strlen($backups[$path]))throw new RuntimeException();
        $staged=$release.'/next-'.$path;
        if(file_put_contents($staged,$data)!==strlen($data)||!chmod($staged,0644)||!rename($staged,$target.'/'.$path))throw new RuntimeException();
        $written[]=$path;if(hash_file('sha256',$target.'/'.$path)!==hash('sha256',$data))throw new RuntimeException();
    }
    $result=['status'=>'deployed','source_sha'=>$request['source_sha'],'owner_login_required'=>false,
        'sha256'=>array_map(static function($data){return hash('sha256',$data);},$files),
        'private_credentials_unchanged'=>true,'database_changed'=>false,'production_changed'=>false];
    $result['saved_search_counts']=[];
    foreach(glob($private.'/searches/*-1.json')?:[] as $firstPath){
        $first=json_decode(file_get_contents($firstPath),true);
        if(!isset($first['store']['created_at']))continue;
        $prefix=substr($firstPath,0,-6).$first['store']['created_at'].'-';
        $pages=[1=>$first];
        foreach(glob($prefix.'*.json')?:[] as $pagePath){$state=json_decode(file_get_contents($pagePath),true);$n=$state['store']['snapshot']['page']??0;if($n)$pages[$n]=$state;}
        ksort($pages);$last=end($pages);
        $result['saved_search_counts'][]=['first_pages_count'=>$first['store']['snapshot']['pages_count']??null,'saved_pages'=>count($pages),'last_page'=>$last['store']['snapshot']['page']??null,'last_pages_count'=>$last['store']['snapshot']['pages_count']??null];
    }
    file_put_contents($release.'/manifest.json',json_encode($result));
}catch(Throwable $ignored){
    foreach(array_reverse($written) as $path)@file_put_contents($target.'/'.$path,$backups[$path]);
    $result=['status'=>'failed','rollback_attempted'=>count($written)>0];
}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result);
'''

def main():
    directory=Path(os.environ['RUNNER_TEMP'])/'andromeda-pagination-followup';directory.mkdir(exist_ok=True)
    root=Path(__file__).resolve().parents[2]
    request={'source_sha':os.environ['SOURCE_SHA'],'files':{}}
    for name in ['api-andromeda-search3-preview.php','anex-search3-preview-v1.js']:
        request['files'][name]=base64.b64encode((root/'v2'/name).read_bytes()).decode()
    save(directory/'reservation.json',{'state':'inflight','source_sha':request['source_sha']},exclusive=True)
    result=owner.ssh_php(SOURCE,request)
    save(directory/'result.json',result,exclusive=True)
    if result.get('status')!='deployed':raise ValueError('patch not confirmed; inspect before continuing')
    print(json.dumps(result))

if __name__=='__main__':main()
