<?php
declare(strict_types=1);
require_once __DIR__.'/../scripts/catalog/anytour_hotel_stay_permission_proof_read_v1.php';
$source=(string)getenv('LOCAL_STAY_SOURCE_ROOT');if ($source===''||realpath($source)!==$source) throw new RuntimeException('Immutable SOURCE fixture required');
$server=(string)getenv('ANYTOUR_STAY_PERMISSION_PROOF_V1_TEST_DSN');
if (!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;charset=utf8mb4$/D',$server)) throw new RuntimeException('Dedicated loopback native MySQL fixture required');
$password=(string)getenv('ANYTOUR_STAY_OFFER_CANDIDATES_V2_TEST_PASSWORD');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_STRINGIFY_FETCHES=>false];
$root=new PDO($server,'root',$password,$options);$checks=0;
function proof_check(bool $ok,string $label): void {global $checks;++$checks;if (!$ok) throw new RuntimeException('PERMISSION_PROOF_CHECK_FAILED:'.$label);}
proof_check((int)$root->query('SELECT @@lower_case_table_names')->fetchColumn()===0&&(int)$root->query('SELECT @@global.partial_revokes')->fetchColumn()===0,'native positive fixture preconditions, no settings changed');
$name='stay_perm_proof_fixture_1';$root->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');$root->exec('CREATE TABLE `'.$name.'`.anytour_hotels (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');$root->exec('INSERT INTO `'.$name.'`.anytour_hotels VALUES (7)');
$escaped=str_replace('_','\\_',$name);$fixturePassword='native-proof-fixture-only';
function proof_account(string $user,string $grant='',string $class=PDO::class): PDO {
    global $root,$server,$name,$fixturePassword,$options;
    $quoted="'".str_replace("'","''",$user)."'@'%'";$root->exec('CREATE USER '.$quoted." IDENTIFIED BY '".$fixturePassword."'");
    if ($grant!=='') $root->exec($grant.' TO '.$quoted);
    return new $class(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),$user,$fixturePassword,$options);
}
$site=sys_get_temp_dir().'/anytour-permission-proof-'.bin2hex(random_bytes(8)).'/anytoour.ru';mkdir($site.'/data',0700,true);
copy($source.'/v2/data/db-v1.php',$site.'/data/db-v1.php');file_put_contents($site.'/config.php',"<?php // PRIVATE NATIVE FIXTURE CONFIG\n");
function proof_identity(PDO $db): string {$r=$db->query('SELECT DATABASE() AS selected_database,@@server_uuid AS server_uuid')->fetch();return hash('sha256',AnyTourHotelStayPermissionProofReadV1::json(['database'=>$r['selected_database'],'serverUuid'=>$r['server_uuid']]));}
function proof_parent(PDO $db): array {
    global $site;$files=AnyTourHotelStayPermissionProofReadV1::fingerprint($site);$identity=proof_identity($db);
    $data=['anchorEvidence'=>null,'sourceFacts'=>null,'currentCohorts'=>null,'draftConcepts'=>null,
        'privilegeProof'=>['CREATE'=>false,'REFERENCES'=>false,'SELECT'=>false,'TRIGGER'=>false,'allRequired'=>false,'scope'=>'direct_global_or_schema','state'=>'direct_metadata_observed'],
        'receipt'=>['schemaVersion'=>1,'source'=>'anytour-hotel-stay-source-facts-read-v1','state'=>'anchor_missing','operationId'=>AnyTourHotelStayPermissionProofReadV1::PARENT_OPERATION,
            'sourceSha'=>AnyTourHotelStayPermissionProofReadV1::SOURCE,'controlSha'=>AnyTourHotelStayPermissionProofReadV1::PARENT_CONTROL,
            'databaseSha256Before'=>$identity,'databaseSha256After'=>$identity,'protectedBefore'=>$files,'protectedAfter'=>$files,
            'supplierCalls'=>0,'databaseWrites'=>0,'schemaWrites'=>0,'profileWrites'=>0,'mappingWrites'=>0,'automaticAccepts'=>0]];
    return ['mode'=>'local-stay-source-facts-read-v1','status'=>'complete','operation_id'=>AnyTourHotelStayPermissionProofReadV1::PARENT_OPERATION,
        'source_sha'=>AnyTourHotelStayPermissionProofReadV1::SOURCE,'local_stay_source_facts'=>['data'=>$data,'fileSha256'=>hash('sha256',AnyTourHotelStayPermissionProofReadV1::json($data)."\n"),'protectedBefore'=>$files,'protectedAfter'=>$files]];
}
function proof_run(PDO $db,array $parent,string $state='completed_read_only'): array {
    global $site;$identity=$parent['local_stay_source_facts']['data']['receipt']['databaseSha256Before'];
    $r=AnyTourHotelStayPermissionProofReadV1::read($db,$parent,$site,$identity);proof_check($r['receipt']['state']===$state,'state '.$state.' actual '.$r['receipt']['state']);
    foreach (['supplierCalls','databaseWrites','schemaWrites','profileWrites','mappingWrites','automaticAccepts'] as $key) proof_check($r['receipt'][$key]===0,'typed0 '.$key);
    proof_check($r['receipt']['oldAccountIdentityKnown']===false&&$r['receipt']['effectiveDenialClaimed']===false&&$r['receipt']['ddlAdmission']===false,'new account fact only, no denial or DDL admission');
    proof_check($r['receipt']['coverageSha256']===hash('sha256',AnyTourHotelStayPermissionProofReadV1::json(['coverage'=>$r['coverage'],'privileges'=>$r['privileges']])),'exact coverage and privileges bound');
    proof_check(strlen(AnyTourHotelStayPermissionProofReadV1::json($r))<AnyTourHotelStayPermissionProofReadV1::MAX_BYTES,'bounded safe output');return $r;
}
function proof_held(array $r): void {proof_check($r['receipt']['allRequiredProven']===false&&array_column($r['privileges'],'status')===array_fill(0,4,'not_proven'),'held proof never inferred effective privilege or denial');}
$positive=proof_account('perm_proof_literal','GRANT CREATE,REFERENCES,SELECT,TRIGGER ON `'.$escaped.'`.*');$parent=proof_parent($positive);
$old=$positive->prepare("SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES WHERE BINARY GRANTEE=BINARY ? AND BINARY TABLE_SCHEMA=BINARY DATABASE() AND PRIVILEGE_TYPE IN ('CREATE','REFERENCES','SELECT','TRIGGER')");$old->execute(["'perm_proof_literal'@'%'"]);
proof_check($old->fetchAll(PDO::FETCH_COLUMN)===[],'historical exact TABLE_SCHEMA predicate misses escaped underscore grant');
proof_check($positive->query('SELECT id FROM anytour_hotels')->fetchColumn()===7,'actual SELECT works despite historical predicate false');
$before=$root->query('SELECT id FROM `'.$name.'`.anytour_hotels')->fetchAll();$r=proof_run($positive,$parent);
proof_check($r['coverage']['state']==='complete'&&$r['coverage']['schemaPatterns']==='single_literal_match'&&$r['coverage']['matchingSchemaPatterns']===1,'one escaped literal schema recognized');
proof_check($r['receipt']['allRequiredProven']===true&&array_column($r['privileges'],'status')===array_fill(0,4,'proven'),'all four direct schema positives proven conservatively');
proof_check(array_column($r['privileges'],'basis')===array_fill(0,4,'single_literal_schema'),'schema basis does not invent global rights');
proof_check($before===$root->query('SELECT id FROM `'.$name.'`.anytour_hotels')->fetchAll(),'application sentinel unchanged by metadata producer');
$public=AnyTourHotelStayPermissionProofReadV1::json($r);proof_check(!str_contains($public,'perm_proof_literal')&&!str_contains($public,$name)&&!str_contains($public,$escaped)&&!str_contains($public,'PRIVATE NATIVE')&&!str_contains($public,$site),'no account/schema/grant/config/path export');
proof_check($r['receipt']['accountSha256Before']===$r['receipt']['accountSha256After']&&$r['receipt']['roleSha256Before']===$r['receipt']['roleSha256After'],'same new account and role binding before/after');

