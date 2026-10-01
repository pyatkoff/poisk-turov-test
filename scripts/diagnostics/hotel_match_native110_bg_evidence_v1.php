<?php
declare(strict_types=1);
require_once __DIR__.'/hotel_match_native110_current_v1.php';
const BE110_OP='int-andromeda-match-native110-bg-evidence-20261001-v1';
const BE110_INPUT_SHA='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e';

/** Preserve the original URL privately; this projection never proves a namespace bridge. */
function be110_link(string $field,string $url): ?array {
    $p=parse_url($url);
    if(!is_array($p)||($p['scheme']??'')!=='https'||!preg_match('/^(?:[a-z0-9-]+\.)*bgoperator\.ru$/D',strtolower($p['host']??''))
        ||isset($p['user'])||isset($p['pass'])||strlen($url)>16384)return null;
    $selectors=[];$signed=false;
    foreach(explode('&',$p['query']??'') as $part){
        $kv=explode('=',$part,2);$key=urldecode($kv[0]);$value=urldecode($kv[1]??'');
        if(preg_match('/token|sign|password|secret|auth/i',$key))$signed=true;
        if(!preg_match('/^(?:id|tid|hotel|hotelid|hotel_id|hotels|hotels\[\]|hotellist|i1hotelinc)$/iD',$key))continue;
        $tokens=preg_split('/[,;|\s]+/',$value,-1,PREG_SPLIT_NO_EMPTY);$ids=[];$opaque=0;
        if(count($tokens)>128)return null;
        foreach($tokens as $token)if(preg_match('/^[1-9][0-9]{0,31}$/D',$token))$ids[]=$token;else ++$opaque;
        $selectors[]=['parameter'=>$key,'positive_tokens'=>$ids,'opaque_tokens'=>$opaque,'value_sha256'=>hash('sha256',$value)];
    }
    if(count($selectors)>32)return null;
    return ['source_field'=>$field,'host'=>strtolower($p['host']),'url_sha256'=>hash('sha256',$url),
        'signed_parameters_present'=>$signed,'hotel_selectors'=>$selectors];
}

function be110_fields(array $row): array {
    $out=['top_fields'=>[],'original_fields'=>[],'location_fields'=>[],'bg_links'=>[]];
    foreach(['row'=>$row,'original'=>is_array($row['original']??null)?$row['original']:[]] as $prefix=>$values){
        $keys=[];
        foreach($values as $key=>$value){
            if(!is_string($key)||!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]{0,79}$/D',$key))continue;
            $keys[]=$key;$field=$prefix.'.'.$key;
            if(preg_match('/^(?:town|city|state|country|latitude|longitude|lat|lng|lon|townkey|townname|hotelLat|hotelLng|hotelLatitude|hotelLongitude|hotelTown|hotelCountry)$/iD',$key)
                &&(is_int($value)||is_float($value)||is_string($value)||$value===null)){
                if(is_string($value)&&!preg_match("~^[\\pL\\pN\\s.,+'’()/_-]{1,180}$~uD",$value))continue;
                $out['location_fields'][]=['source_field'=>$field,'value'=>$value];
            }
            if(is_string($value)&&preg_match('/^(?:(?:hotel|object).*(?:url|link)|url|link)$/iD',$key)){
                $link=be110_link($field,$value);if($link!==null)$out['bg_links'][]=$link;
            }
        }
        sort($keys,SORT_STRING);$out[$prefix==='row'?'top_fields':'original_fields']=$keys;
    }
    return $out;
}

