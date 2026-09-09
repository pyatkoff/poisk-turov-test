"""Publish accepted upstream hotel criteria and verify one bounded ANEX hotel search."""
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
    $expected=['app/integrations/andromeda-client.php'=>'1c25d3e27ded11e52d2023d70c7e4ce0debdd1fe516f5abcf9f32e4c3d954ae4',
        'api-andromeda-search3-preview.php'=>'4c3ea5c4c7ad2c16d9506f2110015c53fa33fb4f9be245401efe578b51288cd7',
        'app/integrations/andromeda-offer-store.php'=>'cc1d40b433df7f1ca39a1e5d005b7336adab0c6c06c4a9f9b8c472c6783e94de'];
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
        $saved=json_decode(file_get_contents($config['catalog_path']),true,32,JSON_THROW_ON_ERROR);
        $saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
        $search=['generation'=>1,'andromeda_operator_ids'=>['5'],'params'=>[
            'countryId'=>'1','departureId'=>'1','dateFrom'=>'2026-09-18','dateTo'=>'2026-09-18',
            'nightsFrom'=>8,'nightsTo'=>8,'adults'=>2,'meal'=>'7','hotelIds'=>['9365']]];
        $criteria=anytour_andromeda_search3_params($search,$pdo,$saved);
        if(($criteria['HOTELS']??null)!=='416247'||($criteria['OPERATORS']??null)!=='5')throw new RuntimeException();
        $session='hotel-filter-'.$request['source_sha'];
        $data=anytour_andromeda_search3_run($search,$pdo,$saved,$config,$session);
        $budgetPath=dirname($config['catalog_path']).'/monthly-requests.json';
        $budget=is_file($budgetPath)?hash_file('sha256',$budgetPath):null;
        $again=anytour_andromeda_search3_run($search,$pdo,$saved,$config,$session);
        $tours=[];$ids=[];foreach($data['hotels'] as $hotel){$ids[]=$hotel['local_id'];foreach($hotel['tours'] as $tour)$tours[]=$tour;}
        $operators=array_values(array_unique(array_map(static function($t){return (string)$t['operator'];},$tours)));
        $result['verification']=['status'=>'checked','criteria'=>['HOTELS'=>$criteria['HOTELS'],'OPERATORS'=>$criteria['OPERATORS']],
            'page'=>$data['page'],'pages_count'=>$data['pages_count'],'received_offers'=>$data['received_offers'],
            'displayed_offers'=>count($tours),'local_hotel_ids'=>$ids,'operator_labels'=>$operators,
            'resume_equal'=>$again===$data,'resume_budget_unchanged'=>$budget===(is_file($budgetPath)?hash_file('sha256',$budgetPath):null)];
        if($again!==$data||$result['verification']['resume_budget_unchanged']!==true||count($tours)!==$data['received_offers']||array_diff($ids,[9365])||array_diff($operators,['Anex Tour']))throw new RuntimeException();
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
    for name in ['app/integrations/andromeda-client.php','v2/api-andromeda-search3-preview.php','app/integrations/andromeda-offer-store.php']:
        request['files'][name.removeprefix('v2/')]=base64.b64encode((root/name).read_bytes()).decode()
    save(directory/'reservation.json',{'state':'inflight','source_sha':request['source_sha']},exclusive=True)
    result=owner.ssh_php(SOURCE,request)
    save(directory/'result.json',result,exclusive=True)
    if result.get('status')!='deployed':raise ValueError('patch not confirmed; inspect before continuing')
    print(json.dumps(result))
    if result.get('verification',{}).get('status')!='checked':raise ValueError('live verification incomplete; do not replay')

if __name__=='__main__':main()
