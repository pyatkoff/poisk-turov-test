<?php
declare(strict_types=1);

const M3S_OPERATION = 'hotel-match-current-missing-third-strict-review-1971-20260911-v1';
const M3S_POLICY = 'owner_exact_and_strong_20260908';
const M3S_CORE8 = [1=>'Египет',2=>'Таиланд',4=>'Турция',8=>'Мальдивы',9=>'ОАЭ',10=>'Куба',12=>'Шри-Ланка',16=>'Вьетнам'];

function m3s_json($v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}
function m3s_norm($v): string {
    $v=mb_strtolower(trim((string)$v),'UTF-8');
    $v=strtr($v,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a','ö'=>'o','ô'=>'o','ó'=>'o','ò'=>'o','ü'=>'u','ú'=>'u','ù'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i','ñ'=>'n']);
    preg_match_all('/[\p{L}\p{N}]+/u',$v,$m);
    return implode(' ',$m[0]);
}
function m3s_tokens($v): array {
    $generic=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'резорт'=>1,'ресорт'=>1,'spa'=>1,'спа'=>1];
    return array_values(array_filter(explode(' ',m3s_norm($v)),static fn($x)=>$x!==''&&!isset($generic[$x])));
}
function m3s_key($v): string { return implode(' ',m3s_tokens($v)); }
function m3s_name_variants($v): array {
    $raw=trim((string)$v); if($raw==='') return [];
    $parts=[$raw];
    if(preg_match_all('/\(\s*(?:ex|ех|former(?:ly)?)\s*\.?\s*[:\-]?\s*([^()]+)\)/iu',$raw,$mm)){
        foreach($mm[1] as $old) if(trim($old)!=='') $parts[]=trim($old);
        $parts[]=preg_replace('/\s*\(\s*(?:ex|ех|former(?:ly)?)\s*\.?\s*[:\-]?\s*[^()]+\)\s*/iu',' ',$raw) ?? $raw;
    }
    if(preg_match('/^(.+?)\s+(?:ex|ех|former(?:ly)?)\s*\.?\s*[:\-]\s*(.+)$/iu',$raw,$m)){
        $parts[]=trim($m[1]); $parts[]=trim($m[2]);
    }
    $out=[];
    foreach($parts as $part){$key=m3s_key($part);if($key!==''&&!isset($out[$key]))$out[$key]=trim((string)$part);}
    return $out;
}
function m3s_qualifiers($v): array {
    $tokens=array_fill_keys(m3s_tokens($v),true); $groups=[];
    $defs=[
        'annex'=>['annex','annexe','annexx'], 'beach'=>['beach','пляж'], 'garden'=>['garden','gardens','сад'],
        'north'=>['north','northern','север','северный'], 'south'=>['south','southern','юг','южный'],
        'east'=>['east','eastern','восток','восточный'], 'west'=>['west','western','запад','западный'],
        'adult'=>['adult','adults','adults-only','adultsonly'], 'family'=>['family','families','семейный'],
        'aqua'=>['aqua','aquapark','waterpark','water','park'], 'gardenview'=>['gardenview'],
        'club'=>['club','клуб'], 'palace'=>['palace','дворец'], 'grand'=>['grand'], 'royal'=>['royal'],
        'premium'=>['premium'], 'select'=>['select'], 'boutique'=>['boutique']
    ];
    foreach($defs as $group=>$words)foreach($words as $word)if(isset($tokens[m3s_norm($word)])){$groups[$group]=true;break;}
    ksort($groups); return array_keys($groups);
}
function m3s_qualifier_compatible($a,$b): bool {
    return m3s_qualifiers($a)===m3s_qualifiers($b);
}
function m3s_country($v): ?int {
    $n=m3s_norm($v); $groups=[1=>['egypt','египет'],2=>['thailand','таиланд','тайланд'],4=>['turkey','turkiye','турция'],8=>['maldives','мальдивы'],9=>['united arab emirates','uae','оаэ','эмираты'],10=>['cuba','куба'],12=>['sri lanka','шри ланка'],16=>['vietnam','viet nam','вьетнам']];
    foreach($groups as $id=>$names)foreach($names as $name)if($n===m3s_norm($name))return $id; return null;
}
function m3s_num($v): ?float { if($v===null||$v==='')return null;$x=(float)$v;return is_finite($x)&&$x!=0.0?$x:null; }
function m3s_coord(array $source): array {
    foreach([['latitude','longitude'],['lat','lng'],['lat','lon'],['hotelLatitude','hotelLongitude']] as $keys){
        if(!array_key_exists($keys[0],$source)||!array_key_exists($keys[1],$source))continue;
        $lat=m3s_num($source[$keys[0]]);$lon=m3s_num($source[$keys[1]]);
        if($lat!==null&&$lon!==null&&abs($lat)<=90&&abs($lon)<=180)return[$lat,$lon];
    }
    return [null,null];
}
function m3s_dist($a,$b,$c,$d): ?float {
    $p=[m3s_num($a),m3s_num($b),m3s_num($c),m3s_num($d)];if(in_array(null,$p,true))return null;
    $p=array_map('deg2rad',$p);[$a,$b,$c,$d]=$p;$x=sin(($c-$a)/2)**2+cos($a)*cos($c)*sin(($d-$b)/2)**2;return 6371000*2*asin(min(1,sqrt($x)));
}
function m3s_place_match(array $source,array $target): bool {
    $a=[];$b=[];foreach($source as $v){$n=m3s_norm($v);if($n!=='')$a[$n]=1;}foreach($target as $v){$n=m3s_norm($v);if($n!=='')$b[$n]=1;}return (bool)array_intersect_key($a,$b);
}
function m3s_evidence($raw): array { try{$v=json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR);return is_array($v)?$v:[];}catch(Throwable $e){return [];} }
function m3s_star(array $source): ?int {
    foreach(['category','star','stars','starName','star_name'] as $key){if(!array_key_exists($key,$source))continue;$v=trim((string)$source[$key]);if(preg_match('/^([1-5])(?:\s*(?:\*|★|stars?))?$/iu',$v,$m))return(int)$m[1];}
    return null;
}
function m3s_similarity($a,$b): array {
    $left=array_fill_keys(array_unique(m3s_tokens($a)),true);$right=array_fill_keys(array_unique(m3s_tokens($b)),true);
    $shared=count(array_intersect_key($left,$right));$union=count($left+$right);return[$union?$shared/$union:0.0,$shared];
}
function m3s_add_name(array &$targetNames,int $local,$name,string $kind): void {
    foreach(m3s_name_variants($name) as $key=>$raw){$targetNames[$local][$key]??=['raw'=>$raw,'kinds'=>[]];$targetNames[$local][$key]['kinds'][$kind]=true;}
}
function m3s_add_index(array &$index,int $country,int $local,array $nameRows): void {
    foreach($nameRows as $key=>$meta){$index[$country][$key][$local][]=$meta;}
}
function m3s_source_names(array $names): array {
    $out=[];foreach($names as $name)foreach(m3s_name_variants($name) as $key=>$raw)$out[$key]=$raw;return $out;
}
function m3s_source_coord(array $source): array {
    [$lat,$lon]=m3s_coord($source);if($lat!==null)return[$lat,$lon];
    foreach(['source','geography','hotel','location'] as $key)if(isset($source[$key])&&is_array($source[$key])){[$lat,$lon]=m3s_coord($source[$key]);if($lat!==null)return[$lat,$lon];}
    return [null,null];
}
function m3s_direct_geo(array $source,array $target): array {
    [$lat,$lon]=m3s_source_coord($source);$distance=m3s_dist($lat,$lon,$target['latitude']??null,$target['longitude']??null);
    $place=m3s_place_match($source['places']??[],[$target['region_name']??'',$target['subregion_name']??'']);
    return ['distance_m'=>$distance===null?null:(int)round($distance),'coordinate_conflict'=>$distance!==null&&$distance>5000,'coordinate_confirm'=>$distance!==null&&$distance<=1000,'place_confirm'=>$place,'direct_confirm'=>($distance!==null&&$distance<=1000)||$place];
}
function m3s_target_row(array $target): array {
    return ['local_hotel_id'=>(int)$target['id'],'name'=>$target['name'],'country_id'=>(int)$target['country_id'],'region'=>$target['region_name'],'subregion'=>$target['subregion_name'],'category'=>$target['category']===null?null:(int)$target['category']];
}
function m3s_exact_candidate(array $source,array $index,array $targets,array $targetNames,array $excluded=[]): array {
    $country=(int)$source['country_id'];$sourceVariants=m3s_source_names($source['names']??[]);$matches=[];
    foreach($sourceVariants as $key=>$raw){foreach($index[$country][$key]??[] as $local=>$metas){foreach($metas as $meta){if(!m3s_qualifier_compatible($raw,$meta['raw']))continue;$matches[(int)$local][]=['source'=>$raw,'target'=>$meta['raw'],'key'=>$key,'kinds'=>array_keys($meta['kinds']??[])];}}}
    if(count($matches)>1)return ['bucket'=>'needs_extra_evidence','reason'=>'strict_name_ambiguous','candidate_ids'=>array_map('intval',array_keys($matches))];
    if(count($matches)!==1)return ['bucket'=>'no_exact'];
    $local=(int)array_key_first($matches);$target=$targets[$local];$match=$matches[$local][0];$geo=m3s_direct_geo($source,$target);
    if(isset($excluded[$local]))return ['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected','target'=>m3s_target_row($target),'match'=>$match,'guard'=>$geo];
    if($geo['coordinate_conflict'])return ['bucket'=>'hard_conflict','reason'=>'coordinate_conflict_gt_5km','target'=>m3s_target_row($target),'match'=>$match,'guard'=>$geo];
    $tokens=count(m3s_tokens($match['source']));
    if($tokens<2&&!$geo['direct_confirm'])return ['bucket'=>'needs_extra_evidence','reason'=>'single_token_requires_direct_geo','target'=>m3s_target_row($target),'match'=>$match,'guard'=>$geo];
    return ['bucket'=>'auto_accept','reason'=>'unique_strict_missing_third_bridge','target'=>m3s_target_row($target),'match'=>$match,'guard'=>$geo,'significant_tokens'=>$tokens];
}
function m3s_fuzzy_candidate(array $source,array $targetIds,array $targets,array $targetNames,array $excluded=[]): array {
    $sourceNames=array_values(m3s_source_names($source['names']??[]));if(!$sourceNames)return ['bucket'=>'needs_extra_evidence','reason'=>'source_name_missing'];
    $ranked=[];
    foreach($targetIds as $local){$target=$targets[$local];$geo=m3s_direct_geo($source,$target);if(!$geo['direct_confirm'])continue;if($geo['coordinate_conflict'])continue;
        $bestScore=0.0;$bestShared=0;$bestPair=null;
        foreach($sourceNames as $sn)foreach($targetNames[$local]??[] as $meta){$tn=$meta['raw'];if(!m3s_qualifier_compatible($sn,$tn))continue;[$score,$shared]=m3s_similarity($sn,$tn);if($score>$bestScore||($score===$bestScore&&$shared>$bestShared)){$bestScore=$score;$bestShared=$shared;$bestPair=['source'=>$sn,'target'=>$tn,'kinds'=>array_keys($meta['kinds']??[])];}}
        if($bestPair)$ranked[]=['local'=>$local,'score'=>$bestScore,'shared'=>$bestShared,'pair'=>$bestPair,'guard'=>$geo];
    }
    usort($ranked,static fn($a,$b)=>$b['score']<=>$a['score'] ?: $b['shared']<=>$a['shared'] ?: $a['local']<=>$b['local']);
    $best=$ranked[0]??null;if(!$best)return ['bucket'=>'needs_extra_evidence','reason'=>'cross_provider_or_supplier_evidence_needed'];$second=$ranked[1]['score']??0.0;$margin=$best['score']-$second;$local=(int)$best['local'];
    if(isset($excluded[$local]))return ['bucket'=>'hard_conflict','reason'=>'pair_exclusion_protected','target'=>m3s_target_row($targets[$local]),'guard'=>$best['guard']];
    if($best['score']>=0.90&&$best['shared']>=2&&$margin>=0.25)return ['bucket'=>'auto_accept','reason'=>'fuzzy_missing_third_bridge_direct_geo_large_margin','target'=>m3s_target_row($targets[$local]),'match'=>$best['pair'],'guard'=>$best['guard'],'score'=>round($best['score'],6),'margin'=>round($margin,6),'shared'=>$best['shared']];
    return ['bucket'=>'needs_extra_evidence','reason'=>'fuzzy_bridge_below_strict_margin','target'=>m3s_target_row($targets[$local]),'guard'=>$best['guard'],'score'=>round($best['score'],6),'margin'=>round($margin,6),'shared'=>$best['shared']];
}
function m3s_classify(array $source,array $index,array $targetIds,array $targets,array $targetNames,array $excluded=[]): array {
    $row=m3s_exact_candidate($source,$index,$targets,$targetNames,$excluded);if(($row['bucket']??'')!=='no_exact')return $row;return m3s_fuzzy_candidate($source,$targetIds,$targets,$targetNames,$excluded);
}
function m3s_coverage(PDO $db): array {
    $anex=[];$q=$db->query("SELECT m.catalog_hotel_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".M3S_POLICY."' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)");foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id)$anex[(int)$id]=true;
    $q=$db->query("SELECT d.catalog_hotel_id FROM anex_hotel_decisions d WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)");foreach($q->fetchAll(PDO::FETCH_COLUMN) as $id)$anex[(int)$id]=true;
    $andr=[];foreach($db->query("SELECT DISTINCT local_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $id)$andr[(int)$id]=true;
    $triple=count(array_intersect_key($anex,$andr));
    $links=(int)$db->query("SELECT COUNT(*) FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".M3S_POLICY."' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)")->fetchColumn();$manual=(int)$db->query("SELECT COUNT(*) FROM anex_hotel_decisions WHERE decision_status='accepted' AND catalog_hotel_id IS NOT NULL")->fetchColumn();
    return ['anex_links'=>$links+$manual,'anex_unique_local'=>count($anex),'andromeda_unique_local'=>count($andr),'all_three'=>$triple,'anex_tv_only'=>count($anex)-$triple,'andromeda_tv_only'=>count($andr)-$triple,'exactly_two'=>count($anex)+count($andr)-2*$triple];
}

try {
    ini_set('display_errors','0');ini_set('log_errors','0');
    $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('server_root_invalid');
    ob_start();require_once $root.'/config.php';require_once (is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php');ob_end_clean();
    $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $required=['catalog_hotels','catalog_hotel_details','hotel_aliases','anex_hotels','anex_search_hotel_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions','andromeda_hotel_identities','andromeda_search_hotel_observations'];
    $marks=implode(',',array_fill(0,count($required),'?'));$q=$db->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($marks)");$q->execute($required);$got=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);foreach($required as $table)if(!isset($got[$table]))throw new RuntimeException('required_table_missing_'.$table);
    $db->beginTransaction();
    $coverage=m3s_coverage($db);

    $targets=[];$targetNames=[];$targetByCountry=[];
    $sql='SELECT h.id,h.country_id,h.name,h.normalized_name,h.region_name,h.subregion_name,h.category,h.is_active,COALESCE(d.latitude,h.latitude) latitude,COALESCE(d.longitude,h.longitude) longitude FROM catalog_hotels h LEFT JOIN catalog_hotel_details d ON d.hotel_id=h.id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1 ORDER BY h.country_id,h.id';
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $h){$id=(int)$h['id'];$c=(int)$h['country_id'];$targets[$id]=$h;$targetByCountry[$c][]=$id;m3s_add_name($targetNames,$id,$h['name'],'local_name');m3s_add_name($targetNames,$id,$h['normalized_name'],'local_normalized');}
    foreach($db->query('SELECT a.hotel_id,a.alias,a.normalized_alias,h.country_id FROM hotel_aliases a JOIN catalog_hotels h ON h.id=a.hotel_id WHERE h.country_id IN (1,2,4,8,9,10,12,16) AND h.is_active=1')->fetchAll(PDO::FETCH_ASSOC) as $a){$id=(int)$a['hotel_id'];m3s_add_name($targetNames,$id,$a['alias'],'local_alias');m3s_add_name($targetNames,$id,$a['normalized_alias'],'local_alias_normalized');}

    $anexPairs=[];$anexLocal=[];
    $sql="SELECT m.anex_hotel_id,m.catalog_hotel_id FROM anex_hotel_search_mappings m LEFT JOIN anex_hotel_decisions d ON d.anex_hotel_id=m.anex_hotel_id WHERE m.enabled=1 AND m.scope='preview' AND m.approval_policy='".M3S_POLICY."' AND d.anex_hotel_id IS NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=m.anex_hotel_id AND x.catalog_hotel_id=m.catalog_hotel_id)";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r){$eid=(int)$r['anex_hotel_id'];$local=(int)$r['catalog_hotel_id'];$anexPairs[$eid]=$local;$anexLocal[$local]=true;}
    $sql="SELECT d.anex_hotel_id,d.catalog_hotel_id FROM anex_hotel_decisions d WHERE d.decision_status='accepted' AND d.catalog_hotel_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM anex_review_pair_exclusions x WHERE x.anex_hotel_id=d.anex_hotel_id AND x.catalog_hotel_id=d.catalog_hotel_id)";
    foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r){$eid=(int)$r['anex_hotel_id'];$local=(int)$r['catalog_hotel_id'];$anexPairs[$eid]=$local;$anexLocal[$local]=true;}
    $andrPairs=[];$andrLocal=[];$andrAccepted=[];
    foreach($db->query("SELECT external_hotel_id,local_hotel_id,evidence_json,catalog_sha256 FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='accepted' AND local_hotel_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $r){$eid=(string)$r['external_hotel_id'];$local=(int)$r['local_hotel_id'];$andrPairs[$eid]=$local;$andrLocal[$local]=true;$andrAccepted[]=$r;}
    $anexOnly=array_diff_key($anexLocal,$andrLocal);$andrOnly=array_diff_key($andrLocal,$anexLocal);

    $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r)$staging[(int)$r['anex_hotel_id']]=$r;
    $anObs=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(int)$r['anex_hotel_id'];$anObs[$id]??=['names'=>[],'country_id'=>(int)$r['country_id'],'search_count'=>0,'last_seen_utc'=>null];if(trim((string)$r['hotel_name'])!=='')$anObs[$id]['names'][(string)$r['hotel_name']]=true;$anObs[$id]['search_count']=max($anObs[$id]['search_count'],(int)$r['search_count']);if(($anObs[$id]['last_seen_utc']??'')<(string)$r['last_seen_utc'])$anObs[$id]['last_seen_utc']=$r['last_seen_utc'];}
    foreach($anexPairs as $eid=>$local){$s=$staging[$eid]??[];foreach([$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''] as $n)m3s_add_name($targetNames,$local,$n,'accepted_anex_name');foreach(array_keys($anObs[$eid]['names']??[]) as $n)m3s_add_name($targetNames,$local,$n,'accepted_anex_observed_name');}

    $andObs=[];foreach($db->query("SELECT * FROM andromeda_search_hotel_observations WHERE supplier_namespace='andromeda_catalog' ORDER BY observed_at_utc DESC,external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){$id=(string)$r['external_hotel_id'];$andObs[$id]??=['count'=>0,'latest'=>$r,'names'=>[],'places'=>[]];$andObs[$id]['count']++;if(trim((string)($r['hotel_name']??''))!=='')$andObs[$id]['names'][(string)$r['hotel_name']]=true;if(trim((string)($r['region_name']??''))!=='')$andObs[$id]['places'][(string)$r['region_name']]=true;}
    foreach($andrAccepted as $r){$local=(int)$r['local_hotel_id'];$ev=m3s_evidence($r['evidence_json']??'');$s=$ev['source']??[];if(!is_array($s))$s=[];foreach([$s['name']??'',$s['lName']??''] as $n)m3s_add_name($targetNames,$local,$n,'accepted_andromeda_name');foreach(array_keys($andObs[(string)$r['external_hotel_id']]['names']??[]) as $n)m3s_add_name($targetNames,$local,$n,'accepted_andromeda_observed_name');}

    $indexForAnex=[];$indexForAndr=[];$andOnlyIds=[];$anexOnlyIds=[];
    foreach(array_keys($andrOnly) as $local){$local=(int)$local;if(!isset($targets[$local]))continue;$c=(int)$targets[$local]['country_id'];$andOnlyIds[$c][]=$local;m3s_add_index($indexForAnex,$c,$local,$targetNames[$local]??[]);}
    foreach(array_keys($anexOnly) as $local){$local=(int)$local;if(!isset($targets[$local]))continue;$c=(int)$targets[$local]['country_id'];$anexOnlyIds[$c][]=$local;m3s_add_index($indexForAndr,$c,$local,$targetNames[$local]??[]);}

    $manualAnex=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);
    $existingAnex=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);
    $excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $r)$excluded[(int)$r['anex_hotel_id']][(int)$r['catalog_hotel_id']]=true;

    $shaCountry=[];$tmp=[];$sql="SELECT i.catalog_sha256,h.country_id,COUNT(*) n FROM andromeda_hotel_identities i JOIN catalog_hotels h ON h.id=i.local_hotel_id WHERE i.supplier_namespace='andromeda_catalog' AND i.local_hotel_id IS NOT NULL AND h.country_id IN (1,2,4,8,9,10,12,16) GROUP BY i.catalog_sha256,h.country_id";foreach($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r)$tmp[$r['catalog_sha256']][(int)$r['country_id']]=(int)$r['n'];foreach($tmp as $sha=>$by){arsort($by);$ids=array_keys($by);if(count($ids)===1||$by[$ids[0]]>($by[$ids[1]]??0)*20)$shaCountry[$sha]=(int)$ids[0];}

    $safe=[];$deferred=[];$conflicts=[];$stats=['anex'=>['observed_examined'=>0,'staging_examined'=>0,'protected'=>0],'andromeda'=>['pending_examined'=>0,'country_unknown'=>0],'reasons'=>[]];$seenAnex=[];
    $push=function(array $base,array $decision)use(&$safe,&$deferred,&$conflicts,&$stats){$row=$base+$decision;$bucket=$decision['bucket']??'needs_extra_evidence';$reason=$decision['reason']??'unknown';$stats['reasons'][$bucket][$reason]=($stats['reasons'][$bucket][$reason]??0)+1;if($bucket==='auto_accept')$safe[]=$row;elseif($bucket==='hard_conflict')$conflicts[]=$row;else $deferred[]=$row;};

    foreach($anObs as $eid=>$obs){$country=(int)$obs['country_id'];if(!isset(M3S_CORE8[$country]))continue;$stats['anex']['observed_examined']++;$seenAnex[$eid]=true;if(isset($manualAnex[$eid])||isset($existingAnex[$eid])){$stats['anex']['protected']++;continue;}$s=$staging[$eid]??[];$source=['provider'=>'anex','external_id'=>$eid,'country_id'=>$country,'observed'=>true,'frequency'=>(int)$obs['search_count'],'last_seen_utc'=>$obs['last_seen_utc'],'names'=>array_merge(array_keys($obs['names']),[$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??'']),'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];$star=m3s_star($s);$decision=m3s_classify($source,$indexForAnex,$andOnlyIds[$country]??[],$targets,$targetNames,$excluded[$eid]??[]);if(isset($decision['target'])){$decision['existing_bridge']='andromeda+tourvisor';$decision['source_star']=$star;$decision['target_star']=$decision['target']['category'];$decision['star_mismatch']=$star!==null&&$decision['target']['category']!==null&&$star!==$decision['target']['category'];}$push($source,$decision);}
    foreach($staging as $eid=>$s){if(isset($seenAnex[$eid])||isset($manualAnex[$eid])||isset($existingAnex[$eid]))continue;$country=m3s_country($s['api_country']??'');if(!$country||!isset(M3S_CORE8[$country]))continue;$stats['anex']['staging_examined']++;$source=['provider'=>'anex','external_id'=>(int)$eid,'country_id'=>$country,'observed'=>false,'frequency'=>0,'last_seen_utc'=>null,'names'=>[$s['api_name']??'',$s['xml_name']??'',$s['xml_alternate_name']??''],'places'=>[$s['api_region']??'',$s['api_town']??''],'latitude'=>$s['latitude']??null,'longitude'=>$s['longitude']??null];$star=m3s_star($s);$decision=m3s_classify($source,$indexForAnex,$andOnlyIds[$country]??[],$targets,$targetNames,$excluded[$eid]??[]);if(isset($decision['target'])){$decision['existing_bridge']='andromeda+tourvisor';$decision['source_star']=$star;$decision['target_star']=$decision['target']['category'];$decision['star_mismatch']=$star!==null&&$decision['target']['category']!==null&&$star!==$decision['target']['category'];}$push($source,$decision);}

    $pending=$db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach($pending as $r){$eid=(string)$r['external_hotel_id'];$obs=$andObs[$eid]??null;$country=(int)($obs['latest']['country_id']??0);if(!isset(M3S_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(M3S_CORE8[$country])){$stats['andromeda']['country_unknown']++;continue;}$stats['andromeda']['pending_examined']++;$ev=m3s_evidence($r['evidence_json']??'');$s=$ev['source']??[];if(!is_array($s))$s=[];$geo=$ev['geography']??[];if(!is_array($geo))$geo=[];$source=['provider'=>'andromeda','external_id'=>$eid,'country_id'=>$country,'observed'=>(bool)$obs,'frequency'=>(int)($obs['count']??0),'last_seen_utc'=>$obs['latest']['observed_at_utc']??null,'names'=>array_merge([$s['name']??'',$s['lName']??''],array_keys($obs['names']??[])),'places'=>array_merge([$s['town']??'',$geo['town']??'',$geo['parent']??''],array_keys($obs['places']??[]))]+$s;$source['source']=$s;$source['geography']=$geo;$star=m3s_star($s);if($star===null&&$obs)$star=m3s_star($obs['latest']);$decision=m3s_classify($source,$indexForAndr,$anexOnlyIds[$country]??[],$targets,$targetNames,[]);if(isset($decision['target'])){$decision['existing_bridge']='anex+tourvisor';$decision['source_star']=$star;$decision['target_star']=$decision['target']['category'];$decision['star_mismatch']=$star!==null&&$decision['target']['category']!==null&&$star!==$decision['target']['category'];}$push($source,$decision);}

    $collision=[];foreach($safe as $i=>$row){$key=$row['provider'].':'.($row['target']['local_hotel_id']??0);$collision[$key][]=$i;}
    $keep=[];$demoted=0;foreach($safe as $i=>$row){$key=$row['provider'].':'.($row['target']['local_hotel_id']??0);if(count($collision[$key]??[])>1){$row['bucket']='needs_extra_evidence';$row['reason']='same_provider_target_collision';$deferred[]=$row;$stats['reasons']['needs_extra_evidence']['same_provider_target_collision']=($stats['reasons']['needs_extra_evidence']['same_provider_target_collision']??0)+1;$demoted++;}else$keep[]=$row;}$safe=$keep;

    $sort=static function($a,$b){return((int)($b['observed']??0)<=> (int)($a['observed']??0)) ?: ((int)($b['frequency']??0)<=> (int)($a['frequency']??0)) ?: ((int)$a['country_id']<=> (int)$b['country_id']) ?: strcmp((string)$a['provider'],(string)$b['provider']) ?: strcmp((string)$a['external_id'],(string)$b['external_id']);};usort($safe,$sort);usort($deferred,$sort);usort($conflicts,$sort);
    $providerSafe=[];$ruleSafe=[];$liveSafe=0;$starMismatch=0;foreach($safe as $row){$providerSafe[$row['provider']]=($providerSafe[$row['provider']]??0)+1;$ruleSafe[$row['reason']]=($ruleSafe[$row['reason']]??0)+1;if($row['observed'])$liveSafe++;if($row['star_mismatch']??false)$starMismatch++;}
    $db->commit();
    $out=['status'=>'completed','operation_id'=>M3S_OPERATION,'mode'=>'current_db_missing_third_strict_review','database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'coverage'=>$coverage,'missing_third_targets'=>['anex_tv_only'=>count($anexOnly),'andromeda_tv_only'=>count($andrOnly)],'examined'=>$stats,'safe_count'=>count($safe),'safe_by_provider'=>$providerSafe,'safe_by_rule'=>$ruleSafe,'safe_observed'=>$liveSafe,'safe_star_mismatch_annotated'=>$starMismatch,'collision_demoted'=>$demoted,'deferred_count'=>count($deferred),'hard_conflict_count'=>count($conflicts),'safe_candidates'=>$safe,'hard_conflicts'=>$conflicts,'deferred_sample'=>array_slice($deferred,0,500),'guards'=>['current_db_recomputed'=>true,'generic_removed'=>['HOTEL','RESORT','SPA'],'significant_qualifiers_retained'=>['ANNEX','BEACH','GARDEN','NORTH','SOUTH','ADULT','FAMILY','AQUA','CLUB','PALACE','GRAND','ROYAL','PREMIUM','SELECT','BOUTIQUE'],'single_token_requires_direct_geo'=>true,'fuzzy_requires_direct_geo'=>true,'fuzzy_min_score'=>0.90,'fuzzy_min_shared_tokens'=>2,'fuzzy_min_margin'=>0.25,'coordinate_conflict_block_m'=>5000,'same_provider_target_collision_demoted'=>true,'star_mismatch_is_annotation_not_identity'=>true,'manual_decisions_protected'=>true,'pair_exclusions_protected'=>true,'existing_mappings_protected'=>true]];
    echo m3s_json($out),PHP_EOL;
} catch(Throwable $e) {
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    echo m3s_json(['status'=>'failed','operation_id'=>M3S_OPERATION,'reason'=>'strict_review_failed','message'=>substr(preg_replace('/[^\p{L}\p{N}\s_.:()\/-]+/u',' ',(string)$e->getMessage())??'',0,180),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false]),PHP_EOL;exit(2);
}
