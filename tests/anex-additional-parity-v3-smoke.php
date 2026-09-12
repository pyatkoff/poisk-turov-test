<?php
declare(strict_types=1);
define('ANYTOUR_ANEX_ADDITIONAL_PARITY_LIBRARY_ONLY', true);
require __DIR__ . '/../scripts/diagnostics/anex_additional_parity_v3.php';

$checks=0;
$assert=static function(bool $ok,string $message)use(&$checks):void{if(!$ok)throw new RuntimeException('FAIL:'.$message);++$checks;};
$anex=[[
    'provider'=>'anex','local_hotel_id'=>1039,'external_hotel_id'=>'33118','date'=>'2026-10-12','nights'=>7,
    'adults'=>2,'children'=>0,'meal_family'=>'ai','room_norm'=>'standard room','placement_norm'=>'dbl','price'=>'120000','currency'=>'RUB',
    'fuel_charge'=>null,'supplier_tour_program_id'=>'778','supplier_currency_id'=>'3','fuel_inclusion_verified'=>false,'final_price_verified'=>false,
]];
$tv=[[
    'provider'=>'tourvisor','local_hotel_id'=>1039,'external_hotel_id'=>'1039','date'=>'2026-10-12','nights'=>7,
    'adults'=>2,'children'=>0,'meal_family'=>'ai','room_norm'=>'standard room','placement_norm'=>'dbl','price'=>'145000','currency'=>'RUB',
    'fuel_charge'=>'25000','supplier_tour_program_id'=>null,'supplier_currency_id'=>null,'fuel_inclusion_verified'=>false,'final_price_verified'=>false,
]];
$pairs=anex_additional_parity_align($anex,$tv);
$assert(count($pairs)===1,'one aligned pair');
$assert(($pairs[0]['basis']??null)==='same_current_local_hotel_date_party_ai_and_exact_room','alignment basis');
$assert(($pairs[0]['identical_supplier_package_verified']??null)===false,'no package overclaim');
$assert(($pairs[0]['anex']['supplier_tour_program_id']??null)==='778','program retained privately');
$bad=$anex;$bad[0]['supplier_tour_program_id']=null;
$assert(anex_additional_parity_align($bad,$tv)===[],'missing program is unusable');
$bad=$tv;$bad[0]['fuel_charge']=null;
$assert(anex_additional_parity_align($anex,$bad)===[],'missing reported fuel is not zero');
$assert(anex_additional_parity_provider_id('03')===null&&anex_additional_parity_provider_id(3)==='3','opaque id guard');
$assert(anex_additional_parity_ai('AI-WITHOUT ALCOHOL')===true,'ai family');
$assert(anex_additional_parity_money('0',true)==='0'&&anex_additional_parity_money('0')===null,'zero semantics');

