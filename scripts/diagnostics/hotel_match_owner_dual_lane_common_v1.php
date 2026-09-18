<?php
declare(strict_types=1);

const HMD_OP='hotel-match-owner-dual-lane-1971-20260918-v1';
const HMD_TV_EXTRA_CAP=300;
const HMD_A_MAX_BATCHES=2;
const HMD_A_BATCH_SIZE=30;
const HMD_B_MAX_CONTEXTS=3;
const HMD_TV_MAX_CONTINUE=10;
const HMD_TV_BODY_LIMIT=16777216;
const HMD_ANEX_MAX_PAGES=20;
const HMD_AND_MAX_PAGES=40;
const HMD_EXCLUDED_COUNTRIES=[46=>true,47=>true];

function hmd_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hmd_cut(string $v,int $max):string{return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);}
function hmd_lower(string $v):string{if(function_exists('mb_strtolower'))return mb_strtolower($v,'UTF-8');return strtr(strtolower($v),array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY)));}
function hmd_text(mixed $v,int $max=500):string{if(!is_scalar($v))return'';$s=trim((string)$v);return hmd_cut($s,$max);}
function hmd_id(mixed $v):?int{if(is_array($v))$v=$v['id']??null;$n=filter_var($v,FILTER_VALIDATE_INT);return$n!==false&&(int)$n>0?(int)$n:null;}
function hmd_norm(string $v):string{$v=hmd_lower(trim($v));$v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','&'=>' ','+'=>' ','_'=>' ','-'=>' ']);$v=preg_replace('/[^\p{L}\p{N}]+/u',' ',$v)??$v;return trim(preg_replace('/\s+/u',' ',$v)??$v);}
function hmd_tokens(string $v):array{$drop=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'гостиница'=>1,'room'=>1,'номер'=>1,'комната'=>1];$o=[];foreach(preg_split('/\s+/u',hmd_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[]as$t)if(!isset($drop[$t]))$o[(string)$t]=true;return array_map('strval',array_keys($o));}
function hmd_room_key(string $v):string{$drop=['room'=>1,'номер'=>1,'комната'=>1];$t=[];foreach(preg_split('/\\s+/u',hmd_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[]as$x)if(!isset($drop[$x]))$t[(string)$x]=true;$k=array_map('strval',array_keys($t));sort($k,SORT_STRING);return implode(' ',$k);}
function hmd_product(string $v):bool{$n=hmd_norm($v);if(preg_match('/^(roulette|рулетка)(\s|$)/u',$n))return true;if(!preg_match('/^(fortuna|фортуна)(\s|$)/u',$n))return false;$tokens=preg_split('/\s+/u',$n,-1,PREG_SPLIT_NO_EMPTY)?:[];$physical=(bool)preg_match('/\b(hotel|hotels|otel|отель|resort)\b/u',$n);return!($physical&&count($tokens)>=3);}
function hmd_rows(PDO $db,string $sql,array $params=[]):array{$s=$db->prepare($sql);$s->execute(array_values($params));return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
function hmd_durable(string $path,array $v):string{$raw=hmd_json($v)."\n";$f=@fopen($path,'x+b');if(!$f)throw new RuntimeException('durable_create');try{if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('durable_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('durable_sync');rewind($f);if(stream_get_contents($f)!==$raw)throw new RuntimeException('durable_readback');}finally{fclose($f);}return hash('sha256',$raw);}
function hmd_append(string $path,array $v):void{$raw=hmd_json($v)."\n";$f=@fopen($path,'ab');if(!$f)throw new RuntimeException('progress_open');try{if(!flock($f,LOCK_EX))throw new RuntimeException('progress_lock');if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('progress_write');if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('progress_sync');}finally{flock($f,LOCK_UN);fclose($f);}}
function hmd_aliases(string $v,array $groups):array{$n=hmd_norm($v);$o=[$n=>true];foreach($groups as$g){$ng=array_map('hmd_norm',$g);if(in_array($n,$ng,true))foreach($ng as$x)$o[$x]=true;}return array_keys($o);}
function hmd_departure_aliases(string $v):array{return hmd_aliases($v,[['Москва','Moscow'],['Санкт-Петербург','Санкт Петербург','С.Петербург','Saint Petersburg','St Petersburg'],['Екатеринбург','Yekaterinburg','Ekaterinburg'],['Казань','Kazan'],['Новосибирск','Novosibirsk'],['Самара','Samara'],['Уфа','Ufa'],['Челябинск','Chelyabinsk'],['Нижний Новгород','Nizhny Novgorod'],['Минеральные Воды','Mineralnye Vody'],['Пермь','Perm'],['Тюмень','Tyumen'],['Омск','Omsk'],['Красноярск','Krasnoyarsk'],['Иркутск','Irkutsk']]);}
function hmd_country_aliases(string $v):array{return hmd_aliases($v,[['Турция','Turkey','Turkiye','Türkiye'],['Египет','Egypt'],['ОАЭ','UAE','United Arab Emirates','Объединенные Арабские Эмираты'],['Мальдивы','Maldives'],['Вьетнам','Vietnam'],['Таиланд','Thailand'],['Куба','Cuba'],['Шри-Ланка','Sri Lanka'],['Катар','Qatar'],['Китай','China'],['Маврикий','Mauritius'],['Индонезия','Indonesia'],['Тунис','Tunisia'],['Индия','India'],['Танзания','Tanzania'],['Узбекистан','Uzbekistan'],['Марокко','Morocco'],['Сейшелы','Seychelles']]);}
function hmd_country_key(string $v):string{$n=hmd_norm($v);$groups=[['turkey','turkiye','türkiye','турция'],['egypt','египет'],['uae','united arab emirates','объединенные арабские эмираты','оаэ'],['maldives','мальдивы'],['vietnam','вьетнам'],['thailand','таиланд','тайланд'],['china','китай'],['india','индия'],['tanzania','танзания'],['qatar','катар'],['cuba','куба'],['sri lanka','шри ланка'],['mauritius','маврикий'],['indonesia','индонезия'],['tunisia','тунис'],['morocco','марокко'],['seychelles','сейшелы']];foreach($groups as$g)if(in_array($n,$g,true))return$g[0];return$n;}
function hmd_dist(mixed $a,mixed $b,mixed $c,mixed $d):?float{foreach([$a,$b,$c,$d]as$v)if(!is_numeric($v))return null;$lat1=deg2rad((float)$a);$lon1=deg2rad((float)$b);$lat2=deg2rad((float)$c);$lon2=deg2rad((float)$d);$x=sin(($lat2-$lat1)/2)**2+cos($lat1)*cos($lat2)*sin(($lon2-$lon1)/2)**2;return 6371000*2*asin(min(1,sqrt($x)));}
function hmd_resort_aliases(string $v,string $sub=''):array{
  $groups=[
    ['Анталья','Анталия','Antalya'],['Аланья','Алания','Alanya'],['Сиде','Side'],['Кемер','Kemer'],['Белек','Belek'],
    ['Бодрум','Bodrum'],['Мармарис','Marmaris'],['Фетхие','Fethiye'],['Даламан','Dalaman'],
    ['Шарм-эль-Шейх','Шарм эль Шейх','Sharm El Sheikh','Sharm El-Sheikh'],['Хургада','Hurghada'],['Макади-Бей','Макади Бей','Makadi Bay'],
    ['Марса-Алам','Марса Алам','Marsa Alam'],['Сома-Бей','Сома Бей','Soma Bay'],
    ['Пхукет','Phuket'],['Паттайя','Pattaya'],['Као-Лак','Као Лак','Khao Lak'],['Самуи','Koh Samui','Samui'],
    ['Фукуок','Фу Куок','Phu Quoc'],['Нячанг','Нячанг','Nha Trang'],['Муйне','Муй Не','Mui Ne'],
    ['Занзибар','Zanzibar'],['Нунгви','Nungwi'],['Пунта-Кана','Пунта Кана','Punta Cana']
  ];
  $out=[];foreach([$v,$sub]as$x){$x=trim($x);if($x==='')continue;foreach(hmd_aliases($x,$groups)as$a)$out[$a]=true;}
  return array_keys($out);
}
function hmd_resort_match(string $town,array $aliases):bool{$n=hmd_norm($town);if($n==='')return false;foreach($aliases as$a){$a=hmd_norm((string)$a);if($a!==''&&($n===$a||str_contains($n,$a)||str_contains($a,$n)))return true;}return false;}
function hmd_dict_id(array $rows,array $aliases):?int{$want=array_fill_keys(array_map('hmd_norm',$aliases),true);$hits=[];foreach($rows as$r){if(!is_array($r))continue;$id=hmd_id($r['id']??$r['key']??null);if(!$id)continue;foreach(['name','lName','nameAlt','alias','currencyISO']as$k){$n=hmd_norm(hmd_text($r[$k]??''));if($n!==''&&isset($want[$n]))$hits[$id]=true;}}return count($hits)===1?(int)array_key_first($hits):null;}
function hmd_operator_id(array $rows,string $family):?int{$aliases=$family==='anex'?['anex','anex tour','anextour','анекс','анекс тур']:[$family];$hits=[];foreach($rows as$r){if(!is_array($r))continue;$id=hmd_id($r['id']??$r['key']??null);$n=hmd_norm(hmd_text($r['name']??$r['lName']??''));if(!$id||$n==='')continue;foreach($aliases as$a){$a=hmd_norm($a);if($n===$a||str_replace(' ','',$n)===str_replace(' ','',$a))$hits[$id]=true;}}return count($hits)===1?(int)array_key_first($hits):null;}
function hmd_star_id(array $rows,int $star):?int{$hits=[];foreach($rows as$r){if(!is_array($r))continue;$id=hmd_id($r['id']??$r['key']??null);$n=hmd_norm(hmd_text($r['name']??$r['lName']??''));if(!$id||$n==='')continue;if(preg_match('/(?:^|\s)'.preg_quote((string)$star,'/').'\s*(?:\*|star|stars|звезд|звезды|звезда)?(?:\s|$)/u',$n))$hits[$id]=true;}return count($hits)===1?(int)array_key_first($hits):null;}
function hmd_star_value(mixed $v):?int{$s=hmd_norm(hmd_text($v,80));if(preg_match('/(?:^|\s)([1-5])(?:\s|\*|star|зв)/u',$s,$m))return(int)$m[1];return null;}
