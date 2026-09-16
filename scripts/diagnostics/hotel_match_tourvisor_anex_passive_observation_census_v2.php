<?php
declare(strict_types=1);

const OP = 'hotel-match-tourvisor-anex-passive-observation-1971-20260916-v2';
const CORE8 = [1=>true,2=>true,4=>true,8=>true,9=>true,10=>true,12=>true,16=>true];

function j($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
function pn(string $p, array $v): string {
    $s=j($v)."\n"; $f=@fopen($p,'x'); if(!$f) throw new RuntimeException('durable_open');
    try { if(fwrite($f,$s)!==strlen($s)) throw new RuntimeException('durable_write'); fflush($f); } finally { fclose($f); }
    if(file_get_contents($p)!==$s) throw new RuntimeException('durable_readback');
    return hash('sha256',$s);
}
function dbp(string $r): string { foreach([$r.'/data/db-v1.php',$r.'/v2/data/db-v1.php'] as $p) if(is_file($p)) return $p; throw new RuntimeException('db_missing'); }
function q(PDO $d,string $s,array $p=[]): array { $x=$d->prepare($s); $x->execute($p); return $x->fetchAll(PDO::FETCH_ASSOC)?:[]; }
function table_exists(PDO $d,string $n): bool { $r=q($d,'SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$n]); return (int)($r[0]['c']??0)>0; }
function nm($s): string {
    $s=mb_strtolower((string)$s,'UTF-8');
    $s=strtr($s,['ё'=>'е','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ö'=>'o','ô'=>'o','ü'=>'u','ú'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g','ı'=>'i']);
    $s=preg_replace('/\b(?:hotel|hotels|resort|resorts|spa|the|and)\b/iu',' ',$s);
    $s=preg_replace('/[^\pL\pN]+/u',' ',$s);
    return trim(preg_replace('/\s+/u',' ',$s));
}
function compact_name($s): string { return str_replace(' ','',nm($s)); }
function country_id($s): ?int {
    $n=compact_name($s);
    $m=['egypt'=>1,'египет'=>1,'turkey'=>2,'turkiye'=>2,'турция'=>2,'thailand'=>4,'таиланд'=>4,'uae'=>8,'unitedarabemirates'=>8,'оаэ'=>8,'объединенныеарабскиеэмираты'=>8,'vietnam'=>9,'вьетнам'=>9,'maldives'=>10,'мальдивы'=>10,'srilanka'=>12,'шриланка'=>12,'cuba'=>16,'куба'=>16];
    return $m[$n]??null;
}
function is_anex($s): bool { $n=mb_strtolower((string)$s,'UTF-8'); return str_contains($n,'anex')||str_contains($n,'анекс'); }
function add_code(array &$out,$v,string $type): void { if(!is_scalar($v)) return; $s=trim((string)$v); if(!preg_match('/^\d{2,10}$/D',$s)) return; $c=(int)$s; if($c>0) $out[$c][$type]=true; }
function scan_anex_url($u,array &$out,string $source): void {
    if(!is_string($u)||$u==='') return; $u=html_entity_decode($u,ENT_QUOTES|ENT_HTML5,'UTF-8'); $parts=@parse_url($u); if(!is_array($parts)) return;
    $host=mb_strtolower((string)($parts['host']??''),'UTF-8'); if(!($host==='anextour.ru'||str_ends_with($host,'.anextour.ru'))) return;
    $qq=[]; parse_str((string)($parts['query']??''),$qq); foreach($qq as $k=>$v){$nk=mb_strtolower((string)$k,'UTF-8');if(in_array($nk,['hotelcode','hotel_code'],true))add_code($out,$v,$source.'_hotelcode');}
    if(preg_match_all('/(?:hotelCode|hotel_code)=([0-9]{2,10})/i',$u,$m)) foreach($m[1] as $v) add_code($out,$v,$source.'_hotelcode');
}
function scan_query($v,array &$out,int $depth=0): void {
    if($depth>6)return;
    if(is_string($v)){scan_anex_url($v,$out,'query_url');$x=json_decode($v,true);if(is_array($x))scan_query($x,$out,$depth+1);return;}
    if(!is_array($v))return;
    foreach($v as $k=>$x){$nk=mb_strtolower((string)$k,'UTF-8');if(in_array($nk,['hotelcode','hotel_code'],true))add_code($out,$x,'operator_query_hotelcode');if(is_array($x)||is_string($x))scan_query($x,$out,$depth+1);}
}
function scan_snapshot($v,array &$out,int $depth=0): void {
    if($depth>8)return;
    if(is_array($v)){foreach($v as $k=>$x){$nk=mb_strtolower((string)$k,'UTF-8');if(in_array($nk,['hotelcode','hotel_code'],true))add_code($out,$x,'snapshot_hotelcode');if(is_array($x)||is_string($x))scan_snapshot($x,$out,$depth+1);}return;}
    if(is_string($v)){if(str_contains($v,'anextour.ru'))scan_anex_url($v,$out,'snapshot_url');$x=json_decode($v,true);if(is_array($x))scan_snapshot($x,$out,$depth+1);}
}

if(in_array('--self-test',$argv??[],true)){
    $o=[];scan_anex_url('https://files.anextour.ru/hotel/x/o417822?hotelCode=5844',$o,'test');
    if(!isset($o[5844]['test_hotelcode'])||country_id('Sri Lanka')!==12||country_id('Maldives')!==10)throw new RuntimeException('selftest');
    echo "PASS\n";exit;
}

$op=(string)getenv('OPERATION_ID');$sha=(string)getenv('MATCH_SOURCE_SHA');if($op!==OP||!preg_match('/^[0-9a-f]{40}$/D',$sha))throw new RuntimeException('guard');
$root=realpath(getcwd());$base=rtrim((string)getenv('HOME'),'/').'/.anytoour-match/operations';if(!$root||basename($root)!=='anytoour.ru'||!is_dir($base))throw new RuntimeException('root');
$dir=$base.'/'.$op;if(!mkdir($dir,0700))throw new RuntimeException('exists');
pn($dir.'/reservation.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'reserved_before_evidence_db','read_only'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
$db=null;
try{
    require_once dbp($root);$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    if(!table_exists($db,'tour_operator_identity_observations'))throw new RuntimeException('observer_table_missing');
    $obs=q($db,'SELECT id,search_id,tour_id,hotel_id,operator_id,operator_name,operator_link,operator_link_query,hotel_link,snapshot_json,observed_at FROM tour_operator_identity_observations ORDER BY observed_at DESC,id DESC');
    $hot=q($db,'SELECT id,name,country_id,is_active FROM catalog_hotels WHERE is_active=1');
    $stg=table_exists($db,'anex_hotels')?q($db,'SELECT * FROM anex_hotels ORDER BY anex_hotel_id'):[];
    $map=table_exists($db,'anex_hotel_search_mappings')?q($db,'SELECT * FROM anex_hotel_search_mappings'):[];
    $manual=table_exists($db,'anex_hotel_decisions')?q($db,'SELECT * FROM anex_hotel_decisions'):[];
    $excl=table_exists($db,'anex_review_pair_exclusions')?q($db,'SELECT anex_hotel_id,catalog_hotel_id FROM anex_review_pair_exclusions'):[];
    $db->exec('ROLLBACK');

    $hm=[];foreach($hot as $x)$hm[(int)$x['id']]=$x;
    $sm=[];foreach($stg as $x)if(isset($x['anex_hotel_id']))$sm[(int)$x['anex_hotel_id']]=$x;
    $mappedCode=[];$mappedLocal=[];foreach($map as $x){$c=(int)($x['anex_hotel_id']??0);$l=(int)($x['catalog_hotel_id']??$x['local_hotel_id']??0);if($c)$mappedCode[$c]=true;if($l)$mappedLocal[$l]=true;}
    $manualCode=[];foreach($manual as $x){$c=(int)($x['anex_hotel_id']??0);if($c)$manualCode[$c]=true;}
    $excluded=[];foreach($excl as $x)$excluded[(int)$x['anex_hotel_id']][(int)$x['catalog_hotel_id']]=true;

    $operatorStats=[];$anex=[];foreach($obs as $x){$name=trim((string)$x['operator_name']);$oid=(string)($x['operator_id']??'');$k=($name!==''?$name:'(blank)').'|'.($oid!==''?$oid:'null');$operatorStats[$k]=($operatorStats[$k]??0)+1;if(is_anex($name))$anex[]=$x;}arsort($operatorStats);
    $pairs=[];$evidenceTypes=[];$uniqueTv=[];
    foreach($anex as $x){
        $lid=(int)($x['hotel_id']??0);if(!$lid)continue;$uniqueTv[$lid]=true;$codes=[];
        scan_anex_url((string)($x['operator_link']??''),$codes,'operator_link');scan_anex_url((string)($x['hotel_link']??''),$codes,'hotel_link');
        $oq=$x['operator_link_query']??null;if(is_string($oq)){$d=json_decode($oq,true);scan_query(is_array($d)?$d:$oq,$codes);}elseif(is_array($oq))scan_query($oq,$codes);
        $sn=$x['snapshot_json']??null;if(is_string($sn)){$d=json_decode($sn,true);scan_snapshot(is_array($d)?$d:$sn,$codes);}elseif(is_array($sn))scan_snapshot($sn,$codes);
        foreach($codes as $code=>$types){foreach(array_keys($types) as $t)$evidenceTypes[$t]=($evidenceTypes[$t]??0)+1;$pairs[$lid][$code]['observations']=($pairs[$lid][$code]['observations']??0)+1;$pairs[$lid][$code]['types']=array_values(array_unique(array_merge($pairs[$lid][$code]['types']??[],array_keys($types))));$pairs[$lid][$code]['operator_name']=$x['operator_name'];$pairs[$lid][$code]['operator_id']=$x['operator_id'];}
    }
    ksort($evidenceTypes);$codeToLocal=[];foreach($pairs as $l=>$cs)foreach($cs as $c=>$v)$codeToLocal[$c][$l]=true;
    $rows=[];$prepared=[];
    foreach($pairs as $l=>$cs)foreach($cs as $c=>$v){
        $types=$v['types'];sort($types);$local=$hm[$l]??null;$stage=$sm[$c]??null;$reason='hold';$countryOk=null;
        if(count($cs)!==1||count($codeToLocal[$c]??[])!==1)$reason='identity_conflict';
        elseif(!$local||(int)($local['is_active']??0)!==1||!isset(CORE8[(int)$local['country_id']]))$reason='local_missing_or_outside_core8';
        elseif(!(in_array('operator_link_hotelcode',$types,true)||in_array('hotel_link_hotelcode',$types,true)||in_array('query_url_hotelcode',$types,true)||in_array('operator_query_hotelcode',$types,true)||in_array('snapshot_url_hotelcode',$types,true)))$reason='weak_code_source';
        elseif(isset($mappedCode[$c])||isset($mappedLocal[$l])||isset($manualCode[$c])||isset($excluded[$c][$l]))$reason='protected_existing_state';
        elseif(!$stage)$reason='anex_staging_missing';
        else{
            $sc=country_id($stage['api_country']??'');$countryOk=$sc!==null&&$sc===(int)$local['country_id'];
            $names=array_filter([(string)($stage['api_name']??''),(string)($stage['xml_name']??''),(string)($stage['xml_alternate_name']??'')],fn($x)=>trim($x)!=='');$exact=false;
            foreach($names as $n)if(nm($n)===nm($local['name'])||compact_name($n)===compact_name($local['name'])){$exact=true;break;}
            if(!$countryOk)$reason='country_guard';elseif(!$exact)$reason='name_not_exact_or_compact';else$reason='guard_passed_prepared';
        }
        $row=['tourvisor_hotel_id'=>(int)$l,'anex_hotel_id'=>(int)$c,'observations'=>(int)$v['observations'],'evidence_types'=>$types,'operator_name'=>$v['operator_name'],'operator_id'=>$v['operator_id'],'local_name'=>$local['name']??null,'local_country_id'=>$local['country_id']??null,'anex_name'=>$stage['api_name']??$stage['xml_name']??null,'anex_country'=>$stage['api_country']??null,'country_guard'=>$countryOk,'route'=>$reason==='guard_passed_prepared'?'guard_passed_prepared':'hold','reason'=>$reason];
        $rows[]=$row;if($reason==='guard_passed_prepared')$prepared[]=$row;
    }
    usort($prepared,fn($a,$b)=>$b['observations']<=>$a['observations']?:$a['tourvisor_hotel_id']<=>$b['tourvisor_hotel_id']);
    $reasonCounts=[];foreach($rows as $r)$reasonCounts[$r['reason']]=($reasonCounts[$r['reason']]??0)+1;ksort($reasonCounts);
    $res=['schema'=>'hotel-match-tourvisor-anex-passive-observation-census/2','operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','observer_total'=>count($obs),'anex_observations'=>count($anex),'anex_unique_tourvisor_hotels'=>count($uniqueTv),'operator_stats'=>$operatorStats,'evidence_type_counts'=>$evidenceTypes,'pair_count'=>count($rows),'reason_counts'=>$reasonCounts,'prepared_count'=>count($prepared),'prepared'=>$prepared,'rows'=>$rows,'table_counts'=>['anex_hotels'=>count($stg),'existing_mappings'=>count($map),'manual_decisions'=>count($manual),'pair_exclusions'=>count($excl)],'safe_to_write_now'=>false,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'no_replay'=>true];
    $dg=pn($dir.'/result.json',$res);pn($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'completed_read_only','result_sha256'=>$dg,'readback_verified'=>hash('sha256',file_get_contents($dir.'/result.json'))===$dg,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);
    echo j(['state'=>'completed_read_only','observer_total'=>count($obs),'anex_observations'=>count($anex),'anex_unique_tourvisor_hotels'=>count($uniqueTv),'evidence_type_counts'=>$evidenceTypes,'pair_count'=>count($rows),'reason_counts'=>$reasonCounts,'prepared_count'=>count($prepared),'prepared'=>$prepared])."\n";
}catch(Throwable $e){
    try{if($db instanceof PDO&&$db->inTransaction())$db->rollBack();}catch(Throwable){}
    $f=['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','error_class'=>get_class($e),'error_message'=>$e->getMessage(),'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true];$dg=pn($dir.'/failure.json',$f);pn($dir.'/receipt.json',['operation_id'=>$op,'source_sha'=>$sha,'state'=>'failed_read_only','failure_sha256'=>$dg,'readback_verified'=>true,'database_writes'=>0,'mapping_writes'=>0,'supplier_calls'=>0,'tourvisor_calls'=>0,'no_replay'=>true]);fwrite(STDERR,"HMAP_FAILED ".$e->getMessage()."\n");exit(2);
}
