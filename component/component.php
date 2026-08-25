<?
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/php_interface/tv_api/tvapi.php');

global $CACHE_MANAGER;
$arResult = array();
/*************************************************************************
	Processing of received parameters
*************************************************************************/
if(!isset($arParams["CACHE_TIME"]))
	$arParams["CACHE_TIME"] = 0;

$arParams['CACHE_GROUPS'] = trim($arParams['CACHE_GROUPS']);
if ('N' != $arParams['CACHE_GROUPS'])
	$arParams['CACHE_GROUPS'] = 'Y';

$arResult["HOTEL_MODE"] = ($arParams["HOTEL_MODE"]=="Y") ? true : false;
$arResult["HOTEL"] 		= ($arParams["HOTEL"]!="") ? $arParams["HOTEL"] : "";
$arResult['COUNTRY'] 	= ($arParams['COUNTRY']!="") ? $arParams['COUNTRY'] : "";
$code       = (!empty($arParams["TG_CODE"]) && $arParams["TG_CODE"]!="") ? $arParams["TG_CODE"] : false;
$formCode   = (!empty($arParams["FORM_CODE"]) && $arParams["FORM_CODE"]!="") ? $arParams["FORM_CODE"] : false;
$arResult["AUTO_START"] = "n";
$dataFilter = array();

$dateNow = new \Bitrix\Main\Type\Date();

