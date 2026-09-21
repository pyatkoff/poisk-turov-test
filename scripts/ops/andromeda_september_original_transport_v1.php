<?php
declare(strict_types=1);

/** One new September original-response experiment. Not a quote/booking consumer. */
function september_action_v1(string $action, array $counts): void
{
    $limits = ['login'=>1, 'price'=>20, 'broninit'=>12];
    if (!isset($limits[$action]) || ($counts[$action] ?? 0) >= $limits[$action]
        || array_sum($counts) >= 33) throw new RuntimeException('SEPTEMBER_ACTION_BUDGET');
}

/** Diverse raw-offer specimens; no first-N hotel sampling or mapping mutation. */
function september_select_v1(array $candidates): array
{
    $unique=[];
    foreach ($candidates as $row) {
        $id=$row['id']??null; $c=$row['identity']??[];
        if (!is_string($id)||$id===''||strlen($id)>2048
            ||!in_array($c['operatorKey']??null,['115','342','315'],true)
            ||!is_string($c['tourKey']??null)||!preg_match('/^[1-9][0-9]{0,18}$/D',$c['tourKey'])
            ||!in_array($c['checkIn']??null,['2026-09-20','2026-09-21','2026-09-22'],true)
            ||!in_array($c['nights']??null,[7,8,9,10],true)
            ||($c['adult']??null)!==2||($c['child']??null)!==0) continue;
        $unique[hash('sha256',$id)]=$row;
    }
    $pool=array_values($unique);
    usort($pool,static fn($a,$b)=>[$a['identity']['checkIn'],$a['identity']['nights'],hash('sha256',$a['id'])]
        <=>[$b['identity']['checkIn'],$b['identity']['nights'],hash('sha256',$b['id'])]);
    $selected=[];$families=[];$used=[];
    foreach(['115','342','315'] as $op) {
        $count=0;
        foreach($pool as $row){
            $c=$row['identity'];$family=$op.':'.$c['tourKey'];
            if($c['operatorKey']!==$op||isset($families[$family]))continue;
            $row['selection_basis']='distinct_tourKey';$selected[]=$row;
            $families[$family]=true;$used[hash('sha256',$row['id'])]=true;
            if(++$count===3)break;
        }
    }
    $seeds=$selected;
    foreach($seeds as $seed){
        if(count($selected)>=12)break;
        $s=$seed['identity'];
        foreach(['nights','checkIn'] as $vary){
            $found=false;
            foreach($pool as $row){
                $c=$row['identity'];$digest=hash('sha256',$row['id']);
                if(isset($used[$digest]))continue;
                if($c['operatorKey']!==$s['operatorKey']||$c['tourKey']!==$s['tourKey']
                    ||($c['programKey']??null)!==($s['programKey']??null))continue;
                $fixed=$vary==='nights'?'checkIn':'nights';
                if($c[$fixed]!==$s[$fixed]||$c[$vary]===$s[$vary])continue;
                $row['selection_basis']='vary_'.$vary;$selected[]=$row;$used[$digest]=true;$found=true;break;
            }
            if($found)break;
        }
    }
    return $selected;
}

