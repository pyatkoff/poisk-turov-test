<?php
declare(strict_types=1);

const HMS34_OPERATION = 'hotel-match-common4-samo34-live-1971-20260919-v1';
const HMS34_CONTEXT_SHA = '041d63ec0606411097b980268591e86400aad2d61ee3abf1786462c3c524855d';
const HMS34_MAX_PROVIDER_CALLS = 104;
const HMS34_MAX_PRICE_CALLS = 100;
const HMS34_MAX_PAGE_PER_GROUP = 20;

function hms34_lower(string $v): string {
    if(function_exists('mb_strtolower')) return mb_strtolower($v,'UTF-8');
    return strtolower(strtr($v,['А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й','К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф','Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я']));
}
function hms34_len(string $v): int {return function_exists('mb_strlen')?mb_strlen($v,'UTF-8'):strlen($v);}
function hms34_cut(string $v,int $max): string {return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);}
function hms34_scalar(mixed $v, int $max=512): string {
    if (!is_scalar($v)) return '';
    $s=trim((string)$v);
    return hms34_cut($s,$max);
}
function hms34_norm(string $v): string {
    $n=hms34_lower(trim($v));
    $n=strtr($n,['ё'=>'е','&'=>' ','+'=>' ','_'=>' ','-'=>' ','’'=>"'",'`'=>"'"]);
    $n=preg_replace('/[^\p{L}\p{N}]+/u',' ',$n)??$n;
    return trim(preg_replace('/\s+/u',' ',$n)??$n);
}
function hms34_sig_tokens(string $v): array {
    $generic=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'resort'=>1,'spa'=>1,'apart'=>1,'apartment'=>1];
    $out=[];
    foreach(preg_split('/\s+/u',hms34_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[] as $t){
        if(isset($generic[$t])||hms34_len($t)<2) continue;
        $out[$t]=true;
    }
    $x=array_keys($out);sort($x,SORT_STRING);return $x;
}
function hms34_name_variants(string $name): array {
    $out=[];$add=function(string $v,string $why)use(&$out){$n=hms34_norm($v);if($n!==''&&hms34_sig_tokens($n))$out[$n][$why]=true;};
    $add($name,'full');
    if(preg_match('/^(.+?)\s*\((?:EX\.?|FORMERLY|БЫВШ\.?)\s*[^)]*\)/iu',$name,$m))$add($m[1],'current');
    if(preg_match_all('/\((?:EX\.?|FORMERLY|БЫВШ\.?)\s*([^)]*)\)/iu',$name,$ms)){
        foreach($ms[1] as $inside)foreach(preg_split('/[;|\/]+/u',(string)$inside)?:[] as $p)$add($p,'former');
    }
    $flat=[];foreach($out as $k=>$v){$why=array_keys($v);sort($why,SORT_STRING);$flat[$k]=$why;}return $flat;
}
function hms34_overlap_score(array $a,array $b): array {
    $A=array_fill_keys($a,true);$B=array_fill_keys($b,true);$common=count(array_intersect_key($A,$B));
    $union=count($A+$B);$jac=$union?($common/$union):0.0;$coverA=count($A)?($common/count($A)):0.0;$coverB=count($B)?($common/count($B)):0.0;
    return ['common'=>$common,'jaccard'=>$jac,'source_coverage'=>$coverA,'target_coverage'=>$coverB,'score'=>(int)round(100*min($coverA,$coverB))];
}
function hms34_geo_relation(array $townLabels,array $hotel): string {
    $locals=array_filter([hms34_scalar($hotel['region_name']??'',160),hms34_scalar($hotel['subregion_name']??'',160)]);
    if(!$townLabels||!$locals)return 'unknown';
    foreach($townLabels as $a){$na=hms34_norm((string)$a);if($na==='')continue;foreach($locals as $b){$nb=hms34_norm($b);if($nb==='')continue;if($na===$nb||str_contains($na,$nb)||str_contains($nb,$na))return 'direct_label_match';}}
    return 'unproven';
}
function hms34_provider_rows(array $hotels,array $towns): array {
    $out=[];
    foreach($hotels as $h){
        if(!is_array($h))continue;$id=hms34_scalar($h['id']??'',64);if(!preg_match('/^[1-9][0-9]{0,31}$/D',$id))continue;
        $names=[];foreach(['name','lName'] as $k){$v=hms34_scalar($h[$k]??'',220);if($v!=='')$names[$v]=true;}
        if(!$names)continue;$tk=hms34_scalar($h['townKey']??'',64);$labels=$tk!==''?($towns[$tk]??[]):[];
        $out[$id]=['catalog_hotel_id'=>$id,'names'=>array_keys($names),'town_key'=>$tk?:null,'town_labels'=>$labels,'star_key'=>hms34_scalar($h['starKey']??'',64)?:null];
    }
    return $out;
}
function hms34_candidates(array $target,array $aliases,array $provider): array {
    $sourceNames=[$target['name']??''];foreach($aliases as $a)$sourceNames[]=(string)$a;
    $sourceVariants=[];$sourceTokens=[];
    foreach($sourceNames as $n){foreach(hms34_name_variants((string)$n) as $v=>$why)$sourceVariants[$v]=true;foreach(hms34_sig_tokens((string)$n) as $t)$sourceTokens[$t]=true;}
    $exact=[];$rank=[];$freq=[];
    foreach($provider as $p)foreach($p['names'] as $name)foreach(hms34_sig_tokens($name) as $t)$freq[$t]=($freq[$t]??0)+1;
    $rare=null;$rareN=PHP_INT_MAX;foreach(array_keys($sourceTokens) as $t){$f=$freq[$t]??0;if($f>0&&$f<$rareN){$rareN=$f;$rare=$t;}}
    foreach($provider as $id=>$p){
        $best=null;$exactWhy=[];
        foreach($p['names'] as $name){
            $pv=hms34_name_variants($name);
            $common=array_intersect_key($sourceVariants,$pv);if($common)$exactWhy=array_merge($exactWhy,array_keys($common));
            $pt=hms34_sig_tokens($name);$s=hms34_overlap_score(array_keys($sourceTokens),$pt);if($best===null||$s['score']>$best['score']||($s['score']===$best['score']&&$s['jaccard']>$best['jaccard']))$best=$s+['provider_name'=>$name];
        }
        $geo=hms34_geo_relation($p['town_labels'],$target);
        $row=$p+['name_score'=>$best,'geo_relation'=>$geo];
        if($exactWhy){$row['retrieval']='exact_variant';$row['exact_keys']=array_values(array_unique($exactWhy));$exact[$id]=$row;continue;}
        if($rare!==null&&in_array($rare,array_unique(array_merge(...array_map('hms34_sig_tokens',$p['names']))),true)&&($best['common']??0)>=1&&($best['source_coverage']??0)>=0.34){$row['retrieval']='rare_token_ranked';$row['rare_token']=$rare;$rank[$id]=$row;}
    }
    $sort=function(array &$rows):void{uasort($rows,function($a,$b){$ga=($a['geo_relation']??'')==='direct_label_match'?1:0;$gb=($b['geo_relation']??'')==='direct_label_match'?1:0;if($ga!==$gb)return $gb<=>$ga;$sa=(int)($a['name_score']['score']??0);$sb=(int)($b['name_score']['score']??0);if($sa!==$sb)return $sb<=>$sa;$ja=(float)($a['name_score']['jaccard']??0);$jb=(float)($b['name_score']['jaccard']??0);return $jb<=>$ja;});};
    if($exact){$sort($exact);return ['mode'=>'exact','candidates'=>array_slice($exact,0,3,true),'total'=>count($exact),'rare_token'=>$rare];}
    $sort($rank);$rank=array_filter($rank,fn($x)=>(int)($x['name_score']['score']??0)>=45||(float)($x['name_score']['jaccard']??0)>=0.45);
    return ['mode'=>$rank?'rare_ranked':'none','candidates'=>array_slice($rank,0,3,true),'total'=>count($rank),'rare_token'=>$rare];
}
function hms34_positive_id(mixed $v): ?string {$s=hms34_scalar($v,64);return preg_match('/^[1-9][0-9]{0,31}$/D',$s)?$s:null;}
function hms34_price_fact(array $row,string $expectedOperator): ?array {
    $hotel=hms34_positive_id($row['hotelKey']??null);if($hotel===null)return null;
    $op=hms34_positive_id($row['operatorKey']??($row['original']['operatorKey']??null));if($op===null||$op!==$expectedOperator)return null;
    $orig=is_array($row['original']??null)?$row['original']:[];$native=hms34_positive_id($orig['hotelKey']??null);
    return ['catalog_hotel_id'=>$hotel,'operator_id'=>$op,'native_operator_hotel_id'=>$native,'is_operator_hotel_key'=>is_scalar($orig['isOperatorHotelKey']??null)?(string)$orig['isOperatorHotelKey']:null];
}
function hms34_durable(string $path,string $raw,bool $exclusive=true): string {
    $f=@fopen($path,$exclusive?'x+b':'c+b');if(!$f)throw new RuntimeException('durable_open');try{if(!$exclusive){fseek($f,0,SEEK_END);}if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');}finally{fclose($f);}return hash('sha256',$raw);
}
function hms34_json_file(string $path,array $v): string {$raw=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";return hms34_durable($path,$raw,true);}
function hms34_table(PDO $pdo,string $t): bool {$s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');$s->execute([$t]);return $s->fetchColumn()!==false;}
function hms34_rows(PDO $pdo,string $sql,array $params=[]): array {$s=$pdo->prepare($sql);$s->execute(array_values($params));return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hms34_call_reserve(string $opDir,string $kind,array $meta,int &$count): void {
    $count++;if($count>HMS34_MAX_PROVIDER_CALLS)throw new RuntimeException('provider_call_cap');
    $line=json_encode(['seq'=>$count,'reserved_at_utc'=>gmdate('c'),'kind'=>$kind,'meta'=>$meta],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
    hms34_durable($opDir.'/provider-calls.jsonl',$line,false);
}
function hms34_raw(string $opDir,string $name,array $payload,array &$manifest): void {
    if(!preg_match('/^[a-z0-9_.-]+$/D',$name))throw new RuntimeException('raw_name');$raw=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";$path=$opDir.'/raw-'.$name.'.json';$sha=hms34_durable($path,$raw,true);$manifest[]=['file'=>basename($path),'sha256'=>$sha,'bytes'=>strlen($raw)];
}
function hms34_unique_dict_id(array $rows,array $aliases): int {
    $want=array_fill_keys(array_map('hms34_norm',$aliases),true);$hits=[];
    foreach($rows as $r){if(!is_array($r))continue;$id=hms34_positive_id($r['id']??null);$name=hms34_norm(hms34_scalar($r['name']??($r['lName']??''),180));if($id!==null&&isset($want[$name]))$hits[$id]=true;}
    if(count($hits)!==1)throw new RuntimeException('dictionary_binding_count_'.count($hits));return (int)array_key_first($hits);
}
function hms34_town_labels(array $rows): array {$out=[];foreach($rows as $r){if(!is_array($r))continue;$id=hms34_scalar($r['id']??'',64);if($id==='')continue;$labels=[];foreach(['name','lName','label','title','region','regionName'] as $k){$v=hms34_scalar($r[$k]??'',180);if($v!=='')$labels[$v]=true;}$out[$id]=array_keys($labels);}return $out;}

if(in_array('--self-test',$argv??[],true)){
    $p=['1'=>['catalog_hotel_id'=>'1','names'=>['ARES CITY (EX. KAMI HOTEL)'],'town_key'=>'7','town_labels'=>['Kemer'],'star_key'=>'3'],'2'=>['catalog_hotel_id'=>'2','names'=>['ARES BLUE'],'town_key'=>'8','town_labels'=>['Alanya'],'star_key'=>'3']];
    $t=['name'=>'ARES CITY (EX. KAMI HOTEL)','region_name'=>'Кемер','subregion_name'=>'Кемер - центр'];$r=hms34_candidates($t,[],$p);if($r['mode']!=='exact'||(string)array_key_first($r['candidates'])!=='1')throw new RuntimeException('candidate_exact');
    $f=hms34_price_fact(['hotelKey'=>177152,'operatorKey'=>315,'original'=>['hotelKey'=>4158,'operatorKey'=>315,'isOperatorHotelKey'=>0]],'315');if(($f['catalog_hotel_id']??'')!=='177152'||($f['native_operator_hotel_id']??'')!=='4158')throw new RuntimeException('price_fact');
    if(hms34_price_fact(['hotelKey'=>1,'operatorKey'=>342,'original'=>['hotelKey'=>2]],'315')!==null)throw new RuntimeException('operator_guard');
    echo "MATCH_COMMON4_SAMO34_LIVE_SELFTEST_OK\n";exit(0);
}
if(PHP_SAPI!=='cli')exit(2);

$opDir=(string)getenv('MATCH_OPERATION_DIR');$sourceSha=(string)getenv('MATCH_SOURCE_SHA');$contextPath=realpath((string)getenv('MATCH_CONTEXT_PATH'));$binderPath=realpath((string)getenv('MATCH_BINDER_PATH'));$clientPath=realpath((string)getenv('MATCH_CLIENT_PATH'));$transportPath=realpath((string)getenv('MATCH_TRANSPORT_PATH'));$root=realpath((string)getenv('ANYTOUR_ROOT'));
if($opDir===''||!is_dir($opDir)||!preg_match('/^[a-f0-9]{40}$/D',$sourceSha)||!is_string($contextPath)||!is_file($contextPath)||!is_string($binderPath)||!is_file($binderPath)||!is_string($clientPath)||!is_file($clientPath)||!is_string($transportPath)||!is_file($transportPath)||!is_string($root)||!is_dir($root))throw new RuntimeException('runtime_guard');
$res=json_decode((string)file_get_contents($opDir.'/reservation.json'),true,32,JSON_THROW_ON_ERROR);if(($res['operation']??'')!==HMS34_OPERATION||($res['state']??'')!=='reserved_before_provider_access')throw new RuntimeException('reservation_guard');
$contextRaw=(string)file_get_contents($contextPath);if(hash('sha256',$contextRaw)!==HMS34_CONTEXT_SHA)throw new RuntimeException('context_sha');$context=json_decode($contextRaw,true,64,JSON_THROW_ON_ERROR);if(($context['operation_id']??'')!=='hotel-match-common4-context34-current-1971-20260919-v1'||($context['state']??'')!=='completed_read_only'||count($context['dossiers']??[])!==39)throw new RuntimeException('context_shape');
require_once $binderPath;require_once $transportPath;require_once $clientPath;
$dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';if(!is_file($dbf))throw new RuntimeException('db_runtime');require_once $dbf;$pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
foreach(['catalog_hotels','hotel_aliases','andromeda_hotel_identities'] as $t)if(!hms34_table($pdo,$t))throw new RuntimeException('missing_'.$t);
$providerAccess=false;$providerCalls=0;$priceCalls=0;$rawManifest=[];$result=[];
try{
    $input=[];foreach($context['dossiers'] as $d){if(($d['status']??'')!=='exact_context_ready'||!in_array($d['operator']??'',['funsun','intourist'],true))throw new RuntimeException('dossier_status');$input[]=$d;}
    $ids=array_values(array_unique(array_map(fn($d)=>(int)$d['tv_hotel_id'],$input)));sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
    $hotels=[];foreach(hms34_rows($pdo,"SELECT id,name,country_id,country_name,region_id,region_name,subregion_id,subregion_name,is_active FROM catalog_hotels WHERE id IN ($ph)",$ids) as $h)$hotels[(int)$h['id']]=$h;
    $aliases=[];foreach(hms34_rows($pdo,"SELECT hotel_id,alias FROM hotel_aliases WHERE hotel_id IN ($ph)",$ids) as $a){$v=trim((string)$a['alias']);if($v!=='')$aliases[(int)$a['hotel_id']][]=$v;}
    $accepted=[];foreach(hms34_rows($pdo,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE supplier_namespace IN ('operator_315','operator_342') AND local_hotel_id IN ($ph)",$ids) as $r){if(($r['decision_status']??'')==='accepted'&&$r['local_hotel_id']!==null)$accepted[(string)$r['supplier_namespace']][(int)$r['local_hotel_id']][]=(string)$r['external_hotel_id'];}
    $pdo->rollBack();
    $activeEdges=[];$already=[];foreach($input as $d){$hid=(int)$d['tv_hotel_id'];$ns=(string)$d['target_supplier_namespace'];$h=$hotels[$hid]??null;if(!$h||(int)$h['is_active']!==1||(int)$h['country_id']!==4){$already[]=['tv_hotel_id'=>$hid,'operator'=>$d['operator'],'state'=>'current_target_invalid'];continue;}if(isset($accepted[$ns][$hid])){$already[]=['tv_hotel_id'=>$hid,'operator'=>$d['operator'],'state'=>'already_accepted','external_hotel_ids'=>$accepted[$ns][$hid]];continue;}$activeEdges[]=$d;}
    if(!$activeEdges)throw new RuntimeException('all_edges_already_closed');

    $stdin=file('php://stdin',FILE_IGNORE_NEW_LINES);$username=trim((string)($stdin[0]??''));$password=trim((string)($stdin[1]??''));unset($stdin);if($username===''||$password==='')throw new RuntimeException('credentials_missing');
    hms34_call_reserve($opDir,'login',[],$providerCalls);$providerAccess=true;$catalogClient=new AnyTourAndromedaClient(new AnyTourAndromedaTransport(false,false),true);$catalogClient->login($username,$password);$session=$catalogClient->privateSession();unset($username,$password);if(!$session)throw new RuntimeException('login_session');
    hms34_call_reserve($opDir,'catalog_townfrom',[],$providerCalls);$townfrom=$catalogClient->catalog('townfrom');hms34_raw($opDir,'catalog-townfrom',$townfrom,$rawManifest);$dep=hms34_unique_dict_id($townfrom['TOWNFROM'],['Москва','Moscow']);
    hms34_call_reserve($opDir,'catalog_state',['TOWNFROMINC'=>$dep],$providerCalls);$state=$catalogClient->catalog('state',['TOWNFROMINC'=>$dep]);hms34_raw($opDir,'catalog-state',$state,$rawManifest);$country=hms34_unique_dict_id($state['STATE'],['Турция','Turkey','Turkiye','Türkiye']);
    hms34_call_reserve($opDir,'catalog_all',['TOWNFROMINC'=>$dep,'STATEINC'=>$country],$providerCalls);$all=$catalogClient->catalog('all',['TOWNFROMINC'=>$dep,'STATEINC'=>$country]);hms34_raw($opDir,'catalog-all',$all,$rawManifest);
    $bindings=hm_common4_resolve($all['OPERATORS'],'samo',['funsun','intourist']);if(($bindings['funsun']['provider_operator_id']??'')!=='315'||($bindings['intourist']['provider_operator_id']??'')!=='342')throw new RuntimeException('operator_namespace_drift');
    $towns=hms34_town_labels($all['TOWNTO']??[]);$provider=hms34_provider_rows($all['HOTELS']??[],$towns);

    $edgePlans=[];$groups=[];
    foreach($activeEdges as $idx=>$d){$hid=(int)$d['tv_hotel_id'];$h=$hotels[$hid];$c=hms34_candidates($h,$aliases[$hid]??[],$provider);$candidateIds=array_keys($c['candidates']);$edgeKey=$d['operator'].'|'.$hid.'|'.(string)$d['source_tour_id'];$cx=$d['context'];$g=implode('|',[$d['operator'],$cx['departure_date'],(int)$cx['nights'],(int)$cx['adults'],(int)$cx['children_count'],(string)$cx['child_ages_signature']]);$edgePlans[$edgeKey]=['edge'=>$d,'current_hotel'=>$h,'retrieval_mode'=>$c['mode'],'candidate_total'=>$c['total'],'rare_token'=>$c['rare_token'],'candidates'=>$c['candidates'],'queried_candidate_ids'=>$candidateIds,'price_facts'=>[],'group_key'=>$candidateIds?$g:null];if(!$candidateIds)continue;$groups[$g]['operator']=$d['operator'];$groups[$g]['context']=$cx;$groups[$g]['edge_keys'][]=$edgeKey;foreach($candidateIds as $id)$groups[$g]['candidate_ids'][$id]=true;}
    foreach($groups as $gkey=>&$g){$candidateIds=array_keys($g['candidate_ids']);if(count($candidateIds)>30)throw new RuntimeException('group_candidate_cap');$opId=(string)$bindings[$g['operator']]['provider_operator_id'];$cx=$g['context'];$params=['TOWNFROMINC'=>$dep,'STATEINC'=>$country,'CHECKIN_BEG'=>str_replace('-','',(string)$cx['departure_date']),'CHECKIN_END'=>str_replace('-','',(string)$cx['departure_date']),'NIGHTS_FROM'=>(int)$cx['nights'],'NIGHTS_TILL'=>(int)$cx['nights'],'ADULT'=>(int)$cx['adults'],'CHILD'=>(int)$cx['children_count'],'CURRENCYINC'=>643,'OPERATORS'=>$opId,'HOTELS'=>implode(',',$candidateIds),'PACKETTYPE'=>0,'PAGE'=>1];if((int)$cx['children_count']>0){$ages=(string)$cx['child_ages_signature'];if(!preg_match('/^[0-9]+(?:,[0-9]+)*$/D',$ages)||count(explode(',',$ages))!==(int)$cx['children_count'])throw new RuntimeException('child_age_shape');$params['AGES']=$ages;}
        $needed=array_fill_keys($candidateIds,true);$seen=[];$pages=0;$pagesCount=null;
        for($page=1;$page<=HMS34_MAX_PAGE_PER_GROUP;$page++){
            if($priceCalls>=HMS34_MAX_PRICE_CALLS)throw new RuntimeException('price_call_cap');$params['PAGE']=$page;hms34_call_reserve($opDir,'price',['group_sha256'=>hash('sha256',$gkey),'page'=>$page,'operator'=>$g['operator'],'hotel_count'=>count($candidateIds)],$providerCalls);$priceCalls++;
            $pc=new AnyTourAndromedaClient(new AnyTourAndromedaTransport(true,false),true);$pc->restorePrivateSession($session);$reply=$pc->price($params);hms34_raw($opDir,'price-'.substr(hash('sha256',$gkey),0,12).'-p'.$page,$reply,$rawManifest);$pages++;$pagesCount=(int)$reply['PAGES_COUNT'];
            foreach($reply['PRICES'] as $row){if(!is_array($row))continue;$fact=hms34_price_fact($row,$opId);if(!$fact)continue;$cid=$fact['catalog_hotel_id'];if(!isset($needed[$cid]))continue;$seen[$cid][]=$fact;foreach($g['edge_keys'] as $ek)if(isset($edgePlans[$ek]['candidates'][$cid]))$edgePlans[$ek]['price_facts'][]=$fact;}
            $unseen=array_diff_key($needed,$seen);if(!$unseen||$pagesCount===0||$page>=$pagesCount)break;
        }
        $remaining=array_keys(array_diff_key($needed,$seen));$g['pages_read']=$pages;$g['pages_count_reported']=$pagesCount;$g['seen_candidate_ids']=array_keys($seen);$g['unseen_candidate_ids']=$remaining;$g['pagination_incomplete']=($remaining&&$pagesCount!==null&&$pagesCount>0&&$pages<$pagesCount);unset($g['candidate_ids']);
    }unset($g);

    $evidenceReady=0;$ambiguous=0;$notReturned=0;$noCandidate=0;$dossiers=[];
    foreach($edgePlans as $ek=>$p){$facts=$p['price_facts'];$byCatalog=[];$native=[];foreach($facts as $f){$byCatalog[$f['catalog_hotel_id']]=true;if($f['native_operator_hotel_id']!==null)$native[$f['native_operator_hotel_id']]=true;}$seenCatalog=array_keys($byCatalog);$nativeIds=array_keys($native);$state='';$incomplete=$p['group_key']!==null&&(($groups[$p['group_key']]['pagination_incomplete']??false)===true);if(!$p['queried_candidate_ids']){$state='no_live_catalog_candidate';$noCandidate++;}elseif($incomplete){$state='provider_pagination_hold';$ambiguous++;}elseif(!$seenCatalog){$state='not_returned_exact_context';$notReturned++;}elseif(count($nativeIds)===1&&count($seenCatalog)===1){$state='direct_operator_hotel_id_observed';$evidenceReady++;}else{$state='ambiguous_provider_evidence';$ambiguous++;}
        $dossiers[]=['tv_hotel_id'=>(int)$p['edge']['tv_hotel_id'],'hotel_name'=>(string)$p['edge']['hotel_name'],'operator'=>(string)$p['edge']['operator'],'target_supplier_namespace'=>(string)$p['edge']['target_supplier_namespace'],'source_tour_id'=>(string)$p['edge']['source_tour_id'],'context'=>$p['edge']['context'],'state'=>$state,'retrieval_mode'=>$p['retrieval_mode'],'candidate_total'=>$p['candidate_total'],'rare_token'=>$p['rare_token'],'queried_candidates'=>array_values(array_map(fn($x)=>['catalog_hotel_id'=>$x['catalog_hotel_id'],'names'=>$x['names'],'town_key'=>$x['town_key'],'town_labels'=>$x['town_labels'],'geo_relation'=>$x['geo_relation'],'retrieval'=>$x['retrieval'],'name_score'=>$x['name_score']],$p['candidates'])),'seen_catalog_hotel_ids'=>$seenCatalog,'native_operator_hotel_ids'=>$nativeIds,'safe_to_write_now'=>false];
    }
    $rawManifestSha=hms34_json_file($opDir.'/raw-manifest.json',['files'=>$rawManifest]);
    $result=['operation'=>HMS34_OPERATION,'state'=>'completed_read_only','source_sha'=>$sourceSha,'source_context_sha256'=>HMS34_CONTEXT_SHA,'provider_access'=>true,'provider_calls'=>$providerCalls,'catalog_calls'=>3,'login_calls'=>1,'price_calls'=>$priceCalls,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>true,'input_edges'=>39,'active_edges'=>count($activeEdges),'already_closed_since_context'=>$already,'live_operator_bindings'=>$bindings,'live_catalog_hotel_count'=>count($provider),'group_count'=>count($groups),'groups'=>$groups,'direct_operator_hotel_id_observed_count'=>$evidenceReady,'ambiguous_provider_evidence_count'=>$ambiguous,'not_returned_exact_context_count'=>$notReturned,'no_live_catalog_candidate_count'=>$noCandidate,'dossiers'=>$dossiers,'raw_manifest_sha256'=>$rawManifestSha];
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$result=['operation'=>HMS34_OPERATION,'state'=>$providerAccess?'terminal_failed_no_replay':'failed_before_provider_access','source_sha'=>$sourceSha,'reason'=>preg_replace('/[^a-z0-9_\-]/i','_',hms34_cut($e->getMessage(),120)),'provider_access'=>$providerAccess,'provider_calls'=>$providerCalls,'price_calls'=>$priceCalls,'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'no_replay'=>$providerAccess];if($rawManifest&&!file_exists($opDir.'/raw-manifest.json')){$result['raw_manifest_sha256']=hms34_json_file($opDir.'/raw-manifest.json',['files'=>$rawManifest]);}}
$resultSha=hms34_json_file($opDir.'/result.json',$result);$receipt=['operation'=>HMS34_OPERATION,'state'=>$result['state'],'result_sha256'=>$resultSha,'provider_access'=>$result['provider_access'],'provider_calls'=>$result['provider_calls'],'price_calls'=>$result['price_calls'],'tourvisor_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'raw_manifest_sha256'=>$result['raw_manifest_sha256']??null,'no_replay'=>$result['no_replay']];hms34_json_file($opDir.'/receipt.json',$receipt);echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit(($result['state']??'')==='completed_read_only'?0:2);
