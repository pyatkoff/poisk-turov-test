<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<?
$arResult["AJAX_PATH"] = $templateFolder."/ajax.php";

if (count($arResult["ITEMS"])>0)
{?>
	<div class="countryMonthWrap">
	<?foreach($arResult["ITEMS"] as $month){
		?>
		<a class="countryMonthCountry" href="/poisk-turov/?country=<?=$arResult["COUNTRY"]?>&date_from=<?=$month["DATES"]["FROM"]?>&date_till=<?=$month["DATES"]["TO"]?>" target="_blank">Туры <?=$arParams["TO_COUNTRY"]?> <?=$month["NAME"]?> от <b><?=number_format($month["MIN_PRICE"],0,"."," ")?></b> руб</a>
	<?}?>	
	</div>


	<?/*?><div class="countryMonthWrap">
	<?foreach($arResult["ITEMS"] as $month){?>
		<div class="countryMonthCountry">Туры <?=$arParams["TO_COUNTRY"]?> <?=$month["NAME"]?> от <?=number_format($month["MIN_PRICE"],0,"."," ")?> руб</div>
		<div class="countryMonthList">
		<?foreach($month["STARS"] as $star=>$tours){?>
			<div class="countryMonthStars">Туры в отели <?=$star?>* от <?=$tours[0]["PRICE"]?> руб</div>
			<div class="contryMonthWrapper">
			<?foreach($tours as $tour){
				if($tour["ACTIVE"])
				{
				?>
				<div class="countryTourWrapper">
					<div class="countryTour" data-tid="<?=$tour["ID"]?>" data-hid="<?=$tour["HID"]?>">
						<div class="countryTourPicWrap">
							<div class="countryTourPic" style="background-image:url(<?=$tour["PIC"]?>)"></div>
						</div>
						<div class="countryTourInfo">
							<div class="countryTourStars">
								<?for($i=1;$i<=$tour["STAR"];$i++){?>
								<span class="gStar"></span>
								<?}?>
							</div>
							<div class="countryTourHotelName">
								<?=$tour["HOTEL"]?>
							</div>	
							<div class="countryTourCountryRestort">
								<?=$tour["RESORT"]?>, <?=$tour["COUNTRY"]?> 
							</div>	
							
							<div class="countryTourPriceWrap">
								<div class="countryDateNights">
									<div class="countryTourDateNights">
										<?=$tour["DATE"]?>, <?=$tour["NIGHTS"]?> ночей
									</div>
									<div class="countryTourFrom">
										<?=$tour["FROM"]?>
									</div>
								</div>
								<div class="countryTourPrice">
									<span><?=$tour["PRICE"]?></span> руб
								</div>
							</div>
						</div>	
					</div>	
				</div>
				<?
				}
			}?>	
			</div>	
		<?}?>
		</div>		
	<?}?>
	</div>
	<?*/?>
<?}?>	
