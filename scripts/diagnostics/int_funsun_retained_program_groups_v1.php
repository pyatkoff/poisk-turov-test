<?php
declare(strict_types=1);

const WINDOW_FROM=1790108545;
const WINDOW_TO=1790108712;

function fg_read(string $path,int $max=3000000): ?array {
    if(!is_file($path)||is_link($path))return null;
    $s=filesize($path);if(!is_int($s)||$s<2||$s>$max)return null;
    try{$v=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);return is_array($v)?$v:null;}
    catch(Throwable $e){return null;}
}
function fg_ref(mixed $v): ?string {
    if(is_int($v)&&$v>0)return (string)$v;
    return is_string($v)&&preg_match('/\A[1-9][0-9]{0,18}\z/D',$v)===1?$v:null;
}
function fg_text(mixed $v,int $max=200): ?string {
    if(!is_string($v))return null;$v=trim($v);
    return $v!==''&&strlen($v)<=$max&&!preg_match('/[\x00-\x1F\x7F]/',$v)?$v:null;
}
function fg_funsun(string $operator): bool {
    $n=mb_strtolower($operator);
    return (str_contains($n,'fun')&&str_contains($n,'sun'))||str_contains($n,'фан');
}
if(PHP_SAPI!=='cli')throw new RuntimeException('cli_required');
$home=rtrim((string)getenv('HOME'),'/');if($home==='')throw new RuntimeException('home_missing');
$dir=$home.'/.anytoour-andromeda/searches';if(!is_dir($dir)||is_link($dir))throw new RuntimeException('searches_invalid');

$targets=[];
foreach(new DirectoryIterator($dir) as $entry){
    if($entry->isDot()||$entry->isLink()||!$entry->isFile())continue;
    $m=$entry->getMTime();if($m<WINDOW_FROM||$m>WINDOW_TO)continue;
    if(!str_ends_with($entry->getFilename(),'-surcharge-v1.json'))continue;
    $r=fg_read($entry->getPathname(),65536);$ctx=is_array($r['context']??null)?$r['context']:[];
    $ref=$ctx['search_ref']??null;$generation=$ctx['generation']??null;
    if(is_string($ref)&&preg_match('/\A[a-f0-9]{64}\z/D',$ref)===1&&is_int($generation)&&$generation>0){
        $targets[$ref.':'.$generation]=['search_ref'=>$ref,'generation'=>$generation];
    }
}
if(count($targets)!==1)throw new RuntimeException('target_cohort_not_unique');
$target=array_values($targets)[0];
$first=fg_read($dir.'/'.$target['search_ref'].'-1.json');
if(!is_array($first)||!is_int($first['store']['created_at']??null))throw new RuntimeException('first_page_invalid');
$created=$first['store']['created_at'];

$paths=[1=>$dir.'/'.$target['search_ref'].'-1.json'];
$prefix=$target['search_ref'].'-'.$created.'-';
foreach(new DirectoryIterator($dir) as $entry){
    if($entry->isDot()||$entry->isLink()||!$entry->isFile())continue;
    $name=$entry->getFilename();
    if(!str_starts_with($name,$prefix)||!str_ends_with($name,'.json'))continue;
    $mid=substr($name,strlen($prefix),-5);
    if(preg_match('/\A[1-9][0-9]{0,3}\z/D',$mid)!==1)continue;
    $paths[(int)$mid]=$entry->getPathname();
}
ksort($paths,SORT_NUMERIC);
$groups=[];$offers=0;$mapped=0;$pages=0;$operatorNames=[];
foreach($paths as $page=>$path){
    $state=fg_read($path);$snap=is_array($state['store']['snapshot']??null)?$state['store']['snapshot']:[];
    if(($snap['generation']??null)!==$target['generation']||($snap['page']??null)!==$page||!is_array($snap['offers']??null))continue;
    ++$pages;
    foreach($snap['offers'] as $offer){
        if(!is_array($offer))continue;
        $operator=fg_text($offer['operator']??null)??'';if(!fg_funsun($operator))continue;
        ++$offers;$operatorNames[$operator]=true;
        if(is_int($offer['local_hotel_id']??null)&&$offer['local_hotel_id']>0)++$mapped;
        $tc=is_array($offer['transport_context']??null)?$offer['transport_context']:[];
        $pk=fg_ref($tc['program_ref']??null);$tk=fg_ref($tc['tour_ref']??null);
        $pl=fg_text($tc['program_label']??null);$tl=fg_text($tc['tour_label']??null);
        $spo=fg_ref($tc['spo_ref']??null);$spl=fg_text($tc['spo_label']??null);
        $fx=is_bool($tc['freight_external']??null)?$tc['freight_external']:null;
        $key=($pk??'-').'|'.($tk??'-');
        if(!isset($groups[$key]))$groups[$key]=[
            'program_key'=>$pk,'program_labels'=>[],'tour_key'=>$tk,'tour_labels'=>[],
            'spo_keys'=>[],'spo_labels'=>[],'offer_count'=>0,'mapped_count'=>0,
            'freight_external'=>['true'=>0,'false'=>0,'null'=>0],
        ];
        ++$groups[$key]['offer_count'];
        if(is_int($offer['local_hotel_id']??null)&&$offer['local_hotel_id']>0)++$groups[$key]['mapped_count'];
        if($pl!==null)$groups[$key]['program_labels'][$pl]=true;
        if($tl!==null)$groups[$key]['tour_labels'][$tl]=true;
        if($spo!==null)$groups[$key]['spo_keys'][$spo]=true;
        if($spl!==null)$groups[$key]['spo_labels'][$spl]=true;
        ++$groups[$key]['freight_external'][$fx===true?'true':($fx===false?'false':'null')];
    }
}
ksort($groups,SORT_NATURAL);$list=[];
foreach($groups as $g){
    foreach(['program_labels','tour_labels','spo_keys','spo_labels'] as $k){$g[$k]=array_keys($g[$k]);sort($g[$k],SORT_STRING);}
    $labels=mb_strtolower(implode(' ',array_merge($g['program_labels'],$g['tour_labels'],$g['spo_labels'])));
    $g['label_hint']=str_contains($labels,'gds')?'gds':
        ((str_contains($labels,'чартер')||str_contains($labels,'charter'))?'charter':
        ((str_contains($labels,'регуляр')||str_contains($labels,'regular'))?'regular':'unclassified'));
    $list[]=$g;
}
$names=array_keys($operatorNames);sort($names,SORT_STRING);
$out=[
 'schema_version'=>1,'source'=>'int-funsun-retained-program-groups-v1',
 'window'=>['from'=>WINDOW_FROM,'to'=>WINDOW_TO],
 'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,'filesystem_writes'=>0,
 'retained_pages'=>$pages,'operator_names'=>$names,'offer_count'=>$offers,'mapped_count'=>$mapped,
 'group_count'=>count($list),'groups'=>$list,
];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
