<?php
declare(strict_types=1);
ini_set('display_errors','0'); ini_set('log_errors','0');
const OP='hotel-match-core8-taxonomy-scale-1971-20260911-v1';
require_once getcwd().'/config.php';
$dbHelper=is_file(getcwd().'/data/db-v1.php')?getcwd().'/data/db-v1.php':getcwd().'/v2/data/db-v1.php'; require_once $dbHelper;
$pdo=v2_data_db();
$aliases=[
 'Egypt'=>['Египет','Egypt'], 'Turkey'=>['Турция','Turkey'], 'Thailand'=>['Таиланд','Thailand'],
 'UAE'=>['ОАЭ','Объединенные Арабские Эмираты','Объединённые Арабские Эмираты','UAE','United Arab Emirates'],
 'Vietnam'=>['Вьетнам','Vietnam'], 'Sri Lanka'=>['Шри-Ланка','Шри Ланка','Sri Lanka'],
 'Maldives'=>['Мальдивы','Maldives'], 'Cuba'=>['Куба','Cuba'],
];
$countries=$pdo->query("SELECT id,name,slug FROM catalog_countries WHERE is_active=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$fold=static fn(string $v):string=>mb_strtolower(trim($v),'UTF-8');
$selected=[];
foreach($aliases as $label=>$names){$hits=[];foreach($countries as $c){foreach($names as $n){if($fold((string)$c['name'])===$fold($n)){$hits[(string)$c['id']]=$c;break;}}}$selected[$label]=array_values($hits);}
$outCountries=[];$countryIds=[];
foreach($selected as $label=>$hits){foreach($hits as $c){$id=(int)$c['id'];$countryIds[$id]=true;$dep=$pdo->prepare('SELECT COUNT(*) FROM catalog_departure_countries WHERE departure_id=1 AND country_id=?');$dep->execute([$id]);$outCountries[]=['label'=>$label,'id'=>$id,'name'=>(string)$c['name'],'slug'=>(string)$c['slug'],'moscow_enabled'=>(int)$dep->fetchColumn()>0];}}
if(count($countryIds)!==8){echo 'MATCH_CORE8_JSON:'.json_encode(['status'=>'failed','reason'=>'core8_country_resolution','resolved'=>$outCountries,'resolved_count'=>count($countryIds),'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(2);}
$ids=array_keys($countryIds);$ph=implode(',',array_fill(0,count($ids),'?'));
$q=$pdo->prepare("SELECT r.country_id,r.id,r.name,COUNT(h.id) hotel_count FROM catalog_regions r LEFT JOIN catalog_hotels h ON h.country_id=r.country_id AND h.region_id=r.id AND h.is_active=1 WHERE r.is_active=1 AND r.country_id IN ($ph) GROUP BY r.country_id,r.id,r.name ORDER BY r.country_id,hotel_count DESC,r.name");$q->execute($ids);$regions=$q->fetchAll(PDO::FETCH_ASSOC);
$q=$pdo->prepare("SELECT r.country_id,s.region_id,s.id,s.name,COUNT(h.id) hotel_count FROM catalog_subregions s JOIN catalog_regions r ON r.id=s.region_id AND r.country_id IN ($ph) LEFT JOIN catalog_hotels h ON h.country_id=r.country_id AND h.region_id=s.region_id AND h.subregion_id=s.id AND h.is_active=1 WHERE s.is_active=1 GROUP BY r.country_id,s.region_id,s.id,s.name ORDER BY r.country_id,s.region_id,hotel_count DESC,s.name");$q->execute($ids);$subs=$q->fetchAll(PDO::FETCH_ASSOC);
$regions=array_map(static fn($r)=>['country_id'=>(int)$r['country_id'],'id'=>(int)$r['id'],'name'=>(string)$r['name'],'hotel_count'=>(int)$r['hotel_count']],$regions);
$subs=array_map(static fn($r)=>['country_id'=>(int)$r['country_id'],'region_id'=>(int)$r['region_id'],'id'=>(int)$r['id'],'name'=>(string)$r['name'],'hotel_count'=>(int)$r['hotel_count']],$subs);
$regionSweep=array_values(array_filter($regions,static fn($r)=>$r['hotel_count']>0));
$subSweep=array_values(array_filter($subs,static fn($r)=>$r['hotel_count']>=10));
echo 'MATCH_CORE8_JSON:'.json_encode(['status'=>'completed','operation_id'=>OP,'countries'=>$outCountries,'regions'=>$regions,'subregions'=>$subs,'region_sweep_candidates'=>$regionSweep,'subregion_sweep_candidates'=>$subSweep,'region_candidate_count'=>count($regionSweep),'subregion_candidate_count'=>count($subSweep),'supplier_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'booking_calls'=>0,'lead_calls'=>0],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;
