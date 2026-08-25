<?php
/** V2-only lead adapter. Writes selected Tourvisor leads directly to the existing AnyTour Bitrix lead iblock. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const V2_LEAD_IBLOCK_ID = 4;
const V2_LEAD_SECTION_ID = 12;
const V2_LEAD_STATUS_ID = 9;
const V2_LEAD_SOURCE_ID = 26;
const V2_LEAD_IDEMPOTENCY_TTL = 600;

function lead_out(array $data,int $status=200):void{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function lead_text($value,int $max=500):string{$value=trim(preg_replace('/\s+/u',' ',(string)$value));return mb_substr($value,0,$max,'UTF-8');}
function lead_phone($value):string{$digits=preg_replace('/\D+/','',trim((string)$value));if(strlen($digits)===11&&$digits[0]==='8')$digits='7'.substr($digits,1);return $digits!==''?'+'.$digits:'';}
function lead_money($value):?int{if($value===null||$value==='')return null;$n=(int)round((float)$value);return $n>0?$n:null;}
function lead_idempotency_key(array $lead):string{return hash('sha256',implode('|',[(string)($lead['phone']??''),(string)($lead['tourId']??''),(string)($lead['searchId']??'')]));}
function lead_idempotency_lock(string $key):array{
    $dir=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'anytour-v2-leads';
    if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return ['ok'=>false,'error'=>'Idempotency storage unavailable'];
    $path=$dir.DIRECTORY_SEPARATOR.$key.'.json';$fh=@fopen($path,'c+');if(!$fh)return ['ok'=>false,'error'=>'Idempotency lock unavailable'];
    if(!flock($fh,LOCK_EX)){fclose($fh);return ['ok'=>false,'error'=>'Idempotency lock failed'];}
    rewind($fh);$raw=stream_get_contents($fh);$stored=json_decode((string)$raw,true);
    if(is_array($stored)&&!empty($stored['leadId'])&&!empty($stored['time'])&&((int)$stored['time']+V2_LEAD_IDEMPOTENCY_TTL)>=time())return ['ok'=>true,'duplicate'=>true,'leadId'=>(int)$stored['leadId'],'fh'=>$fh,'path'=>$path];
    return ['ok'=>true,'duplicate'=>false,'fh'=>$fh,'path'=>$path];
}
function lead_idempotency_store(array $lock,int $leadId):void{
    $fh=$lock['fh']??null;if(!is_resource($fh))return;rewind($fh);ftruncate($fh,0);fwrite($fh,json_encode(['leadId'=>$leadId,'time'=>time()],JSON_UNESCAPED_SLASHES));fflush($fh);flock($fh,LOCK_UN);fclose($fh);
}
function lead_idempotency_release(array $lock):void{$fh=$lock['fh']??null;if(is_resource($fh)){flock($fh,LOCK_UN);fclose($fh);}}

function lead_build(array $data):array{
    $phone=lead_phone($data['phone']??'');
    $tourId=lead_text($data['tourId']??'',200);
    $errors=[];
    if(strlen(preg_replace('/\D+/','',$phone))<10)$errors['phone']='Valid phone is required';
    if($tourId==='')$errors['tourId']='tourId is required';
    if($errors)return ['errors'=>$errors];

    $lead=[
        'name'=>lead_text($data['name']??'',120),'phone'=>$phone,'tourId'=>$tourId,'searchId'=>lead_text($data['searchId']??'',80),
        'hotel'=>lead_text($data['hotel']??'',240),'country'=>lead_text($data['country']??'',120),'region'=>lead_text($data['region']??'',160),
        'departure'=>lead_text($data['departure']??'',160),'date'=>lead_text($data['date']??'',40),'nights'=>isset($data['nights'])?(int)$data['nights']:null,
        'adults'=>isset($data['adults'])?(int)$data['adults']:null,'childs'=>isset($data['childs'])?(int)$data['childs']:null,
        'meal'=>lead_text($data['meal']??'',160),'roomType'=>lead_text($data['roomType']??'',240),'placement'=>lead_text($data['placement']??'',160),
        'operator'=>lead_text($data['operator']??'',160),'price'=>lead_money($data['price']??null),'currency'=>lead_text($data['currency']??'RUB',12)?:'RUB',
        'flight'=>lead_text($data['flight']??'',2500),'flightPrice'=>lead_money($data['flightPrice']??null),'flightFuel'=>lead_money($data['flightFuel']??null),
        'comment'=>lead_text($data['comment']??'',1000),'page'=>lead_text($data['page']??'',500),
        'yclid'=>lead_text($data['yclid']??'',100),'yaclient'=>lead_text($data['yaclient']??'',100),
        'utm_source'=>lead_text($data['utm_source']??'',160),'utm_medium'=>lead_text($data['utm_medium']??'',160),'utm_campaign'=>lead_text($data['utm_campaign']??'',160),
        'utm_content'=>lead_text($data['utm_content']??'',160),'utm_term'=>lead_text($data['utm_term']??'',160),
    ];

    $people='Взрослых: '.max(1,(int)$lead['adults']);
    if((int)$lead['childs']>0)$people.='; Детей: '.(int)$lead['childs'];
    $details=[
        'Имя: '.$lead['name'],'Телефон: '.$lead['phone'],'Город вылета: '.$lead['departure'],'Страна: '.$lead['country'],
        'Туристы: '.$people,'Даты вылета: '.$lead['date'],'Количество ночей: '.(string)$lead['nights'],'V2 Tourvisor tourId: '.$lead['tourId'],
    ];
    if($lead['searchId'])$details[]='searchId: '.$lead['searchId'];
    if($lead['hotel'])$details[]='Отель: '.$lead['hotel'];
    if($lead['region'])$details[]='Регион: '.$lead['region'];
    if($lead['meal'])$details[]='Питание: '.$lead['meal'];
    if($lead['roomType'])$details[]='Номер: '.$lead['roomType'];
    if($lead['placement'])$details[]='Размещение: '.$lead['placement'];
    if($lead['operator'])$details[]='Оператор: '.$lead['operator'];
    if($lead['price'])$details[]='Цена тура: '.$lead['price'].' '.$lead['currency'];
    if($lead['flight'])$details[]='Выбранный перелёт: '.$lead['flight'];
    if($lead['flightPrice'])$details[]='Цена варианта перелёта: '.$lead['flightPrice'].' '.$lead['currency'];
    if($lead['flightFuel'])$details[]='Топливный сбор: '.$lead['flightFuel'].' '.$lead['currency'];
    if($lead['comment'])$details[]='Комментарий пользователя: '.$lead['comment'];
    if($lead['page'])$details[]='Страница: '.$lead['page'];

    $createdAt=date('d.m.Y H:i:s');
    $properties=[
        'DATE'=>$createdAt,'NAME'=>$lead['name'],'PHONE'=>$lead['phone'],'COMMENTS'=>implode('; ',$details),
        'DEPARTURE'=>$lead['departure'],'PEOPLE'=>$people,'COUNTRY'=>$lead['country'],'STATUS'=>V2_LEAD_STATUS_ID,'SOURCE'=>V2_LEAD_SOURCE_ID,
        'YA_CLIENT'=>$lead['yaclient'],'YA_CLID'=>$lead['yclid'],'YA_UTM_SOURCE'=>$lead['utm_source'],'YA_UTM_MEDIUM'=>$lead['utm_medium'],
        'YA_UTM_CAMPAIGN'=>$lead['utm_campaign'],'YA_UTM_CONTENT'=>$lead['utm_content'],'YA_UTM_TERM'=>$lead['utm_term'],
    ];
    if($lead['meal'])$properties['MEAL']=$lead['meal'];
    if($lead['nights'])$properties['NIGHTS']=(string)$lead['nights'];
    return ['lead'=>$lead,'properties'=>$properties,'element'=>[
        'IBLOCK_ID'=>V2_LEAD_IBLOCK_ID,'IBLOCK_SECTION_ID'=>V2_LEAD_SECTION_ID,'PROPERTY_VALUES'=>$properties,
        'NAME'=>'Заявка от '.$createdAt,'ACTIVE'=>'Y',
    ]];
}

function lead_bootstrap():array{
    $docRoot=$_SERVER['DOCUMENT_ROOT']??'';$prolog=$docRoot.'/bitrix/modules/main/include/prolog_before.php';
    if($docRoot===''||!is_file($prolog))return ['ok'=>false,'error'=>'Bitrix bootstrap not found'];
    require_once $prolog;
    $siteConf=$docRoot.'/site_conf.php';
    if(is_file($siteConf))require_once $siteConf;
    if(!class_exists('Bitrix\\Main\\Loader'))return ['ok'=>false,'error'=>'Bitrix Loader unavailable'];
    if(!\Bitrix\Main\Loader::includeModule('iblock'))return ['ok'=>false,'error'=>'iblock module unavailable'];
    if(!class_exists('CIBlockElement'))return ['ok'=>false,'error'=>'CIBlockElement unavailable'];
    return ['ok'=>true];
}
function lead_project_marker(){
    if(class_exists('CSiteParams')&&property_exists('CSiteParams','isAnytourOnline'))return \CSiteParams::$isAnytourOnline;
    return null;
}

if($_SERVER['REQUEST_METHOD']==='GET'){
    if(isset($_GET['selftest'])){
        $sample=lead_build(['name'=>'V2 Selftest','phone'=>'8 (999) 123-45-67','tourId'=>'tv-test-1','searchId'=>'42','hotel'=>'Test Hotel',
            'country'=>'Турция','region'=>'Анталья','departure'=>'Москва','date'=>'15.09.2026','nights'=>7,'adults'=>2,'childs'=>0,'meal'=>'AI',
            'roomType'=>'Standard','placement'=>'DBL','operator'=>'Test Operator','price'=>150000,
            'flight'=>'Туда: 2S172 SVO 14:00 → DLM 18:05; Обратно: 2S171 DLM 09:30 → SVO 13:40','flightPrice'=>89859,'flightFuel'=>20804]);
        $p=$sample['properties']??[];$checks=[
            'validPhone'=>(($sample['lead']['phone']??'')==='+79991234567'),'iblock'=>(($sample['element']['IBLOCK_ID']??0)===4),
            'section'=>(($sample['element']['IBLOCK_SECTION_ID']??0)===12),'status'=>(($p['STATUS']??0)===9),'source'=>(($p['SOURCE']??0)===26),
            'flightStored'=>(strpos((string)($p['COMMENTS']??''),'2S172')!==false&&strpos((string)($p['COMMENTS']??''),'2S171')!==false),
            'idempotencyKeyStable'=>(lead_idempotency_key($sample['lead']??[])===lead_idempotency_key($sample['lead']??[])),
        ];
        lead_out(['ok'=>!in_array(false,$checks,true),'mode'=>'self-test','writes'=>false,'checks'=>$checks,'config'=>['iblock'=>4,'section'=>12,'status'=>9,'source'=>26,'idempotencyTtl'=>V2_LEAD_IDEMPOTENCY_TTL]]);
    }
    lead_out(['ok'=>true,'adapter'=>'v2-direct-bitrix-lead','mode'=>'live','writes'=>true,'config'=>['iblock'=>4,'section'=>12,'status'=>9,'source'=>26,'idempotencyTtl'=>V2_LEAD_IDEMPOTENCY_TTL],'selftest'=>'?selftest=1']);
}
if($_SERVER['REQUEST_METHOD']!=='POST')lead_out(['ok'=>false,'error'=>'Method not allowed'],405);
$raw=file_get_contents('php://input');$data=json_decode((string)$raw,true);if(!is_array($data))$data=$_POST;
$built=lead_build($data);if(!empty($built['errors']))lead_out(['ok'=>false,'error'=>'Validation failed','fields'=>$built['errors']],422);
$key=lead_idempotency_key($built['lead']);$lock=lead_idempotency_lock($key);if(empty($lock['ok']))lead_out(['ok'=>false,'error'=>$lock['error']??'Idempotency failure'],500);if(!empty($lock['duplicate'])){lead_idempotency_release($lock);lead_out(['ok'=>true,'mode'=>'live','writes'=>false,'duplicate'=>true,'leadId'=>(int)$lock['leadId'],'source'=>26]);}
$boot=lead_bootstrap();if(empty($boot['ok'])){lead_idempotency_release($lock);lead_out(['ok'=>false,'error'=>$boot['error']??'Bitrix bootstrap failed'],500);}
$projectMarker=lead_project_marker();
if($projectMarker!==null)$built['element']['PROPERTY_VALUES']['IS_ANYTOUR_ONLINE']=$projectMarker;
$el=new \CIBlockElement();$leadId=$el->Add($built['element']);
if(!$leadId){lead_idempotency_release($lock);lead_out(['ok'=>false,'error'=>'Bitrix lead insert failed','detail'=>(string)$el->LAST_ERROR],500);}
lead_idempotency_store($lock,(int)$leadId);
lead_out(['ok'=>true,'mode'=>'live','writes'=>true,'duplicate'=>false,'leadId'=>(int)$leadId,'source'=>26,'isAnyTourOnline'=>$projectMarker]);
