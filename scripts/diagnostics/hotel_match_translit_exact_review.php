<?php
declare(strict_types=1);
if (!defined('FC_LIBRARY_ONLY')) define('FC_LIBRARY_ONLY', true);
require_once __DIR__ . '/hotel_match_live_priority_review.php';

const HTXR_OPERATION = 'hotel-match-translit-exact-review-1971-20260911-v1';

function htxr_latin(string $v): string {
    $v=mb_strtolower($v,'UTF-8');
    return strtr($v,[
        'щ'=>'shch','ш'=>'sh','ч'=>'ch','ц'=>'ts','ю'=>'yu','я'=>'ya','ё'=>'e','ж'=>'zh','х'=>'kh',
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l',
        'м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','ы'=>'y','э'=>'e',
        'ь'=>'','ъ'=>'','ї'=>'yi','і'=>'i','є'=>'ye','ґ'=>'g',
    ]);
}
function htxr_tokens(string $v): array {
    $out=[];foreach(fc_tokens($v,true) as $t){$t=fc_norm(htxr_latin((string)$t));if($t!=='')$out[]=$t;}return $out;
}
function htxr_key(string $v): string { return implode(' ',htxr_tokens($v)); }
function htxr_script(string $v): string {
    $c=(bool)preg_match('/\p{Cyrillic}/u',$v);$l=(bool)preg_match('/[A-Za-z]/u',$v);return $c&&!$l?'cyr':($l&&!$c?'lat':($c&&$l?'mixed':'other'));
}
function htxr_cross_script(string $a,string $b): bool {
    $x=htxr_script($a);$y=htxr_script($b);return ($x==='cyr'&&$y==='lat')||($x==='lat'&&$y==='cyr')||($x==='mixed'&&in_array($y,['cyr','lat'],true))||($y==='mixed'&&in_array($x,['cyr','lat'],true));
}
function htxr_place(array $source,array $target): bool {
    $a=[];$b=[];foreach($source as $v){$n=fc_norm(htxr_latin((string)$v));if($n!=='')$a[$n]=1;}foreach($target as $v){$n=fc_norm(htxr_latin((string)$v));if($n!=='')$b[$n]=1;}return (bool)array_intersect_key($a,$b);
}
function htxr_catalog_index(array $hotels,array $names): array {
    $idx=[];foreach($hotels as $id=>$h){$country=(int)$h['country_id'];foreach($names[$id]??[(string)$h['name']] as $name){$key=htxr_key((string)$name);if($key===''||count(explode(' ',$key))<2)continue;$idx[$country][$key][(int)$id][(string)$name]=1;}}return $idx;
}
function htxr_candidate(array $sourceNames,array $sourcePlaces,array $coordSource,int $country,array $hotels,array $names,array $index,array $excluded=[]): ?array {
    $matches=[];
    foreach($sourceNames as $sourceName){$sourceName=(string)$sourceName;$key=htxr_key($sourceName);if($key===''||count(explode(' ',$key))<2)continue;
        foreach($index[$country][$key]??[] as $id=>$catalogNames){$id=(int)$id;if(isset($excluded[$id])||!isset($hotels[$id]))continue;$h=$hotels[$id];if(!htxr_place($sourcePlaces,[(string)$h['region_name'],(string)$h['subregion_name']]))continue;
            $matchedCatalog=null;foreach(array_keys($catalogNames) as $catalogName){if(htxr_cross_script($sourceName,(string)$catalogName)){$matchedCatalog=(string)$catalogName;break;}}if($matchedCatalog===null)continue;
            $guard=mbr_target_guard($coordSource,$h);if($guard['coordinate_conflict'])continue;
            $matches[$id]=['target_local_hotel_id'=>$id,'target_name'=>$h['name'],'target_region'=>$h['region_name'],'target_subregion'=>$h['subregion_name'],'source_name'=>$sourceName,'matched_catalog_name'=>$matchedCatalog,'translit_key'=>$key,'distance_m'=>$guard['distance_m']??null];
        }
    }
    if(count($matches)!==1)return null;return reset($matches)?:null;
}

