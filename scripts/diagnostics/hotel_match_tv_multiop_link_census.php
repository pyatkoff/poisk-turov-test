<?php
declare(strict_types=1);
/**
 * MATCH #1971: server-CURRENT read-only census of Tourvisor multi-operator link shapes.
 * Existing observations only: no Tourvisor/provider calls and no DB writes.
 */

const HMMO_OPERATION = 'hotel-match-tv-multiop-link-census-1971-20260916-v1';
const HMMO_CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];
const HMMO_SECRET_RE = '/(?:token|jwt|auth|pass|password|secret|session|sid|cookie|signature|api[_-]?key)/i';
const HMMO_HOTEL_KEYS = [
    'hotel'=>true,'hotelid'=>true,'hotel_id'=>true,'hotelcode'=>true,'hotel_code'=>true,
    'hotellist'=>true,'hotelkey'=>true,'hotel_key'=>true,'hotelcodeid'=>true,
];

function hmmo_json(array $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function hmmo_write_new(string $path,array $v): string {
    $raw=hmmo_json($v)."\n";$fh=@fopen($path,'x');if(!$fh)throw new RuntimeException('durable_create_failed');
    try{if(fwrite($fh,$raw)!==strlen($raw))throw new RuntimeException('durable_write_failed');fflush($fh);}finally{fclose($fh);}
    if(file_get_contents($path)!==$raw)throw new RuntimeException('durable_readback_failed');return hash('sha256',$raw);
}
function hmmo_id($v): ?int {
    if(is_bool($v)||!is_scalar($v))return null;$s=trim((string)$v);
    if(!preg_match('/^[1-9][0-9]{0,14}$/D',$s))return null;return (int)$s;
}
function hmmo_text($v,int $max=240): string {
    if(!is_scalar($v))return '';$s=trim((string)(preg_replace('/\s+/u',' ',(string)$v)??''));
    return function_exists('mb_substr')?mb_substr($s,0,$max,'UTF-8'):substr($s,0,$max);
}
function hmmo_db_path(string $root): string {
    foreach([$root.'/data/db-v1.php',$root.'/v2/data/db-v1.php'] as $p)if(is_file($p))return $p;
    throw new RuntimeException('db_bootstrap_missing');
}
function hmmo_select(PDO $db,string $sql,array $params=[]): array {$s=$db->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);}
function hmmo_safe_parts(array $row): array {
    $link=trim((string)($row['operator_link']??''));$host=strtolower(trim((string)($row['operator_link_host']??'')));
    $path=trim((string)($row['operator_link_path']??''));$query=trim((string)($row['operator_link_query']??''));
    if($link!==''){
        $u=parse_url($link);if(!is_array($u)||strtolower((string)($u['scheme']??''))!=='https'||isset($u['user'])||isset($u['pass']))return ['status'=>'invalid_link'];
        $lh=strtolower((string)($u['host']??''));if($lh==='')return ['status'=>'invalid_link'];
        $lp=(string)($u['path']??'');$lq=(string)($u['query']??'');
        if($host!==''&&$host!==$lh)return ['status'=>'metadata_conflict'];if($path!==''&&$lp!==''&&$path!==$lp)return ['status'=>'metadata_conflict'];if($query!==''&&$lq!==''&&$query!==$lq)return ['status'=>'metadata_conflict'];
        $host=$lh;if($path==='')$path=$lp;if($query==='')$query=$lq;
    }
    if($host===''||$path==='')return ['status'=>'missing_link_shape'];
    $pairs=[];$numeric=[];$keys=[];
    foreach(explode('&',$query) as $part){if($part==='')continue;[$rk,$rv]=array_pad(explode('=',$part,2),2,'');$k=strtolower(trim(urldecode($rk)));if($k==='')continue;if(preg_match(HMMO_SECRET_RE,$k))return ['status'=>'secret_bearing_query'];$keys[$k]=true;$v=trim(urldecode($rv));if(($id=hmmo_id($v))!==null)$numeric[$k][$id]=true;}
    $pathNums=[];foreach(preg_split('~/+~',$path)?:[] as $segment)if(($id=hmmo_id($segment))!==null)$pathNums[$id]=true;
    ksort($keys);ksort($numeric);ksort($pathNums);
    $outNumeric=[];foreach($numeric as $k=>$ids){$x=array_keys($ids);sort($x,SORT_NUMERIC);$outNumeric[$k]=$x;}
    return ['status'=>'safe','host'=>$host,'path'=>$path,'query_keys'=>array_keys($keys),'numeric'=>$outNumeric,'path_numeric'=>array_keys($pathNums)];
}
function hmmo_rule_candidates(array $observations): array {
    // input rows: operator/local/host/path/key/value/weight. Certify only clearly hotel-semantic query keys.
    $groups=[];
    foreach($observations as $r){$g=$r['operator_id'].'|'.$r['host'].'|'.$r['path'].'|'.$r['query_key'];$local=(int)$r['local_hotel_id'];$value=(int)$r['value'];$groups[$g]['meta']=['operator_id'=>(int)$r['operator_id'],'operator_name'=>$r['operator_name'],'host'=>$r['host'],'path'=>$r['path'],'query_key'=>$r['query_key']];$groups[$g]['locals'][$local]['values'][$value]=true;$groups[$g]['locals'][$local]['weight']=($groups[$g]['locals'][$local]['weight']??0)+(int)$r['weight'];}
    $rules=[];
    foreach($groups as $g){$m=$g['meta'];$key=strtolower((string)$m['query_key']);if(!isset(HMMO_HOTEL_KEYS[$key]))continue;$stable=[];$reverse=[];$weight=0;
        foreach($g['locals']??[] as $local=>$v){$vals=array_keys($v['values']??[]);if(count($vals)!==1)continue;$native=(int)$vals[0];$stable[(int)$local]=['native_id'=>$native,'weight'=>(int)($v['weight']??0)];$reverse[$native][(int)$local]=true;$weight+=(int)($v['weight']??0);}
        $collisions=0;foreach($reverse as $locals)if(count($locals)>1)$collisions++;
        $totalLocals=count($g['locals']??[]);$stableLocals=count($stable);$certified=$stableLocals>=3&&$stableLocals===$totalLocals&&$collisions===0;
        $rules[]=$m+['local_count'=>$totalLocals,'stable_local_count'=>$stableLocals,'collision_values'=>$collisions,'observation_weight'=>$weight,'certified_shape'=>$certified,'stable'=>$stable];
    }
    usort($rules,static fn($a,$b)=>($b['certified_shape']<=>$a['certified_shape'])?:($b['stable_local_count']<=>$a['stable_local_count'])?:($b['observation_weight']<=>$a['observation_weight'])?:strcmp($a['operator_id'].'|'.$a['host'].'|'.$a['path'].'|'.$a['query_key'],$b['operator_id'].'|'.$b['host'].'|'.$b['path'].'|'.$b['query_key']));
    return $rules;
}

