<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/diagnostics/hotel_match_pending8_transition_v76.php';
$n=0;
function ck(bool $v,string $m):void{global$n;$n++;w76_need($v,'test_'.$m);}
$entries=w76_prepare($argv[1]??'');ck(count($entries)===8,'input8');
$proofIds=[];foreach($entries as$id=>$e)if($e['proofs'])$proofIds[]=$id;
ck($proofIds===[55945,56479,76753,116886,121109],'exact_five_independent');
$projection=$entries[55945]['history']['source'];$rawSource=array_reverse($projection,true);$rawSource['provider_metadata_not_exported']='retained';
ck($rawSource!==$projection,'old_projection_comparison_reproduced');
ck(w76_source_projection_matches($rawSource,$projection),'projection_order_omissions');
$changed=$rawSource;$changed['name']='Different hotel';ck(!w76_source_projection_matches($changed,$projection),'projection_value_drift');
$missing=$rawSource;unset($missing['town']);ck(!w76_source_projection_matches($missing,$projection),'projection_missing_field');
ck(!w76_source_projection_matches($rawSource,[]),'empty_projection');
ck(count($entries[121109]['proofs'])===2,'two_operators');
function fixture(array $entries):array{
 $c=['sources'=>[],'targets'=>[],'operators'=>[],'hotels'=>[],'manual'=>[],'exclusions'=>[],'live'=>[],'effective'=>['by_local'=>[]]];
 foreach($entries as$id=>&$e){
  $ev=$e['history'];$ev['source']=array_reverse($ev['source'],true);$ev['source']['provider_metadata_not_exported']='retained-fixture';$ev['candidate_ids']=[];$ev['target_name']='';$raw=w76_json($ev);$hash=hash('sha256',$raw);$e['prior']['evidence_sha256']=$hash;
  $row=['supplier_namespace'=>'andromeda_catalog','external_hotel_id'=>$e['catalog_id'],'local_hotel_id'=>null,'decision_status'=>'pending','catalog_sha256'=>$e['prior']['catalog_sha256'],'evidence_sha256'=>$hash,'evidence_json'=>$raw];
  $c['sources'][$e['catalog_id']]=[$row];$c['hotels'][$id]=$e['target'];$c['live'][$id]=true;
  foreach($e['direct_anex_ids']as$x)$c['effective']['by_local'][$id][(int)$x]=true;
 }unset($e);return[$entries,$c];
}
[$f,$c]=fixture($entries);
foreach($f as$id=>$e)ck(w76_classify($e,$c)['status']===($e['proofs']&&(int)$e['history']['source']['starKey']===(int)$e['target']['category']?'ready_pending_transition':'hold'),'base_'.$id);
$id=55945;$cat=W76_PAIRS[$id];
foreach(['occupied','status','evidence','catalog','target','live','manual','excluded','anchor','country','star','town','unknown_prior','manual_origin','history_candidates','source_projection_drift','operator_conflict','operator_bad_hash']as$case){
 $g=$c;$e=$f[$id];
 switch($case){
 case'occupied':$g['sources'][$cat][0]['local_hotel_id']=999;break;
 case'status':$g['sources'][$cat][0]['decision_status']='rejected';break;
 case'evidence':$g['sources'][$cat][0]['evidence_json'].=' ';break;
 case'catalog':$g['sources'][$cat][0]['catalog_sha256']=str_repeat('a',64);break;
 case'target':$g['hotels'][$id]['name']='Another hotel';break;
 case'live':unset($g['live'][$id]);break;
 case'manual':$g['manual'][$id]=true;break;
 case'excluded':$g['exclusions'][$id]=true;break;
 case'anchor':$g['effective']['by_local'][$id]=[123456=>true];break;
 case'country':$g['hotels'][$id]['country_name']='Россия';break;
 case'star':$g['hotels'][$id]['category']='1';break;
 case'town':$g['hotels'][$id]['region_name']='Different';$g['hotels'][$id]['subregion_name']=null;break;
 case'unknown_prior':case'manual_origin':case'history_candidates':case'source_projection_drift':
  $ev=json_decode($g['sources'][$cat][0]['evidence_json'],true);
  if($case==='source_projection_drift')$ev['source']['name']='Other source';elseif($case==='unknown_prior')$ev['manual_review']='reject';elseif($case==='manual_origin')$ev['reason']='manual_rejection';else$ev['candidate_ids']=[999];
  $raw=w76_json($ev);$g['sources'][$cat][0]['evidence_json']=$raw;$g['sources'][$cat][0]['evidence_sha256']=hash('sha256',$raw);$e['prior']['evidence_sha256']=hash('sha256',$raw);break;
 case'operator_conflict':case'operator_bad_hash':
  $raw='{}';$g['operators']['operator_315']['295561']=[['decision_status'=>'accepted','local_hotel_id'=>$case==='operator_conflict'?999:$id,'evidence_json'=>$raw,'evidence_sha256'=>$case==='operator_bad_hash'?str_repeat('f',64):hash('sha256',$raw)]];break;
 }
 ck(w76_classify($e,$g)['status']==='hold',$case);
}
$g=$c;$g['sources'][$cat][0]['local_hotel_id']=$id;$g['sources'][$cat][0]['decision_status']='accepted';ck(w76_classify($f[$id],$g)['status']==='already_accepted_same','no_duplicate');
ck(w76_classify($f[4326],$c)['reasons']===['no_independent_same_operator_proof'],'no_name_acceptance');
// Pure post-COMMIT verifier: exact history retained; unrelated rows cannot drift.
$old=$c['sources'][$cat][0];$key='andromeda_catalog|'.$cat;$ev=['prior_evidence_json'=>$old['evidence_json'],'prior_evidence_sha256'=>$old['evidence_sha256']];$raw=w76_json($ev);
$p=['id'=>$id,'cat'=>$cat,'name'=>$f[$id]['target']['name'],'new_json'=>$raw,'new_sha'=>hash('sha256',$raw),'proof_count'=>1];$post=$old;$post['local_hotel_id']=$id;$post['decision_status']='accepted';$post['evidence_json']=$raw;$post['evidence_sha256']=$p['new_sha'];
ck(count(w76_verify([$key=>$old],[$key=>$p],[$post]))===1,'readback_history');
$bad=$post;$bad['catalog_sha256']=str_repeat('0',64);try{w76_verify([$key=>$old],[$key=>$p],[$bad]);ck(false,'catalog_not_preserved');}catch(RuntimeException $e){ck($e->getMessage()==='original_field_changed','catalog_preserved');}
echo 'W76_PURE_PASS '.$n."\n";
$dsn=getenv('MATCH_WRITER_TEST_DSN');if(!$dsn)exit;
ck(str_starts_with($dsn,'mysql:host=127.0.0.1;')&&str_contains($dsn,'dbname=match_writer_test;'),'fixture_dsn');
$db=new PDO($dsn,'root','fixture-only-76',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function resetdb(PDO $db,array $entries):array{
 foreach(['andromeda_hotel_identities','catalog_hotels','tour_operator_identity_observations','anex_hotel_search_mappings','anex_hotel_decisions','anex_review_pair_exclusions']as$t)$db->exec('DROP TABLE IF EXISTS '.$t);
 $db->exec('CREATE TABLE catalog_hotels (id INT PRIMARY KEY,name VARCHAR(250),country_id INT,country_name VARCHAR(80),region_name VARCHAR(80),subregion_name VARCHAR(80) NULL,category INT,is_active INT,latitude VARCHAR(30) NULL,longitude VARCHAR(30) NULL) ENGINE=InnoDB');
 $db->exec('CREATE TABLE andromeda_hotel_identities (supplier_namespace VARCHAR(40),external_hotel_id VARCHAR(32),local_hotel_id INT NULL,decision_status VARCHAR(32),catalog_sha256 CHAR(64),evidence_sha256 CHAR(64),evidence_json LONGTEXT,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(supplier_namespace,external_hotel_id)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE tour_operator_identity_observations (hotel_id INT PRIMARY KEY,last_seen_at DATETIME) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_hotel_search_mappings (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT,match_class VARCHAR(80),approval_policy VARCHAR(90),enabled INT,scope VARCHAR(30)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_hotel_decisions (anex_hotel_id INT PRIMARY KEY,catalog_hotel_id INT NULL,decision_status VARCHAR(32)) ENGINE=InnoDB');
 $db->exec('CREATE TABLE anex_review_pair_exclusions (anex_hotel_id INT,catalog_hotel_id INT,PRIMARY KEY(anex_hotel_id,catalog_hotel_id)) ENGINE=InnoDB');
 [$entries,$c]=fixture($entries);
 foreach($entries as$id=>$e){$h=$c['hotels'][$id];$q=$db->prepare('INSERT INTO catalog_hotels VALUES (?,?,?,?,?,?,?,?,?,?)');$q->execute(array_values($h));$db->exec("INSERT INTO tour_operator_identity_observations VALUES ($id,UTC_TIMESTAMP())");
  $s=$c['sources'][$e['catalog_id']][0];$q=$db->prepare('INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES (?,?,?,?,?,?,?)');$q->execute(array_values($s));
  foreach($e['direct_anex_ids']as$nat)$db->prepare("INSERT INTO anex_hotel_search_mappings VALUES (?,?,'strong_candidate','owner_exact_and_strong_20260908',1,'preview')")->execute([$nat,$id]);
 }
 $raw='{}';$db->prepare("INSERT INTO andromeda_hotel_identities(supplier_namespace,external_hotel_id,local_hotel_id,decision_status,catalog_sha256,evidence_sha256,evidence_json) VALUES('operator_315','999999',999,'accepted',?,?,?)")->execute([str_repeat('a',64),hash('sha256',$raw),$raw]);
 return$entries;
}
function opdir(string $tag):string{$parent=sys_get_temp_dir().'/w76-test-'.$tag.'-'.bin2hex(random_bytes(4));$p=$parent.'/'.W76_OP;mkdir($p,0700,true);return$p;}
function snap(PDO $db):array{return w76_index(w76_q($db,'SELECT * FROM andromeda_hotel_identities ORDER BY supplier_namespace,external_hotel_id'));}
$head=str_repeat('a',40);
// Real writer, last-row SQL failure must roll back earlier pending transitions.
$f=resetdb($db,$entries);$before=snap($db);
$db->exec("CREATE TRIGGER reject_last BEFORE UPDATE ON andromeda_hotel_identities FOR EACH ROW BEGIN IF NEW.local_hotel_id=121109 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture_late_error'; END IF; END");
$out=w76_execute($db,$f,$head,opdir('rollback'));ck($out['state']==='rolled_back_no_writes','late_rollback');ck(w76_hash(snap($db))===w76_hash($before),'all_unchanged_on_error');
$f=resetdb($db,$entries);$before=snap($db);$out=w76_execute($db,$f,$head,opdir('success'));ck($out['state']==='committed_readback_verified','commit');ck($out['transitioned']===4&&count($out['held'])===4,'four_and_four');ck($out['coverage_before']['full_triple']===0&&$out['coverage_after']['full_triple']===4,'census_delta');ck($out['prior_evidence_preserved']&&$out['unrelated_identities_unchanged'],'preserved');
$after=snap($db);foreach($out['rows']as$r){$k='andromeda_catalog|'.$r['catalog_id'];$e=json_decode($after[$k]['evidence_json'],true);ck($e['prior_evidence_json']===$before[$k]['evidence_json'],'exact_history_'.$r['local_hotel_id']);ck($after[$k]['catalog_sha256']===$before[$k]['catalog_sha256'],'catalog_'.$r['local_hotel_id']);}
$out=w76_execute($db,$f,$head,opdir('already'));ck($out['state']==='completed_no_new_writes'&&count($out['already'])===4&&count($out['held'])===4,'already_noop');ck(w76_hash($after)===w76_hash(snap($db)),'noop_unchanged');
$f=resetdb($db,$entries);$db->exec("UPDATE andromeda_hotel_identities SET local_hotel_id=999,decision_status='accepted' WHERE external_hotel_id='2000037585'");$before=snap($db);$out=w76_execute($db,$f,$head,opdir('conflict'));ck($out['transitioned']===3&&count($out['held'])===5,'conflict_not_block_other_three');ck(w76_hash(snap($db)['andromeda_catalog|2000037585'])===w76_hash($before['andromeda_catalog|2000037585']),'other_target_preserved');
$f=resetdb($db,$entries);$db->exec("INSERT INTO anex_hotel_decisions VALUES(16014,55945,'accepted')");$out=w76_execute($db,$f,$head,opdir('manual'));ck($out['transitioned']===3,'manual_never_overwritten');
$f=resetdb($db,$entries);$db->exec("UPDATE catalog_hotels SET name='Wrong Hotel' WHERE id=116886");$out=w76_execute($db,$f,$head,opdir('drift'));ck($out['transitioned']===3,'target_drift');
$f=resetdb($db,$entries);$db->exec('ALTER TABLE andromeda_hotel_identities ENGINE=MyISAM');$out=w76_execute($db,$f,$head,opdir('engine'));ck($out['database_writes']===0&&$out['reason']==='nontransactional_table','engine_guard');
echo 'W76_MYSQL_TRANSACTION_PASS '.$n."\n";
