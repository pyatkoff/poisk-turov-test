<?php
error_reporting(0);ob_start();
$locked=false;$written=[];$backups=[];
try{
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
    $target=$root.'/_preview/search3-anex-candidate';$private=dirname($root,2).'/.anytoour-andromeda';
    if(!is_dir($target)||is_link($target)||is_link($private))throw new RuntimeException();
    if(!is_dir($private)&&!mkdir($private,0700))throw new RuntimeException();
    if(!mkdir($private.'/deploy.lock',0700))throw new RuntimeException();$locked=true;
    $input=file_get_contents('php://stdin',false,null,0,4000001);if(strlen($input)>4000000)throw new RuntimeException();
    $request=json_decode($input,true,32,JSON_THROW_ON_ERROR);
    if(!preg_match('/^[a-f0-9]{40}$/D',$request['source_sha']))throw new RuntimeException();
    $release=$private.'/release-'.$request['source_sha'];if(file_exists($release)||!mkdir($release,0700))throw new RuntimeException();
    $allowed=['api-andromeda-search3-preview.php','anex-search3-preview-v1.js',
        'app/integrations/andromeda-client.php','app/integrations/andromeda-transport.php',
        'app/integrations/andromeda-search.php','app/integrations/andromeda-offer-store.php',
        'app/integrations/andromeda-normalizer.php','app/integrations/andromeda-hotel-resolver.php'];
    if(count($request['files'])!==count($allowed)||array_diff(array_keys($request['files']),$allowed))throw new RuntimeException();
    if(hash_file('sha256',$target.'/anex-search3-preview-v1.js')!==$request['previous_addon_sha256'])throw new RuntimeException();
    if(is_file($target.'/api-andromeda-search3-preview.php')||is_file($private.'/search3-preview.php'))throw new RuntimeException();
    $files=[];foreach($request['files'] as $path=>$encoded){$data=base64_decode($encoded,true);if($data===false)throw new RuntimeException();$files[$path]=$data;}
    $oldHt=file_get_contents($target.'/.htaccess');
    if(strpos($oldHt,'<Files "api-anex-search3-preview.php">')===false)throw new RuntimeException();
    $files['.htaccess']=$oldHt."\n<Files \"api-andromeda-search3-preview.php\">\n  Require all granted\n</Files>\n";
    $page=file_get_contents($target.'/search-page-v2.php');$count=0;
    $files['search-page-v2.php']=preg_replace('~(anex-search3-preview-v1\\.js\\?v=)[a-f0-9]{40}~','${1}'.$request['source_sha'],$page,-1,$count);
    if($count!==1)throw new RuntimeException();
    $files['.andromeda-private.php']="<?php\nreturn require dirname(__DIR__,4).'/.anytoour-andromeda/search3-preview.php';\n";
    $config=['enabled'=>true,'username'=>$request['username'],'password'=>$request['password'],
        'catalog_path'=>$private.'/catalog.json','excluded_operator_ids'=>[],'source_sha'=>$request['source_sha']];
    if(!$config['username']||!$config['password'])throw new RuntimeException();
    if(file_put_contents($private.'/catalog.json',json_encode($request['catalog'],JSON_UNESCAPED_UNICODE))===false)throw new RuntimeException();
    chmod($private.'/catalog.json',0600);
    if(file_put_contents($private.'/search3-preview.php',"<?php\nreturn ".var_export($config,true).";\n")===false)throw new RuntimeException();
    chmod($private.'/search3-preview.php',0600);
    // Backup every replaced file before activation; addon/page are activated last.
    $addon=$files['anex-search3-preview-v1.js'];unset($files['anex-search3-preview-v1.js']);$files['anex-search3-preview-v1.js']=$addon;
    $page=$files['search-page-v2.php'];unset($files['search-page-v2.php']);$files['search-page-v2.php']=$page;
    foreach($files as $path=>$data){
        $dest=$target.'/'.$path;if(is_link($dest)||is_link(dirname($dest)))throw new RuntimeException();
        $old=is_file($dest)?file_get_contents($dest):null;$backups[$path]=$old;
        if($old!==null)file_put_contents($release.'/'.str_replace('/','__',$path),$old);
        $staged=$release.'/next-'.str_replace('/','__',$path);
        if(file_put_contents($staged,$data)!==strlen($data))throw new RuntimeException();
        chmod($staged,0644);
        if(!rename($staged,$dest))throw new RuntimeException();$written[]=$path;
        if(hash_file('sha256',$dest)!==hash('sha256',$data))throw new RuntimeException();
    }
    $manifest=['status'=>'deployed','source_sha'=>$request['source_sha'],'route'=>'/_preview/search3-anex-candidate/poisk-turov/',
        'files'=>array_keys($files),'sha256'=>array_map(static function($data){return hash('sha256',$data);},$files),
        'production_changed'=>false,'database_changed'=>false,'rollback_directory'=>basename($release)];
    file_put_contents($release.'/manifest.json',json_encode($manifest));$result=$manifest;
}catch(Throwable $e){
    foreach(array_reverse($written) as $path){if($backups[$path]===null)@unlink($target.'/'.$path);else @file_put_contents($target.'/'.$path,$backups[$path]);}
    $result=['status'=>'failed','rollback_attempted'=>count($written)>0];
}
if($locked)@rmdir($private.'/deploy.lock');
while(ob_get_level())ob_end_clean();echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
