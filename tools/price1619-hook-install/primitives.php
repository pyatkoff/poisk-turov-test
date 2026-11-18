<?php
declare(strict_types=1);
function must(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function gitread(string $root,array $args):string{
    $p=proc_open(array_merge(['git','-C',$root],$args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    must(is_resource($p),'git_start_failed');fclose($pipes[0]);$out=stream_get_contents($pipes[1],1048577);fclose($pipes[1]);stream_get_contents($pipes[2],4096);fclose($pipes[2]);
    must(proc_close($p)===0&&is_string($out)&&strlen($out)<=1048576,'git_read_failed');return trim($out);
}
function readfile_exact(string $path,int $limit):array{
    clearstatcache(true,$path);must(!is_link($path)&&is_file($path)&&realpath($path)===$path,'path_invalid');
    $f=fopen($path,'rb');must(is_resource($f),'file_open_failed');
    try{
        $before=fstat($f);must(is_array($before)&&$before['size']>0&&$before['size']<=$limit,'file_size_invalid');
        $raw=stream_get_contents($f,$limit+1);must(is_string($raw)&&strlen($raw)===$before['size'],'file_read_changed');clearstatcache(true,$path);$after=lstat($path);
        foreach(['ino','dev','size','mtime','ctime'] as $k)must(($after[$k]??null)===$before[$k],'file_changed');
        return [$raw,$before];
    }finally{fclose($f);}
}
function write_new(string $path,string $raw,int $mode=0600):void{
    $f=fopen($path,'xb');must(is_resource($f),'exclusive_create_failed');
    try{must(chmod($path,$mode),'chmod_failed');$n=0;while($n<strlen($raw)){$written=fwrite($f,substr($raw,$n));must(is_int($written)&&$written>0,'file_write_failed');$n+=$written;}must(fflush($f),'flush_failed');if(function_exists('fsync'))must(fsync($f),'sync_failed');}
    finally{fclose($f);}
}
function active_collectors():int {
    $p=proc_open(['ps','-eo','pid=,args='],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);must(is_resource($p),'process_read_failed');fclose($pipes[0]);
    $raw=stream_get_contents($pipes[1],2097153);fclose($pipes[1]);stream_get_contents($pipes[2],4096);fclose($pipes[2]);
    must(proc_close($p)===0&&is_string($raw)&&strlen($raw)<=2097152,'process_read_failed');$n=0;
    foreach(explode("\n",$raw) as $line){if(!preg_match('/\A\s*([0-9]+)\s+(.*)\z/D',$line,$parts))continue;if((int)$parts[1]===getmypid())continue;$cmd=$parts[2];if(preg_match('~\A\s*(?:/[^\s]+/)?php(?:[0-9.]*)?\s+.*(?:top500-daily-collector-v1|scheduled-collector-v1|matrix-collector-v1|monitor-price-hook)\.php(?:\s|$)~',$cmd))$n++;}
    return $n;
}
function price1000_inspection_errors_classified(array $r):bool {
    if(array_key_exists('error',$r)&&$r['error']!==null&&$r['error']!=='')return false;
    foreach(['child_failure','pending','preflight_block','skipped'] as $k)if(!empty($r[$k]))return false;
    foreach(['verified_ids','eligible_ids','held_ids','unchanged_ids','updates','deferred'] as $k)
        if(!isset($r[$k])||!is_array($r[$k])||!array_is_list($r[$k]))return false;
    foreach(['error_counts','deferred_counts'] as $k)if(!isset($r[$k])||!is_array($r[$k]))return false;
    $sets=[];
    foreach(['verified_ids','eligible_ids','held_ids','unchanged_ids'] as $key){
        $sets[$key]=[];
        foreach($r[$key] as $id){if(!is_int($id)||$id<=0||isset($sets[$key][$id]))return false;$sets[$key][$id]=true;}
    }
    $updated=[];
    foreach($r['updates'] as $row){
        $id=is_array($row)?($row['tourvisor_hotel_id']??null):null;
        if(!is_int($id)||$id<=0||isset($updated[$id]))return false;$updated[$id]=true;
    }
    $counts=[];$seen=[];$expired=[];
    foreach($r['deferred'] as $row){
        if(!is_array($row)||count($row)!==2||!array_key_exists('tourvisor_hotel_id',$row)||!array_key_exists('reason',$row))return false;
        $id=$row['tourvisor_hotel_id'];$reason=$row['reason'];
        if(!is_int($id)||$id<=0||isset($seen[$id])||!isset($sets['verified_ids'][$id])
            ||!in_array($reason,['no_fresh_observation','abandoned_before_write','abandoned_before_write_record','monitor_price_expired'],true))return false;
        $seen[$id]=true;$counts[$reason]=($counts[$reason]??0)+1;
        if($reason==='monitor_price_expired'){
            if(isset($sets['eligible_ids'][$id])||isset($sets['held_ids'][$id])||isset($sets['unchanged_ids'][$id])||isset($updated[$id]))return false;
            $expired[]=$id;
        }
    }
    $reported=$r['deferred_counts'];ksort($reported);ksort($counts);
    if($reported!==$counts)return false;
    $errors=count($expired)>0?['monitor_price_expired'=>count($expired)]:[];
    return $r['error_counts']===$errors;
}
