<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<link href="<?=$templateFolder. '/styles/slick.css?v1.003'?>" rel="stylesheet">
<?
$arResult["AJAX_PATH"] = $templateFolder."/ajax.php";

if (count($arResult["ITEMS"])>0)
{?>
	<h2><?=$arResult["TITLE"]?></h2>

	<div id="hotWrapper">
		<?foreach($arResult["ITEMS"] as $i=> $tour){?>
		<div class="hotItemWrapper <?if ($i>5){?>mobileHid<?}?>">
			<div class="hotItem" data-tid="<?=$tour["ID"]?>" data-hid="<?=$tour["HID"]?>">
				<div class="hotPicWrap">
					<div class="hotPic" style="background-image:url(<?=$tour["PIC"]?>)"></div>
					<div class="hotDiscount"><?=$tour["DISCOUNT"]?></div>
				</div>
				<div class="hotInfo">
					<div class="hotStart">
						<?for($i=1;$i<=$tour["STAR"];$i++){?>
						<span class="gStar"></span>
						<?}?>
					</div>
					<div class="hotHotelName">
						<?=$tour["HOTEL"]?>
					</div>	
					<div class="hotCountryRestort">
						<?=$tour["RESORT"]?>, <?=$tour["COUNTRY"]?> 
					</div>	
					<div class="hotDateNights">
						<?=$tour["DATE"]?>, <?=$tour["NIGHTS"]?> ночей
					</div>
					<div class="hotFrom">
						<?=$tour["FROM"]?>
					</div>
					<div class="hotPriceWrap">
						<div class="hotPriceOld">
							<?=$tour["OLD_PRICE"]?> руб
						</div>
						<div class="hotPrice">
							<span><?=$tour["PRICE"]?></span> руб
							<div class="priceIcon"></div>
						</div>
					</div>
				</div>	
			</div>	
		</div>
		<?}?>
	</div>	
<?}?>	
<script>
var hotAjaxUrl = "<?=$arResult["AJAX_PATH"]?>";
</script>