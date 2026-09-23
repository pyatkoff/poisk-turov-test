<?php
declare(strict_types=1);

const IRPG_TARGET_OPERATION = 'int-andromeda-intourist-program-fuel-20260923-v2';
const IRPG_MAX_FILES = 100000;
const IRPG_MAX_BYTES = 3000000;

function irpg_fail(string $reason): never { throw new RuntimeException($reason); }
function irpg_json(string $path, int $max=IRPG_MAX_BYTES): ?array {
    if (!is_file($path) || is_link($path)) return null;
    $size=filesize($path);
    if (!is_int($size) || $size<2 || $size>$max) return null;
    try {
        $value=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
        return is_array($value)?$value:null;
    } catch(Throwable $e) { return null; }
}
function irpg_ref(mixed $value): ?string {
    if (is_int($value) && $value>0) return (string)$value;
    return is_string($value) && preg_match('/\A[1-9][0-9]{0,18}\z/D',$value)===1 ? $value : null;
}
function irpg_text(mixed $value,int $max=200): ?string {
    if (!is_string($value)) return null;
    $value=trim($value);
    return $value!=='' && strlen($value)<=$max && preg_match('/[\x00-\x1F\x7F]/',$value)!==1 ? $value : null;
}
function irpg_intourist(mixed $value): bool {
    if (!is_string($value) || $value==='') return false;
    return preg_match('/intourist|интурист/iu',$value)===1;
}
function irpg_generation(string $operation): int {
    return 2100000000 - (hexdec(substr(hash('sha256',$operation),0,6)) % 1000000);
}
/** @param list<array<string,mixed>> $offers */
function irpg_aggregate(array $offers): array {
    $groups=[];$total=0;$mapped=0;$operators=[];
    foreach($offers as $offer){
        if(!is_array($offer) || !irpg_intourist($offer['operator']??null)) continue;
        ++$total;
        $operator=irpg_text($offer['operator']??null);
        if($operator!==null)$operators[$operator]=true;
        $isMapped=is_int($offer['local_hotel_id']??null)&&$offer['local_hotel_id']>0;
        if($isMapped)++$mapped;
        $tc=is_array($offer['transport_context']??null)?$offer['transport_context']:[];
        $pk=irpg_ref($tc['program_ref']??null);$tk=irpg_ref($tc['tour_ref']??null);
        $spo=irpg_ref($tc['spo_ref']??null);
        $pl=irpg_text($tc['program_label']??null);$tl=irpg_text($tc['tour_label']??null);
        $sl=irpg_text($tc['spo_label']??null);
        $freight=is_bool($tc['freight_external']??null)?$tc['freight_external']:null;
        $key=($pk??'-').'|'.($tk??'-');
        if(!isset($groups[$key]))$groups[$key]=[
            'program_key'=>$pk,'program_labels'=>[],'tour_key'=>$tk,'tour_labels'=>[],
            'offer_count'=>0,'mapped_count'=>0,'spo_keys'=>[],'spo_labels'=>[],
            'freight_external'=>['true'=>0,'false'=>0,'null'=>0],
        ];
        ++$groups[$key]['offer_count'];
        if($isMapped)++$groups[$key]['mapped_count'];
        if($pl!==null)$groups[$key]['program_labels'][$pl]=true;
        if($tl!==null)$groups[$key]['tour_labels'][$tl]=true;
        if($spo!==null)$groups[$key]['spo_keys'][$spo]=true;
        if($sl!==null)$groups[$key]['spo_labels'][$sl]=true;
        ++$groups[$key]['freight_external'][$freight===true?'true':($freight===false?'false':'null')];
    }
    $list=[];
    foreach($groups as $g){
        foreach(['program_labels','tour_labels','spo_labels'] as $key){
            $g[$key]=array_keys($g[$key]);sort($g[$key],SORT_STRING);
        }
        $g['distinct_spo_count']=count($g['spo_keys']);
        unset($g['spo_keys']);
        $g['probe_ready']=$g['program_key']!==null && $g['tour_key']!==null
            && $g['distinct_spo_count']>=2 && $g['mapped_count']>=2
            && !($g['program_key']==='30' && $g['tour_key']==='34');
        $list[]=$g;
    }
    usort($list,static function(array $a,array $b):int{
        $c=$b['offer_count']<=>$a['offer_count'];
        if($c!==0)return $c;
        $c=($a['program_key']??'~')<=>($b['program_key']??'~');
        return $c!==0?$c:(($a['tour_key']??'~')<=>($b['tour_key']??'~'));
    });
    $ready=array_values(array_filter($list,static fn(array $g):bool=>$g['probe_ready']===true));
    $names=array_keys($operators);sort($names,SORT_STRING);
    return [
        'operator_names'=>$names,'offer_count'=>$total,'mapped_count'=>$mapped,
        'group_count'=>count($list),'groups'=>$list,'probe_ready_groups'=>$ready,
    ];
}
function irpg_run(): array {
    $home=rtrim((string)getenv('HOME'),'/');
    if($home==='')irpg_fail('home_missing');
    $private=$home.'/.anytoour-int-executor';
    $op=$private.'/'.IRPG_TARGET_OPERATION;
    $reservation=irpg_json($op.'/reservation.json',65536);
    $result=irpg_json($op.'/result.json',1048576);
    if(!is_array($reservation)||($reservation['operation_id']??null)!==IRPG_TARGET_OPERATION
        ||!is_array($result)||($result['operation_id']??null)!==IRPG_TARGET_OPERATION
        ||!in_array($result['status']??null,['complete','reconciled_read_only'],true)){
        irpg_fail('target_operation_not_terminal');
    }
    $generation=irpg_generation(IRPG_TARGET_OPERATION);
    $dir=$home.'/.anytoour-andromeda/searches';
    if(!is_dir($dir)||is_link($dir))irpg_fail('searches_invalid');
    $offers=[];$pages=[];$seen=0;
    foreach(new DirectoryIterator($dir) as $entry){
        if($entry->isDot())continue;
        if(++$seen>IRPG_MAX_FILES)irpg_fail('inventory_too_large');
        if($entry->isLink()||!$entry->isFile())continue;
        $state=irpg_json($entry->getPathname());
        $snap=is_array($state['store']['snapshot']??null)?$state['store']['snapshot']:null;
        if(!is_array($snap)||($snap['generation']??null)!==$generation
            ||!is_int($snap['page']??null)||$snap['page']<1
            ||!is_array($snap['offers']??null)||!array_is_list($snap['offers']))continue;
        $ref=$snap['search_ref']??null;
        if(!is_string($ref)||preg_match('/\A[a-f0-9]{64}\z/D',$ref)!==1)continue;
        $pages[$ref.':'.$snap['page']]=true;
        foreach($snap['offers'] as $offer)if(is_array($offer))$offers[]=$offer;
    }
    if($pages===[]||$offers===[])irpg_fail('retained_cohort_missing');
    $summary=irpg_aggregate($offers);
    return [
        'schema_version'=>1,'source'=>'int-intourist-retained-program-groups-v1',
        'target_operation'=>IRPG_TARGET_OPERATION,'generation'=>$generation,
        'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,'filesystem_writes'=>0,
        'retained_page_count'=>count($pages),
    ]+$summary;
}
if(PHP_SAPI==='cli' && getenv('INT_INTOURIST_GROUPS_LIBRARY_ONLY')!=='1'){
    try{
        echo json_encode(irpg_run(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    }catch(Throwable $e){
        fwrite(STDERR,'INT_INTOURIST_GROUPS_ERROR:'.preg_replace('/[^A-Za-z0-9_.:-]+/','_',$e->getMessage())."\n");
        exit(1);
    }
}
