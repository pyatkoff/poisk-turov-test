<?php
declare(strict_types=1);
// Preserve only an already verified private panel across a later own-preview release.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    $root=realpath($argv[1]??'');$stage=realpath($argv[2]??'');$home=realpath((string)getenv('HOME'));
    if(!$root||!$stage||!$home||$root!==$home.'/www/anytoour.ru'||!preg_match('#\A'.preg_quote($root,'#').'/_preview/\.search3-anex-[0-9a-f]{40}-[0-9a-f]{12}\z#D',$stage))throw new RuntimeException('preserve_scope');
    $private=$home.'/.anytoour-anex/review-owner';$target=$root.'/_preview/search3-anex-candidate';
    if(!file_exists($private)){echo "OWNER_PANEL_NOT_INSTALLED\n";exit;}
    if(realpath($private)!==$private||(fileperms($private)&0777)!==0700)throw new RuntimeException('preserve_private');
    $manifest=json_decode(file_get_contents($private.'/manifest.json'),true,16,JSON_THROW_ON_ERROR);
    if(($manifest['status']??'')!=='published'||!preg_match('/\A[0-9a-f]{40}\z/D',$manifest['source_sha']??''))throw new RuntimeException('preserve_manifest');
    $files=[];
    foreach(['anex-owner-login.php','anex-hotel-review.php'] as $name){
        $p=$target.'/'.$name;
        if(!is_file($p)||is_link($p)||filesize($p)>8192||hash_file('sha256',$p)!==($manifest['after'][$name]??null))throw new RuntimeException('preserve_entry_drift');
        $raw=file_get_contents($p);if(file_put_contents($stage.'/'.$name,$raw)!==strlen($raw)||!chmod($stage.'/'.$name,0644))throw new RuntimeException('preserve_write');
        $files[$name]=hash_file('sha256',$stage.'/'.$name);
    }
    $ht=$stage.'/.htaccess';$raw=file_get_contents($ht);
    if(!is_string($raw)||strpos($raw,'Require all denied')===false||strpos($raw,'noindex')===false)throw new RuntimeException('preserve_isolation');
    foreach(['anex-owner-login.php','anex-hotel-review.php'] as $name){if(strpos($raw,'<Files "'.$name.'">')!==false)throw new RuntimeException('preserve_grant_drift');$raw.="\n<Files \"$name\">\n  Require all granted\n</Files>\n";}
    if(file_put_contents($ht,$raw)!==strlen($raw))throw new RuntimeException('preserve_write');$files['.htaccess']=hash_file('sha256',$ht);
    $public=json_encode(['source_sha'=>$manifest['source_sha'],'files'=>$files,'write_enabled'=>false],JSON_THROW_ON_ERROR);
    if(file_put_contents($stage.'/anex-owner-panel-manifest.json',$public)!==strlen($public)||!chmod($stage.'/anex-owner-panel-manifest.json',0644))throw new RuntimeException('preserve_write');
    echo "OWNER_PANEL_PRESERVED\n";
}catch(Throwable $e){fwrite(STDERR,"OWNER_PANEL_PRESERVATION_FAILED\n");exit(1);}