$tmp=sys_get_temp_dir().'/anex-program2637-'.bin2hex(random_bytes(8));
mkdir($tmp,0700);
$remove=static function(string $path)use(&$remove):void{
    if(is_dir($path)){foreach(scandir($path) as $name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else{unlink($path);}
};
try {
    $checkpoint=['status'=>'completed','data'=>[['price_adult'=>130.0,'price_converted_adult'=>13549.9,'zero'=>0.0]]];
    $bytes=json_encode($checkpoint,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $assert(json_decode($bytes,true)!==$checkpoint,'fixture reproduces JSON integer/float type normalization');
    anex_additional_parity_save($tmp.'/checkpoint.json',$checkpoint);
    $assert(file_get_contents($tmp.'/checkpoint.json')===$bytes,'exact serialized checkpoint survives readback');

    $spec=['experiment_id'=>ANEX_ADDITIONAL_PROGRAM2637_EXPERIMENT,'country'=>'Turkey','date'=>'2026-10-12',
        'nights'=>7,'adults'=>2,'child_ages'=>[],'meal_family'=>'ai','currency'=>'RUB'];
    $assert(anex_additional_parity_input($spec,true)===$spec,'new program has a separate exact operation');
    try { anex_additional_parity_input($spec); $assert(false,'old mode cannot accept new operation'); }
    catch(RuntimeException $e){$assert($e->getMessage()==='ADDITIONAL_PARITY_INVALID_INPUT','cross-mode input refused');}

    $root=$tmp.'/www/anytoour.ru'; mkdir($root.'/_preview/search3-anex-candidate',0700,true);
    mkdir($tmp.'/.anytoour-anex',0700);
    file_put_contents($root.'/config.php',"<?php\n");
    file_put_contents($tmp.'/.anytoour-anex/search3-preview.php',"<?php define('ANEX_B2B_TOKEN','fixture-token');\n");
    $oldPath=$tmp.'/.anytoour-anex/'.ANEX_ADDITIONAL_PARITY_EXPERIMENT.'.json';
    $oldBytes='{"status":"unknown","reason":"ADDITIONAL_PARITY_CHECKPOINT_READBACK"}';
    file_put_contents($oldPath,$oldBytes);
    // No DB, mapping, ANEX search or Tourvisor helpers exist in this fake home.
    // The only provider is this in-process fake; URL fopen is disabled as well.
    $runner=$tmp.'/run.php';
    $child=<<<'PHP'
<?php
final class AnyTourAnexAdditionalPricesClient {
    private $requests=0;
    public function __construct(string $token) {}
    public function additionalPricesDaily(array $criteria):array {
        $expected=['page'=>1,'pageSize'=>10,'tour'=>2637,'dateBeg'=>'2026-10-12','nights'=>7,'currency'=>3];
        if($criteria!==$expected)throw new RuntimeException('ANEX_B2B_CONTEXT_MISMATCH');
        ++$this->requests;
        $log=getenv('HOME').'/calls';
        file_put_contents($log,(string)((is_file($log)?(int)file_get_contents($log):0)+1));
        return ['data'=>[array_merge($criteria,['price_adult'=>100.0,'price_chd'=>100.0,'cashrate'=>104.23,
            'price_converted_adult'=>10423.0,'price_converted_chd'=>10423.0])]];
    }
    public function requestsMade():int{return $this->requests;}
}
define('ANYTOUR_ANEX_ADDITIONAL_PARITY_LIBRARY_ONLY',true);
PHP;
    $child.="\nrequire ".var_export(realpath(__DIR__.'/../scripts/diagnostics/anex_additional_parity_v3.php'),true).";\necho json_encode(anex_additional_parity_main(true));\n";
    file_put_contents($runner,$child);
    $run=static function()use($runner,$root,$tmp,$spec):array{
        $p=proc_open([PHP_BINARY,'-d','allow_url_fopen=0',$runner],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,array_merge($_ENV,['HOME'=>$tmp]));
        if(!is_resource($p))throw new RuntimeException('fixture process unavailable');
        fwrite($pipes[0],json_encode($spec));fclose($pipes[0]);
        $stdout=stream_get_contents($pipes[1]);fclose($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[2]);
        if(proc_close($p)!==0)throw new RuntimeException('fixture process failed:'.$stderr);
        return json_decode($stdout,true,64,JSON_THROW_ON_ERROR);
    };
    $result=$run();
    $assert(($result['status']??null)==='completed'&&($result['additional_prices_requests']??null)===1,'new program completes one fake B2B read');
    $assert($result['direct_anex_requests']===0&&$result['tourvisor_requests']===0&&$result['mapping_writes']===0,'retained program never invokes search or mapping');
    $assert(file_get_contents($oldPath)===$oldBytes,'old unknown v3 checkpoint remains sealed');
    $cached=$run();
    $assert(($cached['reused']??false)===true&&file_get_contents($tmp.'/calls')==='1','completed checkpoint is cached without supplier replay');
    file_put_contents($tmp.'/.anytoour-anex/'.ANEX_ADDITIONAL_PROGRAM2637_EXPERIMENT.'.json','{"status":"unknown"}');
    $blocked=$run();
    $assert(($blocked['reason']??null)==='ADDITIONAL_PARITY_NOT_REPLAYABLE'&&file_get_contents($tmp.'/calls')==='1','unknown program cannot replay');
} finally { $remove($tmp); }

echo "ANEX additional parity v3 smoke: {$checks} checks passed; network=0.\n";
