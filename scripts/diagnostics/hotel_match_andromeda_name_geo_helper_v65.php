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