if(in_array('--self-test',$argv??[],true)){
    $r=hmmo_safe_parts(['operator_link'=>'https://agent.anextour.ru/search/tour?HOTELLIST=4158&ADULT=2']);if(($r['numeric']['hotellist'][0]??0)!==4158)throw new RuntimeException('safe_link_test');
    $r=hmmo_safe_parts(['operator_link'=>'https://example.test/tour?hotelId=123&token=secret']);if(($r['status']??'')!=='secret_bearing_query')throw new RuntimeException('secret_test');
    $fixture=[];foreach([[1,101,4],[2,102,5],[3,103,6]] as [$local,$native,$w])$fixture[]=['operator_id'=>115,'operator_name'=>'Biblio','host'=>'x.test','path'=>'/hotel','query_key'=>'hotelId','local_hotel_id'=>$local,'value'=>$native,'weight'=>$w];
    $rules=hmmo_rule_candidates($fixture);if(count($rules)!==1||!$rules[0]['certified_shape'])throw new RuntimeException('rule_test');
    $fixture[]=['operator_id'=>115,'operator_name'=>'Biblio','host'=>'x.test','path'=>'/hotel','query_key'=>'hotelId','local_hotel_id'=>4,'value'=>101,'weight'=>1];$rules=hmmo_rule_candidates($fixture);if($rules[0]['certified_shape'])throw new RuntimeException('collision_test');
    echo "hotel_match_tv_multiop_link_census self-test PASS\n";exit(0);
}

