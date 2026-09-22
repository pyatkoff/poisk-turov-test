<?php
declare(strict_types=1);

/**
 * Read-only inspection of the exact retained Andromeda external-group probe made
 * by int-andromeda-flight-observe-20260922-v2.
 *
 * No supplier transport, DB access or filesystem mutation. Output contains only
 * supplier search identifiers/labels already retained in private PRICE snapshots.
 */
const WINDOW_FROM = 1790108545;
const WINDOW_TO = 1790108712;
const MAX_FILE_BYTES = 3000000;
const MAX_FILES = 100000;

function fail_read(string $reason): never {
    fwrite(STDERR, 'PROGRAM_TRANSPORT_READONLY_FAILED '.preg_replace('/[^A-Za-z0-9_.:-]+/','_',$reason)."\n");
    exit(2);
}
function read_json(string $path, int $max=MAX_FILE_BYTES): ?array {
    if (!is_file($path) || is_link($path)) return null;
    $size=filesize($path);
    if (!is_int($size) || $size<2 || $size>$max) return null;
    try {
        $v=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
        return is_array($v)?$v:null;
    } catch(Throwable $e) { return null; }
}
function clean_text(mixed $v,int $max=160): ?string {
    if (!is_string($v)) return null;
    $v=trim($v);
    if ($v==='' || strlen($v)>$max || preg_match('/[\x00-\x1F\x7F]/',$v)) return null;
    return $v;
}
function clean_ref(mixed $v): ?string {
    if (is_int($v) && $v>0) return (string)$v;
    return is_string($v) && preg_match('/\A[1-9][0-9]{0,18}\z/D',$v)===1 ? $v : null;
}
function label_hint(?string $program,?string $tour): string {
    $s=strtolower(($program??'').' '.($tour??''));
    $regular=(bool)preg_match('/regular|регуляр/u',$s);
    $charter=(bool)preg_match('/charter|чартер/u',$s);
    if($regular && !$charter) return 'explicit_regular_label';
    if($charter && !$regular) return 'explicit_charter_label';
    if($regular && $charter) return 'mixed_label';
    return 'unclassified_label';
}
function offer_context(array $offer): array {
    $tc=is_array($offer['transport_context']??null)?$offer['transport_context']:[];
    $program=clean_ref($tc['program_ref']??null);
    $tour=clean_ref($tc['tour_ref']??null);
    $programLabel=clean_text($tc['program_label']??null);
    $tourLabel=clean_text($tc['tour_label']??null);
    $freight=$tc['freight_external']??null;
    if(!is_bool($freight)) $freight=null;
    return [
        'operator'=>clean_text($offer['operator']??null),
        'operator_ref'=>clean_ref($offer['operator_ref']??null),
        'check_in'=>clean_text($offer['check_in']??null,16),
        'nights'=>is_int($offer['nights']??null)?$offer['nights']:null,
        'program_key'=>$program,
        'program_label'=>$programLabel,
        'tour_key'=>$tour,
        'tour_label'=>$tourLabel,
        'spo_key'=>clean_ref($tc['spo_ref']??null),
        'spo_label'=>clean_text($tc['spo_label']??null),
        'freight_external'=>$freight,
        'departure_times_reported'=>clean_text($tc['departure_times_reported']??null,240),
        'label_hint'=>label_hint($programLabel,$tourLabel),
    ];
}

if(PHP_SAPI!=='cli') fail_read('cli_required');
$home=rtrim((string)getenv('HOME'),'/');
if($home==='') fail_read('home_missing');
$searches=$home.'/.anytoour-andromeda/searches';
if(!is_dir($searches)||is_link($searches)) fail_read('searches_invalid');

$targets=[];
$files=0;
foreach(new DirectoryIterator($searches) as $entry){
    if($entry->isDot()) continue;
    if(++$files>MAX_FILES) fail_read('inventory_too_large');
    if($entry->isLink()||!$entry->isFile()) continue;
    $mtime=$entry->getMTime();
    if($mtime<WINDOW_FROM||$mtime>WINDOW_TO) continue;
    $name=$entry->getFilename();
    if(!str_ends_with($name,'-surcharge-v1.json')) continue;
    $row=read_json($entry->getPathname(),65536);
    if(!is_array($row)||!is_array($row['context']??null)) continue;
    $ctx=$row['context'];
    $ref=$ctx['search_ref']??null;
    $offerRef=$ctx['offer_ref']??null;
    $page=$ctx['page']??null;
    $generation=$ctx['generation']??null;
    if(!is_string($ref)||preg_match('/\A[a-f0-9]{64}\z/D',$ref)!==1
        ||!is_string($offerRef)||preg_match('/\Aoffer_[a-f0-9]{64}\z/D',$offerRef)!==1
        ||!is_int($page)||$page<1||!is_int($generation)||$generation<1) continue;
    $targets[]=[
        'mtime'=>$mtime,'status'=>clean_text($row['status']??null,32),
        'failure_class'=>clean_text($row['failure_class']??null,96),
        'search_ref'=>$ref,'offer_ref'=>$offerRef,'page'=>$page,'generation'=>$generation
    ];
}
if(count($targets)!==1) fail_read('target_checkpoint_count_'.count($targets));
$target=$targets[0];

