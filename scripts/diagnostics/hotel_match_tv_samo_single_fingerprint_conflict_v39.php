<?php
declare(strict_types=1);

const V39_OP='hotel-match-tv-samo-single-fingerprint-conflict-audit-1971-20260925-v39';
const V39_V38_OP='hotel-match-tv-samo-single-fingerprint-corroboration-1971-20260925-v38';

function v39_need(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function v39_json(mixed $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
function v39_load(string $p):array{$v=json_decode((string)file_get_contents($p),true,512,JSON_THROW_ON_ERROR);v39_need(is_array($v),'json_shape');return$v;}
function v39_save(string $p,array $v):string{$raw=v39_json($v)."\n";$f=@fopen($p,'x+b');v39_need($f!==false,'exclusive_create');try{v39_need(fwrite($f,$raw)===strlen($raw)&&fflush($f),'write');if(function_exists('fsync'))v39_need(fsync($f),'sync');}finally{fclose($f);}return hash('sha256',$raw);}
function v39_query(PDO $db,string $sql,array $args=[]):array{$st=$db->prepare($sql);$st->execute(array_values($args));return$st->fetchAll(PDO::FETCH_ASSOC)?:[];}
function v39_sha(mixed $v):bool{return is_string($v)&&preg_match('/^[0-9a-f]{64}$/D',$v)===1;}
function v39_norm(mixed $v):string{$s=mb_strtolower(trim((string)$v),'UTF-8');$s=str_replace('ё','е',$s);$s=strtr($s,['é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);$s=preg_replace('/[^\p{L}\p{N}]+/u',' ',$s)??$s;return trim(preg_replace('/\s+/u',' ',$s)??$s);}
function v39_name_key(mixed $v):string{$generic=['hotel'=>1,'hotels'=>1,'otel'=>1,'отель'=>1,'отели'=>1,'resort'=>1,'resorts'=>1,'spa'=>1,'the'=>1,'and'=>1,'by'=>1,'резорт'=>1,'ресорт'=>1,'спа'=>1];$p=[];foreach(preg_split('/\s+/u',v39_norm($v),-1,PREG_SPLIT_NO_EMPTY)?:[] as $x)if(!isset($generic[$x]))$p[$x]=true;$k=array_keys($p);sort($k,SORT_STRING);return implode(' ',$k);}
function v39_core_tokens(mixed $v):array{$drop=['adults'=>1,'adult'=>1,'only'=>1,'16'=>1,'18'=>1,'plus'=>1,'ex'=>1,'former'=>1,'wb'=>1,'travel'=>1,'adults-only'=>1];$k=v39_name_key($v);$out=[];foreach(preg_split('/\s+/u',$k,-1,PREG_SPLIT_NO_EMPTY)?:[] as $x)if(!isset($drop[$x]))$out[$x]=true;$a=array_keys($out);sort($a,SORT_STRING);return$a;}
function v39_place_key(mixed $v):string{$n=v39_norm($v);$n=preg_replace('/\\b(?:центр|center|centre|город|city|остров|island)\\b/u',' ',$n)??$n;$n=trim(preg_replace('/\\s+/u',' ',$n)??$n);$groups=['el gouna'=>['el gouna','эль гуна','эль-гуна'],'kemer'=>['kemer','кемер'],'side'=>['side','сиде'],'belek'=>['belek','белек'],'fethiye'=>['fethiye','фетхие'],'alanya'=>['alanya','аланья'],'antalya'=>['antalya','анталья'],'hurghada'=>['hurghada','хургада'],'sharm el sheikh'=>['sharm el sheikh','шарм эль шейх','шарм-эль-шейх']];foreach($groups as$k=>$vals)foreach($vals as$x)if($n===v39_norm($x))return$k;return$n;}
function v39_country(mixed $v):?string{$n=v39_norm($v);if($n==='')return null;$groups=['egypt'=>['egypt','египет'],'thailand'=>['thailand','таиланд','тайланд'],'turkey'=>['turkey','turkiye','türkiye','турция'],'maldives'=>['maldives','мальдивы'],'uae'=>['united arab emirates','uae','оаэ','эмираты'],'cuba'=>['cuba','куба'],'sri_lanka'=>['sri lanka','шри ланка'],'vietnam'=>['vietnam','viet nam','вьетнам'],'qatar'=>['qatar','катар'],'china'=>['china','китай'],'mauritius'=>['mauritius','маврикий'],'seychelles'=>['seychelles','сейшелы'],'morocco'=>['morocco','марокко'],'tunisia'=>['tunisia','тунис'],'tanzania'=>['tanzania','танзания'],'uzbekistan'=>['uzbekistan','узбекистан'],'philippines'=>['philippines','филиппины'],'india'=>['india','индия'],'indonesia'=>['indonesia','индонезия'],'greece'=>['greece','греция'],'cyprus'=>['cyprus','кипр'],'georgia'=>['georgia','грузия'],'armenia'=>['armenia','армения']];foreach($groups as$k=>$vals)foreach($vals as$x)if($n===v39_norm($x))return$k;return$n;}
function v39_side(array $h,array $aliases,array $src):array{
  $nameKeys=[];$coreSets=[];
  foreach(array_merge([(string)($h['name']??''),(string)($h['normalized_name']??'')],$aliases) as$n){$k=v39_name_key($n);if($k!=='')$nameKeys[$k]=true;$ct=v39_core_tokens($n);if($ct)$coreSets[implode(' ',$ct)]=$ct;}
  $strict=(bool)array_intersect_key(array_fill_keys($src['source_name_keys']??[],true),$nameKeys);
  $srcCore=[];foreach($src['source_name_keys']??[] as$n){$t=v39_core_tokens($n);if($t)$srcCore[implode(' ',$t)]=$t;}
  $best=0.0;$coreExact=false;
  foreach($srcCore as$a)foreach($coreSets as$b){$ia=array_intersect($a,$b);$u=array_unique(array_merge($a,$b));$j=$u?count($ia)/count($u):0.0;$best=max($best,$j);if($a===$b)$coreExact=true;}
  $country=v39_country($h['country_name']??'');$countryOk=$country!==null&&in_array($country,$src['source_country_keys']??[],true);
  $places=[];foreach([(string)($h['region_name']??''),(string)($h['subregion_name']??'')] as$p){$k=v39_place_key($p);if($k!=='')$places[$k]=true;}
  $geo=(bool)array_intersect(array_keys($places),$src['source_place_keys']??[]);
  $nameSupported=$strict||$coreExact||$best>=0.80;
  return['local_hotel_id'=>(int)$h['id'],'name'=>(string)$h['name'],'country'=>(string)$h['country_name'],'region'=>(string)$h['region_name'],'subregion'=>(string)$h['subregion_name'],'strict_name_match'=>$strict,'core_name_exact'=>$coreExact,'name_token_jaccard'=>round($best,3),'country_match'=>$countryOk,'geo_match'=>$geo,'supported'=>$nameSupported&&$countryOk&&$geo];
}
function v39_evidence_ok(array $rows):bool{foreach($rows as$r){if(($r['decision_status']??'')!=='accepted')continue;$raw=(string)($r['evidence_json']??'');$eh=(string)($r['evidence_sha256']??'');$ch=(string)($r['catalog_sha256']??'');if(!v39_sha($eh)||!v39_sha($ch)||hash('sha256',$raw)!==$eh)return false;}return true;}
function v39_execute(PDO $db,string $v38Result,string $v38Receipt,string $sourceSha):array{
  $raw=(string)file_get_contents($v38Result);$sha=hash('sha256',$raw);$r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$q=v39_load($v38Receipt);
  v39_need(is_array($r)&&($r['operation']??'')===V39_V38_OP&&($r['state']??'')==='completed_read_only_single_fingerprint_corroboration','v38_state');
  v39_need(($q['result_sha256']??'')===$sha&&($q['readback_verified']??false)===true,'v38_receipt');
  v39_need((int)($r['input_candidate_count']??0)===10&&($r['status_counts']??null)===['hold_source_occupied'=>10],'v38_scope');
  $in=[];foreach($r['rows']??[] as$x){if(!is_array($x))continue;$cid=(string)($x['andromeda_catalog_id']??'');$candidate=(int)($x['local_hotel_id']??0);v39_need($cid!==''&&$candidate>0,'input_row');$in[$cid]=['andromeda_catalog_id'=>$cid,'candidate_local_hotel_id'=>$candidate,'v37_status'=>(string)($x['v37_status']??''),'frontier_bucket'=>(string)($x['frontier_bucket']??''),'source_name_keys'=>$x['source_name_keys']??[],'source_country_keys'=>$x['source_country_keys']??[],'source_place_keys'=>$x['source_place_keys']??[]];}
  v39_need(count($in)===10,'input_count');
  $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION READ ONLY');
  try{
    $ident=v39_query($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' ORDER BY external_hotel_id,local_hotel_id");
    $srcRows=[];$targetIds=[];foreach($ident as$z){$cid=(string)$z['external_hotel_id'];if(isset($in[$cid])){$srcRows[$cid][]=$z;if(($z['decision_status']??'')==='accepted'&&$z['local_hotel_id']!==null)$targetIds[(int)$z['local_hotel_id']]=true;}}
    foreach($in as$x)$targetIds[$x['candidate_local_hotel_id']]=true;
    $ids=array_keys($targetIds);sort($ids,SORT_NUMERIC);$ph=implode(',',array_fill(0,count($ids),'?'));
    $hotels=[];foreach(v39_query($db,"SELECT id,name,normalized_name,country_name,region_name,subregion_name,is_active FROM catalog_hotels WHERE id IN ($ph)",$ids) as$h)$hotels[(int)$h['id']]=$h;
    $aliases=[];foreach(v39_query($db,"SELECT hotel_id,alias,normalized_alias FROM hotel_aliases WHERE hotel_id IN ($ph) ORDER BY hotel_id,id",$ids) as$a){$id=(int)$a['hotel_id'];foreach([$a['alias'],$a['normalized_alias']] as$n)if(trim((string)$n)!=='')$aliases[$id][]=(string)$n;}
    $db->rollBack();
    $rows=[];$counts=[];
    foreach($in as$cid=>$x){$accepted=array_values(array_filter($srcRows[$cid]??[],fn($z)=>($z['decision_status']??'')==='accepted'&&$z['local_hotel_id']!==null));$currentIds=array_values(array_unique(array_map(fn($z)=>(int)$z['local_hotel_id'],$accepted)));sort($currentIds,SORT_NUMERIC);$evOk=v39_evidence_ok($srcRows[$cid]??[]);
      $candidateSide=isset($hotels[$x['candidate_local_hotel_id']])?v39_side($hotels[$x['candidate_local_hotel_id']],$aliases[$x['candidate_local_hotel_id']]??[],$x):null;
      $currentSides=[];foreach($currentIds as$id)if(isset($hotels[$id]))$currentSides[]=v39_side($hotels[$id],$aliases[$id]??[],$x);
      $status='neither_supported';
      if(!$evOk||count($currentIds)!==1)$status='current_mapping_invalid_evidence';
      else{$cur=$currentSides[0]??null;$cok=(bool)($candidateSide['supported']??false);$uok=(bool)($cur['supported']??false);if($uok&&!$cok)$status='current_supported';elseif($cok&&!$uok)$status='fingerprint_candidate_supported';elseif($cok&&$uok)$status='both_plausible';else$status='neither_supported';}
      $counts[$status]=($counts[$status]??0)+1;
      $rows[]=$x+['status'=>$status,'current_accepted_local_ids'=>$currentIds,'accepted_evidence_hashes_valid'=>$evOk,'candidate'=>$candidateSide,'current'=>$currentSides,'safe_to_write_now'=>false];
    }
    ksort($counts);usort($rows,fn($a,$b)=>[$a['status'],$a['candidate_local_hotel_id'],$a['andromeda_catalog_id']]<=>[$b['status'],$b['candidate_local_hotel_id'],$b['andromeda_catalog_id']]);
    return['operation'=>V39_OP,'state'=>'completed_read_only_single_fingerprint_conflict_audit','generated_at_utc'=>gmdate('c'),'source_sha'=>$sourceSha,'v38_result_sha256'=>$sha,'input_count'=>10,'status_counts'=>$counts,'rows'=>$rows,'provider_http_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,'anex_calls'=>0,'andromeda_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
  }catch(Throwable$e){if($db->inTransaction())$db->rollBack();throw$e;}
}
function v39_self_test():void{$src=['source_name_keys'=>['corners ocean three view'],'source_country_keys'=>['egypt'],'source_place_keys'=>['el gouna']];$h=['id'=>1,'name'=>'THE THREE CORNERS OCEAN VIEW ADULTS ONLY 16+','normalized_name'=>'','country_name'=>'Египет','region_name'=>'Эль Гуна','subregion_name'=>''];$x=v39_side($h,[],$src);v39_need($x['supported']===true,'supported');v39_need(v39_core_tokens('WB TRAVEL ANITA MATIATE')===['anita','matiate'],'qualifiers');}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){if(in_array('--self-test',$argv??[],true)){v39_self_test();echo"MATCH_TV_SAMO_SINGLE_FINGERPRINT_CONFLICT_V39_SELFTEST_OK\n";exit;}v39_need(($argv[1]??'')==='--execute','disabled');$root=(string)getenv('ANYTOUR_ROOT');$dir=(string)getenv('MATCH_OPERATION_DIR');$v38=(string)getenv('MATCH_V38_RESULT');$receipt=(string)getenv('MATCH_V38_RECEIPT');$sha=(string)getenv('MATCH_SOURCE_SHA');v39_need(is_dir($root)&&is_dir($dir)&&basename($dir)===V39_OP&&is_file($v38)&&is_file($receipt)&&preg_match('/^[0-9a-f]{40}$/D',$sha)===1,'runtime_scope');$res=v39_load($dir.'/reservation.json');v39_need(($res['operation']??'')===V39_OP,'reservation');require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');try{$r=v39_execute(v2_data_db(),$v38,$receipt,$sha);$h=v39_save($dir.'/result.json',$r);v39_save($dir.'/receipt.json',['operation'=>V39_OP,'state'=>$r['state'],'result_sha256'=>$h,'readback_verified'=>hash_file('sha256',$dir.'/result.json')===$h,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);echo v39_json(['state'=>$r['state'],'status_counts'=>$r['status_counts'],'rows'=>$r['rows']])."\n";}catch(Throwable$e){$f=['operation'=>V39_OP,'state'=>'failed_read_only_single_fingerprint_conflict_audit','reason'=>preg_replace('/[^A-Za-z0-9_.:-]+/','_',mb_substr($e->getMessage(),0,160,'UTF-8')),'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0];$h=v39_save($dir.'/result.json',$f);v39_save($dir.'/receipt.json',['operation'=>V39_OP,'state'=>$f['state'],'result_sha256'=>$h,'readback_verified'=>true,'provider_accessed'=>false,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);fwrite(STDERR,$f['reason']."\n");exit(2);}}
