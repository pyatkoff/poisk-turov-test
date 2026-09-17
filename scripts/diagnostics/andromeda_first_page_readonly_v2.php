<?php
declare(strict_types=1);

const PUBLISHED_AT = 1789658123;

function failv2(string $reason): never {
    fwrite(STDERR, 'ANDROMEDA_FIRST_PAGE_READONLY_FAILED '.preg_replace('/[^A-Za-z0-9_.:-]+/','_',$reason)."\n");
    exit(2);
}
function readv2(string $path,int $max=3000000): ?array {
    if(is_link($path)||!is_file($path))return null;
    $size=filesize($path);if(!is_int($size)||$size<2||$size>$max)return null;
    try{$v=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);return is_array($v)?$v:null;}
    catch(Throwable $e){return null;}
}
function ownedv2(string $raw): bool {
    $v=str_replace(['Ё','ё'],'е',trim($raw));
    $v=function_exists('mb_strtolower')?mb_strtolower($v,'UTF-8'):strtolower($v);
    $c=preg_replace('/[^\p{L}\p{N}]+/u','',$v)??'';
    if($c==='')return false;
    foreach(['anex','анекс','pegas','пегас','coral','корал','sunmar','санмар'] as $x)if(str_contains($c,$x))return false;
    return true;
}
if(PHP_SAPI!=='cli')failv2('cli_required');
$home=rtrim((string)getenv('HOME'),'/');if($home==='')failv2('home_missing');
$dir=$home.'/.anytoour-andromeda/searches';if(!is_dir($dir)||is_link($dir))failv2('searches_invalid');
$matches=[];
foreach(new DirectoryIterator($dir) as $e){
    if($e->isDot()||$e->isLink()||!$e->isFile())continue;
    if(!preg_match('/\A([a-f0-9]{64})-1\.json\z/D',$e->getFilename(),$m))continue;
    $state=readv2($e->getPathname());
    $created=$state['store']['created_at']??null;
    if(is_int($created)&&$created>=PUBLISHED_AT)$matches[]=['ref'=>$m[1],'path'=>$e->getPathname(),'state'=>$state];
}
if(count($matches)!==1)failv2('post_publish_first_page_count_'.count($matches));
$item=$matches[0];$ref=$item['ref'];$first=$item['state'];$store=$first['store']??null;$snap=is_array($store)?($store['snapshot']??null):null;
if(!is_array($store)||!is_array($snap)||($snap['provider']??null)!=='andromeda'||($snap['page']??null)!==1
    ||!is_int($store['created_at']??null)||!is_int($store['expires_at']??null)||$store['expires_at']!==$store['created_at']+900
    ||!is_int($snap['pages_count']??null)||$snap['pages_count']<1||$snap['pages_count']>1000
    ||!is_array($snap['offers']??null)||!array_is_list($snap['offers'])||!is_array($snap['rejected']??null)||!array_is_list($snap['rejected']))failv2('first_page_contract');
$created=$store['created_at'];$expires=$store['expires_at'];$generation=$first['generation']??null;if(!is_int($generation)||$generation<1)failv2('generation_invalid');
$target=$snap['pages_count'];$present=[];$missing=[];$pageOfferCounts=[];$pageRejectedCounts=[];
for($p=1;$p<=$target;++$p){
    $path=$p===1?$item['path']:$dir.'/'.$ref.'-'.$created.'-'.$p.'.json';
    $state=readv2($path);$ss=is_array($state)?($state['store']['snapshot']??null):null;
    if(is_array($ss)&&($state['search_ref']??null)===$ref&&($state['generation']??null)===$generation&&($ss['page']??null)===$p&&is_array($ss['offers']??null)&&is_array($ss['rejected']??null)){
        $present[]=$p;$pageOfferCounts[(string)$p]=count($ss['offers']);$pageRejectedCounts[(string)$p]=count($ss['rejected']);
    }else{$missing[]=$p;}
}
$mapped=0;$owned=0;$other=0;$completeSurcharge=0;
foreach($snap['offers'] as $offer){
    if(!is_array($offer))continue;
    if(is_int($offer['local_hotel_id']??null)&&$offer['local_hotel_id']>0)++$mapped;
    if(ownedv2((string)($offer['operator']??''))){
        ++$owned;
        $offerRef=$offer['offer_ref']??null;
        if(is_string($offerRef)&&preg_match('/\Aoffer_[a-f0-9]{64}\z/D',$offerRef)){
            $s=readv2($dir.'/'.$ref.'-'.$created.'-1-'.$offerRef.'-surcharge-v1.json',16384);
            if(is_array($s)&&($s['status']??null)==='complete'&&is_array($s['fact']??null)&&($s['fact']['state']??null)==='estimated')++$completeSurcharge;
        }
    }else++$other;
}
$auth=readv2($dir.'/'.$ref.'-auth.json',65536);
$authPresent=is_array($auth);$authCreatedMatches=$authPresent&&($auth['created_at']??null)===$created;
$checkpoint=readv2($dir.'/'.$ref.'-'.$created.'-anytour-offer-autosave-v1.json',65536);
$now=time();
$out=[
 'schema_version'=>1,'source'=>'andromeda-first-page-readonly-v2','published_at'=>PUBLISHED_AT,'observed_at'=>$now,
 'filesystem_writes'=>0,'supplier_calls'=>0,'db_writes'=>0,'booking_calls'=>0,'lead_calls'=>0,
 'first_page'=>[
   'created_at'=>$created,'expires_at'=>$expires,'age_seconds'=>max(0,$now-$created),'expired'=>$now>=$expires,
   'status'=>$first['status']??null,'advertised_pages_count'=>$target,'present_pages'=>$present,'missing_pages'=>$missing,
   'page_offer_counts'=>$pageOfferCounts,'page_rejected_counts'=>$pageRejectedCounts,
   'first_page_offer_count'=>count($snap['offers']),'first_page_rejected_count'=>count($snap['rejected']),
   'first_page_retained_mapped_count'=>$mapped,'first_page_andromeda_owned_count'=>$owned,
   'first_page_routed_elsewhere_count'=>$other,'first_page_owned_complete_surcharge_count'=>$completeSurcharge,
   'auth_checkpoint_present'=>$authPresent,'auth_created_matches'=>$authCreatedMatches,
   'autosave_checkpoint_present'=>is_array($checkpoint),
 ]
];
echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
