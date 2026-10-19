<?php
declare(strict_types=1);
// Owner-requested MATCH verification. No DB, search expansion, or booking methods.
const MPO_OP = 'hotel-match-price-hotels-original-1971-20260914-v1';
function mpo_params(): array {
    return ['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260922','CHECKIN_END'=>'20260922',
        'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>10,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,
        'OPERATORS'=>'5','HOTELS'=>'177152','PACKETTYPE'=>0,'PAGE'=>1];
}
function mpo_json(array $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}
function mpo_write(string $dir,string $file,array $value): string {
    $bytes=mpo_json($value); $f=@fopen($dir.'/'.$file,'x+b');
    if(!$f) throw new RuntimeException('MATCH_OUTPUT_EXISTS');
    try {
        if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f)) throw new RuntimeException('MATCH_WRITE_FAILED');
        if(function_exists('fsync')&&!fsync($f)) throw new RuntimeException('MATCH_SYNC_FAILED');
        rewind($f); if(stream_get_contents($f)!==$bytes) throw new RuntimeException('MATCH_READBACK_FAILED');
    } finally {fclose($f);}
    return hash('sha256',$bytes);
}
function mpo_safe($value,array $secrets,int $depth=0): void {
    if($depth>24) throw new RuntimeException('MATCH_RESPONSE_DEPTH');
    if(is_array($value)) {
        foreach($value as $key=>$item) {
            if(is_string($key)&&preg_match('/^(sid|password|username|nonce|token|access_token|authorization|cookie|passport|email|phone)$/i',$key))
                throw new RuntimeException('MATCH_SENSITIVE_FIELD');
            mpo_safe($item,$secrets,$depth+1);
        }
    } elseif(is_string($value)) {
        $s=$value;
        for($i=0;$i<8;$i++) {
            foreach($secrets as $secret) if($secret!==''&&strpos($s,$secret)!==false) throw new RuntimeException('MATCH_SECRET_ECHO');
            $next=rawurldecode(html_entity_decode($s,ENT_QUOTES|ENT_HTML5,'UTF-8'));
            if($s===$next) return;
            $s=$next;
        }
        throw new RuntimeException('MATCH_ENCODING_DEPTH');
    }
}
function mpo_inspect(array $reply): array {
    if(!isset($reply['PRICES'])||!is_array($reply['PRICES'])) throw new RuntimeException('MATCH_PRICES_MISSING');
    $rows=[]; $keys=[]; $invalid=[];
    foreach($reply['PRICES'] as $index=>$r) {
        if(!is_array($r)) throw new RuntimeException('MATCH_ROW_INVALID');
        $original=$r['original']??null;
        $reason=[];
        if((string)($r['hotelKey']??'')!=='177152') $reason[]='different_andromeda_hotel';
        if((string)($r['operatorKey']??'')!=='5') $reason[]='different_operator';
        if((string)($r['isOperatorHotelKey']??'')!=='0') $reason[]='not_catalog_hotel_key';
        if(!is_array($original)||!preg_match('/^[1-9][0-9]*$/D',(string)($original['hotelKey']??''))) $reason[]='original_hotel_key_missing';
        $rows[]=['row'=>$index,'hotelKey'=>$r['hotelKey']??null,'hotel'=>$r['hotel']??null,
            'hotelName'=>$r['hotelName']??null,'operatorKey'=>$r['operatorKey']??null,
            'isOperatorHotelKey'=>$r['isOperatorHotelKey']??null,'original'=>$original,'reasons'=>$reason];
        if($reason) $invalid[]=$index;
        else $keys[(string)$original['hotelKey']]=true;
    }
    $native=array_keys($keys);sort($native,SORT_NUMERIC);
    return ['returned_rows'=>count($rows),'page'=>$reply['PAGE']??null,'pages_count'=>$reply['PAGES_COUNT']??null,
        'rows'=>$rows,'invalid_row_indexes'=>$invalid,'observed_original_hotel_keys'=>$native,
        'expected_anex_hotel_key'=>4158,
        'direct_pair_verified'=>count($rows)>0&&!$invalid&&array_map('strval',$native)===['4158']];
}
function mpo_self_test(): void {
    $base=['hotelKey'=>177152,'hotel'=>'BARCELO TIRAN SHARM','operatorKey'=>5,'isOperatorHotelKey'=>0,'original'=>['hotelKey'=>4158,'stateKey'=>1]];
    if(!mpo_inspect(['PRICES'=>[$base],'PAGE'=>1,'PAGES_COUNT'=>1])['direct_pair_verified']) throw new RuntimeException('TEST_GOOD');
    foreach([['hotelKey'=>4158],['operatorKey'=>2],['isOperatorHotelKey'=>1],['original'=>[]],['original'=>['hotelKey'=>999]]] as $change)
        if(mpo_inspect(['PRICES'=>[array_replace($base,$change)]])['direct_pair_verified']) throw new RuntimeException('TEST_BAD_ROW');
    if(mpo_inspect(['PRICES'=>[]])['direct_pair_verified']) throw new RuntimeException('TEST_EMPTY');
    try {mpo_safe(['original'=>['sid'=>'fake-secret']],[]);throw new LogicException('TEST_SECRET');} catch(RuntimeException $e){}
    try {mpo_safe(['x'=>'fake%2Dsecret'],['fake-secret']);throw new LogicException('TEST_ENCODED_SECRET');} catch(RuntimeException $e){}
    if(mpo_params()['HOTELS']!=='177152'||mpo_params()['OPERATORS']!=='5') throw new RuntimeException('TEST_PARAMS');
    echo "MATCH_PRICE_ORIGINAL_SELF_TEST_PASS 10 cases\n";
}
function mpo_run(string $dir): int {
    $sha=getenv('GITHUB_SHA')?:'';
    $reservation=json_decode((string)file_get_contents($dir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);
    if(($reservation['operation_id']??'')!==MPO_OP||($reservation['source_sha']??'')!==$sha
        ||($reservation['state']??'')!=='reserved_before_supplier_access'||($reservation['params']??null)!==mpo_params()
        ||getenv('GITHUB_RUN_ATTEMPT')!=='1') throw new RuntimeException('MATCH_RESERVATION_INVALID');
    mpo_write($dir,'started.json',['operation_id'=>MPO_OP,'source_sha'=>$sha,'state'=>'started_no_replay']);
    $result=['operation_id'=>MPO_OP,'source_sha'=>$sha,'run_id'=>getenv('GITHUB_RUN_ID'),'action'=>'price',
        'params'=>mpo_params(),'state'=>'started','supplier_calls'=>0,'http_calls'=>[],'database_reads'=>0,
        'mapping_writes'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'no_replay'=>true];
    try {
        require_once __DIR__.'/../../app/integrations/andromeda-client.php';
        require_once __DIR__.'/../../app/integrations/andromeda-transport.php';
        $user=getenv('ANDROMEDA_USERNAME')?:''; $password=getenv('ANDROMEDA_PASSWORD')?:'';
        if($user===''||$password==='') throw new RuntimeException('MATCH_CREDENTIALS_MISSING');
        AnyTourAndromedaClient::validatePriceParams(mpo_params());
        $real=new AnyTourAndromedaTransport(true,false);
        $transport=static function(string $url,array $options=[]) use($real,&$result): array {
            parse_str((string)parse_url($url,PHP_URL_QUERY),$q);
            $action=$q['action']??'';
            $expected=$result['supplier_calls']===0?'login':'price';
            if($result['supplier_calls']>=2||$action!==$expected) throw new RuntimeException('MATCH_ACTION_REJECTED');
            if($action==='price') {
                $p=$q;unset($p['sid'],$p['version'],$p['action']);
                if($p!==array_map('strval',mpo_params())) throw new RuntimeException('MATCH_QUERY_CHANGED');
            }
            ++$result['supplier_calls'];
            $reply=$real($url,$options);
            $result['http_calls'][]=['action'=>$action,'http_status'=>$reply['status']];
            if($action==='price') $result['raw_response_sha256']=hash('sha256',$reply['body']);
            return $reply;
        };
        $client=new AnyTourAndromedaClient($transport,true,false);
        $client->login($user,$password);
        $reply=$client->price(mpo_params());
        $session=$client->privateSession();
        $inspection=mpo_inspect($reply);
        mpo_safe($inspection,[$user,$password,(string)($session['sid']??'')]);
        $result['inspection']=$inspection;
        $result['state']=$inspection['direct_pair_verified']?'verified_price_original':($inspection['returned_rows']===0?'empty_targeted_price':'identity_not_verified');
    } catch(Throwable $e) {
        $result['state']='failed_no_retry';
        $message=$e->getMessage();
        $result['error_code']=preg_match('/^(MATCH|ANDROMEDA)_[A-Z_]+$/D',$message)?$message:'MATCH_EXECUTION_FAILED';
    }
    $hash=mpo_write($dir,'result.json',$result);
    mpo_write($dir,'receipt.json',['operation_id'=>MPO_OP,'source_sha'=>$sha,'run_id'=>getenv('GITHUB_RUN_ID'),
        'state'=>$result['state'],'supplier_calls'=>$result['supplier_calls'],'result_sha256'=>$hash,
        'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$hash,'mapping_writes'=>0,'no_replay'=>true]);
    echo mpo_json($result);
    return $result['state']==='verified_price_original'?0:2;
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    if(($argv[1]??'')==='--self-test') {mpo_self_test();exit(0);}
    if(($argv[1]??'')==='--params') {echo mpo_json(mpo_params());exit(0);}
    exit(mpo_run($argv[1]??''));
}
