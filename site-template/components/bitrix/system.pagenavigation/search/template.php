<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

if(!$arResult["NavShowAlways"])
{
	if ($arResult["NavRecordCount"] == 0 || ($arResult["NavPageCount"] == 1 && $arResult["NavShowAll"] == false))
		return;
}
?>
<div class="modern-page-navigation">
<?

$strNavQueryString = ($arResult["NavQueryString"] != "" ? $arResult["NavQueryString"]."&amp;" : "");
$strNavQueryStringFull = ($arResult["NavQueryString"] != "" ? "?".$arResult["NavQueryString"] : "");
?>

	<?/*?><span class="modern-page-title"><?=GetMessage("pages")?></span><?*/?>
<?
if($arResult["bDescPageNumbering"] === true):

else:
	$bFirst = true;

	if ($arResult["NavPageNomer"] > 1):
		if($arResult["bSavePage"]):
?>
			<a class="modern-page-previous" href="#" onclick="getCurrentResult(<?=($arResult["NavPageNomer"]-1)?>,false); return false;"><?=GetMessage("nav_prev")?></a>
<?
		else:
			if ($arResult["NavPageNomer"] > 2):
?>
			<a class="modern-page-previous" href="#" onclick="getCurrentResult(<?=($arResult["NavPageNomer"]-1)?>,false); return false;"><?=GetMessage("nav_prev")?></a>
<?
			else:
?>
			<a class="modern-page-previous" href="#" onclick="getCurrentResult(<?=$strNavQueryStringFull?>,false); return false;"><?=GetMessage("nav_prev")?></a>
<?
			endif;
		
		endif;
		
		if ($arResult["nStartPage"] > 1):
			$bFirst = false;
			if($arResult["bSavePage"]):
?>
			<a class="modern-page-first" href="#" onclick="getCurrentResult(1,false); return false;">1</a>
<?
			else:
?>
			<a class="modern-page-first" href="#" onclick="getCurrentResult(<?=$strNavQueryStringFull?>,false); return false;">1</a>
<?
			endif;
			if ($arResult["nStartPage"] > 2):
/*?>
			<span class="modern-page-dots">...</span>
<?*/
?>
			<a class="modern-page-dots" href="#" onclick="getCurrentResult(<?=round($arResult["nStartPage"] / 2)?>,false); return false;">...</a>
<?
			endif;
		endif;
	endif;

	do
	{
		if ($arResult["nStartPage"] == $arResult["NavPageNomer"]):
?>
		<span class="<?=($bFirst ? "modern-page-first " : "")?>modern-page-current"><?=$arResult["nStartPage"]?></span>
<?
		elseif($arResult["nStartPage"] == 1 && $arResult["bSavePage"] == false):
?>
		<a href="#" class="<?=($bFirst ? "modern-page-first" : "modern-page-simple")?>" onclick="getCurrentResult(<?=$strNavQueryStringFull?>,false); return false;"><?=$arResult["nStartPage"]?></a>
<?
		else:
?>
		<a href="#" <?
			?> class="<?=($bFirst ? "modern-page-first" : "modern-page-simple")?>" onclick="getCurrentResult(<?=$arResult["nStartPage"]?>,false); return false;"><?=$arResult["nStartPage"]?></a>
<?
		endif;
		$arResult["nStartPage"]++;
		$bFirst = false;
	} while($arResult["nStartPage"] <= $arResult["nEndPage"]);
	
	if($arResult["NavPageNomer"] < $arResult["NavPageCount"]):
		if ($arResult["nEndPage"] < $arResult["NavPageCount"]):
			if ($arResult["nEndPage"] < ($arResult["NavPageCount"] - 1)):
/*?>
		<span class="modern-page-dots">...</span>
<?*/
?>
		<a class="modern-page-dots" href="#" onclick="getCurrentResult(<?=round($arResult["nEndPage"] + ($arResult["NavPageCount"] - $arResult["nEndPage"]) / 2)?>,false); return false;">...</a>
<?
			endif;
?>
		<a href="#" onclick="getCurrentResult(<?=$arResult["NavPageCount"]?>,false); return false;"><?=$arResult["NavPageCount"]?></a>
<?
		endif;
?>
		<a class="modern-page-next" href="#" onclick="getCurrentResult(<?=($arResult["NavPageNomer"]+1)?>,false); return false;"><?=GetMessage("nav_next")?></a>
<?
	endif;
endif;
/*
if ($arResult["bShowAll"]):
	if ($arResult["NavShowAll"]):
?>
		<a class="modern-page-pagen" href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>SHOWALL_<?=$arResult["NavNum"]?>=0"><?=GetMessage("nav_paged")?></a>
<?
	else:
?>
		<a class="modern-page-all" href="<?=$arResult["sUrlPath"]?>?<?=$strNavQueryString?>SHOWALL_<?=$arResult["NavNum"]?>=1"><?=GetMessage("nav_all")?></a>
<?
	endif;
endif*/
?>
</div>