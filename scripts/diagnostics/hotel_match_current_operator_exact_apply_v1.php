<?php
declare(strict_types=1);
/** MATCH-only one-shot guarded apply for exact operator-native -> accepted Andromeda authority. */
const OP = 'hotel-match-current-operator-exact-apply-1971-20260917-v1';
const PREFLIGHT_OP = 'hotel-match-current-operator-exact-resolve-1971-20260917-v2';
const PREFLIGHT_RESULT_SHA256 = 'f27b7e3219115df705237196e8e12a366fb3e5d379abde45562d8cfd98cfe05a';
const PLAN_SHA256 = 'e2f0b38d56f87f3557d9f387618ff7418f01514c057a4fc1a0f2e9f38059a273';
const PLAN_B64 = 'W3sibnMiOiJvcGVyYXRvcl8zMTUiLCJleHRlcm5hbF9pZCI6IjMwMjc5IiwiYW5kcm9tZWRhX2lkIjoiODMwMDYiLCJjb3VudHJ5X2lkIjo0LCJuYW1lIjoiTCdPY2VhbmljYSBCZWFjaCBSZXNvcnQgSG90ZWwiLCJsb2NhbF9pZCI6MTMxM30seyJucyI6Im9wZXJhdG9yXzMxNSIsImV4dGVybmFsX2lkIjoiMzA4NzgiLCJhbmRyb21lZGFfaWQiOiIyMDAwMDM4ODM2IiwiY291bnRyeV9pZCI6NCwibmFtZSI6IkFudGlrIEhvdGVsIElzdGFuYnVsIiwibG9jYWxfaWQiOjE3MjkzfSx7Im5zIjoib3BlcmF0b3JfMzE1IiwiZXh0ZXJuYWxfaWQiOiIzMTAwNyIsImFuZHJvbWVkYV9pZCI6IjE0NDEiLCJjb3VudHJ5X2lkIjo0LCJuYW1lIjoiUmljaG1vbmQgSXN0YW5idWwiLCJsb2NhbF9pZCI6MTc2MDF9LHsibnMiOiJvcGVyYXRvcl8zMTUiLCJleHRlcm5hbF9pZCI6IjMxMDM4IiwiYW5kcm9tZWRhX2lkIjoiMTU2MjgwIiwiY291bnRyeV9pZCI6NCwibmFtZSI6IldvdyBJc3RhbmJ1bCIsImxvY2FsX2lkIjoxNzY5NH0seyJucyI6Im9wZXJhdG9yXzMxNSIsImV4dGVybmFsX2lkIjoiMzY0OTI0IiwiYW5kcm9tZWRhX2lkIjoiMjAwMDA0MDczNyIsImNvdW50cnlfaWQiOjEsIm5hbWUiOiJTdGVsbGEgR2FyZGVucyBSZXNvcnQgJiBTcGEgTWFrYWRpIEh1cmdoYWRhIiwibG9jYWxfaWQiOjE0MTQwfSx7Im5zIjoib3BlcmF0b3JfMzE1IiwiZXh0ZXJuYWxfaWQiOiI3NDgwOSIsImFuZHJvbWVkYV9pZCI6IjIwMDAwMzQxMjkiLCJjb3VudHJ5X2lkIjo0LCJuYW1lIjoiR3JhbmQgTWlyIEFtb3IgSG90ZWwiLCJsb2NhbF9pZCI6MzQxMH0seyJucyI6Im9wZXJhdG9yXzMxNSIsImV4dGVybmFsX2lkIjoiNzc4ODcxIiwiYW5kcm9tZWRhX2lkIjoiMjAwMDA4NDEzNSIsImNvdW50cnlfaWQiOjQsIm5hbWUiOiJTaWRlIFN1bmJlcmsgSG90ZWwiLCJsb2NhbF9pZCI6MjI2M30seyJucyI6Im9wZXJhdG9yXzM0MiIsImV4dGVybmFsX2lkIjoiMTIxNzAiLCJhbmRyb21lZGFfaWQiOiIyMDAwMDQwNzM3IiwiY291bnRyeV9pZCI6MSwibmFtZSI6IlN0ZWxsYSBkaSBNYXJlIEdhcmRlbnMgUmVzb3J0IChNYWthZGkgQmF5KSIsImxvY2FsX2lkIjoxNDE0MH0seyJucyI6Im9wZXJhdG9yXzM0MiIsImV4dGVybmFsX2lkIjoiMTY5MTUiLCJhbmRyb21lZGFfaWQiOiI0NDU3NyIsImNvdW50cnlfaWQiOjQsIm5hbWUiOiJFcGhlc3VzIElzdGFuYnVsIEhvdGVsIiwibG9jYWxfaWQiOjE3NDAzfSx7Im5zIjoib3BlcmF0b3JfMzQyIiwiZXh0ZXJuYWxfaWQiOiIyNDk4NCIsImFuZHJvbWVkYV9pZCI6IjIwMDAwNjgyODIiLCJjb3VudHJ5X2lkIjo0LCJuYW1lIjoiU3Vzb25hIEJvZHJ1bSBMeHIgSG90ZWxzICYgUmVzb3J0IiwibG9jYWxfaWQiOjU2MzQ0fSx7Im5zIjoib3BlcmF0b3JfMzQyIiwiZXh0ZXJuYWxfaWQiOiIzMzIwMSIsImFuZHJvbWVkYV9pZCI6IjIwMDAwNzk2NTgiLCJjb3VudHJ5X2lkIjo0LCJuYW1lIjoiQVJCQVRUIE1BUk1BUklTIEFET0xUIE9OTFkgKyAxNCIsImxvY2FsX2lkIjo4NjQ0MX1d';

