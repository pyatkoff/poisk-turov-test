<?php
declare(strict_types=1);

const HMA682_OPERATION='hotel-match-andromeda-saved-682-current-1971-20260918-v1';
const HMA682_GENERATION=17171836;
const HMA682_EXPECTED_DATE='2026-10-24';
const HMA682_EXPECTED_NIGHTS=7;
const HMA682_EXPECTED_ADULTS=2;
const HMA682_MAX_FIRST_FILES=20000;
const HMA682_MAX_PAGE_BYTES=30000000;

function hma682_rows(PDO $pdo,string $sql,array $params=[]): array {
    $s=$pdo->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
}
function hma682_table(PDO $pdo,string $name): bool {
    $s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
    $s->execute([$name]);return $s->fetchColumn()!==false;
}
function hma682_cols(PDO $pdo,string $name): array {
    $s=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $s->execute([$name]);$out=[];foreach($s->fetchAll(PDO::FETCH_COLUMN) as $c)$out[(string)$c]=true;return$out;
}
function hma682_norm(string $v): string {
    $v=mb_strtolower(trim($v),'UTF-8');
    $v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','&'=>' ','+'=>' ']);
    $v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}
function hma682_hotel_key(string $v): string {
    $tokens=preg_split('/\s+/u',hma682_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[];
    $drop=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'гостиница'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1];
    $out=[];foreach($tokens as $t)if(!isset($drop[$t]))$out[]=$t;
    return implode(' ',$out);
}
function hma682_text(mixed $v,int $max=4096): string {
    if(!is_scalar($v))return '';
    $s=trim((string)$v);
    if($s===''||strlen($s)>$max||!preg_match('//u',$s)||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$s))return '';
    return$s;
}
function hma682_external(mixed $v): ?string {
    if(is_int($v))$v=(string)$v;
    return is_string($v)&&preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$v)?$v:null;
}
function hma682_json_file(string $path): array {
    if(!is_file($path)||is_link($path))throw new RuntimeException('snapshot_file_invalid');
    $size=filesize($path);if(!is_int($size)||$size<2||$size>HMA682_MAX_PAGE_BYTES)throw new RuntimeException('snapshot_file_size');
    $raw=file_get_contents($path);if(!is_string($raw)||strlen($raw)!==$size)throw new RuntimeException('snapshot_file_read');
    $j=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($j))throw new RuntimeException('snapshot_shape');
    return$j;
}
function hma682_family(string $name): string {
    $n=hma682_norm($name);$c=preg_replace('/[^\p{L}\p{N}]+/u','',$n)??'';
    if(str_contains($c,'anex')||str_contains($c,'анекс'))return'anex';
    if(str_contains($c,'biblio')||str_contains($c,'библиоглобус'))return'biblio';
    if(str_contains($c,'funsun')||str_contains($c,'фансан'))return'funsun';
    if(str_contains($c,'intourist')||str_contains($c,'интурист'))return'intourist';
    return $n!==''?$n:'unknown';
}
function hma682_load_generation(string $directory,int $generation): array {
    if(!is_dir($directory)||is_link($directory))throw new RuntimeException('search_directory');
    $files=glob($directory.'/*-1.json',GLOB_NOSORT);if($files===false)throw new RuntimeException('search_glob');
    if(count($files)>HMA682_MAX_FIRST_FILES)throw new RuntimeException('search_file_budget');
    $cohorts=[];
    foreach($files as $path){
        try{$first=hma682_json_file($path);}catch(Throwable){continue;}
        if(($first['generation']??null)!==$generation)continue;
        $snap=$first['store']['snapshot']??null;
        if(!is_array($snap)||($snap['provider']??null)!=='andromeda'||($snap['generation']??null)!==$generation)continue;
        $ref=$snap['search_ref']??null;$page=$snap['page']??null;$pages=$snap['pages_count']??null;$created=$first['store']['created_at']??null;
        if(!is_string($ref)||!preg_match('/^[a-f0-9]{64}$/D',$ref)||$page!==1||!is_int($pages)||$pages<0||$pages>1000||!is_int($created)||$created<1)continue;
        $cohorts[$ref]=['ref'=>$ref,'created_at'=>$created,'advertised_pages'=>$pages,'first_path'=>$path];
    }
    if(!$cohorts)throw new RuntimeException('generation_snapshot_missing');
    $all=[];$meta=[];
    foreach($cohorts as $ref=>$c){
        $target=max(1,(int)$c['advertised_pages']);$seenRefs=[];$offers=[];$pagesLoaded=0;$terminalEmpty=false;
        for($page=1;$page<=$target;++$page){
            $path=$page===1?$c['first_path']:$directory.'/'.$ref.'-'.$c['created_at'].'-'.$page.'.json';
            if(!is_file($path)||is_link($path))throw new RuntimeException('snapshot_page_missing');
            $state=hma682_json_file($path);$snap=$state['store']['snapshot']??null;
            if(!is_array($snap)||($snap['provider']??null)!=='andromeda'||($snap['search_ref']??null)!==$ref
                ||($snap['generation']??null)!==$generation||($snap['page']??null)!==$page
                ||!is_int($snap['pages_count']??null)||!is_array($snap['offers']??null)||!array_is_list($snap['offers']))
                throw new RuntimeException('snapshot_page_shape');
            $pagesLoaded++;
            $adv=(int)$snap['pages_count'];if($adv===0&&count($snap['offers'])===0){$terminalEmpty=true;break;}
            $target=max($target,$adv);
            if($target>1000)throw new RuntimeException('snapshot_page_budget');
            foreach($snap['offers'] as $offer){
                if(!is_array($offer))continue;$oref=$offer['offer_ref']??null;
                if(!is_string($oref)||!preg_match('/^offer_[a-f0-9]{64}$/D',$oref)||isset($seenRefs[$oref]))continue;
                $seenRefs[$oref]=true;$offers[]=['page'=>$page,'offer'=>$offer];
            }
        }
        $meta[]=['search_ref'=>$ref,'created_at'=>$c['created_at'],'advertised_pages'=>$c['advertised_pages'],'pages_loaded'=>$pagesLoaded,'terminal_empty'=>$terminalEmpty,'offers'=>count($offers)];
        foreach($offers as $row)$all[]=$row+['search_ref'=>$ref];
    }
    return ['meta'=>$meta,'rows'=>$all];
}
function hma682_room_evidence(array $rows): array {
    $rooms=[];
    foreach($rows as $r){
        $room=hma682_text($r['room_raw']??$r['room']??'',240);
        $meal='';
        if(is_array($r['meal']??null))$meal=hma682_text($r['meal']['raw_label']??$r['meal']['label']??'',180);
        else$meal=hma682_text($r['meal']??'',180);
        $operator=hma682_text($r['operator']??'',180);$family=hma682_family($operator);
        $key=hma682_norm($room).'|'.hma682_norm($meal).'|'.$family;
        if($key==='||unknown')continue;
        if(!isset($rooms[$key]))$rooms[$key]=['room_raw'=>$room,'meal_raw'=>$meal,'operator_family'=>$family,'offer_count'=>0];
        $rooms[$key]['offer_count']++;
    }
    usort($rooms,fn($a,$b)=>$b['offer_count']<=>$a['offer_count']?:strcmp($a['room_raw'],$b['room_raw']));
    return array_slice(array_values($rooms),0,30);
}

