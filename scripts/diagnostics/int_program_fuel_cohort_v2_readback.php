<?php
declare(strict_types=1);

const TARGET_OPERATION='int-andromeda-program-fuel-collect-20260922-v2';
const TARGET_PROGRAM='30';
const TARGET_TOUR='34';

function pfv2_fail(string $reason): never { throw new RuntimeException($reason); }
function pfv2_json(string $path,int $max=3000000): ?array {
    if(!is_file($path)||is_link($path))return null;
    $size=filesize($path);
    if(!is_int($size)||$size<2||$size>$max)return null;
    try{$v=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);return is_array($v)?$v:null;}
    catch(Throwable $e){return null;}
}
function pfv2_ref(mixed $v): ?string {
    if(is_int($v)&&$v>0)return (string)$v;
    return is_string($v)&&preg_match('/\A[1-9][0-9]{0,18}\z/D',$v)===1?$v:null;
}
function pfv2_text(mixed $v,int $max=180): ?string {
    if(!is_string($v))return null;$v=trim($v);
    return $v!==''&&strlen($v)<=$max&&!preg_match('/[\x00-\x1F\x7F]/',$v)?$v:null;
}
if(PHP_SAPI!=='cli')pfv2_fail('cli_required');
$home=rtrim((string)getenv('HOME'),'/');
if($home==='')pfv2_fail('home_missing');
$opDir=$home.'/.anytoour-int-executor/'.TARGET_OPERATION;
$res=pfv2_json($opDir.'/reservation.json',65536);
$terminal=pfv2_json($opDir.'/result.json',1048576);
if(!is_array($res)||($res['operation_id']??null)!==TARGET_OPERATION||!is_array($terminal)
    ||($terminal['operation_id']??null)!==TARGET_OPERATION)pfv2_fail('operation_receipt_missing');
$from=$res['reserved_at']??null;$to=filemtime($opDir.'/result.json');
if(!is_int($from)||$from<1||!is_int($to)||$to<$from)pfv2_fail('operation_window_invalid');
$generation=2100000000-(hexdec(substr(hash('sha256',TARGET_OPERATION),0,6))%1000000);

$searches=$home.'/.anytoour-andromeda/searches';
if(!is_dir($searches)||is_link($searches))pfv2_fail('searches_invalid');
$pages=[];$searchRefs=[];
foreach(new DirectoryIterator($searches) as $entry){
    if($entry->isDot()||$entry->isLink()||!$entry->isFile())continue;
    $mtime=$entry->getMTime();
    if($mtime<$from-2||$mtime>$to+2)continue;
    $state=pfv2_json($entry->getPathname());
    if(!is_array($state)||($state['generation']??null)!==$generation)continue;
    $snap=$state['store']['snapshot']??null;
    if(!is_array($snap)||($snap['provider']??null)!=='andromeda'||($snap['generation']??null)!==$generation
        ||!is_int($snap['page']??null)||!is_array($snap['offers']??null))continue;
    $ref=$snap['search_ref']??null;
    if(!is_string($ref)||preg_match('/\A[a-f0-9]{64}\z/D',$ref)!==1)continue;
    $key=$ref.':'.$snap['page'];
    $pages[$key]=['page'=>$snap['page'],'status'=>pfv2_text($state['status']??null,32),'offers'=>$snap['offers']];
    $searchRefs[$ref]=true;
}
if(count($searchRefs)!==1||$pages===[])pfv2_fail('cohort_not_unique');
usort($pages,static fn(array $a,array $b):int=>$a['page']<=>$b['page']);

$runtime=$home.'/www/anytoour.ru/_preview/search3-anex-candidate/app/integrations/operator-program-fuel-registry.php';
if(!is_file($runtime)||is_link($runtime))pfv2_fail('program_registry_runtime_missing');
require_once $runtime;

$groups=[];$offerCount=0;$targetOffers=0;$targetMapped=0;$targetRuleResolved=0;$operators=[];
$party=['adults'=>2,'children'=>0,'child_ages'=>[]];
foreach($pages as $page){
    foreach($page['offers'] as $offer){
        if(!is_array($offer))continue;++$offerCount;
        $operator=pfv2_text($offer['operator']??null)??'unknown';$operators[$operator]=($operators[$operator]??0)+1;
        $tc=is_array($offer['transport_context']??null)?$offer['transport_context']:[];
        $pk=pfv2_ref($tc['program_ref']??null);$tk=pfv2_ref($tc['tour_ref']??null);
        $pl=pfv2_text($tc['program_label']??null);$tl=pfv2_text($tc['tour_label']??null);
        $fx=$tc['freight_external']??null;$fx=is_bool($fx)?$fx:null;
        $key=$operator.'|'.($pk??'-').'|'.($tk??'-');
        if(!isset($groups[$key]))$groups[$key]=[
            'operator'=>$operator,'program_key'=>$pk,'program_labels'=>[],'tour_key'=>$tk,'tour_labels'=>[],
            'offer_count'=>0,'mapped_count'=>0,'freight_external'=>['true'=>0,'false'=>0,'null'=>0],
        ];
        ++$groups[$key]['offer_count'];
        if(is_int($offer['local_hotel_id']??null)&&$offer['local_hotel_id']>0)++$groups[$key]['mapped_count'];
        if($pl!==null)$groups[$key]['program_labels'][$pl]=true;
        if($tl!==null)$groups[$key]['tour_labels'][$tl]=true;
        ++$groups[$key]['freight_external'][$fx===true?'true':($fx===false?'false':'null')];
        if(strtolower($operator)==='intourist'&&$pk===TARGET_PROGRAM&&$tk===TARGET_TOUR){
            ++$targetOffers;
            if(is_int($offer['local_hotel_id']??null)&&$offer['local_hotel_id']>0)++$targetMapped;
            $rule=AnyTourOperatorProgramFuelRegistryV1::priceForOffer($searches,$offer,$party,time());
            if(is_array($rule)&&($rule['program_rule']['amount']??null)==='85.00'
                &&($rule['program_rule']['unit']??null)==='per_person_one_way')++$targetRuleResolved;
        }
    }
}
ksort($operators,SORT_STRING);ksort($groups,SORT_STRING);
$list=[];
foreach($groups as $g){
    $g['program_labels']=array_keys($g['program_labels']);sort($g['program_labels'],SORT_STRING);
    $g['tour_labels']=array_keys($g['tour_labels']);sort($g['tour_labels'],SORT_STRING);
    $list[]=$g;
}
$out=[
    'schema_version'=>1,'source'=>'int-program-fuel-cohort-v2-readback',
    'target_operation'=>TARGET_OPERATION,'generation'=>$generation,
    'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,'filesystem_writes'=>0,
    'operation_window'=>['from'=>$from,'to'=>$to],
    'page_count'=>count($pages),'offer_count'=>$offerCount,'operators'=>$operators,
    'target'=>['operator'=>'Intourist','program_key'=>TARGET_PROGRAM,'tour_key'=>TARGET_TOUR,
        'offer_count'=>$targetOffers,'mapped_count'=>$targetMapped,'rule_resolved_count'=>$targetRuleResolved],
    'groups'=>$list,
];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
