<?php
declare(strict_types=1);

// Keep the already-accepted provider/session/price implementation immutable here.
// This public entry point only adds sequential orchestration for a normal grouped search.
require_once __DIR__.'/api-andromeda-search3-preview-core.php';
$andromedaPageOrchestrator=is_file(__DIR__.'/app/integrations/andromeda-search3-page-orchestrator.php')
    ?__DIR__.'/app/integrations/andromeda-search3-page-orchestrator.php'
    :__DIR__.'/../app/integrations/andromeda-search3-page-orchestrator.php';
require_once $andromedaPageOrchestrator;

function anytour_andromeda_search3_http_paginated(): void {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
    if(!is_file(__DIR__.'/.andromeda-private.php'))anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    $config=require __DIR__.'/.andromeda-private.php';
    if(($config['enabled']??false)!==true)anytour_anex_search3_out(['ok'=>false,'error'=>'not_found'],404);
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')anytour_anex_search3_out(['ok'=>false,'error'=>'method_not_allowed'],405);
    if(!in_array($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin',['same-origin','none'],true))anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
    if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='AnyTourSearch3' || (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN']!=='https://anytoour.ru'))anytour_anex_search3_out(['ok'=>false,'error'=>'forbidden'],403);
    if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    $raw=file_get_contents('php://input',false,null,0,16385);
    if(strlen($raw)>16384)anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    session_name('ANYTOUR_ANDROMEDA_SEARCH3');
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Lax','path'=>'/_preview/search3-anex-candidate/']);
    if(!session_start())anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],503);
    $session=session_id();session_write_close();
    try{
        $request=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($request))throw new InvalidArgumentException();
        $root=realpath($_SERVER['DOCUMENT_ROOT']??'');if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException();
        require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $pdo=v2_data_db();$saved=anytour_andromeda_search3_catalog($config,$request);
        $saved['excluded_operator_ids']=$config['excluded_operator_ids']??[];
        anytour_andromeda_search3_params($request,$pdo,$saved);
        if(isset($request['action'])&&!in_array($request['action'],['offer_detail','hotel_offers'],true))throw new InvalidArgumentException();
        if(($request['action']??null)==='offer_detail'){
            $data=anytour_andromeda_search3_detail($request,$pdo,$saved,$config,$session);
        }else{
            $runner=static fn(array $pageRequest):array=>anytour_andromeda_search3_run($pageRequest,$pdo,$saved,$config,$session);
            $data=AnyTourAndromedaSearch3PageOrchestrator::run($request,$runner);
            $data=anytour_andromeda_search3_record_response($data);
        }
        anytour_anex_search3_out(['ok'=>true,'data'=>$data],200);
    }catch(OverflowException $e){anytour_anex_search3_out(['ok'=>false,'error'=>'monthly_quota_exhausted'],429);
    }catch(DomainException $e){anytour_anex_search3_out(['ok'=>false,'error'=>'search_not_supported'],422);
    }catch(InvalidArgumentException $e){anytour_anex_search3_out(['ok'=>false,'error'=>'invalid_request'],400);
    }catch(Throwable $e){anytour_anex_search3_out(['ok'=>false,'error'=>'supplier_unavailable'],502);}
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)anytour_andromeda_search3_http_paginated();
