<?php
declare(strict_types=1);
/** MATCH #1971 — CURRENT evidence revalidation for the immutable 244-row geography manifest. READ ONLY. */
const HMCGE_OPERATION='hotel-match-current-geo-evidence-1971-20260912-v1';
const HMCGE_MAX_HOTELS=200000;
const HMCGE_MAX_ALIASES=1000000;

function hmcge_int($v): ?int { return is_numeric($v)&&(int)$v>0?(int)$v:null; }
function hmcge_table(PDO $pdo,string $table): bool {$q=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);return $q->fetchColumn()!==false;}
function hmcge_cols(PDO $pdo,string $table): array {$q=$pdo->query('SHOW COLUMNS FROM `'.$table.'`');return $q?$q->fetchAll(PDO::FETCH_COLUMN):[];}
function hmcge_pick(array $cols,array $choices): ?string {foreach($choices as $c)if(in_array($c,$cols,true))return $c;return null;}
function hmcge_translit(string $s): string {
    $map=['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    return strtr(mb_strtolower($s,'UTF-8'),$map);
}
function hmcge_norm(string $s): string {
    $s=mb_strtoupper(str_replace(['ё','Ё','&'],['е','Е',' AND '],trim($s)),'UTF-8');
    $s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;
    $parts=preg_split('/\s+/u',trim($s),-1,PREG_SPLIT_NO_EMPTY)?:[];
    $generic=['HOTEL'=>1,'HOTELS'=>1,'RESORT'=>1,'SPA'=>1,'OTEL'=>1,'ОТЕЛЬ'=>1,'ОТЕЛЯ'=>1];
    $parts=array_values(array_filter($parts,static fn($x)=>!isset($generic[$x])));
    return implode(' ',$parts);
}
function hmcge_geo_forms(array $fields): array {
    $out=[];
    foreach($fields as $field){if(!is_scalar($field))continue;$raw=trim((string)$field);if($raw==='')continue;
        $pieces=[$raw];foreach(preg_split('/[-\/,;()]+/u',$raw,-1,PREG_SPLIT_NO_EMPTY)?:[] as $p)if(mb_strlen(trim($p),'UTF-8')>=3)$pieces[]=trim($p);
        foreach($pieces as $p){foreach([$p,hmcge_translit($p)] as $v){$n=hmcge_norm($v);if($n!==''&&mb_strlen($n,'UTF-8')>=3)$out[$n]=true;}}
    }
    return array_keys($out);
}
function hmcge_edge_variants(string $name,array $geo): array {
    $raw=[];$base=hmcge_norm($name);if($base!=='')$raw[$base]=true;
    // Current-name form without parenthetical old-name notes.
    $without=preg_replace('/\s*\([^)]*\)\s*/u',' ',$name)??$name;$n=hmcge_norm($without);if($n!=='')$raw[$n]=true;
    // Former-name contents are aliases, not current identity authority.
    $former=[];if(preg_match_all('/\((?:\s*(?:EX\.?|FORMERLY|БЫВШ\.?)[\s:]*)?([^)]{2,180})\)/iu',$name,$m))foreach($m[1] as $x){$v=hmcge_norm((string)$x);if($v!=='')$former[$v]=true;}
    $expand=static function(array $seed,array $geo): array {$out=$seed;for($i=0;$i<3;$i++){foreach(array_keys($out) as $value)foreach($geo as $g){if($g===''||$value===$g)continue;if(str_starts_with($value,$g.' '))$out[trim(substr($value,strlen($g)))]=true;if(str_ends_with($value,' '.$g))$out[trim(substr($value,0,-strlen($g)))]=true;}}return array_filter(array_keys($out),static fn($x)=>$x!=='');};
    return ['current'=>$expand($raw,$geo),'former'=>$expand($former,$geo)];
}
function hmcge_distance(?float $a,?float $b,?float $c,?float $d): ?float {if($a===null||$b===null||$c===null||$d===null)return null;$r=6371.0088;$p1=deg2rad($a);$p2=deg2rad($c);$da=deg2rad($c-$a);$do=deg2rad($d-$b);$x=sin($da/2)**2+cos($p1)*cos($p2)*sin($do/2)**2;return $r*2*atan2(sqrt($x),sqrt(max(0.0,1-$x)));}
function hmcge_country(string $s): ?string {$n=hmcge_norm($s);$map=['EGYPT'=>['EGYPT','ЕГИПЕТ'],'TURKEY'=>['TURKEY','TURKIYE','TÜRKIYE','ТУРЦИЯ'],'THAILAND'=>['THAILAND','ТАИЛАНД'],'UAE'=>['UAE','UNITED ARAB EMIRATES','ОАЭ','ОБЪЕДИНЕННЫЕ АРАБСКИЕ ЭМИРАТЫ'],'VIETNAM'=>['VIETNAM','VIET NAM','ВЬЕТНАМ'],'SRILANKA'=>['SRI LANKA','ШРИ ЛАНКА'],'MALDIVES'=>['MALDIVES','МАЛЬДИВЫ'],'CUBA'=>['CUBA','КУБА']];foreach($map as $k=>$vals)if(in_array($n,$vals,true))return $k;return null;}
function hmcge_manifest(string $path): array {$m=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);if(($m['schema']??'')!=='hotel-match-geo-current-preflight-manifest/1'||($m['not_write_authority']??false)!==true||count($m['anex']??[])!==209||count($m['andromeda']??[])!==35)throw new RuntimeException('manifest_guard');return $m;}
function hmcge_evidence_walk($value,array &$names,array &$geo,array &$coords,int $depth=0): void {if($depth>10||!is_array($value))return;foreach($value as $k=>$v){$key=mb_strtolower((string)$k,'UTF-8');if(is_scalar($v)){if(in_array($key,['name','lname','hotel','hotelname','hotel_name'],true)){if(trim((string)$v)!=='')$names[(string)$v]=true;}if(in_array($key,['region','regionname','region_name','town','townname','town_name','city','cityname','resort','resortname'],true)){if(trim((string)$v)!=='')$geo[(string)$v]=true;}if(in_array($key,['latitude','lat'],true)&&is_numeric($v))$coords['lat']=(float)$v;if(in_array($key,['longitude','lon','lng'],true)&&is_numeric($v))$coords['lon']=(float)$v;}elseif(is_array($v))hmcge_evidence_walk($v,$names,$geo,$coords,$depth+1);}}
function hmcge_query_ids(PDO $pdo,string $table,string $idCol,array $ids,array $wanted): array {if(!hmcge_table($pdo,$table)||!$ids)return [];$cols=hmcge_cols($pdo,$table);if(!in_array($idCol,$cols,true))return [];$sel=array_values(array_unique(array_merge([$idCol],array_intersect($wanted,$cols))));$out=[];foreach(array_chunk(array_values($ids),500) as $chunk){$q=$pdo->prepare('SELECT `'.implode('`,`',$sel).'` FROM `'.$table.'` WHERE `'.$idCol.'` IN ('.implode(',',array_fill(0,count($chunk),'?')).')');$q->execute($chunk);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$out[]=$r;}return $out;}