function september_selftest_v1(): void
{
    $n=0;$ok=static function(bool $v)use(&$n):void{++$n;if(!$v)throw new RuntimeException('SEPTEMBER_TEST_FAILURE');};
    $row=static fn(string $op,string $tour,string $day,int $nights,string $id):array=>['id'=>$id,'identity'=>[
        'operatorKey'=>$op,'tourKey'=>$tour,'programKey'=>'5','checkIn'=>$day,'nights'=>$nights,'adult'=>2,'child'=>0]];
    $pool=[];foreach(['115','342','315']as$op)foreach(['1','2','3','4']as$tour)foreach([7,10]as$night)
        $pool[]=$row($op,$tour,'2026-09-20',$night,"$op-$tour-$night");
    $r=september_select_v1($pool);$ok(count($r)===12);$ok(count(array_unique(array_column($r,'id')))===12);
    foreach(['115','342','315']as$op)$ok(count(array_filter($r,static fn($x)=>$x['identity']['operatorKey']===$op))>=3);
    $ok(count(array_filter($r,static fn($x)=>$x['selection_basis']==='vary_nights'))===3);
    $ok(september_select_v1(array_reverse($pool))===$r);
    $ok(september_select_v1(array_merge($pool,$pool))===$r);
    foreach([['5','1','2026-09-20',7],['115','1','2026-09-19',7],['115','1','2026-10-20',7],['115','1','2026-09-20',11]]as$bad)
        $ok(september_select_v1([$row(...[...$bad,'bad'])])===[]);
    $bad=$pool[0];$bad['identity']['adult']=3;$ok(september_select_v1([$bad])===[]);
    $bad=$pool[0];$bad['identity']['child']=true;$ok(september_select_v1([$bad])===[]);
    $dates=[$row('115','1','2026-09-20',7,'a'),$row('115','1','2026-09-22',7,'b')];
    $r=september_select_v1($dates);$ok(count($r)===2&&$r[1]['selection_basis']==='vary_checkIn');
    foreach(['get_flights','changeservice','calc','book','all']as$action){
        try{september_action_v1($action,[]);$ok(false);}catch(RuntimeException $e){$ok($e->getMessage()==='SEPTEMBER_ACTION_BUDGET');}
    }
    foreach(['login'=>1,'price'=>20,'broninit'=>12]as$action=>$limit){
        september_action_v1($action,[$action=>$limit-1]);$ok(true);
        try{september_action_v1($action,[$action=>$limit]);$ok(false);}catch(RuntimeException $e){$ok($e->getMessage()==='SEPTEMBER_ACTION_BUDGET');}
    }
    echo 'SEPTEMBER_ORIGINAL_SELFTEST_OK checks='.$n.PHP_EOL;
}

