<?php
declare(strict_types=1);
// Preserve only an already verified private panel across a later own-preview release.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    $root=realpath($argv[1]??'');$stage=realpath($argv[2]??'');$home=realpath((string)getenv('HOME'));
    if(!$root||!$stage||!$home||$root!==$home.'/www/anytoour.ru'||!preg_match('#\A'.preg_quote($root,'#').'/_preview/\.search3-anex-[0-9a-f]{40}-[0-9a-f]{12}\z#D',$stage))throw new RuntimeException('preserve_scope');
    $private=$home.'/.anytoour-anex/review-owner';$target=$root.'/_preview/search3-anex-candidate';
    if(!file_exists($private)){echo json_encode(['owner_panel'=>'not_installed']).PHP_EOL;exit;}
    if(realpath($private)!==$private||(fileperms($private)&0777)!==0700)throw new RuntimeException('preserve_private');
    $manifest=json_decode(file_get_contents($private.'/manifest.json'),true,16,JSON_THROW_ON_ERROR);
    if(($manifest['status']??'')!=='published'||!preg_match('/\A[0-9a-f]{40}\z/D',$manifest['source_sha']??''))throw new RuntimeException('preserve_manifest');
    $files=[];$baseline=[];
    foreach(['anex-owner-login.php','anex-hotel-review.php'] as $name){
        $p=$target.'/'.$name;
        $baseline[$name]=is_file($stage.'/'.$name)?hash_file('sha256',$stage.'/'.$name):null;
        if(!is_file($p)||is_link($p)||filesize($p)>8192||hash_file('sha256',$p)!==($manifest['after'][$name]??null))throw new RuntimeException('preserve_entry_drift');
        $raw=file_get_contents($p);if(file_put_contents($stage.'/'.$name,$raw)!==strlen($raw)||!chmod($stage.'/'.$name,0644))throw new RuntimeException('preserve_write');
        $files[$name]=hash_file('sha256',$stage.'/'.$name);
    }
    $ht=$stage.'/.htaccess';$raw=file_get_contents($ht);$baseline['.htaccess']=hash_file('sha256',$ht);
    if(!is_string($raw)||strpos($raw,'Require all denied')===false||strpos($raw,'noindex')===false)throw new RuntimeException('preserve_isolation');
    foreach(['anex-owner-login.php','anex-hotel-review.php'] as $name){if(strpos($raw,'<Files "'.$name.'">')!==false)throw new RuntimeException('preserve_grant_drift');$raw.="\n<Files \"$name\">\n  Require all granted\n</Files>\n";}
    if(file_put_contents($ht,$raw)!==strlen($raw))throw new RuntimeException('preserve_write');$files['.htaccess']=hash_file('sha256',$ht);
    $public=json_encode(['source_sha'=>$manifest['source_sha'],'files'=>$files,'write_enabled'=>false],JSON_THROW_ON_ERROR);
    if(file_put_contents($stage.'/anex-owner-panel-manifest.json',$public)!==strlen($public)||!chmod($stage.'/anex-owner-panel-manifest.json',0644))throw new RuntimeException('preserve_write');
    // An explicit overlay keeps both old owner-runtime and new site provenance.
    $files['anex-owner-panel-manifest.json']=hash_file('sha256',$stage.'/anex-owner-panel-manifest.json');
    $sizes=[];foreach($files as $name=>$sha)$sizes[$name]=filesize($stage.'/'.$name);
    $overlay=['owner_panel'=>'preserved','owner_source_sha'=>$manifest['source_sha'],'baseline_sha256'=>$baseline,'applied_sha256'=>$files,'applied_bytes'=>$sizes,'write_enabled'=>false];
    $sitePath=$stage.'/anex-preview-manifest.json';
    if(is_file($sitePath)){
        $site=json_decode(file_get_contents($sitePath),true,32,JSON_THROW_ON_ERROR);
        $site['owner_panel_overlay']=$overlay;
        foreach($site['files'] as &$row)if(isset($files[$row['path']])){$row['sha256']=$files[$row['path']];$row['bytes']=filesize($stage.'/'.$row['path']);}unset($row);
        $seen=array_column($site['files'],'path');foreach($files as $name=>$sha)if(!in_array($name,$seen,true))$site['files'][]=['path'=>$name,'sha256'=>$sha,'bytes'=>filesize($stage.'/'.$name)];
        $site['file_count']=count($site['files']);
        if(file_put_contents($sitePath,json_encode($site,JSON_THROW_ON_ERROR))===false)throw new RuntimeException('preserve_write');
    }
    echo json_encode($overlay,JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,"OWNER_PANEL_PRESERVATION_FAILED\n");exit(1);}
