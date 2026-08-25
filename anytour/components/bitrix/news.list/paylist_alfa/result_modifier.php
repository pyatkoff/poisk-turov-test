<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$payUrl  = "https://anytour.online/pay_t/?link=";

$signer = new \Bitrix\Main\Security\Sign\Signer;


foreach($arResult["ITEMS"] as &$arItem){
	
	
	$arr = array("ORDER_ID"=>$arItem["ID"]);
	if ($arItem["PROPERTIES"]["IS_ALFA_ANYTOUR"]["VALUE"]!="")
		$arr["IS_ALFA_ANYTOUR"] = true;
	$arr = base64_encode(serialize($arr));
	//echo $params;
	try
	{
		$link = $signer->sign($arr, 'anexpay.salt');
		$link = $payUrl.$link;
		$arItem["PAY_LINK"] = $link ;
	}	
	catch (\Bitrix\Main\Security\Sign\BadSignatureException $e)
	{
		
	}
}	

?>