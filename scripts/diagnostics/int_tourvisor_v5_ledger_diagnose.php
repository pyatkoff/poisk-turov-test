<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') throw new RuntimeException('V5_DIAG_CLI_ONLY');
$site = isset($argv[1]) ? realpath((string)$argv[1]) : false;
$ops = isset($argv[2]) ? realpath((string)$argv[2]) : false;
$operation = $argv[3] ?? '';
if (!is_string($site) || $site === '' || !is_file($site.'/api-v2.php')) throw new RuntimeException('V5_DIAG_SITE');
if (!is_string($ops) || $ops === '' || !preg_match('/^[a-z0-9-]+$/D',$operation)) throw new RuntimeException('V5_DIAG_ARGS');
$ledger=$ops.'/'.$operation;
if(!is_dir($ledger))throw new RuntimeException('V5_DIAG_LEDGER');
$files=[];
foreach(['state','before.json','after.json','health.json','search-start.json','search-status.json','search-results.json','receipt.json','failure.json','api-v2.before.php'] as $name){
  $path=$ledger.'/'.$name;$files[$name]=['exists'=>is_file($path),'size'=>is_file($path)?filesize($path):null];
}
$searchId=0;
if(is_file($ledger.'/search-start.json')){$x=json_decode((string)file_get_contents($ledger.'/search-start.json'),true);if(is_array($x))$searchId=(int)($x['searchId']??($x['data']['searchId']??0));}
$stateDir=$site.'/.cache/tourvisor-offer-autosave';
$statePath=$searchId>0?$stateDir.'/anytour-tourvisor-offer-'.hash('sha256',(string)$searchId).'.json':'';
$state=null;
if($statePath!==''&&is_file($statePath)){$x=json_decode((string)file_get_contents($statePath),true);if(is_array($x))$state=['version'=>$x['version']??null,'search_id'=>$x['search_id']??null,'started_at'=>$x['started_at']??null,'terminal_at'=>$x['terminal_at']??null,'saved_at'=>$x['saved_at']??null];}
$_SERVER['DOCUMENT_ROOT']=$site;
require_once $site.'/data/db-v1.php';
$db=v2_data_db();
$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
try{$q=static fn(string $sql):int=>(int)$db->query($sql)->fetchColumn();$dbState=[
 'completed_refreshes'=>$q("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='tourvisor' AND status='completed'"),
 'running_refreshes'=>$q("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='tourvisor' AND status='running'"),
 'aborted_refreshes'=>$q("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider='tourvisor' AND status='aborted'"),
 'active_offers'=>$q("SELECT COUNT(*) FROM anytour_offers WHERE provider='tourvisor' AND is_active=1"),
 'ready_rub'=>$q("SELECT COUNT(*) FROM anytour_offers WHERE provider='tourvisor' AND is_active=1 AND final_price_ready=1 AND currency='RUB'"),
 'latest_scopes'=>$q("SELECT COUNT(*) FROM anytour_offer_scope_state WHERE provider='tourvisor' AND latest_complete_refresh_token IS NOT NULL")];$db->rollBack();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
$home=dirname(dirname($site));
$out=['schema_version'=>1,'operation'=>'int-tourvisor-v5-ledger-diagnose-20260917-v1','status'=>'completed_read_only','sealed_operation'=>$operation,'ledger_state'=>is_file($ledger.'/state')?trim((string)file_get_contents($ledger.'/state')):null,'files'=>$files,'search_id'=>$searchId,'state_dir_exists'=>is_dir($stateDir),'deny_guard_exists'=>is_file($stateDir.'/.htaccess'),'deny_guard_exact'=>is_file($stateDir.'/.htaccess')&&file_get_contents($stateDir.'/.htaccess')==="Require all denied\n",'state_path'=>$statePath,'state_exists'=>$statePath!==''&&is_file($statePath),'state'=>$state,'db'=>$dbState,'production_api_sha256'=>hash_file('sha256',$site.'/api-v2.php'),'sibling_app_exists'=>file_exists($home.'/app')||is_link($home.'/app'),'sibling_v2_exists'=>file_exists($home.'/v2')||is_link($home.'/v2'),'supplier_calls'=>0,'db_writes'=>0,'public_writes'=>0];
echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
