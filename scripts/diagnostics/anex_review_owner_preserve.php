<?php
declare(strict_types=1);
// Preserve only verified scoped runtime that is not yet carried by the new own-preview payload.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    $root=realpath($argv[1]??'');$stage=realpath($argv[2]??'');$home=realpath((string)getenv('HOME'));
    if(!$root||!$stage||!$home||$root!==$home.'/www/anytoour.ru'||!preg_match('#\A'.preg_quote($root,'#').'/_preview/\.search3-anex-[0-9a-f]{40}-[0-9a-f]{12}\z#D',$stage))throw new RuntimeException('preserve_scope');
    $target=$root.'/_preview/search3-anex-candidate';

    // The Andromeda credential/config file is deliberately server-only and excluded from every public
    // payload. A full-directory ANEX preview swap must carry that installed private runtime forward,
    // without exposing its bytes, digest or size in stdout/manifests/artifacts. If it is not installed,
    // preserve the fail-closed state; a separate owner-approved operation is required to install it.
    $andromedaPrivate=['status'=>'not_installed','mode'=>null];
    $privateSource=$target.'/.andromeda-private.php';
    $privateDestination=$stage.'/.andromeda-private.php';
    if(file_exists($privateDestination)||is_link($privateDestination))throw new RuntimeException('preserve_andromeda_private_stage');
    if(file_exists($privateSource)||is_link($privateSource)){
        if(!is_file($privateSource)||is_link($privateSource))throw new RuntimeException('preserve_andromeda_private_source');
        $sourceMode=fileperms($privateSource)&0777;
        if(($sourceMode&0002)!==0)throw new RuntimeException('preserve_andromeda_private_mode');
        $size=filesize($privateSource);
        if(!is_int($size)||$size<1||$size>65536)throw new RuntimeException('preserve_andromeda_private_bound');
        $raw=file_get_contents($privateSource);
        if(!is_string($raw)||strlen($raw)!==$size)throw new RuntimeException('preserve_andromeda_private_read');
        if(file_put_contents($privateDestination,$raw)!==$size||!chmod($privateDestination,0600))throw new RuntimeException('preserve_andromeda_private_write');
        if((fileperms($privateDestination)&0777)!==0600||hash_file('sha256',$privateDestination)!==hash('sha256',$raw))throw new RuntimeException('preserve_andromeda_private_drift');
        $andromedaPrivate=['status'=>'preserved','mode'=>'0600'];
    }

    // These five Andromeda core files are installed by the scoped provider publisher but are not yet
    // tracked on the INT base. A full ANEX preview refresh must not silently delete them. If a future
    // payload carries any of them itself, that current source wins and the installed copy is ignored.
    // The same helper is also used by owner-panel-only fixtures; those do not contain a preview payload
    // and therefore do not participate in runtime preservation.
    $runtimeNames=['andromeda-normalizer.php','andromeda-hotel-resolver.php','andromeda-search.php','andromeda-hotel-observations.php','andromeda-offer-store.php'];
    $runtimeDir=$stage.'/app/integrations';
    $runtimeOverlay=['status'=>'not_applicable','sha256'=>[],'bytes'=>[],'sources'=>[]];
    if(is_file($stage.'/anex-preview-manifest.json')||is_dir($runtimeDir)){
        if(!is_dir($runtimeDir)||is_link($runtimeDir))throw new RuntimeException('preserve_runtime_stage');
        $runtimeHashes=[];$runtimeBytes=[];$runtimeSources=[];
        foreach($runtimeNames as $name){
            $destination=$runtimeDir.'/'.$name;
            if(is_file($destination)&&!is_link($destination)){
                $size=filesize($destination);
                if(!is_int($size)||$size<1||$size>300000)throw new RuntimeException('preserve_runtime_bound');
                $runtimeHashes[$name]=hash_file('sha256',$destination);$runtimeBytes[$name]=$size;$runtimeSources[$name]='current_source';
                continue;
            }
            if(file_exists($destination)||is_link($destination))throw new RuntimeException('preserve_runtime_stage');
            $source=$target.'/app/integrations/'.$name;
            if(!is_file($source)||is_link($source))throw new RuntimeException('preserve_runtime_missing');
            $size=filesize($source);
            if(!is_int($size)||$size<1||$size>300000)throw new RuntimeException('preserve_runtime_bound');
            $raw=file_get_contents($source);
            if(!is_string($raw)||strlen($raw)!==$size||file_put_contents($destination,$raw)!==$size||!chmod($destination,0644))throw new RuntimeException('preserve_runtime_write');
            $runtimeHashes[$name]=hash_file('sha256',$destination);$runtimeBytes[$name]=$size;$runtimeSources[$name]='installed_preview';
            if($runtimeHashes[$name]!==hash_file('sha256',$source))throw new RuntimeException('preserve_runtime_drift');
        }
        $runtimeOverlay=['status'=>'preserved','sha256'=>$runtimeHashes,'bytes'=>$runtimeBytes,'sources'=>$runtimeSources];
    }

    // Preserve the separate owner panel when it is installed. Runtime preservation above is independent
    // of the panel so a preview refresh remains safe even when the panel has never been activated.
    $private=$home.'/.anytoour-anex/review-owner';
    if(!file_exists($private)){echo json_encode(['owner_panel'=>'not_installed','runtime_overlay'=>$runtimeOverlay,'andromeda_private_config'=>$andromedaPrivate],JSON_THROW_ON_ERROR).PHP_EOL;exit;}
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
    $overlay=['owner_panel'=>'preserved','owner_source_sha'=>$manifest['source_sha'],'baseline_sha256'=>$baseline,'applied_sha256'=>$files,'applied_bytes'=>$sizes,'write_enabled'=>false,'runtime_overlay'=>$runtimeOverlay,'andromeda_private_config'=>$andromedaPrivate];
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