/** An indexed read of new original hotel URL/location fields for all18 BG cases. */
function be110_review(string $root,array $manifest,array $input): array {
    nc110_scope($manifest);$rows=[];$files=[];
    foreach($manifest['rows'] as $request){
        if($request['catalog_id']===NC110_PROTECTED)continue;
        foreach($request['tv_candidates'] as $candidate){
            if($candidate['operator']!=='bg')continue;
            $cat=$request['catalog_id'];$n=$candidate['native_id'];$index=count($rows);
            $row=['catalog_id'=>$cat,'tv_hotel_id'=>$candidate['tv_hotel_id'],'samo_native_id'=>$n,
                'tv_native_id'=>$candidate['tv_native_id'],'raw_references_examined'=>0,'top_fields'=>[],
                'original_fields'=>[],'location_fields'=>[],'bg_links'=>[],'failures'=>[],'safe_to_write_now'=>false];
            foreach($input['saved_evidence']['source_facts'][$cat]??[] as $f){
                if($f['namespace']!=='operator_115'||$f['native_id']!==$n)continue;
                foreach($f['raw']['references'] as $ref){
                    w76_need($ref['verified']===true&&str_starts_with($ref['source_file'],'operations/'),'bg_saved_reference');
                    $files[substr($ref['source_file'],11)][]=['index'=>$index,'catalog'=>$cat,'native'=>$n,'ref'=>$ref];
                }
            }
            $rows[]=$row;
        }
    }
    w76_need(count($rows)===18&&count($files)<=1000,'bg_exact_scope');$bytes=0;$read=0;ksort($files);
    foreach($files as $relative=>$requests){
        $file=null;$why=null;
        try{
            $path=$root.'/'.$relative;$size=is_file($path)?filesize($path):0;
            w76_need($bytes+$size<=536870912&&$read<1000,'bg_bytes_cap');
            $file=nc110_raw_file($root,$relative);$bytes+=$file['bytes'];w76_need($bytes<=536870912,'bg_bytes_cap');++$read;
        }catch(Throwable $e){$file=null;$why='raw_file_unavailable';}
        foreach($requests as $r){
            $i=$r['index'];++$rows[$i]['raw_references_examined'];
            if($file===null){$rows[$i]['failures'][]=$why;continue;}
            try{
                w76_need(hash_equals($r['ref']['sha256'],$file['sha256']),'bg_raw_digest');
                $raw=w78_ptr($file['raw'],$r['ref']['json_pointer']);w76_need(nc110_fact($raw,$r['catalog'],'operator_115',$r['native']),'bg_raw_identity');
                $fields=be110_fields($raw);
                foreach($fields as $key=>$values)$rows[$i][$key]=array_merge($rows[$i][$key],$values);
            }catch(Throwable $e){$rows[$i]['failures'][]='raw_reference_changed';}
        }
    }
    foreach($rows as &$row){
        if($row['raw_references_examined']===0)$row['failures'][]='raw_evidence_reference_missing';
        foreach(['top_fields','original_fields','failures'] as $key){$row[$key]=array_values(array_unique($row[$key]));sort($row[$key]);}
        foreach(['location_fields','bg_links'] as $key){$unique=[];foreach($row[$key] as $value)$unique[w76_hash($value)]=$value;$row[$key]=array_values($unique);}
        w76_need(count($row['top_fields'])<=256&&count($row['original_fields'])<=256&&count($row['location_fields'])<=128&&count($row['bg_links'])<=128,'bg_projection_cap');
    }unset($row);
    return ['rows'=>$rows,'raw_files_read'=>$read,'raw_bytes_read'=>$bytes];
}

function be110_main(array $args): void {
    w76_need(count($args)===2&&$args[1]==='--read-saved','bg_evidence_disabled');
    $dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    w76_need(realpath($dir)===$dir&&basename($dir)===BE110_OP&&preg_match('/^[a-f0-9]{40}$/D',$head)===1,'bg_execution_scope');
    $reservation=pm1_read(dirname($dir),BE110_OP.'/reservation.json',null,1048576);
    w76_need(($reservation['operation']??null)===BE110_OP&&($reservation['source_sha']??null)===$head
        &&($reservation['input_sha256']??null)===BE110_INPUT_SHA&&($reservation['maximum_writes']??null)===0
        &&($reservation['provider_http_calls']??null)===0,'bg_reservation');
    w76_need(!file_exists($dir.'/result.json'),'bg_no_replay');
    $input=pm1_read(dirname($dir),NC110_OP.'/native110-current-manifest.json',BE110_INPUT_SHA);
    w76_need(($input['operation']??null)===NC110_OP&&($input['batch']??null)===NC110_BATCH
        &&($input['database_writes']??null)===0&&($input['mapping_writes']??null)===0,'bg_input');
    $path=__DIR__.'/fixtures/hotel_match_native110_current_v1.json';w76_need(hash_file('sha256',$path)===NC110_MANIFEST_SHA,'bg_manifest');
    $out=be110_review(dirname($dir),json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR),$input);
    $out+=['state'=>'completed_bg_original_fields_review','operation'=>BE110_OP,'source_sha'=>$head,'batch'=>NC110_BATCH,
        'input_sha256'=>BE110_INPUT_SHA,'provider_http_calls'=>0,'database_reads'=>0,'database_writes'=>0,'mapping_writes'=>0,
        'safe_to_write_now'=>false,'no_replay'=>true];
    w76_save($dir.'/result.json',$out);echo w76_json($out)."\n";
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)be110_main($argv);
