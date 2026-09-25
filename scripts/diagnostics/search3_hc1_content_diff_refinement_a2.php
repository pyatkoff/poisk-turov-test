<?php
/** Exact A1-cohort read-only refinement; no supplier, image HTTP or DB mutations. */
declare(strict_types=1);
require_once dirname(__DIR__,2).'/v2/data/hotel-presentation-read-v1.php';
const OP2='search3-hc1-content-diff-refinement-20260925-a2';
const A1='search3-hc1-full-content-parity-20260925-a1';
const A1_SHA='d9ecd0f2c56f43ca33191383b71d7e722a28b56077e766fc559c4cbe498f621d';
function must(bool $b,string $s): void {if(!$b)throw new RuntimeException('HC2_'.$s);}
function stable2(mixed $v): mixed {if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$x)$x=stable2($x);return $v;}
function j(mixed $v): string {return json_encode(stable2($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function hh(mixed $v): string {return hash('sha256',j($v));}
function tally(array &$r,string $key): void {$r[$key]=($r[$key]??0)+1;}
function setdiff(array $a,array $b): array {return array_values(array_diff($a,$b));}
function gallery(array $a,array $b,int $local): string {
 if($a===$b)return 'equal';if(!$b&&$a)return 'canonical_missing';if(!$a&&$b)return 'reader_missing';$missing=setdiff($a,$b);$extra=setdiff($b,$a);
 if(!$missing&&!$extra)return 'order_only';
 $different=array_unique(array_merge($missing,$extra));$preview='https://static.tourvisor.ru/hotel_pics/main400/'.$local.'.jpg';
 if(count($different)===1&&reset($different)===$preview)return 'only_main400_preview_url_presence';
 return 'other_gallery_reference_difference';
}
function coordinate(mixed $a,mixed $b): array {
 if(!is_array($a)||!is_array($b)||!isset($a['latitude'],$a['longitude'],$b['latitude'],$b['longitude']))return ['state'=>'missing_side'];
 $dx=abs((float)$a['latitude']-(float)$b['latitude']);$dy=abs((float)$a['longitude']-(float)$b['longitude']);
 if($dx===0.0&&$dy===0.0)return ['state'=>'exact'];
 if($dx<=0.00000005001&&$dy<=0.00000005001)return ['state'=>'decimal_7_rounding_only'];
 $lat1=deg2rad((float)$a['latitude']);$lat2=deg2rad((float)$b['latitude']);$z=sin(($lat2-$lat1)/2)**2+cos($lat1)*cos($lat2)*sin(deg2rad((float)$b['longitude']-(float)$a['longitude'])/2)**2;
 $m=6371000*2*asin(min(1,sqrt(max(0,$z))));return ['state'=>$m<=1?'under_1m':($m<=10?'1_10m':($m<=100?'10_100m':($m<=1000?'100_1000m':'over_1000m'))),'meters'=>round($m,3)];
}
if(($argv[1]??'')==='--self-test'){
 must(gallery(['a'],['a'],1)==='equal','EQ');must(gallery(['a'],['https://static.tourvisor.ru/hotel_pics/main400/1.jpg','a'],1)==='only_main400_preview_url_presence','PREVIEW');must(gallery(['a'],['b'],1)==='other_gallery_reference_difference','DIFF');
 must(coordinate(['latitude'=>1.123456789,'longitude'=>2],['latitude'=>1.1234568,'longitude'=>2])['state']==='decimal_7_rounding_only','ROUND');must(coordinate(['latitude'=>1,'longitude'=>2],['latitude'=>1.01,'longitude'=>2])['state']==='over_1000m','DISTANCE');must(coordinate(null,null)['state']==='missing_side','NULL');echo "HC2_SELF_TEST_OK checks=6\n";exit;
}
must(($argv[1]??'')==='--execute','EXECUTE');$dir=(string)getenv('HC1_OPERATION_DIR');$root=(string)getenv('ANYTOUR_ROOT');must(is_dir($dir)&&basename($dir)===OP2&&is_file($root.'/config.php'),'DIR');
$src=dirname($dir).'/'.A1;must(hash_file('sha256',$src.'/result.json')===A1_SHA,'A1_RESULT');$a1=json_decode(file_get_contents($src.'/result.json'),true,512,JSON_THROW_ON_ERROR);must($a1['state']==='completed_read_only'&&$a1['activeProfiles']===15999&&hash_file('sha256',$src.'/hotels.jsonl')===$a1['hotelRowsSha256'],'A1_ROWS');
$receipt=json_decode(file_get_contents($src.'/receipt.json'),true,512,JSON_THROW_ON_ERROR);must(($receipt['resultSha256']??null)===A1_SHA&&($receipt['state']??null)==='completed_read_only'&&($receipt['operation']??null)===A1,'A1_RECEIPT');
$input=[];$h=fopen($src.'/hotels.jsonl','rb');while(($line=fgets($h))!==false){$r=json_decode($line,true,512,JSON_THROW_ON_ERROR);must(count($r['pairs'])===1,'PAIR');$p=$r['pairs'][0];if($p['rawStatus']==='raw_valid'||!$p['fields']['images']['saved']['empty']||!$p['fields']['images']['canonical']['empty'])$input[(int)$r['anytourHotelId']]=$r;}fclose($h);must(count($input)<=5000,'BOUND');
$marker=fopen($dir.'/audit-started.json','xb');must(is_resource($marker),'NO_REPLAY');fwrite($marker,j(['operation'=>OP2,'inputProfiles'=>count($input)]));fclose($marker);
$_SERVER['DOCUMENT_ROOT']=$root;require_once dirname(__DIR__,2).'/v2/data/db-v1.php';$pdo=null;
$r=['operation'=>OP2,'state'=>'incomplete','inputA1Sha256'=>A1_SHA,'inputProfiles'=>count($input),'checkedProfiles'=>0,'driftedProfiles'=>[],'gallery'=>[],'primaryImage'=>[],'rawToStoredCoordinates'=>[],'rawToReaderCoordinates'=>[],'readerToCanonicalCoordinates'=>[],'rawGalleryClipping'=>['profiles'=>0,'uniqueReferencesOmittedBy100Cap'=>0],'readerGalleryLoss'=>['profiles'=>0,'uniqueRawReferencesMissing'=>0],'rows'=>[],'databaseWrites'=>0,'supplierCalls'=>0];
try{
 $pdo=v2_data_db();$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();$r['snapshotAt']=$pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn();
 foreach(array_chunk(array_keys($input),100) as $chunk){
  $marks=implode(',',array_fill(0,count($chunk),'?'));$q=$pdo->prepare("SELECT id,profile_json,profile_sha256,revision FROM anytour_hotels WHERE is_active=1 AND id IN ($marks)");$q->execute($chunk);$owns=[];foreach($q->fetchAll() as $x)$owns[(int)$x['id']]=$x;
  $q=$pdo->prepare("SELECT anytour_hotel_id,external_key,source_sha256 FROM anytour_hotel_sources WHERE namespace='legacy_catalog' AND anytour_hotel_id IN ($marks)");$q->execute($chunk);$links=[];foreach($q->fetchAll() as $x)$links[(int)$x['anytour_hotel_id']][(int)$x['external_key']]=$x['source_sha256'];
  $locals=array_map(static fn($id)=>(int)$input[$id]['pairs'][0]['legacyHotelId'],$chunk);$saved=[];foreach(hotel_presentation_read_many($pdo,$locals)['items'] as $x)$saved[(int)$x['id']]=$x;
  $q=$pdo->prepare("SELECT hotel_id,status,source_hash,raw_json,latitude,longitude,fetched_at FROM catalog_hotel_details WHERE hotel_id IN ($marks)");$q->execute($locals);$details=[];foreach($q->fetchAll() as $x)$details[(int)$x['hotel_id']]=$x;
  foreach($chunk as $id){$before=$input[$id];$bp=$before['pairs'][0];$local=(int)$bp['legacyHotelId'];$own=$owns[$id]??null;$s=$saved[$local]??null;
   $guard=$own&&$s&&($links[$id][$local]??null)===$bp['legacySourceSha256']&&hash('sha256',$own['profile_json'])===$before['profileSha256']&&$own['profile_sha256']===$before['profileSha256']&&$s['detailsFetchedAt']===$bp['detailsFetchedAt'];
   foreach(['images','primaryImage','coordinates','rating','name'] as $f)$guard=$guard&&hh($s[$f]??null)===$bp['fields'][$f]['saved']['sha256'];
   if(!$guard){$r['driftedProfiles'][]=$id;continue;}
   ++$r['checkedProfiles'];$p=json_decode($own['profile_json'],true,512,JSON_THROW_ON_ERROR);$sg=$s['images']??[];$pg=$p['images']??[];
   $row=['id'=>$id,'legacyHotelId'=>$local,'name'=>$before['name'],'gallery'=>gallery($sg,$pg,$local),'readerPhotoCount'=>count($sg),'canonicalPhotoCount'=>count($pg),'readerReferencesMissingInCanonical'=>count(setdiff($sg,$pg)),'canonicalReferencesNotInReader'=>count(setdiff($pg,$sg))];tally($r['gallery'],$row['gallery']);
   $preview='https://static.tourvisor.ru/hotel_pics/main400/'.$local.'.jpg';$main=$s['primaryImage']??null;$ownMain=$p['primaryImage']??null;
   $mainKind=$main===$ownMain?'equal':(!$ownMain&&$main?'canonical_missing':($main===$preview||$ownMain===$preview?'one_side_main400_preview':'different_nonpreview_reference'));$row['primaryImage']=$mainKind;tally($r['primaryImage'],$mainKind);
   $coord=coordinate($s['coordinates']??null,$p['coordinates']??null);$row['readerToCanonicalCoordinates']=$coord;tally($r['readerToCanonicalCoordinates'],$coord['state']);
   $d=$details[$local]??null;
   if($d&&$d['status']==='success'&&is_string($d['raw_json'])&&hash('sha256',$d['raw_json'])===$d['source_hash']){
    $raw=json_decode($d['raw_json'],true,512,JSON_THROW_ON_ERROR);must((int)$raw['id']===$local,'RAW_ID');$unique=[];foreach($raw['images']??[] as $url){$u=v2_hotel_detail_https_url($url);if($u!==null)$unique[$u]=true;}$all=array_keys($unique);$normal=v2_hotel_detail_normalized($raw);
    $row['rawUniquePhotoCount']=count($all);$row['normalPhotoCount']=count($normal['images']);$loss=count(setdiff($all,$normal['images']));$row['omittedBy100Cap']=$loss;if($loss){++$r['rawGalleryClipping']['profiles'];$r['rawGalleryClipping']['uniqueReferencesOmittedBy100Cap']+=$loss;}
    $loss=count(setdiff($all,$sg));$row['rawReferencesMissingInReader']=$loss;if($loss){++$r['readerGalleryLoss']['profiles'];$r['readerGalleryLoss']['uniqueRawReferencesMissing']+=$loss;}
    $rawCoord=$normal['latitude']!==null&&$normal['longitude']!==null?['latitude'=>$normal['latitude'],'longitude'=>$normal['longitude']]:null;
    $stored=$d['latitude']!==null&&$d['longitude']!==null?['latitude'=>$d['latitude'],'longitude'=>$d['longitude']]:null;
    foreach(['rawToStoredCoordinates'=>$stored,'rawToReaderCoordinates'=>$s['coordinates']??null] as $k=>$v){$a=coordinate($rawCoord,$v);$row[$k]=$a;tally($r[$k],$a['state']);}
   }
   $r['rows'][]=$row;
  }
 }
 must($pdo->inTransaction(),'TRANSACTION');$pdo->rollBack();$r['state']='completed_read_only';$r['finishedAt']=gmdate('c');
}catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$r['errorClass']=get_class($e);$r['errorCode']=preg_match('/^HC2_[A-Z0-9_]+$/D',$e->getMessage())?$e->getMessage():'REFINEMENT_EXCEPTION';}
file_put_contents($dir.'/result.json',j($r)."\n");file_put_contents($dir.'/receipt.json',j(['operation'=>OP2,'state'=>$r['state'],'resultSha256'=>hash_file('sha256',$dir.'/result.json'),'databaseWrites'=>0,'supplierCalls'=>0])."\n");exit($r['state']==='completed_read_only'?0:1);
