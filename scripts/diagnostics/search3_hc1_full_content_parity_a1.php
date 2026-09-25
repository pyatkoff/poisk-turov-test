<?php
/** HC-1 all-active-profile content audit. No HTTP, mutations, schema or matching. */
declare(strict_types=1);
require_once dirname(__DIR__,2).'/v2/data/anytour-canonical-catalog-v1.php';
require_once dirname(__DIR__,2).'/v2/data/anytour-profile-enrichment-v1.php';
const OP='search3-hc1-full-content-parity-20260925-a1';
const FIELDS=['name','country','region','subRegion','category','rating','type','description','primaryImage','images','address','place','build','repair','square','coordinates','phone','site','hotelInformation.infrastructure','hotelInformation.services','hotelInformation.meals','hotelInformation.roomTypes','hotelInformation.infrastructure.beach','hotelInformation.infrastructure.territory','hotelInformation.services.child','hotelInformation.services.animation','hotelInformation.services.free','hotelInformation.services.servicesPay','hotelInformation.services.tags'];
function need(bool $ok,string $why): void {if(!$ok)throw new RuntimeException('HC1_'.$why);}
function stable(mixed $v): mixed {if(!is_array($v))return $v;if(!array_is_list($v))ksort($v,SORT_STRING);foreach($v as &$x)$x=stable($x);return $v;}
function js(mixed $v): string {return json_encode(stable($v),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function dig(mixed $v): string {return hash('sha256',js($v));}
function at(array $v,string $path): mixed {foreach(explode('.',$path) as $key){if(!is_array($v)||!array_key_exists($key,$v))return null;$v=$v[$key];}return $v;}
function emptyValue(mixed $v): bool {return $v===null||$v===[]||(is_string($v)&&trim($v)==='');}
function shape(mixed $v): string {return is_array($v)?(array_is_list($v)?'list':'object'):get_debug_type($v);}
function plain(string $v): string {return trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($v),ENT_QUOTES|ENT_HTML5,'UTF-8'))??$v);}
function compare(mixed $s,mixed $o): string {
 if(emptyValue($s))return emptyValue($o)?'both_missing':'source_missing_local_present';
 if(emptyValue($o))return 'local_missing';
 if(js($s)===js($o))return 'equal';
 if(is_string($s)&&is_string($o)&&plain($s)!==''&&plain($s)===plain($o))return 'format_only';
 if(is_array($s)&&is_array($o)&&array_is_list($s)&&array_is_list($o)){
  $a=array_map('js',$s);$b=array_map('js',$o);sort($a);sort($b);if($a===$b)return 'order_only';
 }
 return shape($s)!==shape($o)?'shape_diff':'different';
}
function desc(mixed $v): array {return ['shape'=>shape($v),'empty'=>emptyValue($v),'sha256'=>dig($v),'size'=>is_array($v)?count($v):(is_string($v)?strlen($v):null)];}
function cleanSample(mixed $v): mixed {
 if(is_string($v))return mb_substr(plain($v),0,240,'UTF-8');
 if(is_array($v))return ['shape'=>shape($v),'count'=>count($v),'keys'=>array_slice(array_map('strval',array_keys($v)),0,24),'sha256'=>dig($v)];
 return $v;
}
function sourceProfile(array $s): array {
 $p=$s;foreach(['country','region','subRegion'] as $key){$p[$key]=is_array($s[$key]??null)&&isset($s[$key]['name'])?['name'=>$s[$key]['name']]:null;}
 $p['hotelInformation']=['infrastructure'=>$s['infrastructure']??[],'services'=>$s['services']??[],'meals'=>$s['meals']??[],'roomTypes'=>$s['roomTypes']??null];return $p;
}
function rawPresentation(array $raw,array $n): array {
 $out=['id'=>$n['hotel_id'],'name'=>$n['name'],'country'=>$raw['country']??null,'region'=>$raw['region']??null,'subRegion'=>$raw['subRegion']??null,'category'=>$n['category'],'rating'=>$n['rating'],'type'=>$n['hotel_type'],'primaryImage'=>$n['primary_image_url'],'images'=>$n['images']];
 foreach(['description','address','place','build','repair','square','phone','site'] as $f)$out[$f]=$n[$f];
 $out['coordinates']=$n['latitude']!==null&&$n['longitude']!==null?['latitude'=>$n['latitude'],'longitude'=>$n['longitude']]:null;
 foreach(['infrastructure','services','meals'] as $f)$out[$f]=$raw[$f]??[];$out['roomTypes']=$n['room_types'];return sourceProfile($out);
}
function checkedJson(mixed $s,mixed $hash): ?array {
 if(!is_string($s)||!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||!hash_equals(hash('sha256',$s),$hash))return null;
 try{$v=json_decode($s,true,512,JSON_THROW_ON_ERROR);return is_array($v)?$v:null;}catch(Throwable){return null;}
}
function bump(array &$r,string $f,string $state): void {$r[$f][$state]=($r[$f][$state]??0)+1;}
function selfTest(): void {
 need(compare(null,0)==='source_missing_local_present','ZERO_NOT_MISSING');need(compare(false,null)==='local_missing','FALSE_NOT_MISSING');
 need(compare(['beach'=>'x'],[])==='local_missing','OBJECT_GAP');need(compare(['beach'=>'x'],['beach'=>'y'])==='different','OBJECT_VALUE');
 need(compare(['a'=>1,'b'=>2],['b'=>2,'a'=>1])==='equal','KEY_ORDER');need(compare(['a','b'],['b','a'])==='order_only','LIST_ORDER');
 need(compare('<p>A &amp; B</p>','A & B')==='format_only','TEXT');need(compare('OLD','NEW')==='different','TEXT_DIFF');
 need(checkedJson('{}',str_repeat('0',64))===null,'HASH');need(at(sourceProfile(['services'=>['child'=>'kids']]),'hotelInformation.services.child')==='kids','PATH');
 $m=new ReflectionMethod(AnyTourProfileEnrichmentV1::class,'sourceFacts');$m->setAccessible(true);
 need($m->invoke(null,['services'=>['child'=>'kids']])['hotelInformation.services']===null,'REPRODUCE_OBJECT_DROP');
 need($m->invoke(null,['services'=>['wifi']])['hotelInformation.services']===['wifi'],'LIST_PASSTHROUGH');
 echo "HC1_PARITY_SELF_TEST_OK checks=12\n";
}
if(($argv[1]??'')==='--self-test'){selfTest();exit;}
need(($argv[1]??'')==='--execute','EXPLICIT_EXECUTE');
$dir=(string)getenv('HC1_OPERATION_DIR');$root=(string)getenv('ANYTOUR_ROOT');
need($dir!==''&&is_dir($dir)&&basename($dir)===OP&&$root!==''&&is_file($root.'/config.php'),'PATH');
need(!file_exists($dir.'/audit-started.json'),'NO_REPLAY');
file_put_contents($dir.'/audit-started.json',js(['operation'=>OP,'startedAt'=>gmdate('c'),'databaseWrites'=>0])."\n",LOCK_EX);
$_SERVER['DOCUMENT_ROOT']=$root;require_once dirname(__DIR__,2).'/v2/data/db-v1.php';
$pdo=null;$rowsHandle=null;
$result=['operation'=>OP,'state'=>'incomplete','sourceSha'=>'772cef853390afde25041a7ca65d30f86f97b549','databaseWrites'=>0,'supplierCalls'=>0,'liveTourvisorRead'=>false,'scannedProfiles'=>0,'comparableProfiles'=>0,'scannedPairs'=>0,'fieldOrder'=>FIELDS,'savedToCanonical'=>[],'rawToSavedDto'=>[],'rawToStored'=>[],'provenance'=>[],'sourceStatus'=>[],'sourceFreshness'=>[],'sourceDates'=>['oldest'=>null,'newest'=>null],'examples'=>[],'fullUiJourney'=>'not_checked'];
try{
 $pdo=v2_data_db();need($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql','MYSQL');
 (new AnyTourCanonicalCatalog($pdo))->assertSchema();
 $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();
 $tot=$pdo->query('SELECT COUNT(*) total,SUM(is_active=1) active,MAX(id) high FROM anytour_hotels')->fetch();
 $result['installedHashes']=[];foreach(['data/db-v1.php','data/hotel-details-v1.php','data/hotel-presentation-read-v1.php','data/anytour-canonical-catalog-v1.php','data/anytour-profile-enrichment-v1.php'] as $rel){$path=$root.'/_preview/search3-local-candidate/'.$rel;$result['installedHashes'][$rel]=is_file($path)?hash_file('sha256',$path):null;}
 $result['snapshotAt']=(string)$pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn();
 $result['totalProfiles']=(int)$tot['total'];$result['activeProfiles']=(int)$tot['active'];$high=(int)$tot['high'];
 need($result['activeProfiles']<=50000,'SAFETY_BOUND');
 $result['sourceCatalogActive']=(int)$pdo->query('SELECT COUNT(*) FROM catalog_hotels WHERE is_active=1')->fetchColumn();
 $result['savedDetailsByStatus']=$pdo->query('SELECT status,COUNT(*) count FROM catalog_hotel_details GROUP BY status')->fetchAll();
 $result['sourceNamespaces']=$pdo->query('SELECT namespace,acquired_via,COUNT(*) count FROM anytour_hotel_sources GROUP BY namespace,acquired_via')->fetchAll();
 $detailCols=$pdo->query('SHOW COLUMNS FROM catalog_hotel_details')->fetchAll(PDO::FETCH_COLUMN);$result['rawColumnsAvailable']=in_array('raw_json',$detailCols,true)&&in_array('source_hash',$detailCols,true);
 $rowsHandle=fopen($dir.'/hotels.jsonl','xb');need(is_resource($rowsHandle),'OUTPUT');
 $m=new ReflectionMethod(AnyTourProfileEnrichmentV1::class,'sourceFacts');$m->setAccessible(true);
 $cursor=0;$sampleIds=[];$sampleFieldSeen=[];$samples=[];$diffProfiles=[];$allEqual=0;$rawKeys=[];$commonKeys=[];$objectDrop=[];$largeGallery=0;$rawValid=0;
 while(true){
  $q=$pdo->prepare('SELECT id,profile_json,profile_sha256,revision FROM anytour_hotels WHERE is_active=1 AND id>? AND id<=? ORDER BY id LIMIT 100');$q->execute([$cursor,$high]);$hotels=$q->fetchAll();if(!$hotels)break;
  $ids=array_map(static fn($h)=>(int)$h['id'],$hotels);$cursor=max($ids);$marks=implode(',',array_fill(0,count($ids),'?'));
  $q=$pdo->prepare("SELECT namespace,external_key,anytour_hotel_id,acquired_via,source_json,source_sha256,last_seen_at FROM anytour_hotel_sources WHERE anytour_hotel_id IN ($marks) AND namespace IN ('legacy_catalog','anytour_local_id','profile_enrichment:legacy_saved_v1') ORDER BY id");$q->execute($ids);$sources=[];
  foreach($q->fetchAll() as $sr)$sources[(int)$sr['anytour_hotel_id']][$sr['namespace']][]=$sr;
  $localIds=[];foreach($sources as $group)foreach($group['legacy_catalog']??[] as $sr){$id=hotel_details_read_id($sr['external_key']);if($id!==null)$localIds[$id]=$id;}
  $saved=[];$details=[];
  foreach(array_chunk(array_values($localIds),100) as $chunk){
   foreach(hotel_presentation_read_many($pdo,$chunk)['items'] as $s)$saved[(int)$s['id']]=$s;
   $mm=implode(',',array_fill(0,count($chunk),'?'));$q=$pdo->prepare("SELECT * FROM catalog_hotel_details WHERE hotel_id IN ($mm)");$q->execute($chunk);foreach($q->fetchAll() as $d)$details[(int)$d['hotel_id']]=$d;
  }
  foreach($hotels as $h){
   $own=(int)$h['id'];++$result['scannedProfiles'];$profile=checkedJson($h['profile_json'],$h['profile_sha256']);
   $entry=['anytourHotelId'=>$own,'revision'=>(int)$h['revision'],'profileSha256'=>$h['profile_sha256'],'pairs'=>[]];
   if($profile===null){$entry['state']='invalid_profile';bump($result['sourceStatus'],'profile','invalid_hash_or_json');fwrite($rowsHandle,js($entry)."\n");continue;}
   $entry['name']=is_string($profile['name']??null)?$profile['name']:'';$links=$sources[$own]['legacy_catalog']??[];
   if(!$links){$entry['state']='no_legacy_source';bump($result['sourceStatus'],'profile','no_legacy_source');fwrite($rowsHandle,js($entry)."\n");continue;}
   $hasComparable=false;$hasDiff=false;$seedExact=false;$receiptExact=false;
   foreach($sources[$own]['profile_enrichment:legacy_saved_v1']??[] as $pr){$p=checkedJson($pr['source_json'],$pr['source_sha256']);if($p&&($p['canonical_hotel_id']??null)===$own&&($p['result_profile_sha256']??null)===$h['profile_sha256']&&($p['result_revision']??null)===(int)$h['revision'])$receiptExact=true;}
   foreach($links as $link){
    ++$result['scannedPairs'];$local=hotel_details_read_id($link['external_key']);$seed=checkedJson($link['source_json'],$link['source_sha256']);
    $pair=['legacyHotelId'=>$local,'legacySourceSha256'=>$link['source_sha256'],'fields'=>[],'rawToSaved'=>[],'rawToStored'=>[]];
    if($local===null||$seed===null||hotel_details_read_id($seed['id']??null)!==$local){$pair['state']='invalid_source_identity_or_hash';$entry['pairs'][]=$pair;bump($result['sourceStatus'],'pair',$pair['state']);continue;}
    $aliasValid=false;foreach($sources[$own]['anytour_local_id']??[] as $ar){if((string)$ar['external_key']!==(string)$local)continue;$alias=checkedJson($ar['source_json'],$ar['source_sha256']);$aliasValid=$alias&&($alias['accepted_local_hotel_id']??null)===$local&&($alias['canonical_hotel_id']??null)===$own&&($alias['derived_from_namespace']??null)==='legacy_catalog';}
    $pair['localAliasValid']=$aliasValid;$s=$saved[$local]??null;if(!$s){$pair['state']='saved_catalog_row_missing_or_inactive';$entry['pairs'][]=$pair;bump($result['sourceStatus'],'pair',$pair['state']);continue;}
    $hasComparable=true;$sp=sourceProfile($s);$detail=$details[$local]??null;$raw=null;$norm=null;$rawP=null;
    $rawState=$detail===null?'not_acquired':(($detail['status']??null)!=='success'?'detail_not_success':'raw_missing');
    if($detail&&($detail['status']??null)==='success'&&is_string($detail['raw_json']??null)&&$detail['raw_json']!==''){
     $raw=checkedJson($detail['raw_json'],$detail['source_hash']??null);$rawState=$raw===null?'raw_invalid_hash_or_json':'raw_valid';
     if($raw!==null&&hotel_details_read_id($raw['id']??null)!==$local){$raw=null;$rawState='raw_identity_mismatch';}
     if($raw!==null){$norm=v2_hotel_detail_normalized($raw);$rawP=rawPresentation($raw,$norm);++$rawValid;foreach(array_keys($raw) as $k)$rawKeys[$k]=($rawKeys[$k]??0)+1;foreach(array_keys(is_array($raw['common']??null)?$raw['common']:[]) as $k)$commonKeys[$k]=($commonKeys[$k]??0)+1;}
    }
    $pair['rawStatus']=$rawState;$pair['detailsFetchedAt']=$s['detailsFetchedAt'];bump($result['sourceStatus'],'raw',$rawState);
    if($s['detailsFetchedAt']){
     $stamp=(string)$s['detailsFetchedAt'];$age=max(0,strtotime($result['snapshotAt'].' UTC')-strtotime($stamp.' UTC'));
     $bucket=$age<=86400?'0_1d':($age<=7*86400?'1_7d':($age<=30*86400?'7_30d':'over_30d'));$result['sourceFreshness'][$bucket]=($result['sourceFreshness'][$bucket]??0)+1;
     foreach(['oldest','newest'] as $k){$before=$result['sourceDates'][$k];if($before===null||($k==='oldest'?$stamp<$before:$stamp>$before))$result['sourceDates'][$k]=$stamp;}
    }
    $facts=$m->invoke(null,$s);$seenDiff=[];$invalid=[];
    foreach(['infrastructure','services','meals','images'] as $j){$value=$detail[$j.'_json']??null;if(is_string($value)&&trim($value)!==''){try{json_decode($value,true,512,JSON_THROW_ON_ERROR);}catch(Throwable){$invalid[$j]=true;}}}
    foreach(FIELDS as $f){
     $sv=at($sp,$f);$ov=at($profile,$f);$state=compare($sv,$ov);
     if(in_array($f,['phone','site'],true))$state=emptyValue($detail[$f]??null)?'not_exposed_by_saved_reader':'saved_reader_omits_available';
     foreach($invalid as $j=>$_)if($f===$j||str_starts_with($f,'hotelInformation.'.$j))$state='source_invalid_json';
     if($f==='type'&&!emptyValue($sv)&&emptyValue($ov))$state='native_type_not_local_dictionary';
     $pair['fields'][$f]=['state'=>$state,'saved'=>desc($sv),'canonical'=>desc($ov)];bump($result['savedToCanonical'],$f,$state);
     if(!in_array($state,['equal','both_missing','format_only','not_exposed_by_saved_reader'],true)){$hasDiff=true;$seenDiff[]=$f;}
     if($rawP!==null){$rs=compare(at($rawP,$f),$sv);$pair['rawToSaved'][$f]=$rs;bump($result['rawToSavedDto'],$f,$rs);}
    }
    if($norm!==null){
     $map=['hotel_id'=>'hotel_id','name'=>'name','country_id'=>'country_id','region_id'=>'region_id','subregion_id'=>'subregion_id','category'=>'category','rating'=>'rating','hotel_type'=>'hotel_type','description'=>'description','address'=>'address','place'=>'place','phone'=>'phone','site'=>'site','build'=>'build_info','repair'=>'repair_info','square'=>'square_info','latitude'=>'latitude','longitude'=>'longitude','primary_image_url'=>'primary_image_url','images_json'=>'images_json','infrastructure_json'=>'infrastructure_json','services_json'=>'services_json','meals_json'=>'meals_json','room_types'=>'room_types'];
     foreach($map as $n=>$column){$nv=$norm[$n]??null;$dv=$detail[$column]??null;if(str_ends_with($n,'_json')){try{$nv=$nv?json_decode($nv,true,512,JSON_THROW_ON_ERROR):null;$dv=$dv?json_decode($dv,true,512,JSON_THROW_ON_ERROR):null;}catch(Throwable){$pair['rawToStored'][$n]='invalid_json';bump($result['rawToStored'],$n,'invalid_json');continue;}}
      elseif(in_array($n,['hotel_id','country_id','region_id','subregion_id','category','rating','hotel_type','latitude','longitude'],true)&&is_numeric($dv))$dv=$dv+0;
      $state=compare($nv,$dv);$pair['rawToStored'][$n]=$state;bump($result['rawToStored'],$n,$state);
     }
     $pair['rawImageCount']=is_array($raw['images']??null)?count($raw['images']):0;$pair['normalizedImageCount']=count($norm['images']);if($pair['rawImageCount']>100)++$largeGallery;
    }
    foreach(['infrastructure','services'] as $f){if(is_array($s[$f]??null)&&$s[$f]!==[]&&!array_is_list($s[$f])&&($facts['hotelInformation.'.$f]??null)===null){$objectDrop[$f]=($objectDrop[$f]??0)+1;$pair['existingEnrichmentRejectsObject'][]=$f;}}
    try{if(dig(AnyTourCanonicalCatalog::initialProfile($seed))===dig($profile))$seedExact=true;}catch(Throwable){}
    $pair['state']='compared';$entry['pairs'][]=$pair;
    $interesting=(bool)array_diff($seenDiff,array_keys($sampleFieldSeen));$pilot=$own>=4349&&$own<=4366;
    if(count($samples)<20&&!isset($sampleIds[$own])&&($interesting||$pilot||$own===1||$own===1379)){
     $sampleIds[$own]=true;foreach($seenDiff as $f)$sampleFieldSeen[$f]=true;
     $safe=['anytourHotelId'=>$own,'legacyHotelId'=>$local,'name'=>$entry['name'],'revision'=>(int)$h['revision'],'profileSha256'=>$h['profile_sha256'],'detailsFetchedAt'=>$s['detailsFetchedAt'],'rawStatus'=>$rawState,'differences'=>[],'canonical'=>[],'saved'=>[]];
     foreach(FIELDS as $f){$sv=at($sp,$f);$ov=at($profile,$f);$safe['canonical'][$f]=$ov;$safe['saved'][$f]=$sv;if(in_array($f,$seenDiff,true))$safe['differences'][$f]=['state'=>$pair['fields'][$f]['state'],'saved'=>cleanSample($sv),'local'=>cleanSample($ov)];}
     $samples[]=$safe;
    }
   }
   $entry['state']=$hasComparable?'compared':'unavailable';$entry['originEvidence']=$seedExact?'matches_retained_seed_projection':($receiptExact?'matches_verified_enrichment_receipt':'not_proven_from_retained_evidence');
   bump($result['provenance'],'profile',$entry['originEvidence']);if($hasComparable)++$result['comparableProfiles'];if($hasDiff)$diffProfiles[$own]=true;elseif($hasComparable)++$allEqual;
   fwrite($rowsHandle,js($entry)."\n");
  }
 }
 need($result['scannedProfiles']===$result['activeProfiles'],'DENOMINATOR');need($pdo->inTransaction(),'SNAPSHOT_TRANSACTION');$pdo->rollBack();fclose($rowsHandle);$rowsHandle=null;
 $result['rawValidPairs']=$rawValid;$result['profilesWithAnyRecordedDifference']=count($diffProfiles);$result['profilesWithoutRecordedDifference']=$allEqual;$result['existingEnrichmentDropsObjects']=$objectDrop;$result['rawGalleriesOver100']=$largeGallery;$result['rawTopKeys']=$rawKeys;$result['rawCommonKeys']=$commonKeys;
 $result['examples']=array_map(static function($s){unset($s['canonical'],$s['saved']);return $s;},$samples);
 file_put_contents($dir.'/samples.json',js(['basis'=>'saved-source-not-live-TV','samples'=>$samples])."\n");
 $result['hotelRowsSha256']=hash_file('sha256',$dir.'/hotels.jsonl');$result['samplesSha256']=hash_file('sha256',$dir.'/samples.json');$result['state']='completed_read_only';$result['completeActiveProfileScan']=true;$result['finishedAt']=gmdate('c');
}catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();if(is_resource($rowsHandle))fclose($rowsHandle);$result['state']='incomplete';$result['completeActiveProfileScan']=false;$result['errorClass']=get_class($e);$result['errorCode']=preg_match('/^HC1_[A-Z0-9_]+$/D',$e->getMessage())?$e->getMessage():'AUDIT_EXCEPTION';}
file_put_contents($dir.'/result.json',js($result)."\n");file_put_contents($dir.'/receipt.json',js(['operation'=>OP,'state'=>$result['state'],'resultSha256'=>hash_file('sha256',$dir.'/result.json'),'databaseWrites'=>0,'supplierCalls'=>0])."\n");
echo js(['state'=>$result['state'],'scanned'=>$result['scannedProfiles'],'databaseWrites'=>0,'supplierCalls'=>0])."\n";exit($result['state']==='completed_read_only'?0:1);
