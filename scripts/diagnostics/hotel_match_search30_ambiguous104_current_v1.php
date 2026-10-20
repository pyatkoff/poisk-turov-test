<?php
declare(strict_types=1);
const OP='hotel-match-search30-ambiguous104-current-1971-20260921-v1';
const NS=[25=>'operator_315',43=>'operator_342'];

function need(bool $x,string $r):void{if(!$x)throw new RuntimeException($r);}
function rows(PDO $db,string $s,array $p=[]):array{$q=$db->prepare($s);$q->execute(array_values($p));return $q->fetchAll(PDO::FETCH_ASSOC)?:[];}
function wr(string $p,array $v):string{$b=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";$f=fopen($p,'xb');need(is_resource($f),'open');need(fwrite($f,$b)===strlen($b),'write');fflush($f);if(function_exists('fsync'))fsync($f);fclose($f);return hash('sha256',$b);}
function hm_pos($v):?string{$s=(string)$v;return preg_match('/^[1-9][0-9]{0,18}$/D',$s)?$s:null;}
function uniqRows(array $xs):array{$out=[];foreach($xs as$r){$k=hash('sha256',json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));$out[$k]=$r;}return array_values($out);}

if(($argv[1]??'')==='--self-test'){
  need(hm_pos('123')==='123'&&hm_pos('0')===null&&NS[25]==='operator_315'&&NS[43]==='operator_342','self');
  echo "SEARCH30_AMBIGUOUS104_CURRENT_SELFTEST_OK\n";exit;
}
need(PHP_SAPI==='cli'&&($argv[1]??'')==='--execute','disabled');
$root=realpath((string)getenv('ANYTOUR_ROOT'));$inp=realpath((string)getenv('MATCH_INPUT_PATH'));$dir=(string)getenv('MATCH_OPERATION_DIR');
need(is_string($root)&&basename($root)==='anytoour.ru'&&is_string($inp)&&is_dir($dir)&&basename($dir)===OP,'runtime');
need(hash_file('sha256',$inp)==='76c740c4efbb95a2c2c44fd7fe69ecea30b5091c0e17dfec2bb4cf30da75cea2','input_hash');
$in=json_decode((string)file_get_contents($inp),true,256,JSON_THROW_ON_ERROR);
need(($in['operation']??'')==='hotel-match-residual2041-search30-common4-1971-20260921-v3','input_op');

$edges=[];
foreach((array)($in['edges']??[]) as $e){
  if(($e['link_state']??'')!=='captured_ambiguous_native')continue;
  $op=(int)($e['operator_id']??0);need(in_array($op,[13,25,43],true),'operator');
  $tok=array_values(array_unique(array_filter(array_map('hm_pos',(array)($e['positive_native_candidates']??[])))));
  need(count($tok)>=2,'ambiguous_tokens');
  $tv=(int)($e['tv_hotel_id']??0);need($tv>0,'tv');
  $key=$op.'|'.$tv.'|'.(string)($e['tour_id']??'');need(!isset($edges[$key]),'dup_edge');
  $edges[$key]=[
    'operator_id'=>$op,'operator'=>(string)($e['operator']??''),'tv_hotel_id'=>$tv,
    'native_candidates'=>$tok,'raw_identity_key'=>$e['raw_identity_key']??null,
    'raw_identity_tokens'=>$e['raw_identity_tokens']??[],'operator_link'=>$e['operator_link']??null,
    'operator_link_sha256'=>$e['operator_link_sha256']??null,'tour_id'=>(string)($e['tour_id']??''),
    'search_id'=>(string)($e['search_id']??''),'batch'=>(int)($e['batch']??0),
  ];
}
need(count($edges)===104,'input104');
$by=[];foreach($edges as$e)$by[$e['operator_id']]=($by[$e['operator_id']]??0)+1;
need(($by[13]??0)===89&&($by[25]??0)===3&&($by[43]??0)===12,'operator_counts');

