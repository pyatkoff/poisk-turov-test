"""Publish pagination, supplier content and unresolved cards in the existing scoped search."""
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
    $lock=fopen($private.'/public-update.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException();
    $release=$private.'/pages-'.$request['source_sha'];if(file_exists($release)||!mkdir($release,0700))throw new RuntimeException();
    $expected=['api-andromeda-search3-preview.php'=>'e834ab92774a1776061250de98355d2d42452f1cdd8caaf80cdbe85394de4e93',
        'anex-search3-preview-v1.js'=>'0bbde5c35808dfef3c9e44bbcbb99286d11c27511004dae3089b2c98e4b79864',
        'app/integrations/andromeda-client.php'=>'879b844eb1d5d65873a9df770c284b564946b7376187ccbf084d13b43e388298',
        'app/integrations/andromeda-search.php'=>'0ea2434acb976d17109f515f30ac892cce4cfefc65efd3c58507ddb28e1513b1',
        'app/integrations/andromeda-offer-store.php'=>'b2d431bd687f12e409fd9e53140a3938ba04af724d1301f3d8242ea173677f1c'];
    if(array_keys($request['files'])!==array_keys($expected))throw new RuntimeException();
    foreach($expected as $path=>$hash)if(is_link($target.'/'.$path)||hash_file('sha256',$target.'/'.$path)!==$hash)throw new RuntimeException();
    $files=[];foreach($request['files'] as $path=>$encoded){$data=base64_decode($encoded,true);if($data===false)throw new RuntimeException();$files[$path]=$data;}
    $page=file_get_contents($target.'/search-page-v2.php');$count=0;
    $files['search-page-v2.php']=str_replace('anex-search3-preview-v1.js?v=64bca6e0946e55f597eda2e6d06624b642ea3437','anex-search3-preview-v1.js?v='.$request['source_sha'],$page,$count);
    if($count!==1)throw new RuntimeException();
    $entry=$files['api-andromeda-search3-preview.php'];$addon=$files['anex-search3-preview-v1.js'];$page=$files['search-page-v2.php'];
    unset($files['api-andromeda-search3-preview.php'],$files['anex-search3-preview-v1.js'],$files['search-page-v2.php']);
    $files['api-andromeda-search3-preview.php']=$entry;$files['anex-search3-preview-v1.js']=$addon;$files['search-page-v2.php']=$page;
    foreach($files as $path=>$data){
        $backups[$path]=file_get_contents($target.'/'.$path);
        if(file_put_contents($release.'/'.str_replace('/','__',$path),$backups[$path])!==strlen($backups[$path]))throw new RuntimeException();
        $staged=$release.'/next-'.str_replace('/','__',$path);
        if(file_put_contents($staged,$data)!==strlen($data)||!chmod($staged,0644)||!rename($staged,$target.'/'.$path))throw new RuntimeException();
        $written[]=$path;if(hash_file('sha256',$target.'/'.$path)!==hash('sha256',$data))throw new RuntimeException();
    }
    $result=['status'=>'deployed','pagination'=>true,'monthly_limit'=>5000000,'per_minute_limit'=>null,'unresolved_cards'=>true,'supplier_content'=>true,'source_sha'=>$request['source_sha'],'owner_login_required'=>false,
        'sha256'=>array_map(static function($data){return hash('sha256',$data);},$files),
        'private_credentials_unchanged'=>true,'database_changed'=>false,'production_changed'=>false];
    file_put_contents($release.'/manifest.json',json_encode($result));
}catch(Throwable $ignored){
    foreach(array_reverse($written) as $path)@file_put_contents($target.'/'.$path,$backups[$path]);
    $result=['status'=>'failed','rollback_attempted'=>count($written)>0];
}
if($lock){flock($lock,LOCK_UN);fclose($lock);}while(ob_get_level())ob_end_clean();echo json_encode($result);
'''

def main():
    directory=Path(os.environ['RUNNER_TEMP'])/'andromeda-pages';directory.mkdir(exist_ok=True)
    root=Path(__file__).resolve().parents[2]
    request={'source_sha':os.environ['SOURCE_SHA'],'files':{}}
    for name in ['v2/api-andromeda-search3-preview.php','v2/anex-search3-preview-v1.js','app/integrations/andromeda-client.php','app/integrations/andromeda-search.php','app/integrations/andromeda-offer-store.php']:
        request['files'][name.removeprefix('v2/')]=base64.b64encode((root/name).read_bytes()).decode()
    save(directory/'reservation.json',{'state':'inflight','source_sha':request['source_sha']},exclusive=True)
    result=owner.ssh_php(SOURCE,request)
    save(directory/'result.json',result,exclusive=True)
    if result.get('status')!='deployed':raise ValueError('patch not confirmed; inspect before continuing')
    print(json.dumps(result))

if __name__=='__main__':main()
