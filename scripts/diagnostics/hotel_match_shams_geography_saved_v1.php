<?php
declare(strict_types=1);
/** Missing SHAMS geography fields only. No database, provider or acceptance entrypoint. */
require_once __DIR__.'/hotel_match_native110_current_v1.php';

const SG110_OP='int-andromeda-match-shams-geo-evidence-20261001-v1';
const SG110_INPUT_SHA='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e';
const SG110_INPUT_SOURCE='9c82d143ccd6173ade0d6b1d52c6e3a41657d460';

function sg110_fields(array $row,string $prefix): array {
    $keys=[];$locations=[];
    foreach($row as $key=>$value){
        if(!is_string($key)||!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]{0,79}$/D',$key))continue;
        $keys[]=$key;
        if(!preg_match('/^(?:town|city|state|country|country_name|region_name|subregion_name|latitude|longitude|lat|lng|lon|townKey|townName|stateName|countryName|cityName|hotelLat|hotelLng|hotelLatitude|hotelLongitude|hotelTown|hotelCountry)$/iD',$key))continue;
        if(is_float($value)&&!is_finite($value))continue;
        if(!is_int($value)&&!is_float($value)&&!is_string($value)&&$value!==null)continue;
        if(is_string($value)&&!preg_match("~^[\\pL\\pN\\s.,+'’()/_-]{1,180}$~uD",$value))continue;
        $locations[]=['source_field'=>$prefix.'.'.$key,'value'=>$value];
    }
    sort($keys,SORT_STRING);
    w76_need(count($keys)<=256&&count($locations)<=64,'shams_fields_cap');
    return ['field_names'=>$keys,'location_fields'=>$locations];
}

function sg110_review(string $root,array $input): array {
    w76_need(($input['schema']??null)==='native110-current-review/1'&&($input['operation']??null)===NC110_OP
        &&($input['source_sha']??null)===SG110_INPUT_SOURCE&&($input['batch']??null)===NC110_BATCH
        &&($input['provider_http_calls']??null)===0&&($input['database_writes']??null)===0
        &&($input['mapping_writes']??null)===0&&($input['safe_to_write_now']??null)===false
        &&($input['no_replay']??null)===true&&count($input['rows']??[])===110,'shams_input_binding');
    $source=array_values(array_filter($input['rows'],fn($r)=>is_array($r)&&($r['catalog_id']??null)==='9501'));
    w76_need(count($source)===1&&($source[0]['state']??null)==='current_review_observed','shams_source_binding');
    $targets=array_values(array_filter($source[0]['targets']??[],fn($t)=>is_array($t)&&($t['kind']??null)==='tv_candidate'&&($t['id']??null)===420));
    w76_need(count($targets)===1&&is_array($targets[0]['catalog_record']??null)
        &&(int)($targets[0]['catalog_record']['id']??0)===420,'shams_target_binding');
    $target=array_intersect_key($targets[0]['catalog_record'],array_flip(['country_name','region_name','subregion_name','latitude','longitude']));
    $targetFields=sg110_fields($target,'saved_target');
    $facts=$input['saved_evidence']['source_facts']['9501']??[];$files=[];$factsSeen=[];
    foreach($facts as $fact){
        $ns=$fact['namespace']??null;$native=$fact['native_id']??null;
        w76_need(($ns==='operator_5'&&$native==='835')||($ns==='operator_342'&&$native==='24402'),'shams_native_scope');
        w76_need(!isset($factsSeen[$ns])&&($fact['raw']['raw_verified']??null)===true,'shams_verified_fact');$factsSeen[$ns]=true;
        foreach($fact['raw']['references']??[] as $ref){
            w76_need(($ref['verified']??null)===true&&is_string($ref['source_file']??null)
                &&str_starts_with($ref['source_file'],'operations/')&&w76_sha($ref['sha256']??null)
                &&is_string($ref['json_pointer']??null)&&preg_match('~^/(?:PRICES|prices)/[0-9]{1,8}$~D',$ref['json_pointer']),'shams_reference_binding');
            $files[substr($ref['source_file'],11)][]=['namespace'=>$ns,'native_id'=>$native,'reference'=>$ref];
        }
    }
    w76_need(count($factsSeen)===2&&count($files)>0&&count($files)<=8,'shams_file_scope');
    $out=[];$bytes=0;$read=0;$references=0;ksort($files,SORT_STRING);
    foreach($files as $relative=>$requests){
        $file=null;
        try{
            $size=is_file($root.'/'.$relative)&&!is_link($root.'/'.$relative)?filesize($root.'/'.$relative):0;
            w76_need($read<8&&$bytes+$size<=67108864,'shams_bytes_cap');
            $file=nc110_raw_file($root,$relative);$bytes+=$file['bytes'];++$read;
        }
        catch(Throwable $e){$file=null;}
        foreach($requests as $request){
            w76_need(++$references<=128,'shams_references_cap');$ref=$request['reference'];
            $row=['namespace'=>$request['namespace'],'native_id'=>$request['native_id'],'page_sha256'=>$ref['sha256'],
                'json_pointer'=>$ref['json_pointer'],'field_names'=>[],'original_field_names'=>[],'location_fields'=>[],
                'raw_verified'=>false,'failures'=>[]];
            try{
                w76_need($file!==null&&hash_equals($ref['sha256'],$file['sha256']),'shams_page_changed');
                $raw=w78_ptr($file['raw'],$ref['json_pointer']);
                w76_need(is_array($raw)&&nc110_fact($raw,'9501',$request['namespace'],$request['native_id']),'shams_raw_identity');
                $top=sg110_fields($raw,'row');$original=sg110_fields(is_array($raw['original']??null)?$raw['original']:[],'original');
                $row['field_names']=$top['field_names'];$row['original_field_names']=$original['field_names'];
                $row['location_fields']=array_merge($top['location_fields'],$original['location_fields']);$row['raw_verified']=true;
            }catch(Throwable $e){$row['failures'][]='saved_geo_reference_unverified';}
            $out[]=$row;
        }
    }
    return ['catalog_id'=>'9501','tv_hotel_id'=>420,'snapshot_captured_at_utc'=>$input['captured_at_utc']??null,
        'saved_target_geography'=>$targetFields['location_fields'],'source_history_geography_exported'=>false,
        'raw_files_read'=>$read,'raw_bytes_read'=>$bytes,'references_examined'=>$references,'references'=>$out,
        'database_reads'=>0,'provider_http_calls'=>0,'database_writes'=>0,'mapping_writes'=>0,'safe_to_write_now'=>false];
}

