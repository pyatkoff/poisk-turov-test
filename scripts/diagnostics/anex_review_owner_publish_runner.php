<?php
declare(strict_types=1);
// Fixed, bounded CLI-only filesystem publisher. No application/supplier/DB calls.
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
ini_set('display_errors','0');
function owner_publish_write(string $path,string $raw,int $mode): void {
    if (file_exists($path)||is_link($path)) throw new RuntimeException('publish_target_exists');
    if (file_put_contents($path,$raw,LOCK_EX)!==strlen($raw)||!chmod($path,$mode)||file_get_contents($path)!==$raw) throw new RuntimeException('publish_readback_failed');
}
function owner_publish_file(string $path): string {
    if (!is_file($path)||is_link($path)||(lstat($path)['nlink']??0)!==1||filesize($path)>1000000) throw new RuntimeException('publish_source_invalid');
    return hash_file('sha256',$path);
}
try {
    umask(0077);
    $raw=stream_get_contents(STDIN,2000001);
    if(!is_string($raw)||strlen($raw)>2000000) throw new RuntimeException('publish_bound');
    $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($input)||!in_array($input['action']??'', ['inspect','apply','repair','panel_links'],true)||!preg_match('/\A[0-9a-f]{40}\z/D',$input['source_sha']??'')) throw new RuntimeException('publish_contract');
    $root=realpath(getcwd());$home=realpath((string)getenv('HOME'));
    if(!$root||!$home||$root!==$home.'/www/anytoour.ru'||is_link(getcwd())) throw new RuntimeException('publish_anytour_root');
    $parent=$home.'/.anytoour-anex';$private=$parent.'/review-owner';$target=$root.'/_preview/search3-anex-candidate';
    if(realpath($parent)!==$parent||!is_dir($parent)||(fileperms($parent)&0777)!==0700||realpath($target)!==$target||!is_dir($target)) throw new RuntimeException('publish_scope_invalid');
    $ht=$target.'/.htaccess';$htRaw=file_get_contents($ht);
    if(!is_string($htRaw)||strpos($htRaw,'Require all denied')===false||strpos($htRaw,'noindex')===false) throw new RuntimeException('publish_isolation_required');
    $before=['.htaccess'=>owner_publish_file($ht)];
    foreach(['anex-owner-login.php','anex-hotel-review.php'] as $name) $before[$name]=file_exists($target.'/'.$name)?owner_publish_file($target.'/'.$name):null;
    $helper=null;foreach(['data/db-v1.php','v2/data/db-v1.php'] as $name) { $p=realpath($root.'/'.$name);if($p&&strpos($p,$root.'/')===0&&is_file($p)&&!is_link($root.'/'.$name)){$helper=$p;break;} }
    if(!$helper) throw new RuntimeException('publish_db_helper_missing');
    if(in_array($input['action'],['repair','panel_links'],true)) {
        // Separate bounded upgrades. The original repair still allows only its exact header change.
        $links=$input['action']==='panel_links';$stage=$links?'links':'repair';
        if(realpath($private)!==$private||(fileperms($private)&0777)!==0700)throw new RuntimeException('repair_private_invalid');
        $manifestPath=$private.'/manifest.json';owner_publish_file($manifestPath);
        $old=json_decode(file_get_contents($manifestPath),true,32,JSON_THROW_ON_ERROR);
        $oldSource=$old['source_sha']??'';
        if(($old['status']??'')!=='published'||!preg_match('/\A[0-9a-f]{40}\z/D',$oldSource)||$oldSource!==($input['expected_source']??null)
            ||($old['runtime_files']??null)!=($input['expected_runtime']??null)||($old['after']??null)!=$before||($old['write_enabled']??null)!==false)throw new RuntimeException('repair_manifest_drift');
        $receipt=json_decode(file_get_contents($private.'/publication-state.json'),true,16,JSON_THROW_ON_ERROR);
        if(($receipt['state']??'')!=='completed'||($receipt['manifest_sha256']??'')!==hash_file('sha256',$manifestPath))throw new RuntimeException('repair_publication_incomplete');
        $oldRuntime=$private.'/runtime-'.$oldSource;$runtime=$private.'/runtime-'.$input['source_sha'];
        if(realpath($oldRuntime)!==$oldRuntime||file_exists($runtime)||is_link($runtime)||(file_exists($private.'/'.$stage.'-state.json')||is_link($private.'/'.$stage.'-state.json')))throw new RuntimeException('repair_already_started');
        if(!is_array($input['files']??null)||array_keys($input['files'])!==array_keys($old['runtime_files']))throw new RuntimeException('repair_paths');
        if($links){
            $allowedDelta=['app/admin/anex-review/view.php','v2/anex-hotel-review.php'];
            $delta=$input['allowed_delta']??null;
            if(!is_array($delta)||array_keys($delta)!==$allowedDelta)throw new RuntimeException('links_delta_paths');
            foreach($delta as $digest)if(!is_string($digest)||!preg_match('/\A[0-9a-f]{64}\z/D',$digest))throw new RuntimeException('links_delta_digest');
        }
        foreach($input['files'] as $name=>$file){
            if(!is_string($file['content']??null)||strlen($file['content'])>1000000||hash('sha256',$file['content'])!==($file['sha256']??''))throw new RuntimeException('repair_content');
            $oldPath=$oldRuntime.'/'.$name;
            if(owner_publish_file($oldPath)!==$old['runtime_files'][$name])throw new RuntimeException('repair_runtime_drift');
            $expected=file_get_contents($oldPath);
            if($links){
                if(isset($delta[$name])){
                    if($file['sha256']!==$delta[$name]||$file['sha256']===$old['runtime_files'][$name])throw new RuntimeException('links_delta_digest');
                }elseif($file['content']!==$expected)throw new RuntimeException('links_delta_not_allowed');
            }else{
                if($name==='v2/anex-owner-login.php')$expected=str_replace("header('Referrer-Policy: no-referrer');","header('Referrer-Policy: same-origin');",$expected,$count);
                if($file['content']!==$expected)throw new RuntimeException('repair_delta_not_allowed');
            }
        }
        if(!$links&&($count??0)!==1)throw new RuntimeException('repair_delta_missing');
        owner_publish_file($private.'/owner.lock');
        $lock=fopen($private.'/owner.lock','r+');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('repair_lock');
        try {
            $accountHash=owner_publish_file($private.'/owner.json');$configHash=owner_publish_file($private.'/config.php');
            $account=json_decode(file_get_contents($private.'/owner.json'),true,16,JSON_THROW_ON_ERROR);
            // Private receipt blocks retries after any partial write. Old immutable runtime stays available.
            owner_publish_write($private.'/'.$stage.'-state.json',json_encode(['state'=>'executing','source_sha'=>$input['source_sha']]),0600);
            mkdir($runtime,0700);
            foreach($input['files'] as $name=>$file){$p=$runtime.'/'.$name;if(!is_dir(dirname($p)))mkdir(dirname($p),0700,true);owner_publish_write($p,$file['content'],0600);}
            foreach(['anex-owner-login.php'=>'login','anex-hotel-review.php'=>'panel'] as $name=>$kind){
                $entry=$private.'/entry-'.$kind.'.php';$existing=file_get_contents($entry);
                $expected='<?php define("ANYTOUR_ANEX_OWNER_CONFIG",__DIR__."/config.php");require __DIR__."/runtime-'.$oldSource.'/v2/'.$name.'";';
                if($existing!==$expected)throw new RuntimeException('repair_entry_drift');
                owner_publish_write($private.'/'.$stage.'-backup-entry-'.$kind.'.php',$existing,0600);
                $next=str_replace('runtime-'.$oldSource,'runtime-'.$input['source_sha'],$existing);
                owner_publish_write($private.'/'.$stage.'-next-'.$kind.'.php',$next,0600);
                if(!rename($private.'/'.$stage.'-next-'.$kind.'.php',$entry)||file_get_contents($entry)!==$next)throw new RuntimeException('repair_entry_readback');
            }
            $report=$old;$report['source_sha']=$input['source_sha'];$report['previous_source_sha']=$oldSource;
            if($links)$report['upgrade_action']='panel_links';
            $report['runtime_files']=array_map(static fn($f)=>$f['sha256'],$input['files']);
            $report['owner_activated']=is_string($account['password_hash']??null);$report['activation_expires_at']=$account['setup_expires_at']??0;
            $report['account_preserved']=owner_publish_file($private.'/owner.json')===$accountHash;
            $report['config_preserved']=owner_publish_file($private.'/config.php')===$configHash;
            if(!$report['account_preserved']||!$report['config_preserved'])throw new RuntimeException('repair_preservation');
            owner_publish_write($private.'/'.$stage.'-backup-manifest.json',file_get_contents($manifestPath),0600);
            owner_publish_write($private.'/'.$stage.'-next-manifest.json',json_encode($report,JSON_THROW_ON_ERROR),0600);
            if(!rename($private.'/'.$stage.'-next-manifest.json',$manifestPath))throw new RuntimeException('repair_manifest_write');
            $public=$target.'/anex-owner-panel-manifest.json';
            owner_publish_write($private.'/'.$stage.'-backup-public-manifest.json',file_get_contents($public),0600);
            owner_publish_write($private.'/'.$stage.'-next-public.json',json_encode(['source_sha'=>$input['source_sha'],'files'=>$before,'write_enabled'=>false],JSON_THROW_ON_ERROR),0644);
            if(!rename($private.'/'.$stage.'-next-public.json',$public))throw new RuntimeException('repair_manifest_write');
            foreach(['publication-state',$stage.'-state'] as $state){
                owner_publish_write($private.'/'.$state.'.next',json_encode(['state'=>'completed','source_sha'=>$input['source_sha'],'manifest_sha256'=>hash_file('sha256',$manifestPath)],JSON_THROW_ON_ERROR),0600);
                if(!rename($private.'/'.$state.'.next',$private.'/'.$state.'.json'))throw new RuntimeException('repair_receipt');
            }
            echo json_encode($report,JSON_THROW_ON_ERROR).PHP_EOL;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
        exit;
    }
    if(file_exists($private)||is_link($private)) throw new RuntimeException('publish_owner_already_exists');
    if(file_exists($target.'/anex-owner-panel-manifest.json')||is_link($target.'/anex-owner-panel-manifest.json'))throw new RuntimeException('publish_manifest_exists');
    foreach(['anex-owner-login.php','anex-hotel-review.php'] as $name)if(strpos($htRaw,'<Files "'.$name.'">')!==false)throw new RuntimeException('publish_htaccess_conflict');
    if($input['action']==='inspect') {
        echo json_encode(['status'=>'ready','before'=>$before,'private_exists'=>false,'database_calls'=>0,'supplier_requests'=>0,'published'=>false],JSON_THROW_ON_ERROR).PHP_EOL;exit;
    }
    if(($input['expected_before']??null)!=$before||!preg_match('/\A[0-9a-f]{64}\z/D',$input['setup_hash']??'')||!is_array($input['files']??null)) throw new RuntimeException('publish_pinned_readiness');
    $allowed=['app/admin/anex-review/access.php','app/admin/anex-review/service.php','app/admin/anex-review/view.php','app/admin/anex-review/dossier-store.php','app/admin/anex-review/owner-auth.php','app/admin/anex-review/owner-login.php','app/admin/anex-review/public-cards.json','v2/anex-hotel-review.php','v2/anex-owner-login.php'];
    $keys=array_keys($input['files']);sort($keys);sort($allowed);if($keys!==$allowed)throw new RuntimeException('publish_manifest_paths');
    foreach($input['files'] as $name=>$file)if(!is_array($file)||!is_string($file['content']??null)||strlen($file['content'])>1000000||hash('sha256',$file['content'])!==($file['sha256']??null))throw new RuntimeException('publish_manifest_digest');
    // Private lock remains on any unknown failure; never retry or reset partial publication.
    if(!mkdir($private,0700))throw new RuntimeException('publish_owner_exists');
    owner_publish_write($private.'/publication-state.json',json_encode(['state'=>'executing','source_sha'=>$input['source_sha']]),0600);
    $runtime=$private.'/runtime-'.$input['source_sha'];mkdir($runtime,0700);
    foreach($input['files'] as $name=>$file){$p=$runtime.'/'.$name;if(!is_dir(dirname($p)))mkdir(dirname($p),0700,true);owner_publish_write($p,$file['content'],0600);}
    require $runtime.'/app/admin/anex-review/owner-auth.php';
    mkdir($private.'/sessions',0700);
    $expires=time()+3600;
    AnexReviewOwnerAuth::bootstrapHash($private,$root,$input['setup_hash'],$expires);
    $config='<?php return ["private_directory"=>__DIR__,"pdo_factory"=>static function(): PDO { require_once '.var_export($helper,true).'; if(!function_exists("v2_data_db"))throw new RuntimeException("review_db_not_connected"); $db=v2_data_db(); if(!$db instanceof PDO)throw new RuntimeException("review_db_not_connected");return $db;}];';
    owner_publish_write($private.'/config.php',$config,0600);
    $published=[];
    foreach(['anex-owner-login.php'=>'login','anex-hotel-review.php'=>'panel'] as $name=>$kind){
        $entry='<?php define("ANYTOUR_ANEX_OWNER_CONFIG",__DIR__."/config.php");require __DIR__."/runtime-'.$input['source_sha'].'/v2/'.$name.'";';
        owner_publish_write($private.'/entry-'.$kind.'.php',$entry,0600);
        $stub='<?php ini_set("display_errors","0");header("Cache-Control: no-store, private");header("X-Robots-Tag: noindex, nofollow");$p=dirname(__DIR__,4)."/.anytoour-anex/review-owner";if(realpath($p)!==$p||!is_dir($p)||(fileperms($p)&0777)!==0700){http_response_code(503);exit("Защищённый вход пока недоступен.");}require $p."/entry-'.$kind.'.php";';
        if(file_exists($target.'/'.$name))owner_publish_write($private.'/backup-'.$name,file_get_contents($target.'/'.$name),0600);
        owner_publish_write($private.'/next-'.$name,$stub,0644);
        if(!rename($private.'/next-'.$name,$target.'/'.$name))throw new RuntimeException('publish_install_failed');
        $published[$name]=owner_publish_file($target.'/'.$name);
    }
    owner_publish_write($private.'/backup-htaccess',$htRaw,0600);
    $htNext=$htRaw;
    foreach(['anex-owner-login.php','anex-hotel-review.php'] as $name){
        if(strpos($htNext,'<Files "'.$name.'">')!==false)throw new RuntimeException('publish_htaccess_conflict');
        $htNext.="\n<Files \"$name\">\n  Require all granted\n</Files>\n";
    }
    owner_publish_write($private.'/next-htaccess',$htNext,0644);
    if(!rename($private.'/next-htaccess',$ht))throw new RuntimeException('publish_install_failed');
    $published['.htaccess']=owner_publish_file($ht);
    $manifest=['status'=>'published','source_sha'=>$input['source_sha'],'before'=>$before,'after'=>$published,'runtime_files'=>array_map(static fn($f)=>$f['sha256'],$input['files']),'write_enabled'=>false,'database_calls'=>0,'supplier_requests'=>0,'owner_activated'=>false,'activation_expires_at'=>$expires];
    owner_publish_write($private.'/manifest.json',json_encode($manifest,JSON_THROW_ON_ERROR),0600);
    // Public manifest contains source/file provenance only, never account/session/setup state.
    owner_publish_write($target.'/anex-owner-panel-manifest.json',json_encode(['source_sha'=>$input['source_sha'],'files'=>$published,'write_enabled'=>false],JSON_THROW_ON_ERROR),0644);
    owner_publish_write($private.'/publication-state.next',json_encode(['state'=>'completed','source_sha'=>$input['source_sha'],'manifest_sha256'=>hash_file('sha256',$private.'/manifest.json')],JSON_THROW_ON_ERROR),0600);
    if(!rename($private.'/publication-state.next',$private.'/publication-state.json'))throw new RuntimeException('publish_install_failed');
    echo json_encode($manifest,JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){
    $reason=$e instanceof RuntimeException&&preg_match('/\A[a-z_]+\z/D',$e->getMessage())?$e->getMessage():'publish_runtime_failure';
    echo json_encode(['status'=>'failed','reason'=>$reason,'database_calls'=>0,'supplier_requests'=>0,'reset_performed'=>false]).PHP_EOL;
}