$op=(string)getenv('OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');
if($op!==HMMO_OPERATION||!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('operation_guard');
$root=(string)realpath(getcwd());if($root===''||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');
$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations';if(!is_dir($base))throw new RuntimeException('operations_root_missing');
$dir=$base.'/'.$op;if(!mkdir($dir,0700))throw new RuntimeException('operation_exists');
hmmo_write_new($dir.'/reservation.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'reserved_before_db_access','read_only'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);

$db=null;
try{
    require_once hmmo_db_path($root);$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $tables=[];foreach(['tour_operator_identity_observations','andromeda_hotel_identities','catalog_hotels'] as $t){$q=$db->query("SHOW TABLES LIKE ".$db->quote($t));$tables[$t]=$q&&$q->fetchColumn()!==false;}foreach($tables as $t=>$ok)if(!$ok)throw new RuntimeException('missing_table_'.$t);
    $catalog=[];foreach(hmmo_select($db,'SELECT id,country_id,name,region_name,subregion_name FROM catalog_hotels WHERE country_id IN (1,2,4,8,9,10,12,16) AND is_active=1') as $r)$catalog[(int)$r['id']]=$r;
    $registry=[];$localNamespaces=[];foreach(hmmo_select($db,'SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities') as $r){$ns=(string)$r['supplier_namespace'];$ext=(string)$r['external_hotel_id'];$registry[$ns.'|'.$ext]=$r;if($r['local_hotel_id']!==null&&$r['decision_status']==='accepted')$localNamespaces[(int)$r['local_hotel_id']][$ns]=true;}
    $rows=hmmo_select($db,'SELECT id,first_seen_at,last_seen_at,observation_count,source,search_id,country_id,region_id,subregion_id,hotel_id,hotel_name,region_name,subregion_name,latitude,longitude,operator_id,operator_name,tour_id,operator_link,operator_link_host,operator_link_path,operator_link_query FROM tour_operator_identity_observations WHERE country_id IN (1,2,4,8,9,10,12,16) AND hotel_id IS NOT NULL AND operator_id IS NOT NULL ORDER BY observation_count DESC,last_seen_at DESC,id');
    $db->exec('ROLLBACK');

    $stats=['rows_scanned'=>count($rows),'active_core8_rows'=>0,'invalid_link'=>0,'metadata_conflict'=>0,'missing_link_shape'=>0,'secret_bearing_query'=>0,'safe_link_shape'=>0,'numeric_query_rows'=>0];$facts=[];$operatorStats=[];
    foreach($rows as $r){$local=(int)$r['hotel_id'];if(!isset($catalog[$local]))continue;$stats['active_core8_rows']++;$oid=hmmo_id($r['operator_id']);if($oid===null)continue;$oname=hmmo_text($r['operator_name']??'');$weight=max(1,(int)($r['observation_count']??1));$parts=hmmo_safe_parts($r);$status=(string)($parts['status']??'invalid_link');if($status!=='safe'){$stats[$status]=($stats[$status]??0)+1;continue;}$stats['safe_link_shape']++;$opKey=(string)$oid;$operatorStats[$opKey]['operator_id']=$oid;$operatorStats[$opKey]['operator_names'][$oname]=true;$operatorStats[$opKey]['rows']=($operatorStats[$opKey]['rows']??0)+1;$operatorStats[$opKey]['weight']=($operatorStats[$opKey]['weight']??0)+$weight;$operatorStats[$opKey]['hosts'][$parts['host']]=true;
        $had=false;foreach($parts['numeric'] as $key=>$values){if(count($values)!==1)continue;$had=true;$facts[]=['operator_id'=>$oid,'operator_name'=>$oname,'host'=>$parts['host'],'path'=>$parts['path'],'query_key'=>$key,'local_hotel_id'=>$local,'value'=>(int)$values[0],'weight'=>$weight];}$stats['numeric_query_rows']+=($had?1:0);
    }
    $rules=hmmo_rule_candidates($facts);$certified=[];$missing=[];$existingSame=[];$holds=[];
    foreach($rules as $rule){$stable=$rule['stable'];unset($rule['stable']);if(!$rule['certified_shape'])continue;$certified[]=$rule;
        $ns='operator_'.$rule['operator_id'];foreach($stable as $local=>$sv){$native=(string)$sv['native_id'];$cur=$registry[$ns.'|'.$native]??null;$c=$catalog[(int)$local];$baseRow=['supplier_namespace'=>$ns,'operator_id'=>$rule['operator_id'],'operator_name'=>$rule['operator_name'],'host'=>$rule['host'],'path'=>$rule['path'],'query_key'=>$rule['query_key'],'native_hotel_id'=>$native,'local_hotel_id'=>(int)$local,'local_hotel_name'=>$c['name'],'country_id'=>(int)$c['country_id'],'region_name'=>$c['region_name'],'subregion_name'=>$c['subregion_name'],'observation_weight'=>(int)$sv['weight'],'existing_namespaces'=>array_keys($localNamespaces[(int)$local]??[])];sort($baseRow['existing_namespaces'],SORT_STRING);
            if($cur===null){$missing[]=$baseRow+['route'=>'prepared_missing_typed_identity'];}
            elseif($cur['decision_status']==='accepted'&&(int)$cur['local_hotel_id']===(int)$local){$existingSame[]=$baseRow+['route'=>'already_accepted_same'];}
            else{$holds[]=$baseRow+['route'=>'current_identity_hold','current_status'=>$cur['decision_status'],'current_local_hotel_id'=>$cur['local_hotel_id']===null?null:(int)$cur['local_hotel_id']];}
        }
    }
    foreach($operatorStats as &$o){$names=array_keys($o['operator_names']??[]);sort($names,SORT_STRING);$hosts=array_keys($o['hosts']??[]);sort($hosts,SORT_STRING);$o['operator_names']=$names;$o['hosts']=$hosts;}unset($o);ksort($operatorStats,SORT_NATURAL);
    usort($missing,static fn($a,$b)=>($b['observation_weight']<=>$a['observation_weight'])?:strcmp($a['supplier_namespace'].'|'.$a['native_hotel_id'],$b['supplier_namespace'].'|'.$b['native_hotel_id']));
    usort($existingSame,static fn($a,$b)=>($b['observation_weight']<=>$a['observation_weight'])?:strcmp($a['supplier_namespace'].'|'.$a['native_hotel_id'],$b['supplier_namespace'].'|'.$b['native_hotel_id']));
    usort($holds,static fn($a,$b)=>($b['observation_weight']<=>$a['observation_weight'])?:strcmp($a['supplier_namespace'].'|'.$a['native_hotel_id'],$b['supplier_namespace'].'|'.$b['native_hotel_id']));
    $result=['schema'=>'hotel-match-tv-multiop-link-census/1','operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','server_current'=>true,'stats'=>$stats,'operator_stats'=>array_values($operatorStats),'rule_candidates'=>array_map(static function($r){$x=$r;unset($x['stable']);return $x;},$rules),'certified_rules'=>$certified,'certified_rule_count'=>count($certified),'prepared_missing_typed_identity_count'=>count($missing),'prepared_missing_observation_weight'=>array_sum(array_column($missing,'observation_weight')),'prepared_missing_typed_identities'=>$missing,'already_accepted_same_count'=>count($existingSame),'already_accepted_same'=>$existingSame,'current_identity_hold_count'=>count($holds),'current_identity_holds'=>$holds,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'safe_to_write_now'=>false,'no_replay'=>true];
    $digest=hmmo_write_new($dir.'/result.json',$result);hmmo_write_new($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$digest,'readback_verified'=>hash('sha256',(string)file_get_contents($dir.'/result.json'))===$digest,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
    echo hmmo_json(['state'=>$result['state'],'rows_scanned'=>$stats['rows_scanned'],'safe_link_shape'=>$stats['safe_link_shape'],'certified_rule_count'=>count($certified),'prepared_missing_typed_identity_count'=>count($missing),'prepared_missing_observation_weight'=>$result['prepared_missing_observation_weight'],'already_accepted_same_count'=>count($existingSame),'current_identity_hold_count'=>count($holds)])."\n";
}catch(Throwable $e){if($db instanceof PDO&&$db->inTransaction())$db->rollBack();$failure=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','error_class'=>get_class($e),'error_sha256'=>hash('sha256',$e->getMessage()),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];$digest=hmmo_write_new($dir.'/failure.json',$failure);hmmo_write_new($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','failure_sha256'=>$digest,'readback_verified'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);fwrite(STDERR,"HMMO_FAILED\n");exit(2);}