require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try{
  $tv=array_values(array_unique(array_column($edges,'tv_hotel_id')));$hot=[];$occ=[];
  foreach(array_chunk($tv,400)as$c){
    $ph=implode(',',array_fill(0,count($c),'?'));
    foreach(rows($db,"SELECT id,name,country_id,country_name,region_name,subregion_name,category,is_active FROM catalog_hotels WHERE id IN ($ph)",$c)as$r)$hot[(int)$r['id']]=$r;
    foreach(rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,catalog_sha256 FROM andromeda_hotel_identities WHERE decision_status='accepted' AND local_hotel_id IN ($ph)",$c)as$r)$occ[(int)$r['local_hotel_id']][]=$r;
  }

  $provider=[];
  foreach(NS as$op=>$ns){
    $ids=[];foreach($edges as$e)if($e['operator_id']===$op)$ids=array_merge($ids,$e['native_candidates']);
    $ids=array_values(array_unique($ids));
    foreach(array_chunk($ids,400)as$c){
      $ph=implode(',',array_fill(0,count($c),'?'));$p=array_merge([$ns],$c);
      foreach(rows($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,catalog_sha256,match_evidence FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id IN ($ph)",$p)as$r)$provider[$ns.'|'.(string)$r['external_hotel_id']][]=$r;
    }
  }

  $anexIds=[];$anexTv=[];
  foreach($edges as$e)if($e['operator_id']===13){$anexIds=array_merge($anexIds,$e['native_candidates']);$anexTv[]=$e['tv_hotel_id'];}
  $anexIds=array_values(array_unique($anexIds));$anexTv=array_values(array_unique($anexTv));$maps=[];$dec=[];$exc=[];
  foreach(array_chunk($anexIds,300)as$c){
    $ph=implode(',',array_fill(0,count($c),'?'));
    foreach(rows($db,"SELECT * FROM anex_hotel_search_mappings WHERE anex_hotel_id IN ($ph)",$c)as$r)$maps['n|'.(string)$r['anex_hotel_id']][]=$r;
    foreach(rows($db,"SELECT * FROM anex_hotel_decisions WHERE anex_hotel_id IN ($ph)",$c)as$r)$dec['n|'.(string)$r['anex_hotel_id']][]=$r;
    foreach(rows($db,"SELECT * FROM anex_review_pair_exclusions WHERE anex_hotel_id IN ($ph)",$c)as$r)$exc['n|'.(string)$r['anex_hotel_id']][]=$r;
  }
  foreach(array_chunk($anexTv,300)as$c){
    $ph=implode(',',array_fill(0,count($c),'?'));
    foreach(rows($db,"SELECT * FROM anex_hotel_search_mappings WHERE catalog_hotel_id IN ($ph)",$c)as$r)$maps['t|'.(int)$r['catalog_hotel_id']][]=$r;
    foreach(rows($db,"SELECT * FROM anex_hotel_decisions WHERE catalog_hotel_id IN ($ph)",$c)as$r)$dec['t|'.(int)$r['catalog_hotel_id']][]=$r;
    foreach(rows($db,"SELECT * FROM anex_review_pair_exclusions WHERE catalog_hotel_id IN ($ph)",$c)as$r)$exc['t|'.(int)$r['catalog_hotel_id']][]=$r;
  }
  $db->rollBack();

  $out=[];$counts=[];$byop=[];$tokenCounts=[];$uniqueCurrent=0;
  foreach($edges as$e){
    $op=$e['operator_id'];$tv=$e['tv_hotel_id'];$tokenRows=[];$sameTokens=[];$pendingTokens=[];$protected=false;
    foreach($e['native_candidates'] as$native){
      $status='';$cur=[];
      if($op===13){
        $nrows=uniqRows($maps['n|'.$native]??[]);
        $enabled=array_values(array_filter($nrows,fn($r)=>(int)($r['enabled']??1)===1));
        $same=array_values(array_filter($enabled,fn($r)=>(int)($r['catalog_hotel_id']??0)===$tv));
        $other=array_values(array_filter($enabled,fn($r)=>(int)($r['catalog_hotel_id']??0)>0&&(int)($r['catalog_hotel_id']??0)!==$tv));
        $pairDec=array_values(array_filter(uniqRows(array_merge($dec['n|'.$native]??[],$dec['t|'.$tv]??[])),fn($r)=>(string)($r['anex_hotel_id']??'')===$native&&(int)($r['catalog_hotel_id']??0)===$tv));
        $pairExc=array_values(array_filter(uniqRows(array_merge($exc['n|'.$native]??[],$exc['t|'.$tv]??[])),fn($r)=>(string)($r['anex_hotel_id']??'')===$native&&(int)($r['catalog_hotel_id']??0)===$tv));
        if($pairExc){$status='anex_pair_exclusion_present';$protected=true;}
        elseif($pairDec){$status='anex_pair_decision_present';$protected=true;}
        elseif(count($same)>=1&&count($other)===0){$status='anex_enabled_same_target';$sameTokens[]=$native;}
        elseif(count($same)>=1){$status='anex_enabled_same_and_other_targets';$protected=true;}
        elseif(count($other)>=1){$status='anex_enabled_other_target';$protected=true;}
        else $status='anex_no_enabled_mapping';
        $cur=['mapping_rows'=>$nrows,'pair_decisions'=>$pairDec,'pair_exclusions'=>$pairExc];
      }else{
        $ns=NS[$op];$rr=uniqRows($provider[$ns.'|'.$native]??[]);
        $acceptedSame=array_values(array_filter($rr,fn($r)=>(string)($r['decision_status']??'')==='accepted'&&(int)($r['local_hotel_id']??0)===$tv));
        $acceptedOther=array_values(array_filter($rr,fn($r)=>(string)($r['decision_status']??'')==='accepted'&&(int)($r['local_hotel_id']??0)>0&&(int)($r['local_hotel_id']??0)!==$tv));
        $pending=array_values(array_filter($rr,fn($r)=>(string)($r['decision_status']??'')==='pending'&&((int)($r['local_hotel_id']??0)===0||(int)($r['local_hotel_id']??0)===$tv)));
        if($acceptedSame){$status='provider_accepted_same_target';$sameTokens[]=$native;}
        elseif($acceptedOther){$status='provider_accepted_other_target';$protected=true;}
        elseif(count($pending)===1&&count($rr)===1){$status='provider_pending_same_or_unassigned';$pendingTokens[]=$native;}
        elseif(count($rr)===0)$status='provider_no_current_namespace_row';
        else{$status='provider_current_protected_or_multiple';$protected=true;}
        $cur=['provider_rows'=>$rr];
      }
      $tokenCounts[$status]=($tokenCounts[$status]??0)+1;
      $tokenRows[]=['native_hotel_id'=>$native,'classification'=>$status,'current'=>$cur];
    }

    if(count($sameTokens)===1){$cls=$op===13?'anex_unique_current_same_target_token':'provider_unique_current_same_target_token';$uniqueCurrent++;}
    elseif(count($sameTokens)>1)$cls=$op===13?'anex_multiple_current_same_target_tokens':'provider_multiple_current_same_target_tokens';
    elseif(count($pendingTokens)===1&&!$protected)$cls='provider_unique_pending_token_only';
    elseif($protected)$cls='protected_or_conflicting_ambiguous_tokens';
    else $cls='unresolved_ambiguous_tokens';

    $reasons=[];if(!isset($hot[$tv])||(int)($hot[$tv]['is_active']??0)!==1)$reasons[]='target_inactive_or_missing';
    $counts[$cls]=($counts[$cls]??0)+1;$byop[$op][$cls]=($byop[$op][$cls]??0)+1;
    $out[]=$e+[
      'classification'=>$cls,'current_unique_same_target_token'=>count($sameTokens)===1?$sameTokens[0]:null,
      'token_reviews'=>$tokenRows,'target'=>$hot[$tv]??null,'target_occupants'=>$occ[$tv]??[],
      'reasons'=>$reasons,'safe_to_write_now'=>false
    ];
  }
  ksort($counts);ksort($tokenCounts);foreach($byop as&$v)ksort($v);unset($v);
  $res=['operation'=>OP,'state'=>'completed_read_only','input_edges'=>104,'operator_counts'=>$by,'unique_tv_hotels'=>count($tv),
    'classification_counts'=>$counts,'operator_classification_counts'=>$byop,'token_classification_counts'=>$tokenCounts,
    'current_unique_same_target_edges'=>$uniqueCurrent,'rows'=>$out,'provider_calls'=>0,'tourvisor_calls'=>0,'samo_calls'=>0,
    'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
  $rh=wr($dir.'/result.json',$res);
  wr($dir.'/receipt.json',['operation'=>OP,'state'=>'completed_read_only','result_sha256'=>$rh,'provider_calls'=>0,'database_writes'=>0,'mapping_writes'=>0]);
  echo json_encode(['classification_counts'=>$counts,'operator_classification_counts'=>$byop,'token_classification_counts'=>$tokenCounts,'current_unique_same_target_edges'=>$uniqueCurrent,'result_sha256'=>$rh],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
