<?php
declare(strict_types=1);
/** MATCH-only CURRENT identity census. No supplier transport or database mutations. */
const HM_CENSUS_OP = 'hotel-match-core8-identity-census-1971-20260912-v1';
function hmCountry($value): ?string {
    $s = strtolower(strtr(trim((string)$value), array_combine(preg_split('//u','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ',-1,PREG_SPLIT_NO_EMPTY),preg_split('//u','абвгдеёжзийклмнопрстуфхцчшщъыьэюя',-1,PREG_SPLIT_NO_EMPTY))));
    $s = preg_replace('/[\s_-]+/u', ' ', str_replace('ё','е',$s));
    $names = ['egypt'=>['египет','egypt'], 'turkey'=>['турция','turkey','türkiye','turkiye'],
        'thailand'=>['таиланд','thailand'], 'uae'=>['оаэ','объединенные арабские эмираты','united arab emirates','uae'],
        'vietnam'=>['вьетнам','vietnam','viet nam'], 'srilanka'=>['шри ланка','sri lanka'],
        'maldives'=>['мальдивы','maldives'], 'cuba'=>['куба','cuba']];
    foreach ($names as $key=>$values) if (in_array($s,$values,true)) return $key;
    return null;
}
function hmProjection(array $row, array $keys): array {
    $out=[];
    foreach ($keys as $key) if (array_key_exists($key,$row) && (is_scalar($row[$key]) || $row[$key]===null)) {
        $value=$row[$key];
        if (is_string($value) && (strlen($value)>2000 || preg_match('/oauth_token|authorization|bearer\s|[<>\x00]/i',$value))) continue;
        $out[$key]=$value;
    }
    return $out;
}
function hmEvidenceSources(array $value, int $depth=0): array {
    if ($depth>12) return [];
    $out=[];
    if (isset($value['name']) && (isset($value['id']) || isset($value['hotelKey']))) {
        $out[]=hmProjection($value,['id','hotelKey','name','lName','state','stateKey','town','townKey',
            'region','regionKey','star','starKey','latitude','longitude','lat','lon','lng','address']);
    }
    foreach ($value as $key=>$child) {
        if (is_array($child) && !in_array((string)$key,['candidates','targets','local','hotels','offers'],true))
            $out=array_merge($out,hmEvidenceSources($child,$depth+1));
    }
    $unique=[]; foreach ($out as $row) $unique[hash('sha256',json_encode($row))]=$row;
    return array_values($unique);
}
function hmSelect(PDO $pdo, string $table, array $wanted, int $limit, array &$schemas): array {
    $allowed=['catalog_hotels','hotel_aliases','anex_hotels','anex_hotel_search_mappings','anex_hotel_decisions',
        'anex_review_pair_exclusions','anex_search_hotel_observations','andromeda_hotel_identities'];
    if (!in_array($table,$allowed,true)) throw new RuntimeException('table_not_allowed');
    $columns=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
    $schemas[$table]=$columns;
    $selected=array_values(array_intersect($wanted,$columns));
    if (!$selected) throw new RuntimeException('missing_identity_columns');
    $sql='SELECT `'.implode('`,`',$selected).'` FROM `'.$table.'` LIMIT '.($limit+1);
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)>$limit) throw new RuntimeException('census_bound_exceeded');
    return $rows;
}
if (in_array('--self-test', $_SERVER['argv']??[],true)) {
    assert_options(ASSERT_ACTIVE,1);
    if(hmCountry('Турция')!=='turkey'||hmCountry('Украина')!==null||hmCountry('not egypt')!==null)exit(2);
    $fixture=['prior_evidence'=>['source'=>['id'=>'1','name'=>'Test Hotel','state'=>'Egypt','token'=>'SECRET']],
        'candidates'=>[['id'=>999,'name'=>'Not a supplier']]];
    $rows=hmEvidenceSources($fixture);
    if(count($rows)!==1||isset($rows[0]['token'])||$rows[0]['id']!=='1')exit(3);
    if(hmProjection(['name'=>'<secret>','id'=>5],['name','id'])!==['id'=>5])exit(4);
    echo "MATCH census offline self-test PASS; network=0 database=0\n";exit;
}
error_reporting(0);ob_start();$pdo=null;
try {
    if(PHP_SAPI!=='cli')throw new RuntimeException('cli_only');
    $root=realpath(getcwd());
    if(!$root||basename($root)!=='anytoour.ru')throw new RuntimeException('root');
    require_once $root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');
    $pdo=v2_data_db();$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION READ ONLY');$schemas=[];
    $all=hmSelect($pdo,'catalog_hotels',['id','name','country_id','country_name','region_id','region_name',
        'subregion_id','subregion_name','category','latitude','longitude','lat','lon','lng','is_active'],200000,$schemas);
    $local=[];$countries=[];$localIds=[];
    foreach($all as $row){$cc=hmCountry($row['country_name']??'');if($cc===null||(int)($row['is_active']??0)!==1)continue;
        $row['country_class']=$cc;$local[]=$row;$localIds[(string)$row['id']]=true;
        $countries[(string)$row['country_id']]=$cc;}
    $aliases=array_values(array_filter(hmSelect($pdo,'hotel_aliases',['hotel_id','alias','source'],1000000,$schemas),
        static fn($row)=>isset($localIds[(string)$row['hotel_id']])));
    $anex=hmSelect($pdo,'anex_hotels',['anex_hotel_id','source_fingerprint','xml_name','xml_alternate_name','xml_town_id',
        'api_name','api_country','api_region','api_town','api_town_id','api_address','latitude','longitude','checked_at'],100000,$schemas);
    $mappings=hmSelect($pdo,'anex_hotel_search_mappings',['anex_hotel_id','catalog_hotel_id','match_class','scope','approval_policy',
        'source_row_digest','mapping_digest','enabled'],100000,$schemas);
    $decisions=hmSelect($pdo,'anex_hotel_decisions',['anex_hotel_id','catalog_hotel_id','decision','status','decision_status'],100000,$schemas);
    $exclusions=hmSelect($pdo,'anex_review_pair_exclusions',['anex_hotel_id','catalog_hotel_id','local_hotel_id'],100000,$schemas);
    $observations=hmSelect($pdo,'anex_search_hotel_observations',['anex_hotel_id','hotel_name','country_id','anex_country_id',
        'last_catalog_hotel_id','search_count','last_seen_utc'],100000,$schemas);
    $andromeda=hmSelect($pdo,'andromeda_hotel_identities',['supplier_namespace','external_hotel_id','country_id','local_hotel_id',
        'decision_status','evidence_json','evidence_sha256','catalog_sha256'],100000,$schemas);
    $and=[];$identityCounts=[];
    foreach($andromeda as $row){$status=(string)($row['decision_status']??'unknown');$identityCounts[$status]=($identityCounts[$status]??0)+1;
        $cc=$countries[(string)($row['country_id']??'')]??null;
        $e=json_decode((string)($row['evidence_json']??''),true);
        $sources=is_array($e)?hmEvidenceSources($e):[];
        $sourceCountries=[];
        foreach($sources as $src) if((string)($src['id']??$src['hotelKey']??'')===(string)$row['external_hotel_id']) {
            $sc=hmCountry($src['state']??'');if($sc!==null)$sourceCountries[$sc]=true;
        }
        if($cc===null&&count($sourceCountries)===1)$cc=array_key_first($sourceCountries);
        if($cc===null && $status==='accepted' && !isset($localIds[(string)($row['local_hotel_id']??'')]))continue;
        $row['actual_evidence_sha256']=hash('sha256',(string)($row['evidence_json']??''));
        unset($row['evidence_json']);$row['country_class']=$cc;$row['sources']=$sources;$and[]=$row;}
    $pdo->exec('ROLLBACK');
    $out=['status'=>'completed','operation_id'=>HM_CENSUS_OP,'generated_at_utc'=>gmdate('c'),'country_ids'=>$countries,
        'schemas'=>$schemas,'counts'=>['all_local_rows'=>count($all),'core8_local'=>count($local),'aliases'=>count($aliases),
            'anex_rows'=>count($anex),'anex_mappings'=>count($mappings),'anex_decisions'=>count($decisions),
            'anex_exclusions'=>count($exclusions),'anex_observations'=>count($observations),
            'andromeda_all_statuses'=>$identityCounts,'andromeda_core8_rows'=>count($and)],
        'local'=>$local,'aliases'=>$aliases,'anex'=>$anex,'anex_mappings'=>$mappings,'anex_decisions'=>$decisions,
        'anex_exclusions'=>$exclusions,'anex_observations'=>$observations,'andromeda'=>$and,
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
} catch(Throwable $e) {
    if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
    $out=['status'=>'failed','operation_id'=>HM_CENSUS_OP,'error_class'=>get_class($e),'safe_message'=>'identity_census_failed',
        'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];
}
while(ob_get_level())ob_end_clean();
echo 'MATCH_CENSUS:'.json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
exit($out['status']==='completed'?0:2);