function rq(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function js(array $x):string{return json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}
function wr(string $path,array $x):string{
    $raw=js($x);$f=@fopen($path,'x+b');rq(is_resource($f),'exclusive_output');
    try{rq(fwrite($f,$raw)===strlen($raw)&&fflush($f),'output_write');if(function_exists('fsync'))rq(fsync($f),'output_sync');rewind($f);rq(stream_get_contents($f)===$raw,'output_readback');}
    finally{fclose($f);} return hash('sha256',$raw);
}
function q(PDO $db,string $sql,array $args=[]):array{
    $s=$db->prepare($sql);$s->execute(array_values($args));$r=$s->fetchAll(PDO::FETCH_ASSOC);rq(count($r)<100000,'query_budget');return $r;
}
function prot($v,string $key='',int $depth=0):bool{
    if($depth>24)return true;
    $sensitive=preg_match('/manual|exclude|exclusion|conflict|reject|review|protect/i',$key)===1;
    if(is_array($v)){
        if($sensitive&&$v!==[])return true;
        foreach($v as $k=>$x)if(prot($x,(string)$k,$depth+1))return true;
        return false;
    }
    if(!$sensitive)return false;
    if($v===null)return false;
    if(is_bool($v))return $v;
    if(is_numeric($v))return (float)$v!=0.0;
    if(is_string($v))return !in_array(strtolower(trim($v)),['','false','none','no','null'],true);
    return true;
}
function points($x,array &$out,int $depth=0):void{
    rq($depth<=32,'coordinate_depth');
    if(!is_array($x))return;
    $lat=$x['latitude']??$x['lat']??null;$lon=$x['longitude']??$x['lon']??$x['lng']??null;
    if(is_numeric($lat)&&is_numeric($lon)){
        $a=(float)$lat;$b=(float)$lon;
        if(is_finite($a)&&is_finite($b)&&abs($a)<=90&&abs($b)<=180&&($a!=0.0||$b!=0.0))$out[]=[$a,$b];
    }
    foreach($x as $v)if(is_array($v))points($v,$out,$depth+1);
}
function km(array $a,array $b):float{
    [$x,$y,$u,$v]=array_map('deg2rad',[$a[0],$a[1],$b[0],$b[1]]);
    $h=sin(($u-$x)/2)**2+cos($x)*cos($u)*sin(($v-$y)/2)**2;
    return 6371.0088*2*asin(sqrt(min(1.0,max(0.0,$h))));
}
function plan():array{
    $raw=base64_decode(PLAN_B64,true);rq(is_string($raw)&&hash_equals(PLAN_SHA256,hash('sha256',$raw)),'plan_digest');
    $p=json_decode($raw,true,32,JSON_THROW_ON_ERROR);rq(is_array($p)&&count($p)===11,'plan_population');
    $ids=[];$targets=[];
    foreach($p as $r){
        rq(in_array($r['ns']??'',['operator_315','operator_342'],true),'plan_namespace');
        foreach(['external_id','andromeda_id'] as $k)rq(is_string($r[$k]??null)&&preg_match('/^[1-9][0-9]{0,19}$/D',$r[$k])===1,'plan_id');
        rq(is_int($r['country_id']??null)&&$r['country_id']>0&&is_int($r['local_id']??null)&&$r['local_id']>0,'plan_target');
        rq(is_string($r['name']??null)&&$r['name']!=='','plan_name');
        $ik=$r['ns'].'/'.$r['external_id'];$tk=$r['ns'].'/'.$r['local_id'];
        rq(!isset($ids[$ik])&&!isset($targets[$tk]),'plan_duplicate');$ids[$ik]=1;$targets[$tk]=1;
    }
    return $p;
}
function bridge(array $e):?array{
    $b=$e['provider_bridges'][0]??null;return is_array($b)?$b:null;
}
function main():void{
    rq(PHP_SAPI==='cli'&&getenv('MATCH_OPERATION_ID')===OP,'operation_guard');
    $sha=(string)getenv('MATCH_SOURCE_SHA');rq(preg_match('/^[a-f0-9]{40}$/D',$sha)===1,'source_guard');
    $p=plan();$dir=(string)getenv('HOME').'/.anytoour-match/operations/'.OP;
    $rp=$dir.'/reservation.json';rq(is_file($rp)&&!is_link($rp)&&realpath($rp)===$rp&&filesize($rp)<16384,'reservation_path');
    $res=json_decode((string)file_get_contents($rp),true,32,JSON_THROW_ON_ERROR);
    rq(($res['operation_id']??'')===OP&&($res['source_sha']??'')===$sha&&($res['state']??'')==='reserved_before_db_access','reservation_binding');
    rq(($res['preflight_result_sha256']??'')===PREFLIGHT_RESULT_SHA256&&($res['plan_sha256']??'')===PLAN_SHA256,'reservation_pins');

    $db=null;$committed=false;$commitAttempted=false;$valid=[];$holds=[];$readback=[];
    $out=['operation_id'=>OP,'source_sha'=>$sha,'state'=>'failed_no_replay','no_replay'=>true,'preflight_operation'=>PREFLIGHT_OP,
          'preflight_result_sha256'=>PREFLIGHT_RESULT_SHA256,'plan_sha256'=>PLAN_SHA256,'planned_count'=>11,
          'supplier_calls'=>0,'tourvisor_calls'=>0,'booking_calls'=>0,'lead_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,
          'post_commit_readback_verified'=>false];
    ob_start();
    try{
        $root=realpath(getcwd());rq(is_string($root)&&basename($root)==='anytoour.ru','project_guard');
        $bp=$root.(is_file($root.'/data/db-v1.php')?'/data/db-v1.php':'/v2/data/db-v1.php');rq(realpath($bp)===$bp&&!is_link($bp),'bootstrap_path');
        require_once $bp;$db=v2_data_db();$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $db->exec('SET SESSION innodb_lock_wait_timeout=20');$db->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$db->beginTransaction();

        $eng=q($db,"SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('andromeda_hotel_identities','catalog_hotels')");
        rq(count($eng)===2,'table_contract');foreach($eng as $t){ $t=array_change_key_case($t,CASE_LOWER);rq(strtoupper((string)$t['engine'])==='INNODB','nontransactional_table');}
        $extra=q($db,"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (LOWER(TABLE_NAME) LIKE '%hotel%' OR LOWER(TABLE_NAME) LIKE '%identity%') AND LOWER(TABLE_NAME) REGEXP 'manual|exclu|decision|review' AND LOWER(TABLE_NAME) NOT LIKE 'anex_%'");
        rq(!$extra,'separate_protection_contract_requires_review');

        foreach($p as $r){
            $why=[];$ns=$r['ns'];$eid=$r['external_id'];$aid=$r['andromeda_id'];$lid=$r['local_id'];$cid=$r['country_id'];
            $src=q($db,"SELECT supplier_namespace,external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=? FOR UPDATE",[$ns,$eid]);
            if(count($src)!==1){$holds[]=['ns'=>$ns,'external_id'=>$eid,'local_id'=>$lid,'reasons'=>['source_missing_or_duplicate']];continue;}
            $s=$src[0];$prior=(string)$s['evidence_sha256'];$raw=(string)$s['evidence_json'];
            if($s['decision_status']!=='pending'||$s['local_hotel_id']!==null)$why[]='not_pending_null';
            if(!preg_match('/^[a-f0-9]{64}$/D',$prior)||!hash_equals($prior,hash('sha256',$raw)))$why[]='source_evidence_digest_drift';
            try{$e=json_decode($raw,true,64,JSON_THROW_ON_ERROR);}catch(Throwable $x){$e=[];$why[]='source_evidence_json';}
            if(!is_array($e)){$e=[];$why[]='source_evidence_shape';}
            if(prot($e))$why[]='source_protected';
            $b=bridge($e);
            if(!$b|| (string)($b['andromeda_hotel_id']??'')!==$aid || (int)($b['country_id']??0)!==$cid || (string)($b['hotel_name']??'')!==$r['name'])$why[]='provider_bridge_changed';

            $ar=q($db,"SELECT external_hotel_id,local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace='andromeda_catalog' AND external_hotel_id=? FOR UPDATE",[$aid]);
            if(count($ar)!==1){$why[]='authority_missing';$a=null;}else{$a=$ar[0];if($a['decision_status']!=='accepted'||$a['local_hotel_id']===null||(int)$a['local_hotel_id']!==$lid)$why[]='authority_changed';}
            $ae=[];if($a!==null){try{$ae=json_decode((string)$a['evidence_json'],true,64,JSON_THROW_ON_ERROR);}catch(Throwable $x){$why[]='authority_evidence_json';}
                if(!is_array($ae)){$ae=[];$why[]='authority_evidence_shape';} if(prot($ae))$why[]='authority_protected';}

            $hr=q($db,"SELECT id,country_id,name,latitude,longitude FROM catalog_hotels WHERE id=? AND is_active=1 FOR UPDATE",[$lid]);
            if(count($hr)!==1){$why[]='target_inactive_or_missing';$h=null;}else{$h=$hr[0];if((int)$h['country_id']!==$cid)$why[]='target_country_changed';}

            $busy=q($db,"SELECT external_hotel_id FROM andromeda_hotel_identities WHERE supplier_namespace=? AND decision_status='accepted' AND local_hotel_id=? AND external_hotel_id<>? FOR UPDATE",[$ns,$lid,$eid]);
            if($busy)$why[]='same_namespace_target_occupied';

            $coord=[];$tp=[];
            if($h!==null&&is_numeric($h['latitude'])&&is_numeric($h['longitude'])){$la=(float)$h['latitude'];$lo=(float)$h['longitude'];if(is_finite($la)&&is_finite($lo)&&abs($la)<=90&&abs($lo)<=180&&($la!=0.0||$lo!=0.0))$tp=[$la,$lo];}
            $sp=[];points($e,$sp);$ap=[];points($ae,$ap);
            if($tp){
                foreach([['source',$sp],['authority',$ap]] as $group)foreach($group[1] as $pt){ $d=km($pt,$tp);$coord[]=['kind'=>$group[0],'km'=>round($d,3)];if($d>5.0)$why[]=$group[0].'_coordinate_conflict_gt5km'; }
            }
            if($why){$holds[]=['ns'=>$ns,'external_id'=>$eid,'local_id'=>$lid,'andromeda_id'=>$aid,'reasons'=>array_values(array_unique($why))];continue;}

            $e['operator_exact_andromeda_acceptance']=['operation_id'=>OP,'source_sha'=>$sha,'preflight_operation'=>PREFLIGHT_OP,
                'preflight_result_sha256'=>PREFLIGHT_RESULT_SHA256,'plan_sha256'=>PLAN_SHA256,'prior_evidence_sha256'=>$prior,
                'andromeda_hotel_id'=>$aid,'andromeda_authority_evidence_sha256'=>(string)$a['evidence_sha256'],
                'local_hotel_id'=>$lid,'country_id'=>$cid,'coordinate_distances_km'=>$coord,'current_guards_verified'=>true];
            $new=js($e);$valid[]=['ns'=>$ns,'external_id'=>$eid,'local_id'=>$lid,'andromeda_id'=>$aid,'prior_evidence_sha256'=>$prior,
                                  'new_evidence_sha256'=>hash('sha256',$new),'evidence_json'=>$new];
        }

        $public=array_map(static function(array $x):array{unset($x['evidence_json']);return $x;},$valid);
        wr($dir.'/precommit.json',['operation_id'=>OP,'source_sha'=>$sha,'state'=>'validated_before_commit','planned_count'=>11,
                                  'valid_count'=>count($valid),'held_count'=>count($holds),'rows'=>$public,'holds'=>$holds,'no_replay'=>true]);

        $up=$db->prepare("UPDATE andromeda_hotel_identities SET local_hotel_id=?,decision_status='accepted',evidence_sha256=?,evidence_json=? WHERE supplier_namespace=? AND external_hotel_id=? AND decision_status='pending' AND local_hotel_id IS NULL AND evidence_sha256=?");
        foreach($valid as $w){$up->execute([$w['local_id'],$w['new_evidence_sha256'],$w['evidence_json'],$w['ns'],$w['external_id'],$w['prior_evidence_sha256']]);rq($up->rowCount()===1,'conditional_write_count');}

        $commitAttempted=true;$db->commit();$committed=true;
        foreach($valid as $w){
            $rr=q($db,"SELECT local_hotel_id,decision_status,evidence_sha256,evidence_json FROM andromeda_hotel_identities WHERE supplier_namespace=? AND external_hotel_id=?",[$w['ns'],$w['external_id']]);
            rq(count($rr)===1,'readback_count');$x=$rr[0];$ee=json_decode((string)$x['evidence_json'],true,64,JSON_THROW_ON_ERROR);
            rq((int)$x['local_hotel_id']===$w['local_id']&&$x['decision_status']==='accepted'&&hash_equals($w['new_evidence_sha256'],(string)$x['evidence_sha256'])&&
               hash_equals($w['new_evidence_sha256'],hash('sha256',(string)$x['evidence_json']))&&($ee['operator_exact_andromeda_acceptance']['operation_id']??'')===OP,'post_commit_mismatch');
            $readback[]=['ns'=>$w['ns'],'external_id'=>$w['external_id'],'local_id'=>$w['local_id'],'andromeda_id'=>$w['andromeda_id'],'decision_status'=>'accepted','evidence_sha256'=>$x['evidence_sha256']];
        }
        $pending=[];$pt=0;foreach(q($db,"SELECT supplier_namespace,COUNT(*) c FROM andromeda_hotel_identities WHERE supplier_namespace IN ('andromeda_catalog','operator_315','operator_342','operator_5') AND decision_status='pending' AND local_hotel_id IS NULL GROUP BY supplier_namespace ORDER BY supplier_namespace") as $x){$pending[(string)$x['supplier_namespace']]=(int)$x['c'];$pt+=(int)$x['c'];}
        $out['state']=count($valid)>0?'completed_committed':'completed_no_write';$out['valid_count']=count($valid);$out['held_count']=count($holds);$out['holds']=$holds;
        $out['committed_count']=count($valid);$out['database_writes']=count($valid);$out['mapping_writes']=count($valid);$out['post_commit_readback_verified']=count($readback)===count($valid);
        $out['readback']=$readback;$out['post_pending_by_namespace']=$pending;$out['post_pending_total']=$pt;$out['completed_at_utc']=gmdate('c');
    }catch(Throwable $e){
        if($db instanceof PDO&&$db->inTransaction()){$db->rollBack();}
        if($commitAttempted&&!$committed)$out['state']='unknown_commit_outcome_no_replay';
        elseif($committed)$out['state']='committed_readback_failed_no_replay';
        $out['error_code']=preg_match('/^[a-z0-9_]{2,120}$/i',$e->getMessage())?$e->getMessage():'sanitized_failure';
        $out['valid_count']=count($valid);$out['held_count']=count($holds);$out['committed_count']=$committed?count($valid):0;
        $out['database_writes']=$committed?count($valid):0;$out['mapping_writes']=$committed?count($valid):0;$out['holds']=$holds;
    }
    while(ob_get_level())ob_end_clean();
    $rh=wr($dir.'/result.json',$out);
    wr($dir.'/receipt.json',['operation_id'=>OP,'source_sha'=>$sha,'state'=>$out['state'],'result_sha256'=>$rh,'readback_verified'=>$out['post_commit_readback_verified']??false,
                            'no_replay'=>true,'database_writes'=>$out['database_writes'],'mapping_writes'=>$out['mapping_writes'],'supplier_calls'=>0,'tourvisor_calls'=>0]);
    echo js(['state'=>$out['state'],'planned_count'=>11,'valid_count'=>$out['valid_count']??0,'held_count'=>$out['held_count']??0,'committed_count'=>$out['committed_count']??0,
             'post_pending_by_namespace'=>$out['post_pending_by_namespace']??null,'post_pending_total'=>$out['post_pending_total']??null,'result_sha256'=>$rh]);
    if(!in_array($out['state'],['completed_committed','completed_no_write'],true)||!($out['post_commit_readback_verified']??false))exit(2);
}
if(in_array('--self-test',$argv??[],true)){
    $p=plan();rq(count($p)===11,'self_plan');rq(prot(['manual'=>true])&&!prot(['manual'=>false]),'self_prot');
    $d=km([36.713018,31.563078],[36.713018,31.563078]);rq($d<0.001,'self_geo');echo "3 exact-apply self-tests PASS\n";exit;
}
main();
