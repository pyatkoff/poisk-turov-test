<?php
declare(strict_types=1);

// Execute the actual CLI with local boundary doubles only. No supplier, real DB,
// private config, or deployed LOCAL runtime is accessed by these wiring tests.
function familyCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
}

function familyCli(array $extra): array
{
    $dir=sys_get_temp_dir().'/anytour-andromeda-family-'.bin2hex(random_bytes(8));
    if (!mkdir($dir,0700)) throw new RuntimeException('fixture directory');
    $put=static function(string $path,string $content)use($dir):void{
        $file=$dir.'/'.$path;
        if (!is_dir(dirname($file))) mkdir(dirname($file),0700,true);
        if (file_put_contents($file,$content)===false) throw new RuntimeException('fixture write');
    };
    $process=null;
    try {
        $source=file_get_contents(__DIR__.'/../scripts/ops/andromeda_local_offer_collect.php');
        if (!is_string($source)) throw new RuntimeException('CLI source');
        $put('payload/scripts/ops/andromeda_local_offer_collect.php',$source);
        $put('payload/app/integrations/andromeda-local-offer-collector.php', '<?php
function familyTrace(string $stage, array $value=[]):void {
    file_put_contents('.var_export($dir.'/trace.jsonl',true).',json_encode([$stage,$value],JSON_THROW_ON_ERROR)."\n",FILE_APPEND);
}
familyTrace("runtime");
final class AnyTourAndromedaLocalOfferCollectorV1 {
    public static function collect($request,$search,$load,$allow,$capture,$autosave,$maxCaptures,$mode,$seconds):array {
        familyTrace("collector",["request"=>$request,"maxCaptures"=>$maxCaptures,"mode"=>$mode,"seconds"=>$seconds]);
        $search($request);
        $autosave($request,str_repeat("a",64),$request["generation"]);
        return ["status"=>"complete","test_boundary_only"=>true];
    }
}
');
        $put('payload/v2/api-andromeda-search3-preview.php','<?php
function anytour_andromeda_search3_catalog($config,$request):array { familyTrace("catalog",$request);return []; }
function anytour_andromeda_search3_run_pages($request,$runner):array { familyTrace("pages",$request);return $runner($request); }
function anytour_andromeda_search3_run($request,$db,$saved,$config,$session):array { familyTrace("search",$request);return []; }
');
        $put('payload/app/integrations/andromeda-saved-package-runtime.php','<?php
function anytour_andromeda_capture_selected_package(...$args):array { throw new RuntimeException("capture must not run in fixture"); }
');
        $put('payload/app/integrations/andromeda-anytour-offer-autosave-cache-runtime.php','<?php
function anytour_andromeda_anytour_offer_autosave_cache_runtime($request,$db,$saved,$directory,$ref,$generation):array {
    familyTrace("autosave",$request);return ["published"=>false,"reason"=>"fixture_only"];
}
');
        $put('private/config.php','<?php familyTrace("private_config");return '.var_export([
            'enabled'=>true,'catalog_path'=>$dir.'/private/catalog.json','excluded_operator_ids'=>['5'],
        ],true).';');
        mkdir($dir.'/private/searches',0700);
        $put('anytoour.ru/config.php','<?php familyTrace("site_config");');
        $put('anytoour.ru/data/db-v1.php','<?php
final class FamilyFixturePdo extends PDO {
    public function __construct() {}
    public function setAttribute(int $attribute, mixed $value):bool { return true; }
}
function v2_data_db():PDO { familyTrace("db_double");return new FamilyFixturePdo(); }
');
        $put('anytoour.ru/_preview/search3-local-candidate/data/anytour-offer-snapshot-ingest-v1.php','<?php // Fixture path only.');
        $command=array_merge([PHP_BINARY,$dir.'/payload/scripts/ops/andromeda_local_offer_collect.php',
            '--site-root='.$dir.'/anytoour.ru','--private-config='.$dir.'/private/config.php',
            '--source-sha='.str_repeat('f',40),'--date-from=2026-10-30','--date-to=2026-10-31',
            '--generation=73','--departure=3','--country=4','--nights=9','--adults=2',
        ],$extra);
        $pipes=[];
        $process=proc_open($command,[0=>['file','/dev/null','r'],1=>['file',$dir.'/stdout','w'],2=>['file',$dir.'/stderr','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('fixture process');
        $deadline=microtime(true)+5;
        do {
            $status=proc_get_status($process);
            if (!$status['running']) break;
            if (microtime(true)>$deadline) throw new RuntimeException('fixture timeout');
            usleep(10000);
        } while (true);
        $closed=proc_close($process);$process=null;
        $trace=[];
        if (is_file($dir.'/trace.jsonl')) foreach(file($dir.'/trace.jsonl',FILE_IGNORE_NEW_LINES) as $line) {
            $trace[]=json_decode($line,true,64,JSON_THROW_ON_ERROR);
        }
        return ['code'=>$closed>=0?$closed:$status['exitcode'],'stdout'=>file_get_contents($dir.'/stdout'),
            'stderr'=>file_get_contents($dir.'/stderr'),'trace'=>$trace];
    } finally {
        if (is_resource($process)) { proc_terminate($process,9);proc_close($process); }
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $file) {
            if ($file->isDir()&&!$file->isLink()) rmdir($file->getPathname());else unlink($file->getPathname());
        }
        rmdir($dir);
    }
}

$positive=[
    [[],[]], [['--child-ages='],[]], [['--child-ages=7,3'],[3,7]],
    [['--child-ages=7,4'],[4,7]], [['--child-ages=5,5'],[5,5]],
    [['--child-ages=0'],[0]], [['--child-ages=17'],[17]],
    [['--child-ages=17,0,5'],[0,5,17]],
    [['--child-ages=3,7','--capture-mode=all','--max-captures=3','--max-capture-seconds=7'],[3,7]],
];
$adultParams=null;
foreach($positive as $index=>[$args,$ages]) {
    $run=familyCli($args);
    familyCheck($run['code']===0,'family CLI exits successfully '.$index.': '.$run['stderr']);
    $stages=array_column($run['trace'],0);
    familyCheck($stages===['runtime','private_config','site_config','db_double','catalog','collector','pages','search','autosave'],'one ordered pass '.$index);
    $events=array_column($run['trace'],1,0);
    $request=$events['catalog'];$params=$request['params'];
    if ($adultParams===null) $adultParams=$params;
    familyCheck($params['childs']===$ages,'exact child ages at catalog '.$index.': '.json_encode($params['childs']));
    familyCheck(array_replace($params,['childs'=>[]])===$adultParams,'only party ages differ '.$index);
    familyCheck($params['adults']===2&&$params['departureId']==='3'&&$params['countryId']==='4'
        &&$params['nightsFrom']===9&&$params['nightsTo']===9&&$params['dateFrom']==='2026-10-30'
        &&$params['dateTo']==='2026-10-31'&&$request['generation']===73,'explicit context unchanged');
    foreach(['pages','search','autosave'] as $stage) familyCheck($events[$stage]===$request,'same exact family request at '.$stage);
    familyCheck($events['collector']['request']===$request,'collector receives family request');
    familyCheck($events['collector']['mode']===($index===8?'all':'non_external_only'),'capture mode unchanged');
    familyCheck($events['collector']['maxCaptures']===($index===8?3:2)&&$events['collector']['seconds']===($index===8?7:0),'capture limits unchanged');
}
$invalid=['18','-1','1,2,3,4','7,','7,,3','x','3.5','07',' 7','7, 3','[]','7;echo invalid'];
foreach($invalid as $value) {
    $run=familyCli(['--child-ages='.$value]);
    familyCheck($run['code']!==0&&$run['stdout']==='','invalid ages not successful: '.$value);
    familyCheck($run['trace']===[],'invalid ages rejected before runtime/config/DB: '.$value);
    familyCheck(str_contains($run['stderr'],'ANDROMEDA_COLLECTOR_'),'bounded validation error');
}
echo 'ANDROMEDA_COLLECTOR_FAMILY_CLI_OK positive='.count($positive).' invalid='.count($invalid).' exact_request=1 supplier=0 db=0'."\n";