$first=read_json($searches.'/'.$target['search_ref'].'-1.json');
if(!is_array($first)||($first['generation']??null)!==$target['generation']
    ||!is_array($first['store']['snapshot']??null)
    ||!is_int($first['store']['created_at']??null)) fail_read('first_page_invalid');
$created=$first['store']['created_at'];
$advertisedPages=(int)($first['store']['snapshot']['pages_count']??0);
if($advertisedPages<1||$advertisedPages>1000) fail_read('page_count_invalid');

// Do not require every advertised page to exist: run_pages can terminate on an
// empty supplier page before the initial advertised upper bound. Read only the
// retained valid pages that actually exist for this exact searchRef/created cohort.
$pagePaths=[1=>$searches.'/'.$target['search_ref'].'-1.json'];
$prefix=$target['search_ref'].'-'.$created.'-';
foreach(new DirectoryIterator($searches) as $entry){
    if($entry->isDot()||$entry->isLink()||!$entry->isFile()) continue;
    $name=$entry->getFilename();
    if(!str_starts_with($name,$prefix)||!str_ends_with($name,'.json')) continue;
    $middle=substr($name,strlen($prefix),-5);
    if(preg_match('/\A[1-9][0-9]{0,3}\z/D',$middle)!==1) continue;
    $page=(int)$middle;
    if($page>1000) continue;
    $pagePaths[$page]=$entry->getPathname();
}
ksort($pagePaths,SORT_NUMERIC);

$allOffers=[];
$targetOffer=null;
$validPages=0;
foreach($pagePaths as $page=>$path){
    $state=read_json($path);
    $snapshot=is_array($state)?($state['store']['snapshot']??null):null;
    if(!is_array($snapshot)||($snapshot['generation']??null)!==$target['generation']
        ||($snapshot['page']??null)!==$page||!is_array($snapshot['offers']??null)) continue;
    ++$validPages;
    foreach($snapshot['offers'] as $offer){
        if(!is_array($offer)) continue;
        $allOffers[]=$offer;
        if(($offer['offer_ref']??null)===$target['offer_ref']) $targetOffer=$offer;
    }
}
if($validPages<1) fail_read('no_valid_pages');
if(!is_array($targetOffer)) fail_read('target_offer_missing');
$targetFacts=offer_context($targetOffer);
if($targetFacts['operator']===null) fail_read('operator_missing');

$programCount=0;$tourCount=0;$programTours=[];$programFreight=['true'=>0,'false'=>0,'null'=>0];
$operatorOfferCount=0;$operatorPrograms=[];
foreach($allOffers as $offer){
    $facts=offer_context($offer);
    if($facts['operator']!==$targetFacts['operator']) continue;
    ++$operatorOfferCount;
    if($facts['program_key']!==null) $operatorPrograms[$facts['program_key']]=true;
    if($targetFacts['program_key']!==null && $facts['program_key']===$targetFacts['program_key']){
        ++$programCount;
        if($facts['tour_key']!==null) $programTours[$facts['tour_key']]=true;
        $programFreight[$facts['freight_external']===true?'true':($facts['freight_external']===false?'false':'null')]++;
    }
    if($targetFacts['tour_key']!==null && $facts['tour_key']===$targetFacts['tour_key']) ++$tourCount;
}

$out=[
    'schema_version'=>1,
    'source'=>'andromeda-program-transport-readonly-v1',
    'window'=>['from'=>WINDOW_FROM,'to'=>WINDOW_TO],
    'supplier_calls'=>0,'database_reads'=>0,'database_writes'=>0,'filesystem_writes'=>0,
    'target_checkpoint'=>[
        'status'=>$target['status'],'failure_class'=>$target['failure_class'],
        'page'=>$target['page'],'offer_ref_sha256'=>hash('sha256',$target['offer_ref'])
    ],
    'target'=>$targetFacts,
    'cohort'=>[
        'advertised_pages'=>$advertisedPages,'retained_valid_pages'=>$validPages,'offer_count'=>count($allOffers),
        'same_operator_offer_count'=>$operatorOfferCount,
        'same_operator_distinct_program_keys'=>count($operatorPrograms),
        'same_program_offer_count'=>$programCount,
        'same_program_distinct_tour_keys'=>count($programTours),
        'same_program_freight_external_counts'=>$programFreight,
        'same_tour_offer_count'=>$tourCount,
    ],
    'interpretation'=>[
        'freight_external_is_not_charter_flag'=>true,
        'regular_or_charter_authority'=>'explicit_supplier_label_or_flight_evidence_required',
    ],
];
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
