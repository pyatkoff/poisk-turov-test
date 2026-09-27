<?php
declare(strict_types=1);
const R72_OP='hotel-match-samo31-reconcile-1971-20260927-v72';
const R72_PAIRS=[167=>'261102',242=>'244469',320=>'405440',856=>'174700',898=>'73024',1299=>'2000123210',1734=>'129812',1735=>'5767',1736=>'298738',1737=>'150070',1739=>'450605',1760=>'207647',1773=>'37255',1781=>'315584',3111=>'141649',4100=>'105904',15819=>'2000028170',22632=>'450630',23214=>'394688',23668=>'2000073524',45140=>'2000034456',51062=>'2000034535',68667=>'10866',4326=>'309768',55945=>'2000037585',56479=>'2000093384',67304=>'2000055490',76753=>'2000087342',108356=>'269426',116886=>'2000103169',121109=>'2000090159'];
function q(PDO $db,string $sql,array $a=[]):array{$s=$db->prepare($sql);if(!$s||!$s->execute(array_values($a)))throw new RuntimeException('query');return $s->fetchAll(PDO::FETCH_ASSOC);}
function main(array $argv):int{
 if(($argv[1]??'')!=='--execute')throw new RuntimeException('disabled');$root=getenv('ANYTOUR_ROOT');$dir=getenv('MATCH_OPERATION_DIR');$head=getenv('MATCH_SOURCE_SHA');
 if(!is_dir($root)||!is_dir($dir)||basename($dir)!==R72_OP)throw new RuntimeException('scope');
 require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');$db=v2_data_db();$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
 $ids=array_keys(R72_PAIRS);$cats=array_values(R72_PAIRS);$ih=implode(',',array_fill(0,count($ids),'?'));$ch=implode(',',array_fill(0,count($cats),'?'));
 $rows=q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status FROM andromeda_hotel_identities WHERE (supplier_namespace='andromeda_catalog' AND external_hotel_id IN ($ch)) OR local_hotel_id IN ($ih) ORDER BY supplier_namespace,external_hotel_id",array_merge($cats,$ids));
 $src=[];$target=[];foreach($rows as $r){if($r['supplier_namespace']==='andromeda_catalog')$src[(string)$r['external_hotel_id']][]=$r;if($r['supplier_namespace']==='andromeda_catalog')$target[(int)$r['local_hotel_id']][]=$r;}
 $out=[];$counts=[];foreach(R72_PAIRS as $id=>$cat){$s=$src[$cat]??[];$t=$target[$id]??[];$status='missing';
  if($s){$same=array_values(array_filter($s,fn($r)=>(int)$r['local_hotel_id']===$id));$other=array_values(array_filter($s,fn($r)=>(int)$r['local_hotel_id']!==$id));if($other)$status='source_conflict';elseif($same&&$same[0]['decision_status']==='accepted')$status='already_accepted';elseif($same)$status='pending_same';}
  elseif($t)$status='target_occupied';
  $counts[$status]=($counts[$status]??0)+1;$out[]=['local_hotel_id'=>$id,'catalog_id'=>$cat,'status'=>$status];}
 $db->rollBack();ksort($counts);$res=['operation'=>R72_OP,'state'=>'completed_read_only','source_sha'=>$head,'counts'=>$counts,'rows'=>$out,'database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0];
 $raw=json_encode($res,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";file_put_contents($dir.'/result.json',$raw,LOCK_EX);$rec=['operation'=>R72_OP,'state'=>$res['state'],'result_sha256'=>hash('sha256',$raw),'database_writes'=>0,'mapping_writes'=>0,'provider_http_calls'=>0];file_put_contents($dir.'/receipt.json',json_encode($rec)."\n",LOCK_EX);echo json_encode(['state'=>$res['state'],'counts'=>$counts])."\n";return 0;}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(main($argv));