$table=proof_account('perm_proof_table','GRANT SELECT ON `'.$name.'`.anytour_hotels');proof_check($table->query('SELECT id FROM anytour_hotels')->fetchColumn()===7,'native table-only SELECT works');
$t=proof_run($table,proof_parent($table));proof_held($t);proof_check($t['coverage']['state']==='complete'&&$t['privileges']['SELECT']['basis']==='none'&&$t['coverage']['tableColumnScopes'][0]['tablePrivileges']===['SELECT'],'table privilege observed separately, never database-wide SELECT proof');

$root->exec("CREATE USER 'perm_proof_roles'@'%' IDENTIFIED BY '".$fixturePassword."'");$root->exec("CREATE ROLE 'perm_proof_role'");$root->exec('GRANT SELECT ON `'.$escaped."`.* TO 'perm_proof_role'");$root->exec("GRANT 'perm_proof_role' TO 'perm_proof_roles'@'%'");$roleUser=new PDO($server,'perm_proof_roles',$fixturePassword,$options);$roleUser->exec("SET ROLE 'perm_proof_role'");$roleUser->exec('USE `'.$name.'`');
proof_check($roleUser->query('SELECT id FROM anytour_hotels')->fetchColumn()===7,'active native role supplies SELECT');$role=proof_run($roleUser,proof_parent($roleUser));proof_held($role);
proof_check($role['coverage']['activeRoles']==='unexpanded'&&$role['coverage']['state']==='partial','active roles explicitly held without graph expansion');