function htxr_review(PDO $db,string $operation=HTXR_OPERATION): array {
    if($operation!==HTXR_OPERATION)throw new RuntimeException('operation_scope');
    $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
    try{
        [$hotels,$names,$strict,$broad,$places,$scope]=mbr_catalog($db);$index=htxr_catalog_index($hotels,$names);$shaCountry=fc_sha_countries($db);[$latest,$counts,$lastSeen]=mlp_observations($db);
        $rows=[];$stats=['andromeda_examined'=>0,'anex_examined'=>0,'prepared'=>0,'prepared_andromeda'=>0,'prepared_anex'=>0,'live_prepared'=>0,'ambiguous_or_none'=>0];
        foreach($db->query("SELECT * FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id")->fetchAll(PDO::FETCH_ASSOC) as $r){
            $external=(string)$r['external_hotel_id'];$obs=$latest[$external]??null;$country=(int)($obs['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)($shaCountry[$r['catalog_sha256']]??0);if(!isset(MBR_CORE8[$country]))continue;$stats['andromeda_examined']++;
            [$prior,$source,$sourceNames,$sourcePlaces,$sourceCategory,$coordSource]=mlp_source_context($r,$obs);$c=htxr_candidate($sourceNames,$sourcePlaces,$coordSource,$country,$hotels,$names,$index);if($c===null){$stats['ambiguous_or_none']++;continue;}
            $rows[]=array_merge(['provider'=>'andromeda','external_id'=>$external,'country_id'=>$country,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'observation_count'=>(int)($counts[$external]??0),'last_seen_utc'=>$lastSeen[$external]??null,'rule'=>'cross_script_exact_translit_direct_geo'], $c);$stats['prepared_andromeda']++;
        }
        $manual=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_decisions')->fetchAll(PDO::FETCH_COLUMN)),true);$existing=array_fill_keys(array_map('intval',$db->query('SELECT anex_hotel_id FROM anex_hotel_search_mappings')->fetchAll(PDO::FETCH_COLUMN)),true);$excluded=[];foreach($db->query('SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions')->fetchAll(PDO::FETCH_ASSOC) as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;
        $staging=[];foreach($db->query('SELECT * FROM anex_hotels ORDER BY anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $s)$staging[(int)$s['anex_hotel_id']]=$s;$observed=[];foreach($db->query('SELECT * FROM anex_search_hotel_observations ORDER BY search_count DESC,last_seen_utc DESC,anex_hotel_id')->fetchAll(PDO::FETCH_ASSOC) as $o){$id=(int)$o['anex_hotel_id'];if(!isset($observed[$id]))$observed[$id]=$o;}
        foreach($staging as $id=>$s){$id=(int)$id;if(isset($manual[$id])||isset($existing[$id]))continue;$o=$observed[$id]??null;$country=(int)($o['country_id']??0);if(!isset(MBR_CORE8[$country]))$country=(int)(fc_country($s['api_country']??'')??0);if(!isset(MBR_CORE8[$country]))continue;$stats['anex_examined']++;
            $sourceNames=array_values(array_unique(array_filter([(string)($o['hotel_name']??''),(string)($s['api_name']??''),(string)($s['xml_name']??''),(string)($s['xml_alternate_name']??'')],static fn($v)=>trim($v)!=='')));$sourcePlaces=array_values(array_unique(array_filter([(string)($s['api_region']??''),(string)($s['api_town']??'')],static fn($v)=>trim($v)!=='')));
            $c=htxr_candidate($sourceNames,$sourcePlaces,$s,$country,$hotels,$names,$index,$excluded[$id]??[]);if($c===null){$stats['ambiguous_or_none']++;continue;}
            $rows[]=array_merge(['provider'=>'anex','external_id'=>$id,'country_id'=>$country,'source_names'=>$sourceNames,'source_places'=>$sourcePlaces,'observation_count'=>(int)($o['search_count']??0),'last_seen_utc'=>$o['last_seen_utc']??null,'rule'=>'cross_script_exact_translit_direct_geo'], $c);$stats['prepared_anex']++;
        }
        $stats['prepared']=count($rows);$stats['live_prepared']=count(array_filter($rows,static fn($r)=>(int)$r['observation_count']>0));usort($rows,static fn($a,$b)=>(int)$b['observation_count']<=>(int)$a['observation_count'] ?: strcmp((string)$a['provider'],(string)$b['provider']) ?: strcmp((string)$a['external_id'],(string)$b['external_id']));$coverage=fc_coverage($db);$db->commit();
        return ['status'=>'completed','operation_id'=>$operation,'database_writes'=>0,'supplier_calls'=>0,'historical_operations_replayed'=>false,'catalog_scope'=>$scope,'coverage'=>$coverage,'stats'=>$stats,'prepared'=>$rows];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
