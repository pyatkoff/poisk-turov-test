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
    $expected=['api-andromeda-search3-preview.php'=>'ef32b70dc3d11d7a331da8ed7166119b681cb794c06c8ec47bafdf47ac99e150'];
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
        $importPath=dirname($config['catalog_path']).'/turkey-catalog-v1/import-result.json';
        $import=json_decode(file_get_contents($importPath),true,32,JSON_THROW_ON_ERROR);
        if(($import['status']??null)!=='imported'||($import['readback_verified']??false)!==true)throw new RuntimeException();
        $country=$import['country_id'];
        $search=['generation'=>1,'andromeda_operator_ids'=>['5'],'params'=>[
            'countryId'=>(string)$country,'departureId'=>'1','dateFrom'=>'2026-09-18','dateTo'=>'2026-09-18',
            'nightsFrom'=>8,'nightsTo'=>8,'adults'=>2,'meal'=>'7']];
        $saved=anytour_andromeda_search3_catalog($config,$search);
        if(hash_file('sha256',dirname($config['catalog_path']).'/countries/'.$country.'.json')!==$import['catalog_sha256'])throw new RuntimeException();
        $counts=$pdo->prepare("SELECT decision_status,COUNT(*) AS total FROM andromeda_hotel_identities WHERE catalog_sha256=? GROUP BY decision_status");
        $counts->execute([$import['catalog_sha256']]);$actual=$counts->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach($import['counts'] as $status=>$count)if((int)($actual[$status]??0)!==$count)throw new RuntimeException();
        $result['turkey_import']=$import;$result['database_readback_verified']=true;
        $criteria=anytour_andromeda_search3_params($search,$pdo,$saved);
        if($criteria['STATEINC']!==$import['supplier_country_id']||$criteria['OPERATORS']!=='5')throw new RuntimeException();
        $session='turkey-live-'.$request['source_sha'];
        $data=anytour_andromeda_search3_run($search,$pdo,$saved,$config,$session);
        $budgetPath=dirname($config['catalog_path']).'/monthly-requests.json';$budget=hash_file('sha256',$budgetPath);
        $again=anytour_andromeda_search3_run($search,$pdo,$saved,$config,$session);
        $labels=[];$mapped=0;$unresolved=0;$tours=0;
        foreach($data['hotels'] as $hotel){
            if($hotel['country']!=='Турция')throw new RuntimeException();
            $hotel['local_id']===null?++$unresolved:++$mapped;
            foreach($hotel['tours'] as $tour){++$tours;$labels[(string)$tour['operator']]=true;}
        }
        if($again!==$data||hash_file('sha256',$budgetPath)!==$budget||array_diff(array_keys($labels),['Anex Tour']))throw new RuntimeException();
        $result['verification']=['status'=>'checked','criteria'=>['STATEINC'=>$criteria['STATEINC'],'OPERATORS'=>$criteria['OPERATORS']],
            'page'=>$data['page'],'pages_count'=>$data['pages_count'],'received_offers'=>$data['received_offers'],
            'displayed_offers'=>$tours,'mapped_hotels'=>$mapped,'unresolved_hotels'=>$unresolved,
            'country'=>'Турция','operator_labels'=>array_keys($labels),'resume_equal'=>true,'resume_budget_unchanged'=>true];
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
