"""Publish unresolved supplier-category filtering and verify saved pages without supplier calls."""
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
    $expected=['api-andromeda-search3-preview.php'=>'4c3ea5c4c7ad2c16d9506f2110015c53fa33fb4f9be245401efe578b51288cd7'];
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
    // The private release fence above prevents replay of this new live verification.
    try {
        require_once $target.'/api-andromeda-search3-preview.php';
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $pdo=v2_data_db();$config=require $target.'/.andromeda-private.php';
        $files=glob(dirname($config['catalog_path']).'/searches/*.json')?:[];
        $seen=[];$retained=[];$pages=0;
        foreach($files as $path){
            if(str_ends_with($path,'-auth.json'))continue;
            $state=json_decode(file_get_contents($path),true);
            $page=$state['store']['snapshot']??null;if(!is_array($page)||!isset($page['offers'],$page['page'],$page['pages_count']))continue;
            ++$pages;
            // Apply the new projection to saved unresolved offers only; never re-run supplier search.
            $offers=array_values(array_filter($page['offers'],static function($o){return $o['local_hotel_id']===null;}));
            if(!$offers)continue;$page['offers']=$offers;
            $page['status']=$page['status']??'complete';$page['search_ref']=$page['search_ref']??'saved_category_audit';
            $p=['generation'=>1,'params'=>['countryId'=>'1','dateFrom'=>'2026-09-18','dateTo'=>'2026-09-18','hotelCategory'=>'4']];
            $out=anytour_andromeda_search3_project($p,$pdo,$page);
            foreach($offers as $offer)$seen[$offer['supplier_namespace'].':'.$offer['external_hotel_id']]=true;
            foreach($out['hotels'] as $hotel){
                if(!is_int($hotel['category'])||$hotel['category']<4||$hotel['category']>5||$hotel['local_id']!==null)throw new RuntimeException();
                $retained[$hotel['card_key']]=['name'=>$hotel['name'],'category'=>$hotel['category']];
            }
        }
        if($pages===0)throw new RuntimeException();
        $result['verification']=['status'=>'checked','saved_pages'=>$pages,'unresolved_identities'=>count($seen),
            'retained_at_4_stars'=>count($retained),'retained'=>array_values($retained),'supplier_calls'=>0,'database_writes'=>0];
    }catch(Throwable $probeError){$result['verification']['status']='failed';}
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
    for name in ['v2/api-andromeda-search3-preview.php']:
        request['files'][name.removeprefix('v2/')]=base64.b64encode((root/name).read_bytes()).decode()
    save(directory/'reservation.json',{'state':'inflight','source_sha':request['source_sha']},exclusive=True)
    result=owner.ssh_php(SOURCE,request)
    save(directory/'result.json',result,exclusive=True)
    if result.get('status')!='deployed':raise ValueError('patch not confirmed; inspect before continuing')
    print(json.dumps(result))
    if result.get('verification',{}).get('status')!='checked':raise ValueError('live verification incomplete; do not replay')

if __name__=='__main__':main()