$ambiguous=proof_account('perm_proof_ambiguous','GRANT CREATE,REFERENCES,SELECT,TRIGGER ON `'.$escaped.'`.*');
$root->exec("GRANT SELECT ON `stay\\_perm\\_proof\\_fixture\\_%`.* TO 'perm_proof_ambiguous'@'%'");
$amb=proof_run($ambiguous,proof_parent($ambiguous));proof_held($amb);proof_check($amb['coverage']['state']==='partial'&&$amb['coverage']['schemaPatterns']==='ambiguous'&&$amb['coverage']['matchingSchemaPatterns']===2,'multiple matching patterns never unioned into proof');

$exotic=proof_account("perm_proof'quote",'GRANT SELECT ON `'.$escaped.'`.*');$unsafe=proof_run($exotic,proof_parent($exotic));proof_held($unsafe);
proof_check($unsafe['coverage']['accountEncoding']==='unsupported'&&$unsafe['coverage']['state']==='partial'&&$unsafe['coverage']['metadataRowsBefore']===null,'exotic account syntax held without guessing GRANTEE encoding');

final class ProofRevokesScalarFixturePdo extends PDO {
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false {
        if ($query===AnyTourHotelStayPermissionProofReadV1::BINDING_SQL) $query=str_replace('@@global.partial_revokes AS partial_revokes','1 AS partial_revokes',$query);
        return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs);
    }
}
$revokes=new ProofRevokesScalarFixturePdo(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'perm_proof_literal',$fixturePassword,$options);
$rev=proof_run($revokes,proof_parent($revokes));proof_held($rev);proof_check($rev['coverage']['partialRevokes']===true&&$rev['coverage']['state']==='partial','simulated partial_revokes report guards proof; no server revoke semantics claimed or globals changed');

$root->exec('CREATE DATABASE stay_perm_other_fixture');$other=new PDO(str_replace(';charset=utf8mb4',';dbname=stay_perm_other_fixture;charset=utf8mb4',$server),'root',$password,$options);
$wrong=proof_run($other,$parent,'database_identity');proof_held($wrong);
$bad=$parent;$bad['local_stay_source_facts']['data']['receipt']['controlSha']=str_repeat('a',40);proof_held(proof_run($positive,$bad,'parent_invalid'));
$config=file_get_contents($site.'/config.php');file_put_contents($site.'/config.php','changed');proof_held(proof_run($positive,$parent,'protected_drift'));file_put_contents($site.'/config.php',$config);
$positive->beginTransaction();proof_held(proof_run($positive,$parent,'caller_transaction'));proof_check($positive->inTransaction(),'caller transaction remains owned');$positive->rollBack();

final class ProofBindingDriftFixturePdo extends PDO {
    private int $bindings=0;
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false {
        if ($query===AnyTourHotelStayPermissionProofReadV1::BINDING_SQL&&++$this->bindings>1) $query=str_replace('CURRENT_USER() AS account',"CONCAT(CURRENT_USER(),'-changed') AS account",$query);
        return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs);
    }
}
$drift=new ProofBindingDriftFixturePdo(str_replace(';charset=utf8mb4',';dbname='.$name.';charset=utf8mb4',$server),'perm_proof_literal',$fixturePassword,$options);
proof_held(proof_run($drift,proof_parent($drift),'binding_drift'));

// A fixed allowlisted column grant explains a source-column observation, never database-wide SELECT.
$column=proof_account('perm_proof_column','GRANT SELECT (id) ON `'.$name.'`.anytour_hotels');
proof_check($column->query('SELECT id FROM anytour_hotels')->fetchColumn()===7,'native fixed-column SELECT works');
$columnProof=proof_run($column,proof_parent($column));proof_held($columnProof);$observed=$columnProof['coverage']['tableColumnScopes'][0];
proof_check($observed['observationScope']==='direct_metadata_fixed_columns'&&$observed['columnGrants']===[['columnName'=>'id','privileges'=>['SELECT']]]
    &&$observed['columnGrantRows']===1&&$columnProof['privileges']['SELECT']['basis']==='none','id direct column grant observed without table/column absence or database proof');

// Real own-GRANTEE schema metadata exceeds256rows; nonmatching patterns never broaden target authority.
$bounded=proof_account('perm_proof_bounds','GRANT SELECT (id) ON `'.$name.'`.anytour_hotels');
for ($i=0;$i<65;++$i) $root->exec("GRANT CREATE,REFERENCES,SELECT,TRIGGER ON `perm\\_proof\\_unused\\_".$i."%`.* TO 'perm_proof_bounds'@'%'");
$limit=proof_run($bounded,proof_parent($bounded),'metadata_limit');proof_held($limit);
proof_check($limit['coverage']['state']==='unavailable'&&$limit['coverage']['metadataRowsBefore']===null,'real metadata row budget exhaustion held, not silently truncated');
proof_check($root->query('SELECT id FROM `'.$name.'`.anytour_hotels')->fetchColumn()===7,'sentinel unchanged through all proof cases');
echo 'HOTEL_STAY_PERMISSION_PROOF_READ_OK checks='.$checks.' database=NATIVE_MYSQL supplier_calls=0 application_writes=0 schema_writes=0'.PHP_EOL;
