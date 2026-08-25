<?php
/**
 * V2-only lead adapter, phase 1.
 * This endpoint validates and normalizes a lead payload but intentionally
 * performs no CRM/network/database writes until the adapter contract is
 * verified independently.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function lead_out(array $data,int $status=200):void{
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function lead_text($value,int $max=500):string{
    $value=trim(preg_replace('/\s+/u',' ',(string)$value));
    return mb_substr($value,0,$max,'UTF-8');
}
function lead_phone($value):string{
    $raw=trim((string)$value);
    $digits=preg_replace('/\D+/','',$raw);
    if(strlen($digits)===11&&$digits[0]==='8')$digits='7'.substr($digits,1);
    return $digits!==''?'+'.$digits:'';
}
function lead_money($value):?int{
    if($value===null||$value==='')return null;
    $n=(int)round((float)$value);
    return $n>0?$n:null;
}

if($_SERVER['REQUEST_METHOD']==='GET'){
    lead_out([
        'ok'=>true,
        'adapter'=>'v2-lead-normalizer',
        'mode'=>'dry-run',
        'writes'=>false,
        'required'=>['phone','tourId'],
    ]);
}
if($_SERVER['REQUEST_METHOD']!=='POST')lead_out(['ok'=>false,'error'=>'Method not allowed'],405);

$raw=file_get_contents('php://input');
$data=json_decode((string)$raw,true);
if(!is_array($data))$data=$_POST;

$phone=lead_phone($data['phone']??'');
$tourId=lead_text($data['tourId']??'',200);
$errors=[];
if(strlen(preg_replace('/\D+/','',$phone))<10)$errors['phone']='Valid phone is required';
if($tourId==='')$errors['tourId']='tourId is required';
if($errors)lead_out(['ok'=>false,'error'=>'Validation failed','fields'=>$errors],422);

$lead=[
    'source'=>'poisk-turov-test-v2',
    'name'=>lead_text($data['name']??'',120),
    'phone'=>$phone,
    'tourId'=>$tourId,
    'searchId'=>lead_text($data['searchId']??'',80),
    'hotel'=>lead_text($data['hotel']??'',240),
    'country'=>lead_text($data['country']??'',120),
    'region'=>lead_text($data['region']??'',160),
    'departure'=>lead_text($data['departure']??'',160),
    'date'=>lead_text($data['date']??'',40),
    'nights'=>isset($data['nights'])?(int)$data['nights']:null,
    'adults'=>isset($data['adults'])?(int)$data['adults']:null,
    'childs'=>isset($data['childs'])?(int)$data['childs']:null,
    'meal'=>lead_text($data['meal']??'',160),
    'roomType'=>lead_text($data['roomType']??'',240),
    'placement'=>lead_text($data['placement']??'',160),
    'operator'=>lead_text($data['operator']??'',160),
    'price'=>lead_money($data['price']??null),
    'currency'=>lead_text($data['currency']??'RUB',12)?:'RUB',
    'comment'=>lead_text($data['comment']??'',1000),
    'page'=>lead_text($data['page']??'',500),
    'createdAt'=>gmdate('c'),
];

lead_out([
    'ok'=>true,
    'mode'=>'dry-run',
    'writes'=>false,
    'lead'=>$lead,
]);