function sg110_main(array $args): void {
    w76_need(count($args)===2&&$args[1]==='--read-saved','shams_geo_disabled');
    $dir=(string)getenv('MATCH_OPERATION_DIR');$head=(string)getenv('MATCH_SOURCE_SHA');
    w76_need(realpath($dir)===$dir&&basename($dir)===SG110_OP&&preg_match('/^[a-f0-9]{40}$/D',$head),'shams_execution_scope');
    $reservation=pm1_read(dirname($dir),SG110_OP.'/reservation.json',null,1048576);
    w76_need(($reservation['operation']??null)===SG110_OP&&($reservation['source_sha']??null)===$head
        &&($reservation['batch']??null)===NC110_BATCH&&($reservation['input_sha256']??null)===SG110_INPUT_SHA
        &&($reservation['maximum_writes']??null)===0&&($reservation['provider_http_calls']??null)===0,'shams_reservation');
    w76_need(!file_exists($dir.'/result.json'),'shams_no_replay');
    $terminal=pm1_read(dirname($dir),NC110_OP.'/native110-current-summary.json',null,262144);
    w76_need(($terminal['state']??null)==='completed_native110_current_review'
        &&($terminal['operation']??null)===NC110_OP&&($terminal['source_sha']??null)===SG110_INPUT_SOURCE
        &&($terminal['manifest_sha256']??null)===SG110_INPUT_SHA&&($terminal['no_replay']??null)===true,'shams_terminal_input');
    $input=pm1_read(dirname($dir),NC110_OP.'/native110-current-manifest.json',SG110_INPUT_SHA);
    $result=sg110_review(dirname($dir),$input)+['schema'=>'match-shams-saved-geography/1','state'=>'completed_saved_geography_evidence',
        'operation'=>SG110_OP,'batch'=>NC110_BATCH,'source_sha'=>$head,'input_sha256'=>SG110_INPUT_SHA,'no_replay'=>true];
    w76_save($dir.'/result.json',$result);echo w76_json($result)."\n";
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)sg110_main($argv);
