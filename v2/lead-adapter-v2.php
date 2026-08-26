<?php
/** Hardened V2-only lead adapter. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__.'/lead-price-v1.php';

const V2_LEAD_IBLOCK_ID = 4;
const V2_LEAD_SECTION_ID = 12;
const V2_LEAD_STATUS_ID = 9;
const V2_LEAD_SOURCE_ID = 26;
const V2_LEAD_IDEMPOTENCY_TTL = 600;
const V2_LEAD_MAX_BODY = 65536;

function lead_out(array $data,int $status=200){http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function lead_text($value,int $max=500){$value=trim(preg_replace('/\s+/u',' ',(string)$value));return mb_substr($value,0,$max,'UTF-8');}
function lead_phone($value){$digits=preg_replace('/\D+/','',trim((string)$value));if(strlen($digits)===11&&$digits[0]==='8')$digits='7'.substr($digits,1);return $digits!==''?'+'.$digits:'';}
function lead_money($value){if($value===null||$value==='')return null;$n=(int)round((float)$value);return $n>0?$n:null;}
function lead_bool($value){return filter_var($value,FILTER_VALIDATE_BOOLEAN);}
function lead_idempotency_key(array $lead){return hash('sha256',implode('|',[(string)($lead['phone']??''),(string)($lead['tourId']??''),(string)($lead['searchId']??'')]));}
function lead_idempotency_cleanup($dir){if(mt_rand(1,50)!==1)return;$cutoff=time()-(V2_LEAD_IDEMPOTENCY_TTL*3);foreach((array)glob($dir.DIRECTORY_SEPARATOR.'*.json') as $file){if(is_file($file)&&@filemtime($file)<$cutoff)@unlink($file);}}
function lead_idempotency_lock(string $key){$dir=rtrim(sys_get_temp_dir(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'anytour-v2-leads';if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return['ok'=>false,'error'=>'Idempotency storage unavailable'];lead_idempotency_cleanup($dir);$path=$dir.DIRECTORY_SEPARATOR.$key.'.json';$fh=@fopen($path,'c+');if(!$fh)return['ok'=>false,'error'=>'Idempotency lock unavailable'];if(!flock($fh,LOCK_EX)){fclose($fh);return['ok'=>false,'error'=>'Idempotency lock failed'];}rewind($fh);$stored=json_decode((string)stream_get_contents($fh),true);if(is_array($stored)&&!empty($stored['leadId'])&&!empty($stored['time'])&&((int)$stored['time']+V2_LEAD_IDEMPOTENCY_TTL)>=time())return['ok'=>true,'duplicate'=>true,'leadId'=>(int)$stored['leadId'],'fh'=>$fh];return['ok'=>true,'duplicate'=>false,'fh'=>$fh];}
function lead_idempotency_store(array $lock,int $leadId){$fh=$lock['fh']??null;if(!is_resource($fh))return;rewind($fh);ftruncate($fh,0);fwrite($fh,json_encode(['leadId'=>$leadId,'time'=>time()],JSON_UNESCAPED_SLASHES));fflush($fh);flock($fh,LOCK_UN);fclose($fh);}
function lead_idempotency_release(array $lock){$fh=$lock['fh']??null;if(is_resource($fh)){flock($fh,LOCK_UN);fclose($fh);}}
function lead_bootstrap(){$docRoot=$_SERVER['DOCUMENT_ROOT']??'';$prolog=$docRoot.'/bitrix/modules/main/include/prolog_before.php';if($docRoot===''||!is_file($prolog))return['ok'=>false,'error'=>'Bitrix bootstrap not found'];require_once $prolog;if(!class_exists('Bitrix\\Main\\Loader'))return['ok'=>false,'error'=>'Bitrix Loader unavailable'];if(!\Bitrix\Main\Loader::includeModule('iblock'))return['ok'=>false,'error'=>'iblock module unavailable'];if(!class_exists('CIBlockElement'))return['ok'=>false,'error'=>'CIBlockElement unavailable'];return['ok'=>true];}
function lead_project_marker(){if(class_exists('CSiteParams')&&property_exists('CSiteParams','isAnytourOnline'))return \CSiteParams::$isAnytourOnline;return null;}
function lead_same_origin(){$origin=trim((string)($_SERVER['HTTP_ORIGIN']??''));if($origin==='')return true;$host=strtolower((string)($_SERVER['HTTP_HOST']??''));$originHost=strtolower((string)(parse_url($origin,PHP_URL_HOST)??''));return $host!==''&&$originHost===$host;}

function lead_build(array $data){
    $phone=lead_phone($data['phone']??'');$phoneDigits=strlen(preg_replace('/\D+/','',$phone));$tourId=lead_text($data['tourId']??'',200);$consent=lead_bool($data['consent']??false);$errors=[];
    if($phoneDigits<10||$phoneDigits>15)$errors['phone']='Valid phone is required';
    if($tourId==='')$errors['tourId']='tourId is required';
    if(!$consent)$errors['consent']='Consent is required';
    if($errors)return['errors'=>$errors];
    $lead=['name'=>lead_text($data['name']??'',120),'phone'=>$phone,'tourId'=>$tourId,'searchId'=>lead_text($data['searchId']??'',80),'hotel'=>lead_text($data['hotel']??'',240),'country'=>lead_text($data['country']??'',120),'region'=>lead_text($data['region']??'',160),'departure'=>lead_text($data['departure']??'',160),'date'=>lead_text($data['date']??'',40),'nights'=>isset($data['nights'])?(int)$data['nights']:null,'adults'=>isset($data['adults'])?(int)$data['adults']:null,'childs'=>isset($data['childs'])?(int)$data['childs']:null,'meal'=>lead_text($data['meal']??'',160),'roomType'=>lead_text($data['roomType']??'',240),'placement'=>lead_text($data['placement']??'',160),'operator'=>lead_text($data['operator']??'',160),'price'=>lead_money($data['price']??null),'currency'=>lead_text($data['currency']??'RUB',12)?:'RUB','flight'=>lead_text($data['flight']??'',2500),'flightPrice'=>lead_money($data['flightPrice']??null),'flightFuel'=>lead_money($data['flightFuel']??null),'comment'=>lead_text($data['comment']??'',1000),'page'=>lead_text($data['page']??'',500),'yclid'=>lead_text($data['yclid']??'',100),'yaclient'=>lead_text($data['yaclient']??'',100),'utm_source'=>lead_text($data['utm_source']??'',160),'utm_medium'=>lead_text($data['utm_medium']??'',160),'utm_campaign'=>lead_text($data['utm_campaign']??'',160),'utm_content'=>lead_text($data['utm_content']??'',160),'utm_term'=>lead_text($data['utm_term']??'',160),'consent'=>true];
    $priceSummary=v2_lead_price_summary($lead['price'],$lead['flightPrice']);$lead['basePrice']=$priceSummary['basePrice'];$lead['selectedPrice']=$priceSummary['selectedPrice'];$lead['priceDelta']=$priceSummary['delta'];
    $people='Взрослых: '.max(1,(int)$lead['adults']);if((int)$lead['childs']>0)$people.='; Детей: '.(int)$lead['childs'];
    $details=['Имя: '.$lead['name'],'Телефон: '.$lead['phone'],'Город вылета: '.$lead['departure'],'Страна: '.$lead['country'],'Туристы: '.$people,'Даты вылета: '.$lead['date'],'Количество ночей: '.(string)$lead['nights'],'V2 Tourvisor tourId: '.$lead['tourId'],'Согласие на обработку персональных данных: получено '.date('d.m.Y H:i:s')];
    if($lead['searchId'])$details[]='searchId: '.$lead['searchId'];if($lead['hotel'])$details[]='Отель: '.$lead['hotel'];if($lead['region'])$details[]='Регион: '.$lead['region'];if($lead['meal'])$details[]='Питание: '.$lead['meal'];if($lead['roomType'])$details[]='Номер: '.$lead['roomType'];if($lead['placement'])$details[]='Размещение: '.$lead['placement'];if($lead['operator'])$details[]='Оператор: '.$lead['operator'];if($lead['basePrice'])$details[]='Базовая цена тура: '.$lead['basePrice'].' '.$lead['currency'];if($lead['selectedPrice'])$details[]='Цена с выбранным рейсом: '.$lead['selectedPrice'].' '.$lead['currency'];if($lead['priceDelta']>0)$details[]='Доплата за выбранный рейс: '.$lead['priceDelta'].' '.$lead['currency'];elseif($lead['priceDelta']<0)$details[]='Изменение цены выбранного рейса: '.$lead['priceDelta'].' '.$lead['currency'];if($lead['flight'])$details[]='Выбранный перелёт: '.$lead['flight'];if($lead['flightFuel'])$details[]='Топливный сбор: '.$lead['flightFuel'].' '.$lead['currency'];if($lead['comment'])$details[]='Комментарий пользователя: '.$lead['comment'];if($lead['page'])$details[]='Страница: '.$lead['page'];
    $createdAt=date('d.m.Y H:i:s');$properties=['DATE'=>$createdAt,'NAME'=>$lead['name'],'PHONE'=>$lead['phone'],'COMMENTS'=>implode('; ',$details),'DEPARTURE'=>$lead['departure'],'PEOPLE'=>$people,'COUNTRY'=>$lead['country'],'STATUS'=>V2_LEAD_STATUS_ID,'SOURCE'=>V2_LEAD_SOURCE_ID,'YA_CLIENT'=>$lead['yaclient'],'YA_CLID'=>$lead['yclid'],'YA_UTM_SOURCE'=>$lead['utm_source'],'YA_UTM_MEDIUM'=>$lead['utm_medium'],'YA_UTM_CAMPAIGN'=>$lead['utm_campaign'],'YA_UTM_CONTENT'=>$lead['utm_content'],'YA_UTM_TERM'=>$lead['utm_term']];if($lead['meal'])$properties['MEAL']=$lead['meal'];if($lead['nights'])$properties['NIGHTS']=(string)$lead['nights'];
    return['lead'=>$lead,'properties'=>$properties,'element'=>['IBLOCK_ID'=>V2_LEAD_IBLOCK_ID,'IBLOCK_SECTION_ID'=>V2_LEAD_SECTION_ID,'PROPERTY_VALUES'=>$properties,'NAME'=>'Заявка от '.$createdAt,'ACTIVE'=>'Y']];
}

if($_SERVER['REQUEST_METHOD']==='GET')lead_out(['ok'=>true,'adapter'=>'v2-direct-bitrix-lead','version'=>2,'writes'=>true]);
if($_SERVER['REQUEST_METHOD']!=='POST')lead_out(['ok'=>false,'error'=>'Method not allowed'],405);
if(!lead_same_origin())lead_out(['ok'=>false,'error'=>'Origin not allowed'],403);
if(stripos((string)($_SERVER['CONTENT_TYPE']??''),'application/json')!==0)lead_out(['ok'=>false,'error'=>'JSON request required'],415);
if((int)($_SERVER['CONTENT_LENGTH']??0)>V2_LEAD_MAX_BODY)lead_out(['ok'=>false,'error'=>'Request too large'],413);

$boot=lead_bootstrap();if(empty($boot['ok']))lead_out(['ok'=>false,'error'=>$boot['error']??'Bitrix bootstrap failed'],500);
$raw=file_get_contents('php://input');if(strlen((string)$raw)>V2_LEAD_MAX_BODY)lead_out(['ok'=>false,'error'=>'Request too large'],413);$data=json_decode((string)$raw,true);if(!is_array($data))lead_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$_REQUEST['sessid']=lead_text($data['sessid']??'',128);$_POST['sessid']=$_REQUEST['sessid'];
if(!function_exists('check_bitrix_sessid')||!check_bitrix_sessid())lead_out(['ok'=>false,'error'=>'Session validation failed'],403);
$built=lead_build($data);if(!empty($built['errors']))lead_out(['ok'=>false,'error'=>'Validation failed','fields'=>$built['errors']],422);
$key=lead_idempotency_key($built['lead']);$lock=lead_idempotency_lock($key);if(empty($lock['ok']))lead_out(['ok'=>false,'error'=>$lock['error']??'Idempotency failure'],500);if(!empty($lock['duplicate'])){lead_idempotency_release($lock);lead_out(['ok'=>true,'mode'=>'live','writes'=>false,'duplicate'=>true,'leadId'=>(int)$lock['leadId'],'source'=>V2_LEAD_SOURCE_ID]);}
$projectMarker=lead_project_marker();if($projectMarker!==null)$built['element']['PROPERTY_VALUES']['IS_ANYTOUR_ONLINE']=$projectMarker;
$el=new \CIBlockElement();$leadId=$el->Add($built['element']);if(!$leadId){lead_idempotency_release($lock);lead_out(['ok'=>false,'error'=>'Bitrix lead insert failed'],500);}lead_idempotency_store($lock,(int)$leadId);
lead_out(['ok'=>true,'mode'=>'live','writes'=>true,'duplicate'=>false,'leadId'=>(int)$leadId,'source'=>V2_LEAD_SOURCE_ID,'isAnyTourOnline'=>$projectMarker]);
