<?php
declare(strict_types=1);

const OP='hotel-match-live942-samo-anex-refresh-1971-20260923-v1';
const MAX_HTTP=2200;

function s942_need(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function s942_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function s942_save(string $p,array $v):string{
    $b=s942_json($v)."\n";$f=@fopen($p,'x+b');s942_need($f!==false,'exclusive_create');
    try{s942_need(fwrite($f,$b)===strlen($b)&&fflush($f),'durable_write');if(function_exists('fsync'))s942_need(fsync($f),'durable_sync');}
    finally{fclose($f);}return hash('sha256',$b);
}
function s942_read(string $p):array{$x=json_decode((string)file_get_contents($p),true,64,JSON_THROW_ON_ERROR);s942_need(is_array($x),'json_shape');return $x;}
function s942_norm(mixed $v):string{$s=mb_strtolower(trim((string)$v),'UTF-8');$s=str_replace('ё','е',$s);return trim(preg_replace('/\s+/u',' ',$s)??$s);}
function s942_one(array $rows,array $aliases):int{
    $want=array_fill_keys(array_map('s942_norm',$aliases),true);$ids=[];
    foreach($rows as $r){if(!is_array($r))continue;$name=s942_norm($r['name']??$r['lName']??'');if(isset($want[$name])&&preg_match('/^[1-9][0-9]{0,12}$/D',(string)($r['id']??'')))$ids[(int)$r['id']]=true;}
    s942_need(count($ids)===1,'dictionary_binding');return (int)array_key_first($ids);
}
function s942_budget(string $root,string $op,int $call):void{
    $p=$root.'/monthly-requests.json';$lock=fopen($p.'.lock','c');s942_need($lock!==false&&flock($lock,LOCK_EX),'budget_lock');
    try{
        $month=gmdate('Y-m');$s=is_file($p)?s942_read($p):[];
        if(($s['month']??'')!==$month)$s=['month'=>$month,'reserved_requests'=>0,'monthly_limit'=>5000000,'scope'=>'this_integration'];
        $used=(int)($s['reserved_requests']??0);$limit=(int)($s['monthly_limit']??5000000);
        s942_need($limit===5000000&&$used<$limit,'monthly_quota');
        $s['reserved_requests']=$used+1;$s['last_match_operation']=$op;$s['last_match_call']=$call;
        $tmp=$p.'.'.bin2hex(random_bytes(4));file_put_contents($tmp,s942_json($s));chmod($tmp,0600);rename($tmp,$p);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function s942_catalog(string $path,int $localCountry):array{
    $p=$localCountry===1?$path:dirname($path).'/countries/'.$localCountry.'.json';
    s942_need(is_file($p)&&!is_link($p),'catalog_missing_'.$localCountry);return s942_read($p);
}
function s942_execute(string $root,string $dir,string $planPath,string $sourceSha):int{
    $plan=s942_read($planPath);$res=s942_read($dir.'/reservation.json');
    s942_need(($res['operation']??'')===OP&&($plan['frontier_count']??0)===942&&count($plan['rows']??[])===942,'input_guard');
    $app=$root.'/app/integrations';if(!is_dir($app))$app=$root.'/v2/app/integrations';
    require_once $app.'/andromeda-client.php';require_once $app.'/andromeda-transport.php';
    $v2=$root.'/v2';$cfg=require $v2.'/.andromeda-private.php';
    s942_need(($cfg['enabled']??false)===true&&is_string($cfg['catalog_path']??null)&&$cfg['catalog_path']!=='','config');
    $private=dirname($cfg['catalog_path']);$calls=0;$transport=new AnyTourAndromedaTransport(true);
    $wrap=function($url,$opts)use($transport,$private,&$calls,$dir){
        $next=$calls+1;s942_need($next<=MAX_HTTP,'operation_http_cap');s942_budget($private,OP,$next);$calls=$next;
        s942_save($dir.'/samo-http-'.str_pad((string)$calls,4,'0',STR_PAD_LEFT).'-reserved.json',['operation'=>OP,'call'=>$calls,'state'=>'reserved_before_http']);
        return $transport($url,$opts);
    };
    $base=['operation'=>OP,'source_sha'=>$sourceSha,'frontier_count'=>942,'tourvisor_calls'=>0,'direct_anex_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
    $state='failed_before_provider_access';$reason=null;$rows=[];$dicts=[];
    try{
        s942_save($dir.'/samo-login-reserved.json',['operation'=>OP,'state'=>'reserved_before_login']);
        $login=new AnyTourAndromedaClient($wrap,true);$login->login($cfg['username'],$cfg['password']);$session=$login->privateSession();s942_need(is_array($session)&&$session!==[],'login');
        s942_save($dir.'/samo-login-result.json',['operation'=>OP,'state'=>'login_succeeded']);
        $today=new DateTimeImmutable('now',new DateTimeZone('Europe/Moscow'));$beg=$today->modify('+7 days')->format('Ymd');$end=$today->modify('+28 days')->format('Ymd');
        foreach($plan['rows'] as $ix=>$target){
            $tv=(int)$target['tv_hotel_id'];$country=(int)$target['country_id'];$anchors=array_values(array_unique(array_map('intval',$target['samo_hotel_ids']??[])));sort($anchors);
            s942_need($tv>0&&$country>0&&$anchors!==[],'target_shape');
            if(!isset($dicts[$country])){
                $saved=s942_catalog($cfg['catalog_path'],$country);$stateInc=(int)($saved['all']['params']['STATEINC']??0);s942_need($stateInc>0,'state_missing_'.$country);
                $dep=s942_one($saved['townfrom']['payload']['TOWNFROM']??[],['Москва','Moscow']);
                $anex=s942_one($saved['all']['payload']['OPERATORS']??[],['Anex','Anex Tour','AnexTour','Анекс','Анекс Тур']);
                s942_need($anex===5,'anex_operator_binding_changed_'.$anex);
                $hotelSet=[];foreach($saved['all']['payload']['HOTELS']??[] as $h)if(is_array($h)&&isset($h['id']))$hotelSet[(string)$h['id']]=true;
                $dicts[$country]=['state'=>$stateInc,'departure'=>$dep,'operator'=>$anex,'hotels'=>$hotelSet];
            }
            $d=$dicts[$country];$attempts=[];$native=[];$catalog=[];$offerRows=0;
            foreach($anchors as $anchor){
                $a=(string)$anchor;
                if(!isset($d['hotels'][$a])){$attempts[]=['samo_hotel_id'=>$anchor,'state'=>'anchor_not_in_current_catalog'];continue;}
                s942_save($dir.'/samo-target-'.$tv.'-'.$anchor.'-reserved.json',['operation'=>OP,'tv_hotel_id'=>$tv,'samo_hotel_id'=>$anchor,'state'=>'reserved_before_price']);
                $cl=new AnyTourAndromedaClient($wrap,true);$cl->restorePrivateSession($session);
                $params=['TOWNFROMINC'=>$d['departure'],'STATEINC'=>$d['state'],'CHECKIN_BEG'=>$beg,'CHECKIN_END'=>$end,'NIGHTS_FROM'=>5,'NIGHTS_TILL'=>14,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,'PACKETTYPE'=>0,'PAGE'=>1,'OPERATORS'=>'5','HOTELS'=>$a];
                $raw=$cl->price($params);$rawHash=hash('sha256',s942_json($raw));file_put_contents($dir.'/samo-target-'.$tv.'-'.$anchor.'-raw.json',s942_json($raw)."\n");chmod($dir.'/samo-target-'.$tv.'-'.$anchor.'-raw.json',0600);
                $seen=[];$opNative=[];$catIds=[];$count=0;
                foreach(($raw['PRICES']??[]) as $z){
                    if(!is_array($z)||(string)($z['operatorKey']??'')!=='5')continue;$count++;$id=(string)($z['hotelKey']??'');$flag=(string)($z['isOperatorHotelKey']??'');
                    if(!preg_match('/^[1-9][0-9]{0,15}$/D',$id)||!in_array($flag,['0','1'],true))continue;$k=$flag.'|'.$id;if(isset($seen[$k]))continue;$seen[$k]=true;
                    if($flag==='1')$opNative[$id]=true;else $catIds[$id]=true;
                }
                $offerRows+=$count;foreach($opNative as $id=>$_)$native[$id]=true;foreach($catIds as $id=>$_)$catalog[$id]=true;
                $attempts[]=['samo_hotel_id'=>$anchor,'state'=>$count>0?'returned':'not_returned','operator_price_rows'=>$count,'operator_native_ids'=>array_map('intval',array_keys($opNative)),'catalog_ids'=>array_map('intval',array_keys($catIds)),'response_sha256'=>$rawHash];
                s942_save($dir.'/samo-target-'.$tv.'-'.$anchor.'-result.json',end($attempts));
                if(count($native)===1)break;
            }
            $ids=array_map('intval',array_keys($native));sort($ids);$st=count($ids)===1?'unique_operator_native':(count($ids)>1?'ambiguous_operator_native':($offerRows>0?'returned_without_operator_native':'not_returned'));
            $row=['tv_hotel_id'=>$tv,'country_id'=>$country,'samo_anchor_count'=>count($anchors),'attempted_anchors'=>count($attempts),'operator_price_rows'=>$offerRows,'state'=>$st,'anex_native_candidates'=>$ids,'catalog_candidates'=>array_map('intval',array_keys($catalog)),'attempts'=>$attempts,'safe_to_write_now'=>false];
            s942_save($dir.'/samo-hotel-'.$tv.'.json',$row);$rows[]=$row;
        }
        $state='completed_read_only';
    }catch(Throwable $e){
        $reason=preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8'));
        $state=$calls>0?'terminal_failed_no_replay':'failed_before_provider_access';
        if(str_contains($reason,'monthly_quota')||str_contains($reason,'operation_http_cap'))$state='terminal_quota_stop_no_replay';
    }
    if($rows===[]){foreach(glob($dir.'/samo-hotel-*.json')?:[] as $p)$rows[]=s942_read($p);}
    $counts=[];$nativeSources=[];foreach($rows as $r){$counts[$r['state']]=($counts[$r['state']]??0)+1;if($r['state']==='unique_operator_native')$nativeSources[(string)$r['anex_native_candidates'][0]][]=(int)$r['tv_hotel_id'];}ksort($counts);
    $mutual=0;foreach($nativeSources as $tvIds)if(count(array_unique($tvIds))===1)$mutual++;
    $out=$base+['state'=>$state,'reason'=>$reason,'samo_http_calls'=>$calls,'searched_hotels'=>count($rows),'date_window'=>[$beg??null,$end??null],'operator_id'=>5,'status_counts'=>$counts,'unique_native_source_unique_count'=>$mutual,'rows'=>$rows];
    $h=s942_save($dir.'/result.json',$out);s942_save($dir.'/receipt.json',$base+['state'=>$state,'result_sha256'=>$h,'samo_http_calls'=>$calls,'searched_hotels'=>count($rows),'no_replay'=>$calls>0]);
    echo s942_json(['state'=>$state,'reason'=>$reason,'samo_http_calls'=>$calls,'searched_hotels'=>count($rows),'status_counts'=>$counts,'unique_native_source_unique_count'=>$mutual])."\n";
    return in_array($state,['completed_read_only','terminal_quota_stop_no_replay'],true)?0:2;
}
if(($argv[1]??'')==='--self-test'){s942_need(s942_norm('АНЕКС  ТУР')==='анекс тур','norm');echo "MATCH_LIVE942_SAMO_ANEX_REFRESH_V1_SELFTEST_OK\n";exit;}
s942_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$plan=(string)getenv('MATCH_PLAN_PATH');$sha=(string)getenv('MATCH_SOURCE_SHA');
s942_need(is_dir($root)&&is_dir($dir)&&basename($dir)===OP&&is_file($plan)&&preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'runtime_scope');
exit(s942_execute($root,$dir,$plan,$sha));
