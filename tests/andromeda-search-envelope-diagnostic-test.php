<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/integrations/andromeda-search-envelope-diagnostic.php';

$checks=0;
$assert=static function($actual,$expected,string $label)use(&$checks):void{
    ++$checks;
    if($actual!==$expected){
        fwrite(STDERR,$label.': expected '.var_export($expected,true).', got '.var_export($actual,true)."\n");
        exit(1);
    }
};
$request=['generation'=>17001701,'params'=>[]];
$base=[
    'provider'=>'andromeda','search_ref'=>str_repeat('a',64),'pages_count'=>29,'page'=>29,
    'generation'=>17001701,'status'=>'complete','hotels'=>[['local_id'=>1]],'grouped'=>true,
    'first_page_only'=>false,'external_search_pending'=>false,'received_offers'=>100,'mapped_offers'=>90,
];
$code=static fn($search)=>AnyTourAndromedaSearchEnvelopeDiagnosticV1::exceptionCode($request,$search);

$assert($code($base),null,'complete accepted');
$partial=$base;$partial['status']='partial';
$assert($code($partial),null,'drained partial accepted');
$interrupted=$partial;$interrupted['page']=28;$interrupted['external_search_pending']=true;
$assert($code($interrupted),null,'interrupted live partial accepted as incomplete evidence');
$empty=$base;$empty['pages_count']=0;$empty['page']=1;$empty['status']='complete';$empty['hotels']=[];$empty['received_offers']=0;$empty['mapped_offers']=0;
$assert($code($empty),null,'terminal empty accepted');

$assert($code(null),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_NOT_ARRAY','not array');
$x=$base;$x['provider']='other';$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PROVIDER','provider');
$x=$base;$x['search_ref']='bad';$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_SEARCH_REF','search ref');
$x=$base;$x['pages_count']=-1;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PAGES_COUNT','pages count');
$x=$base;$x['status']='pending';$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_STATUS','status');

$x=$partial;$x['page']=28;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_NOT_DRAINED','partial drained');
$x=$partial;$x['grouped']=false;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_GROUPED','partial grouped');
$x=$partial;$x['first_page_only']=true;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_FIRST_PAGE','partial first page');
$x=$partial;$x['external_search_pending']=true;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_EXTERNAL_PENDING','partial pending');
$x=$partial;$x['received_offers']='100';$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_RECEIVED','partial received');
$x=$partial;$x['mapped_offers']=-1;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_PARTIAL_MAPPED','partial mapped');

$x=$empty;$x['page']=2;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_TERMINAL_EMPTY_PAGE','empty page');
$x=$empty;$x['generation']=17001702;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_TERMINAL_EMPTY_GENERATION','empty generation');
$x=$empty;$x['status']='partial';$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_TERMINAL_EMPTY_STATUS','empty status');
$x=$empty;$x['hotels']=[['local_id'=>1]];$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_TERMINAL_EMPTY_HOTELS','empty hotels');
$x=$empty;$x['grouped']=false;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_TERMINAL_EMPTY_GROUPED','empty grouped');
$x=$empty;$x['external_search_pending']=true;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_TERMINAL_EMPTY_EXTERNAL_PENDING','empty pending');
$x=$empty;$x['received_offers']=1;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_TERMINAL_EMPTY_RECEIVED','empty received');
$x=$empty;$x['mapped_offers']=1;$assert($code($x),'ANDROMEDA_LOCAL_COLLECTOR_SEARCH_TERMINAL_EMPTY_MAPPED','empty mapped');

$source=file_get_contents(__DIR__.'/../scripts/ops/andromeda_local_offer_collect.php');
$assert(is_string($source)&&str_contains($source,"andromeda-search-envelope-diagnostic.php';"),true,'collector loads diagnostic');
$assert(is_string($source)&&str_contains($source,'AnyTourAndromedaSearchEnvelopeDiagnosticV1::exceptionCode($req,$search)'),true,'collector invokes diagnostic');
$assert(is_string($source)&&str_contains($source,'AnyTourAndromedaLocalOfferCollectorV1::collect('),true,'authoritative collector still invoked');

echo "Andromeda search-envelope diagnostic: {$checks} checks passed; supplier/server/DB calls=0\n";