if(in_array('--self-test',$_SERVER['argv']??[],true)){
    $v=hmcge_edge_variants('BIRBEY HOTEL LALELI',['LALELI']);if(!in_array('BIRBEY',$v['current'],true))exit(2);
    $v=hmcge_edge_variants('PICKALBATROS CITADEL RESORT (EX. CITADEL AZUR RESORT)',['HURGHADA']);if(!in_array('CITADEL AZUR',$v['former'],true))exit(3);
    if(hmcge_country('Турция')!=='TURKEY'||hmcge_norm('North Garden Resort & Spa')!=='NORTH GARDEN AND')exit(4);
    if((hmcge_distance(36.713018,31.563078,36.713018,31.563078)??1)>0.001)exit(5);
    echo "MATCH CURRENT geo evidence self-test PASS; network=0 database=0\n";exit(0);
}

error_reporting(0);$pdo=null;
try{
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');$root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    $manifest=hmcge_manifest(getenv('HMCGE_MANIFEST_PATH')?:$root.'/reports/hotel-match-geo-current-preflight-manifest-1971.json');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');require_once $root.'/app/integrations/anex-search-mapping-registry.php';
    $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION READ ONLY');
    $catalogCols=hmcge_cols($pdo,'catalog_hotels');$latCol=hmcge_pick($catalogCols,['latitude','lat']);$lonCol=hmcge_pick($catalogCols,['longitude','lon','lng']);
    $wanted=['id','name','country_id','country_name','region_name','subregion_name','is_active'];if($latCol)$wanted[]=$latCol;if($lonCol)$wanted[]=$lonCol;
    $q=$pdo->query('SELECT `'.implode('`,`',array_values(array_intersect($wanted,$catalogCols))).'` FROM catalog_hotels WHERE is_active=1 LIMIT '.(HMCGE_MAX_HOTELS+1));$locals=$q->fetchAll(PDO::FETCH_ASSOC);if(count($locals)>HMCGE_MAX_HOTELS)throw new RuntimeException('catalog_bound');
    $local=[];$countryIndex=[];$index=[];
    foreach($locals as $r){$id=hmcge_int($r['id']??null);if(!$id)continue;$geo=hmcge_geo_forms([$r['region_name']??'',$r['subregion_name']??'']);$forms=hmcge_edge_variants((string)($r['name']??''),$geo);$r['_geo']=$geo;$r['_current']=$forms['current'];$r['_alias']=[];$local[$id]=$r;foreach($forms['current'] as $key)$index[(string)($r['country_id']??'')][$key][$id]['current']=true;}
    if(hmcge_table($pdo,'hotel_aliases')){$cols=hmcge_cols($pdo,'hotel_aliases');$nameCol=hmcge_pick($cols,['alias','name']);if($nameCol&&in_array('hotel_id',$cols,true)){$rows=$pdo->query('SELECT hotel_id,`'.$nameCol.'` alias FROM hotel_aliases LIMIT '.(HMCGE_MAX_ALIASES+1))->fetchAll(PDO::FETCH_ASSOC);if(count($rows)>HMCGE_MAX_ALIASES)throw new RuntimeException('alias_bound');foreach($rows as $r){$id=hmcge_int($r['hotel_id']??null);if(!$id||!isset($local[$id]))continue;$f=hmcge_edge_variants((string)$r['alias'],$local[$id]['_geo']);foreach(array_merge($f['current'],$f['former']) as $key){$local[$id]['_alias'][$key]=true;$index[(string)$local[$id]['country_id']][$key][$id]['alias']=true;}}}}
    $anexIds=array_map(static fn($x)=>(int)$x[0],$manifest['anex']);$andIds=array_map(static fn($x)=>(string)$x[0],$manifest['andromeda']);$targets=[];foreach(['anex','andromeda'] as $p)foreach($manifest[$p] as $x)$targets[(int)$x[1]]=true;
    $anexStage=[];foreach(hmcge_query_ids($pdo,'anex_hotels','anex_hotel_id',$anexIds,['xml_name','xml_alternate_name','api_name','api_country','api_region','api_town','latitude','longitude']) as $r)$anexStage[(int)$r['anex_hotel_id']]=$r;
    $anexObs=[];foreach(hmcge_query_ids($pdo,'anex_search_hotel_observations','anex_hotel_id',$anexIds,['hotel_name','country_id','country_name','region_name','last_seen_utc']) as $r){$id=(int)$r['anex_hotel_id'];$anexObs[$id][]=$r;}
    $andIdentity=[];foreach(hmcge_query_ids($pdo,'andromeda_hotel_identities','external_hotel_id',$andIds,['supplier_namespace','country_id','local_hotel_id','decision_status','evidence_json']) as $r)$andIdentity[(string)$r['external_hotel_id']][]=$r;
    $andObs=[];foreach(hmcge_query_ids($pdo,'andromeda_search_hotel_observations','external_hotel_id',$andIds,['supplier_namespace','hotel_name','country_id','country_name','region_name']) as $r)$andObs[(string)$r['external_hotel_id']][]=$r;
    $registry=AnyTourAnexSearchMappingRegistry::fromPdo($pdo);$resolve=$registry->previewResolver();
    $result=['status'=>'read_only_complete','operation_id'=>HMCGE_OPERATION,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'counts'=>['anex'=>[],'andromeda'=>[]],'rows'=>['anex'=>[],'andromeda'=>[]]];
    $classify=function(string $provider,$external,int $target,array $names,array $aliases,array $geo,?int $countryId,?string $countryName,?float $slat,?float $slon)use(&$local,&$index,&$result): void {
        $row=['external_hotel_id'=>$external,'proposed_local_id'=>$target,'source_names'=>array_values(array_keys($names)),'source_geography'=>array_values(array_keys($geo))];
        if(!isset($local[$target])){$bucket='target_missing_or_inactive';goto done;}$t=$local[$target];$targetCountry=(int)($t['country_id']??0);$sourceClass=$countryName!==null?hmcge_country($countryName):null;$targetClass=hmcge_country((string)($t['country_name']??''));
        if($countryId!==null&&$countryId>0&&$countryId!==$targetCountry){$bucket='country_conflict';goto done;}if($sourceClass!==null&&$targetClass!==null&&$sourceClass!==$targetClass){$bucket='country_conflict';goto done;}
        $tlat=null;$tlon=null;foreach(['latitude','lat'] as $k)if(isset($t[$k])&&is_numeric($t[$k])){$tlat=(float)$t[$k];break;}foreach(['longitude','lon','lng'] as $k)if(isset($t[$k])&&is_numeric($t[$k])){$tlon=(float)$t[$k];break;}$dist=hmcge_distance($slat,$slon,$tlat,$tlon);$row['distance_km']=$dist;if($dist!==null&&$dist>5){$bucket='coordinate_conflict';goto done;}
        if(!$names){$bucket='source_name_missing';goto done;}$geoForms=hmcge_geo_forms(array_keys($geo));$sourceCurrent=[];$sourceAlias=[];foreach(array_keys($names) as $n){$v=hmcge_edge_variants($n,$geoForms);foreach($v['current'] as $k)$sourceCurrent[$k]=true;foreach($v['former'] as $k)$sourceAlias[$k]=true;}foreach(array_keys($aliases) as $n){$v=hmcge_edge_variants($n,$geoForms);foreach(array_merge($v['current'],$v['former']) as $k)$sourceAlias[$k]=true;}
        $candidates=[];$currentTargetHit=false;$aliasTargetHit=false;$matched=[];foreach(array_keys($sourceCurrent+$sourceAlias) as $key){foreach($index[(string)$targetCountry][$key]??[] as $lid=>$kind){$candidates[(int)$lid]=true;if((int)$lid===$target){if(isset($sourceCurrent[$key])&&isset($kind['current']))$currentTargetHit=true;else $aliasTargetHit=true;$matched[$key]=true;}}}
        $row['candidate_local_ids']=array_values(array_keys($candidates));sort($row['candidate_local_ids']);$row['matched_keys']=array_values(array_keys($matched));sort($row['matched_keys']);
        if(!$candidates||!isset($candidates[$target])){$bucket='name_not_currently_supported';goto done;}if(count($candidates)!==1){$bucket='ambiguous_current_identity';goto done;}if($currentTargetHit){$bucket='strong_current_unique';goto done;}if($aliasTargetHit){$bucket='alias_only_unique';goto done;}$bucket='name_not_currently_supported';
        done:$result['counts'][$provider][$bucket]=($result['counts'][$provider][$bucket]??0)+1;$result['rows'][$provider][$bucket][]=$row;
    };
    foreach($manifest['anex'] as [$external,$target]){$external=(int)$external;$target=(int)$target;$current=$resolve('anex_online',(string)$external);if(is_int($current)&&$current>0){$result['counts']['anex'][$current===$target?'already_same':'target_drift']=($result['counts']['anex'][$current===$target?'already_same':'target_drift']??0)+1;continue;}$s=$anexStage[$external]??[];$names=[];$aliases=[];$geo=[];foreach(['api_name','xml_name'] as $k)if(trim((string)($s[$k]??''))!=='')$names[(string)$s[$k]]=true;if(trim((string)($s['xml_alternate_name']??''))!=='')$aliases[(string)$s['xml_alternate_name']]=true;foreach(['api_region','api_town'] as $k)if(trim((string)($s[$k]??''))!=='')$geo[(string)$s[$k]]=true;$cid=null;$cname=trim((string)($s['api_country']??''))?:null;foreach($anexObs[$external]??[] as $o){if(trim((string)($o['hotel_name']??''))!=='')$names[(string)$o['hotel_name']]=true;if(hmcge_int($o['country_id']??null))$cid=hmcge_int($o['country_id']);if(!$cname&&trim((string)($o['country_name']??''))!=='')$cname=(string)$o['country_name'];if(trim((string)($o['region_name']??''))!=='')$geo[(string)$o['region_name']]=true;}$lat=is_numeric($s['latitude']??null)?(float)$s['latitude']:null;$lon=is_numeric($s['longitude']??null)?(float)$s['longitude']:null;$classify('anex',$external,$target,$names,$aliases,$geo,$cid,$cname,$lat,$lon);}
    foreach($manifest['andromeda'] as [$external,$target]){$eid=(string)$external;$target=(int)$target;$entries=$andIdentity[$eid]??[];$accepted=null;foreach($entries as $e)if(($e['decision_status']??'')==='accepted'&&hmcge_int($e['local_hotel_id']??null))$accepted=hmcge_int($e['local_hotel_id']);if($accepted!==null){$result['counts']['andromeda'][$accepted===$target?'already_same':'target_drift']=($result['counts']['andromeda'][$accepted===$target?'already_same':'target_drift']??0)+1;continue;}$names=[];$aliases=[];$geo=[];$coords=[];$cid=null;$cname=null;foreach($entries as $e){if(hmcge_int($e['country_id']??null))$cid=hmcge_int($e['country_id']);$raw=json_decode((string)($e['evidence_json']??''),true);if(is_array($raw))hmcge_evidence_walk($raw,$names,$geo,$coords);}foreach($andObs[$eid]??[] as $o){if(trim((string)($o['hotel_name']??''))!=='')$names[(string)$o['hotel_name']]=true;if(hmcge_int($o['country_id']??null))$cid=hmcge_int($o['country_id']);if(trim((string)($o['country_name']??''))!=='')$cname=(string)$o['country_name'];if(trim((string)($o['region_name']??''))!=='')$geo[(string)$o['region_name']]=true;}$classify('andromeda',$eid,$target,$names,$aliases,$geo,$cid,$cname,$coords['lat']??null,$coords['lon']??null);}
    $pdo->rollBack();$pdo=null;foreach(['anex','andromeda'] as $p)ksort($result['counts'][$p]);$result['safe_current_unique_total']=($result['counts']['anex']['strong_current_unique']??0)+($result['counts']['andromeda']['strong_current_unique']??0);echo 'HMCGE_JSON:'.json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();echo 'HMCGE_JSON:'.json_encode(['status'=>'failed','operation_id'=>HMCGE_OPERATION,'reason'=>preg_replace('/[^A-Za-z0-9_:. -]/','?',substr($e->getMessage(),0,180)),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0],JSON_UNESCAPED_SLASHES).PHP_EOL;exit(2);}
