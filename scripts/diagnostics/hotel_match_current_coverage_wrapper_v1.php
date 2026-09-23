<?php
declare(strict_types=1);

require_once __DIR__.'/hotel_match_tv_samo_anex_coverage_v1.php';

function hmcw_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function hmcw_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmcw_save(string $path,array $value):string{
    $raw=hmcw_json($value)."\n";$f=@fopen($path,'x+b');hmcw_need($f!==false,'exclusive_create');
    try{hmcw_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'durable_write');if(function_exists('fsync'))hmcw_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$raw);
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $mode=$argv[1]??'';
    if($mode==='--self-test'){
        $facts=[
            1=>['name'=>'A','country_name'=>'Turkey','region_name'=>'R','subregion_name'=>'S','category'=>'5'],
            2=>['name'=>'B','country_name'=>'Egypt','region_name'=>'H','subregion_name'=>'','category'=>'4'],
        ];
        $x=hmtsac_bucket([1,2],[1=>['s'=>true]],[1=>['a'=>true]],$facts);
        hmcw_need(($x['counts']['full_triple']??0)===1&&($x['counts']['neither']??0)===1,'bucket');
        echo "MATCH_CURRENT_COVERAGE_WRAPPER_V1_SELFTEST_OK\n";exit;
    }
    hmcw_need($mode==='--execute','disabled');
    $root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$source=(string)getenv('MATCH_SOURCE_SHA');$op=(string)getenv('MATCH_CHILD_OPERATION');
    hmcw_need(is_dir($root)&&is_dir($dir)&&basename($dir)===$op&&preg_match('/^hotel-match-current-coverage-1971-20260923-v[1-9][0-9]*$/D',$op)===1&&preg_match('/^[a-f0-9]{40}$/D',$source)===1,'runtime_scope');
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    hmcw_need(($reservation['operation']??'')===$op&&($reservation['state']??'')==='reserved_before_db_read','reservation');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    try{
        $result=hmtsac_execute(v2_data_db());
        $result['operation']=$op;$result['source_sha']=$source;
        hmcw_need(($result['state']??'')==='completed_read_only_coverage','state');
        foreach(['provider_http_calls','tourvisor_calls','samo_calls','anex_calls','database_writes','mapping_writes'] as $k)hmcw_need(($result[$k]??null)===0,'nonzero_'.$k);
        $h=hmcw_save($dir.'/result.json',$result);
        hmcw_save($dir.'/receipt.json',['operation'=>$op,'state'=>$result['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
        echo hmcw_json(['state'=>$result['state'],'edge_counts'=>$result['edge_counts'],'active'=>$result['active_tv']['counts'],'live30'=>$result['live_30d']['counts'],'samo_live30'=>$result['samo_live_30d']??null])."\n";
    }catch(Throwable $e){
        $f=['operation'=>$op,'state'=>'failed_read_only_coverage','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];
        $h=hmcw_save($dir.'/result.json',$f);hmcw_save($dir.'/receipt.json',['operation'=>$op,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true]);
        fwrite(STDERR,$f['reason']."\n");exit(2);
    }
}
