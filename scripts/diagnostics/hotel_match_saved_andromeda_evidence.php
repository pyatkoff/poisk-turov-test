<?php
declare(strict_types=1);
/** MATCH-only retained hotel evidence intake. No network client or mapping writer. */
const MSA_OP = 'hotel-match-saved-andromeda-evidence-1971-20260915-v1';
function msa_json(array $x): string { return json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
function msa_write(string $path, array $x): string {
    $raw=msa_json($x); $f=@fopen($path,'x+b'); if(!$f)throw new RuntimeException('exclusive_output');
    try { if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('output_write');
        if(function_exists('fsync')&&!fsync($f))throw new RuntimeException('output_sync');
        rewind($f); if(stream_get_contents($f)!==$raw)throw new RuntimeException('output_readback');
    } finally { fclose($f); } return hash('sha256',$raw);
}
function msa_read(string $path, int $limit=3000000): array {
    if(is_link($path)||realpath($path)!==$path||!is_file($path))throw new RuntimeException('unsafe_input_path');
    $f=@fopen($path,'rb'); if(!$f)throw new RuntimeException('input_unreadable');
    try { $a=fstat($f); if($a['nlink']!==1||$a['size']>$limit)throw new RuntimeException('input_bounds');
        $raw=stream_get_contents($f,$limit+1); $b=fstat($f);
        if(!is_string($raw)||strlen($raw)!==$a['size']||$a['size']!==$b['size']||$a['mtime']!==$b['mtime']||$a['ino']!==$b['ino'])throw new RuntimeException('input_changed');
    } finally { fclose($f); }
    $x=json_decode($raw,true,64,JSON_THROW_ON_ERROR); if(!is_array($x))throw new RuntimeException('input_shape');
    return [$x,hash('sha256',$raw),strlen($raw)];
}
function msa_id($x): ?string { return (is_int($x)||is_string($x))&&preg_match('/^[1-9][0-9]{0,19}$/D',(string)$x)?(string)$x:null; }
function msa_text($x): ?string { return is_string($x)&&trim($x)!==''&&strlen($x)<=1024&&!preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$x)?trim($x):null; }
function msa_fields(array $r): array {
    $out=[];
    foreach(['name','lName','hotel','hotel_name','hotelName','original_name','town','townName','region','regionName','stateName','countryName','parentName'] as $k)if(($v=msa_text($r[$k]??null))!==null)$out[$k]=$v;
    foreach(['townKey','town_key','town','townId','town_id','townToKey','townToId','regionKey','regionId','parentKey','parentId','parent','stateKey','state_key','stateId','countryKey'] as $k)if(($v=msa_id($r[$k]??null))!==null)$out[$k]=$v;
    foreach(['latitude','lat','longitude','lon','lng'] as $k)if(isset($r[$k])&&is_numeric($r[$k])&&is_finite((float)$r[$k])&&abs((float)$r[$k])<= (in_array($k,['latitude','lat'],true)?90:180))$out[$k]=(float)$r[$k];
    // Star/category values are evidence only; supplier star IDs are not ratings.
    foreach(['star','starKey','starId'] as $k)if(is_int($r[$k]??null)||is_string($r[$k]??null))$out[$k]=strlen((string)$r[$k])<=64?(string)$r[$k]:null;
    return $out;
}
function msa_url($value): ?array {
    if(!is_string($value)||strlen($value)>2048||preg_match('/[\x00-\x20\x7f]/',$value))return null;
    $u=parse_url($value); if(!$u||($u['scheme']??'')!=='https'||isset($u['user'])||isset($u['pass'])||isset($u['port']))return null;
    $host=strtolower($u['host']??'');
    // Metadata only, never fetch. Keep known operator hotel/media hosts, not arbitrary links.
    if(!preg_match('/^(?:[a-z0-9-]+\.)*(?:anextour\.(?:ru|com)|anex\.(?:ru|com)|intourist\.ru|fstravel\.com|bgoperator\.ru)$/D',$host))return null;
    $q=$u['query']??'';
    if(preg_match('/(?:sid|token|password|secret|auth|session|email|phone|yclid|gclid|api.?key)/i',rawurldecode($q)))return null;
    $ids=[]; $kept=[];
    foreach($q===''?[]:explode('&',$q) as $part){
        if($part==='')continue; $kv=explode('=',$part,2);$k=rawurldecode($kv[0]);$v=rawurldecode($kv[1]??'');
        if(!in_array(strtolower($k),['hotelcode','hotellist'],true))continue;
        if(msa_id($v)===null)return null;
        $key=strtolower($k); if(isset($kept[$key])&&$kept[$key]!==$v)return null;
        $kept[$key]=$v;$ids[$v]=true;
    }
    if(count($ids)>1)return null;
    $path=$u['path']??'/';if(preg_match('/(?:token|session|auth|password|secret)/i',rawurldecode($path)))return null;
    ksort($kept);$safe='https://'.$host.$path.($kept?'?'.http_build_query($kept,'','&',PHP_QUERY_RFC3986):'');
    $anex=(bool)preg_match('/(?:^|\.)(?:anextour|anex)\.(?:ru|com)$/D',$host);
    return ['url'=>$safe,'explicit_anex_hotel_id'=>$anex&&count($ids)===1?(string)array_key_first($ids):null,
        'id_evidence'=>$anex&&count($ids)===1?'explicit_hotelCode_or_HOTELLIST':'none',
        'source_url_sha256'=>hash('sha256',$value)];
}
function msa_urls(array $r): array {
    $out=[];foreach(['hotelUrl','hotelImage','operatorUrl','operatorLink','hotel_url','image_url','url'] as $k)if(($v=msa_url($r[$k]??null))!==null)$out[$k]=$v;return $out;
}
function msa_search_filename(string $name): bool { return (bool)preg_match('/^[a-f0-9]{64}-(?:1|[1-9][0-9]{8,10}-[1-9][0-9]{0,3})\.json$/D',$name); }
function msa_core(string $name): bool {
    return in_array(strtr(strtolower(trim($name)),array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY))),['египет','egypt','турция','turkey','таиланд','thailand','оаэ','uae','united arab emirates','вьетнам','vietnam','шри-ланка','шри ланка','sri lanka','мальдивы','maldives','куба','cuba'],true);
}
function msa_pending(array $rows, array $excluded): array {
    $out=[];$skip=array_fill_keys(array_map('strval',$excluded),true);
    foreach($rows as $r){$id=msa_id($r['external_hotel_id']??null);
        if(!$id||($r['supplier_namespace']??null)!=='andromeda_catalog'||($r['decision_status']??null)!=='pending'||($r['local_hotel_id']??null)!==null||isset($skip[$id]))continue;
        $e=json_decode((string)($r['evidence_json']??'{}'),true,64,JSON_THROW_ON_ERROR);$s=is_array($e['source']??null)?$e['source']:$e;
        $out[$id]=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$id,'evidence_sha256'=>$r['evidence_sha256'],
            'current_source'=>msa_fields(is_array($s)?$s:[]),'current_source_urls'=>msa_urls(is_array($s)?$s:[])];
    }ksort($out,SORT_STRING);return $out;
}
function msa_catalog(array $saved,int $country,array $pending,string $digest): array {
    if((int)($saved['local_country_id']??1)!==$country)throw new RuntimeException('catalog_country_conflict');
    $p=$saved['all']['payload']??null;if(!is_array($p)||!is_array($p['HOTELS']??null)||!is_array($p['TOWNTO']??null))throw new RuntimeException('catalog_shape');
    $towns=[];$townConflicts=[];
    foreach($p['TOWNTO'] as $r){if(!is_array($r)||($id=msa_id($r['id']??null))===null)continue;$v=msa_fields($r);
        if(isset($towns[$id])&&$towns[$id]!==$v)$townConflicts[$id]=true;else$towns[$id]=$v;
    }
    $hotels=[];$hotelConflicts=[];
    foreach($p['HOTELS'] as $r){if(!is_array($r)||($id=msa_id($r['id']??null))===null||!isset($pending[$id]))continue;
        $v=['fields'=>msa_fields($r),'urls'=>msa_urls($r)];
        if(isset($hotels[$id])&&$hotels[$id]!==$v)$hotelConflicts[$id]=true;else$hotels[$id]=$v;
    }
    $rows=[];foreach($hotels as $id=>$v){$links=[];
        foreach(['townKey','town_key','town','townId','town_id','townToKey','townToId'] as $k)if(isset($v['fields'][$k])&&($tid=msa_id($v['fields'][$k]))!==null){
            $links[$k]=['namespace'=>'andromeda_town','country_id'=>$country,'external_id'=>$tid,'dictionary_fields'=>$towns[$tid]??null,'conflict'=>isset($townConflicts[$tid])];
        }
        $rows[]=['external_hotel_id'=>(string)$id,'country_id'=>$country,'source_kind'=>'saved_HOTELS_TOWNTO','retained_file_sha256'=>$digest,
            'original_http_response_sha256'=>null,'hotel_fields'=>$v['fields'],'urls'=>$v['urls'],'typed_town_links'=>$links,
            'holds'=>isset($hotelConflicts[$id])?['saved_duplicate_hotel_conflict']:[],
            'safe_to_write_now'=>false];
    }
    return ['hotel_rows'=>$rows,'town_rows'=>count($towns),'duplicate_town_conflicts'=>count($townConflicts),'source_hotel_rows'=>count($p['HOTELS'])];
}
function msa_search(array $state,array $pending,string $digest): array {
    if(!in_array($state['status']??null,['complete','partial'],true))return [];
    $store=$state['store']??null;if(!is_array($store)||($store['version']??null)!==1||!is_array($store['snapshot']['offers']??null))return [];
    $out=[];
    foreach($store['snapshot']['offers'] as $r){if(!is_array($r)||($r['supplier_namespace']??null)!=='andromeda_catalog'||($id=msa_id($r['external_hotel_id']??null))===null||!isset($pending[$id]))continue;
        $content=is_array($r['hotel_content']??null)?$r['hotel_content']:[];$operator=is_array($r['operator']??null)?$r['operator']:[];
        $v=['external_hotel_id'=>$id,'source_kind'=>'retained_normalized_PRICE','retained_file_sha256'=>$digest,'original_http_response_sha256'=>null,
            'supplier_state_key'=>msa_id($state['criteria']['STATEINC']??null),'hotel_fields'=>msa_fields($r),
            'content_fields'=>msa_fields($content),'urls'=>msa_urls($r)+msa_urls($content),
            'operator'=>['id'=>msa_id($operator['id']??null),'name'=>msa_text($operator['name']??null)],'safe_to_write_now'=>false];
        $key=hash('sha256',msa_json($v));$out[$key]=$v;
    }return array_values($out);
}
function msa_query(PDO $db,string $sql): array { $q=$db->query($sql);return $q->fetchAll(PDO::FETCH_ASSOC); }
function msa_main(): void {
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
    $sha=(string)getenv('MATCH_SOURCE_SHA');$op=(string)getenv('MATCH_OPERATION_ID');
    if($op!==MSA_OP||!preg_match('/^[a-f0-9]{40}$/D',$sha)||!defined('MSA_EXCLUDED'))throw new RuntimeException('operation_guard');
    $dir=(string)getenv('HOME').'/.anytoour-match/operations/'.MSA_OP;
    [$reservation]=msa_read($dir.'/reservation.json',16384);
    if(($reservation['operation_id']??null)!==MSA_OP||($reservation['source_sha']??null)!==$sha||($reservation['state']??null)!=='reserved_before_db_access')throw new RuntimeException('reservation_guard');
    $phase='configuration';$db=null;$out=['operation_id'=>MSA_OP,'source_sha'=>$sha,'claim_comment_id'=>5681482250,'state'=>'failed_no_replay',
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'no_replay'=>true,'prepared_only'=>true,'safe_to_write_now'=>false];
    ob_start();
    try {
        $root=realpath(getcwd());if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('project_guard');
        $cp=$root.'/_preview/search3-anex-candidate/.andromeda-private.php';
        if(realpath($cp)!==$cp||is_link($cp)||!is_file($cp))throw new RuntimeException('configured_runtime_missing');
        $config=require $cp;$catalog=$config['catalog_path']??null;$enabled=$config['enabled']??false;unset($config);
        if($enabled!==true||!is_string($catalog)||realpath($catalog)!==$catalog||is_link($catalog))throw new RuntimeException('configured_catalog_missing');
        $base=dirname($catalog);if(realpath($base)!==$base||is_link($base))throw new RuntimeException('configured_directory_invalid');
        $phase='current_read';require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
        $db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
        $countries=[];foreach(msa_query($db,'SELECT id,name FROM catalog_countries WHERE is_active=1 ORDER BY id') as $c)if(msa_core($c['name']))$countries[(int)$c['id']]=$c['name'];
        if(count($countries)<6)throw new RuntimeException('core_countries_missing');
        $rows=msa_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND decision_status='pending' AND local_hotel_id IS NULL ORDER BY external_hotel_id");
        $pending=msa_pending($rows,MSA_EXCLUDED);$out['current_pending_total']=count($rows);$out['excluded_pending_count']=count($rows)-count($pending);$out['pending_after_claim_exclusions']=count($pending);unset($rows);
        $out['current_pending_digest']=hash('sha256',msa_json($pending));$phase='saved_catalogs';$evidence=[];$catalogs=[];$bytes=0;$covered=[];
        foreach($countries as $cid=>$name){$path=$cid===1?$catalog:$base.'/countries/'.$cid.'.json';
            if(!file_exists($path)){ $catalogs[]=['country_id'=>$cid,'state'=>'not_installed'];continue; }
            [$saved,$digest,$size]=msa_read($path,32000000);$bytes+=$size;if($bytes>256000000)throw new RuntimeException('catalog_total_bounds');
            $got=msa_catalog($saved,$cid,$pending,$digest);unset($saved);
            foreach($got['hotel_rows'] as $r){$evidence[]=$r;$covered[$r['external_hotel_id']]=true;}
            $catalogs[]=['country_id'=>$cid,'state'=>'read_verified','retained_file_sha256'=>$digest,'bytes'=>$size,'source_hotel_rows'=>$got['source_hotel_rows'],'matching_pending'=>count($got['hotel_rows']),'town_rows'=>$got['town_rows'],'duplicate_town_conflicts'=>$got['duplicate_town_conflicts']];
        }
        $out['catalogs']=$catalogs;$out['catalog_covered_pending']=count($covered);$phase='saved_searches';$searches=$base.'/searches';$files=[];$read=0;$skipped=0;$searchBytes=0;$searchCovered=[];$searchEvidence=[];$cacheComplete=true;
        if(is_dir($searches)){
            if(realpath($searches)!==$searches||is_link($searches))throw new RuntimeException('search_directory_invalid');
            $entries=scandir($searches);if(count($entries)>20000)throw new RuntimeException('directory_entry_bounds');
            foreach($entries as $entry)if(msa_search_filename($entry))$files[]=$entry;sort($files,SORT_STRING);
            foreach($files as $entry){
                if($read>=5000||$searchBytes>256000000){$cacheComplete=false;break;}
                [$state,$digest,$size]=msa_read($searches.'/'.$entry);$read++;$searchBytes+=$size;
                $got=msa_search($state,array_intersect_key($pending,$covered),$digest);if(!$got)$skipped++;
                foreach($got as $r){$id=$r['external_hotel_id'];$identity=$r;unset($identity['retained_file_sha256']);$key=hash('sha256',msa_json($identity));
                    if(!isset($searchEvidence[$key]))$searchEvidence[$key]=$r+['other_retained_file_sha256'=>[],'observation_count'=>0];
                    $searchEvidence[$key]['observation_count']++;
                    if($digest!==$searchEvidence[$key]['retained_file_sha256']&&count($searchEvidence[$key]['other_retained_file_sha256'])<8)$searchEvidence[$key]['other_retained_file_sha256'][$digest]=$digest;
                    $searchCovered[$id]=true;
                }
            }
        }
        foreach($searchEvidence as &$r)$r['other_retained_file_sha256']=array_values($r['other_retained_file_sha256']);unset($r);
        $out['saved_search_files']=['eligible'=>count($files),'read'=>$read,'without_pending_metadata'=>$skipped,'bytes'=>$searchBytes,'complete_within_bounds'=>$cacheComplete];
        $out['search_covered_pending']=count($searchCovered);$out['search_unique_metadata']=count($searchEvidence);
        $evidence=array_merge($evidence,array_values($searchEvidence));$allCovered=$covered+$searchCovered;
        $out['evidence_rows']=$evidence;$out['covered_pending_count']=count($allCovered);$out['current_pending']=array_values(array_intersect_key($pending,$allCovered));
        $out['explicit_anex_url_evidence_rows']=count(array_filter($evidence,static function($r){foreach($r['urls'] as $v)if($v['explicit_anex_hotel_id']!==null)return true;return false;}));
        $phase='budget_read';$bp=$base.'/monthly-requests.json';$out['budget']=['state'=>'absent','scope'=>'not_account_wide','supplier_calls_this_operation'=>0];
        if(file_exists($bp)){[$b,$digest]=msa_read($bp,16384);$out['budget']=['state'=>'read_only','month'=>is_string($b['month']??null)&&preg_match('/^[0-9]{4}-[0-9]{2}$/D',$b['month'])?$b['month']:null,
            'reserved_requests'=>is_int($b['reserved_requests']??null)?$b['reserved_requests']:null,'monthly_limit'=>is_int($b['monthly_limit']??null)?$b['monthly_limit']:null,'scope'=>'this_integration_not_account_wide','file_sha256'=>$digest,'supplier_calls_this_operation'=>0];}
        $db->exec('COMMIT');$out['state']='completed_read_only';$out['read_at_utc']=gmdate('c');$out['transaction']='REPEATABLE READ / READ ONLY';
    }catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$out['error_phase']=$phase;$out['error_code']=preg_match('/^[a-z_]{3,80}$/D',$e->getMessage())?$e->getMessage():'sanitized_failure';}
    while(ob_get_level())ob_end_clean();
    $digest=msa_write($dir.'/result.json',$out);
    msa_write($dir.'/receipt.json',['operation_id'=>MSA_OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$digest,'readback_verified'=>true,'no_replay'=>true,'database_writes'=>0,'supplier_calls'=>0]);
    echo msa_json(['state'=>$out['state'],'covered_pending_count'=>$out['covered_pending_count']??null,'result_sha256'=>$digest]);
    if($out['state']!=='completed_read_only')exit(2);
}
if(getenv('MATCH_SAVED_EVIDENCE_TEST_LIBRARY')==='1')return;
msa_main();
