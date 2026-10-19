<?php
declare(strict_types=1);

/** Shape-independent discovery; money semantics and selection authority are never inferred. */
function anytour_original_markup_scan_v3(array $root): array
{
    $hits=[];$nodes=0;$detailKeys=0;$transportKeys=0;
    $skip=['clients','client','tourists','tourist','passengers','passenger','people','person','sid','password','token','auth','credentials','passport','email','phone'];
    $known=['claimDocument','variants','variant','transports','transport','details','detail','services','service','groups','group','moneys','money','markup','price','amount','value','currency','currencyAlias','currencyCode'];
    $safe=null;
    $safe=static function(mixed $v,int $depth=0)use(&$safe):array{
        if($depth>8)throw new RuntimeException('MARKUP_SCAN_DEPTH');
        if($v===null)return ['state'=>'null'];
        if(is_bool($v))return ['state'=>'invalid_boolean'];
        if(is_array($v)){
            $out=[];
            foreach($v as $k=>$child){
                if(is_int($k)||in_array($k,['amount','value','price','currency','currencyAlias','currencyCode'],true))$out[(string)$k]=$safe($child,$depth+1);
            }
            return ['state'=>'structured','fields'=>$out];
        }
        if(is_int($v)||is_float($v)||is_string($v)){
            $text=(string)$v;
            if(preg_match('/\A-?(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?\z/D',$text))return ['state'=>preg_match('/[1-9]/',$text)?'reported':'reported_zero','value'=>$text];
            if(is_string($v)&&preg_match('/\A[A-Z]{3}\z/D',$v))return ['state'=>'currency_code','value'=>$v];
        }
        return ['state'=>'unparsed','type'=>gettype($v)];
    };
    $walk=null;
    $walk=static function(array $node,string $path,bool $inTransport,int $depth)use(&$walk,&$nodes,&$hits,&$detailKeys,&$transportKeys,$skip,$known,$safe):void{
        if($depth>32||++$nodes>250000)throw new RuntimeException('MARKUP_SCAN_COMPLEXITY');
        foreach($node as $key=>$value){
            if(is_int($key)){$next=$path.'['.$key.']';$under=$inTransport;}
            else{
                $lower=strtolower($key);if(in_array($lower,$skip,true))continue;
                if($lower==='details'||$lower==='detail')++$detailKeys;
                if($lower==='transport'||$lower==='transports')++$transportKeys;
                $label=in_array($key,$known,true)?$key:'_';$next=$path.'.'.$label;
                $under=$inTransport||in_array($lower,['transport','transports'],true);
                if($lower==='markup'){
                    $fields=['markup'=>$safe($value)];
                    foreach(['currency','currencyAlias','currencyCode','amount','price','quantity','count']as$k)if(array_key_exists($k,$node))$fields[$k]=$safe($node[$k]);
                    $hits[]=['path'=>$next,'inside_transport'=>$inTransport,'fields'=>$fields];
                    if(count($hits)>20000)throw new RuntimeException('MARKUP_SCAN_COMPLEXITY');
                }
            }
            if(is_array($value))$walk($value,$next,$under,$depth+1);
        }
    };
    $walk($root,'$',false,0);
    return ['markup_count'=>count($hits),'markup'=>$hits,'details_key_count'=>$detailKeys,'transport_key_count'=>$transportKeys,'array_nodes'=>$nodes];
}

function anytour_original_markup_discovery_v3(string $directory,int $cutoff):array
{
    require_once __DIR__.'/andromeda_original_transport_retained_v2.php';
    $bound=anytour_original_transport_retained_v2($directory,$cutoff);
    $wanted=[];foreach($bound['rows']as$row)$wanted[$row['package_checkpoint_sha256']]=$row['context'];
    $rows=[];$skips=[];$examined=0;
    foreach(new DirectoryIterator($directory)as$entry){
        if($entry->isDot()||$entry->isLink()||!$entry->isFile()||$entry->getMTime()>$cutoff)continue;
        if(!preg_match('/\A[a-f0-9]{64}-[0-9]{1,12}-[0-9]{1,4}-offer_[a-f0-9]{64}-package\.json\z/D',$entry->getFilename()))continue;
        if(++$examined>600)throw new RuntimeException('MARKUP_SCAN_FILE_BUDGET');
        try{
            $size=$entry->getSize();if($size<2||$size>3000000)continue;
            $bytes=file_get_contents($entry->getPathname());if(!is_string($bytes)||strlen($bytes)!==$size)throw new RuntimeException('MARKUP_SCAN_READ');
            $hash=hash('sha256',$bytes);if(!isset($wanted[$hash]))continue;
            $env=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);$record=$env['record']??[];$raw=$record['private_package']??null;
            if(!is_array($raw))throw new RuntimeException('MARKUP_SCAN_PACKAGE');
            $storedHash=$record['package_sha256']??null;$computed=hash('sha256',json_encode($raw,JSON_THROW_ON_ERROR));
            if(!is_string($storedHash)||!hash_equals($storedHash,$computed))throw new RuntimeException('MARKUP_SCAN_INTEGRITY');
            $scan=anytour_original_markup_scan_v3($raw);
            $rows[]=['package_checkpoint_sha256'=>$hash,'package_integrity_verified'=>true,'context'=>$wanted[$hash],'scan'=>$scan];
        }catch(Throwable $e){$reason=$e->getMessage();if(!preg_match('/\A[A-Z_]{1,96}\z/D',$reason))$reason='MARKUP_SCAN_INVALID';$skips[$reason]=($skips[$reason]??0)+1;}
    }
    ksort($skips);
    return ['version'=>3,'state'=>'completed_read_only','bound_packages'=>$bound['reported'],'inspected_originals'=>count($rows),'skips'=>$skips,'rows'=>$rows,
        'supplier_calls'=>0,'db_connections'=>0,'db_writes'=>0,'retained_file_writes'=>0,'get_flights_calls'=>0,'changeservice_calls'=>0,'calc_calls'=>0,'booking_calls'=>0,'finalPriceReady'=>false];
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try{if(count($argv)!==3||!ctype_digit($argv[2]))throw new RuntimeException('MARKUP_SCAN_ARGUMENT');echo json_encode(anytour_original_markup_discovery_v3($argv[1],(int)$argv[2]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;}
    catch(Throwable $e){fwrite(STDERR,"MARKUP_DISCOVERY_V3_FAILED\n");exit(2);}
}
