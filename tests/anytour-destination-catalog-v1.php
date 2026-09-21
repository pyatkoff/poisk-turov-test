<?php
declare(strict_types=1);
require_once __DIR__.'/../v2/data/anytour-destination-catalog-v1.php';
function dc(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function err(callable $f,string $m):void{try{$f();throw new RuntimeException('expected '.$m);}catch(Throwable $e){if($e->getMessage()!==$m)throw$e;}}
$dsn=(string)getenv('ANYTOUR_DESTINATION_TEST_DSN');if($dsn===''){echo "DESTINATION_PURE_LOAD_OK\n";exit;}
$db=new PDO($dsn,'root',(string)getenv('ANYTOUR_DESTINATION_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$sql=preg_replace('/^\s*--.*$/m','',file_get_contents(__DIR__.'/../v2/data/migrations/20260921-anytour-destination-identities-v1.sql'));
foreach(preg_split('/;\s*(?:\r?\n|$)/',$sql)as$s)if(trim($s)!=='')$db->exec($s);
$db->beginTransaction();try{
 $i=$db->prepare("INSERT INTO anytour_destinations_v1(kind,parent_id,name_ru,slug,revision,is_active,created_at,updated_at) VALUES(?,?,?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
 $i->execute(['country',null,'Турция','turkey']);$country=(int)$db->lastInsertId();
 $i->execute(['region',$country,'Анталья','antalya']);$region=(int)$db->lastInsertId();
 $i->execute(['subregion',$region,'Белек','belek']);$belek=(int)$db->lastInsertId();
 $i->execute(['subregion',$region,'Сиде','side']);$side=(int)$db->lastInsertId();
 $s=$db->prepare("INSERT INTO anytour_destination_sources_v1(provider,kind,external_id,anytour_destination_id,state,evidence_ref,evidence_sha256,reviewed_by,created_at) VALUES(?,?,?,?,'accepted',?,?,?,UTC_TIMESTAMP())");
 foreach([['tourvisor','country','4',$country],['tourvisor','region','1',$region],['tourvisor','subregion','10',$belek],['tourvisor','subregion','11',$side],['anex','subregion','BELEK',$belek],['samo','subregion','777',$belek],['andromeda','subregion','A-77',$belek]]as$r)$s->execute([$r[0],$r[1],$r[2],$r[3],'fixture://reviewed',hash('sha256',implode('|',$r)),'fixture']);
 $c=new AnyTourDestinationCatalogV1($db);dc($c->readable(),'readable');
 dc(array_column($c->children($region,'subregion'),'id')===[$belek,$side],'own child IDs');
 dc($c->nativeIds('tourvisor','subregion',[$belek,$side])===['10','11'],'local to TV IDs');
 dc($c->nativeIds('anex','subregion',[$belek])===['BELEK'],'local to ANEX ID');
 dc($c->nativeIds('samo','subregion',[$belek])===['777'],'local to SAMO ID');
 dc($c->nativeIds('andromeda','subregion',[$belek])===['A-77'],'local to Andromeda ID');
 dc($c->localId('tourvisor','subregion','10')===$belek,'TV back to local');
 dc($c->localId('anex','subregion','BELEK')===$belek,'ANEX back to local');
 err(fn()=>$c->nativeIds('anex','subregion',[$belek,$side]),'DESTINATION_UNMAPPED');
 err(fn()=>$c->nativeIds('tourvisor','subregion',[$country]),'DESTINATION_UNMAPPED');
 err(fn()=>$c->nativeIds('bad','subregion',[$belek]),'DESTINATION_PROVIDER');
 dc($c->localId('tourvisor','subregion','999')===null,'unknown native stays unknown');
 echo "DESTINATION_MYSQL_OK local_ids=1 tourvisor=1 anex=1 samo=1 andromeda=1 name_matching=0 partial_broadening=0\n";
}finally{$db->rollBack();}
