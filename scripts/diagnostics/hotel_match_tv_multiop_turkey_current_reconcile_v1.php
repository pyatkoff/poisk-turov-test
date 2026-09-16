<?php
declare(strict_types=1);
const OP='hotel-match-tv-multiop-turkey-current-reconcile-1971-20260916-v1';
function j($v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function putnew(string $p,$v):string{$b=j($v)."\n";$f=@fopen($p,'x');if(!$f)throw new RuntimeException('durable_exists');fwrite($f,$b);fflush($f);fclose($f);if(file_get_contents($p)!==$b)throw new RuntimeException('durable_readback');return hash('sha256',$b);}
function rows(PDO $db,string $sql,array $p=[]):array{$s=$db->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);}
function table(PDO $db,string $name):bool{try{$db->query('SELECT 1 FROM `'.$name.'` LIMIT 1');return true;}catch(Throwable $e){return false;}}
if(in_array('--self-test',$argv??[],true)){if(OP!=='hotel-match-tv-multiop-turkey-current-reconcile-1971-20260916-v1')throw new RuntimeException('op');echo "PASS\n";exit;}
$op=(string)getenv('OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');if($op!==OP||!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('operation_guard');
$root=(string)realpath(getcwd());if($root===''||basename($root)!=='anytoour.ru')throw new RuntimeException('root_guard');$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations';if(!is_dir($base))throw new RuntimeException('operations_root_missing');$dir=$base.'/'.$op;if(!mkdir($dir,0700))throw new RuntimeException('operation_exists');putnew($dir.'/reservation.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'reserved_before_db','read_only'=>true,'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'supplier_calls'=>0,'no_replay'=>true]);
$res=[];
try{
  $dbf=is_file($root.'/data/db-v1.php')?$root.'/data/db-v1.php':$root.'/v2/data/db-v1.php';require_once $dbf;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
  $anex=[35898=>1221,10115=>2160,32880=>17443];$aids=array_keys($anex);$lids=array_values($anex);
  $map=rows($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_hotel_search_mappings WHERE anex_hotel_id IN (?,?,?) OR catalog_hotel_id IN (?,?,?)',array_merge($aids,$lids));
  $dec=table($db,'anex_hotel_decisions')?rows($db,'SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id IN (?,?,?)',$aids):[];
  $exc=table($db,'anex_review_pair_exclusions')?rows($db,'SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id IN (?,?,?) OR catalog_hotel_id IN (?,?,?)',array_merge($aids,$lids)):[];
  $hot=rows($db,'SELECT * FROM catalog_hotels WHERE id IN (?,?,?)',$lids);
  $aliases=[];foreach(['catalog_hotel_aliases','hotel_aliases'] as $t){if(table($db,$t)){try{$aliases[$t]=rows($db,'SELECT * FROM `'.$t.'` WHERE hotel_id IN (?,?,?)',$lids);}catch(Throwable $e){$aliases[$t]=[['error'=>'schema_unexpected']];}}}
  $native=[];$nativeCols=[];if(table($db,'andromeda_hotel_identities')){$nativeCols=rows($db,'SHOW COLUMNS FROM andromeda_hotel_identities');$all=rows($db,'SELECT * FROM andromeda_hotel_identities');foreach($all as $r){$hit=false;foreach($r as $v){if(is_scalar($v)&&trim((string)$v)==='30752'){$hit=true;break;}}if($hit)$native[]=$r;}}
  $obs=[];if(table($db,'tour_operator_identity_observations')){$obs=rows($db,'SELECT * FROM tour_operator_identity_observations WHERE hotel_id IN (?,?,?)',$lids);}
  $db->exec('ROLLBACK');
  $res=['operation_id'=>$op,'source_sha'=>$sha,'status'=>'completed_read_only','evidence'=>['funsun_tv_local'=>1221,'funsun_native_observed'=>'30752','anex_proposals'=>$anex],'anex_mapping_rows'=>$map,'anex_decision_rows'=>$dec,'anex_exclusion_rows'=>$exc,'catalog_rows'=>$hot,'alias_rows'=>$aliases,'typed_identity_columns'=>$nativeCols,'typed_identity_30752_hits'=>$native,'saved_operator_observations'=>$obs,'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'supplier_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];
}catch(Throwable $e){try{if(isset($db)&&$db->inTransaction())$db->exec('ROLLBACK');}catch(Throwable $x){}$res=['operation_id'=>$op,'source_sha'=>$sha,'status'=>'blocked','reason'=>$e->getMessage(),'database_writes'=>0,'mapping_writes'=>0,'tourvisor_calls'=>0,'supplier_calls'=>0,'no_replay'=>true];}
$h=putnew($dir.'/result.json',$res);putnew($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>$res['status'],'result_sha256'=>$h,'readback_verified'=>hash('sha256',file_get_contents($dir.'/result.json'))===$h,'no_replay'=>true]);echo j($res)."\n";exit($res['status']==='completed_read_only'?0:2);