if($code)
{
   
    require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/php_interface/telegrambot/telegramsearchclass.php');
    $claim = TelegramSearchApi::getClaimByCode($code);
    if(count($claim)==0)
    {
        require_once($_SERVER["DOCUMENT_ROOT"].'/bitrix/php_interface/telegrambot/telegramsearchonlineclass.php');
        $claim = TelegramSearchOnlineApi::getClaimByCode($code);
    }    
    if($claim["UF_CITY"])
        $dataFilter['FROM']    = $claim["UF_CITY"];
    if($claim["UF_COUNTRY"])
        $dataFilter['COUNTRY'] = $claim["UF_COUNTRY"];
    if($claim["UF_ADULTS"])    
        $dataFilter['ADULTS']  = $claim["UF_ADULTS"];
    if($claim["UF_CHILD"])    
    {
        $dataFilter['KIDS'] = $claim["UF_CHILD"];
        if($claim["UF_AGE"])
        {
            $age = explode(",",$claim["UF_AGE"]);
            foreach($age as $i=>$val)
            {
                $dataFilter['CHILD_YEAR'.($i+1)] = intval($val);
            }
        }     
    }   

    if($claim["UF_STARS"])    
        $dataFilter['STARS']  = $claim["UF_STARS"];
    if($claim["UF_MEAL"])    
        $dataFilter['MEAL']  = $claim["UF_MEAL"];    
    if($claim["UF_NIGHTS"])    
    {
        $nights = explode("-",$claim["UF_NIGHTS"]);
        if(is_array($nights) && count($nights)==1)
            $nights[1] = $nights[0];
        $dataFilter['NIGHTS_FROM'] = $nights[0];
        $dataFilter['NIGHTS_TO']   = $nights[1];
       
    }   
    if($claim["UF_DATE_DEPART"])    
    {
        $date = $claim["UF_DATE_DEPART"];
        $dateObjPlus = new  \Bitrix\Main\Type\DateTime($date);
		$dateObjPlus->add("3 day");
		$dateObjMinus = new  \Bitrix\Main\Type\DateTime($date);
		$dateObjMinus->add("-3 day");
		$dateNow = new \Bitrix\Main\Type\Date();

        if($dateNow->getTimestamp() < $dateObjPlus->getTimestamp())
        {
            if($dateNow->getTimestamp() > $dateObjMinus->getTimestamp())
			    $dateObjMinus = $dateNow;

            $dataFilter['DATE_FROM'] = $dateObjMinus->format("Y-m-d");
            $dataFilter['DATE_TO']   = $dateObjPlus->format("Y-m-d");    
        }
    }    
    if($claim["UF_STARS"])    
    {
        $dataFilter['STARS'] = $claim["UF_STARS"];
    }   
    if($claim["UF_MEAL"])    
    {
        $dataFilter['MEAL'] = $claim["UF_MEAL"];
    } 
    $arResult["AUTO_START"] = "y";
  
}
elseif($formCode)
{
    //prf($formCode);
    $dateNow = new \Bitrix\Main\Type\Date();
    $dateFrom = false;
	$savedParams = TvApi::getSearchForm($formCode);
	if (isset($savedParams['country']))
        $dataFilter['COUNTRY'] =$savedParams['country'];
	if (isset($savedParams['departure']))
        $dataFilter['FROM'] =$savedParams['departure'];
	if (isset($savedParams['resort']))
        $dataFilter['RESORT'] = $savedParams['resort'];
	if (isset($savedParams['adults']))
        $dataFilter['ADULTS'] =$savedParams['adults'];
    if (isset($savedParams['child']))
        $dataFilter['KIDS'] =$savedParams['child'];
	if (isset($savedParams['childage1']) || $savedParams['childage1']===0)
        $dataFilter['CHILD_YEAR1'] =$savedParams['childage1'];
    if (isset($savedParams['childage2']) || $savedParams['childage2']===0)
        $dataFilter['CHILD_YEAR2'] =$savedParams['childage2'];
    if (isset($savedParams['childage3']) || $savedParams['childage3']===0)
        $dataFilter['CHILD_YEAR3'] =$savedParams['childage3'];
	if (isset($savedParams['nightsfrom']))
        $dataFilter['NIGHTS_FROM'] = $savedParams['nightsfrom'];	
    if (isset($savedParams['nightsto']))
        $dataFilter['NIGHTS_TO'] = $savedParams['nightsto'];	
	if (isset($savedParams['datefrom']))
	{	
		$dateFrom = new \Bitrix\Main\Type\Date($savedParams['datefrom']);
        if($dateFrom<$dateNow)
            $dateFrom = $dateNow;
        $dataFilter['DATE_FROM'] =$dateFrom->format("Y-m-d");
	}	
    if (isset($savedParams['dateto']))
	{	
		$dateTo = new  \Bitrix\Main\Type\Date($savedParams['dateto']);
        if($dateFrom && $dateTo<$dateFrom)
            $dateTo = $dateFrom;
        $dataFilter['DATE_TO'] =$dateTo->format("Y-m-d");	
	}

	if (isset($savedParams['pricefrom']))
        $dataFilter['PRICE_FROM'] =$savedParams['pricefrom'];
    if (isset($savedParams['priceto']))
        $dataFilter['PRICE_TILL'] =$savedParams['priceto'];	
	if (isset($savedParams['stars']))
		$dataFilter['STARS'] = $savedParams["stars"];
	if (isset($savedParams['meal']))
		$dataFilter['MEAL'] = $savedParams["meal"];
	if (is_array($savedParams['hotels']) && count($savedParams['hotels'])>0)
		$dataFilter['HOTELS'] = $savedParams['hotels'];
	
	$arResult["AUTO_START"] = "y";	
}	
else
{

    if (isset($_REQUEST['from']))
    {
        $dataFilter['FROM'] =htmlspecialchars($_REQUEST['from']);
        $arResult["AUTO_START"] = "y";
    }	
    elseif (isset($arParams['FROM']))
        $dataFilter['FROM'] = $arParams['FROM'];
        
    if (isset($_REQUEST['country']))
        $dataFilter['COUNTRY'] =htmlspecialchars($_REQUEST['country']);	
    elseif (isset($arParams['COUNTRY']) && $arParams['COUNTRY']!=false)
        $dataFilter['COUNTRY'] = $arParams['COUNTRY'];

    if (isset($_REQUEST['resort']))
        $dataFilter['RESORT'] = $_REQUEST['resort'];    
    elseif (isset($arParams['RESORT']))
        $dataFilter['RESORT'] = $arParams['RESORT'];

    if (isset($_REQUEST['hotel']))
        $dataFilter['HOTEL'] =htmlspecialchars($_REQUEST['hotel']);	   
    elseif (isset($arParams['HOTEL']))
        $dataFilter['HOTEL'] = $arParams['HOTEL'];

    if (isset($_REQUEST['count_people']))
        $dataFilter['ADULTS'] =htmlspecialchars($_REQUEST['count_people']);
    if (isset($_REQUEST['stars']))
        $dataFilter['STARS'] =htmlspecialchars($_REQUEST['stars']);
    if (isset($_REQUEST['meal']))
        $dataFilter['MEAL'] =htmlspecialchars($_REQUEST['meal']);
    if (isset($_REQUEST['count_child']))
        $dataFilter['KIDS'] =htmlspecialchars($_REQUEST['count_child']);
    if (isset($_REQUEST['days_from']))
        $dataFilter['NIGHTS_FROM'] =htmlspecialchars($_REQUEST['days_from']);	
    if (isset($_REQUEST['days_till']))
        $dataFilter['NIGHTS_TO'] =htmlspecialchars($_REQUEST['days_till']);	
    if (isset($_REQUEST['date_from']))
    {
        $dateTemp = htmlspecialchars($_REQUEST['date_from']);	
        $dateFrom = new \Bitrix\Main\Type\Date();
        $dateFrom->add("1 Day");
        try
        {
            $dateFromNew = new \Bitrix\Main\Type\Date($dateTemp,"Y-m-d");
            if($dateFromNew>$dateNow)
                $dateFrom = $dateFromNew;
        }
        catch (Exception $e) {
        }
        $dataFilter['DATE_FROM'] = $dateFrom->format("Y-m-d");
    }
    if (isset($_REQUEST['date_till']))
    {
        $dateTemp = htmlspecialchars($_REQUEST['date_till']);	
        $dateTo = new \Bitrix\Main\Type\Date();
        $dateTo->add("14 Day");
        try
        {
            $dateToNew = new \Bitrix\Main\Type\Date($dateTemp,"Y-m-d");
            if($dateToNew>$dateNow)
                $dateTo = $dateToNew;
        }
        catch (Exception $e) {
        }
       $dataFilter['DATE_TO'] = $dateTo->format("Y-m-d");

    }

    if (isset($_REQUEST['child_year1']) || $_REQUEST['child_year1']===0)
        $dataFilter['CHILD_YEAR1'] =htmlspecialchars($_REQUEST['child_year1']);
    if (isset($_REQUEST['child_year2']) || $_REQUEST['child_year2']===0)
        $dataFilter['CHILD_YEAR2'] =htmlspecialchars($_REQUEST['child_year2']);
    if (isset($_REQUEST['child_year3']) || $_REQUEST['child_year3']===0)
        $dataFilter['CHILD_YEAR3'] =htmlspecialchars($_REQUEST['child_year3']);
    //  prf($dataFilter);
}	

$arResult["IS_ADMIN"] = $USER->IsAuthorized(); 
/*************************************************************************
			Work with cache
*************************************************************************/
if($this->StartResultCache(false, ($arParams["CACHE_GROUPS"]==="N"? false: $USER->GetGroups())))
{
	$arResult["FORM_PARAMS"] = TvApi::prepareForm($dataFilter);
    //prf($arResult["FORM_PARAMS"]);
	
    $arResult["AJAX_PATH"] = $this->GetPath()."/ajax.php";    
    $resultCacheKeys = array();
    $this->SetResultCacheKeys($resultCacheKeys);
    $this->IncludeComponentTemplate();
    
}

?>