function september_original_run_v1(string $site, string $privateConfig, string $ledger): array
{
    $operation='int-september-original-transport-1717-20260919-v1';
    if(realpath($site)!==$site||basename($site)!=='anytoour.ru'||is_link($site)
        ||!is_file($privateConfig)||is_link($privateConfig)||!is_dir($ledger)||is_link($ledger)
        ||basename($ledger)!==$operation||basename(dirname($ledger))!=='.anytour-ops')throw new RuntimeException('SEPTEMBER_ROOT');
    umask(0077);ini_set('zend.exception_ignore_args','1');
    $write=static function(string $name,string $bytes)use($ledger):string{
        if(!preg_match('/^[a-z0-9._-]{1,100}$/D',$name))throw new RuntimeException('SEPTEMBER_FILE_NAME');
        $path=$ledger.'/'.$name;$f=fopen($path,'x');if(!$f)throw new RuntimeException('SEPTEMBER_WRITE');
        try{if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f)||(function_exists('fsync')&&!fsync($f)))throw new RuntimeException('SEPTEMBER_WRITE');}
        finally{fclose($f);}
        $hash=hash('sha256',$bytes);if(!hash_equals($hash,(string)hash_file('sha256',$path)))throw new RuntimeException('SEPTEMBER_READBACK');return $hash;
    };
    $json=static fn(array $x):string=>json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    $out=['operation'=>$operation,'scope'=>['departureId'=>1,'countryId'=>4,'dateFrom'=>'2026-09-20','dateTo'=>'2026-09-22','nightsFrom'=>7,'nightsTo'=>10,'adult'=>2,'child'=>0],
        'counts'=>['login'=>0,'price'=>0,'broninit'=>0],'price_rows'=>0,'raw_operator_rows'=>[],'raw_tourKeys'=>[],
        'price_pagination_complete'=>false,'specimens'=>[],'status'=>'reserved','db_writes'=>0,'autosave_calls'=>0,
        'get_flights_calls'=>0,'changeservice_calls'=>0,'calc_calls'=>0,'booking_calls'=>0,'publication'=>false,'finalPriceReady'=>false];
    $progress=0;$save=static function()use(&$out,&$progress,$write,$json):void{$write(sprintf('progress.%03d.json',++$progress),$json($out));};
    $safeError=static fn(Throwable $e):string=>preg_match('/^[A-Z][A-Z0-9_:-]{1,100}$/D',$e->getMessage())?$e->getMessage():'SEPTEMBER_UNCLASSIFIED';
    $pdo=null;
    try{
        $save();$root=dirname(__DIR__,2);
        require_once $root.'/v2/api-andromeda-search3-preview.php';
        require_once $root.'/app/integrations/andromeda-operator-config.php';
        require_once $root.'/scripts/diagnostics/andromeda_original_transport_facts.php';
        require_once $root.'/scripts/ops/andromeda_original_markup_discovery_v3.php';
        $config=require $privateConfig;if(!is_array($config)||($config['enabled']??null)!==true)throw new RuntimeException('SEPTEMBER_CONFIG');
        require_once $site.'/config.php';require_once (is_file($site.'/data/db-v1.php')?$site.'/data/db-v1.php':$site.'/v2/data/db-v1.php');
        $pdo=v2_data_db();$pdo->exec('START TRANSACTION READ ONLY');
        $request=['generation'=>17171960,'params'=>['departureId'=>'1','countryId'=>'4','dateFrom'=>'2026-09-20','dateTo'=>'2026-09-22',
            'nightsFrom'=>7,'nightsTo'=>10,'adults'=>2,'childs'=>[],'meal'=>'','hotelCategory'=>'','hotelRating'=>'','hotelTypes'=>[],
            'hotelIds'=>[],'hotelServices'=>[],'arrivalId'=>'','regionIds'=>[],'subregionIds'=>[],'operatorIds'=>[],
            'priceFrom'=>'','priceTo'=>'','currency'=>'RUB','onlyCharter'=>false,'onlyDirect'=>false]];
        $saved=anytour_andromeda_search3_catalog($config,$request);
        $saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
        $criteria=anytour_andromeda_search3_params($request,$pdo,$saved);$pdo->rollBack();
        $out['criteria']=$criteria;
        $monthly=dirname((string)$config['catalog_path']);$last=0.0;$rawReplies=[];
        $http=static function(string $url,array $options)use(&$out,&$last,$monthly,$write,$json,$save,&$rawReplies):array{
            parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$action=$q['action']??'';
            september_action_v1($action,$out['counts']);$i=array_sum($out['counts'])+1;
            $write(sprintf('request.%03d.reserved.json',$i),$json(['action'=>$action,'ordinal'=>$i]));
            $wait=1.05-(microtime(true)-$last);if($wait>0)usleep((int)ceil($wait*1000000));
            anytour_andromeda_search3_budget($monthly);++$out['counts'][$action];$last=microtime(true);$save();
            $transport=new AnyTourAndromedaTransport($action==='price',$action==='broninit');
            $reply=$transport($url,$options);
            if($action!=='login'&&is_string($reply['body']??null)){
                $hash=$write(sprintf('raw.%03d.%s.json',$i,$action),$reply['body']);
                $rawReplies[$action]=['sha256'=>$hash,'body'=>$reply['body'],'http_status'=>$reply['status']??null,'ordinal'=>$i];
            }
            return $reply;
        };
        $client=new AnyTourAndromedaClient($http,true,false);$client->ensureLogin((string)$config['username'],(string)$config['password']);
        $session=$client->privateSession();$target=1;$pool=[];$ref=hash('sha256',$operation);
        for($page=1;$page<=$target&&$page<=20;++$page){
            $pc=$criteria;$pc['PAGE']=$page;
            $c=new AnyTourAndromedaClient($http,true,false);$c->restorePrivateSession($session);$payload=$c->price($pc);
            $out['price_rows']+=count($payload['PRICES']);$target=max($page,(int)$payload['PAGES_COUNT']);
            $ids=[];foreach($payload['PRICES']as$raw){
                if(!is_array($raw))continue;$op=(string)($raw['operatorKey']??'unknown');
                if(!preg_match('/^[0-9]{1,12}$/D',$op))$op='unknown';
                $out['raw_operator_rows'][$op]=($out['raw_operator_rows'][$op]??0)+1;
                $tour=(string)($raw['tourKey']??'');if(preg_match('/^[1-9][0-9]{0,18}$/D',$tour))$out['raw_tourKeys'][$op][$tour]=true;
                $id=$raw['id']??null;if(!is_string($id)||$id===''||strlen($id)>2048)continue;
                $offerRef='offer_'.hash('sha256',json_encode([$ref,17171960,$op,$id],JSON_THROW_ON_ERROR));$ids[$offerRef]=['id'=>$id,'raw'=>$raw];
            }
            $normal=AnyTourAndromedaNormalizer::page($payload,$pc,$ref,17171960);
            foreach($normal['offers']as$offer){
                $source=$ids[$offer['offer_ref']]??null;if($source===null)continue;$t=$offer['transport_context'];
                $pool[]=['id'=>$source['id'],'identity'=>['operatorKey'=>$offer['operator_ref'],'tourKey'=>$t['tour_ref'],
                    'programKey'=>$t['program_ref'],'spoKey'=>$t['spo_ref'],'checkIn'=>$offer['check_in'],'nights'=>$offer['nights'],
                    'adult'=>$offer['adults'],'child'=>$offer['children'],'currency'=>$offer['price']['currency']],
                    'base_price'=>$offer['price']['amount'],'price_page'=>$page,'price_body_sha256'=>$rawReplies['price']['sha256'],
                    'price_scan'=>anytour_original_markup_scan_v3($source['raw'])];
            }
            $out['price_pages_reported']=$target;$out['price_pagination_complete']=$page>=$target;$save();
        }
        $selected=september_select_v1($pool);$out['eligible_normalized_rows']=count($pool);$out['selected_count']=count($selected);$save();
        [$opLogin,$opPassword]=anytour_andromeda_operator_credentials_from_config($config);
        foreach($selected as $index=>$candidate){
            $digest=hash('sha256',$candidate['id']);$write('specimen.'.$digest.'.reserved.json',$json(['supplier_offer_sha256'=>$digest,'context'=>$candidate['identity']]));
            $entry=['context'=>$candidate['identity'],'base_price'=>$candidate['base_price'],'price_page'=>$candidate['price_page'],
                'price_body_sha256'=>$candidate['price_body_sha256'],'selection_basis'=>$candidate['selection_basis'],
                'price_scan'=>$candidate['price_scan'],'status'=>'reserved'];
            try{
                $q=['version'=>'1.01','action'=>'broninit','sid'=>$session['sid'],'claiminc'=>$candidate['id']];
                if($opLogin!==null&&$opPassword!==null){$q['OPERATOR_LOGIN']=$opLogin;$q['OPERATOR_PASSWORD']=$opPassword;}
                $reply=$http('https://gateway.samo.ru/api/?'.http_build_query($q,'','&',PHP_QUERY_RFC3986),[]);
                $raw=$rawReplies['broninit'];$entry['raw_body_sha256']=$raw['sha256'];$entry['http_status']=$raw['http_status'];
                if($raw['http_status']!==200)throw new RuntimeException('SEPTEMBER_HTTP');
                $body=json_decode($raw['body'],true,32,JSON_THROW_ON_ERROR);
                if(!is_array($body))throw new RuntimeException('SEPTEMBER_RESPONSE');
                if(array_key_exists('error',$body)){
                    $entry['supplier_error']=(new AnyTourAndromedaPackageSupplierException($body['error']))->diagnosticFacts();
                    $entry['status']='supplier_error';
                }else{
                    $entry['scan']=anytour_original_markup_scan_v3($body);
                    try{$entry['facts']=AnyTourAndromedaOriginalTransportFacts::fromJson($raw['body'],$candidate['identity']);}
                    catch(Throwable $e){$entry['parser_error']=$safeError($e);}
                    $entry['status']='captured';
                }
            }catch(Throwable $e){$entry['status']='failed_terminal';$entry['failure_class']=$safeError($e);}
            $out['specimens'][]=$entry;$save();
        }
        $out['status']='completed_terminal';
    }catch(Throwable $e){$out['status']='failed_terminal';$out['failure_class']=$safeError($e);}
    finally{if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();}
    $save();$write('result.json',$json($out));return $out;
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{
        if(($argv[1]??'')==='--self-test'){september_selftest_v1();exit(0);}
        if(count($argv)!==4)throw new RuntimeException('SEPTEMBER_ARGUMENTS');
        $r=september_original_run_v1($argv[1],$argv[2],$argv[3]);
        echo json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
        exit($r['status']==='completed_terminal'?0:2);
    }catch(Throwable $e){fwrite(STDERR,"SEPTEMBER_ORIGINAL_FAILED\n");exit(2);}
}