if(in_array('--self-test',$argv??[],true)){
    if(hma682_hotel_key('Porto Bello Hotel Resort & Spa')!=='porto bello')throw new RuntimeException('key');
    if(hma682_family('FUN&SUN')!=='funsun')throw new RuntimeException('family');
    echo "ANDROMEDA_SAVED_682_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$root=realpath((string)getenv('ANYTOUR_ROOT'));$private=realpath((string)getenv('ANDROMEDA_PRIVATE_CONFIG'));$opdir=realpath((string)getenv('MATCH_OPERATION_DIR'));
if(!$root||basename($root)!=='anytoour.ru'||!$private||!is_file($private)||!$opdir)throw new RuntimeException('runtime_paths');
$config=require $private;if(!is_array($config)||($config['enabled']??null)!==true||!is_string($config['catalog_path']??null))throw new RuntimeException('private_config');
$directory=dirname($config['catalog_path']).'/searches';
require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
require_once $opdir.'/payload/anex-search-mapping-registry.php';

$saved=hma682_load_generation($directory,HMA682_GENERATION);
$offerRows=$saved['rows'];$sources=[];$offerRefs=[];$dateCounts=[];$operatorCounts=[];$namespaceCounts=[];
foreach($offerRows as $row){
    $o=$row['offer'];if(!is_array($o))continue;
    $oref=$o['offer_ref']??null;if(!is_string($oref)||isset($offerRefs[$oref]))continue;$offerRefs[$oref]=true;
    $ns=hma682_text($o['supplier_namespace']??'',80);$namespaceCounts[$ns]=($namespaceCounts[$ns]??0)+1;
    $date=hma682_text($o['check_in']??'',20);if($date!=='')$dateCounts[$date]=($dateCounts[$date]??0)+1;
    $operator=hma682_text($o['operator']??'',180);$family=hma682_family($operator);$operatorCounts[$family]=($operatorCounts[$family]??0)+1;
    if($ns!=='andromeda_catalog')continue;
    $external=hma682_external($o['external_hotel_id']??null);$name=hma682_text($o['hotel']??'',300);
    if($external===null||$name==='')continue;
    if(!isset($sources[$external]))$sources[$external]=['external_hotel_id'=>$external,'names'=>[],'offers'=>[],'operators'=>[]];
    $sources[$external]['names'][$name]=true;$sources[$external]['offers'][]=$o;$sources[$external]['operators'][$family]=true;
}
ksort($dateCounts);ksort($operatorCounts);ksort($namespaceCounts);

$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
try{
    foreach(['tour_price_observations','catalog_hotels','hotel_aliases','andromeda_hotel_identities','anex_hotel_search_mappings','anex_hotel_decisions'] as $t)
        if(!hma682_table($pdo,$t))throw new RuntimeException('missing_'.$t);
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);
    $anexTargets=[];foreach(hma682_rows($pdo,'SELECT anex_hotel_id FROM anex_hotel_search_mappings UNION SELECT anex_hotel_id FROM anex_hotel_decisions')as$r){$t=$registry->resolve('anex_online',(string)$r['anex_hotel_id'],'preview');if(is_int($t)&&$t>0)$anexTargets[$t]=true;}
    $andromedaTargets=[];$identity=[];
    $cols=hma682_cols($pdo,'andromeda_hotel_identities');$sel=['supplier_namespace','external_hotel_id','local_hotel_id','decision_status'];foreach(['country_id','evidence_sha256','evidence_json']as$c)if(isset($cols[$c]))$sel[]=$c;
    foreach(hma682_rows($pdo,'SELECT '.implode(',',$sel)." FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog'")as$r){
        $eid=(string)$r['external_hotel_id'];$identity[$eid]=$r;
        if(($r['decision_status']??null)==='accepted'&&$r['local_hotel_id']!==null)$andromedaTargets[(int)$r['local_hotel_id']]=true;
    }
    $seen=[];foreach(hma682_rows($pdo,"SELECT hotel_id,COUNT(*) obs,MAX(observed_at) last_seen FROM tour_price_observations WHERE source='user_search' GROUP BY hotel_id")as$r)$seen[(int)$r['hotel_id']]=['obs'=>(int)$r['obs'],'last_seen'=>(string)$r['last_seen']];
    $locals=[];$index=[];$frontier=[];
    foreach(hma682_rows($pdo,'SELECT id,country_id,country_name,name,region_name,subregion_name,is_active FROM catalog_hotels WHERE is_active=1')as$r){
        $id=(int)$r['id'];$locals[$id]=$r;$key=hma682_hotel_key((string)$r['name']);if($key!=='')$index[(int)$r['country_id']][$key][$id]=true;
        if(isset($seen[$id])&&!isset($anexTargets[$id])&&!isset($andromedaTargets[$id]))$frontier[$id]=$r+$seen[$id];
    }
    foreach(hma682_rows($pdo,'SELECT a.hotel_id,a.alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.is_active=1')as$r){
        $key=hma682_hotel_key((string)$r['alias']);if($key!=='')$index[(int)$r['country_id']][$key][(int)$r['hotel_id']]=true;
    }
    $pdo->rollBack();

    $candidates=[];$blocked=[];$currentSame=[];$currentOther=[];$targetSources=[];
    foreach($sources as $external=>$s){
        $cur=$identity[$external]??null;
        if(is_array($cur)&&($cur['decision_status']??null)==='accepted'&&$cur['local_hotel_id']!==null){
            $currentSame[]=['external_hotel_id'=>$external,'local_hotel_id'=>(int)$cur['local_hotel_id']];continue;
        }
        if(is_array($cur)&&($cur['decision_status']??null)==='conflict'){$blocked['current_conflict']=($blocked['current_conflict']??0)+1;continue;}
        $keys=[];foreach(array_keys($s['names'])as$n){$k=hma682_hotel_key($n);if($k!=='')$keys[$k]=true;}
        if(count($keys)!==1){$blocked['source_names_not_one_key']=($blocked['source_names_not_one_key']??0)+1;continue;}
        $key=(string)array_key_first($keys);$targets=array_keys($index[4][$key]??[]);
        if(count($targets)!==1){$blocked[count($targets)>1?'local_key_ambiguous':'no_local_exact']=($blocked[count($targets)>1?'local_key_ambiguous':'no_local_exact']??0)+1;continue;}
        $target=(int)$targets[0];
        if(!isset($frontier[$target])){$blocked['target_not_current_user_seen_frontier']=($blocked['target_not_current_user_seen_frontier']??0)+1;continue;}
        if(is_array($cur)&&$cur['local_hotel_id']!==null&&(int)$cur['local_hotel_id']!==$target){$currentOther[]=['external_hotel_id'=>$external,'current_local'=>(int)$cur['local_hotel_id'],'proposed_local'=>$target];continue;}
        $candidate=[
            'external_hotel_id'=>$external,'local_hotel_id'=>$target,'hotel_key'=>$key,
            'supplier_names'=>array_values(array_keys($s['names'])),'local_name'=>(string)$frontier[$target]['name'],
            'operators'=>array_values(array_keys($s['operators'])),'offer_count'=>count($s['offers']),
            'user_observations'=>$frontier[$target]['obs'],'last_user_seen'=>$frontier[$target]['last_seen'],
            'room_evidence'=>hma682_room_evidence($s['offers']),
            'current_identity_status'=>is_array($cur)?($cur['decision_status']??null):null,
            'current_identity_present'=>is_array($cur),
        ];
        $candidates[]=$candidate;$targetSources[$target][]=$external;
    }
    usort($candidates,fn($a,$b)=>$b['user_observations']<=>$a['user_observations']?:$b['offer_count']<=>$a['offer_count']?:$a['local_hotel_id']<=>$b['local_hotel_id']);
    $targetAmbiguous=0;foreach($targetSources as$ids)if(count($ids)>1)$targetAmbiguous++;
    echo json_encode([
        'operation'=>HMA682_OPERATION,'state'=>'read_only_complete','generation'=>HMA682_GENERATION,
        'cohorts'=>$saved['meta'],'snapshot_offer_rows'=>count($offerRows),'unique_offer_refs'=>count($offerRefs),
        'date_counts'=>$dateCounts,'namespace_counts'=>$namespaceCounts,'operator_family_counts'=>$operatorCounts,
        'andromeda_catalog_unique_hotels'=>count($sources),'current_user_seen_frontier'=>count($frontier),
        'exact_candidate_identities'=>count($candidates),'exact_candidate_unique_local_hotels'=>count($targetSources),
        'candidate_targets_with_multiple_external_ids'=>$targetAmbiguous,
        'already_accepted_source_identities'=>count($currentSame),'current_other_target_conflicts'=>count($currentOther),
        'blocked_counts'=>$blocked,'candidates'=>$candidates,'current_other_target'=>$currentOther,
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
